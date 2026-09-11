<?php
/**
 * Give already-imported places their opening hours, without re-scanning a city.
 *
 * The first four cities were imported before the hours parser worked, so a few hundred places
 * carry an address and a point and nothing about when the door is open. The naive fix is to run
 * the city kit again, which makes a volunteer run server search a bounding box fifteen more times
 * for rows it has already given us.
 *
 * This asks the other way round: the site says which OSM objects it holds with no hours, the
 * provider is handed that list of ids, and the answer goes back in through the ordinary ingest
 * door, where the same whitelist, the same quarantine rules and the same deduplication apply.
 * A lookup by id is the cheapest question there is to ask Overpass.
 *
 * Nothing here invents an hour. A place whose OSM record carries no `opening_hours`, or carries
 * one in a form the parser refuses, simply stays as it was.
 *
 * Usage:
 *   php scripts/backfill_hours.php --site=https://ruinmytrip.com --key=… --city=lisbon-portugal
 *   php scripts/backfill_hours.php … --city=all --batch=120 --pause=20
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) continue;
    $bit = substr($arg, 2);
    [$k, $v] = str_contains($bit, '=') ? explode('=', $bit, 2) : [$bit, '1'];
    $opts[$k] = $v;
}
$site  = rtrim((string) ($opts['site'] ?? ''), '/');
$key   = (string) ($opts['key'] ?? '');
$city  = (string) ($opts['city'] ?? '');
$batch = max(20, min(200, (int) ($opts['batch'] ?? 60)));
$pause = max(5, (int) ($opts['pause'] ?? 20));
$dry   = isset($opts['dry']);

if ($site === '' || $key === '' || $city === '') {
    fwrite(STDERR, "--site, --key and --city are required\n");
    exit(2);
}

/* One at a time. Two of these running together would double what a free, volunteer run service is
   asked for, and the second one would re-fetch exactly what the first is already fetching,
   because both start from the same "which places have no hours" answer. The lock is released when
   the process ends, however it ends, including being killed. */
$lock = fopen(sys_get_temp_dir() . '/rmt_backfill_hours.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "another backfill is already running\n");
    exit(3);
}

/* Chunked, resumable and observable are the three properties that matter for a job nobody is
   watching. Chunked and resumable come from the shape of it: the site is asked what is still
   missing on every run, so an interrupted run leaves no state to clean up and a restart simply
   asks a shorter question. Observable is this line and the peak figure printed per city. */
fprintf(STDERR, "backfill: batch=%d pause=%ds php memory_limit=%s\n", $batch, $pause, ini_get('memory_limit'));

/** One GET against the site's own cron door. */
function rmt_bh_get(string $url): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
    $ca = (string) ($GLOBALS['config']['ca_bundle'] ?? (getenv('CURL_CA_BUNDLE') ?: ''));
    if ($ca !== '' && is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $out = (string) curl_exec($ch);
    curl_close($ch);
    return $out;
}

$cities = $city === 'all'
    ? ['lisbon-portugal', 'paris-france', 'rome-italy', 'barcelona-spain', 'london-uk',
       'tokyo-japan', 'new-york-city-usa', 'las-vegas-usa', 'amsterdam-netherlands',
       'bangkok-thailand']
    : [$city];

foreach ($cities as $slug) {
    echo "== $slug\n";
    $raw = rmt_bh_get($site . '/cron/places?' . http_build_query(
        ['key' => $key, 'op' => 'needs_hours', 'city' => $slug, 'limit' => 400]));
    $want = json_decode($raw, true);
    $refs = is_array($want) ? ($want['refs'] ?? []) : [];
    if (!$refs) { echo "  nothing without hours\n"; continue; }
    echo '  ' . count($refs) . " place(s) with no hours yet\n";

    $wrote = 0; $asked = 0; $carried = 0;
    foreach (array_chunk($refs, $batch) as $chunk) {
        $query = rmt_osm_query_refs($chunk);
        if ($query === '') continue;
        $asked++;
        $res = rmt_osm_fetch($query, 45);
        /* A batch that fails takes its whole chunk with it, and the next run would ask for the
           same ids and fail the same way. Halved once and asked again after a longer rest: a
           shorter list is a cheaper question, and two smaller asks spread over a minute are
           gentler on the provider than a hundred rows nobody ever gets. */
        if ($res['error'] !== null && count($chunk) > 20) {
            echo '  provider: ' . $res['error'] . ", halving and asking again\n";
            sleep($pause * 2);
            $half = (int) ceil(count($chunk) / 2);
            $chunk = array_slice($chunk, 0, $half);
            $res = rmt_osm_fetch(rmt_osm_query_refs($chunk), 45);
        }
        if ($res['error'] !== null) {
            echo '  provider: ' . $res['error'] . ", left for the next run\n";
            sleep($pause * 2);
            continue;
        }

        /* Only rows that actually gained an hours value are sent on. An object whose record says
           nothing about opening is not worth a write, and posting it back would be churn. */
        $rows = []; $aliases = []; $stated = 0; $refused = 0;
        foreach ($res['elements'] as $el) {
            $c = rmt_osm_to_place($el);
            if ($c['row'] === null) continue;
            if (trim((string) ($c['row']['opening_hours'] ?? '')) === '') continue;
            $stated++;
            /* Counted separately on purpose. "No hours" and "hours in a form we will not guess at"
               are different facts about this pipeline, and only one of them is ours to fix. */
            if (rmt_osm_hours_parse((string) $c['row']['opening_hours']) === null) { $refused++; continue; }
            $rows[] = $c['row'];
            $aliases[(string) $c['row']['source_ref']] = $c['aliases'];
        }
        $carried += count($rows);
        printf("  asked for %d, %d came back, %d state hours, %d refused, %d kept\n",
               count($chunk), count($res['elements']), $stated, $refused, count($rows));

        if ($rows && !$dry) {
            $body = json_encode(['rows' => $rows, 'aliases' => $aliases],
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $url = $site . '/cron/places?' . http_build_query(
                ['key' => $key, 'op' => 'ingest', 'city' => $slug]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 180,
            ]);
            $ca = (string) ($GLOBALS['config']['ca_bundle'] ?? (getenv('CURL_CA_BUNDLE') ?: ''));
            if ($ca !== '' && is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
            echo '  ingest: ' . trim((string) curl_exec($ch)) . "\n";
            curl_close($ch);
            $wrote += count($rows);
        }
        /* Spread out. The provider is free, run by volunteers, and nothing here is urgent. */
        sleep($pause);
    }
    printf("  %s: %d request(s), %d place(s) carried hours, peak %.1f MB\n",
           $slug, $asked, $carried, memory_get_peak_usage(true) / 1048576);
}
