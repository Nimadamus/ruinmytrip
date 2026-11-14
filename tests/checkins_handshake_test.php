<?php
/**
 * The two gates of partner matching (migration 121, app/checkins.php, app/buddies.php).
 *
 * 1. The handshake. A traveler asks to join a trip, the poster says yes or no, and no private
 *    message can pass until the answer is yes. A trip has a number of places: the poster cannot
 *    accept more people than that, and taking the last place closes the trip to new requests.
 *
 * 2. Expiry. A check-in puts a traveler in a city's room until a time. The second that time
 *    passes they are out of the count, the roster and the room, and their messages go with them,
 *    WITHOUT the sweep job having run. Every expiry case below is asked before the sweep and
 *    again after it, and the answers must match.
 *
 * Runs against a throwaway in-memory SQLite DB, built from the real migration files.
 *
 *   php tests/checkins_handshake_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/profiles.php';
require BASE_PATH . '/app/plans.php';
require BASE_PATH . '/app/buddies.php';
require BASE_PATH . '/app/checkins.php';

function rmt_is_blocked(int $a, int $b): bool {
    if ($a === $b) return false;
    return (bool) q_one('SELECT 1 FROM blocks WHERE (blocker_id=? AND blocked_id=?) OR (blocker_id=? AND blocked_id=?)', [$a, $b, $b, $a]);
}

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? "\n      $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, status TEXT NOT NULL DEFAULT 'active',
              birthdate TEXT, email_verified_at TEXT, created_at TEXT)");
$pdo->exec('CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, avatar_url TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT)');
$pdo->exec('CREATE TABLE neighborhoods (id INTEGER PRIMARY KEY, destination_id INT, slug TEXT, canonical_name TEXT)');
$pdo->exec('CREATE TABLE blocks (blocker_id INT, blocked_id INT)');
$pdo->exec('CREATE TABLE local_connects (id INTEGER PRIMARY KEY AUTOINCREMENT, from_user_id INT, to_user_id INT, state TEXT)');
$pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT,
              actor_id INT, target_type TEXT, target_id INT, read_at TEXT, created_at TEXT)');
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/099_travel_buddies.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/121_checkins_city_rooms.sqlite.sql'));

$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'tokyo-japan','Tokyo','Japan'),(2,'osaka-japan','Osaka','Japan')");
$pdo->exec("INSERT INTO neighborhoods (id,destination_id,slug,canonical_name) VALUES (1,1,'shibuya','Shibuya'),(2,2,'namba','Namba')");
foreach ([1 => 'owner', 2 => 'ana', 3 => 'leo', 4 => 'kim', 5 => 'sam', 6 => 'gone'] as $id => $n) {
    q_run('INSERT INTO users (id,username,status,created_at) VALUES (?,?,?,?)', [$id, $n, 'active', '2026-01-01']);
    q_run('INSERT INTO profiles (user_id, display_name) VALUES (?,?)', [$id, ucfirst($n)]);
}

/* ================= 1. the handshake ================= */
$next = ((int) date('Y') + 1);
q_run("INSERT INTO buddy_posts (id,user_id,trip_type,title,where_text,destination_id,date_from,date_to,spots,description,status,created_at)
       VALUES (1,1,'trip','Tokyo in spring','',1,?,?,2,'Looking for two people to eat with.','open',?)",
      ["$next-04-01", "$next-04-10", '2026-01-01 00:00:00']);
$post = static fn(): array => q_one('SELECT * FROM buddy_posts WHERE id = 1');
$state = static fn(int $u): ?string => q_one('SELECT state FROM buddy_interest WHERE post_id=1 AND user_id=?', [$u])['state'] ?? null;

ok('nobody can message before asking', !rmt_buddy_mutual(1, 2));
ok('a traveler asks to join', rmt_buddy_toggle_interest($post(), 2, 'hello')['ok'] && $state(2) === 'interested');
ok('a pending request does not open messages', !rmt_buddy_mutual(1, 2) && !rmt_buddy_mutual(2, 1));
ok('the requester cannot approve their own request', !rmt_buddy_decide($post(), 2, 2, 'accepted') && $state(2) === 'interested');
ok('a third member cannot approve it', !rmt_buddy_decide($post(), 3, 2, 'accepted') && $state(2) === 'interested');
ok('the poster cannot approve somebody who never asked', !rmt_buddy_decide($post(), 1, 5, 'accepted') && $state(5) === null);

