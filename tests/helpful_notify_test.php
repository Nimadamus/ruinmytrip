<?php
/**
 * "Found your review helpful" reaches the writer (2026-11-07): once per voter per review, never for
 * your own, never across a block, and rolled into one line however many people say it.
 *   php tests/helpful_notify_test.php
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
$dest = q_one('SELECT id FROM destinations ORDER BY id LIMIT 1');
$uid = [];
foreach (['hwriter', 'hreader1', 'hreader2', 'hreader3', 'hblocked'] as $u) {
    q_run("INSERT INTO users (username,email,password_hash,role,status,email_verified_at,created_at) VALUES (?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', 'member', 'active', $now, $now]);
    $uid[$u] = (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];
}
q_run("INSERT INTO reviews (user_id,destination_id,subject_type,subject_name,rating,title,body,what_ruined,status,created_at,updated_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)", [$uid['hwriter'], $dest['id'], 'destination', 'Somewhere', 2, 'Taxi trap',
       'The airport taxi rank quoted triple the meter price, use the app.', 'The taxi rank.', 'published', $now, $now]);
$rid = (int) q_one('SELECT id FROM reviews WHERE user_id=?', [$uid['hwriter']])['id'];
$count = fn() => (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=? AND type='helpful'", [$uid['hwriter']])['n'];

rmt_notify_helpful($uid['hreader1'], $rid);
ok($count() === 1, 'a helpful vote tells the writer');
rmt_notify_helpful($uid['hreader1'], $rid);
ok($count() === 1, 'the same reader voting again does not repeat it');
rmt_notify_helpful($uid['hwriter'], $rid);
ok($count() === 1, 'your own vote is not news');
q_run('INSERT INTO blocks (blocker_id,blocked_id) VALUES (?,?)', [$uid['hwriter'], $uid['hblocked']]);
rmt_notify_helpful($uid['hblocked'], $rid);
ok($count() === 1, 'nobody blocked reaches the writer this way');
rmt_notify_helpful($uid['hreader2'], $rid);
rmt_notify_helpful($uid['hreader3'], $rid);
$rows = q_all("SELECT n.*, u.username actor FROM notifications n LEFT JOIN users u ON u.id=n.actor_id WHERE n.user_id=? ORDER BY n.id DESC", [$uid['hwriter']]);
$rolled = rmt_notifications_rollup($rows);
ok(count($rolled) === 1 && (int) $rolled[0]['others'] === 2, 'three readers roll into one line');

$pdo->rollBack();
echo $fails ? "helpful_notify_test: $fails FAILED\n" : "helpful_notify_test: all passed\n";
exit($fails ? 1 : 0);
