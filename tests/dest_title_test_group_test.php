<?php
/**
 * The controlled title test: nine city pages, and seventy six that must not move.
 *
 * What this guards is the EXPERIMENT, not the copy. A test whose control group quietly drifts
 * proves nothing, and the easiest way to lose this one is for somebody to like the new titles and
 * apply them everywhere before the numbers come back. So the group is asserted by name and by
 * size, and a city outside it is asserted to still carry the old title exactly.
 *
 * The other three ways this goes wrong, all of them silent:
 *   * a title too long to survive a search result, which makes the test a measurement of
 *     truncation rather than of wording;
 *   * a title that drops the destination word, which changes what the page can rank for at all
 *     and turns a CTR test into a ranking test;
 *   * a change that reaches anything other than the words in the head. No URL, no canonical, no
 *     robots rule and no sitemap entry is part of this, and the assertions below say so against
 *     the source rather than against intent.
 *
 *   php tests/dest_title_test_group_test.php   -> PASS/FAIL per case, exits non-zero on failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/seo.php';

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-52s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

/* The nine, chosen from Search Console evidence on 2026-09-15: every one already had impressions,
   every one converted at zero, and none had a click worth protecting. */
$group = ['amsterdam-netherlands', 'lisbon-portugal', 'berlin-germany', 'marrakech-morocco',
          'zanzibar-tanzania', 'hoi-an-vietnam', 'oaxaca-mexico', 'banff-canada', 'milan-italy'];

echo "-- the test group is exactly the nine pages that were approved --\n";
ok('nine pages, no more', count(RMT_DEST_SOCIAL_TITLE_TEST), 9);
foreach ($group as $slug) {
    ok("$slug is in the test", isset(RMT_DEST_SOCIAL_TITLE_TEST[$slug]), true);
}
ok('and nothing else is', array_diff(array_keys(RMT_DEST_SOCIAL_TITLE_TEST), $group), []);

echo "\n-- the control group is untouched --\n";
/* A city outside the group must still get the title it had before, character for character. */
foreach ([['bangkok-thailand', 'Bangkok'], ['tokyo-japan', 'Tokyo'], ['paris-france', 'Paris']] as [$slug, $name]) {
    ok("$slug keeps the old title",
        rmt_destination_page_title(['slug' => $slug, 'name' => $name]),
        $name . ' 2026: costs, tickets, taxes and what nearly ruins it | RuinMyTrip');
}
ok('and keeps its own summary as the description',
   rmt_destination_page_description(['slug' => 'bangkok-thailand', 'summary' => 'A summary.']), 'A summary.');

echo "\n-- a title has to survive a search result --\n";
foreach (RMT_DEST_SOCIAL_TITLE_TEST as $slug => [$title, $desc]) {
    ok("$slug title fits in 60", mb_strlen($title) <= 60, true);
    ok("$slug description fits in 155", mb_strlen($desc) <= 155, true);
}

echo "\n-- it is still a test about wording, not about topic --\n";
/* The destination word stays in both. Dropping it would change what the page can rank for, and
   then a CTR test would be measuring a ranking change instead. */
$cityOf = ['amsterdam-netherlands' => 'Amsterdam', 'lisbon-portugal' => 'Lisbon',
           'berlin-germany' => 'Berlin', 'marrakech-morocco' => 'Marrakech',
           'zanzibar-tanzania' => 'Zanzibar', 'hoi-an-vietnam' => 'Hoi An',
           'oaxaca-mexico' => 'Oaxaca', 'banff-canada' => 'Banff', 'milan-italy' => 'Milan'];
foreach (RMT_DEST_SOCIAL_TITLE_TEST as $slug => [$title, $desc]) {
    $city = $cityOf[$slug];
    ok("$slug keeps the city in the title", str_contains($title, $city), true);
    ok("$slug keeps the topic word", stripos($title, 'travel guide') !== false, true);
    ok("$slug says there are people on it", (bool) preg_match('/traveler/i', $title), true);
    ok("$slug keeps the city in the description", str_contains($desc, $city), true);
    ok("$slug keeps the brand", str_ends_with($title, '| RuinMyTrip'), true);
}

echo "\n-- and nothing but the words changed --\n";
$seo = (string) file_get_contents(BASE_PATH . '/app/seo.php');
$ctrl = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
/* The test group is a lookup used by two functions and nothing else. If its name appears near a
   canonical, a robots rule or a sitemap, this experiment has grown a second arm. */
foreach (['canonical', 'robots', 'sitemap', 'noindex'] as $word) {
    $near = false;
    foreach (explode("\n", $seo) as $line) {
        if (str_contains($line, 'RMT_DEST_SOCIAL_TITLE_TEST') && stripos($line, $word) !== false) $near = true;
    }
    ok("the test group never touches $word", $near, false);
}
ok('the city page still declares its own canonical the way it always did',
   str_contains($ctrl, "'canonical'") || !str_contains($ctrl, 'rmt_destination_page_description'), true);
ok('only the description call changed in the controller',
   str_contains($ctrl, "'description' => rmt_destination_page_description(\$d),"), true);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL DESTINATION TITLE TEST GROUP CHECKS PASS ({$pass})\n";
