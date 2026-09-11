<?php
/**
 * What a traveler is doing there, and who is allowed to know.
 *
 * Activities are the newest thing on this site that reads another person's plans, which makes them
 * the newest way to leak one. The rules, in order of how much they would cost to get wrong:
 *
 *   - an activity is exactly as visible as its trip, and may be less (a public trip can hide one
 *     dinner), and can never be more (a private trip can never leak one)
 *   - a day has to sit inside the trip: a plan on a day nobody is there is a typo, and it would
 *     put the wrong week on a city page
 *   - the city view shows only activities the viewer may see, from trips the viewer may see
 *   - "popular" counts people, not rows, and says one when it is one
 *   - joining is possible only where the owner opened it
 *
 *   php tests/activities_test.php
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
require BASE_PATH . '/app/activities.php';

function dest_by_id(int $id): ?array { return q_one('SELECT * FROM destinations WHERE id=?', [$id]); }

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active', role TEXT DEFAULT 'user')");
$pdo->exec('CREATE TABLE profiles (user_id INT, avatar_url TEXT, display_name TEXT)');
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT)');
$pdo->exec('CREATE TABLE blocks (blocker_id INT, blocked_id INT)');
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, status TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, body TEXT, status TEXT, visibility TEXT, date_from TEXT, date_to TEXT)");
$pdo->exec("CREATE TABLE trip_activities (id INTEGER PRIMARY KEY AUTOINCREMENT, trip_id INT, user_id INT,
              destination_id INT, day TEXT, start_time TEXT, title TEXT, category TEXT, place_id INT,
              location_text TEXT, notes TEXT, link TEXT, photo_url TEXT, storage_key TEXT,
              visibility TEXT DEFAULT 'trip', join_mode TEXT DEFAULT 'no', done INT DEFAULT 0,
              rating INT, recommend INT, sort INT DEFAULT 0, status TEXT DEFAULT 'published',
              created_at TEXT, updated_at TEXT, capacity INT, meeting_point TEXT, end_time TEXT,
              cancelled_at TEXT)");
$pdo->exec("CREATE TABLE activity_joins (activity_id INT, user_id INT, state TEXT, created_at TEXT,
              decided_at TEXT, decided_by INT, PRIMARY KEY (activity_id, user_id))");
$pdo->exec("CREATE TABLE activity_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, activity_id INT,
              user_id INT, url TEXT, storage_key TEXT, caption TEXT, width INT, height INT,
              bytes INT, sort INT, status TEXT DEFAULT 'published', created_at TEXT)");
$pdo->exec('CREATE TABLE trip_photos (id INTEGER PRIMARY KEY, trip_id INT)');
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, trip_id INT, status TEXT)");

$pdo->exec("INSERT INTO destinations VALUES (7,'lisbon-portugal','Lisbon')");
$pdo->exec("INSERT INTO users (id,username) VALUES (1,'ana'),(2,'ben'),(3,'cleo'),(4,'dev')");
$pdo->exec('INSERT INTO profiles VALUES (1,NULL,NULL),(2,NULL,NULL),(3,NULL,NULL),(4,NULL,NULL)');
$pdo->exec('INSERT INTO follows VALUES (3,1)');   // cleo follows ana

$from = '2026-10-03'; $to = '2026-10-10';
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,body,status,visibility,date_from,date_to) VALUES
  (1,1,7,'Public','p','','published','public','$from','$to'),
  (2,1,7,'Followers','f','','published','followers','$from','$to'),
  (3,1,7,'Private','v','','published','private','$from','$to')");

$now = '2026-09-01 10:00:00';
$ins = static function (int $id, int $trip, int $user, string $title, string $vis = 'trip',
                        ?string $day = '2026-10-05', string $join = 'no') use ($pdo, $now): void {
    $pdo->prepare("INSERT INTO trip_activities (id,trip_id,user_id,destination_id,day,title,category,
                     visibility,join_mode,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,'published',?)")
        ->execute([$id, $trip, $user, 7, $day, $title, 'food', $vis, $join, $now]);
};
$ins(1, 1, 1, 'Dinner in Alfama');
$ins(2, 1, 1, 'A private dinner', 'private');
$ins(3, 2, 1, 'Followers-only plan');
$ins(4, 3, 1, 'On a private trip');
$ins(5, 1, 1, 'Benfica vs Porto', 'trip', '2026-10-07', 'open');
$ins(6, 1, 1, 'Sintra at some point', 'trip', null);

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $m\n"; } }
$titles = static fn(array $rows): array => array_map(static fn(array $r) => (string) $r['title'], $rows);

$owner = ['id' => 1, 'role' => 'user'];
$follower = ['id' => 3, 'role' => 'user'];
$stranger = ['id' => 2, 'role' => 'user'];

// --- one trip's plan ------------------------------------------------------------------------
$pubStranger = $titles(rmt_activities_for_trip(1, $stranger));
ok(in_array('Dinner in Alfama', $pubStranger, true), 'a plan on a public trip is public');
ok(!in_array('A private dinner', $pubStranger, true), 'a plan marked private is not, even on a public trip');
ok(in_array('A private dinner', $titles(rmt_activities_for_trip(1, $owner)), true), 'the owner sees their own private plan');
ok(in_array('A private dinner', $titles(rmt_activities_for_trip(1, ['id' => 9, 'role' => 'mod'])), true),
   'a moderator can see it, for moderation');
ok(!in_array('A private dinner', $titles(rmt_activities_for_trip(1, null)), true), 'and a signed out reader cannot');

// --- ordering -------------------------------------------------------------------------------
$rows = rmt_activities_for_trip(1, $owner);
$days = rmt_activities_by_day($rows);
ok($days[0]['day'] === '2026-10-05', 'days come in order');
ok(end($days)['day'] === null, 'and the things with no day come last');
ok(end($days)['label'] === 'Not fixed to a day', 'said in words rather than left blank');

// --- validation -----------------------------------------------------------------------------
$trip = q_one('SELECT * FROM trips WHERE id = 1');
$v = rmt_activity_validate(['title' => 'Dinner', 'day' => '2026-10-05'], $trip);
ok($v['ok'], 'a title and a day inside the trip is enough');
ok(rmt_activity_validate(['title' => ''], $trip)['ok'] === false, 'a plan with no words is not a plan');
$v = rmt_activity_validate(['title' => 'Dinner', 'day' => '2026-11-20'], $trip);
ok(!$v['ok'], 'a day outside the trip is refused');
$v = rmt_activity_validate(['title' => 'Dinner', 'start_time' => '25:00'], $trip);
ok(!$v['ok'], 'a time that is not a time is refused');
$v = rmt_activity_validate(['title' => 'Dinner', 'link' => 'javascript:alert(1)'], $trip);
ok(!$v['ok'], 'a link that is not http is refused, which is the one that matters');
$v = rmt_activity_validate(['title' => 'Dinner', 'category' => 'nonsense', 'join_mode' => 'nonsense',
                            'visibility' => 'public'], $trip);
ok($v['data']['category'] === 'other', 'an unknown category falls back rather than being stored');
ok($v['data']['join_mode'] === 'no', 'and an unknown join mode is the safe one');
ok($v['data']['visibility'] === 'trip', 'and visibility can never be set to something more open');

// --- the city view --------------------------------------------------------------------------
$city = $titles(rmt_activities_in_city(7, null, $from, $to));
ok(in_array('Dinner in Alfama', $city, true), 'a public plan is on the city view');
ok(!in_array('A private dinner', $city, true), 'a private plan is not');
ok(!in_array('Followers-only plan', $city, true), 'a plan on a followers-only trip is not, to a stranger');
ok(!in_array('On a private trip', $city, true), 'and a plan on a private trip is never');
ok(in_array('Followers-only plan', $titles(rmt_activities_in_city(7, $follower, $from, $to)), true),
   'a follower sees the followers-only one');
ok(in_array('Sintra at some point', $city, true), 'a plan with no day counts when the trip overlaps the window');

$narrow = $titles(rmt_activities_in_city(7, null, '2026-10-07', '2026-10-08'));
ok(in_array('Benfica vs Porto', $narrow, true), 'a date window keeps what is inside it');
ok(!in_array('Dinner in Alfama', $narrow, true), 'and drops what is outside it');


// --- filtering the city view --------------------------------------------------------------------
/* A filter narrows what is already allowed. It must never widen it, which is the only way a
   filter can become a leak. */
