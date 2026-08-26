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
preg_match_all('#<loc>([^<]+)</loc>#', $xml, $m);
$urls = $m[1] ?? [];
echo count($urls) . " URLs in sitemap\n";

$ok = rmt_indexnow_submit($urls);
echo $ok ? "IndexNow: submitted\n" : "IndexNow: submit failed (non-fatal)\n";

$ping = 'https://www.google.com/ping?sitemap=' . rawurlencode($base . '/sitemap.xml');
$g = @file_get_contents($ping);
echo $g !== false ? "Google sitemap ping: sent\n" : "Google sitemap ping: failed (non-fatal)\n";
