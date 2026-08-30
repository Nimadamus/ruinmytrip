<?php
/**
 * The pilot's live state, in one table, for reviewing against docs/SEO_PILOT_BASELINE.md.
 *
 * Deliberately small: it reads what the site already knows and prints it. Search Console numbers
 * are pasted in beside this by whoever is looking -- building an API integration to fetch six rows
 * would be more machinery than the question deserves.
 *
 *   php scripts/seo_pilot_status.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$rows = array_values(array_filter(rmt_index_categories(), static fn(array $c) => $c['verdict']['ok']));

printf("%-46s %-14s %5s  %-10s %s\n", 'URL', 'DESTINATION', 'N', 'VERDICT', 'IN SITEMAP');
foreach ($rows as $c) {
    $path = '/d/' . $c['dest_slug'] . '/' . rmt_category_slug((string) $c['type']);
    $inMap = q_one("SELECT 1 x FROM sitemap_cache WHERE group_key = 'categories' AND xml LIKE ?",
                   ['%' . $path . '<%']) ? 'yes' : 'NO';
    printf("%-46s %-14s %5d  %-10s %s\n", $path, $c['dest_name'], $c['place_count'],
           $c['verdict']['reason'], $inMap);
}

// The near misses matter as much as the passes: this is the list a second batch would come from.
$near = array_values(array_filter(rmt_index_categories(),
    static fn(array $c) => !$c['verdict']['ok'] && $c['place_count'] >= RMT_IDX_CAT_MIN_PLACES - 3));
if ($near) {
    echo "\nclosest to qualifying (threshold " . RMT_IDX_CAT_MIN_PLACES . ", do not lower it):\n";
    foreach (array_slice($near, 0, 12) as $c) {
        printf("  %-30s %-12s %d, needs %d more\n", $c['dest_name'], $c['type'],
               $c['place_count'], RMT_IDX_CAT_MIN_PLACES - $c['place_count']);
    }
}
