<?php
/**
 * Direct email: who qualifies, and the caps that keep it from becoming the thing people filter.
 *
 *   php tests/notify_email_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
                      'db_driver' => 'sqlite', 'sqlite_path' => ':memory:', 'security_salt' => 'test-salt'];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/ratelimit.php';
/* Stubs, not the real mail layer. What is under test is who gets past the gates and the caps; a
   test that reached api.resend.com would be testing the network. */
$GLOBALS['sent'] = [];
function rmt_mail_enabled(): bool { return getenv('RESEND_API_KEY') !== false && getenv('RESEND_API_KEY') !== ''; }
function rmt_mail_layout(string $h, string $b, string $t = '', string $u = ''): string { return $b; }
function rmt_unsubscribe_url(int $uid): string { return 'https://example.test/unsubscribe?u=' . $uid; }
function rmt_mail_send(string $to, string $subject, string $html, string $text = ''): array {
    $GLOBALS['sent'][] = ['to' => $to, 'subject' => $subject];
    return [true, 'stubbed'];
}
require BASE_PATH . '/app/notify_email.php';

// The shell this runs in may export a real key. Start with mail off, deliberately.
putenv('RESEND_API_KEY=');

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, status TEXT, email_verified_at TEXT)");
$pdo->exec("CREATE TABLE profiles (user_id INT, digest_opt_out INT DEFAULT 0)");
$pdo->exec("CREATE TABLE rate_limits (bucket TEXT, window_start INT, hits INT, PRIMARY KEY (bucket, window_start))");
$pdo->exec("INSERT INTO users (id,username,email,status,email_verified_at) VALUES
    (1,'verified','v@example.test','active','2026-01-01 00:00:00'),
    (2,'unverified','u@example.test','active',NULL),
    (3,'optedout','o@example.test','active','2026-01-01 00:00:00'),
    (4,'deleted','d@example.test','deleted','2026-01-01 00:00:00')");
$pdo->exec("INSERT INTO profiles (user_id,digest_opt_out) VALUES (1,0),(2,0),(3,1),(4,0)");

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-56s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

/* No RESEND_API_KEY in a test run, so rmt_mail_enabled() is false and nothing can leave the
   machine. That is the point: what is under test is who gets past the gates, and the send itself
   is the last thing that happens. */
echo "-- the gates --\n";
check('mail off means nothing is sent', rmt_notify_email_direct(1, 's', 'l', '/talk'), false);

putenv('RESEND_API_KEY=test-key-not-real');
check('an unverified address is never mailed', rmt_notify_email_direct(2, 's', 'l', '/talk'), false);
check('an opt-out is honoured', rmt_notify_email_direct(3, 's', 'l', '/talk'), false);
check('a deleted account is not mailed', rmt_notify_email_direct(4, 's', 'l', '/talk'), false);
check('nobody is not mailed', rmt_notify_email_direct(0, 's', 'l', '/talk'), false);

echo "\n-- the caps --\n";
$hourBucket = 'direct_mail_hour:1';
$dayBucket  = 'direct_mail_day:1';
$hits = static fn(string $b): int => (int) (q_one('SELECT SUM(hits) c FROM rate_limits WHERE bucket=?', [$b])['c'] ?? 0);
// The send fails (no real API key), but the gates it passed are recorded, which is what proves it
// reached them.
rmt_notify_email_direct(1, 's', 'l', '/talk');
check('a qualifying recipient is sent one', count($GLOBALS['sent']), 1);
check('addressed to their verified address', $GLOBALS['sent'][0]['to'] ?? '', 'v@example.test');
check('and it passed the hourly gate', $hits($hourBucket), 1);
check('and the daily one', $hits($dayBucket), 1);
rmt_notify_email_direct(1, 's', 'l', '/talk');
check('a second attempt inside the hour is stopped there', $hits($hourBucket), 2);
check('and never reaches the daily window', $hits($dayBucket), 1);
check('so only one email exists', count($GLOBALS['sent']), 1);
putenv('RESEND_API_KEY');

echo $fail ? "\nFAILED: $fail\n" : "\nOK\n";
exit($fail ? 1 : 0);
