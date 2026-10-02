<?php
/**
 * 2026-10-02: one tap rating, optional headline, daily KPI rows.
 *
 *   php tests/review_rate_kpi_test.php   (runs against database/dev.sqlite, read only)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/database/dev.sqlite')) { echo "SKIP  no dev database\n"; exit(0); }
require $root . '/app/bootstrap.php';
require BASE_PATH . '/app/controllers.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

ok(rmt_review_headline_from('Packed by eleven. Go early.') === 'Packed by eleven.', 'headline is the first sentence');
$long = str_repeat('word ', 40);
$h = rmt_review_headline_from($long);
ok(mb_strlen($h) <= 93 && str_ends_with($h, '...') && !str_contains($h, 'wor...'), 'a long first sentence is cut at a word');
$dest = q_one('SELECT id FROM destinations ORDER BY id LIMIT 1');
$v = rmt_review_validate(['destination_id' => (string) $dest['id'], 'subject_type' => RMT_REVIEW_CATEGORIES[0],
    'subject_name' => 'Somewhere', 'title' => '', 'rating' => '4',
    'body' => 'Went on a rainy Sunday and it was packed by eleven. Go early.'], false);
ok($v['ok'] && $v['data']['title'] === 'Went on a rainy Sunday and it was packed by eleven.', 'an empty headline publishes with the writer\'s first sentence');
$v = rmt_review_validate(['destination_id' => (string) $dest['id'], 'subject_type' => RMT_REVIEW_CATEGORIES[0],
    'subject_name' => 'Somewhere', 'title' => '', 'rating' => '4', 'body' => 'Too short.'], false);
ok(!$v['ok'], 'a too short review is still refused');

$days = rmt_kpi_days(2, '2026-10-02 06:00:00');
ok($days[1]['day'] === '2026-10-01' && $days[1]['from'] === '2026-10-01 07:00:00' && $days[1]['to'] === '2026-10-02 07:00:00',
   'a Pacific day is its UTC bounds (PDT)');
$days = rmt_kpi_days(1, '2026-12-01 12:00:00');
ok($days[0]['from'] === '2026-12-01 08:00:00', 'and PST in winter');
$k = rmt_kpi_daily(3);
ok(count($k['days']) === 3 && count($k['metrics']) === 10, 'three days, ten metrics');

$src = file_get_contents(BASE_PATH . '/views/_rate_place.php');
ok(str_contains($src, 'rel="nofollow"') && str_contains($src, 'src=place_rate&rating='), 'stars are nofollow links into the form');
ok(in_array('review_held_for_join', RMT_CONTRIB_EVENTS, true) && in_array('buddy_search', RMT_CONTRIB_EVENTS, true)
   && in_array('place_rate', RMT_CONTRIB_SOURCES, true), 'the new events and source are allowed');

echo "\nreview_rate_kpi_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
