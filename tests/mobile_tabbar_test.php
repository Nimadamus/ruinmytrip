<?php
/**
 * The mobile app bar (views/layout/footer.php, .tabbar in app.css).
 *
 * Most people who will ever see this site will see it on a phone, where every route out of the
 * page was behind a hamburger: several taps to reach the thing they came for. The bar is five
 * thumb-reach targets, and the middle one is the single action the site exists to collect.
 *
 * What must hold:
 *   - it is on every page, because it is rendered in the layout footer, not per view.
 *   - it never appears on desktop, where the header nav is already one glance.
 *   - the targets are big enough to hit, and the page does not end underneath the bar.
 *   - the post button asks a logged-out visitor to join and carries them back to posting dates.
 *
 *   php tests/mobile_tabbar_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$footer = (string) file_get_contents($root . '/views/layout/footer.php');
$css    = (string) file_get_contents($root . '/public/assets/css/app.css');

ok('the bar is in the layout, so it is on every page', str_contains($footer, 'class="tabbar"'));
ok('it is a nav with a label', str_contains($footer, '<nav class="tabbar" aria-label='));
ok('every target carries an aria-label', substr_count($footer, 'aria-label="') >= 5);

// Hidden by default, shown only on small screens: a desktop reader must never see it.
ok('hidden by default', (bool) preg_match('/\.tabbar\{display:none\}/', $css));
ok('shown only inside a max-width media query',
   (bool) preg_match('/@media\s*\(max-width:\s*860px\)\s*\{[^}]*\.tabbar\s*\{[^}]*display:flex/s', $css));
ok('it is pinned to the bottom', (bool) preg_match('/\.tabbar\s*\{[^}]*position:fixed[^}]*bottom:0/s', $css));

// Thumb targets and the home indicator.
ok('targets are at least 44px', str_contains($css, 'min-height:44px'));
ok('the bar clears the iPhone home indicator', str_contains($css, 'env(safe-area-inset-bottom)'));
ok('page content is not hidden behind it', (bool) preg_match('/body\{padding-bottom:\d+px\}/', $css));

// The action in the middle, and what it does for somebody with no account.
ok('the middle button posts dates by default', str_contains($footer, "\$rmt_post_to = '/going'"));
/* On a city page it means "say something about this city": sending somebody looking at Lisbon to a
   generic form is how the thought gets lost between the tap and the box. */
ok('on a city page it points at that city composer',
   str_contains($footer, "'/d/' . \$rmt_m[1] . '/travelers#say'"));
ok('on talk it points at the talk composer', str_contains($footer, "'/talk#say'"));
$city = (string) file_get_contents($root . '/views/destination_travelers.php');
$talk = (string) file_get_contents($root . '/views/posts_index.php');
ok('both composers are somewhere a link can land',
   str_contains($city, 'id="say"') && str_contains($talk, 'id="say"'));
/* The return path is whatever the button was pointing at, so joining from Lisbon's page brings you
   back to Lisbon's composer rather than to a generic form. */
ok('a logged-out visitor is asked to join and brought back to where they tapped',
   str_contains($footer, "register?return=' . rawurlencode(\$rmt_post_to)"));
ok('the unread dot only renders when there is something unread',
   str_contains($footer, 'rmt_unread_notification_count') && str_contains($footer, 'tabbar-dot'));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
