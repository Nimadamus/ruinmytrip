<?php
/**
 * Regression test: the login form must re-fill the submitted email after a failed attempt.
 *
 * Every other form in this app (trip_new, guide_new, review_new, register) re-populates its
 * fields from the prior submission on validation failure, so the user isn't forced to retype
 * everything -- login.php was the one exception, silently clearing both fields even though only
 * the password was wrong. The password field must still never be pre-filled; only the email is
 * safe (and expected) to preserve.
 *
 *   php tests/login_form_test.php   -> PASS/FAIL per case, exits non-zero on failure.
 */
declare(strict_types=1);

$src = file_get_contents(dirname(__DIR__) . '/views/auth/login.php');

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) {
    printf("  [%s] %s\n", $ok ? 'PASS' : 'FAIL', $name);
    if (!$ok) $fail++;
};

// A naive "<input...>" regex breaks here: the attribute value itself contains a literal ">"
// as part of PHP's own short-echo closing sequence, which would end the match early. Locate
// each input by its name="..." anchor instead and inspect a fixed-size window around it.
$window = static function (string $src, string $anchor, int $span = 120): string {
    $pos = strpos($src, $anchor);
    return $pos === false ? '' : substr($src, $pos, $span);
};

$emailInput = $window($src, 'name="email"');
$check('email input found in views/auth/login.php', $emailInput !== '');
$check('email input re-fills the submitted value after a failed attempt',
    (bool) preg_match('/value="<\?=\s*e\(input\([\'"]email[\'"]\)\)\s*\?>"/', $emailInput));

$passwordInput = $window($src, 'name="password"');
$check('password input found in views/auth/login.php', $passwordInput !== '');
$check('password input is never pre-filled (no value= attribute before the tag closes)',
    strpos(substr($passwordInput, 0, strpos($passwordInput, '>') ?: strlen($passwordInput)), 'value=') === false);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL LOGIN FORM TESTS PASS\n";
