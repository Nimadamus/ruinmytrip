<?php
/**
 * A place title says "prices" when, and only when, the write up names a price.
 *
 * The title read $ed['prices'], a column place_editorial never had (it is `tickets`), so every
 * write up was titled "opening hours" or "address & map" while leading with a price.
 *
 *   php tests/place_title_price_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip'];
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/seo.php';

$fails = 0;
function ok(string $name, bool $cond): void {
    global $fails;
    echo ($cond ? 'PASS  ' : 'FAIL  ') . $name . "\n";
    if (!$cond) $fails++;
}
$has = fn(string $t, string $path = '') => rmt_place_title_price_text(['tickets' => $t], $path) !== '';

ok('krona amount', $has('Set menu 2,900 SEK, wine pairing 2,300 SEK'));
ok('euros written out', $has('Tasting menu 85 euros without drinks'));
ok('symbol first', $has('Adults €22.50 online'));
ok('code first', $has('Standard HKD 190, concessions HKD 100'));
ok('dollars written out', $has('Adult admission 18 dollars.'));
ok('free entry', $has('Free, donations welcome'));
ok('a la carte with no amount is not a price', !$has('A la carte, no set menu. Reservations possible.'));
ok('unpublished hotel rates are not a price', !$has('Nightly rates are not published as a fixed figure and vary by season'));
ok('an age is not a price', !$has('Ages 13 and over need a ticket, booked online.'));
ok('no editorial, no price', rmt_place_title_price_text(null) === '');
ok('a page in the snippet test keeps its title', !$has('Coffee 2.50 euros', '/p/le-rapido-paris'));

$p = ['name' => 'Ekstedt', 'dest_name' => 'Stockholm', 'hours_count' => 7];
ok('Ekstedt reads prices & hours', str_contains(rmt_place_page_title($p + ['editorial_prices' => rmt_place_title_price_text(['tickets' => 'Set menu 2,900 SEK'])]), 'prices & hours'));
$src = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('the place page uses the tickets field, not the missing prices column', !str_contains($src, "\$ed['prices']") && str_contains($src, 'rmt_place_title_price_text($ed'));

echo $fails ? "\nplace_title_price_test: $fails failed\n" : "\nplace_title_price_test: all passed\n";
exit($fails ? 1 : 0);
