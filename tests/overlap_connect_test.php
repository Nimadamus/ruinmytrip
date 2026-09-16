<?php
/**
 * Learning that somebody's dates landed on yours, and being able to say you would like to meet.
 *
 * The two halves have opposite failure modes, which is why they are tested together.
 *
 * The NOTIFICATION fails by being too loud. Every trip posted, every edit, every retried request
 * is an opportunity to tap the same person on the shoulder again, and a product that does that
 * gets its notifications turned off once and forever. So the rules here are about restraint: one
 * per recipient per trip ever, one per pair per city while the first is still unread, nothing at
 * all for a trip that moved out of the way, and nothing for anybody who said they are not looking
 * to meet.
 *
 * The CONNECT fails by being too permissive. It is the first thing one stranger does to another
 * on this site, so: no words, nothing private disclosed, no enrolment, no second ask after a no,
 * and a database-level unique index rather than a check in code, because a check in code is a race
 * two tabs can win.
 *
 * The last section is a guard on two defects fixed in Task 3, which Nima asked to stay fixed.
 *
 *   php tests/overlap_connect_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
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
require BASE_PATH . '/app/connects.php';

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-54s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}
function rmt_is_blocked(int $a, int $b): bool {
    return (bool) q_one('SELECT 1 FROM blocks WHERE (blocker_id=? AND blocked_id=?) OR (blocker_id=? AND blocked_id=?)',
                        [$a, $b, $b, $a]);
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
              title TEXT, slug TEXT, body TEXT, status TEXT DEFAULT 'published',
              visibility TEXT DEFAULT 'public', date_from TEXT, date_to TEXT,
              travel_style TEXT, open_to_meeting INT, created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT,
              actor_id INT, target_type TEXT, target_id INT, created_at TEXT, read_at TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/092_trip_connects.sqlite.sql'));
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (1,'bangkok-thailand','Bangkok'),(2,'lisbon-portugal','Lisbon')");
foreach ([[1,'host'],[2,'sarah'],[3,'quiet'],[4,'blocked_one'],[5,'other']] as [$id,$n]) {
    $pdo->exec("INSERT INTO users (id,username) VALUES ($id,'$n')");
    $pdo->exec("INSERT INTO profiles (user_id) VALUES ($id)");
}
$pdo->exec('INSERT INTO blocks (blocker_id, blocked_id) VALUES (4, 1)');

$d = static fn(int $days): string => gmdate('Y-m-d', strtotime("+$days days"));
$mk = static function (int $uid, int $dest, string $from, string $to,
                       ?int $meet = null, string $vis = 'public') use ($pdo): int {
    $st = $pdo->prepare("INSERT INTO trips (user_id,destination_id,title,slug,date_from,date_to,
                           visibility,open_to_meeting,status,created_at)
                         VALUES (?,?,'t','t',?,?,?,?, 'published','2026-09-15')");
    $st->execute([$uid, $dest, $from, $to, $vis, $meet]);
    return (int) $pdo->lastInsertId();
};
$count = static fn(int $forUser): int => (int) db()->query(
    "SELECT COUNT(*) FROM notifications WHERE user_id = $forUser AND type = '" . RMT_MATCH_NOTIFY_TYPE . "'")->fetchColumn();

/* host is in Bangkok for eleven days, and is the person everything below is news to. */
$hostTrip = $mk(1, 1, $d(30), $d(40));

echo "-- an overlap is news, once --\n";
$sarah = $mk(2, 1, $d(33), $d(43));
ok('a partial overlap tells the host', rmt_match_notify(2, $sarah, 1, $d(33), $d(43), 'public'), 1);
ok('...and the same trip never tells them again', rmt_match_notify(2, $sarah, 1, $d(33), $d(43), 'public'), 0);
ok('one row, not two', $count(1), 1);

$sarahExact = $mk(2, 1, $d(30), $d(40));
ok('a second trip to the same city, while the first is unread, says nothing',
   rmt_match_notify(2, $sarahExact, 1, $d(30), $d(40), 'public'), 0);
$pdo->exec("UPDATE notifications SET read_at = '2026-09-15 10:00:00' WHERE user_id = 1");
$sarahThird = $mk(2, 1, $d(31), $d(39));
ok('...and says something again once the first has been read',
   rmt_match_notify(2, $sarahThird, 1, $d(31), $d(39), 'public'), 1);

echo "\n-- who is never told --\n";
/* A clean city with exactly one resident, so a count is about the person under test rather than
   about everybody else who happens to be in Bangkok that week. rmt_match_notify() returns how many
   people it told IN TOTAL, which is the right return value and the wrong thing to assert on. */