$pdo->exec("UPDATE trip_activities SET category = 'sport' WHERE id = 5");
$catted = $titles(rmt_activities_in_city(7, null, $from, $to, 30, ['category' => 'sport']));
ok(in_array('Benfica vs Porto', $catted, true), 'a category filter keeps what is in it');
ok(!in_array('Dinner in Alfama', $catted, true), 'and drops what is not');
ok($titles(rmt_activities_in_city(7, null, $from, $to, 30, ['category' => 'nonsense; DROP'])) === $city,
   'a category nobody could have chosen is ignored rather than trusted');

$openOnly = $titles(rmt_activities_in_city(7, null, $from, $to, 30, ['joinable' => true]));
ok(in_array('Benfica vs Porto', $openOnly, true), 'the open plan is offered as joinable');
ok(!in_array('Sintra at some point', $openOnly, true), 'a plan nobody can join is not');
ok(!in_array('A private dinner', $openOnly, true), 'and a filter never widens what a stranger can see');
$pdo->exec("UPDATE trip_activities SET cancelled_at = '$now' WHERE id = 5");
ok(!in_array('Benfica vs Porto', $titles(rmt_activities_in_city(7, null, $from, $to, 30, ['joinable' => true])), true),
   'a cancelled plan is not something to join');
$pdo->exec("UPDATE trip_activities SET cancelled_at = NULL, category = 'food' WHERE id = 5");
$pdo->exec('INSERT INTO blocks VALUES (2,1)');
ok(!in_array('Dinner in Alfama', $titles(rmt_activities_in_city(7, $stranger, $from, $to)), true),
   'somebody who blocked you does not appear on the city view');
