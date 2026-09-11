<?php
/**
 * "Travelers here also go to" is a count of people, or it is nothing.
 *
 * This is the only honest form of "you might also like" the site can make: no model, no similarity
 * score, no editorial guess about which cities are alike. Two travelers who posted dates for both
 * cities is two travelers, and it says two.
 *
 * What this pins down:
 *   - counted in distinct people, so one person with three Porto trips is one
 *   - public trips only, because this is shown to everybody including a crawler
 *   - a draft, and the city itself, are never in the list
 *   - nothing at all when nobody has been to two cities
 *
 *   php tests/related_destinations_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';

/* discovery.php is a module of queries; only the one function is needed, and pulling the whole
   application in to reach it would make this a slower test of the same twenty lines. */
$src = file_get_contents(BASE_PATH . '/app/discovery.php');
$start = strpos($src, 'function rmt_related_destinations(int $destId, int $limit = 6): array {');
if ($start === false) { echo "FAIL: rmt_related_destinations not found\n"; exit(1); }
$end = strpos($src, "\n}\n", $start);
eval(substr($src, $start, $end - $start + 3));

$at2 = strpos($src, 'function rmt_prefer_city(array $rows, string $key, int $destId): array {');
if ($at2 === false) { echo "FAIL: rmt_prefer_city not found
"; exit(1); }
$end2 = strpos($src, "
}
", $at2);
eval(substr($src, $at2, $end2 - $at2 + 3));

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$pdo = db();
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, country TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT,
              status TEXT DEFAULT 'published', visibility TEXT DEFAULT 'public')");
$pdo->exec("CREATE TABLE IF NOT EXISTS trip_members (trip_id INT, user_id INT, role TEXT, state TEXT, invited_by INT, created_at TEXT, decided_at TEXT, PRIMARY KEY (trip_id, user_id))");
$pdo->exec("INSERT INTO destinations VALUES (1,'Lisbon','lisbon','Portugal'),(2,'Porto','porto','Portugal'),
            (3,'Madrid','madrid','Spain'),(4,'Nowhere','nowhere','Nowhereland')");

/* Ana and Ben both did Lisbon and Porto. Ana did Porto three times, which is still one Ana.
   Cara did Lisbon and Madrid, but kept Madrid to herself. Dan did Lisbon and a draft. */
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,status,visibility) VALUES
  (1,1,1,'published','public'), (2,1,2,'published','public'),
  (3,1,2,'published','public'), (4,1,2,'published','public'),
  (5,2,1,'published','public'), (6,2,2,'published','public'),
  (7,3,1,'published','public'), (8,3,3,'published','followers'),
  (9,4,1,'published','public'), (10,4,3,'draft','public')");

$out = rmt_related_destinations(1);
$by = [];
foreach ($out as $r) $by[(string) $r['name']] = (int) $r['n'];

ok(isset($by['Porto']) && $by['Porto'] === 2, 'two people who did both cities is two, not four trips');
ok(!isset($by['Lisbon']), 'the city itself is never in its own list');
ok(!isset($by['Madrid']), 'a followers-only trip is not published to everybody through a count');
ok(!isset($by['Nowhere']), 'a city nobody has been to is not there');
ok(count($out) === 1, 'and a draft adds nothing');

ok(rmt_related_destinations(4) === [], 'a city whose travelers went nowhere else says nothing');
ok(rmt_related_destinations(0) === [], 'no destination, no list');

// The limit is a limit.
$pdo->exec("INSERT INTO destinations VALUES (5,'E','e','X'),(6,'F','f','X'),(7,'G','g','X'),
            (8,'H','h','X'),(9,'I','i','X'),(10,'J','j','X')");
for ($i = 5; $i <= 10; $i++) $pdo->exec("INSERT INTO trips (user_id,destination_id) VALUES (1,$i)");
ok(count(rmt_related_destinations(1)) === 6, 'six at most');
ok(count(rmt_related_destinations(1, 3)) === 3, 'or fewer when asked');

/* City context in search. "Time Out Market" means the one in Lisbon when the reader is reading
   about Lisbon, and full text ranking scores a name against a name. rmt_prefer_city() reorders
   what a search already found; the rule that matters is that it NEVER adds or drops a row, because
   a search result the viewer was not allowed to see must not appear through a reordering. */
$rows = [
    ['id' => 1, 'destination_id' => 2, 'n' => 'porto one'],
    ['id' => 2, 'destination_id' => 1, 'n' => 'lisbon one'],
    ['id' => 3, 'destination_id' => 3, 'n' => 'madrid one'],
    ['id' => 4, 'destination_id' => 1, 'n' => 'lisbon two'],
];
$out = rmt_prefer_city($rows, 'destination_id', 1);
ok(array_column($out, 'id') === [2, 4, 1, 3], 'the city asked about comes first, in its own order');
ok(count($out) === count($rows), 'and nothing is added or dropped by reordering');
ok(rmt_prefer_city($rows, 'destination_id', 0) === $rows, 'no city context changes nothing');
ok(rmt_prefer_city($rows, 'destination_id', 99) === $rows, 'and a city with no rows changes nothing');
ok(rmt_prefer_city([], 'destination_id', 1) === [], 'an empty result stays empty');
$missing = [['id' => 9]];
ok(rmt_prefer_city($missing, 'destination_id', 1) === $missing, 'a row with no city is left where it was');

echo "related_destinations_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
