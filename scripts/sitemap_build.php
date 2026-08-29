<?php
/**
 * Regenerate every child sitemap and store the XML.
 *
 * Runs at deploy, after migrations and enrichment, because both change what is indexable. Cheap
 * enough to run unconditionally: one query per entity group, then one INSERT per part.
 *
 *   php scripts/sitemap_build.php [--quiet]
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

$quiet = in_array('--quiet', $argv, true);
$t0 = microtime(true);
$counts = rmt_sitemap_generate();
$ms = (int) round((microtime(true) - $t0) * 1000);

if (!$quiet) {
    foreach ($counts as $group => $n) printf("  %-14s %d\n", $group, $n);
}
printf("sitemap: %d urls across %d groups in %dms\n", array_sum($counts), count(array_filter($counts)), $ms);
