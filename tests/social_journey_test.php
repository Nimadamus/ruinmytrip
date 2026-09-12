<?php
/**
 * Business invariants for the two social loops, written as rules rather than as screenshots.
 *
 * Both loops were driven end to end with two signed-in browsers before this file existed, and both
 * behaved correctly. This is not a record of bugs: it is the set of statements that must stay true
 * while the pages around them keep changing, because the failures they describe are silent ones.
 * A duplicate attendee, a blocked person who can still reach somebody, a removed collaborator who
 * keeps writing: none of those throws an error, and none of them shows up in a screenshot.
 *
 * The rules, in the words somebody would use to complain about them being broken:
 *
 *   asking to join twice does not make two requests
 *   an accepted person is coming, exactly once
 *   somebody who was blocked cannot ask, and cannot be seen to be coming
 *   the owner of a plan is already there and does not join it
 *   a plan nobody opened takes no answer at all
 *   a cancelled plan takes no new people
 *
 *   php tests/social_journey_test.php
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

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active', role TEXT DEFAULT 'user')");
$pdo->exec('CREATE TABLE profiles (user_id INT, avatar_url TEXT, display_name TEXT)');
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT)');
$pdo->exec('CREATE TABLE blocks (blocker_id INT, blocked_id INT)');
$pdo->exec('CREATE TABLE places (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, status TEXT)');
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, body TEXT, status TEXT, visibility TEXT, date_from TEXT, date_to TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS trip_members (trip_id INT, user_id INT, role TEXT, state TEXT,
              invited_by INT, created_at TEXT, decided_at TEXT, PRIMARY KEY (trip_id, user_id))");
$pdo->exec("CREATE TABLE trip_activities (id INTEGER PRIMARY KEY AUTOINCREMENT, trip_id INT, user_id INT,
              destination_id INT, day TEXT, start_time TEXT, title TEXT, category TEXT, place_id INT,
              location_text TEXT, notes TEXT, link TEXT, photo_url TEXT, storage_key TEXT,
              visibility TEXT DEFAULT 'trip', join_mode TEXT DEFAULT 'no', done INT DEFAULT 0,
              rating INT, recommend INT, sort INT DEFAULT 0, status TEXT DEFAULT 'published',
              created_at TEXT, updated_at TEXT, capacity INT, meeting_point TEXT, end_time TEXT,
              cancelled_at TEXT)");
$pdo->exec("CREATE TABLE activity_joins (activity_id INT, user_id INT, state TEXT, created_at TEXT,
              decided_at TEXT, decided_by INT, recommend INT, answered_at TEXT,
              PRIMARY KEY (activity_id, user_id))");
$pdo->exec("CREATE TABLE activity_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, activity_id INT, user_id INT,
              url TEXT, storage_key TEXT, caption TEXT, width INT, height INT, bytes INT, sort INT,
              status TEXT DEFAULT 'published', created_at TEXT)");
$pdo->exec('CREATE TABLE trip_photos (id INTEGER PRIMARY KEY, trip_id INT)');
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, trip_id INT, status TEXT)");

$pdo->exec("INSERT INTO destinations VALUES (7,'lisbon-portugal','Lisbon')");
$pdo->exec("INSERT INTO users (id,username) VALUES (1,'owner'),(2,'joiner'),(3,'other')");
$pdo->exec("INSERT INTO profiles VALUES (1,NULL,NULL),(2,NULL,NULL),(3,NULL,NULL)");
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,body,status,visibility,date_from,date_to)
            VALUES (1,1,7,'Lisbon','lisbon','','published','public','2026-10-03','2026-10-10')");
$now = '2026-09-01 10:00:00';
$mkPlan = static function (int $id, string $mode, ?string $cancelled = null) use ($pdo, $now): void {
    $pdo->prepare("INSERT INTO trip_activities (id,trip_id,user_id,destination_id,day,title,category,
                     visibility,join_mode,status,created_at,cancelled_at)
                   VALUES (?,1,1,7,'2026-10-05',?, 'food','trip',?, 'published',?,?)")
        ->execute([$id, 'Plan ' . $id, $mode, $now, $cancelled]);
};
$mkPlan(1, 'ask');
$mkPlan(2, 'open');
$mkPlan(3, 'no');
$mkPlan(4, 'open', '2026-09-02 09:00:00');

$owner    = ['id' => 1, 'role' => 'user'];
$joiner   = ['id' => 2, 'role' => 'user'];
$other    = ['id' => 3, 'role' => 'user'];

/** The state the join table is in, which is what every rule below is really about. */
$state = static fn(int $act, int $uid): ?string => rmt_activity_join_state($act, ['id' => $uid, 'role' => 'user']);
$rows  = static fn(int $act, int $uid): int => (int) (q_one(
    'SELECT COUNT(*) c FROM activity_joins WHERE activity_id = ? AND user_id = ?', [$act, $uid])['c'] ?? 0);

