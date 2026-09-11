<?php
/**
 * Mirror choice, health and cooldown.
 *
 * One public endpoint is a single point of failure and it is somebody else's free service. The
 * rules this pins down are the ones that keep us a polite heavy user rather than the reason a free
 * service gets locked down:
 *
 *   - a mirror that just failed goes to the back and is left alone for a while
 *   - the cooldown grows with repeated failures and is capped, so a bad afternoon is not a ban
 *   - a success clears the record, because yesterday's outage is not evidence about today
 *   - no history means no opinion: a missing or corrupt file is the same as a fresh start
 *   - the list is configurable, so a mirror can be added or dropped without a deploy
 *
 *   php tests/osm_mirrors_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$tmp = sys_get_temp_dir() . '/rmt_osm_health_test_' . getmypid() . '.json';
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
    'osm_health_file' => $tmp,
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/place_provider_osm.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}
@unlink($tmp);

$host = static fn(string $u): string => (string) parse_url($u, PHP_URL_HOST);
$order = static fn(): array => array_map($host, rmt_osm_endpoints_ranked());

ok(count(rmt_osm_endpoints()) >= 3, 'there is more than one mirror to fall back to');
ok(rmt_osm_health_read() === [], 'no file means no history rather than an error');
ok(count($order()) === count(rmt_osm_endpoints()), 'with no history every mirror is still offered');

$first = $order()[0];
rmt_osm_health_note($first, false);
ok($order()[0] !== $first, 'a mirror that just failed is not the next one tried');
ok(in_array($first, $order(), true), 'but it is not dropped either: one bad answer is not a ban');

$h = rmt_osm_health_read();
$cool1 = (int) $h[$first]['until'] - time();
ok($cool1 > 0 && $cool1 <= 60, 'the first failure buys a short rest');

rmt_osm_health_note($first, false);
rmt_osm_health_note($first, false);
$h = rmt_osm_health_read();
$cool3 = (int) $h[$first]['until'] - time();
ok($cool3 > $cool1, 'repeated failures buy a longer one');
for ($i = 0; $i < 10; $i++) rmt_osm_health_note($first, false);
$h = rmt_osm_health_read();
ok((int) $h[$first]['until'] - time() <= 900, 'and it is capped, because a bad afternoon is not a ban');

rmt_osm_health_note($first, true);
$h = rmt_osm_health_read();
ok((int) $h[$first]['fails'] === 0 && (int) $h[$first]['until'] === 0,
   'one success clears the record: yesterday is not evidence about today');

// A corrupt file is the same as no file.
file_put_contents($tmp, 'not json at all');
ok(rmt_osm_health_read() === [], 'a corrupt health file means no opinion, not a crash');
ok(count($order()) === count(rmt_osm_endpoints()), 'and every mirror is offered again');

// Configurable without a deploy.
$GLOBALS['config']['osm_mirrors'] = 'https://one.example/api,https://two.example/api';
ok(count(rmt_osm_endpoints()) === 2, 'the mirror list can be set in config');
ok($host(rmt_osm_endpoints()[0]) === 'one.example', 'and it is used as given');
$GLOBALS['config']['osm_mirrors'] = '';
ok(count(rmt_osm_endpoints()) >= 3, 'an empty setting falls back to the built in list');

@unlink($tmp);
/* The time budget is spent unevenly on purpose: an early attempt gets a short one because there
   is another mirror to try, and the last gets the rest because there is nowhere else to go. A flat
   budget meant the mirror that answers in twenty six seconds always failed by a hair and never
   recorded a single success, which then buried it further down the ranking. */
$src = (string) file_get_contents(BASE_PATH . '/app/place_provider_osm.php');
ok(str_contains($src, '$i === $last ? max($timeout, 45) : min($timeout, 12)'),
   'the last mirror gets a longer timeout than the first');
ok(!str_contains($src, 'overpass.osm.jp'),
   'a mirror that never completed a TLS handshake from here is not in the list');

/* An empty answer is not the same as an answer.

   Some public instances host only a regional extract and answer a question about the rest of the
   world with a cheerful empty list: HTTP 200, zero elements, no error. overpass.osm.ch did exactly
   that for Tokyo, which reads as "that city has no bars" and silently under-imports a whole city.
   It is the most dangerous failure mode there is, because it does not look like one. */
ok(str_contains($src, "if (!\$json['elements'] && \$i < \$last)"),
   'an empty result is checked against a second mirror before it is believed');
ok(!in_array('https://overpass.osm.ch/api/interpreter', RMT_OSM_DEFAULT_ENDPOINTS, true),
   'and the regional instance that caused it is not in the list');

/* Asking for objects by id is the cheapest question this importer can put to a volunteer run
   server: a bounding box makes it search an area, a list of ids makes it look rows up. The hours
   backfill is built on it, so the shape of the query is pinned here. */
$q = rmt_osm_query_refs(['node/1', 'way/2', 'node/3', 'relation/4', 'node/1']);
ok(str_contains($q, 'node(id:1,3);'), 'nodes are asked for in one statement');
ok(str_contains($q, 'way(id:2);') && str_contains($q, 'relation(id:4);'), 'and so are ways and relations');
ok(substr_count($q, 'node(id:') === 1, 'a repeated reference is not asked for twice');
ok(str_contains($q, 'out center tags;'), 'and a way still comes back with a point');
ok(!preg_match('/\(-?\d+\.\d+,/', $q), 'and there is no bounding box in it at all');
ok(rmt_osm_query_refs(['nonsense', '../etc', 'node/x']) === '',
   'anything that is not an OSM reference asks for nothing');

echo "osm_mirrors_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
