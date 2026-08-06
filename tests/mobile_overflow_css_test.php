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

/*
 * The hero search row, which is the site's PRIMARY call to action.
 *
 * Measured in a real browser at 375px: the pill-shaped row (search input + "Check a Destination")
 * needs about 450px of natural width, so the button's right edge landed at x=449 on a 375px
 * viewport. Because .hero sets overflow:hidden, the button was CLIPPED rather than merely scrolled
 * off — the primary conversion path was unreachable on a standard phone, with no scrollbar to hint
 * that anything was missing. Stacking the row below 680px is the fix; these assertions stop it
 * silently regressing when Playwright is unavailable.
 */
$mobileBlocks = [];
if (preg_match_all('/@media\s*\(max-width:\s*680px\s*\)\s*\{(.*?)
\}/s', $css, $m)) {
    $mobileBlocks = $m[1];
}
$mobileCss = implode("
", $mobileBlocks);
$check('a max-width:680px breakpoint exists', $mobileCss !== '');
$check('.hero-search stacks vertically on phones',
    (bool) preg_match('/\.hero-search\s*\{[^}]*flex-direction:\s*column/s', $mobileCss));
$check('.hero-search input goes full width',
    (bool) preg_match('/\.hero-search input\s*\{[^}]*width:\s*100%/s', $mobileCss));
$check('the hero CTA button goes full width (so it cannot be clipped)',
    (bool) preg_match('/\.hero-search \.btn\s*\{[^}]*width:\s*100%/s', $mobileCss));

/* The hero clips its children, which is what turned the overflow above into an invisible failure. */
$heroRule = css_rule($css, '.hero');
$check('.hero still clips (so any future overflow there is a real bug, not a scrollbar)',
    strpos($heroRule, 'overflow:hidden') !== false);

/* Wide content must scroll inside its own container rather than widening the page. */
$check('.table-scroll exists for wide admin tables',
    strpos(css_rule($css, '.table-scroll'), 'overflow-x:auto') !== false);

/* Grids that would otherwise force a minimum width collapse to fewer columns on phones. */
$check('.cat-grid collapses on phones',
    (bool) preg_match('/\.cat-grid\s*\{[^}]*grid-template-columns:\s*repeat\(2/s', $mobileCss));
$check('.signup-grid collapses to one column',
    (bool) preg_match('/@media\s*\(max-width:\s*900px\)\s*\{\s*\.signup-grid\s*\{\s*grid-template-columns:\s*1fr/s', $css));

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL MOBILE OVERFLOW CSS TESTS PASS\n";
