<?php
/**
 * Switching RMT_SITEMAP_PLACES on only ever adds place URLs (docs/SEO_RECOVERY_20261011.md).
 *
 * Served by a real server over the dev database, with the switch off and on:
 *   - every URL in every sitemap with the switch off is still there with it on (nothing indexed today
 *     can drop out of the sitemaps);
 *   - everything added is a /p/ page on database/sitemap_places.txt;
 *   - robots.txt is byte for byte the same.
 *
 *   php tests/sitemap_preserve_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$db = $root . '/database/dev.sqlite';
if (!is_file($db) || filesize($db) < 1024) { echo "SKIP  no dev database\n"; exit(0); }
$ini = is_file($root . '/php.local.ini') ? ['-c', $root . '/php.local.ini'] : [];

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

/** @return array{0:resource,1:string} */
function serve(string $root, array $ini, int $port, array $env): array {
    $cmd = array_merge([PHP_BINARY], $ini, ['-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php']);
    $log = $root . '/tests/.render_server.log';
    $baseEnv = getenv();
    unset($baseEnv['DATABASE_URL'], $baseEnv['RMT_SITEMAP_PLACES']);
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root,
                      $env + $baseEnv + ['RMT_PAGE_CACHE' => '0', 'RMT_APP_URL' => 'http://127.0.0.1:' . $port]);
    $base = 'http://127.0.0.1:' . $port;
    for ($i = 0; $i < 40; $i++) {
        if (@file_get_contents($base . '/healthz', false, stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]])) !== false) break;
        usleep(250000);
    }
    return [$proc, $base];
}

function fetch(string $url): string {
    return (string) @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true,
        'header' => "User-Agent: Mozilla/5.0 (sitemap_preserve_test)\r\n"]]));
}

/** Every <loc> in the sitemap index and every child it lists, as paths. @return array<string,true> */
function all_urls(string $base): array {
    $out = [];
    $queue = [$base . '/sitemap.xml'];
    $seen = [];
    while ($queue) {
        $u = array_shift($queue);
        if (isset($seen[$u])) continue;
        $seen[$u] = true;
        $xml = fetch($u);
        preg_match_all('#<loc>([^<]+)</loc>#', $xml, $m);
        $isIndex = str_contains($xml, '<sitemapindex');
        foreach ($m[1] as $loc) {
            $path = (string) parse_url(html_entity_decode($loc), PHP_URL_PATH);
            if ($isIndex) $queue[] = $base . $path;
            else $out[$path] = true;
        }
    }
    return $out;
}

/* Sitemaps are built into the sitemap_cache table (at every deploy, by scripts/sitemap_build.php),
   so each side gets its own copy of the database, built the way the deploy builds it. */
$copies = [];
foreach (['off' => [], 'on' => ['RMT_SITEMAP_PLACES' => '1']] as $side => $env) {
    $copy = $root . "/tests/.sitemap_$side.sqlite";
    copy($db, $copy);
    $copies[$side] = $copy;
    $envStr = '';
    foreach ($env + ['RMT_SQLITE' => $copy] as $k => $v) putenv("$k=$v");
    exec(escapeshellarg(PHP_BINARY) . ' ' . implode(' ', array_map('escapeshellarg', $ini)) . ' '
         . escapeshellarg($root . '/scripts/sitemap_build.php') . ' --quiet', $o, $rc);
    putenv('RMT_SITEMAP_PLACES'); putenv('RMT_SQLITE');
    ok("sitemaps built ($side)", $rc === 0, implode(' ', $o));
}
[$off, $baseOff] = serve($root, $ini, 8131, ['RMT_SQLITE' => $copies['off']]);
[$on, $baseOn] = serve($root, $ini, 8132, ['RMT_SITEMAP_PLACES' => '1', 'RMT_SQLITE' => $copies['on']]);
register_shutdown_function(static function () use ($off, $on): void { @proc_terminate($off); @proc_terminate($on); });

$a = all_urls($baseOff);
$b = all_urls($baseOn);
ok('the sitemaps list URLs with the switch off', count($a) > 50, (string) count($a));
$lost = array_diff_key($a, $b);
ok('switching on removes nothing', !$lost, implode(', ', array_slice(array_keys($lost), 0, 10)));
$list = array_flip(array_filter(array_map('trim', file($root . '/database/sitemap_places.txt')),
                                static fn($l) => $l !== '' && $l[0] !== '#'));
$added = array_keys(array_diff_key($b, $a));
$bad = array_filter($added, static fn($p) => !str_starts_with($p, '/p/') || !isset($list[substr($p, 3)]));
ok('everything added is a listed place page', !$bad, implode(', ', array_slice($bad, 0, 10)));
echo "INFO  off " . count($a) . " URLs, on " . count($b) . " URLs, added " . count($added) . "\n";
ok('robots.txt is unchanged', fetch($baseOff . '/robots.txt') === fetch($baseOn . '/robots.txt'));

ok('switching on adds listed places', count($added) > 0, (string) count($added));
proc_terminate($off); proc_terminate($on);
usleep(300000);
foreach ($copies as $c) @unlink($c);
echo $fails ? "\nsitemap_preserve_test: $fails FAILED\n" : "\nsitemap_preserve_test: all passed\n";
exit($fails ? 1 : 0);
