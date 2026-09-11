<?php
/**
 * Who appears in "Also there then" on a trip page (rmt_trip_overlappers).
 *
 * This box names other people's travel dates on a page anybody can open, which makes it exactly the
 * kind of feature that leaks something quietly. The rules it has to keep:
 *
 *   - only trips that really overlap, in the same city
 *   - a public trip is visible to everybody, a followers only trip only to a follower, a private
 *     trip to nobody but its owner
 *   - never the trip being looked at, and never another trip by the same person
 *   - a blocked account does not appear, in either direction
 *   - a draft does not appear
 *
 *   php tests/trip_overlappers_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/plans.php';
require BASE_PATH . '/app/matching.php';

function dest_by_id(int $id): ?array { return q_one('SELECT * FROM destinations WHERE id=?', [$id]); }

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active')");
$pdo->exec('CREATE TABLE profiles (user_id INT, avatar_url TEXT)');
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT)');
$pdo->exec('CREATE TABLE blocks (blocker_id INT, blocked_id INT)');
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, body TEXT, status TEXT, visibility TEXT, date_from TEXT, date_to TEXT)");

$pdo->exec("INSERT INTO destinations VALUES (7,'lisbon-portugal','Lisbon'),(8,'porto-portugal','Porto')");
$pdo->exec("INSERT INTO users (id,username) VALUES (1,'ana'),(2,'ben'),(3,'cleo'),(4,'dev'),(5,'eze'),(6,'fin')");
$pdo->exec("INSERT INTO profiles VALUES (1,NULL),(2,NULL),(3,NULL),(4,NULL),(5,NULL),(6,NULL)");
// cleo follows ben, so a followers-only trip of ben's is visible to cleo and to nobody else.
$pdo->exec("INSERT INTO follows VALUES (3,2)");
// eze has blocked ana, so neither sees the other.
$pdo->exec("INSERT INTO blocks VALUES (5,1)");

$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,body,status,visibility,date_from,date_to) VALUES
  (1,1,7,'Mine','m','','published','public','2026-04-10','2026-04-20'),
  (2,2,7,'Ben public','b','','published','public','2026-04-15','2026-04-18'),
  (3,2,7,'Ben followers','bf','','published','followers','2026-04-12','2026-04-14'),
  (4,4,7,'Dev private','dp','','published','private','2026-04-11','2026-04-19'),
  (5,6,7,'Fin misses','fm','','published','public','2026-04-21','2026-04-25'),
  (6,6,8,'Fin other city','fo','','published','public','2026-04-12','2026-04-16'),
  (7,5,7,'Eze blocked','eb','','published','public','2026-04-13','2026-04-17'),
  (8,1,7,'My second','ms','','published','public','2026-04-12','2026-04-16'),
  (9,3,7,'Cleo draft','cd','','draft','public','2026-04-12','2026-04-16')");

$trip = q_one('SELECT * FROM trips WHERE id=1');
$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $m\n"; } }
$names = static fn(array $rows): array => array_map(static fn(array $r) => (string) $r['username'], $rows);

// A logged-out reader: public overlaps only.
$out = $names(rmt_trip_overlappers($trip, null));
sort($out);
ok($out === ['ben', 'eze'], 'logged out sees only public overlapping trips, got ' . implode(',', $out));

// A follower of ben also sees ben's followers-only trip, and it is the same person twice.
$out = $names(rmt_trip_overlappers($trip, ['id' => 3]));
ok(in_array('ben', $out, true), 'a follower sees the followers-only trip');
ok(count(array_filter($out, static fn($n) => $n === 'ben')) === 2, 'both of ben trips overlap for a follower');

// Nobody ever sees the private one.
foreach ([null, ['id' => 2], ['id' => 3], ['id' => 5]] as $viewer) {
    $out = $names(rmt_trip_overlappers($trip, $viewer));
    ok(!in_array('dev', $out, true), 'a private trip never appears');
}

// The trip itself, and the owner's own second trip in the same window, are not company.
$out = $names(rmt_trip_overlappers($trip, ['id' => 1]));
ok(!in_array('ana', $out, true), 'your own trips are not other travelers');

// A block hides the other person in both directions.
ok(!in_array('eze', $names(rmt_trip_overlappers($trip, ['id' => 1])), true), 'somebody who blocked you is hidden');
ok(!in_array('ana', $names(rmt_trip_overlappers(q_one('SELECT * FROM trips WHERE id=7'), ['id' => 5])), true),
   'somebody you blocked is hidden');

// Dates and cities have to actually meet.
$out = $names(rmt_trip_overlappers($trip, null));
ok(!in_array('fin', $out, true), 'a trip that starts after this one ends is not an overlap');

// A trip with no dates has nobody, rather than everybody.
ok(rmt_trip_overlappers(['id' => 99, 'user_id' => 1, 'destination_id' => 7, 'date_from' => '', 'date_to' => ''], null) === [],
   'an undated trip returns nothing');

// A draft is not company either.
ok(!in_array('cleo', $names(rmt_trip_overlappers($trip, ['id' => 3])), true), 'a draft trip does not appear');

echo "trip_overlappers_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