$pdo->exec('DELETE FROM notifications');
$hostLisbon = $mk(1, 2, $d(30), $d(40));
$hostGot = static fn(): int => (int) db()->query(
    "SELECT COUNT(*) FROM notifications WHERE user_id = 1 AND type = '" . RMT_MATCH_NOTIFY_TYPE . "'")->fetchColumn();

$noOverlap = $mk(5, 2, $d(60), $d(70));
rmt_match_notify(5, $noOverlap, 2, $d(60), $d(70), 'public');
ok('dates that do not touch tell nobody', $hostGot(), 0);

$shy = $mk(3, 2, $d(30), $d(40), 0);
rmt_match_notify(3, $shy, 2, $d(30), $d(40), 'public');
ok('somebody who said they are not looking to meet announces nothing', $hostGot(), 0);

$blocked = $mk(4, 2, $d(30), $d(40));
rmt_match_notify(4, $blocked, 2, $d(30), $d(40), 'public');
ok('somebody who blocked the host tells the host nothing', $hostGot(), 0);

$hidden = $mk(5, 2, $d(32), $d(38));
rmt_match_notify(5, $hidden, 2, $d(32), $d(38), 'followers');
ok('a followers only trip is not an announcement', $hostGot(), 0);
rmt_match_notify(5, $hidden, 2, $d(32), $d(38), 'private');
ok('and a private one is a note to yourself', $hostGot(), 0);

/* The host says they are not looking to meet: now nobody's trip reaches THEM either. Both
   directions, because "either traveler has opted out" is the rule. */
$pdo->exec("UPDATE trips SET open_to_meeting = 0 WHERE id = $hostLisbon");
$again = $mk(5, 2, $d(34), $d(36));
rmt_match_notify(5, $again, 2, $d(34), $d(36), 'public');
ok('a host who opted out is not told about overlaps either', $hostGot(), 0);
$pdo->exec("UPDATE trips SET open_to_meeting = NULL WHERE id = $hostLisbon");

/* And the same dates in a city the host is not going to are not news to the host. */
$pdo->exec('DELETE FROM notifications');
$elsewhere = $mk(5, 1, $d(30), $d(40));
rmt_match_notify(5, $elsewhere, 1, $d(30), $d(40), 'public');
ok('the same dates in another city tell the host nothing about Lisbon', $hostGot(), 1);
$pdo->exec('DELETE FROM notifications');

echo "\n-- a trip that moves takes its unread news with it --\n";
$mover = $mk(5, 2, $d(30), $d(40));
rmt_match_notify(5, $mover, 2, $d(30), $d(40), 'public');
ok('the host was told', $hostGot(), 1);
rmt_match_notify_clear($mover, []);
ok('moving out of the way removes the unread row', $hostGot(), 0);

/* A row already read is history, not ours to rewrite. */
$pdo->exec('DELETE FROM notifications');
$mover2 = $mk(5, 2, $d(31), $d(39));
rmt_match_notify(5, $mover2, 2, $d(31), $d(39), 'public');
$pdo->exec("UPDATE notifications SET read_at = '2026-09-15 10:00:00'");
ok('a notification they have already read is left alone', rmt_match_notify_clear($mover2, []), 0);
ok('...and is still there', $hostGot(), 1);

/* And the people it still lands on keep theirs. */
$pdo->exec('DELETE FROM notifications');
$mover3 = $mk(5, 2, $d(30), $d(40));
rmt_match_notify(5, $mover3, 2, $d(30), $d(40), 'public');
rmt_match_notify_clear($mover3, [1]);
ok('somebody it still overlaps keeps theirs', $hostGot(), 1);

echo "\n-- saying you would like to meet --\n";
$pdo->exec('DELETE FROM trip_connects');
$r = rmt_connect_request(2, $hostTrip);
ok('the request is recorded', $r['state'], 'interested');
ok('...as a new one', $r['created'], true);
$again2 = rmt_connect_request(2, $hostTrip);
ok('pressing again is the same request', $again2['created'], false);
ok('...still in the same state', $again2['state'], 'interested');
ok('one row, whatever the browser does',
   (int) $pdo->query("SELECT COUNT(*) FROM trip_connects WHERE trip_id = $hostTrip")->fetchColumn(), 1);

