<?php
/**
 * Regression tests for CSRF hardening. Unit-tests csrf_valid() against every token-state
 * combination, so the empty-vs-empty bypass can never silently return.
 *
 *   php tests/csrf_test.php   -> prints PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

// csrf.php uses e() from helpers.php; load both. No session/DB needed — csrf_valid() reads
// $_SESSION and $_POST superglobals directly.
require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/csrf.php';

$GOOD = str_repeat('a', 64);   // a well-formed 64-hex token
$OTHER = str_repeat('b', 64);

$cases = [
    // [name, session token, sent token, expected csrf_valid()]
    ['1 valid token -> accepted',            $GOOD, $GOOD, true],
    ['2 missing submitted token -> rejected', $GOOD, null,  false],
    ['3 empty submitted token -> rejected',   $GOOD, '',    false],
    ['4 missing session token -> rejected',   null,  $GOOD, false],
    ['5 empty session token -> rejected',     '',    $GOOD, false],
    ['6 mismatched tokens -> rejected',       $GOOD, $OTHER, false],
    // the exact old-bug shape: both empty must NOT pass
    ['6b empty vs empty -> rejected',         '',    '',    false],
    ['6c both missing -> rejected',           null,  null,  false],
    // non-string inputs must be rejected, not throw
    ['6d array submitted token -> rejected',  $GOOD, ['x'], false],
];

$fail = 0;
foreach ($cases as [$name, $session, $sent, $expect]) {
    // Reset superglobals for each case.
    if ($session === null) unset($_SESSION['_csrf']); else $_SESSION['_csrf'] = $session;
    if ($sent === null)    unset($_POST['_csrf']);    else $_POST['_csrf']    = $sent;

    $got = csrf_valid();
    $ok = ($got === $expect);
    if (!$ok) $fail++;
    printf("  [%s] %-40s expected=%s got=%s\n",
        $ok ? 'PASS' : 'FAIL', $name,
        $expect ? 'true' : 'false', $got ? 'true' : 'false');
}

echo "\n" . ($fail === 0 ? "ALL CSRF UNIT TESTS PASS\n" : "$fail FAILURE(S)\n");
exit($fail === 0 ? 0 : 1);
