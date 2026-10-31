<?php
/**
 * Email preferences: every email is filed under a category by where it links, and a member who
 * switches a category off gets no email in it.
 *
 *   php tests/email_prefs_test.php
 */
putenv('DATABASE_URL');
require dirname(__DIR__) . '/app/bootstrap.php';

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }

foreach (['/messages/ana' => 'messages', '/trip/12/chat' => 'messages', '/stay/4' => 'requests', '/buddies/mine' => 'requests',
          '/hosts-locals/me' => 'requests', '/activity/9' => 'requests', '/post/3' => 'social', '/review/5/x' => 'social',
          '/trip/12/lisbon' => 'trips', '/matches' => 'trips', 'https://ruinmytrip.com/messages/bo' => 'messages', '/welcome' => 'other'] as $path => $want)
    ok(rmt_email_category($path) === $want, "$path is $want");

$pdo = db(); $pdo->beginTransaction();
q_run("INSERT INTO users (username,email,password_hash,role,status,email_verified_at,created_at) VALUES ('prefcheck','prefcheck@example.invalid','x','member','active',?,?)", [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
$uid = (int) q_one("SELECT id FROM users WHERE username='prefcheck'")['id'];
rmt_profile_ensure($uid);
ok(rmt_email_muted_list($uid) === [], 'everything is on by default');
q_run("UPDATE profiles SET email_muted='social,bogus,trips' WHERE user_id=?", [$uid]);
ok(rmt_email_muted_list($uid) === ['social', 'trips'], 'only real categories are read');
ok(rmt_email_muted($uid, 'social') && !rmt_email_muted($uid, 'messages'), 'a muted category is muted, others are not');
putenv('RESEND_API_KEY=test-not-sent');
ok(rmt_notify_email_direct($uid, 'x', 'y', '/post/1') === false, 'no email in a muted category');
putenv('RESEND_API_KEY');
$pdo->rollBack();
echo $fails ? "\nemail_prefs_test: $fails FAILED\n" : "\nemail_prefs_test: all passed\n";
exit($fails ? 1 : 0);
