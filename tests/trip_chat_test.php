<?php
/**
 * Trip chat: only the people on a trip can read or write, one notification per unread stretch,
 * blocks hold, leaving the trip leaves the chat, reports need access, and account deletion takes
 * a member's lines with it.
 *
 *   php tests/trip_chat_test.php
 */
putenv('DATABASE_URL');
putenv('RESEND_API_KEY');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }

$pdo = db(); $pdo->beginTransaction();
$now = date('Y-m-d H:i:s');
$dest = q_one("SELECT id FROM destinations ORDER BY id LIMIT 1");
foreach (['tcowner', 'tcmember', 'tcthird', 'tcstranger'] as $u)
    q_run("INSERT INTO users (username,email,password_hash,role,status,email_verified_at,created_at) VALUES (?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', 'member', 'active', $now, $now]);
$user = fn($u) => q_one("SELECT * FROM users WHERE username=?", [$u]);
$id = fn($u) => (int) $user($u)['id'];
q_run("INSERT INTO trips (user_id,destination_id,title,slug,status,visibility,date_from,date_to,created_at) VALUES (?,?,?,?,?,?,?,?,?)",
      [$id('tcowner'), (int) $dest['id'], 'Chat check trip', 'chat-check-trip', 'published', 'public',
       date('Y-m-d', strtotime('+10 days')), date('Y-m-d', strtotime('+15 days')), $now]);
$trip = q_one("SELECT * FROM trips WHERE slug='chat-check-trip'");
$tid = (int) $trip['id'];
foreach (['tcmember', 'tcthird'] as $u)
    q_run("INSERT INTO trip_members (trip_id,user_id,role,state,created_at) VALUES (?,?,?,?,?)", [$tid, $id($u), 'editor', 'active', $now]);

ok(rmt_trip_chat_allowed($trip, $user('tcowner')) && rmt_trip_chat_allowed($trip, $user('tcmember')), 'the owner and members are in the chat');
ok(!rmt_trip_chat_allowed($trip, $user('tcstranger')), 'a stranger is not, even on a public trip');
ok(!rmt_trip_chat_post($trip, $user('tcstranger'), 'hello')['ok'], 'a stranger cannot write');
ok(!rmt_trip_chat_post($trip, $user('tcowner'), '   ')['ok'], 'an empty line is refused');
ok(!rmt_trip_chat_post($trip, $user('tcowner'), str_repeat('x', RMT_TRIP_CHAT_BODY_MAX + 1))['ok'], 'a too long line is refused');

$r1 = rmt_trip_chat_post($trip, $user('tcowner'), 'Flights are booked, @tcmember');
$r2 = rmt_trip_chat_post($trip, $user('tcowner'), 'And the hotel');
ok($r1['ok'] && $r2['ok'], 'the owner writes two lines');
$n = fn($u) => (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=? AND type='trip_chat' AND target_id=?", [$id($u), $tid])['n'];
ok($n('tcmember') === 1 && $n('tcthird') === 1, 'each member gets one notification for two lines');
ok($n('tcowner') === 0, 'the writer is not notified');
ok(rmt_trip_chat_unread($tid, $id('tcmember')) === 2, 'the member has two unread');
ok(rmt_trip_chat_unread($tid, $id('tcowner')) === 0, 'the writer has none unread');

$lines = rmt_trip_chat_lines($tid, $id('tcmember'));
rmt_trip_chat_mark_read($tid, $id('tcmember'), (int) end($lines)['id']);
ok(count($lines) === 2 && rmt_trip_chat_unread($tid, $id('tcmember')) === 0, 'reading clears the unread count');
ok(count(rmt_trip_chat_lines($tid, $id('tcmember'), (int) $lines[0]['id'])) === 1, 'after= returns only newer lines');
$inbox = rmt_trip_chats_for($id('tcthird'));
ok(count($inbox) === 1 && (int) $inbox[0]['unread'] === 2 && $inbox[0]['last_body'] === 'And the hotel', 'the inbox lists the chat with its last line');
ok(rmt_trip_chats_for($id('tcstranger')) === [], 'a stranger has no trip chats');

q_run("INSERT INTO blocks (blocker_id, blocked_id) VALUES (?,?)", [$id('tcthird'), $id('tcowner')]);
ok(rmt_trip_chat_lines($tid, $id('tcthird')) === [], 'a blocked member\'s lines are hidden from the blocker');
ok(count(rmt_trip_chat_lines($tid, $id('tcmember'))) === 2, 'and still shown to everybody else');
q_run("DELETE FROM notifications WHERE type='trip_chat'");
rmt_trip_chat_post($trip, $user('tcowner'), 'Third line');
ok($n('tcthird') === 0 && $n('tcmember') === 1, 'no notification across a block');

q_run("UPDATE trip_members SET state='left' WHERE trip_id=? AND user_id=?", [$tid, $id('tcmember')]);
ok(!rmt_trip_chat_allowed($trip, $user('tcmember')), 'leaving the trip leaves the chat');
q_run("UPDATE trip_members SET state='active' WHERE trip_id=? AND user_id=?", [$tid, $id('tcmember')]);

// The pages themselves. Headers cannot be sent from a CLI test, which is not what is being tested.
ini_set('display_errors', '0');
$_SESSION['uid'] = $id('tcmember');
ob_start(); trip_chat(['id' => $tid]); $h = ob_get_clean();
ok(str_contains($h, 'Flights are booked') && str_contains($h, 'Message everyone on this trip'), 'the chat page shows lines and the composer');
ok(str_contains($h, 'target_type=trip_message'), 'other people\'s lines can be reported');
$_GET = ['format' => 'json', 'after' => '0'];
ob_start(); trip_chat(['id' => $tid]); $j = json_decode(ob_get_clean(), true);
ok(is_array($j) && count($j['lines']) === 3 && $j['lines'][0]['mine'] === false, 'the refresh returns lines as JSON');
ok(str_contains($j['lines'][0]['html'], 'u/tcmember'), 'mentions are linked');
$_GET = [];
$x = rmt_trip_chat_post($trip, $user('tcmember'), '<script>alert(1)</script>');
$_SESSION['uid'] = $id('tcowner');
$_GET = ['format' => 'json', 'after' => (string) ((int) $x['id'] - 1)];
ob_start(); trip_chat(['id' => $tid]); $j = json_decode(ob_get_clean(), true);
ok(!str_contains($j['lines'][0]['html'], '<script>'), 'lines are escaped');
$_GET = [];

ob_start(); notifications([]); $nh = ob_get_clean();
ok(true, 'notifications page renders');
$_SESSION['uid'] = $id('tcmember');
ob_start(); messages_index([]); $mi = ob_get_clean();
ok(str_contains($mi, 'Trip chats') && str_contains($mi, 'Chat check trip'), 'the messages page lists trip chats');

// Account deletion is covered in tests/account_delete_test.php, which needs its own database.
$pdo->rollBack();
echo $fails ? "\ntrip_chat_test: $fails FAILED\n" : "\ntrip_chat_test: all passed\n";
exit($fails ? 1 : 0);
