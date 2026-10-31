<?php
/**
 * Sign out is POST with the CSRF token (2026-10-30).
 *
 * A GET /logout let any page anywhere sign a member out with an <img src>. GET now renders a page
 * with a Sign out button and changes nothing; POST /logout checks the token first. The browser
 * side (GET keeps you signed in, a tokenless POST is refused) is in scripts/ci/member_journey_e2e.py M5.
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$fail = 0; $pass = 0;
function ok(string $name, bool $cond): void {
    global $fail, $pass;
    if ($cond) { $pass++; echo "  [PASS] $name\n"; } else { $fail++; echo "  [FAIL] $name\n"; }
}
$router = file_get_contents("$root/public/index.php");
$ctl = file_get_contents("$root/app/controllers.php");
$route = fn(string $m, string $fn): bool => (bool) preg_match('~\[\'' . $m . '\',\s*\'#\^/logout\$#\',\s*\'' . $fn . '\'\]~', $router);
ok('GET /logout routes to the confirm page', $route('GET', 'logout_form'));
ok('POST /logout routes to the action', $route('POST', 'logout_action'));
ok('no GET route signs out', !$route('GET', 'logout_action'));
preg_match('/function logout_action\(array \$a\): void \{(.*?)\n/s', $ctl, $m);
$body = $m[1] ?? '';
ok('logout_action checks the CSRF token before signing out',
   ($p1 = strpos($body, 'csrf_check()')) !== false && ($p2 = strpos($body, 'logout()')) !== false && $p1 < $p2);
preg_match('/function logout_form\(array \$a\): void \{(.*?)\n\}/s', $ctl, $m);
ok('logout_form does not call logout()', isset($m[1]) && !str_contains($m[1], 'logout()'));
$links = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/views", FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!str_ends_with((string) $f, '.php')) continue;
    $src = file_get_contents((string) $f);
    if (preg_match('/href="[^"]*url\(\'logout\'\)/', $src) || preg_match('/href="[^"]*\/logout"/', $src)) $links[] = basename((string) $f);
    if (preg_match_all('/<form[^>]*url\(\'logout\'\)[^>]*>(.{0,80})/', $src, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $one) {
            ok(basename((string) $f) . ': sign out form is POST', str_contains($one[0], 'method="post"'));
            ok(basename((string) $f) . ': sign out form carries the token', str_contains($one[1], 'csrf_field()'));
        }
    }
}
ok('no view links to /logout (every sign out is a form)', $links === []);
$v = file_get_contents("$root/views/auth/logout.php");
ok('the GET page offers a POST Sign out button with the token', str_contains($v, 'method="post"') && str_contains($v, 'csrf_field()'));
echo "\nlogout_csrf_test: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
