<?php
/**
 * Funnel invariants: the things that must never be true again.
 *
 * Every case here was found by signing up from zero in a browser and watching what the product
 * actually did. They are not style preferences; each one either lost a member's work or told them
 * two contradictory things on the first screen they ever saw.
 *
 *   - the verification page cannot claim it sent an email and that it failed, at the same time
 *   - no raw internal route is printed in copy addressed to a traveller
 *   - a member with no trip is asked where they are going, not what they just found out
 *   - a first trip written before the address came back is held, not discarded
 *   - and written exactly once, with everything typed still in it
 *
 * Most of this reads source rather than driving a browser on purpose. A funnel invariant is worth
 * having in the gate that runs on every push, and these are the shapes that broke.
 *
 *   php tests/onboarding_funnel_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$notice      = (string) file_get_contents($root . '/views/auth/verify_notice.php');
$feed        = (string) file_get_contents($root . '/views/feed.php');
$controllers = (string) file_get_contents($root . '/app/controllers.php');
$pending     = (string) file_get_contents($root . '/app/onboarding_pending.php');

echo "-- the verification page tells one story --\n";
/* The banner said the email could not be sent; the card beneath said "We sent a link to you".
   First screen, first thirty seconds, both on it. */
ok(str_contains($notice, '$mailSent ?? true'),
   'the page is told whether the send actually worked');
ok(substr_count($notice, 'We sent a link to') === 1,
   'and claims a send in exactly one branch');
ok(str_contains($notice, 'did not go out'), 'with a failure branch that says so plainly');
ok(str_contains($controllers, "\$_SESSION['rmt_mail_ok']"),
   'the controller passes the real result rather than the usual one');

echo "\n-- no internal routes in traveller copy --\n";
/* "Request a new link from /verify-email." was printed as a sentence, not as a link. */
foreach ([['auth/verify_notice.php', $notice], ['feed.php', $feed]] as [$name, $src]) {
    $text = preg_replace('/<\?php.*?\?>/s', '', $src) ?? $src;      // drop PHP, keep what is rendered
    $text = preg_replace('/<[^>]+>/', ' ', $text) ?? $text;          // drop tags, keep prose
    ok(!preg_match('#(^|\s)/(verify-email|trip/new|feed|matches|talk)(\s|\.|,|$)#', $text),
       "$name prints no raw route in its prose");
}
ok(!str_contains($controllers, 'Request a new link from /verify-email'),
   'and the flash that used to has gone');

echo "\n-- a member with no trip is asked the first question --\n";
ok(str_contains($feed, 'Where are you going?'), 'the home page asks it');
/* Guarded on both: no upcoming trip AND no trip that just ended, because somebody who got back
   from Lisbon on Tuesday is asked how it went rather than where they are going. */
ok(str_contains($feed, '<?php if (!$nt && !$je): ?>'),
   'only when there is neither a trip to show nor one that just ended');
ok(str_contains($feed, 'How was <?= e((string) ($je[' . chr(39) . 'dest_name' . chr(39) . ']'),
   'and a trip that just ended is asked about instead');
ok(str_contains($feed, "\$nt ? 'Where are you going, or what did you just find out?'"),
   'and the composer below does not ask the same thing twice');

echo "\n-- a first trip survives the publish gate --\n";
/* Every member is unverified thirty seconds after joining, so the first trip anybody writes was
   the submission most certain to be thrown away. */
ok(str_contains($controllers, "rmt_pending_stash(['trip' => \$_POST])"),
   'an unverified trip is held rather than discarded');
ok(str_contains($controllers, 'function rmt_trip_create_row('),
   'and both paths write a trip through one function, so they cannot drift');
ok(substr_count($controllers, 'INSERT INTO trips (user_id,destination_id,title,slug,body,cover_url') === 1,
   'which is the only place a trip is inserted');
ok(str_contains($pending, 'rmt_trip_validate($held[\'trip\'])'),
   'held work is re-validated on the way out rather than trusted');
ok(str_contains($pending, 'unset($_SESSION[RMT_PENDING_KEY]);'),
   'and the slot is cleared before anything is written, so a second confirmation writes nothing');
ok(str_contains($controllers, "Your trip is saved."),
   'the member is told their work is safe rather than left to guess');
ok(str_contains($controllers, "Your trip is live"),
   'and told where it went when it goes live');

echo "\n-- the gate itself has not moved --\n";
ok(str_contains($controllers, 'if (!email_is_verified($me)) {'),
   'an unverified member still cannot publish a trip');
ok(!str_contains($pending, "\$_SESSION[RMT_PENDING_KEY]['trip'] = "),
   'nothing writes into the held slot except the one stash function');

echo "\nonboarding_funnel_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
