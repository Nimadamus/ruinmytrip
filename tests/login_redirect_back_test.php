<?php
/**
 * Regression test: signing in after being bounced to /login from a protected route must land
 * back on that route, not always on /feed -- and the return path must never be usable as an
 * open redirect.
 *
 * Before this fix, require_login() sent every logged-out visitor to a protected route straight
 * to plain /login with no memory of where they were headed, and login_submit() always redirected
 * to /feed on success. A logged-out tap on a notification link, a deep link, or a bookmark to
 * (say) /trip/new meant: sign in, land on /feed, then have to navigate there again by hand --
 * most annoying on mobile, where re-finding a specific page is slower than on desktop.
 *
 *   php tests/login_redirect_back_test.php   -> PASS/FAIL per case, exits non-zero on failure.
 */
declare(strict_types=1);

$GLOBALS['config'] = ['app_url' => 'https://ruinmytrip.com'];
require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/auth.php';

$fail = 0;
$check = function (string $name, $got, $expect) use (&$fail) {
    $ok = $got === $expect;
    printf("  [%s] %-65s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
        var_export($expect, true), var_export($got, true));
    if (!$ok) $fail++;
};

$check('a real relative path is preserved', rmt_safe_return_path('/trip/5/kyoto-story'), '/trip/5/kyoto-story');
$check('a path with a query string is preserved', rmt_safe_return_path('/explore?q=kyoto'), '/explore?q=kyoto');
$check('empty path falls back to /feed', rmt_safe_return_path(''), '/feed');
$check('a bare filename with no leading slash falls back to /feed', rmt_safe_return_path('trip/5'), '/feed');
$check('a protocol-relative path ("//host") falls back to /feed -- would redirect off-site', rmt_safe_return_path('//evil.example.com/phish'), '/feed');
$check('a full external URL falls back to /feed -- open-redirect protection', rmt_safe_return_path('https://evil.example.com/phish'), '/feed');
$check('a javascript: URL falls back to /feed', rmt_safe_return_path('javascript:alert(1)'), '/feed');
// Action forms (comment/react/follow/report/meetup RSVP) build their `return` field with url(),
// which always emits a same-origin ABSOLUTE URL, not a bare path -- confirmed live: this reached
// rmt_safe_return_path() as "https://ruinmytrip.com/trip/22/..." and fell back to /feed instead
// of the trip page, because the naive checks correctly (but too broadly) reject anything
// containing "://". A same-origin absolute URL must resolve to just its path+query.
$check('a same-origin absolute URL resolves to its path', rmt_safe_return_path('https://ruinmytrip.com/trip/22/qa-test'), '/trip/22/qa-test');
$check('a same-origin absolute URL with a query string resolves correctly', rmt_safe_return_path('https://ruinmytrip.com/explore?q=kyoto'), '/explore?q=kyoto');
$check('the bare origin with no path resolves to /', rmt_safe_return_path('https://ruinmytrip.com'), '/');
// Prefix-confusion attacks must still fail: a foreign host that merely starts with our domain
// name is NOT a same-origin match and must not be treated as one.
$check('a look-alike host ("ruinmytrip.com.evil.com") is NOT treated as same-origin', rmt_safe_return_path('https://ruinmytrip.com.evil.com/phish'), '/feed');
$check('userinfo-confusion ("ruinmytrip.com@evil.com") is NOT treated as same-origin', rmt_safe_return_path('https://ruinmytrip.com@evil.com/phish'), '/feed');
// Conservative by design: a path merely containing "://" anywhere (even as a query value) is
// rejected rather than parsed apart to check where it actually points. Falling back to /feed is
// always safe; trying to be clever about what counts as "safe enough" is how open redirects
// happen in the first place.
$check('a path containing "://" anywhere is rejected even mid-query (safe-by-default, not parsed)',
    rmt_safe_return_path('/redirect?next=http://example.com'), '/feed');

// require_login() itself ends in redirect(), which calls exit() -- it can't be invoked directly
// in this process. POST-only action endpoints (comment, react, follow, report, meetup RSVP) have
// no GET route at all, so capturing their raw REQUEST_URI as the return target sent a freshly
// logged-in user to a dead 404 instead of back to the page they were actually on (confirmed live:
// a session expiring mid comment-submit landed on /login?return=%2Fcomment, and GET /comment is a
// 404). Those forms already carry their own `return` field pointing at the real page for their
// own post-success redirect; this is a static check that require_login() prefers it.
$authSrc = file_get_contents(dirname(__DIR__) . '/app/auth.php');
$reqLoginStart = strpos($authSrc, 'function require_login(');
$reqLoginBody = $reqLoginStart === false ? '' : substr($authSrc, $reqLoginStart, 1400);
$check("require_login() prefers input('return') over the raw request URI",
    (bool) preg_match('/input\([\'"]return[\'"]\)\s*\?:/', $reqLoginBody), true);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL LOGIN REDIRECT-BACK TESTS PASS\n";
