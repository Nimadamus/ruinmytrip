<?php
/**
 * Adding a city, and refusing to add half of one.
 *
 * The design decision this pins down is that a city being written lives in its own table, which no
 * other query in this application reads, and publishing is one insert. The alternative was a
 * status column on destinations, and it was rejected because a hundred and eighty queries read
 * that table: a draft would have been visible to every one of them until each was found and
 * filtered, and the leak would have looked exactly like a normal page.
 *
 * So most of what follows is about the gate. A draft may be as unfinished as it likes. A city may
 * not.
 *
 *   php tests/destination_new_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/destination_new.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}
/** Whether a validation run refused, and with which complaint. */
function refused(array $v, string $needle): bool {
    if ($v['ok']) return false;
    foreach ($v['errors'] as $e) if (stripos($e, $needle) !== false) return true;
    return false;
}

$pdo = db();
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT, name TEXT,
              country TEXT, region TEXT, lat REAL, lng REAL, summary TEXT, category TEXT,
              hero_url TEXT, hero_credit TEXT, hero_license TEXT, hero_source_url TEXT, name_norm TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/089_destination_drafts.sqlite.sql'));
$pdo->exec("INSERT INTO destinations (slug,name,country) VALUES ('lisbon-portugal','Lisbon','Portugal')");

$miami = [
    'name' => 'Miami', 'country' => 'United States', 'region' => 'Florida',
    'lat' => '25.7743', 'lng' => '-80.1937', 'category' => 'beach',
    'summary' => str_repeat('Miami is a real city and this is a real sentence about it. ', 3),
];

// --- a draft may be unfinished --------------------------------------------------------------------
$bare = rmt_dest_draft_validate(['name' => 'Miami', 'country' => 'United States'], false);
ok($bare['ok'], 'a draft with only a name and a country is a fine draft');
ok($bare['data']['slug'] === 'miami-united-states', 'and its address is made from both, not from the name alone');
ok($bare['data']['lat'] === null, 'no coordinates yet is null, not zero');

// --- a city may not --------------------------------------------------------------------------------
ok(refused(rmt_dest_draft_validate(['name' => 'Miami', 'country' => 'United States'], true), 'coordinates'),
   'publishing without coordinates is refused: the map and the importer both start from them');
$noSum = $miami; unset($noSum['summary']);
ok(refused(rmt_dest_draft_validate($noSum, true), 'summary'), 'and without a summary');
$shortSum = $miami; $shortSum['summary'] = 'Nice place.';
ok(refused(rmt_dest_draft_validate($shortSum, true), 'summary'), 'a sentence is not a summary');
$noCat = $miami; unset($noCat['category']);
ok(refused(rmt_dest_draft_validate($noCat, true), 'category'), 'and without a category it cannot be found on Explore');
ok(rmt_dest_draft_validate($miami, true)['ok'], 'with all of them, it publishes');

// --- values that are not values ---------------------------------------------------------------------
$atlantic = $miami; $atlantic['lat'] = '0'; $atlantic['lng'] = '0';
ok(refused(rmt_dest_draft_validate($atlantic, true), 'Atlantic'),
   'zero and zero is what an empty form submits, and it is in the sea');
$offEarth = $miami; $offEarth['lat'] = '95';
ok(refused(rmt_dest_draft_validate($offEarth, true), 'Latitude'), 'a latitude past the pole is refused');
$badCat = $miami; $badCat['category'] = 'nightlife';
ok(refused(rmt_dest_draft_validate($badCat, true), 'categories'), 'a category we do not have is refused, not invented');
$badSlug = $miami; $badSlug['slug'] = 'Miami Beach!';
ok(refused(rmt_dest_draft_validate($badSlug, true), 'web address'), 'a slug with spaces and punctuation is refused');
$taken = $miami; $taken['slug'] = 'lisbon-portugal';
ok(refused(rmt_dest_draft_validate($taken, true), 'already a city'), 'and one that is already a city');

// --- a photograph carries its attribution ------------------------------------------------------------
$naked = $miami; $naked['hero_url'] = 'https://example.test/miami.jpg';
ok(refused(rmt_dest_draft_validate($naked, true), 'credit'),
   'a photograph with no credit, licence and source is one we may not publish');
$http = $miami; $http['hero_url'] = 'http://example.test/miami.jpg';
$http['hero_credit'] = 'A'; $http['hero_license'] = 'CC BY'; $http['hero_source_url'] = 'https://example.test/p';
ok(refused(rmt_dest_draft_validate($http, true), 'https'), 'and it has to be served over https');
$dressed = $miami;
$dressed['hero_url'] = 'https://example.test/miami.jpg';
$dressed['hero_credit'] = 'Someone'; $dressed['hero_license'] = 'CC BY 4.0';
$dressed['hero_source_url'] = 'https://example.test/photo';
ok(rmt_dest_draft_validate($dressed, true)['ok'], 'with all three it is fine');

// --- saving and publishing -----------------------------------------------------------------------------
$id = rmt_dest_draft_save(rmt_dest_draft_validate(['name' => 'Miami', 'country' => 'United States'], false)['data'], 1);
ok($id > 0, 'a draft is saved');
ok(count(rmt_dest_drafts()) === 1, 'and is listed as being written');
/* The whole point of the design: nothing else can see it. */
ok((int) $pdo->query("SELECT COUNT(*) FROM destinations WHERE slug = 'miami-united-states'")->fetchColumn() === 0,
   'an unfinished city is not in the destinations table at all, so no query anywhere can show it');

$bad = rmt_dest_draft_publish($id);
ok(!$bad['ok'], 'publishing an unfinished draft is refused');
ok((int) $pdo->query("SELECT COUNT(*) FROM destinations")->fetchColumn() === 1, 'and writes nothing');

rmt_dest_draft_save(rmt_dest_draft_validate($miami + ['slug' => 'miami-united-states'], false, $id)['data'], 1, $id);
$good = rmt_dest_draft_publish($id);
ok($good['ok'] && $good['slug'] === 'miami-united-states', 'a finished one publishes');
$row = q_one("SELECT * FROM destinations WHERE slug = 'miami-united-states'");
ok($row !== null, 'and the city exists');
ok((string) $row['name'] === 'Miami' && (string) $row['country'] === 'United States', 'with what was typed');
ok(abs((float) $row['lat'] - 25.7743) < 0.0001, 'and the point it was given');
ok(rmt_dest_drafts() === [], 'the draft is no longer waiting to be written');
ok(!rmt_dest_draft_publish($id)['ok'], 'and publishing it twice does not make two cities');
ok((int) $pdo->query("SELECT COUNT(*) FROM destinations")->fetchColumn() === 2, 'there are two cities, not three');

echo "destination_new_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