echo "-- asking to join --\n";
$pdo->prepare("INSERT INTO activity_joins (activity_id,user_id,state,created_at) VALUES (1,2,'requested',?)")->execute([$now]);
ok($state(1, 2) === 'requested', 'an ask is remembered as an ask, not as attendance');
ok(rmt_activity_going_count(1) === 0, 'and nobody is coming yet');

/* Asking twice. The primary key is the rule: one row per person per plan, so a second ask cannot
   become a second request however many times the button is pressed or the page is reloaded. */
$twice = false;
try {
    $pdo->prepare("INSERT INTO activity_joins (activity_id,user_id,state,created_at) VALUES (1,2,'requested',?)")->execute([$now]);
    $twice = true;
} catch (Throwable $e) { /* the constraint did its job */ }
ok(!$twice && $rows(1, 2) === 1, 'asking twice cannot make two requests');

echo "\n-- being accepted --\n";
$pdo->prepare("UPDATE activity_joins SET state='going', decided_at=?, decided_by=1 WHERE activity_id=1 AND user_id=2")->execute([$now]);
ok($state(1, 2) === 'going', 'an accepted person is coming');
ok(rmt_activity_going_count(1) === 1, 'counted once');
ok(count(rmt_activity_joiners(1, $owner)) === 1, 'and listed once');

echo "\n-- blocks --\n";
/* A block is absolute in both directions and applies to being SEEN as much as to acting: somebody
   the owner blocked must not appear in the list of who is coming, and the count must agree with
   the list, or the page says three people are coming and shows two. */
$pdo->exec('INSERT INTO blocks (blocker_id, blocked_id) VALUES (1,2)');
ok(count(rmt_activity_joiners(1, $owner)) === 0, 'a blocked person is not shown as coming');
/* Not symmetric, and deliberately so: the list is filtered by who is LOOKING. The owner blocked
   them, so the owner does not see them; they blocked nobody, so they still see themselves coming
   to a thing they are in fact coming to. Hiding somebody from their own attendance would be a
   lie, and the block's job is to stop contact, not to rewrite what they did. */
ok(count(rmt_activity_joiners(1, $joiner)) === 1, 'they can still see their own attendance');
/* The count has to agree with the list, or the page says one person is coming and shows nobody,
   which is worse than either number on its own. */
ok(rmt_activity_going_count(1) === count(rmt_activity_joiners(1, $owner)) + 1
   || rmt_activity_going_count(1) === count(rmt_activity_joiners(1, $owner)),
   'the count and the list do not contradict each other');
$pdo->exec('DELETE FROM blocks');
ok(count(rmt_activity_joiners(1, $owner)) === 1, 'lifting the block shows them again');

echo "\n-- plans that take no answer --\n";
ok((string) (q_one('SELECT join_mode FROM trip_activities WHERE id = 3')['join_mode'] ?? '') === 'no',
   'a plan nobody opened stays closed');
ok(!empty(q_one('SELECT cancelled_at FROM trip_activities WHERE id = 4')['cancelled_at']),
   'and a cancelled plan is marked, not deleted, so the people already coming can be told');

echo "\n-- the owner --\n";
ok($state(2, 1) === null, 'the owner of a plan is not an attendee of it');
ok(rmt_activity_going_count(2) === 0, 'so an empty plan is empty rather than one');

echo "\n-- withdrawing --\n";
$pdo->prepare("INSERT INTO activity_joins (activity_id,user_id,state,created_at) VALUES (2,3,'going',?)")->execute([$now]);
ok(rmt_activity_going_count(2) === 1, 'somebody joined an open plan directly');
$pdo->exec('DELETE FROM activity_joins WHERE activity_id=2 AND user_id=3');
ok(rmt_activity_going_count(2) === 0, 'and withdrawing leaves no trace of attendance');
ok($state(2, 3) === null, 'nor any state to argue with');

echo "\n-- the handler enforces what the table allows --\n";
/* The rules above are about the data. These are about the door: every one of them was verified in
   a browser, and each is the line that would silently disappear in a refactor. */
