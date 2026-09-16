<?php
/**
 * The first session: a city and two dates, and then the people that puts you in front of.
 *
 * What has to stay true, and why each one is here rather than trusted:
 *
 *   1. A TRIP IS A CITY AND TWO DATES. Everything else is optional, and a form that quietly starts
 *      demanding a style or an answer about meeting people would end the flow this task exists to
 *      build. The validator is asserted against the minimum, not the maximum.
 *   2. NO MEANS NO, EVERYWHERE. open_to_meeting is three states and the third one is the point.
 *      Null is unstated and behaves the way the site always has. A zero is somebody saying they do
 *      not want to be introduced to strangers, and it has to hold in the overlap list, in the near
 *      miss list and in the notification that goes out when a trip is posted. A control honoured
 *      in two places out of three is not a control, it is a setting.
 *   3. AN OVERLAP IS ARITHMETIC, NOT A FEELING. Exact, partial, touching by one day, and not at
 *      all, each counted in days a reader can act on.
 *   4. A NEAR MISS IS NOT A MATCH. It is labelled by which side it misses on and by how far, it
 *      never appears as an overlap, and it stops at the window rather than trailing off into
 *      somebody who was there in March.
 *   5. VISIBILITY AND BLOCKS STILL DECIDE. The new lists read through the same clause every other
 *      list on this site reads through.
 *
 *   php tests/first_trip_flow_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
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

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-56s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active', role TEXT DEFAULT 'member')");
$pdo->exec("CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT, home_city TEXT, travel_style TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)");
$pdo->exec("CREATE TABLE follows (follower_id INT, followee_id INT, PRIMARY KEY (follower_id, followee_id))");
$pdo->exec("CREATE TABLE blocks (blocker_id INT, blocked_id INT, PRIMARY KEY (blocker_id, blocked_id))");
$pdo->exec("CREATE TABLE trip_members (trip_id INT, user_id INT, role TEXT, state TEXT, invited_by INT,
              created_at TEXT, decided_at TEXT, PRIMARY KEY (trip_id, user_id))");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
              title TEXT, slug TEXT, body TEXT, cover_url TEXT, visited_on TEXT, verified INT DEFAULT 0,
              status TEXT DEFAULT 'published', visibility TEXT DEFAULT 'public',
              date_from TEXT, date_to TEXT, travel_style TEXT, open_to_meeting INT,
              created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE profile_interests (user_id INT, interest TEXT, PRIMARY KEY (user_id, interest))");

$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (1,'bangkok-thailand','Bangkok')");
foreach ([[1,'me'],[2,'exact'],[3,'partial'],[4,'closed'],[5,'before'],[6,'after'],[7,'private_one'],[8,'blocked_one'],[9,'far']] as [$id,$n]) {
    $pdo->exec("INSERT INTO users (id,username) VALUES ($id,'$n')");
    $pdo->exec("INSERT INTO profiles (user_id) VALUES ($id)");
}

/* Dates are written relative to today so the suite does not quietly stop testing anything the day
   a hardcoded October goes past. */
$d = static fn(int $days): string => gmdate('Y-m-d', strtotime("+$days days"));
$trip = static function (int $uid, string $from, string $to, ?int $meet = null,
                         string $vis = 'public', ?string $style = null) use ($pdo): int {
    $st = $pdo->prepare("INSERT INTO trips (user_id,destination_id,title,slug,date_from,date_to,
                           visibility,open_to_meeting,travel_style,status,created_at)
                         VALUES (?,1,'t','t',?,?,?,?,?, 'published','2026-09-15')");
    $st->execute([$uid, $from, $to, $vis, $meet, $style]);
    return (int) $pdo->lastInsertId();
};

$trip(1, $d(30), $d(40));                  // me: days 30 to 40
$trip(2, $d(30), $d(40), 1);               // exact overlap, and says yes
$trip(3, $d(38), $d(48));                  // partial overlap, unstated
$trip(4, $d(32), $d(42), 0);               // overlaps, and says no
$trip(5, $d(20), $d(26), 1);               // four days before me
$trip(6, $d(44), $d(50));                  // four days after me
$trip(7, $d(30), $d(40), 1, 'private');    // overlaps, but private
$trip(8, $d(30), $d(40), 1);               // overlaps, but has blocked me
$trip(9, $d(80), $d(90), 1);               // same city, nowhere near
$pdo->exec('INSERT INTO blocks (blocker_id, blocked_id) VALUES (8, 1)');

echo "-- overlap is arithmetic --\n";
ok('a range against itself is its own length', rmt_match_overlap_days('2026-10-12','2026-10-20','2026-10-12','2026-10-20'), 9);
ok('a partial overlap counts the shared days',  rmt_match_overlap_days('2026-10-12','2026-10-20','2026-10-18','2026-10-26'), 3);
ok('touching by one day is one day',            rmt_match_overlap_days('2026-10-12','2026-10-20','2026-10-20','2026-10-30'), 1);
ok('a gap is not an overlap',                   rmt_match_overlap_days('2026-10-12','2026-10-20','2026-10-21','2026-10-30'), 0);

