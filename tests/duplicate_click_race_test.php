<?php
/**
 * Regression test for the like/save/follow/meetup-RSVP/registration double-click race: each of
 * these is protected by a DB uniqueness constraint -- (user_id, target_type, target_id) for
 * likes/saves, (follower_id, followee_id) for follows, (meetup_id, user_id) for RSVPs,
 * username/email UNIQUE for registration -- so a duplicate row can never land, but before this
 * fix, two near-simultaneous requests that both passed the "not taken yet" check raced to
 * INSERT, and the loser's uncaught PDOException surfaced as a raw 500 page instead of a no-op
 * (or, for registration, instead of the same friendly "already taken" message everyone else
 * gets from the normal, non-race duplicate-signup path).
 *
 * Confirmed live locally (not reproducible as a deterministic unit test, since it depends on
 * request timing): a second INSERT of an identical (user_id,target_type,target_id) row throws
 * PDOException with getCode() === '23000' on SQLite -- exactly the code react_action/
 * follow_action now catch and swallow. Postgres raises the equivalent violation as '23505',
 * which the same catch also covers.
 *
 * This test is static/source-level (mirrors tests/search_case_test.php's approach): it verifies
 * both the try/catch shape and that it only swallows the specific duplicate-key codes rather
 * than silently absorbing every PDOException in that function.
 *
 *   php tests/duplicate_click_race_test.php   -> PASS/FAIL per case, exits non-zero on failure.
 */
declare(strict_types=1);

$controllersSrc = file_get_contents(dirname(__DIR__) . '/app/controllers.php');
$authSrc = file_get_contents(dirname(__DIR__) . '/app/auth.php');
$src = $controllersSrc . "\n" . $authSrc;

function extract_function(string $src, string $name): string {
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

foreach (['react_action', 'follow_action', 'meetup_rsvp', 'register_user'] as $fn) {
    $body = extract_function($src, $fn);
    $check("{$fn}() found in app/controllers.php or app/auth.php", $body !== '');
    $check("{$fn}(): the racy INSERT is inside a try block", (bool) preg_match('/try\s*\{[^}]*INSERT INTO/s', $body));
    $check("{$fn}(): catches \\PDOException specifically (not a bare Throwable/Exception)", (bool) preg_match('/catch\s*\(\s*\\\\?PDOException/', $body));
    // Must check the duplicate-key codes for BOTH drivers this app runs on (sqlite dev, pgsql
    // prod) -- and must rethrow anything else, or a real unrelated DB failure goes silently missing.
    $check("{$fn}(): recognizes SQLite's duplicate-key code 23000", strpos($body, "'23000'") !== false);
    $check("{$fn}(): recognizes Postgres's duplicate-key code 23505", strpos($body, "'23505'") !== false);
    $check("{$fn}(): rethrows any other error code instead of swallowing it", (bool) preg_match('/throw \$e;/', $body));
}

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL DUPLICATE-CLICK RACE TESTS PASS\n";
