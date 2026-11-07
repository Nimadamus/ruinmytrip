<?php
/**
 * Moderators can suspend the account behind a report, and reinstate it (2026-11-07). Before this
 * the only way to stop a harasser was a script against the production database.
 *   php tests/account_suspend_test.php
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
$uid = [];
foreach (['smod' => 'mod', 'sabuser' => 'member', 'svictim' => 'member', 'sadmin' => 'admin', 'sed' => RMT_EDITORIAL_ROLE] as $u => $role) {
    q_run("INSERT INTO users (username,email,password_hash,role,status,email_verified_at,created_at) VALUES (?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', $role, 'active', $now, $now]);
    $uid[$u] = (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];
}
$dest = q_one('SELECT id FROM destinations ORDER BY id LIMIT 1');
q_run("INSERT INTO reviews (user_id,destination_id,subject_type,subject_name,rating,title,body,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
      [$uid['sabuser'], $dest['id'], 'destination', 'X', 1, 'Abuse', 'An abusive review written to harass another member.', 'published', $now, $now]);
$rid = (int) q_one('SELECT id FROM reviews WHERE user_id=?', [$uid['sabuser']])['id'];
q_run("INSERT INTO reports (reporter_id,target_type,target_id,reason,details,status,created_at) VALUES (?,?,?,?,?,?,?)",
      [$uid['svictim'], 'review', $rid, 'harassment', 'Targets me by name.', 'open', $now]);
$repId = (int) q_one('SELECT id FROM reports WHERE target_id=?', [$rid])['id'];

ok(rmt_moderation_account_for('review', $rid) === $uid['sabuser'], 'a review report points at its author');
ok(rmt_moderation_account_for('user', $uid['sabuser']) === $uid['sabuser'], 'a user report points at the user');
ok(rmt_moderation_account_for('stay', 1) === null, 'a stay names no single account');

$status = fn($u) => (string) q_one('SELECT status FROM users WHERE id=?', [$uid[$u]])['status'];
$r = rmt_account_set_status($uid['smod'], $uid['sabuser'], 'suspend', $repId, 'Harassment, second report');
ok($r['ok'] && $status('sabuser') === 'suspended', 'a moderator suspends the account');
$log = q_one("SELECT * FROM moderation_log WHERE target_type='user' AND target_id=? ORDER BY id DESC", [$uid['sabuser']]);
ok($log && $log['action'] === 'suspend' && $log['from_status'] === 'active' && $log['to_status'] === 'suspended' && (int) $log['report_id'] === $repId, 'and it is logged with before, after and the report');
ok(!in_array('sabuser', array_column(rmt_reviews_ruined(500), 'username'), true), 'a suspended author drops out of public lists');
ok(!rmt_account_set_status($uid['smod'], $uid['sadmin'], 'suspend')['ok'] && $status('sadmin') === 'active', 'staff are not suspended from the queue');
ok(!rmt_account_set_status($uid['smod'], $uid['sed'], 'suspend')['ok'], 'nor is the editorial account');
ok(!rmt_account_set_status($uid['smod'], $uid['smod'], 'suspend')['ok'], 'nobody suspends themselves');
ok(!rmt_account_set_status($uid['smod'], $uid['sabuser'], 'delete')['ok'], 'only suspend and reinstate exist');
$r = rmt_account_set_status($uid['smod'], $uid['sabuser'], 'reinstate', null, 'Appeal accepted');
ok($r['ok'] && $status('sabuser') === 'active', 'and reinstates it, with everything still there');
ok((int) q_one('SELECT COUNT(*) n FROM reviews WHERE id=?', [$rid])['n'] === 1, 'nothing was deleted');

// The queue offers the button for this report.
$queue = rmt_moderation_queue(100); $history = [];
ob_start(); include dirname(__DIR__) . '/views/admin_moderation.php'; $html = (string) ob_get_clean();
ok(str_contains($html, 'admin/moderation/account') && str_contains($html, 'value="' . $uid['sabuser'] . '"') && str_contains($html, 'Suspend account'), 'the moderation queue offers Suspend account for the reported author');

$pdo->rollBack();
echo $fails ? "account_suspend_test: $fails FAILED\n" : "account_suspend_test: all passed\n";
exit($fails ? 1 : 0);
