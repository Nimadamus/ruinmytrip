<?php
/**
 * Empty pages stay honest.
 *
 * A young network is mostly empty, and the temptation on every page with nothing on it is to make
 * it look busier than it is. That is the one thing this product cannot do: the whole pitch is that
 * everything here was posted by a real traveler, and a single invented row would make every real
 * one unbelievable.
 *
 * So the suggestions offered on an empty page are checked against the database they came from:
 *   - a city is offered only when somebody has actually posted dates or a meetup for it
 *   - the count shown next to it is the real count
 *   - a person is offered only when they exist, are active, and are not the house account
 *   - a viewer is never offered themselves
 *   - when there is nothing real to offer, nothing is offered
 *
 *   php tests/empty_states_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
const RMT_EDITORIAL_ROLE = 'editorial';

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/plans.php';
require BASE_PATH . '/app/going.php';
require BASE_PATH . '/app/matching.php';
require BASE_PATH . '/app/discovery.php';

function dest_by_id(int $id): ?array { return q_one('SELECT * FROM destinations WHERE id=?', [$id]); }

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT)');
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active', role TEXT DEFAULT 'user')");
$pdo->exec('CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT, home_city TEXT, home_destination_id INT, travel_style TEXT, open_to_meeting INT DEFAULT 0)');
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT)');
$pdo->exec('CREATE TABLE blocks (blocker_id INT, blocked_id INT)');
$pdo->exec('CREATE TABLE saves (user_id INT, target_type TEXT, target_id INT, created_at TEXT)');
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, status TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT, slug TEXT, status TEXT, visibility TEXT, date_from TEXT, date_to TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS trip_members (trip_id INT, user_id INT, role TEXT, state TEXT, invited_by INT, created_at TEXT, decided_at TEXT, PRIMARY KEY (trip_id, user_id))");
$pdo->exec("CREATE TABLE meetups (id INTEGER PRIMARY KEY, host_id INT, destination_id INT, title TEXT, date_start TEXT, status TEXT)");
$pdo->exec('CREATE TABLE meetup_rsvps (meetup_id INT, user_id INT, status TEXT)');
$pdo->exec('CREATE TABLE collection_members (collection_id INT, user_id INT, status TEXT)');
$pdo->exec("CREATE TABLE collections (id INTEGER PRIMARY KEY, user_id INT, title TEXT, slug TEXT, status TEXT)");
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, body TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE comments (id INTEGER PRIMARY KEY, user_id INT, target_type TEXT, target_id INT, status TEXT, created_at TEXT)");
$pdo->exec('CREATE TABLE likes (user_id INT, target_type TEXT, target_id INT)');
$pdo->exec('CREATE TABLE profile_interests (user_id INT, interest TEXT, PRIMARY KEY (user_id, interest))');

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $m\n"; } }

// --- nothing at all ------------------------------------------------------------------------
$pdo->exec("INSERT INTO destinations VALUES (1,'lisbon-portugal','Lisbon','Portugal'),(2,'porto-portugal','Porto','Portugal')");
$empty = rmt_empty_state_suggestions(null);
ok($empty['cities'] === [], 'a site with no trips offers no cities, rather than listing every city it knows');
ok($empty['people'] === [], 'and offers nobody when nobody has done anything');

// --- one real trip -------------------------------------------------------------------------
$soon = date('Y-m-d', strtotime('+10 days'));
$soon2 = date('Y-m-d', strtotime('+17 days'));
$pdo->exec("INSERT INTO users (id,username,role) VALUES (1,'ana','user'),(2,'ben','user'),(9,'house','editorial')");
$pdo->exec("INSERT INTO profiles (user_id) VALUES (1),(2),(9)");
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,status,visibility,date_from,date_to)
            VALUES (1,1,1,'Lisbon','l','published','public','$soon','$soon2')");

$one = rmt_empty_state_suggestions(null);
ok(count($one['cities']) === 1, 'one trip makes exactly one city worth offering');
ok((string) $one['cities'][0]['slug'] === 'lisbon-portugal', 'and it is the city that trip is to');
ok((int) $one['cities'][0]['going'] === 1, 'the count next to it is the real count');

// A private trip is not a reason to advertise a city.
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,status,visibility,date_from,date_to)
            VALUES (2,2,2,'Porto','p','published','private','$soon','$soon2')");
$two = rmt_empty_state_suggestions(null);
ok(count($two['cities']) === 1, 'a private trip does not put a city on the list');

// A finished trip is not a reason either.
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,status,visibility,date_from,date_to)
            VALUES (3,2,2,'Porto last year','p2','published','public','2025-01-01','2025-01-08')");
ok(count(rmt_empty_state_suggestions(null)['cities']) === 1, 'a trip that already happened does not either');

// A meetup does.
$pdo->exec("INSERT INTO meetups VALUES (1,1,2,'Coffee','" . date('Y-m-d H:i:s', strtotime('+5 days')) . "','published')");
$three = rmt_empty_state_suggestions(null);
ok(count($three['cities']) === 2, 'a meetup is a reason to offer a city');

// --- people ---------------------------------------------------------------------------------
$pdo->exec("INSERT INTO reviews VALUES (1,1,'published')");
$who = rmt_empty_state_suggestions(null);
$names = array_map(static fn(array $r) => (string) $r['username'], $who['people']);
ok(in_array('ana', $names, true), 'somebody who has written a review is offered');
ok(!in_array('ben', $names, true), 'somebody who has done nothing is not');
ok(!in_array('house', $names, true), 'and the editorial account is never offered as a traveler');

$mine = rmt_empty_state_suggestions(['id' => 1]);
$mineNames = array_map(static fn(array $r) => (string) $r['username'], $mine['people']);
ok(!in_array('ana', $mineNames, true), 'a viewer is never offered themselves');

/* The reason line is a fact about the row, not a claim about popularity. */
foreach ($who['people'] as $r) {
    ok(str_contains((string) ($r['reason'] ?? ''), 'review'), 'the reason says what the person actually did');
}

echo "empty_states_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
