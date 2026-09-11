<?php
/**
 * A prototype: read places out of a downloaded OpenStreetMap extract instead of asking Overpass.
 *
 * Why this exists. The importer depends on a free public API run by volunteers, and one kind in
 * seven times out at four cities. A downloaded extract has no rate limit, no queue, no mirror
 * having a bad afternoon, and the same licence: Geofabrik and BBBike publish regional extracts of
 * the OSM database under ODbL, free, with no key and no account. Attribution is already handled,
 * because the rows it produces are the same canonical rows with the same data_source.
 *
 * What this is NOT: it is not wired into production and it does not replace the working importer.
 * It reads a file, it prints or posts the same canonical rows, and it is proved against a fixture.
 * Swapping the production path over is a decision to take when a real extract has been run through
 * it, not before.
 *
 * Format: uncompressed `.osm` XML, which is what `bzip2 -d` gives you from a Geofabrik `.osm.bz2`.
 * PBF is smaller and needs a binary parser this does not have; XML is streamed with XMLReader, so a
 * file larger than memory is fine.
 *
 * Usage:
 *   php scripts/osm_extract.php --file=portugal.osm --city=lisbon-portugal --km=12 [--json]
 *   php scripts/osm_extract.php --file=portugal.osm --city=lisbon-portugal \
 *       --site=https://ruinmytrip.com --key=… --limit-per-kind=25
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

$file = (string) ($opts['file'] ?? '');
$city = (string) ($opts['city'] ?? '');
$km   = (float) ($opts['km'] ?? 12);
$perKind = max(1, (int) ($opts['limit-per-kind'] ?? 25));
$asJson = isset($opts['json']);
$site = rtrim((string) ($opts['site'] ?? ''), '/');
$key  = (string) ($opts['key'] ?? '');

if ($file === '' || $city === '') { fwrite(STDERR, "--file and --city are required\n"); exit(2); }
if (!is_file($file)) { fwrite(STDERR, "no such file: $file\n"); exit(2); }

$dest = q_one('SELECT * FROM destinations WHERE slug = ?', [$city]);
if (!$dest) { fwrite(STDERR, "no such city: $city\n"); exit(2); }
if ($dest['lat'] === null || $dest['lng'] === null) {
    fwrite(STDERR, "that city has no coordinates, so there is nowhere to look\n");
    exit(2);
}
[$south, $west, $north, $east] = rmt_osm_bbox((float) $dest['lat'], (float) $dest['lng'], $km);

/* Streamed, not loaded. A country extract is gigabytes and the interesting part of it is a few
   thousand nodes inside one bounding box, so this walks the file once and keeps only those. Ways
   are kept when the extract carries a center, which Geofabrik does not add, so this prototype
   reads nodes: enough to prove the path, and the gap is written down rather than hidden. */
$reader = new XMLReader();
if (!$reader->open($file)) { fwrite(STDERR, "could not open $file\n"); exit(1); }

$rows = [];
$aliases = [];
$perKindCount = [];
$seen = 0;
$kept = 0;

while ($reader->read()) {
    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'node') continue;
    $lat = (float) $reader->getAttribute('lat');
    $lng = (float) $reader->getAttribute('lon');
    $id  = (int) $reader->getAttribute('id');
    $seen++;
    if ($lat < $south || $lat > $north || $lng < $west || $lng > $east) { $reader->next(); continue; }
    if ($reader->isEmptyElement) continue;

    $xml = $reader->readOuterXml();
    $reader->next();
    if ($xml === '') continue;
    $el = @simplexml_load_string($xml);
    if (!$el) continue;

    $tags = [];
    foreach ($el->tag as $t) $tags[(string) $t['k']] = (string) $t['v'];
    if (trim((string) ($tags['name'] ?? '')) === '') continue;

    $c = rmt_osm_to_place(['type' => 'node', 'id' => $id, 'lat' => $lat, 'lon' => $lng, 'tags' => $tags]);
    if ($c['row'] === null) continue;

    $kind = (string) $c['row']['source_kind'];
    if (($perKindCount[$kind] ?? 0) >= $perKind) continue;
    $perKindCount[$kind] = ($perKindCount[$kind] ?? 0) + 1;

    $rows[] = $c['row'];
    $aliases[(string) $c['row']['source_ref']] = $c['aliases'];
    $kept++;
}
$reader->close();

fprintf(STDERR, "read %d nodes, kept %d places in %s\n", $seen, $kept, (string) $dest['name']);
arsort($perKindCount);
foreach (array_slice($perKindCount, 0, 12, true) as $k => $n) fprintf(STDERR, "  %-16s %d\n", $k, $n);

if (!$rows) exit(0);

if ($asJson || $site === '') {
    echo json_encode(['rows' => $rows, 'aliases' => $aliases],
                     JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}

/* Posted through exactly the same door as everything else: the field whitelist, the registered
   provider check, the quarantine rules and the deduplication all still apply. An extract is a
   different way to FETCH, never a different way to write. */
foreach (array_chunk($rows, 150) as $i => $chunk) {
    $body = json_encode(['rows' => $chunk, 'aliases' => $aliases],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $url = $site . '/cron/places?' . http_build_query(['key' => $key, 'op' => 'ingest', 'city' => $city]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 180,
    ]);
    $ca = (string) ($GLOBALS['config']['ca_bundle'] ?? (getenv('CURL_CA_BUNDLE') ?: ''));
    if ($ca !== '' && is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $out = curl_exec($ch);
    curl_close($ch);
    printf("batch %d: %s", $i + 1, (string) $out);
    sleep(2);
}
