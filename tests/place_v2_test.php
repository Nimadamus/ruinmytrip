<?php
/**
 * Place page v2 behind RMT_PLACE_V2 (app/place_v2.php, views/place_show_v2.php).
 *
 * What must hold, on real pages served by a real server over the dev database:
 *   - with the flag off, nothing changes: the old template is served.
 *   - with the flag on, the new template is served and every SEO signal is byte for byte the
 *     one the old template sent: title, meta description, canonical, robots, JSON-LD, H1.
 *   - the new template keeps every internal link the old one had to places, the city and its lists.
 *   - ?design=v2 previews it and answers noindex, so a preview URL can never compete in search.
 *   - no PHP warning or notice on any of the sampled places, with and without reviews or hours.
 *
 *   php tests/place_v2_test.php
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
    unset($baseEnv['DATABASE_URL'], $baseEnv['RESEND_API_KEY']);
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root,
                      $env + $baseEnv + ['RMT_PAGE_CACHE' => '0']);
    $base = 'http://127.0.0.1:' . $port;
    for ($i = 0; $i < 40; $i++) {
        if (@file_get_contents($base . '/healthz', false, stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]])) !== false) break;
        usleep(250000);
    }
    return [$proc, $base];
}

function fetch(string $url): string {
    return (string) @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true,
        'header' => "User-Agent: Mozilla/5.0 (place_v2_test)\r\n"]]));
}

/** The signals a search engine reads, pulled out of a page. */
function seo(string $html): array {
    $one = static fn(string $re) => preg_match($re, $html, $m) ? $m[1] : null;
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $ld);
    return [
        'title' => $one('#<title>(.*?)</title>#s'),
        'description' => $one('#<meta name="description" content="([^"]*)"#'),
        'canonical' => $one('#<link rel="canonical" href="([^"]*)"#'),
        'robots' => $one('#<meta name="robots" content="([^"]*)"#'),
        'jsonld' => $ld[1],
        'h1' => trim(strip_tags((string) $one('#<h1[^>]*>(.*?)</h1>#s'))),
    ];
}

/** Internal links that matter for crawling: places, the city, its place list. */
function links(string $html): array {
    preg_match_all('~href="https?://[^/"]+(/(?:p|d)/[^"?#]+)"~', $html, $m);
    $l = array_values(array_unique(array_filter($m[1], static fn($u) => !str_ends_with($u, '/correct'))));
    sort($l);
    return $l;
}

$pdo = new PDO('sqlite:' . $db);
$slugs = array_column($pdo->query("SELECT slug FROM places WHERE status = 'active' ORDER BY id LIMIT 25")->fetchAll(PDO::FETCH_ASSOC), 'slug');
$slugs = array_values(array_unique(array_merge($slugs, ['a-brasileira-lisbon', 'rijksmuseum-amsterdam'])));
ok('places to sample', count($slugs) >= 10, (string) count($slugs));

[$off, $baseOff] = serve($root, $ini, 8124, []);
[$on, $baseOn] = serve($root, $ini, 8125, ['RMT_PLACE_V2' => '1']);
// A failure below must never leave two servers running and the gate waiting on them.
register_shutdown_function(static function () use ($off, $on): void { @proc_terminate($off); @proc_terminate($on); });

foreach ($slugs as $slug) {
    $v1 = fetch("$baseOff/p/$slug");
    $v2 = fetch("$baseOn/p/$slug");
    if ($v1 === '' || $v2 === '') { ok("$slug: both servers answered", false); continue; }
    ok("$slug: flag off serves the old page", !str_contains($v1, 'class="pv2"'));
    ok("$slug: flag on serves v2", str_contains($v2, 'class="pv2"'));
    $a = seo($v1); $b = seo($v2);
    foreach (['title', 'description', 'canonical', 'robots', 'jsonld', 'h1'] as $k) {
        ok("$slug: $k unchanged", $a[$k] === $b[$k], json_encode([$a[$k], $b[$k]]));
    }
    $lost = array_diff(links($v1), links($v2));
    ok("$slug: no internal link lost", !$lost, implode(', ', $lost));
    foreach (['Warning:', 'Notice:', 'Deprecated:', 'Fatal error'] as $w) {
        ok("$slug: no '$w' in v2", !str_contains($v2, $w));
    }
}

$pv = fetch("$baseOff/p/a-brasileira-lisbon?design=v2");
ok('a preview serves v2 with the flag off', str_contains($pv, 'class="pv2"'));
ok('a preview is noindex', seo($pv)['robots'] === 'noindex,follow', (string) seo($pv)['robots']);
ok('a preview keeps the real canonical', seo($pv)['canonical'] === seo(fetch("$baseOff/p/a-brasileira-lisbon"))['canonical']);
$back = fetch("$baseOn/p/a-brasileira-lisbon?design=v1");
ok('?design=v1 shows the old page with the flag on', !str_contains($back, 'class="pv2"') && seo($back)['robots'] === 'noindex,follow');

proc_terminate($off); proc_terminate($on);
echo $fails ? "\nplace_v2_test: $fails FAILED\n" : "\nplace_v2_test: all passed\n";
exit($fails ? 1 : 0);