$pdo->exec('DELETE FROM blocks');

// --- counting honestly ------------------------------------------------------------------------
$ins(7, 1, 1, 'Dinner in Alfama', 'trip', '2026-10-06');   // the same person, again
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,body,status,visibility,date_from,date_to)
            VALUES (4,4,7,'Dev trip','d','','published','public','$from','$to')");
$ins(8, 4, 4, 'Dinner in Alfama');
$pop = rmt_activity_popular_in_city(7, null, $from, $to);
$dinner = null;
foreach ($pop as $row) if (mb_strtolower($row['label']) === 'dinner in alfama') $dinner = $row;
ok($dinner !== null, 'the thing two people planned is on the popular list');
ok($dinner !== null && $dinner['n'] === 2, 'and it counts people, not rows: one person twice is one');

// --- joining ----------------------------------------------------------------------------------
$open = q_one('SELECT * FROM trip_activities WHERE id = 5');
ok(($open['join_mode'] ?? '') === 'open', 'the match is open to others');
ok((string) (q_one('SELECT join_mode FROM trip_activities WHERE id = 1')['join_mode'] ?? '') === 'no',
   'and a dinner is not, by default');
$pdo->exec("INSERT INTO activity_joins (activity_id,user_id,state,created_at) VALUES (5,2,'going','$now')");
ok(rmt_activity_join_state(5, $stranger) === 'going', 'a join is remembered');
ok(rmt_activity_join_state(5, $follower) === null, 'and belongs to one person');
ok(count(rmt_activity_joiners(5, $owner)) === 1, 'the owner can see who is coming');
$pdo->exec('INSERT INTO blocks VALUES (2,3)');
ok(count(rmt_activity_joiners(5, $follower)) === 0, 'a blocked person is not shown in the list of who is coming');
$pdo->exec('DELETE FROM blocks');


// --- capacity ---------------------------------------------------------------------------------
$pdo->exec('UPDATE trip_activities SET capacity = 2 WHERE id = 5');
$capped = q_one('SELECT * FROM trip_activities WHERE id = 5');
ok(rmt_activity_going_count(5) === 1, 'one person is already coming');
ok(rmt_activity_has_room($capped), 'and there is room for one more');
$pdo->exec("INSERT INTO activity_joins (activity_id,user_id,state,created_at) VALUES (5,3,'going','$now')");
ok(!rmt_activity_has_room(q_one('SELECT * FROM trip_activities WHERE id = 5')),
   'a full plan says it is full');