rmt_buddy_toggle_interest($post(), 3);
ok('a declined request does not open messages', rmt_buddy_decide($post(), 1, 3, 'declined') && $state(3) === 'declined' && !rmt_buddy_mutual(1, 3));

ok('the poster approves, and messages open both ways', rmt_buddy_decide($post(), 1, 2, 'accepted') && rmt_buddy_mutual(1, 2) && rmt_buddy_mutual(2, 1));
ok('approving one person opens nothing for anybody else', !rmt_buddy_mutual(1, 3) && !rmt_buddy_mutual(2, 3));
ok('the requester is told', (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=2 AND type='buddy_accepted'")['n'] === 1);
ok('approving twice is not a second approval', !rmt_buddy_decide($post(), 1, 2, 'accepted')
    && (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=2 AND type='buddy_accepted'")['n'] === 1);
ok('one of two places taken leaves the trip open', $post()['status'] === 'open' && !rmt_buddy_is_full($post()));

rmt_buddy_toggle_interest($post(), 4);
rmt_buddy_toggle_interest($post(), 5);
ok('the second approval takes the last place', rmt_buddy_decide($post(), 1, 4, 'accepted') && rmt_buddy_is_full($post()));
ok('a full trip closes to new requests', $post()['status'] === 'closed');
ok('a full trip cannot approve a third person', !rmt_buddy_decide($post(), 1, 5, 'accepted') && $state(5) === 'interested' && !rmt_buddy_mutual(1, 5));
ok('nobody new can ask to join a full trip', !rmt_buddy_toggle_interest($post(), 6)['ok']);
ok('a poster can change their mind about a declined person only while there is room', !rmt_buddy_decide($post(), 1, 3, 'accepted'));

ok('declining somebody who was approved frees the place', rmt_buddy_decide($post(), 1, 4, 'declined') && !rmt_buddy_is_full($post()));
ok('and closes their messages', !rmt_buddy_mutual(1, 4));
ok('the freed place can go to somebody waiting', rmt_buddy_decide($post(), 1, 5, 'accepted') && rmt_buddy_mutual(1, 5));

ok('a requester who withdraws loses the conversation gate', rmt_buddy_toggle_interest($post(), 5)['action'] === 'withdrawn' && !rmt_buddy_mutual(1, 5));

q_run("UPDATE buddy_posts SET status='open' WHERE id=1");
q_run('INSERT INTO blocks (blocker_id, blocked_id) VALUES (1, 3)');
ok('a blocked member cannot be approved', !rmt_buddy_decide($post(), 1, 3, 'accepted') && $state(3) === 'declined');
q_run('DELETE FROM blocks');
q_run("UPDATE buddy_posts SET status='removed' WHERE id=1");
ok('a removed trip approves nobody', !rmt_buddy_decide($post(), 1, 3, 'accepted'));
q_run("UPDATE buddy_posts SET status='open' WHERE id=1");
ok('no transaction is left open by a refusal', !$pdo->inTransaction());

/* The trip is over once its last day has passed. */
q_run("INSERT INTO buddy_posts (id,user_id,trip_type,title,where_text,destination_id,date_from,date_to,spots,description,status,created_at)
       VALUES (2,1,'trip','Last month','',1,'2026-03-01','2026-03-05',1,'Over.','closed','2026-01-01 00:00:00'),
              (3,1,'trip','Ends today','',1,'2026-03-08','2026-03-10',1,'Still on.','open','2026-01-01 00:00:00'),
              (4,1,'trip','Removed','',1,'2026-03-01','2026-03-05',1,'Gone.','removed','2026-01-01 00:00:00')");
$done = rmt_buddy_complete_past('2026-03-10 09:00:00');
$stamp = static fn(int $id): ?string => q_one('SELECT completed_at FROM buddy_posts WHERE id=?', [$id])['completed_at'];
ok('a trip whose last day has passed is stamped complete', $done === 1 && $stamp(2) === '2026-03-10 09:00:00');
ok('a trip on its last day is not', $stamp(3) === null);
ok('a future trip is not', $stamp(1) === null);
ok('a removed trip is not', $stamp(4) === null);
ok('stamping again changes nothing', rmt_buddy_complete_past('2026-03-10 10:00:00') === 0 && $stamp(2) === '2026-03-10 09:00:00');

/* ================= 2. check-ins and expiry ================= */
$t0 = '2026-03-10 12:00:00';
$at = static fn(int $minutes): string => date('Y-m-d H:i:s', strtotime('2026-03-10 12:00:00') + $minutes * 60);

ok('checking in needs a real city', !rmt_checkin_start(2, 99, 24, null, '', $t0)['ok']);
$a = rmt_checkin_start(2, 1, 2, 1, 'coffee near the station?', $t0);          // ana: Tokyo, 2 hours
$l = rmt_checkin_start(3, 1, 6, 2, '', $t0);                                  // leo: Tokyo, 6 hours, Osaka's neighborhood
$k = rmt_checkin_start(4, 2, 6, null, '', $t0);                               // kim: Osaka
ok('a check-in expires when it says', $a['ok'] && $a['expires_at'] === '2026-03-10 14:00:00');
ok('a neighborhood of another city is dropped', q_one('SELECT neighborhood_id FROM active_checkins WHERE id=?', [$l['id']])['neighborhood_id'] === null);
ok('length is clamped to a week', rmt_checkin_start(5, 2, 9999, null, '', $t0)['expires_at'] === '2026-03-17 12:00:00');
ok('and to at least an hour', rmt_checkin_start(5, 2, -3, null, '', $t0)['expires_at'] === '2026-03-10 13:00:00');
ok('checking in again replaces the first, never doubles it',
   (int) q_one('SELECT COUNT(*) n FROM active_checkins WHERE user_id=5 AND ended_at IS NULL')['n'] === 1
   && q_one("SELECT COUNT(*) n FROM active_checkins WHERE user_id=5 AND end_reason='moved'")['n'] == 1);
rmt_checkin_end(5, 'checked_out', $t0);

ok('the city counts who is there', rmt_city_room_count(1, $t0) === 2 && rmt_city_room_count(2, $t0) === 1);
ok('a member sees the roster', count(rmt_city_room_roster(1, 2, $t0)) === 2);
ok('somebody checked in elsewhere does not', rmt_city_room_roster(1, 4, $t0) === []);
ok('somebody not checked in does not', rmt_city_room_roster(1, 6, $t0) === []);

ok('a member can post', rmt_city_room_post(2, 1, 'Anyone up for ramen tonight?', $at(1))['ok']);
ok('so can another', rmt_city_room_post(3, 1, 'Yes, I am in.', $at(2))['ok']);
ok('somebody in another city cannot post here', !rmt_city_room_post(4, 1, 'hi', $at(2))['ok']);
ok('somebody not checked in cannot post', !rmt_city_room_post(6, 1, 'hi', $at(2))['ok']);
ok('an empty message is refused', !rmt_city_room_post(2, 1, "  \n ", $at(2))['ok']);
ok('an overlong message is refused', !rmt_city_room_post(2, 1, str_repeat('a', RMT_CITY_ROOM_BODY_MAX + 1), $at(2))['ok']);
$feed = rmt_city_room_feed(1, 3, 0, $at(3));
ok('members read the room, oldest first', count($feed) === 2 && $feed[0]['username'] === 'ana' && $feed[1]['username'] === 'leo');
ok('the feed can be read from a message onward', count(rmt_city_room_feed(1, 3, (int) $feed[0]['id'], $at(3))) === 1);
ok('outsiders read nothing', rmt_city_room_feed(1, 4, 0, $at(3)) === [] && rmt_city_room_feed(1, 6, 0, $at(3)) === []);
ok('rooms do not leak into each other', rmt_city_room_feed(2, 4, 0, $at(3)) === []);

q_run('INSERT INTO blocks (blocker_id, blocked_id) VALUES (3, 2)');
ok('a block hides the other person from roster and feed, both ways',
   count(rmt_city_room_roster(1, 2, $at(3))) === 1 && count(rmt_city_room_feed(1, 2, 0, $at(3))) === 1
   && count(rmt_city_room_roster(1, 3, $at(3))) === 1 && count(rmt_city_room_feed(1, 3, 0, $at(3))) === 1);
q_run('DELETE FROM blocks');

/* --- ana's two hours run out. NO SWEEP HAS RUN. --- */
$late = $at(121);
ok('[no sweep] the row is still marked open in the table', q_one('SELECT ended_at FROM active_checkins WHERE id=?', [$a['id']])['ended_at'] === null);
ok('[no sweep] an expired traveler has no live check-in', rmt_checkin_live(2, $late) === null);
ok('[no sweep] and is out of the count', rmt_city_room_count(1, $late) === 1);
ok('[no sweep] and off the roster', array_column(rmt_city_room_roster(1, 3, $late), 'username') === ['leo']);
ok('[no sweep] and cannot read the room', rmt_city_room_feed(1, 2, 0, $late) === []);
ok('[no sweep] and cannot post', !rmt_city_room_post(2, 1, 'still here?', $late)['ok']);
ok('[no sweep] and their messages left with them', array_column(rmt_city_room_feed(1, 3, 0, $late), 'username') === ['leo']);
ok('[no sweep] expiry is exact: live one second before', rmt_checkin_live(2, '2026-03-10 13:59:59') !== null
   && rmt_checkin_live(2, '2026-03-10 14:00:00') === null);

$sw = rmt_checkin_sweep($late);
ok('the sweep closes exactly the expired check-in', $sw['expired'] === 1);
$row = q_one('SELECT ended_at, end_reason FROM active_checkins WHERE id=?', [$a['id']]);
ok('stamped with the moment it expired, not the moment the job ran', $row['ended_at'] === '2026-03-10 14:00:00' && $row['end_reason'] === 'expired');
ok('[after sweep] same count', rmt_city_room_count(1, $late) === 1);
ok('[after sweep] same roster', array_column(rmt_city_room_roster(1, 3, $late), 'username') === ['leo']);
ok('[after sweep] same feed', array_column(rmt_city_room_feed(1, 3, 0, $late), 'username') === ['leo']);
ok('the sweep leaves live check-ins alone', rmt_checkin_live(3, $late) !== null && rmt_checkin_live(4, $late) !== null);
ok('running it again does nothing', rmt_checkin_sweep($late)['expired'] === 0);
ok('recent messages are kept for moderation', (int) q_one('SELECT COUNT(*) n FROM city_room_messages')['n'] === 2);

$back = rmt_checkin_start(2, 1, 2, null, '', $at(130));
ok('coming back is a new check-in', $back['ok'] && rmt_city_room_count(1, $at(131)) === 2);
ok('it does not bring the old messages back', array_column(rmt_city_room_feed(1, 2, 0, $at(131)), 'username') === ['leo']);

ok('checking out early works at once', rmt_checkin_end(3, 'checked_out', $at(140)) && rmt_checkin_live(3, $at(140)) === null
   && rmt_city_room_feed(1, 2, 0, $at(141)) === []);
ok('checking out twice is a no', !rmt_checkin_end(3, 'checked_out', $at(141)));

q_run("UPDATE users SET status='suspended' WHERE id=2");
ok('a suspended account is not counted', rmt_city_room_count(1, $at(141)) === 0);
q_run("UPDATE users SET status='active' WHERE id=2");

/* An expired row the sweep never reached must not block a new check-in. */
$again = rmt_checkin_start(2, 2, 1, null, '', $at(60 * 24));
ok('an unswept expired row does not block checking in again', $again['ok']
   && (int) q_one('SELECT COUNT(*) n FROM active_checkins WHERE user_id=2 AND ended_at IS NULL')['n'] === 1);
ok('and it is closed as expired, at its own expiry', q_one('SELECT end_reason, ended_at, expires_at FROM active_checkins WHERE id=?', [$back['id']])['end_reason'] === 'expired');

$old = rmt_checkin_sweep($at(60 * 24 * (RMT_CITY_ROOM_RETAIN_DAYS + 2)));
ok('messages past the retention window are deleted', $old['messages_deleted'] === 2 && (int) q_one('SELECT COUNT(*) n FROM city_room_messages')['n'] === 0);
ok('the sweep also stamps finished trips', array_key_exists('trips_completed', $old));
ok('no transaction is left open', !$pdo->inTransaction());

/* ---- wiring ---- */
$routes = (string) file_get_contents(BASE_PATH . '/public/index.php');
foreach (['checkin_start', 'checkin_end', 'city_room_feed', 'city_room_post', 'cron_checkins'] as $fn) {
    ok("route handler $fn is wired and exists", str_contains($routes, "'$fn'") && function_exists($fn));
}
ok('the app loads the module', str_contains((string) file_get_contents(BASE_PATH . '/app/bootstrap.php'), "/app/checkins.php"));
$src = (string) file_get_contents(BASE_PATH . '/app/checkins.php');
ok('no query here trusts the database clock', !preg_match('/\bNOW\s*\(\s*\)|CURRENT_TIMESTAMP/', $src));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
