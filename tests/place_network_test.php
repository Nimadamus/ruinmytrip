<?php
/**
 * The people layer of a place: who has it planned, who went and would go again.
 *
 * A place page carries other people's plans, which makes it a page that can leak them. Every rule
 * that holds on a trip has to hold here: a private trip's plan is not on it, a followers-only trip
 * shows only to a follower, a plan marked private shows only to its author, and somebody who
 * blocked you is not on your screen.
 *
 * The counting rules matter as much: one person who planned the same restaurant twice is one
 * person, a cancelled plan is nobody, and a plan whose day has passed is not "going".
 *
 *   php tests/place_network_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/plans.php';
require BASE_PATH . '/app/matching.php';
require BASE_PATH . '/app/activities.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active')");
$pdo->exec("CREATE TABLE profiles (user_id INT, avatar_url TEXT, display_name TEXT)");
$pdo->exec("CREATE TABLE follows (followee_id INT, follower_id INT)");
$pdo->exec("CREATE TABLE blocks (blocker_id INT, blocked_id INT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY, destination_id INT, name TEXT, slug TEXT,
              name_key TEXT, status TEXT DEFAULT 'active')");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, status TEXT DEFAULT 'published', visibility TEXT DEFAULT 'public',
              date_from TEXT, date_to TEXT)");
$pdo->exec("CREATE TABLE trip_members (trip_id INT, user_id INT, role TEXT, state TEXT,
              invited_by INT, created_at TEXT, decided_at TEXT, PRIMARY KEY (trip_id, user_id))");
$pdo->exec("CREATE TABLE trip_activities (id INTEGER PRIMARY KEY, trip_id INT, user_id INT,
              destination_id INT, day TEXT, start_time TEXT, title TEXT, category TEXT, place_id INT,
              location_text TEXT, notes TEXT, visibility TEXT DEFAULT 'trip',
              join_mode TEXT DEFAULT 'no', recommend INT, done INT, cancelled_at TEXT,
              status TEXT DEFAULT 'published', created_at TEXT)");
$pdo->exec("CREATE TABLE activity_joins (activity_id INT, user_id INT, state TEXT, created_at TEXT)");

$soon = date('Y-m-d', strtotime('+10 days'));
$soonEnd = date('Y-m-d', strtotime('+17 days'));
$past = date('Y-m-d', strtotime('-10 days'));

$pdo->exec("INSERT INTO users (id,username) VALUES (1,'ana'),(2,'ben'),(3,'cara'),(4,'dan'),(5,'eve')");
$pdo->exec("INSERT INTO places VALUES (1,7,'Time Out Market','time-out-market','time out market','active')");
/* One public trip each, plus one followers-only and one private, all to the same city and all in
   the same window. Eve's trip already happened. */
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,visibility,date_from,date_to) VALUES
  (1,1,7,'Ana','a','public','$soon','$soonEnd'),
  (2,2,7,'Ben','b','public','$soon','$soonEnd'),
  (3,3,7,'Cara','c','followers','$soon','$soonEnd'),
  (4,4,7,'Dan','d','private','$soon','$soonEnd'),
  (5,5,7,'Eve','e','public','$past','$past'),
  (6,1,7,'Ana again','a2','public','$soon','$soonEnd')");
$pdo->exec("INSERT INTO follows VALUES (3,1)");   // Ana follows Cara

$act = static function (int $id, int $trip, int $user, ?string $day, string $vis = 'trip',
                        ?int $rec = null, ?string $cancelled = null) use ($pdo): void {
    $d = $day === null ? 'NULL' : "'$day'";
    $c = $cancelled === null ? 'NULL' : "'$cancelled'";
    $r = $rec === null ? 'NULL' : (string) $rec;
    $pdo->exec("INSERT INTO trip_activities (id,trip_id,user_id,destination_id,day,title,category,
                  place_id,visibility,join_mode,recommend,cancelled_at,status,created_at)
                VALUES ($id,$trip,$user,7,$d,'Lunch','food',1,'$vis','no',$r,$c,'published','2026-09-01')");
};