$own = rmt_connect_request(1, $hostTrip);
ok('nobody asks to meet themselves', $own['ok'], false);
$shutTrip = $mk(3, 1, $d(30), $d(40), 0);
$shutAsk = rmt_connect_request(2, $shutTrip);
ok('a traveler who said no to meeting cannot be asked', $shutAsk['ok'], false);
ok('...and is told why, without a name', $shutAsk['reason'], 'That traveler is not looking to meet up.');
$blockedTrip = $mk(4, 1, $d(30), $d(40));
ok('a block stops the asking too', rmt_connect_request(1, $blockedTrip)['ok'], false);
$privateTrip = $mk(5, 1, $d(30), $d(40), 1, 'private');
ok('a trip somebody cannot see cannot be asked about', rmt_connect_request(2, $privateTrip)['ok'], false);
$overTrip = $mk(5, 1, '2020-01-01', '2020-01-09');
ok('a trip that is over is not something to meet on', rmt_connect_request(2, $overTrip)['ok'], false);

echo "\n-- the answer belongs to the person asked --\n";
$c = rmt_connect_get($hostTrip, 2);
ok('a stranger cannot answer for the host', rmt_connect_decide(5, (int) $c['id'], 'accept')['ok'], false);
$dec = rmt_connect_decide(1, (int) $c['id'], 'accept');
ok('the host can', $dec['state'], 'accepted');
ok('...and it changed something', $dec['changed'], true);
ok('answering twice does not change it again', rmt_connect_decide(1, (int) $c['id'], 'decline')['changed'], false);
ok('the answer stands', (string) rmt_connect_get($hostTrip, 2)['state'], 'accepted');
ok('and only now can they message each other', rmt_connect_mutual(1, 2), true);
ok('two strangers still cannot', rmt_connect_mutual(2, 5), false);

$pdo->exec("UPDATE trip_connects SET state = 'declined' WHERE trip_id = $hostTrip AND from_user_id = 2");
$retry = rmt_connect_request(2, $hostTrip);
ok('a no is not a door to knock on again', $retry['created'], false);
ok('...and the no stands', $retry['state'], 'declined');

$t2 = $mk(5, 2, $d(30), $d(40));
rmt_connect_request(2, $t2);
$c2 = rmt_connect_get($t2, 2);
ok('the asker can take it back', rmt_connect_withdraw(2, (int) $c2['id']), true);
ok('...but not twice', rmt_connect_withdraw(2, (int) $c2['id']), false);
ok('and changing their mind back is allowed', rmt_connect_request(2, $t2)['state'], 'interested');
ok('nobody else can withdraw it', rmt_connect_withdraw(5, (int) $c2['id']), false);

echo "\n-- what a connect does NOT carry --\n";
$cols = array_column($pdo->query('PRAGMA table_info(trip_connects)')->fetchAll(), 'name');
foreach (['body', 'message', 'text', 'email', 'phone', 'address', 'lat', 'lng'] as $forbidden) {
    ok("there is nowhere to put a $forbidden", in_array($forbidden, $cols, true), false);
}

echo "\n-- two defects from the last task, kept fixed --\n";
/* 1. The matches page assembled itself before reading the reader's own trips, which printed PHP
      warnings above the content for anybody whose first trip was their only one. The controller
      must still read the plans before it builds the cities from them. */
$ctrl = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
$posPlans  = strpos($ctrl, '$myPlans = rmt_going_list_for_profile($uid, $me);');
$posCities = strpos($ctrl, 'foreach ($myPlans as $mp) {');
ok('the page reads the reader\'s trips before it builds from them',
   $posPlans !== false && $posCities !== false && $posPlans < $posCities, true);
/* 2. The same trip posted twice made two identical rows. The guard is a lookup on user, city and
      both dates before the insert. */
ok('an identical trip is looked for before another is written',
   (bool) preg_match('/SELECT id, slug FROM trips\s+WHERE user_id = \? AND destination_id = \? AND date_from = \? AND date_to = \?/', $ctrl), true);
ok('...and lands on the one that already exists',
   str_contains($ctrl, "You have already posted that trip."), true);

/* 3. And one found in this task, the same class of defect as the first two: the edit form marked
      body as required, so a trip posted as a city and two dates could never be edited again. The
      browser refused to submit and the Save button did nothing at all: no message, no error. A
      trip that cannot be edited cannot be moved into or out of an overlap, which is half of what
      this task is about. */
$editForm = (string) file_get_contents(BASE_PATH . '/views/trip_edit.php');
$fields = [];
foreach (['title', 'body'] as $name) {
    preg_match('/<(?:input|textarea)[^>]*name="' . $name . '"[^>]*>/', $editForm, $m);
    $fields[$name] = $m[0] ?? '';
}
ok('the edit form still has a body field', $fields['body'] !== '', true);
ok('a trip with no story can still be saved', str_contains($fields['body'], 'required'), false);
ok('and a trip with no title can too', str_contains($fields['title'], 'required'), false);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL OVERLAP AND CONNECT TESTS PASS ({$pass})\n";
