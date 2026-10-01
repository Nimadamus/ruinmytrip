<?php
/**
 * Q7 (approved 2026-10-01): exactly fourteen country hubs moved to their travel buddy page, every
 * link builder points straight at the new page (no redirect hops), and the other /in pages stay
 * where they are. Thin tags (under three items) stay off the sitemap.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/buddy_landing_pages.php';

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-56s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

ok('exactly fourteen moved', count(RMT_COUNTRY_MOVED), 14);
ok('no duplicates', count(array_unique(RMT_COUNTRY_MOVED)), 14);
foreach (RMT_COUNTRY_MOVED as $slug) {
    ok("$slug has a travel buddy page", isset(RMT_BUDDY_LANDING[$slug]) && RMT_BUDDY_LANDING[$slug]['kind'] === 'country', true);
}
ok('Greece links to its buddy page', rmt_country_path('Greece'), 'travel-buddies/greece');
ok('Vietnam links to its buddy page', rmt_country_path('Vietnam'), 'travel-buddies/vietnam');
ok('an unmoved country keeps /in', rmt_country_path('Germany'), 'in/germany');
ok('United States keeps /in', rmt_country_path('United States'), 'in/united-states');

$src = file_get_contents(BASE_PATH . '/app/controllers.php') . file_get_contents(BASE_PATH . '/views/destination.php') . file_get_contents(BASE_PATH . '/views/explore.php');
ok('no link builder hard codes in/ plus a country', preg_match("#url\('in/'\.rmt_country_slug#", $src), 0);

$tags = file_get_contents(BASE_PATH . '/app/tags.php');
ok('tag threshold is three', str_contains($tags, 'const RMT_TAG_SITEMAP_MIN = 3;'), true);
foreach (['app/sitemap.php', 'app/seo.php'] as $f) {
    $s = file_get_contents(BASE_PATH . '/' . $f);
    ok("$f submits only qualifying tags", str_contains($s, 'rmt_sitemap_tags()') && !str_contains($s, 'rmt_top_tags(100)'), true);
}

printf("\n%s: %d passed, %d failed\n", basename(__FILE__, '.php'), $pass, $fail);
exit($fail ? 1 : 0);
