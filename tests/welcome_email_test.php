<?php
declare(strict_types=1);
/* Welcome email is transactional (sent without a postal address); the onboarding nudge is
   promotional (only once MAIL_POSTAL_ADDRESS is set), once per member, incomplete profiles only. */
require __DIR__ . '/../app/bootstrap.php';
$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) { $fails++; echo "FAIL  $n\n"; } else echo "PASS  $n\n"; }

$src = (string) file_get_contents(__DIR__ . '/../app/notify_email.php');
preg_match('/function rmt_welcome_email.*?\n}\n/s', $src, $m);
$w = $m[0] ?? '';
ok($w !== '', 'welcome function found');
ok(!str_contains($w, 'rmt_mail_marketing_ready'), 'welcome does not wait for promotional readiness');
ok(str_contains($w, "rmt_rate_ok('welcome_mail'"), 'welcome is sent once per account');
ok(!str_contains($w, 'utm_') && !str_contains($w, 'review/new') && !str_contains($w, 'buddies'), 'welcome carries no promotion');
preg_match('/function rmt_onboarding_nudges.*?\n}\n/s', $src, $m2);
$n = $m2[0] ?? '';
ok(str_contains($n, 'if (!rmt_mail_marketing_ready()) return 0;'), 'nudge is gated on promotional readiness');
ok(str_contains($n, 'rmt_mail_unsub_headers') && str_contains($n, 'rmt_unsubscribe_url'), 'nudge carries unsubscribe');
ok(str_contains($n, 'digest_opt_out'), 'nudge respects the email opt out');
ok(str_contains((string) file_get_contents(__DIR__ . '/../app/quick_join.php'), 'rmt_welcome_email($uid)'), 'Google sign ups get the welcome');
ok(str_contains((string) file_get_contents(__DIR__ . '/../app/lifecycle.php'), 'rmt_onboarding_nudges()'), 'lifecycle runs the nudge');

putenv('MAIL_POSTAL_ADDRESS');
ok(rmt_onboarding_nudges() === 0, 'nudge sends nothing without a postal address');
putenv('RESEND_API_KEY');
ok(rmt_welcome_email(1) === false, 'welcome sends nothing while mail is off');

echo $fails ? "welcome_email_test: $fails FAILED\n" : "welcome_email_test: all passed\n";
exit($fails ? 1 : 0);
