<?php
declare(strict_types=1);

/**
 * Submit the live sitemap to IndexNow (Bing etc.) and ping Google's sitemap endpoint.
 *
 *   php scripts/indexnow_submit.php
 *   php scripts/indexnow_submit.php https://ruinmytrip.com
 */
define('RMT_NO_AUTOSEED', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$base = rtrim((string) ($argv[1] ?? cfg('app_url')), '/');
$xml = @file_get_contents($base . '/sitemap.xml');
if ($xml === false) {
    fwrite(STDERR, "could not fetch {$base}/sitemap.xml\n");
    exit(1);
}
// /sitemap.xml is a sitemap INDEX now, so its <loc> entries are child sitemaps rather than
// pages. Reading them straight would submit seven XML files to IndexNow as though they were
// content: the submitter would keep working and stop submitting anything real.
$urls = [];
if (str_contains($xml, '<sitemapindex')) {
    preg_match_all('#<sitemap>.*?<loc>([^<]+)</loc>.*?</sitemap>#s', $xml, $kids);
    foreach ($kids[1] ?? [] as $child) {
        $childXml = @file_get_contents(trim($child));
        if ($childXml === false) { echo '  could not fetch ' . $child . "\n"; continue; }
        preg_match_all('#<url>.*?<loc>([^<]+)</loc>#s', $childXml, $m);
        $urls = array_merge($urls, $m[1] ?? []);
    }
} else {
    preg_match_all('#<loc>([^<]+)</loc>#', $xml, $m);
    $urls = $m[1] ?? [];
}
$urls = array_values(array_unique(array_map('trim', $urls)));
echo count($urls) . ' URLs in sitemap' . "\n";

$ok = rmt_indexnow_submit($urls);
echo $ok ? "IndexNow: submitted\n" : "IndexNow: submit failed (non-fatal)\n";

$ping = 'https://www.google.com/ping?sitemap=' . rawurlencode($base . '/sitemap.xml');
$g = @file_get_contents($ping);
echo $g !== false ? "Google sitemap ping: sent\n" : "Google sitemap ping: failed (non-fatal)\n";
