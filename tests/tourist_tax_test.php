<?php
// The calculator must reproduce the worked examples in the notes it cites, and every city must point at a real note.
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/tourist_tax.php';
$fail = 0;
$C = rmt_tt_cities();
// city, tier, nights, people, rooms, price, expected total
$cases = [
    ['paris', '4', 4, 2, 1, 0, 67.60], ['paris', '3', 4, 2, 1, 0, 44.24], ['paris', '5', 4, 2, 1, 0, 93.60],
    ['rome', '4', 4, 2, 1, 0, 60.0], ['rome', '3', 14, 2, 1, 0, 120.0],
    ['milan', '4', 4, 2, 1, 0, 80.0], ['milan', 'rental', 4, 2, 1, 0, 76.0], ['milan', 'hostel', 4, 2, 1, 0, 24.0],
    ['naples', '4', 5, 2, 1, 0, 60.0], ['naples', '3', 5, 2, 1, 0, 55.0], ['naples', '4', 30, 1, 1, 0, 84.0],
    ['nice', '4', 6, 2, 1, 0, 41.76], ['nice', '3', 6, 2, 1, 0, 27.36],
    ['new-york', '', 3, 1, 1, 350, 72.19 ],
    ['berlin', '', 4, 1, 1, 130, 39.0], ['budapest', '', 3, 1, 1, 60000, 7200.0],
    ['buenos-aires', '', 6, 2, 1, 0, 18.0], ['tokyo', '', 5, 2, 1, 22000, 2000.0], ['tokyo', '', 5, 2, 1, 9000, 0.0],
    ['osaka', '', 1, 1, 1, 4999, 0.0], ['osaka', '', 1, 1, 1, 15000, 400.0], ['kyoto', '', 1, 1, 1, 100000, 10000.0],
    ['kyoto', '', 1, 1, 1, 5999, 200.0], ['lisbon', '', 10, 2, 1, 0, 56.0], ['porto', '', 9, 1, 1, 0, 21.0],
    ['edinburgh', '', 7, 1, 1, 100, 25.0], ['reykjavik', '', 2, 3, 2, 0, 3200.0],
    // Barcelona taxes at most 7 nights per person (ATC, base imposable, checked 2026-11-07).
    ['barcelona', '4', 3, 2, 1, 0, 50.40], ['barcelona', '4', 10, 2, 1, 0, 117.60], ['barcelona', '5', 8, 1, 1, 0, 84.0],
];
foreach ($cases as [$k, $t, $n, $p, $r, $price, $want]) {
    $got = rmt_tt_estimate($C[$k], $C[$k]['tiers'][$t], $n, $p, $r, (float) $price)['total'];
    if (abs($got - $want) > 0.005) { echo "FAIL $k $t: $got want $want\n"; $fail++; }
}
$posts = [];
foreach (json_decode(file_get_contents(BASE_PATH . '/database/editorial/blog.json'), true)['posts'] as $p) $posts[$p['slug']] = $p;
foreach ($C as $k => $c) {
    if (!isset($posts[$c['note']])) { echo "FAIL $k note missing\n"; $fail++; continue; }
    if (($posts[$c['note']]['destination_slug'] ?? '') !== $c['dest']) { echo "FAIL $k dest\n"; $fail++; }
}
$copy = file_get_contents(BASE_PATH . '/app/tourist_tax.php') . file_get_contents(BASE_PATH . '/views/tourist_tax.php');
if (preg_match('/[\x{2013}\x{2014}]/u', $copy)) { echo "FAIL dash in copy\n"; $fail++; }
if (rmt_tt_money(1234.5, 'EUR') !== '€1,234.50' || rmt_tt_money(7200, 'HUF') !== '7,200 HUF') { echo "FAIL money\n"; $fail++; }
if (rmt_tt_key_for_dest('paris-france') !== 'paris' || rmt_tt_key_for_dest('nowhere') !== null) { echo "FAIL dest key\n"; $fail++; }
$nb = array_column(rmt_tt_neighbours('vienna', 2), 'name');
if ($nb !== ['Amsterdam', 'Barcelona']) { echo "FAIL neighbours wrap\n"; $fail++; }
// Same trip table (2026-11-09): euro cities only, highest first, 2027 only where a change is stated.
$cmp = rmt_tt_compare();
$byKey = array_column($cmp, null, 'key');
if (count($cmp) !== count(array_filter($C, static fn($c) => $c['cur'] === 'EUR'))) { echo "FAIL compare: not every euro city
"; $fail++; }
if (abs($byKey['amsterdam']['now'] - 100.0) > 0.001 || abs((float) $byKey['amsterdam']['next'] - 128.0) > 0.001) { echo "FAIL compare amsterdam
"; $fail++; }
if (abs($byKey['barcelona']['now'] - 67.2) > 0.001 || abs((float) $byKey['barcelona']['next'] - 75.2) > 0.001) { echo "FAIL compare barcelona
"; $fail++; }
if ($byKey['paris']['next'] !== null || $byKey['paris']['status'] !== 'No 2027 change in our notes') { echo "FAIL compare no guess
"; $fail++; }
$nows = array_column($cmp, 'now'); $sorted = $nows; rsort($sorted);
if ($nows !== $sorted) { echo "FAIL compare order
"; $fail++; }
echo $fail ? "FAILED\n" : "ok\n";
exit($fail ? 1 : 0);
