<?php
/**
 * Long usernames must never push a page sideways on a phone. The real check was a 390px headless
 * pass with every username swapped for a 57 character one (feed, city, profile, travelers, search,
 * talk, matches, saved, notifications); this keeps the rules that made it pass.
 *
 *   php tests/long_username_test.php
 */
declare(strict_types=1);
$css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/app.css');
$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "FAIL: $m\n"; } }
ok(str_contains($css, 'main :is(a,b,strong,span,p,h1,h2,h3,h4,li,td){overflow-wrap:anywhere}'), 'member text wraps inside main');
foreach (['.person-who', '.cc-post-main', '.cc-post-by', '.card-body', '.rail-row', '.profile-head > div'] as $sel) {
    ok((bool) preg_match('/' . preg_quote($sel, '/') . '[^{]*\{min-width:0/', $css), "$sel can shrink below its content");
}
ok(str_contains($css, '.profile-head h1{overflow-wrap:anywhere}'), 'the profile name wraps');
ok(!preg_match('/\.cc-post-body\{[^}]*pre-wrap/', $css), 'post bodies do not double their line breaks (nl2br already adds them)');
echo "long_username_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