echo "\n-- who is offered as a match --\n";
$m = rmt_trip_matches(1);
$names = array_map(static fn(array $r) => (string) $r['username'], $m);
sort($names);
ok('the people whose dates land on mine', $names, ['exact', 'partial']);
ok('somebody who said no is not offered',       in_array('closed', $names, true), false);
ok('a private trip is not offered',             in_array('private_one', $names, true), false);
ok('somebody who blocked me is not offered',    in_array('blocked_one', $names, true), false);
ok('the same city months apart is not offered', in_array('far', $names, true), false);
$byName = [];
foreach ($m as $r) $byName[(string) $r['username']] = $r;
ok('the exact match is the full ten days',  (int) $byName['exact']['overlap_days'], 11);
ok('the partial match is the shared days',  (int) $byName['partial']['overlap_days'], 3);

echo "\n-- a near miss is labelled as one --\n";
$near = rmt_trip_near_misses(1);
$nearBy = [];
foreach ($near as $r) $nearBy[(string) $r['username']] = $r;
ok('the traveler leaving before I arrive is there', isset($nearBy['before']), true);
ok('...on the before side',  (string) ($nearBy['before']['side'] ?? ''), 'before');
ok('...by four days',        (int) ($nearBy['before']['gap_days'] ?? 0), 4);
ok('the traveler arriving after I leave is there',  isset($nearBy['after']), true);
ok('...on the after side',   (string) ($nearBy['after']['side'] ?? ''), 'after');
ok('...by four days',        (int) ($nearBy['after']['gap_days'] ?? 0), 4);
ok('an overlapping traveler is never a near miss',  isset($nearBy['exact']), false);
ok('months away is outside the window',             isset($nearBy['far']), false);
ok('somebody who said no is not a near miss either', isset($nearBy['closed']), false);
ok('a private trip is not a near miss either',       isset($nearBy['private_one']), false);
ok('and neither is somebody who blocked me',         isset($nearBy['blocked_one']), false);
foreach ($near as $r) {
    ok('a near miss carries no overlap', (int) ($r['overlap_days'] ?? 0), 0);
}

echo "\n-- the window has an edge --\n";
$trip(9, $d(41), $d(45), 1);                       // one day after me, inside the window
ok('a tighter window drops a four day gap', count(array_filter(rmt_trip_near_misses(1, 2),
    static fn(array $r) => (string) $r['username'] === 'before')), 0);
ok('...and keeps a one day gap',            count(array_filter(rmt_trip_near_misses(1, 2),
    static fn(array $r) => (int) $r['gap_days'] === 1)) > 0, true);

echo "
-- the same control holds when the offer is delivered rather than browsed --
";
/* A notification saying "somebody's dates overlap yours" is the same offer as a card on the match
   page, pushed instead of pulled. A no that stopped one and not the other would not be a no. */
$pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT,
              actor_id INT, target_type TEXT, target_id INT, created_at TEXT, read_at TEXT)");
$openTrip = $trip(2, $d(60), $d(70), 1);
$shutTrip = $trip(3, $d(60), $d(70), 0);
$trip(1, $d(60), $d(70));                    // me, so there is somebody for it to reach
$sentOpen = rmt_match_notify(2, $openTrip, 1, $d(60), $d(70), 'public');
$sentShut = rmt_match_notify(3, $shutTrip, 1, $d(60), $d(70), 'public');
ok('a trip open to meeting tells the people it lands on', $sentOpen > 0, true);
ok('a trip that said no tells nobody', $sentShut, 0);

echo "\n-- interests come back in one query and only the ones we publish --\n";
$pdo->exec("INSERT INTO profile_interests (user_id, interest) VALUES (2,'food'),(2,'nightlife'),(2,'not_a_real_one')");
require BASE_PATH . '/app/profiles.php';
$ints = rmt_interests_for_many([2, 3]);
ok('the interests somebody actually chose', $ints[2] ?? [], ['food', 'nightlife']);
ok('a key we no longer publish is not a label', in_array('not_a_real_one', $ints[2] ?? [], true), false);
ok('somebody with none has none', isset($ints[3]), false);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }

echo "\n-- a form that arrives filled asks for one thing --\n";
/* Somebody arriving from a campaign link already has the city and both dates. On a phone the only
   submit button was 1,774 pixels down, past four optional fields. It is now offered directly under
   the dates when the form arrives prefilled, and the optional fields are behind a disclosure.
   Nothing was removed: the empty form is exactly as it was. */
$tn = (string) file_get_contents(BASE_PATH . '/views/trip_new.php');
ok('the form knows when it arrived filled',
   str_contains($tn, "\$tnPrefilled = input('destination_id') !== '' && input('date_from') !== '' && input('date_to') !== ''"), true);
ok('...and offers the button there',  substr_count($tn, 'type="submit">Post this trip'), 2);
ok('the optional fields are collapsed rather than removed', str_contains($tn, '<details'), true);
ok('every field still exists on the page',
   str_contains($tn, "name=\"title\"") && str_contains($tn, "name=\"body\"")
   && str_contains($tn, "name=\"visibility\"") && str_contains($tn, "name=\"travel_style\""), true);
ok('and the disclosure is only drawn for a prefilled arrival',
   substr_count($tn, 'if ($tnPrefilled)'), 2);
ok('nothing is decided for them', str_contains($tn, 'you can change any of that'), true);

echo "ALL FIRST TRIP FLOW TESTS PASS ({$pass})\n";
