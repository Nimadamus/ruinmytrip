<?php
/**
 * Fetch places from the provider here, and post the canonical rows to a running site.
 *
 * Why this exists: fetching needs an internet connection, writing needs the database credentials,
 * and those are not always in the same place. The public Overpass instance queues per address, so
 * from a shared cloud address a request can sit behind every other tenant of the platform until it
 * times out, which is exactly what a production import did twice. From a laptop the same question
 * answers in under three seconds.
 *
 * It is not a wider door into the database. The rows go through the same import as everything else
 * on the far side: the field whitelist still drops anything that is not a fact, the provider must
 * still be registered, and deduplication still runs.
 *
 * Usage:
 *   php scripts/push_places.php --site=https://ruinmytrip.com --key=… --city=lisbon-portugal \
 *       --type=restaurant --limit=40 [--km=6] [--dry]
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
$site = rtrim((string) ($opts['site'] ?? ''), '/');
$key  = (string) ($opts['key'] ?? '');
$city = (string) ($opts['city'] ?? '');
$type = (string) ($opts['type'] ?? '');
$limit = max(1, (int) ($opts['limit'] ?? 40));
$km    = (float) ($opts['km'] ?? 0);
/* Optional: restrict to particular provider kinds, comma separated, so a city gets a deliberate
   mix rather than whatever the bounding box happens to be densest in. */
$only  = array_values(array_filter(array_map('trim', explode(',', (string) ($opts['osm'] ?? '')))));
$dry   = isset($opts['dry']);
if ($site === '' || $key === '' || $city === '' || $type === '') {
    fwrite(STDERR, "--site --key --city --type are all required\n");
    exit(2);
}

$dest = q_one('SELECT * FROM destinations WHERE slug = ?', [$city]);
if (!$dest) { fwrite(STDERR, "no such city locally: $city\n"); exit(2); }

/* Retries, each one asking a SMALLER question rather than the same question more loudly.

   Overpass scans the whole bounding box whatever the output limit says, and in a city as densely
   mapped as Tokyo or London that scan is what times out, not the network. Halving the radius looks
   closer to the centre, which is both more likely to answer and more likely to be the part of the
   city a traveler cares about. Backing off and shrinking is the only kind of retry a free service
   run by volunteers deserves. */
$pull = ['ok' => false, 'error' => 'not attempted', 'tries' => []];
$useKm = $km ?: null;
for ($attempt = 1; $attempt <= 4; $attempt++) {
    $pull = rmt_osm_places_for_destination($dest, $type, $limit, $useKm, $only);
    if ($pull['ok']) break;
    fprintf(STDERR, "  attempt %d (%s km): %s [%s]
", $attempt,
            $useKm === null ? 'default' : (string) round((float) $useKm, 1),
            (string) $pull['error'], implode('; ', $pull['tries'] ?? []));
    $useKm = max(1.5, ($useKm ?? rmt_osm_default_km($type)) / 2);
    if ($attempt < 4) sleep($attempt * 12);
}
if (!$pull['ok']) {
    fwrite(STDERR, 'provider gave up after 4 attempts: ' . (string) $pull['error'] . "
");
    exit(1);
}
if (count($pull['tries'] ?? []) > 1) echo '  provider: ', implode('; ', $pull['tries']), "
";
printf("fetched %d %s rows for %s\n", count($pull['rows']), $type, (string) $dest['name']);
if (!empty($pull['tags'])) {
    $bits = [];
    foreach (array_slice($pull['tags'], 0, 8, true) as $k => $n) $bits[] = $k . '=' . $n;
    echo "  kinds: ", implode(", ", $bits), "
";
}
if (!$pull['rows']) exit(0);

$body = json_encode(['rows' => $pull['rows'], 'aliases' => $pull['aliases']],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$url = $site . '/cron/places?' . http_build_query([
    'key' => $key, 'op' => 'ingest', 'city' => $city, 'dry' => $dry ? '1' : '0',
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
]);
$ca = (string) ($GLOBALS['config']['ca_bundle'] ?? (getenv('CURL_CA_BUNDLE') ?: ''));
if ($ca !== '' && is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
$out = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($out === false) { fwrite(STDERR, "post failed: $err\n"); exit(1); }
echo "HTTP $code\n", $out;
exit($code === 200 ? 0 : 1);
