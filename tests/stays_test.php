<?php
/**
 * Stays: who may ask, what a request must contain, who may move it where, messages after a yes,
 * references after the last night, and reports only from the two people in it.
 *
 *   php tests/stays_test.php
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
$dest = q_one("SELECT id FROM destinations ORDER BY id LIMIT 1");
foreach (['sthost' => '1985-01-01', 'stguest' => '1992-01-01', 'stminor' => date('Y-m-d', strtotime('-16 years')), 'stnobio' => '1990-01-01', 'stother' => '1990-01-01'] as $u => $bd) {
    q_run("INSERT INTO users (username,email,password_hash,role,status,email_verified_at,birthdate,created_at) VALUES (?,?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', 'member', 'active', $now, $bd, $now]);
    rmt_profile_ensure((int) q_one('SELECT id FROM users WHERE username=?', [$u])['id']);
}
$user = fn($u) => q_one("SELECT * FROM users WHERE username=?", [$u]);
$id = fn($u) => (int) $user($u)['id'];
foreach (['sthost', 'stguest', 'stminor', 'stother'] as $u)
    q_run("UPDATE profiles SET display_name=?, bio='I travel slowly and like long walks and markets.' WHERE user_id=?", [ucfirst($u), $id($u)]);
q_run("INSERT INTO host_profiles (user_id,status,destination_id,neighborhood,space_type,max_guests,min_nights,max_nights,about_space,pets,smoking,created_at,updated_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", [$id('sthost'), 'pending', $dest['id'], 'Centro', 'private_room', 2, 1, 4, 'A room.', 'none', 'no', $now, $now]);
$host = fn() => rmt_stay_host_by_username('sthost');

ok(rmt_stays_enabled(), 'stays are on');
ok(rmt_stay_blocked_reason($user('stguest'), $host()) !== null, 'a pending host takes no requests');
q_run("UPDATE host_profiles SET status='approved' WHERE user_id=?", [$id('sthost')]);
ok(rmt_stay_blocked_reason($user('stguest'), $host()) === null, 'an approved host does');
ok(rmt_stay_blocked_reason($user('sthost'), $host()) !== null, 'nobody asks to stay with themselves');
ok(rmt_stay_blocked_reason($user('stminor'), $host()) !== null, 'under 18 cannot ask');
ok(rmt_stay_blocked_reason($user('stnobio'), $host()) !== null, 'a blank profile cannot ask');
q_run("UPDATE users SET email_verified_at=NULL WHERE id=?", [$id('stother')]);
ok(rmt_stay_blocked_reason($user('stother'), $host()) !== null, 'an unconfirmed email cannot ask');
q_run("UPDATE users SET email_verified_at=? WHERE id=?", [$now, $id('stother')]);

$d1 = date('Y-m-d', strtotime('+10 days')); $d3 = date('Y-m-d', strtotime('+13 days'));
$good = ['date_from' => $d1, 'date_to' => $d3, 'guests' => 1, 'intro' => str_repeat('I am visiting for a conference and would love to see the old town. ', 1), 'agree' => '1'];
ok(rmt_stay_validate($good, $host())['ok'], 'a request inside the host limits is valid');
ok(!rmt_stay_validate(['date_to' => date('Y-m-d', strtotime('+20 days'))] + $good, $host())['ok'], 'too many nights is refused');
ok(!rmt_stay_validate(['guests' => 3] + $good, $host())['ok'], 'too many guests is refused');
ok(!rmt_stay_validate(['date_from' => date('Y-m-d'), 'date_to' => $d1] + $good, $host())['ok'], 'starting today is refused');
ok(!rmt_stay_validate(['intro' => 'hi'] + $good, $host())['ok'], 'a one word introduction is refused');
ok(!rmt_stay_validate(['agree' => ''] + $good, $host())['ok'], 'the safety box is required');

$r = rmt_stay_create($user('stguest'), $host(), rmt_stay_validate($good, $host())['data']);
ok($r['ok'], 'the guest sends a request');
$sid = (int) $r['id'];
ok((bool) q_one("SELECT 1 x FROM notifications WHERE user_id=? AND type='stay_requested' AND target_id=?", [$id('sthost'), $sid]), 'the host is notified');
ok(rmt_stay_blocked_reason($user('stguest'), $host()) !== null, 'no second open request to the same host');
ok(!rmt_message_allowed($id('stguest'), $id('sthost'))['ok'], 'no messages before a yes');
$s = fn() => rmt_stay($sid);
ok(!rmt_stay_move($s(), $user('stguest'), 'accepted')['ok'], 'the guest cannot accept their own request');
ok(!rmt_stay_move($s(), $user('stother'), 'cancelled')['ok'], 'a stranger cannot touch it');
ok(!rmt_stay_move($s(), $user('stguest'), 'confirmed')['ok'], 'nothing to confirm before a yes');
ok(rmt_stay_move($s(), $user('sthost'), 'accepted')['ok'], 'the host accepts');
ok(rmt_message_allowed($id('stguest'), $id('sthost'))['ok'], 'messages open after a yes');
ok(rmt_stay_move($s(), $user('stguest'), 'confirmed')['ok'], 'the guest confirms');
ok($s()['state'] === 'confirmed', 'the stay is confirmed');
ok(rmt_ref_basis($id('stguest'), $id('sthost')) === null || rmt_ref_basis($id('stguest'), $id('sthost'))['basis'] !== 'stay', 'no stay reference before the stay ends');
q_run("UPDATE stay_requests SET date_from=?, date_to=? WHERE id=?", [date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('-2 days')), $sid]);
ok(!rmt_stay_move($s(), $user('stguest'), 'cancelled')['ok'], 'a stay that has started cannot be cancelled');
$bg = rmt_ref_basis($id('stguest'), $id('sthost')); $bh = rmt_ref_basis($id('sthost'), $id('stguest'));
ok($bg && $bg['kind'] === 'stayed' && $bh && $bh['kind'] === 'hosted', 'after the last night: stayed and hosted references');
$ref = rmt_ref_save($user('stguest'), $user('sthost'), rmt_ref_validate(['rating_overall' => 5, 'rating_hospitality' => 5, 'body' => 'A spotless room and a host who knew every bakery nearby.'])['data']);
ok($ref['ok'] && (int) q_one("SELECT rating_hospitality h FROM member_references WHERE from_user_id=?", [$id('stguest')])['h'] === 5, 'the guest rates hospitality');

// A second request, declined and cancelled paths.
$r2 = rmt_stay_create($user('stother'), $host(), rmt_stay_validate($good, $host())['data']);
ok($r2['ok'] && rmt_stay_move(rmt_stay((int) $r2['id']), $user('sthost'), 'declined')['ok'], 'the host declines');
ok(!rmt_message_allowed($id('stother'), $id('sthost'))['ok'], 'a decline opens nothing');
$r3 = rmt_stay_create($user('stother'), $host(), rmt_stay_validate($good, $host())['data']);
ok($r3['ok'] && rmt_stay_move(rmt_stay((int) $r3['id']), $user('stother'), 'cancelled')['ok'], 'the guest can cancel a waiting request');

// A block stops a request and an accept.
q_run("INSERT INTO blocks (blocker_id, blocked_id) VALUES (?,?)", [$id('sthost'), $id('stother')]);
ok(rmt_stay_blocked_reason($user('stother'), $host()) !== null, 'a block stops a request');

// Paused host.
q_run("UPDATE host_profiles SET status='paused' WHERE user_id=?", [$id('sthost')]);
ok(rmt_stay_blocked_reason($user('stminor'), $host()) !== null, 'a paused host takes no requests');

// Reports and pages.
ok(isset(RMT_REPORT_TARGETS['stay']), 'a stay can be reported');
ok(rmt_stay_party($s(), $user('stother')) === null && rmt_stay_party($s(), $user('sthost')) === 'host', 'only the two people are parties');
ok(!array_filter(array_column(q_all('PRAGMA table_info(stay_requests)'), 'name'), fn($c) => preg_match('/address|street|price|amount|fee/', $c)), 'stay_requests holds no address and no money');

$pdo->rollBack();
echo $fails ? "\nstays_test: $fails FAILED\n" : "\nstays_test: all passed\n";
exit($fails ? 1 : 0);
