<?php
/**
 * Finding people, without finding anybody who did not agree to be found.
 *
 * Discovery is the feature this product exists for and it is also the one that can leak the most:
 * every one of these queries reads other people's travel dates, and one of them lists somebody as
 * available to meet strangers. What must hold:
 *
 *   - every trip is read through the same visibility clause as the rest of the site
 *   - blocks apply in both directions, in every query
 *   - "here now" means a published date range that covers today, never a location
 *   - a local appears only after ticking the box, never because they live somewhere
 *   - nobody is ever shown themselves, and nobody already followed is suggested again
 *
 *   php tests/discovery_test.php
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
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active', role TEXT DEFAULT 'user')");
$pdo->exec("CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT, home_city TEXT,
              home_destination_id INT, travel_style TEXT, open_to_meeting INT DEFAULT 0, bio TEXT)");
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT)');
$pdo->exec('CREATE TABLE blocks (blocker_id INT, blocked_id INT)');
$pdo->exec("CREATE TABLE saves (user_id INT, target_type TEXT, target_id INT, created_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, status TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, body TEXT, status TEXT, visibility TEXT, date_from TEXT, date_to TEXT)");
$pdo->exec("CREATE TABLE meetups (id INTEGER PRIMARY KEY, host_id INT, destination_id INT, title TEXT,
              date_start TEXT, status TEXT)");
$pdo->exec("CREATE TABLE meetup_rsvps (meetup_id INT, user_id INT, status TEXT)");
/* rmt_follow_suggestions() reaches into the rooms and the activity tables too, so they have to
   exist for rmt_discover_all() to run at all. Empty is fine: what is under test here is that the
   five lists come back together and that nothing leaks into them. */
