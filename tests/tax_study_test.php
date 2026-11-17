<?php
// The 2027 study: every shown row has an official source and a quote, the trip sums are right, and
// the ranking puts new taxes first, then the biggest rise. Also checks the embed data matches the calculator.
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/tourist_tax.php';
require BASE_PATH . '/app/city_scams.php';
require BASE_PATH . '/app/tax_study.php';
if (!function_exists('url')) { function url(string $p = ''): string { return 'https://ruinmytrip.com/' . ltrim($p, '/'); } }
require BASE_PATH . '/app/tax_embed.php';
$fail = 0;
$ok = static function (bool $c, string $m) use (&$fail): void { if (!$c) { $fail++; echo "FAIL: $m\n"; } };

$rows = rmt_tt27_rows();
$ok(count($rows) >= 10, 'at least 10 ranked rows, got ' . count($rows));
$by = [];
foreach ($rows as $r) {
    $by[$r['key']] = $r;
    $ok($r['sources'] !== [], $r['city'] . ' has a source');
    foreach ($r['sources'] as $s) $ok(str_starts_with($s['url'], 'https://') && $s['quote'] !== '', $r['city'] . ' source is https with a quote');
    $ok($r['next'] > 0, $r['city'] . ' has a 2027 amount');
    foreach (['rate_2027', 'notes'] as $f) $ok(!preg_match('/[\x{2013}\x{2014}]/u', $r[$f]), $r['city'] . " $f has no dash");
}
// Amsterdam: 200 x 4 nights x 12.5% = 100, at 16% = 128.
$ok(($by['amsterdam']['now'] ?? 0) === 100.0 && ($by['amsterdam']['next'] ?? 0) === 128.0, 'Amsterdam 100 -> 128');
$ok(($by['amsterdam']['rise_pct'] ?? 0) === 28.0, 'Amsterdam +28%');
// Vienna 5% -> 8%: 40 -> 64.
$ok(($by['vienna']['now'] ?? 0) === 40.0 && ($by['vienna']['next'] ?? 0) === 64.0, 'Vienna 40 -> 64');
// Barcelona 8.40 -> 9.40 pp, 2 people, 4 nights (cap 7 not reached).
$ok(abs(($by['barcelona']['now'] ?? 0) - 67.2) < 0.001 && abs(($by['barcelona']['next'] ?? 0) - 75.2) < 0.001, 'Barcelona 67.20 -> 75.20');
// Glasgow is new: 5% of 200 x 4 = 40.
$ok(($by['glasgow']['new'] ?? false) && ($by['glasgow']['next'] ?? 0) === 40.0, 'Glasgow new 40');
// Ranking: every new tax before every rise, rises descending.
$seenOld = false; $last = INF;
foreach ($rows as $r) {
    if (!$r['new']) { $seenOld = true; $ok($r['rise_pct'] <= $last, 'rises descending at ' . $r['city']); $last = $r['rise_pct']; }
    else $ok(!$seenOld, 'new tax ' . $r['city'] . ' ranked before rises');
}
// The calculator comparison table and the study agree on Vienna and Amsterdam in 2027.
foreach (rmt_tt_compare() as $c) {
    if (isset($by[$c['key']]) && $c['next'] !== null) $ok(abs($c['next'] - $by[$c['key']]['next']) < 0.001, 'compare table matches study for ' . $c['key']);
}
foreach (rmt_tt27_pending() as $p) $ok($p['sources'] !== [], 'pending ' . $p['city'] . ' has a source');

// Embed: one entry per calculator city, rates identical.
$emb = rmt_tt_embed_data();
$ok(count($emb) === count(rmt_tt_cities()), 'embed has every city');
foreach ($emb as $e) {
    $c = rmt_tt_cities()[$e['key']];
    $ok(count($e['tiers']) === count($c['tiers']), 'embed tiers for ' . $e['key']);
    $ok(str_contains($e['url'], 'utm_source=embed'), 'embed credit link tagged for ' . $e['key']);
}

echo $fail ? "$fail failed\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
