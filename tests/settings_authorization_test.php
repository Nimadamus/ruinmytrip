<?php
/**
 * Regression test: /settings (the legacy profile-settings route, still linked from older pages)
 * must require login, require a valid CSRF token, and only ever write the CURRENTLY
 * AUTHENTICATED user's own profile row -- never a caller-supplied id.
 *
 * This was flagged as UNTESTED (not as a known bug) after an earlier audit batch, then actually
 * reproduced end to end against production before writing this test. Every scenario came back
 * correctly blocked:
 *   - unauthenticated GET  /settings  -> 302 to /login?return=%2Fsettings
 *   - unauthenticated POST /settings -> 302 to /login?return=%2Fsettings, no write
 *   - authenticated POST, no _csrf field    -> 403 "Your form session expired.", no write
 *   - authenticated POST, garbage _csrf     -> 403, no write
 *   - authenticated POST, valid _csrf       -> 302 success, write persisted
 *   - /Settings (case variant)  -> 404 (no route-matching bypass)
 * No code change was needed; this test exists purely to guard that behavior against regression.
 *
 * settings_form()/settings_save() both end in redirect(), which calls exit() -- they can't be
 * invoked directly in a test process, so the require_login()/csrf_check() ordering is a
 * static/source-level check (mirrors tests/deleted_content_media_cleanup_test.php's approach).
 * rmt_profile_validate() (the actual field-validation + IDOR-relevant logic settings_save() reuses
 * from profile_edit_submit(), so the two routes can never drift apart) IS a plain function and is
 * tested dynamically below.
 *
 *   php tests/settings_authorization_test.php   -> PASS/FAIL per case, exits non-zero on failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/profiles.php';

$src = file_get_contents(BASE_PATH . '/app/controllers.php');

function extract_fn(string $src, string $name): string {
    $start = strpos($src, "function {$name}(");
    if ($start === false) return '';
    $depth = 0; $i = strpos($src, '{', $start); $bodyStart = $i;
    for (; $i < strlen($src); $i++) {
        if ($src[$i] === '{') $depth++;
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) return substr($src, $bodyStart, $i - $bodyStart + 1); }
    }
    return '';
}

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) {
    printf("  [%s] %s\n", $ok ? 'PASS' : 'FAIL', $name);
    if (!$ok) $fail++;
};

// ---- settings_form(): must require login before anything else ----
$formBody = extract_fn($src, 'settings_form');
$check('settings_form() found in controllers.php', $formBody !== '');
$check('settings_form() calls require_login()', strpos($formBody, 'require_login()') !== false);

// ---- settings_save(): auth, then CSRF, in that order, before any write ----
$saveBody = extract_fn($src, 'settings_save');
$check('settings_save() found in controllers.php', $saveBody !== '');
$loginPos = strpos($saveBody, 'require_login()');
$csrfPos  = strpos($saveBody, 'csrf_check()');
$updatePos = strpos($saveBody, 'UPDATE profiles');
$check('settings_save() calls require_login()', $loginPos !== false);
$check('settings_save() calls csrf_check()', $csrfPos !== false);
$check('require_login() runs before csrf_check() (auth gates before CSRF is even considered)',
    $loginPos !== false && $csrfPos !== false && $loginPos < $csrfPos);
$check('both auth checks run before the UPDATE (no write path skips them)',
    $updatePos !== false && $loginPos < $updatePos && $csrfPos < $updatePos);

// ---- IDOR guard: the write must target the AUTHENTICATED user's own id, never a posted one ----
// A naive "no closing bracket" regex breaks here: the execute([...]) array literal contains
// $d['display_name']-style nested brackets of its own. Check the two load-bearing facts
// independently instead of trying to match the whole statement in one pattern.
$check("settings_save()'s UPDATE targets rows by user_id",
    (bool) preg_match('/UPDATE profiles SET .*WHERE user_id\s*=\s*\?/', $saveBody));
$check("...and the bound value is (int)\$me['id'] (current_user()'s own id, never user-supplied)",
    (bool) preg_match('/\(int\)\s*\$me\[.id.\]\]\);/', $saveBody));
$check("settings_save() never reads a user id out of \$_POST/\$_GET for the target row",
    strpos($saveBody, "input('user_id')") === false && strpos($saveBody, "input(\"user_id\")") === false);

// ---- settings_save() reuses the same validator as profile_edit_submit() (no drift) ----
$check('settings_save() validates via rmt_profile_validate($_POST), same as profile_edit_submit()',
    strpos($saveBody, 'rmt_profile_validate($_POST)') !== false);

// ---- rmt_profile_validate() itself: real dynamic behavior, not just source matching ----
$ok = rmt_profile_validate(['display_name' => 'A Real Name', 'bio' => 'A bio.', 'home_city' => 'Lisbon']);
$check('rmt_profile_validate(): valid input passes', $ok['ok'] === true);

$badAvatar = rmt_profile_validate(['avatar_url' => '/relative/path.jpg']);
$check('rmt_profile_validate(): relative avatar_url rejected (javascript:/data: guard)', $badAvatar['ok'] === false);

$tooLong = rmt_profile_validate(['display_name' => str_repeat('x', 61)]);
$check('rmt_profile_validate(): over-length display_name rejected', $tooLong['ok'] === false);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL SETTINGS AUTHORIZATION TESTS PASS\n";
