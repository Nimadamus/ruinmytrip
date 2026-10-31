<?php
/**
 * "Near {city}" on a city page is geography: the same country first, then distance, never itself,
 * never a city already shown under "Travelers here also go to", and never a city without
 * coordinates. Also pins the city page wiring and the editorial file for the three new Official
 * Reviews (San Francisco, Manaus, Buzios), whose summaries must equal the live meta descriptions.
 *
 *   php tests/nearby_destinations_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';

$src = file_get_contents(BASE_PATH . '/app/discovery.php');
$start = strpos($src, 'function rmt_nearby_destinations(');
if ($start === false) { echo "FAIL: rmt_nearby_destinations not found\n"; exit(1); }
$end = strpos($src, "\n}\n", $start);
eval(substr($src, $start, $end - $start + 3));

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$pdo = db();
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, country TEXT, lat REAL, lng REAL)");
$rows = [
    [1, 'Lisbon', 'lisbon-portugal', 'Portugal', 38.7223, -9.1393],
    [2, 'Porto', 'porto-portugal', 'Portugal', 41.1579, -8.6291],
    [3, 'Madrid', 'madrid-spain', 'Spain', 40.4168, -3.7038],
    [4, 'Casablanca', 'casablanca-morocco', 'Morocco', 33.5731, -7.5898],
    [5, 'Tokyo', 'tokyo-japan', 'Japan', 35.6762, 139.6503],
    [6, 'Nowhere', 'nowhere', 'Portugal', null, null],
];
$st = $pdo->prepare('INSERT INTO destinations VALUES (?,?,?,?,?,?)');
foreach ($rows as $r) $st->execute($r);
$lisbon = ['id' => 1, 'country' => 'Portugal', 'lat' => 38.7223, 'lng' => -9.1393];

$n = rmt_nearby_destinations($lisbon, 3);
ok(array_column($n, 'slug') === ['porto-portugal', 'madrid-spain', 'casablanca-morocco'], 'same country first, then nearest');
ok(!in_array('lisbon-portugal', array_column($n, 'slug'), true), 'never the city itself');
ok(!in_array('nowhere', array_column($n, 'slug'), true), 'no coordinates, not listed');
ok($n[0]['km'] > 250 && $n[0]['km'] < 300, 'Lisbon to Porto measured at about 274 km (' . $n[0]['km'] . ')');
ok($n[0]['same_country'] === true && $n[1]['same_country'] === false, 'same_country flag set');
$x = rmt_nearby_destinations($lisbon, 3, [2]);
ok(!in_array('porto-portugal', array_column($x, 'slug'), true), 'a city already in the traveler list is not repeated');
ok(rmt_nearby_destinations(['id' => 1, 'lat' => null, 'lng' => null], 3) === [], 'a city without coordinates gets nothing');

$ctl = file_get_contents(BASE_PATH . '/app/controllers.php');
ok(str_contains($ctl, "rmt_nearby_destinations(\$d, 6, ") && str_contains($ctl, "'related','nearby',"), 'city controller passes nearby to the view');
$view = file_get_contents(BASE_PATH . '/views/destination.php');
ok(str_contains($view, '$nearby') && str_contains($view, '$relatedPosts') && str_contains($view, "url('g/'.\$cityGuide['slug'])"), 'city view renders nearby cities, the 2026 posts and the city guide');

/* The three new Official Reviews keep each city's live summary, so the page's meta description
   does not change with them. */
$ed = json_decode((string) file_get_contents(BASE_PATH . '/database/editorial/cities_20261030.json'), true);
$live = [
    'san-francisco-usa' => 'Hills, fog, and a real comeback: 24.2 million visitors forecast for 2026, a 14 percent hotel tax, 9 dollar cable cars, and Alcatraz tickets that sell out weeks ahead.',
    'manaus-brazil' => 'A gilded opera house in the Amazon, the Meeting of the Waters, and the real jungle a boat ride away, with yellow fever prep and app-only transport as the price of entry.',
    'buzios-brazil' => 'Twenty-three beaches on a peninsula three hours from Rio, still the easy Brazilian beach town Brigitte Bardot put on the map, reachable by bus from about 19 US dollars.',
];
foreach ($ed['destinations'] ?? [] as $d) {
    ok(($live[$d['slug']] ?? null) === $d['summary'], "{$d['slug']}: summary equals the live meta description");
    ok(empty($d['visited_on']), "{$d['slug']}: editorial entry claims no visit");
    $all = json_encode($d, JSON_UNESCAPED_UNICODE);
    ok(!preg_match('/[\x{2012}-\x{2015}]| - /u', $all), "{$d['slug']}: no dashes");
}
ok(count($ed['destinations'] ?? []) === 3, 'three cities in the file');

/* Blog posts link on to the city's guide, sibling posts and the buddies page, except roundups. */
ok(str_contains($ctl, "'blogDest','blogMore'") && str_contains($ctl, '$blogCities <= 2'), 'blog controller builds blogMore and skips multi city roundups');
$bv = file_get_contents(BASE_PATH . '/views/blog_show.php');
ok(str_contains($bv, "url('g/' . \$bm['guide']['slug'])") && str_contains($bv, 'rmt_buddy_landing_path'), 'blog view links the guide and the buddies page');

/* Trip type pages say something of their own, and every country they link is a real landing page. */
$bti = file_get_contents(BASE_PATH . '/views/_buddy_type_intro.php');
ok(str_contains(file_get_contents(BASE_PATH . '/views/buddies_index.php'), '_buddy_type_intro.php'), 'buddies index includes the trip type intro');
$countryBlock = substr($bti, (int) strpos($bti, '$btCountries'), (int) strpos($bti, '$btText') - (int) strpos($bti, '$btCountries'));
preg_match_all("/'([a-z]+)'/", (string) preg_replace("/'[a-z_]+'\s*=>/", '', $countryBlock), $m);
$landing = file_get_contents(BASE_PATH . '/app/buddy_landing_pages.php') . file_get_contents(BASE_PATH . '/app/buddy_landing.php');
ok(count($m[1]) > 10, 'trip type intro lists country pages');
foreach (array_unique($m[1]) as $c) ok(str_contains($landing, "'$c'"), "trip type intro links a real country page: $c");
ok(!preg_match('/[\x{2012}-\x{2015}]| - /u', $bti . file_get_contents(BASE_PATH . '/views/_site_purpose.php')), 'no dashes in the new hub copy');

echo "\nnearby_destinations_test: " . ($fail ? "$fail FAILED" : 'all passed') . "\n";
exit($fail ? 1 : 0);
