<?php
/**
 * The three emails that bring a traveler back, and the things they must never do.
 *
 * Each one is triggered by a real event about the recipient: their dates were landed on, somebody
 * asked to connect, somebody accepted. Everything else about them is a refusal, and the refusals
 * are what this asserts, because an email that says too much is worse than no email.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));

$pass = 0; $fail = 0;
function ok(string $what, $got, $want = true): void {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  [PASS] $what\n"; }
    else { $fail++; echo "  [FAIL] $what  expected=" . var_export($want, true)
                      . " got=" . var_export($got, true) . "\n"; }
}

$match = (string) file_get_contents(BASE_PATH . '/app/matching.php');
$ctrl  = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
$mail  = (string) file_get_contents(BASE_PATH . '/app/notify_email.php');

echo "\n-- all three are wired --\n";
ok('a match sends one',      str_contains($match, 'Somebody overlaps your dates in'), true);
ok('a request sends one',    str_contains($ctrl, 'Somebody wants to connect on your trip'), true);
ok('an acceptance sends one', str_contains($ctrl, 'You can message each other now'), true);

echo "\n-- and each one goes through the capped helper --\n";
/* Never rmt_mail_send() directly: the caps, the opt out, the unverified address check and the
   unsubscribe link all live in the helper, and a direct send would skip every one of them. */
ok('matching.php never sends mail itself', (bool) preg_match('/\brmt_mail_send\s*\(/', $match), false);
/* controllers.php has exactly one direct send and it is the admin mail check, which tests the
   mail provider rather than writing to a member. One, and it stays one. */
ok('controllers.php sends directly only for the admin mail check',
   substr_count($ctrl, 'rmt_mail_send('), 1);
ok('...and that one is the mail check',
   str_contains(substr($ctrl, (int) strpos($ctrl, 'rmt_mail_send('), 120), 'RuinMyTrip mail check'), true);
ok('the helper refuses an unverified address', str_contains($mail, "empty(\$u['email_verified_at'])"), true);
ok('...and anybody opted out',                 str_contains($mail, "opt_out'] === 1"), true);
ok('...and more than one an hour',             str_contains($mail, 'direct_mail_hour'), true);
ok('...and more than six a day',               str_contains($mail, 'direct_mail_day'), true);
ok('every one carries an unsubscribe link',    str_contains($mail, 'rmt_unsubscribe_url'), true);
ok('and says why it arrived',                  str_contains($mail, 'You are getting this because'), true);

echo "\n-- what they are not allowed to say --\n";
/* The rule these exist under: the email brings somebody back, it does not deliver the content.
   No message text, no exact dates, no other person's name, no address. */
$emails = [];
foreach ([$match, $ctrl] as $src) {
    if (preg_match_all('/rmt_notify_email_direct\((.*?)\);/s', $src, $m)) {
        foreach ($m[1] as $call) $emails[] = $call;
    }
}
ok('there are calls to inspect', count($emails) >= 3, true);
foreach ($emails as $i => $call) {
    ok("call $i carries no message body",  (bool) preg_match('/\$(body|message|text|note)\b/', $call), false);
    ok("call $i carries no email address", (bool) preg_match('/\bemail\b/i', $call), false);
    ok("call $i carries no exact dates",   (bool) preg_match('/date_from|date_to|\$from|\$to\b/', $call), false);
}
/* The overlap email names the city and nothing else about the other traveler. */
ok('the overlap email names a city and no person',
   str_contains($match, "'A traveler posted dates in ' . \$city . ' that overlap yours.'"), true);
ok('...and never the other username',
   (bool) preg_match('/rmt_notify_email_direct\([^;]*username/s', $match), false);

echo "\n-- a decline still tells nobody --\n";
ok('declining sends no email',
   (bool) preg_match('/Declined\. They are not told\./', $ctrl), true);

echo "\n-- duplicates --\n";
/* The match email sits inside the same loop as the notification, after both duplicate guards, so
   anything that cannot produce a second notification cannot produce a second email either. */
$loop = substr($match, (int) strpos($match, 'foreach (rmt_trip_match_user_ids'), 2200);
ok('the email is inside the guarded loop', str_contains($loop, 'rmt_notify_email_direct'), true);
ok('...after the seen guard',   strpos($loop, '$seen = q_one') < strpos($loop, 'rmt_notify_email_direct'), true);
ok('...and after the pending guard', strpos($loop, '$pending = q_one') < strpos($loop, 'rmt_notify_email_direct'), true);
/* A repeated connect press does not reach the insert or the email. */
$req = substr($ctrl, (int) strpos($ctrl, "if (\$r['created']) {"), 1400);
ok('a repeat connect press sends nothing', str_contains($req, 'rmt_notify_email_direct'), true);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL ACTIVATION EMAIL TESTS PASS ({$pass})\n";
