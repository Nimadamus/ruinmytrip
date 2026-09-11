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
$batch = max(20, min(200, (int) ($opts['batch'] ?? 120)));
$pause = max(5, (int) ($opts['pause'] ?? 20));
$dry   = isset($opts['dry']);

if ($site === '' || $key === '' || $city === '') {
    fwrite(STDERR, "--site, --key and --city are required\n");
    exit(2);
}

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
        if ($res['error'] !== null) {
            echo '  provider: ' . $res['error'] . "\n";
            sleep($pause * 2);
            continue;
        }

        /* Only rows that actually gained an hours value are sent on. An object whose record says
           nothing about opening is not worth a write, and posting it back would be churn. */
        $rows = []; $aliases = [];
        foreach ($res['elements'] as $el) {
            $c = rmt_osm_to_place($el);
            if ($c['row'] === null) continue;
            if (trim((string) ($c['row']['opening_hours'] ?? '')) === '') continue;
            if (rmt_osm_hours_parse((string) $c['row']['opening_hours']) === null) continue;
            $rows[] = $c['row'];
            $aliases[(string) $c['row']['source_ref']] = $c['aliases'];
        }
        $carried += count($rows);
        echo '  asked for ' . count($chunk) . ', ' . count($res['elements']) . ' came back, '
           . count($rows) . " carry hours we trust\n";

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
    printf("  %s: %d request(s), %d place(s) carried hours\n", $slug, $asked, $carried);
}
