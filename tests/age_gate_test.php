<?php
/**
 * The 18+ step (app/age_gate.php).
 *
 * Every member who joined since 2026-10-01 has no birthdate, because joining asks only for a 16+
 * tick. can_host_meetups() needs one, so before this every new member was told "Travel buddies is
 * 18+" with no way forward, and a buddy post ticked on the trip first form vanished when the trip
 * went live. This checks the validator, the return path guard, and that no 18+ gate and no buddy
 * hand over can go back to dropping a member silently.
 *
 *   php tests/age_gate_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip'];
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/age_gate.php';

$fails = 0;
function ok(string $name, bool $cond): void {
    global $fails;
    echo ($cond ? 'PASS  ' : 'FAIL  ') . $name . "\n";
    if (!$cond) $fails++;
}

$today = '2026-10-29';
ok('a 30 year old is accepted', rmt_age_validate('1996-05-01', $today)[0] === '1996-05-01');
ok('a 17 year old is accepted and stored (under 18 is told, not refused)', rmt_age_validate('2009-01-15', $today)[0] === '2009-01-15');
ok('under 16 is refused', rmt_age_validate('2012-01-01', $today)[0] === null);
ok('a future date is refused', rmt_age_validate('2027-01-01', $today)[0] === null);
ok('a made up day is refused', rmt_age_validate('1990-02-31', $today)[0] === null);
ok('free text is refused', rmt_age_validate('yesterday', $today)[0] === null);
ok('an impossible age is refused', rmt_age_validate('1800-01-01', $today)[0] === null);
ok('blank is refused with a message', rmt_age_validate('', $today)[1] === 'Enter your date of birth.');

ok('a site path is kept', rmt_age_safe_return('/matches?new=12') === '/matches?new=12');
ok('another site is never a return', rmt_age_safe_return('https://evil.example/') === '/buddies');
ok('a protocol relative url is never a return', rmt_age_safe_return('//evil.example/x') === '/buddies');
ok('a backslash trick is never a return', rmt_age_safe_return('/\\evil.example') === '/buddies');

ok('a member with no birthdate is unknown', rmt_age_unknown(['id' => 5, 'birthdate' => null]));
ok('a member with a birthdate is known', !rmt_age_unknown(['id' => 5, 'birthdate' => '1990-01-01']));
ok('a visitor is not asked', !rmt_age_unknown(null));

// No gate may go back to a dead end for a member whose age is simply unknown.
foreach (['app/buddies.php', 'app/controllers.php'] as $f) {
    $src = (string) file_get_contents(BASE_PATH . '/' . $f);
    ok("$f: every refused 18+ check goes through rmt_adult_gate",
        !preg_match('/if \(!can_host_meetups\([^)]*\)\)\s*\{\s*flash\(/', $src));
}
$plan = (string) file_get_contents(BASE_PATH . '/app/plan_first.php');
ok('trip first: a buddy post waiting on age is held, not dropped', str_contains($plan, 'rmt_age_hold_buddy($uid'));
$pend = (string) file_get_contents(BASE_PATH . '/app/onboarding_pending.php');
ok('email confirm: a buddy post waiting on age is held, not dropped', str_contains($pend, 'rmt_age_hold_buddy($uid'));
$routes = (string) file_get_contents(BASE_PATH . '/public/index.php');
ok('the age step has its routes', str_contains($routes, "'age_form'") && str_contains($routes, "'age_submit'"));
$ev = (string) file_get_contents(BASE_PATH . '/app/contribution_events.php');
ok('age_confirmed is on the event list, so it is counted', str_contains($ev, "'age_confirmed'"));

echo $fails ? "\nage_gate_test: $fails failed\n" : "\nage_gate_test: all passed\n";
exit($fails ? 1 : 0);