$pdo->exec('UPDATE trip_activities SET capacity = NULL WHERE id = 5');
ok(rmt_activity_has_room(q_one('SELECT * FROM trip_activities WHERE id = 5')),
   'and no capacity means no limit, which is what almost every plan is');
$pdo->exec('DELETE FROM activity_joins WHERE activity_id = 5 AND user_id = 3');

// --- the meeting point ------------------------------------------------------------------------
$pdo->exec("UPDATE trip_activities SET meeting_point = 'By the fountain' WHERE id = 5");
$withPoint = q_one('SELECT * FROM trip_activities WHERE id = 5');
ok(rmt_activity_meeting_point_visible($withPoint, $owner), 'the owner sees the meeting point');
ok(rmt_activity_meeting_point_visible($withPoint, $stranger), 'and so does somebody who is going');
ok(!rmt_activity_meeting_point_visible($withPoint, $follower), 'somebody who is not coming does not');
ok(!rmt_activity_meeting_point_visible($withPoint, null), 'and a stranger who is not signed in never does');
$pdo->exec("UPDATE activity_joins SET state = 'requested' WHERE activity_id = 5 AND user_id = 2");
ok(!rmt_activity_meeting_point_visible($withPoint, $stranger),
   'somebody who has only asked, and not been answered, does not see it either');
$pdo->exec("UPDATE activity_joins SET state = 'going' WHERE activity_id = 5 AND user_id = 2");

// --- the request lifecycle ---------------------------------------------------------------------
$pdo->exec("INSERT INTO activity_joins (activity_id,user_id,state,created_at) VALUES (1,3,'requested','$now')");
ok(count(rmt_activity_requests(1, $owner, 'requested')) === 1, 'the owner can see who asked');
ok(count(rmt_activity_requests(1, $owner, 'going')) === 0, 'and they are not counted as coming');
$pdo->exec("UPDATE activity_joins SET state = 'declined' WHERE activity_id = 1 AND user_id = 3");
ok(rmt_activity_join_state(1, $follower) === 'declined', 'a no is remembered rather than forgotten');
ok(count(rmt_activity_requests(1, $owner, 'requested')) === 0, 'and the ask leaves the pending list');

$pdo->exec('INSERT INTO blocks VALUES (3,1)');
ok(count(rmt_activity_requests(1, $owner, 'declined')) === 0,
   'somebody who blocked you does not appear in your own lists');
$pdo->exec('DELETE FROM blocks');


// --- what I could join ------------------------------------------------------------------------
/* The end of the sentence the product is built toward: people overlap my dates and I can join
   whichever fits me. Ben gets a trip to Lisbon in the same window as Ana's plans. */
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,body,status,visibility,date_from,date_to)
            VALUES (9,2,7,'Ben in Lisbon','bl','','published','public','$from','$to')");
/* Ben said yes to the match earlier in this file. Something already answered is not something to
   offer again, which is itself the rule being relied on here, so the row goes first. */
$pdo->exec('DELETE FROM activity_joins WHERE user_id = 2');
$joinTitles = static fn(int $uid): array =>
    array_map(static fn(array $r) => (string) $r['title'], rmt_activities_joinable_for($uid));

ok(in_array('Benfica vs Porto', $joinTitles(2), true), 'an open plan on my dates is offered');
ok(!in_array('Dinner in Alfama', $joinTitles(2), true), 'a plan nobody can join is not');
ok(!in_array('A private dinner', $joinTitles(2), true), 'and a private one never is');
ok(!in_array('On a private trip', $joinTitles(2), true), 'nor one on a trip I cannot see');

$pdo->exec("UPDATE trip_activities SET cancelled_at = '$now' WHERE id = 5");
ok(!in_array('Benfica vs Porto', $joinTitles(2), true), 'a cancelled plan is not something to join');
$pdo->exec("UPDATE trip_activities SET cancelled_at = NULL WHERE id = 5");
ok(in_array('Benfica vs Porto', $joinTitles(2), true), 'and it is back when the plan is back on');

