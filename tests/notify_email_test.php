<?php
/**
 * Who a transactional email is refused to, and why.
 *
 * Every one of the three activation emails goes through rmt_notify_email_direct(), so every rule
 * about who may be written to lives in one function. This tests the refusals rather than the send,
 * because the refusals are the part that protects somebody, and because a test that actually posts
 * to the mail provider is a test that sends real email.
 *
 * THIS SHELL HAS A LIVE RESEND_API_KEY IN ITS ENVIRONMENT. The first version of this file did not
 * clear it, and the very first assertion made a real outbound request to the mail provider. So the
 * key is cleared before anything is loaded, every eligibility case is refused long before a message
 * is composed, and the cap is proved by spending the bucket directly rather than by sending.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://ruinmytrip.com',
                      'app_name' => 'RuinMyTrip', 'db_driver' => 'sqlite', 'sqlite_path' => ':memory:'];

/* Before anything else. See the note above. */
putenv('RESEND_API_KEY=');

require BASE_PATH . '/app/db.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, status TEXT, email_verified_at TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INTEGER, digest_opt_out INTEGER DEFAULT 0)');
$pdo->exec('CREATE TABLE rate_limits (bucket TEXT, window_start INTEGER, hits INTEGER, PRIMARY KEY (bucket, window_start))');
$pdo->exec("INSERT INTO users VALUES (1,'ok','a@x.invalid','active','2026-01-01 00:00:00')");
$pdo->exec("INSERT INTO users VALUES (2,'unverified','b@x.invalid','active',NULL)");
$pdo->exec("INSERT INTO users VALUES (3,'suspended','c@x.invalid','suspended','2026-01-01 00:00:00')");
$pdo->exec("INSERT INTO users VALUES (4,'optout','d@x.invalid','active','2026-01-01 00:00:00')");
$pdo->exec('INSERT INTO profiles (user_id, digest_opt_out) VALUES (4,1)');

require_once BASE_PATH . '/app/helpers.php';
require_once BASE_PATH . '/app/ratelimit.php';
require_once BASE_PATH . '/app/mail.php';
require_once BASE_PATH . '/app/notify_email.php';

$pass = 0; $fail = 0;
function ok(string $what, $got, $want = true): void {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  [PASS] $what\n"; }
    else { $fail++; echo "  [FAIL] $what  expected=" . var_export($want, true)
                      . " got=" . var_export($got, true) . "\n"; }
}

echo "\n-- with no mail provider configured, nothing is attempted --\n";
ok('a verified active member still gets nothing',
   rmt_notify_email_direct(1, 's', 'l', '/matches'), false);
ok('and no rate limit was spent on the attempt',
   (int) $pdo->query('SELECT COUNT(*) FROM rate_limits')->fetchColumn(), 0);

/* With a key present the function runs its real checks. Nothing below reaches a send: every case
   is refused before the message is composed, which is exactly what is being asserted. */
putenv('RESEND_API_KEY=test-key-not-used-for-sending');
ok('the provider now reads as configured', rmt_mail_enabled(), true);

echo "\n-- who is refused, and it is not a judgement call --\n";
ok('an unconfirmed address is never written to', rmt_notify_email_direct(2, 's', 'l', '/matches'), false);
ok('a suspended account is not written to',      rmt_notify_email_direct(3, 's', 'l', '/matches'), false);
ok('somebody opted out is not written to',       rmt_notify_email_direct(4, 's', 'l', '/matches'), false);
ok('a member who does not exist is not written to', rmt_notify_email_direct(999, 's', 'l', '/matches'), false);
ok('and neither is user zero',                   rmt_notify_email_direct(0, 's', 'l', '/matches'), false);
ok('none of those spent a rate limit either',
   (int) $pdo->query('SELECT COUNT(*) FROM rate_limits')->fetchColumn(), 0);

echo "
-- the caps are real, and they are per person --
";
ok('one an hour is one',  RMT_DIRECT_MAIL_PER_HOUR, 1);
ok('six a day is six',    RMT_DIRECT_MAIL_PER_DAY, 6);
/* The bucket is spent directly rather than by sending, so an eligible member can be shown to be
   refused by the cap without this test ever reaching the mail provider. */
rmt_rate_ok('direct_mail_hour', '1', RMT_DIRECT_MAIL_PER_HOUR, 3600);
ok('a member already at the hourly cap is refused',
   rmt_notify_email_direct(1, 's', 'l', '/matches'), false);
ok('the bucket is keyed by the member, so one busy member cannot silence another',
   (bool) $pdo->query("SELECT 1 FROM rate_limits WHERE bucket = 'direct_mail_hour:1'")->fetchColumn(), true);
ok('and nobody else inherited that cap',
   (bool) $pdo->query("SELECT 1 FROM rate_limits WHERE bucket = 'direct_mail_hour:2'")->fetchColumn(), false);

putenv('RESEND_API_KEY=');

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL NOTIFY EMAIL TESTS PASS ({$pass})\n";