$act(1, 1, 1, $soon);                       // Ana, public, upcoming
$act(2, 6, 1, $soon);                       // Ana again, same place: still one Ana
$act(3, 2, 2, $soon);                       // Ben, public, upcoming
$act(4, 3, 3, $soon);                       // Cara, followers-only trip
$act(5, 4, 4, $soon);                       // Dan, private trip
$act(6, 5, 5, $past, 'trip', 1);            // Eve went and would go again
$act(7, 2, 2, $soon, 'private');            // Ben's own private line
$act(8, 1, 1, $soon, 'trip', null, '2026-09-02 10:00:00');  // Ana cancelled one

$anon = null;
$ana = ['id' => 1]; $ben = ['id' => 2]; $dan = ['id' => 4]; $stranger = ['id' => 99];

$names = static fn(array $net, string $k): array =>
    array_map(static fn(array $r) => (string) $r['username'], $net[$k]);

$n = rmt_place_network(1, $anon);
$plannedSorted = $names($n, 'planned'); sort($plannedSorted);
ok($plannedSorted === ['ana', 'ben'], 'a stranger sees only the plans on public trips');
ok($n['planned_n'] === 2, 'two people, not three rows: planning it twice is still one person');
ok($names($n, 'recommended') === ['eve'], 'and the person who went and would go again');
ok($n['overlapping'] === 0, 'nobody overlaps with somebody who is not signed in');

$n = rmt_place_network(1, $ana);
ok(in_array('cara', $names($n, 'planned'), true), 'a follower sees the followers-only trip');
ok(!in_array('dan', $names($n, 'planned'), true), 'and never the private one');

$n = rmt_place_network(1, $stranger);
ok(!in_array('cara', $names($n, 'planned'), true), 'somebody who does not follow does not');
ok(!in_array('dan', $names($n, 'planned'), true), 'and a private trip is invisible to everybody else');

$n = rmt_place_network(1, $dan);
ok(in_array('dan', $names($n, 'planned'), true), 'the traveler whose private trip it is still sees their own');

// A plan marked private is its author's alone, even on a public trip.
$n = rmt_place_network(1, $anon);
ok(count(array_filter($n['planned'], static fn(array $r) => (int) $r['id'] === 7)) === 0,
   'a plan marked private is on nobody else\'s screen');
$n = rmt_place_network(1, $ben);
ok($n['planned_n'] === 2, 'and does not double-count its own author either');

// Blocks, both directions.
$pdo->exec('INSERT INTO blocks VALUES (2,1)');
ok(!in_array('ben', $names(rmt_place_network(1, $ana), 'planned'), true),
   'somebody who blocked you is not on the page');
$pdo->exec('DELETE FROM blocks');
$pdo->exec('INSERT INTO blocks VALUES (1,2)');
ok(!in_array('ben', $names(rmt_place_network(1, $ana), 'planned'), true),
   'and neither is somebody you blocked');
$pdo->exec('DELETE FROM blocks');

// Overlap: Ben is there while Ana is, and the past trip is not.
$n = rmt_place_network(1, $ana);
ok($n['overlapping'] >= 1, 'somebody whose dates land on yours is counted as there while you are');
ok($n['overlapping'] < $n['planned_n'], 'and the count never includes you');

// An account that is gone, and a place nobody has planned.
$pdo->exec("UPDATE users SET status = 'deleted' WHERE id = 2");
ok(!in_array('ben', $names(rmt_place_network(1, $anon), 'planned'), true),
   'a deleted account is planning nothing');
ok(rmt_place_network(999, $anon)['planned_n'] === 0, 'a place nobody has planned says nothing');
ok(rmt_place_network(0, $anon)['planned_n'] === 0, 'and no place at all is not a fatal');

echo "place_network_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