ok($joinTitles(3) === [], 'somebody with no trip to that city is offered nothing');
ok(!in_array('Benfica vs Porto', $joinTitles(1), true), 'and nobody is offered their own plan');

/* Two trips to the same city, overlapping. The same plan offered twice is the bug that a join
   instead of an EXISTS gives you, and it looks like the site is inventing activity. */
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,body,status,visibility,date_from,date_to)
            VALUES (11,2,7,'Ben again','bl2','','published','public','$from','$to')");
$again = $joinTitles(2);
ok(count($again) === count(array_unique($again)), 'two trips to one city never offer the same plan twice');
$pdo->exec('DELETE FROM trips WHERE id = 11');



// --- the loop closing -------------------------------------------------------------------------
/* PLAN then DO then SAY SO. Only the member's own plans, only after the day, only while the answer
   is still easy, and only until it is answered. */
$pdo->exec("UPDATE trip_activities SET day = '2026-09-08' WHERE id = 1");
$revTitles = static fn(int $uid): array =>
    array_map(static fn(array $r) => (string) $r['title'], rmt_activities_to_review($uid, 10));

ok(in_array('Dinner in Alfama', $revTitles(1), true), 'a plan whose day has passed is asked about');
ok(!in_array('Dinner in Alfama', $revTitles(2), true), 'and only of the person whose plan it is');
ok(!in_array('Benfica vs Porto', $revTitles(1), true), 'a plan that has not happened yet is not');

$pdo->exec('UPDATE trip_activities SET done = 1 WHERE id = 1');
ok(!in_array('Dinner in Alfama', $revTitles(1), true), 'once answered it stops asking');
$pdo->exec('UPDATE trip_activities SET done = 0 WHERE id = 1');

$pdo->exec("UPDATE trip_activities SET cancelled_at = '$now' WHERE id = 1");
ok(!in_array('Dinner in Alfama', $revTitles(1), true), 'and nobody is asked how a cancelled plan went');
$pdo->exec('UPDATE trip_activities SET cancelled_at = NULL WHERE id = 1');

$pdo->exec("UPDATE trip_activities SET day = '2026-07-12' WHERE id = 1");
ok(!in_array('Dinner in Alfama', $revTitles(1), true), 'a plan from two months ago is homework, so it is dropped');


// --- what the people who went say --------------------------------------------------------------
/* A recommendation is the most reusable thing on the site, and also the easiest thing to leak: it
   carries a name, a place and a date range. It obeys exactly the same visibility rule as the plan
   it came from. */
$pdo->exec('UPDATE trip_activities SET recommend = 1, done = 1 WHERE id IN (1,3,4,6)');
$recLabels = static fn(?array $viewer): array =>
    array_map(static fn(array $r) => (string) $r['label'], rmt_activity_recommended_in_city(7, $viewer));

$anonRecs = $recLabels(null);
ok(in_array('Dinner in Alfama', $anonRecs, true), 'a public plan that was worth it is said so');
ok(!in_array('A private dinner', $anonRecs, true), 'a private plan never is');
ok(!in_array('On a private trip', $anonRecs, true), 'nor one on a private trip');
ok(!in_array('Followers-only plan', $anonRecs, true), 'nor a followers-only one, to a stranger');
ok(in_array('Followers-only plan', $recLabels($follower), true), 'a follower does see theirs');

$recRow = null;
foreach (rmt_activity_recommended_in_city(7, null) as $r) if ($r['label'] === 'Dinner in Alfama') $recRow = $r;
ok($recRow !== null && $recRow['n'] === 1, 'one person who went is counted as one, never rounded up');
ok($recRow !== null && $recRow['users'] === ['ana'], 'and named, because a name is the point');

$pdo->exec('INSERT INTO blocks VALUES (2,1)');
ok(!in_array('Dinner in Alfama', $recLabels($stranger), true),
   'somebody who blocked you does not recommend anything to you');
$pdo->exec('DELETE FROM blocks');
$pdo->exec('UPDATE trip_activities SET recommend = NULL, done = 0');

echo "activities_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