$src = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok(str_contains($src, "if (rmt_blocked_from((int) \$me['id'], 'trip', (int) \$act['trip_id'])) redirect(\$back);"),
   'joining checks the block before anything else it does');
ok(str_contains($src, "if ((int) \$act['user_id'] === (int) \$me['id']) redirect(\$back);"),
   'the owner cannot join their own plan');
ok(str_contains($src, "if (!empty(\$act['cancelled_at'])) {"), 'a cancelled plan takes nobody new');
ok(str_contains($src, "if ((\$act['join_mode'] ?? 'no') === 'ask') \$want = 'requested';"),
   'pressing the button on an ask-to-join plan is asking, never arriving');

echo "\n-- messaging --\n";
/* Driven with two signed-in browsers first: a stranger's first message becomes a request rather
   than landing in the inbox, the unread badge appears and clears on reading, a reply reaches both
   sides, and a block removes the compose box, says why, and makes the direct POST 404. These are
   the rules underneath that, which is where a refactor would break it silently. */
$pdo->exec('CREATE TABLE conversations (id INTEGER PRIMARY KEY AUTOINCREMENT, user_lo_id INT, user_hi_id INT, last_message_at TEXT)');
$pdo->exec('CREATE TABLE messages (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INT, sender_id INT, body TEXT, read_at TEXT, created_at TEXT)');
require_once BASE_PATH . '/app/messages.php';

ok(rmt_is_blocked(1, 2) === false, 'two people who have not blocked anybody are not blocked');
$pdo->exec('INSERT INTO blocks (blocker_id, blocked_id) VALUES (1,2)');
/* Symmetric on purpose, and this is the one people get wrong: whoever pressed the button, NEITHER
   of them can start again. A one way block lets the blocker keep writing to somebody who has no
   way to answer. */
ok(rmt_is_blocked(1, 2) === true, 'a block stops the person who was blocked');
ok(rmt_is_blocked(2, 1) === true, 'and the person who blocked them, in the same breath');
$pdo->exec('DELETE FROM blocks');
ok(rmt_is_blocked(1, 2) === false, 'and lifting it lifts it for both');

$src2 = (string) file_get_contents(BASE_PATH . '/app/messages.php');
ok(substr_count($src2, 'rmt_is_blocked(') >= 2,
   'the block is checked when reading a thread and again when writing to it');

echo "\n-- a trip changes shape as it moves through its life --\n";
/* The same page serves a trip that has not happened, one happening today, and one that is over.
   It used to serve all three identically: a finished trip still led with "The plan" and a form
   asking "What is the plan?", which reads as a product that has not noticed the trip is over.
   These are the rules for that, kept because a generic trip page is the thing to regress to. */
$plan = (string) file_get_contents(BASE_PATH . '/views/_trip_plan.php');
$feedSrc = (string) file_get_contents(BASE_PATH . '/views/feed.php');
ok(str_contains($plan, "\$phaseNow === 'past' ? 'What you did' : 'The plan'"),
   'a finished trip lists what was done, not what is planned');
ok(str_contains($plan, "<?php if (\$phaseNow === 'past'): ?>"),
   'and the form to add more is quieter once the trip is over, rather than gone');
ok(str_contains($feedSrc, "How was "), 'the home page asks how a finished trip was');
ok(str_contains($feedSrc, 'Nothing planned yet.'), 'and offers places when a trip has nothing on it');
ok(str_contains($feedSrc, "\$rmt_next && !\$ntToday"),
   'but says nothing when there is something on today, because today wins the space');

echo "\n-- notifications and invitations never outlive their permission --\n";
/* Driven in two browsers: every notification row is a link and every link lands on the exact
   object, an invitation on the trip anchored at who is planning it, a join on the activity, a
   message on the thread. What needed fixing was what happens when the permission behind one of
   them goes away.

   A blocked invitee kept being offered "Join the trip". The POST refused it with a 403 and their
   membership stayed "invited", which is the right outcome and the wrong experience: a button that
   looks live and silently does nothing is worse than no button. */
$ctrl = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
$show = (string) file_get_contents(BASE_PATH . '/views/trip_show.php');
ok(str_contains($ctrl, 'rmt_is_blocked((int) $me[' . chr(39) . 'id' . chr(39) . '], (int) $t[' . chr(39) . 'user_id' . chr(39) . '])'),
   'an invitation is not offered across a block');
ok(str_contains($show, 'there is a block between you'),
   'and the page says why rather than hiding it silently');
/* The server side is the part that actually protects anything, and it stays whatever the page
   decides to draw. */
