<?php
/**
 * The five country landing pages send each button somewhere real.
 * Every other landing page is left alone.
 *
 *   php tests/landing_cta_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
function url(string $path = ''): string {
    return 'https://ruinmytrip.com/' . ltrim($path, '/');
}
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
require BASE_PATH . '/app/buddy_landing.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$want = [
    'iceland' => ['reykjavik-iceland', 7, 'Reykjavik', 'Iceland'],
    'japan'   => ['tokyo-japan', 22, 'Tokyo', 'Japan'],
    'greece'  => ['athens-greece', 46, 'Athens', 'Greece'],
    'italy'   => ['rome-italy', 14, 'Rome', 'Italy'],
    'mexico'  => ['mexico-city-mexico', 17, 'Mexico City', 'Mexico'],
];
foreach ($want as $slug => [$citySlug, $id, $city, $country]) {
    $cta = rmt_buddy_landing_cta($slug, $country, ['id' => $id, 'slug' => $citySlug, 'name' => $city], null);
    ok("$slug trip opens that city without the buddy essay", $cta !== null
        && str_contains($cta['trip'], 'plan?d=' . $citySlug . '&cta=buddy_landing')
        && !str_contains($cta['trip'], 'buddy=1'));
    ok("$slug review is that city", str_contains((string) ($cta['review'] ?? ''), "review/new?destination=$id&about=destination"));
    ok("$slug buddies keep the country", str_contains((string) ($cta['buddies'] ?? ''), 'buddies?' . http_build_query(['where' => $country])));
    ok("$slug account returns to the same page", str_contains((string) ($cta['join'] ?? ''), rawurlencode('/travel-buddies/' . $slug)));
    ok("$slug profile starts at welcome", str_contains((string) ($cta['profile'] ?? ''), rawurlencode('/welcome')));
}

$in = rmt_buddy_landing_cta('iceland', 'Iceland', ['id' => 7, 'slug' => 'reykjavik-iceland', 'name' => 'Reykjavik'], ['username' => 'ada']);
ok('a member builds the profile they already have', str_contains((string) $in['profile'], '/u/ada/edit'));
ok('thailand is unchanged', rmt_buddy_landing_cta('thailand', 'Thailand', ['id' => 1, 'slug' => 'bangkok-thailand', 'name' => 'Bangkok'], null) === null);
ok('a missing city does not invent a button', rmt_buddy_landing_cta('iceland', 'Iceland', null, null) === null);

$acts = $in;
$onDark = true;
$me = null;
ob_start();
include BASE_PATH . '/views/_landing_acts.php';
$guestHtml = ob_get_clean();
ok('a guest is asked to create an account', str_contains($guestHtml, 'Create a free account') && str_contains($guestHtml, 'Build your profile'));
ok('guest buttons stack as real links', substr_count($guestHtml, '<a class="btn') === 5);

$me = ['username' => 'ada'];
$onDark = false;
ob_start();
include BASE_PATH . '/views/_landing_acts.php';
$memberHtml = ob_get_clean();
ok('a member is not asked to create an account', !str_contains($memberHtml, 'Create a free account') && str_contains($memberHtml, '/u/ada/edit'));
ok('buttons are tall enough to tap', str_contains((string) file_get_contents(BASE_PATH . '/public/assets/css/app.css'), '.buddy-acts .btn{min-height:44px'));

echo $fails ? "\nlanding_cta_test: $fails FAILED\n" : "\nlanding_cta_test: all passed\n";
exit($fails ? 1 : 0);