$pdo->exec("CREATE TABLE collection_members (collection_id INT, user_id INT, status TEXT)");
$pdo->exec("CREATE TABLE collections (id INTEGER PRIMARY KEY, user_id INT, title TEXT, slug TEXT, status TEXT)");
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, body TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE comments (id INTEGER PRIMARY KEY, user_id INT, target_type TEXT, target_id INT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE likes (user_id INT, target_type TEXT, target_id INT)");

$pdo->exec("INSERT INTO destinations VALUES (7,'lisbon-portugal','Lisbon'),(8,'porto-portugal','Porto')");
$pdo->exec("INSERT INTO users (id,username,role) VALUES
  (1,'ana','user'),(2,'ben','user'),(3,'cleo','user'),(4,'dev','user'),(5,'eze','user'),(9,'house','editorial')");
$pdo->exec("INSERT INTO profiles (user_id,travel_style,home_destination_id,open_to_meeting) VALUES
  (1,'slow',NULL,0),
  (2,'slow',7,1),       -- ben lives in Lisbon and opted in
  (3,'fast',7,0),       -- cleo lives in Lisbon and did not
  (4,'slow',NULL,0),
  (5,'slow',NULL,0),
  (9,NULL,7,1)");   // the house account, which must never be offered as a person
$pdo->exec("INSERT INTO reviews VALUES (1,2,'published')");

$today = date('Y-m-d');
$soon  = date('Y-m-d', strtotime('+20 days'));
$soon2 = date('Y-m-d', strtotime('+27 days'));
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,body,status,visibility,date_from,date_to) VALUES
  (1,1,7,'Mine','m','','published','public','$soon','$soon2'),
  (2,4,7,'Dev same week','d','','published','public','$soon','$soon2'),
  (3,5,7,'Eze private','e','','published','private','$soon','$soon2'),
  (4,3,7,'Cleo other weeks','c','','published','public','" . date('Y-m-d', strtotime('+90 days')) . "','" . date('Y-m-d', strtotime('+97 days')) . "'),
  (5,2,7,'Ben here now','b','','published','public','" . date('Y-m-d', strtotime('-2 days')) . "','" . date('Y-m-d', strtotime('+2 days')) . "'),
  (6,5,7,'Eze here now but private','ep','','published','private','" . date('Y-m-d', strtotime('-1 days')) . "','" . date('Y-m-d', strtotime('+3 days')) . "')");
$pdo->exec("INSERT INTO saves VALUES (1,'destination',7,'2026-01-01'),(1,'destination',8,'2026-01-01'),
                                     (4,'destination',7,'2026-01-01'),(4,'destination',8,'2026-01-01'),
                                     (5,'destination',7,'2026-01-01')");
$pdo->exec("INSERT INTO meetups VALUES (1,2,7,'Coffee at the market','" . date('Y-m-d H:i:s', strtotime('+10 days')) . "','published')");
$pdo->exec("INSERT INTO meetup_rsvps VALUES (1,1,'going'),(1,4,'going'),(1,5,'maybe')");

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $m\n"; } }
$names = static fn(array $rows): array => array_map(static fn(array $r) => (string) $r['username'], $rows);

// --- going where you are going -----------------------------------------------------------
$same = $names(rmt_discover_same_city(1));
ok(in_array('dev', $same, true), 'somebody going to the same city is found');
ok(in_array('cleo', $same, true), 'even when their weeks are different, which is the point of this one');
ok(!in_array('eze', $same, true), 'a private trip never puts somebody in the list');
ok(!in_array('ana', $same, true), 'you are never your own match');

$pdo->exec("INSERT INTO follows VALUES (1,4)");
ok(!in_array('dev', $names(rmt_discover_same_city(1)), true), 'somebody you already follow is not suggested again');
$pdo->exec("DELETE FROM follows");

$pdo->exec("INSERT INTO blocks VALUES (3,1)");   // cleo blocked ana
ok(!in_array('cleo', $names(rmt_discover_same_city(1)), true), 'somebody who blocked you is hidden');
$pdo->exec("DELETE FROM blocks");
$pdo->exec("INSERT INTO blocks VALUES (1,3)");   // ana blocked cleo
ok(!in_array('cleo', $names(rmt_discover_same_city(1)), true), 'somebody you blocked is hidden too');
$pdo->exec("DELETE FROM blocks");

// --- here now ----------------------------------------------------------------------------
$now = $names(rmt_discover_here_now(7, ['id' => 1]));
ok(in_array('ben', $now, true), 'a published range covering today counts as here now');
ok(!in_array('eze', $now, true), 'a private trip is not here now to anybody else');
ok(!in_array('dev', $now, true), 'a trip that has not started is not here now');
ok(in_array('eze', $names(rmt_discover_here_now(7, ['id' => 5])), true), 'the owner still sees their own');
ok(rmt_discover_here_now(8, ['id' => 1]) === [], 'a city with nobody in it says nobody');

// --- locals ------------------------------------------------------------------------------
$locals = $names(rmt_discover_locals(7, ['id' => 1]));
ok(in_array('ben', $locals, true), 'a local who ticked the box is listed');
ok(!in_array('cleo', $locals, true), 'a local who did not tick it is never listed');
ok(!in_array('house', $locals, true), 'the editorial account is not a person to meet');
$pdo->exec("INSERT INTO blocks VALUES (2,1)");
ok(!in_array('ben', $names(rmt_discover_locals(7, ['id' => 1])), true), 'a block hides a local as well');
$pdo->exec("DELETE FROM blocks");
ok(!in_array('ben', $names(rmt_discover_locals(7, ['id' => 2])), true), 'a local is not shown to themselves');

// --- kindred -----------------------------------------------------------------------------
$kin = rmt_discover_kindred(1);
$kinNames = $names($kin);
ok(in_array('dev', $kinNames, true), 'two shared cities and the same style is a match');
ok($kin && (int) $kin[0]['shared_cities'] === 2, 'the shared city count is real');
ok(!in_array('house', $kinNames, true), 'the editorial account is never suggested as a traveler');
ok(!in_array('ana', $kinNames, true), 'and neither are you');

// --- meetup peers ------------------------------------------------------------------------
$peers = $names(rmt_discover_meetup_peers(1));
ok(in_array('dev', $peers, true), 'somebody going to the same meetup is found');
ok(!in_array('eze', $peers, true), 'a maybe is not a yes');
ok(!in_array('ana', $peers, true), 'you are not your own meetup peer');

// --- the whole page ----------------------------------------------------------------------
$all = rmt_discover_all(1);
ok(array_keys($all) === ['overlapping', 'same_city', 'kindred', 'meetup_peers', 'suggested'],
   'the page asks for all five at once');
ok(rmt_discover_all(0) !== null && rmt_discover_all(0)['same_city'] === [],
   'a signed out visitor gets empty lists rather than an error');

echo "discovery_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
