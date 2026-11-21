<?php
/**
 * Place page <-> ticket note pairs and the tax note index (app/editorial_blog.php).
 *   php tests/seo_pairs_test.php
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/editorial_blog.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL  $m\n"; } }

$pairs = rmt_place_ticket_pairs();
ok(count($pairs) >= 15, 'pairs load');
$slugs = array_column(rmt_editorial_blog_items(), 'slug');
foreach ($pairs as $place => $note) {
    ok(in_array($note, $slugs, true), "$place: paired note $note exists");
    ok(rmt_place_for_ticket_note($note) === $place, "$note: maps back to $place");
}
ok(count(array_unique($pairs)) === count($pairs), 'no note is paired twice');
$v = rmt_ticket_note_for_place('vasa-museum-stockholm');
ok($v !== null && $v['slug'] === 'vasa-museum-tickets-2026' && str_contains($v['title'], 'Vasa'), 'vasa place gets its ticket note');
ok(rmt_ticket_note_for_place('no-such-place') === null, 'unpaired place gets nothing');
ok(rmt_place_for_ticket_note('tourist-taxes-2026') === null, 'unpaired note gets nothing');

$idx = array_column(rmt_tax_note_index(), 'slug');
ok(count($idx) >= 60, 'tax index lists every tax note (' . count($idx) . ')');
ok(!in_array('tourist-taxes-2026', $idx, true), 'index does not list the hub itself');
foreach (['zanzibar-infrastructure-tax-2026', 'copenhagen-tourist-tax-2026', 'san-francisco-hotel-tax-2026', 'us-hotel-taxes-2026'] as $s) {
    ok(in_array($s, $idx, true), "index has $s");
}
ok(!in_array('vasa-museum-tickets-2026', $idx, true), 'ticket notes are not in the tax index');

echo "seo_pairs_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
