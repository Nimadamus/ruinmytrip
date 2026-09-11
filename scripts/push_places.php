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
$dry   = isset($opts['dry']);
if ($site === '' || $key === '' || $city === '' || $type === '') {
    fwrite(STDERR, "--site --key --city --type are all required\n");
    exit(2);
}

$dest = q_one('SELECT * FROM destinations WHERE slug = ?', [$city]);
if (!$dest) { fwrite(STDERR, "no such city locally: $city\n"); exit(2); }

$pull = rmt_osm_places_for_destination($dest, $type, $limit, $km ?: null);
if (!$pull['ok']) { fwrite(STDERR, 'provider: ' . (string) $pull['error'] . "\n"); exit(1); }
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