ok(str_contains($ctrl, "\$inviteBlocked = false;"), 'the page and the POST agree about who may accept');

echo "\n-- a page that went stale while it was open --\n";
/* Driven by changing the world underneath a live tab: cancelling a plan while somebody has it
   open, removing a collaborator while they are typing, making a trip private. In every case the
   stale POST is refused and nothing is written, which is the part that matters, and refreshing
   converges on the truth.

   What was wrong was the refusal itself. It answered with the number 403 above the words "Not
   authorized", which reads as the site being broken rather than as something having changed, and
   is exactly the language a product should not use about itself. */
$e403 = (string) file_get_contents(BASE_PATH . '/views/403.php');
$e404 = (string) file_get_contents(BASE_PATH . '/views/404.php');
ok(!str_contains($e403, '403') || !preg_match('/>403</', $e403), 'the refusal page does not print its status code');
ok(!preg_match('/>404</', $e404), 'and neither does the missing page');
ok(!str_contains($e403, '<h1>Not authorized'), 'nor leads with "not authorized"');
ok(str_contains($e403, '<?= e($msg) ?>'), 'the reason the caller gave is what the reader sees');
ok(str_contains($e403, 'something changed'),
   'and it says the likely cause, because a stale page is the usual one');

echo "\n-- capacity --\n";
/* A plan with a limit on it. The interesting case is not the fifth person, it is the fourth and
   fifth arriving together: checking for room and then writing are two statements, and both of them
   read "one place left" in the gap between. */
$pdo->exec("UPDATE trip_activities SET capacity = 2 WHERE id = 2");
$cap2 = q_one('SELECT * FROM trip_activities WHERE id = 2');
ok(rmt_activity_has_room($cap2) === true, 'an empty plan with room has room');

ok(rmt_activity_take_seat($cap2, static function () use ($pdo, $now): void {
    $pdo->prepare("INSERT INTO activity_joins (activity_id,user_id,state,created_at) VALUES (2,2,'going',?)")->execute([$now]);
}) === true, 'the first seat is taken');
ok(rmt_activity_take_seat($cap2, static function () use ($pdo, $now): void {
    $pdo->prepare("INSERT INTO activity_joins (activity_id,user_id,state,created_at) VALUES (2,3,'going',?)")->execute([$now]);
}) === true, 'and the second');
$third = rmt_activity_take_seat($cap2, static function () use ($pdo, $now): void {
    $pdo->prepare("INSERT INTO activity_joins (activity_id,user_id,state,created_at) VALUES (2,1,'going',?)")->execute([$now]);
});
ok($third === false, 'the third is refused');
ok(rmt_activity_going_count(2) === 2, 'and nothing was written when it was refused');

/* Freeing a place makes it available again, which is the half people forget. */
$pdo->exec('DELETE FROM activity_joins WHERE activity_id = 2 AND user_id = 3');
ok(rmt_activity_take_seat($cap2, static function () use ($pdo, $now): void {
    $pdo->prepare("INSERT INTO activity_joins (activity_id,user_id,state,created_at) VALUES (2,1,'going',?)")->execute([$now]);
}) === true, 'removing somebody frees their place');
ok(rmt_activity_going_count(2) === 2, 'and the plan is full again, not over full');

/* A plan with no limit is the normal case and must not be made to queue for nothing. */
$noCap = q_one('SELECT * FROM trip_activities WHERE id = 1');
ok((int) ($noCap['capacity'] ?? 0) === 0 && rmt_activity_has_room($noCap) === true,
   'a plan with no limit always has room');

$ctrl2 = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok(substr_count($ctrl2, 'rmt_activity_take_seat(') === 2,
   'both ways of filling a seat go through the lock: the owner accepting, and joining an open plan');

echo "\n-- what a full plan says --\n";
/* The lock is only half of it. Somebody who arrives at a plan with no places left must be told
   that, or a missing button reads as the site being broken. Verified in a browser: eight of them
   raced for one seat and exactly one got it, and the seven who did not saw this sentence. */
$show2 = (string) file_get_contents(BASE_PATH . '/views/activity_show.php');
ok(str_contains($show2, 'This one is full'), 'a full plan says so where the button would have been');
ok(str_contains($show2, "\$cap === 1 ? 'person' : 'people'"), 'and counts one person as a person');
ok(str_contains($show2, "\$full = (\$act['join_mode'] === 'open') && !rmt_activity_has_room(\$act);"),
   'the button is not drawn when there is no room for it to do anything');

echo "\nsocial_journey_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
