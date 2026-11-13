<?php
/**
 * Scams and traps on city pages (app/city_scams.php): nothing shows without its evidence, and the
 * data file itself is clean, so a dropped item is a red test rather than a silent gap.
 *   php tests/city_scams_test.php
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/city_scams.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL  $m\n"; } }

$src = ['publisher' => 'UK FCDO', 'url' => 'https://www.gov.uk/foreign-travel-advice/italy/safety-and-security', 'quote' => 'Be alert to pickpockets'];
$good = ['name' => 'Pickpockets on buses', 'what_happens' => 'Thieves work crowded routes.', 'avoid' => 'Keep bags closed.', 'sources' => [$src]];

ok(rmt_city_scam_item($good) !== null, 'a sourced item is shown');
ok(rmt_city_scam_item(['sources' => []] + $good) === null, 'an item with no source is dropped');
ok(rmt_city_scam_item(['sources' => [['url' => 'http://example.gov/x'] + $src]] + $good) === null, 'a non-https source does not count');
ok(rmt_city_scam_item(['sources' => [['quote' => ''] + $src]] + $good) === null, 'a source with no supporting quote does not count');
ok(rmt_city_scam_item(['sources' => [['publisher' => ''] + $src]] + $good) === null, 'a source with no publisher does not count');
ok(rmt_city_scam_item(['name' => ''] + $good) === null, 'an unnamed item is dropped');
ok(rmt_city_scam_item(['what_happens' => ''] + $good) === null, 'an item that says nothing is dropped');
ok(rmt_city_scam_url_ok('https://travel.state.gov/content/x.html') && !rmt_city_scam_url_ok('javascript:alert(1)'), 'only https links are printed');

// The real file: valid JSON, every item survives validation, every city key is a real slug shape,
// and no source is a blog, forum or review site.
if (is_file(RMT_CITY_SCAMS_FILE)) {
    $raw = json_decode((string) file_get_contents(RMT_CITY_SCAMS_FILE), true);
    ok(is_array($raw), 'city_scams.json parses');
    $banned = '#(reddit|tripadvisor|blogspot|wordpress\.com|medium\.com|quora|facebook|tiktok|youtube|instagram)\.#i';
    foreach ((array) $raw as $slug => $city) {
        ok((bool) preg_match('/^[a-z0-9-]+$/', (string) $slug), "$slug: slug shape");
        $items = (array) ($city['items'] ?? []);
        ok(count($items) >= 1 && count($items) <= 8, "$slug: between 1 and 8 items, no padding");
        foreach ($items as $i => $it) {
            ok(rmt_city_scam_item($it) !== null, "$slug item $i: survives validation (sourced, quoted, https)");
            foreach ((array) ($it['sources'] ?? []) as $s) {
                ok(!preg_match($banned, (string) ($s['url'] ?? '')), "$slug item $i: source is not a forum, blog or social site");
                ok(mb_strlen((string) ($s['quote'] ?? '')) <= 220, "$slug item $i: quote is a short excerpt, not a copied page");
            }
        }
    }
    ok(count(rmt_city_scams_all()) === count((array) $raw), 'every city in the file renders');
}

// Scam types (2026-11-09): a typo in the file would be dropped silently, so check the raw tags.
foreach ((array) $raw as $slug => $city) foreach ((array) ($city['items'] ?? []) as $n => $it) {
    $tt = (array) ($it['types'] ?? []);
    ok(!array_diff($tt, array_keys(RMT_SCAM_TYPES)), "$slug#$n: every type is a known type");
    ok(count($tt) === count(array_unique($tt)), "$slug#$n: no repeated type");
}
$tr = rmt_scam_type_counts();
ok((bool) $tr, 'type table is not empty');
$prev = PHP_INT_MAX; $sorted = true;
foreach ($tr as $r) { if (count($r['cities']) > $prev) $sorted = false; $prev = count($r['cities']); ok(count($r['cities']) === count(array_unique($r['cities'])), $r['type'] . ': a city counts once'); }
ok($sorted, 'type table is most cities first');

echo "city_scams_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
