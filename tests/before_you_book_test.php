<?php
/**
 * "Check before you book" lists (app/before_you_book.php): nothing shows without its evidence, the
 * data file is clean, and the published words follow the house style (no dashes).
 *   php tests/before_you_book_test.php
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/city_scams.php';
require BASE_PATH . '/app/before_you_book.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL  $m\n"; } }

$src = ['publisher' => 'City of Amsterdam', 'url' => 'https://www.amsterdam.nl/en/', 'quote' => 'You need a registration number'];
$good = ['title' => 'Check the registration number', 'detail' => 'Holiday rentals need one.', 'check' => 'Look for it on the listing.', 'kind' => 'booking', 'sources' => [$src]];
ok(rmt_byb_item($good) !== null, 'a sourced item is shown');
ok(rmt_byb_item(['sources' => []] + $good) === null, 'no source, not shown');
ok(rmt_byb_item(['sources' => [['url' => 'http://x.nl/a'] + $src]] + $good) === null, 'non https source does not count');
ok(rmt_byb_item(['sources' => [['quote' => ''] + $src]] + $good) === null, 'unquoted source does not count');
ok(rmt_byb_item(['title' => ''] + $good) === null, 'untitled item dropped');
ok(rmt_byb_item(['kind' => 'nonsense'] + $good)['kind'] === 'booking', 'unknown kind falls back');

$raw = json_decode((string) file_get_contents(RMT_BYB_FILE), true);
ok(is_array($raw), 'before_you_book.json parses');
$banned = '#(reddit|tripadvisor|blogspot|wordpress\.com|medium\.com|quora|facebook|tiktok|youtube|instagram|wikipedia)\.#i';
$scams = json_decode((string) file_get_contents(RMT_CITY_SCAMS_FILE), true);
foreach ((array) $raw as $slug => $city) {
    ok(isset($scams[$slug]), "$slug: has a warnings page to live on");
    $items = (array) ($city['checklist'] ?? []);
    ok(count($items) >= 5 && count($items) <= 15, "$slug: 5 to 15 items, no padding");
    foreach ($items as $i => $it) {
        ok(rmt_byb_item($it) !== null, "$slug #$i: survives validation");
        ok(isset(RMT_BYB_KINDS[(string) ($it['kind'] ?? '')]), "$slug #$i: known kind");
        foreach (['title', 'detail', 'check'] as $f) {
            ok(!preg_match('/[\x{2013}\x{2014}]| - /u', (string) ($it[$f] ?? '')), "$slug #$i $f: no dashes in published words");
        }
        foreach ((array) ($it['sources'] ?? []) as $s) {
            ok(!preg_match($banned, (string) ($s['url'] ?? '')), "$slug #$i: source is official, not a forum or blog");
            ok(mb_strlen((string) ($s['quote'] ?? '')) <= 260, "$slug #$i: quote is a short excerpt");
        }
    }
}
ok(count(rmt_byb_all()) === count((array) $raw), 'every city in the file renders');

echo "before_you_book: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
