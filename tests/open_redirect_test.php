<?php
/**
 * Regression test for the open redirect in the POST-only action endpoints.
 *
 * Every one of these forms (follow, like/save, destination save, place save, review vote,
 * compliment, comment, comment delete, block, unblock, message send) carries a `return` field
 * naming the page the button was pressed on, and every one of them used to hand that value
 * straight to redirect(). Anyone could hand out a link to ruinmytrip.com that deposited the
 * visitor on their own site instead -- our domain in the link, their page on the screen. That is
 * how a phishing page borrows a trusted host.
 *
 * Two halves are tested:
 *   1. rmt_return_to() itself, against the values an attacker actually tries
 *   2. that no action endpoint has gone back to following input('return') directly
 *
 * The second half is the one that matters over time: the helper is easy to keep correct and easy
 * to forget to use.
 *
 *   php tests/open_redirect_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/helpers.php';

// rmt_return_to() lives in app/auth.php, which pulls in the session/user machinery this test has
// no use for. Only the two functions under test are needed, so they are lifted out by name.
$authSrc = file_get_contents(BASE_PATH . '/app/auth.php');
function lift(string $src, string $name): string {
    $start = strpos($src, "function {$name}(");
    if ($start === false) return '';
    $depth = 0; $i = strpos($src, '{', $start);
    for (; $i < strlen($src); $i++) {
        if ($src[$i] === '{') $depth++;
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) return substr($src, $start, $i - $start + 1); }
    }
    return '';
}
eval(lift($authSrc, 'rmt_safe_return_path') . "\n" . lift($authSrc, 'rmt_return_to'));

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if ($cond) { echo "  PASS  $name\n"; return; }
    $fails++;
    echo "  FAIL  $name" . ($detail !== '' ? "  ($detail)" : '') . "\n";
}

/** Drive rmt_return_to() the way a request does: through the POST body. */
function returns_to(?string $posted): string {
    $_POST = $_GET = [];
    if ($posted !== null) $_POST['return'] = $posted;
    return rmt_return_to('/fallback');
}

echo "open redirect\n";

// --- What the helper does with an honest value ------------------------------------------------
ok('a same-origin path is kept', returns_to('/d/barcelona-spain') === '/d/barcelona-spain');
ok('a deep path is kept', returns_to('/p/hotel-arts-barcelona') === '/p/hotel-arts-barcelona');
ok('our own absolute URL is reduced to its path',
   returns_to('https://ruinmytrip.com/saved') === '/saved',
   returns_to('https://ruinmytrip.com/saved'));
ok('our bare origin becomes the site root', returns_to('https://ruinmytrip.com') === '/');
ok('nothing posted uses the caller\'s fallback', returns_to(null) === '/fallback');
ok('an empty value uses the caller\'s fallback', returns_to('') === '/fallback');
ok('whitespace only uses the caller\'s fallback', returns_to('   ') === '/fallback');

// --- What it does with the values an attacker sends -------------------------------------------
// Every one of these must land somewhere on this site. The exact landing page does not matter;
// that it is never another host is the whole point.
$hostile = [
    'an absolute URL to another host'      => 'https://evil.example/login',
    'a protocol-relative URL'              => '//evil.example/login',
    'a scheme-less host'                   => 'evil.example/login',
    'a javascript: URL'                    => 'javascript:alert(1)',
    'a data: URL'                          => 'data:text/html,<script>alert(1)</script>',
    'a backslash-prefixed authority'       => '\\\\evil.example/login',
    'our host as a prefix of theirs'       => 'https://ruinmytrip.com.evil.example/x',
    'a userinfo trick'                     => 'https://ruinmytrip.com@evil.example/x',
];
foreach ($hostile as $label => $value) {
    $got = returns_to($value);
    ok("refuses $label", $got !== '' && $got[0] === '/' && !str_starts_with($got, '//')
        && !str_contains($got, '://'), 'got=' . $got);
}

// --- No endpoint follows the raw value --------------------------------------------------------
// The helper only helps where it is used. A new action endpoint that reaches for input('return')
// reintroduces exactly the bug this file exists for.
foreach (['app/controllers.php', 'app/messages.php', 'app/auth.php'] as $rel) {
    $src = file_get_contents(BASE_PATH . '/' . $rel);
    ok("$rel: no redirect() follows input('return') directly",
        !preg_match("/redirect\(\s*input\(\s*'return'/", $src));
}

// The helper has to still be the thing that sanitises, not a passthrough that got hollowed out.
$authBody = lift($authSrc, 'rmt_return_to');
ok('rmt_return_to() normalises through rmt_safe_return_path',
   strpos($authBody, 'rmt_safe_return_path') !== false);

// And it must be reached for: a count near zero would mean the endpoints quietly stopped using it.
$uses = preg_match_all('/rmt_return_to\(/', file_get_contents(BASE_PATH . '/app/controllers.php'))
      + preg_match_all('/rmt_return_to\(/', file_get_contents(BASE_PATH . '/app/messages.php'));
ok('the action endpoints actually use it', $uses >= 30, 'uses=' . $uses);

echo $fails ? "\n$fails FAILED\n" : "\nAll open redirect tests passed.\n";
exit($fails ? 1 : 0);
