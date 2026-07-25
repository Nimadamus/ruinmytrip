<?php
/**
 * Regression tests for double-submit protection (app/idempotency.php).
 *
 * trip_create/guide_create/review_create/comment_action had no duplicate-submission guard at
 * all: a double-click, a refresh-and-resubmit, or a replayed POST created a second identical row
 * every time, since none of those tables have a uniqueness constraint that could catch it.
 *
 *   php tests/submit_token_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/idempotency.php';

$fail = 0;
$check = function (string $name, $got, $expect) use (&$fail) {
    $ok = $got === $expect;
    printf("  [%s] %-60s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
        var_export($expect, true), var_export($got, true));
    if (!$ok) $fail++;
};

$_SESSION = [];

echo "-- basic token lifecycle --\n";
$t = rmt_submit_token('trip_new');
$check('fresh token is a 32-char hex string', (bool) preg_match('/^[0-9a-f]{32}$/', $t), true);
$check('a fresh token is accepted', rmt_submit_ok('trip_new', $t), true);
$check('the same token cannot be reused (single-use)', rmt_submit_ok('trip_new', $t), false);

echo "\n-- missing/unknown/empty tokens are rejected --\n";
$check('null token rejected', rmt_submit_ok('trip_new', null), false);
$check('empty-string token rejected', rmt_submit_ok('trip_new', ''), false);
$check('unknown token rejected', rmt_submit_ok('trip_new', 'not-a-real-token'), false);

echo "\n-- a token from one form does not validate another form --\n";
$tTrip = rmt_submit_token('trip_new');
$check('trip_new token rejected against guide_new', rmt_submit_ok('guide_new', $tTrip), false);
$check('trip_new token still valid against its own form', rmt_submit_ok('trip_new', $tTrip), true);

echo "\n-- multi-tab: two renders of the same form each get an independently valid token --\n";
$_SESSION = [];
$tabA = rmt_submit_token('guide_new');
$tabB = rmt_submit_token('guide_new'); // simulates opening a second tab to the same "new" page
$check('opening a second tab does not invalidate the first tab\'s token', rmt_submit_ok('guide_new', $tabA), true);
$check('the second tab\'s token is still independently valid', rmt_submit_ok('guide_new', $tabB), true);

echo "\n-- replayed POST: the exact same request sent twice is blocked the second time --\n";
$_SESSION = [];
$replay = rmt_submit_token('comment_trip_1');
$check('first delivery of a replayed POST succeeds', rmt_submit_ok('comment_trip_1', $replay), true);
$check('second delivery of the identical replayed POST is blocked', rmt_submit_ok('comment_trip_1', $replay), false);

echo "\n-- token set is bounded so an abandoned form page can't grow the session forever --\n";
$_SESSION = [];
for ($i = 0; $i < 25; $i++) { $tokens[] = rmt_submit_token('trip_new'); }
$check('token set capped at 20 entries', count($_SESSION['_submit']['trip_new']), 20);
$check('the most recent token past the cap is still valid', rmt_submit_ok('trip_new', end($tokens)), true);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL SUBMIT TOKEN TESTS PASS\n";
