<?php
/**
 * Regression test: flex rows that lay out variable-length, user-generated content (usernames,
 * timestamps, badges, profile stat counts) must wrap on narrow viewports.
 *
 * Found via a static CSS audit while Playwright (the usual browser-driven mobile check) was
 * disconnected: every other multi-item flex row in the stylesheet (.profile-head, .rating-split,
 * .tag-list, .invite-row, .hero-stats) already sets flex-wrap:wrap, but .stat-inline (the
 * "N reviews · N places visited · N followers · N following" row on every profile page) and
 * .meta-row (the avatar/username/timestamp/badge row used across 9 templates -- trip cards,
 * comments, reviews, guides, meetups) did not. At a 320-375px phone width, four stat phrases or a
 * long username plus a "Verified visit" badge easily exceed the viewport, forcing horizontal
 * overflow/a scrollbar on some of the most-viewed pages in the app.
 *
 *   php tests/mobile_overflow_css_test.php   -> PASS/FAIL per case, exits non-zero on failure.
 */
declare(strict_types=1);

$css = file_get_contents(dirname(__DIR__) . '/public/assets/css/app.css');

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) {
    printf("  [%s] %s\n", $ok ? 'PASS' : 'FAIL', $name);
    if (!$ok) $fail++;
};

/** Extracts a top-level CSS rule's declaration block by selector, e.g. ".stat-inline{...}". */
function css_rule(string $css, string $selector): string {
    $needle = $selector . '{';
    $pos = strpos($css, $needle);
    if ($pos === false) return '';
    $end = strpos($css, '}', $pos);
    return $end === false ? '' : substr($css, $pos, $end - $pos + 1);
}

foreach (['.stat-inline', '.meta-row'] as $selector) {
    $rule = css_rule($css, $selector);
    $check("{$selector} rule found in app.css", $rule !== '');
    $check("{$selector} is display:flex (still a flex row)", strpos($rule, 'display:flex') !== false);
    $check("{$selector} wraps on narrow viewports (flex-wrap:wrap)", strpos($rule, 'flex-wrap:wrap') !== false);
}

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL MOBILE OVERFLOW CSS TESTS PASS\n";
