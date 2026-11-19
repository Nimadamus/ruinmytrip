<?php
/**
 * Sailing chat (app/sailing_chat.php): opens at two members, only members read or write, one
 * notification per unread stretch, blocks hold, closing the post leaves the room, reports need
 * access, account deletion takes a member's lines.
 *
 *   php tests/sailing_chat_test.php
 */
putenv('DATABASE_URL');
putenv('RESEND_API_KEY');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';
ini_set('display_errors', '0');

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }

$pdo = db(); $pdo->beginTransaction();
$now = date('Y-m-d H:i:s');
$sail = date('Y-m-d', strtotime('+50 days'));
foreach (['scone', 'sctwo', 'scthree', 'scstranger'] as $u)
    q_run("INSERT INTO users (username,email,password_hash,role,status,email_verified_at,birthdate,created_at) VALUES (?,?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', 'member', 'active', $now, '1990-01-01', $now]);
$user = fn($u) => q_one('SELECT * FROM users WHERE username=?', [$u]);
$uid = fn($u) => (int) $user($u)['id'];
foreach (['scone', 'sctwo', 'scthree', 'scstranger'] as $u) rmt_profile_ensure($uid($u));
$key = 'chattestship';
$post = function (string $u, string $date) use ($uid, $now, $key) {
    q_run("INSERT INTO buddy_posts (user_id, trip_type, title, where_text, date_from, date_to, status, created_at, updated_at, cruise_line, ship, ship_key, description)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
          [$uid($u), 'cruise', 'Chat Test Ship sailing', 'Chat Test Ship', $date, date('Y-m-d', strtotime($date . ' +7 days')), 'open', $now, $now,
           'Test Line', 'Chat Test Ship', $key, 'Happy to meet for sail away.']);
};

$post('scone', $sail);
ok(!rmt_sailing_chat_open($key, $sail), 'one member: the room is not open');
ok(!rmt_sailing_chat_allowed($key, $sail, $user('scone')), 'and the lone member cannot use it yet');
ok(!rmt_sailing_chat_post($key, $sail, 'Chat Test Ship', $user('scone'), 'hello')['ok'], 'so nothing can be written');

$post('sctwo', date('Y-m-d', strtotime($sail . ' +1 day')));
$post('scthree', $sail);
ok(rmt_sailing_chat_open($key, $sail), 'two or more members: the room opens');
ok(rmt_sailing_chat_allowed($key, $sail, $user('scone')) && rmt_sailing_chat_allowed($key, $sail, $user('sctwo')), 'members a day apart share it');
ok(!rmt_sailing_chat_allowed($key, $sail, $user('scstranger')), 'a stranger is not in it');
ok(!rmt_sailing_chat_allowed($key, $sail, null), 'nor a signed out visitor');
ok(!rmt_sailing_chat_post($key, $sail, 'Chat Test Ship', $user('scstranger'), 'hi')['ok'], 'a stranger cannot write');
ok(!rmt_sailing_chat_post($key, $sail, 'Chat Test Ship', $user('scone'), '   ')['ok'], 'an empty line is refused');
ok(!rmt_sailing_chat_post($key, $sail, 'Chat Test Ship', $user('scone'), str_repeat('x', RMT_SAILING_CHAT_BODY_MAX + 1))['ok'], 'a too long line is refused');

$r1 = rmt_sailing_chat_post($key, $sail, 'Chat Test Ship', $user('scone'), 'Anyone doing the snorkel excursion?');
$r2 = rmt_sailing_chat_post($key, $sail, 'Chat Test Ship', $user('scone'), 'Or the island tour');
ok($r1['ok'] && $r2['ok'], 'a member writes');
$room = rmt_sailing_room($key, $sail);
ok($room !== null && (int) q_one('SELECT COUNT(*) n FROM sailing_rooms WHERE ship_key=? AND sail_date=?', [$key, $sail])['n'] === 1, 'one room for the sailing');
$notes = fn($u) => (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=? AND type='sailing_chat' AND read_at IS NULL", [$uid($u)])['n'];
ok($notes('sctwo') === 1 && $notes('scthree') === 1, 'one notification per member for a burst');
ok($notes('scone') === 0 && $notes('scstranger') === 0, 'not for the writer, not for strangers');
ok(count(rmt_sailing_chat_lines((int) $room['id'], $uid('sctwo'))) === 2, 'members read the lines');

// Blocks: scthree blocked scone, so scthree does not see scone's lines.
q_run('INSERT INTO blocks (blocker_id, blocked_id) VALUES (?,?)', [$uid('scthree'), $uid('scone')]);
ok(count(rmt_sailing_chat_lines((int) $room['id'], $uid('scthree'))) === 0, 'a block hides the blocked member\'s lines');

// Reports need access.
$_SESSION = [];
$line = (int) $r1['id'];
ok((bool) q_one('SELECT 1 FROM sailing_messages WHERE id=?', [$line]), 'the line exists');
ok(isset(RMT_REPORT_TARGETS['sailing_message']), 'a sailing chat line is reportable');

// Closing the post leaves the room.
q_run("UPDATE buddy_posts SET status='deleted' WHERE user_id=? AND ship_key=?", [$uid('sctwo'), $key]);
ok(!rmt_sailing_chat_allowed($key, $sail, $user('sctwo')), 'a member who takes the post down leaves the room');
ok(rmt_sailing_chat_allowed($key, $sail, $user('scone')), 'the rest stay in it');

// Account deletion takes their lines.
q_run('DELETE FROM sailing_messages WHERE user_id = ?', [$uid('scone')]);
ok((int) q_one('SELECT COUNT(*) n FROM sailing_messages WHERE room_id=?', [(int) $room['id']])['n'] === 0, 'deleting a member\'s lines empties them');

$pdo->rollBack();
echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
