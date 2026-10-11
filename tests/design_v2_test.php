<?php
/**
 * The 2026-10 redesign behind RMT_DESIGN_V2 (app/design_v2.php).
 *
 * What must hold, on real pages served by a real server over the dev database:
 *   - switches off: the current site, byte for byte the same markers (no v2 header, no v2 sheet).
 *   - switches on: the new header and page bodies, and every SEO signal identical to the old page:
 *     title, meta description, canonical, robots, JSON-LD.
 *   - explore, city and profile lose no internal link to a city, place, profile or country page.
 *   - ?design=v2 previews it with the switches off and answers noindex; a cookie carries the
 *     preview to the next page; ?design=v1 forces the old site with the switches on.
 *   - RMT_CITY_TOP_PLACES=1 alone adds the top places block to the old city page and nothing else.
 *   - no PHP warning or notice anywhere.
 *
 *   php tests/design_v2_test.php
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
    unset($baseEnv['DATABASE_URL'], $baseEnv['RESEND_API_KEY'], $baseEnv['RMT_DESIGN_V2'], $baseEnv['RMT_CITY_TOP_PLACES']);
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root,
                      $env + $baseEnv + ['RMT_PAGE_CACHE' => '0']);
    $base = 'http://127.0.0.1:' . $port;
    for ($i = 0; $i < 40; $i++) {
        if (@file_get_contents($base . '/healthz', false, stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]])) !== false) break;
        usleep(250000);
    }
    return [$proc, $base];
}

/** @return array{0:string,1:list<string>} body and response headers */
function fetch(string $url, string $cookie = ''): array {
    $h = "User-Agent: Mozilla/5.0 (design_v2_test)\r\n" . ($cookie !== '' ? "Cookie: $cookie\r\n" : '');
    $body = (string) @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true, 'header' => $h]]));
    return [$body, $http_response_header ?? []];
}

function seo(string $html): array {
    $one = static fn(string $re) => preg_match($re, $html, $m) ? $m[1] : null;
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $ld);
    return [
        'title' => $one('#<title>(.*?)</title>#s'),
        'description' => $one('#<meta name="description" content="([^"]*)"#'),
        'canonical' => $one('#<link rel="canonical" href="([^"]*)"#'),
        'robots' => $one('#<meta name="robots" content="([^"]*)"#'),
        'jsonld' => $ld[1],
    ];
}

/** Links to pages a crawler should reach: cities, places, profiles, countries, category pages. */
function links(string $html): array {
    preg_match_all('~href="(?:https?://[^/"]+)?(/(?:p|d|u|in)/[^"?#]+)"~', $html, $m);
    $l = array_values(array_unique($m[1]));
    sort($l);
    return $l;
}

$pages = ['/', '/explore', '/d/lisbon-portugal', '/d/paris-france', '/u/ruinmytrip', '/u/maya_wanders', '/p/pasteis-de-belem-lisbon'];
$keepLinks = ['/explore', '/d/lisbon-portugal', '/d/paris-france', '/u/ruinmytrip', '/u/maya_wanders'];

[$off, $baseOff] = serve($root, $ini, 8126, []);
[$on, $baseOn] = serve($root, $ini, 8127, ['RMT_DESIGN_V2' => 'all']);
[$top, $baseTop] = serve($root, $ini, 8128, ['RMT_CITY_TOP_PLACES' => '1']);
register_shutdown_function(static function () use ($off, $on, $top): void { @proc_terminate($off); @proc_terminate($on); @proc_terminate($top); });

foreach ($pages as $path) {
    [$v1] = fetch($baseOff . $path);
    [$v2] = fetch($baseOn . $path);
    if ($v1 === '' || $v2 === '') { ok("$path: both servers answered", false); continue; }
    ok("$path: switches off serve the current site", !str_contains($v1, 'class="v2-header"') && !str_contains($v1, 'design-v2.css'));
    ok("$path: switches on serve the redesign", str_contains($v2, 'class="v2-header"') && str_contains($v2, 'design-v2.css'));
    $a = seo($v1); $b = seo($v2);
    foreach (['title', 'description', 'canonical', 'robots', 'jsonld'] as $k) {
        ok("$path: $k unchanged", $a[$k] === $b[$k], json_encode([$a[$k], $b[$k]]));
    }
    if (in_array($path, $keepLinks, true)) {
        $lost = array_diff(links($v1), links($v2));
        ok("$path: no city, place, profile or country link lost", !$lost, implode(', ', $lost));
    }
    foreach (['Warning:', 'Notice:', 'Deprecated:', 'Fatal error'] as $w) {
        ok("$path: no '$w' in the redesign", !str_contains($v2, $w));
    }
}

// Every link the current header and its More menu carry is still in the redesigned header and sheet.
[$h1] = fetch($baseOff . '/explore');
[$h2] = fetch($baseOn . '/explore');
$navOld = preg_match('#<header class="site-header">(.*?)</header>#s', $h1, $m) ? $m[1] : '';
$navNew = preg_match('#<header class="v2-header">(.*?)<main#s', $h2, $m) ? $m[1] : '';
preg_match_all('#href="([^"]+)"#', $navOld, $o); preg_match_all('#href="([^"]+)"#', $navNew, $n);
$lostNav = array_diff(array_unique($o[1]), array_unique($n[1]));
ok('the redesigned navigation keeps every link the current one has', !$lostNav, implode(', ', $lostNav));

// Home page v2: real places to rate, real cities, the embedded map.
[$home] = fetch($baseOn . '/');
ok('home v2 has the embedded travel map', str_contains($home, 'data-tm-embed'));
ok('home v2 offers places to rate with one tap', (bool) preg_match('#review/new\?place=\d+&amp;rating=5&amp;src=home#', $home));
ok('home v2 star links are nofollow', !preg_match('#<a href="[^"]*rating=\d[^"]*"(?![^>]*rel="nofollow")[^>]*>#', $home));

// Preview and cookie.
[$pv, $hdr] = fetch($baseOff . '/explore?design=v2');
ok('a preview serves the redesign with the switches off', str_contains($pv, 'class="v2-header"'));
ok('a preview is noindex', seo($pv)['robots'] === 'noindex,follow', (string) seo($pv)['robots']);
ok('a preview sets the preview cookie', (bool) preg_grep('#^Set-Cookie: rmt_design=v2#i', $hdr));
[$ck] = fetch($baseOff . '/d/lisbon-portugal', 'rmt_design=v2');
ok('the cookie carries the preview to the next page', str_contains($ck, 'class="v2-header"') && str_contains($ck, 'v2-city-hero'));
ok('a cookie preview keeps the page indexable', seo($ck)['robots'] === seo(fetch($baseOff . '/d/lisbon-portugal')[0])['robots']);
[$back] = fetch($baseOn . '/explore?design=v1');
ok('?design=v1 shows the current site with the switches on', !str_contains($back, 'class="v2-header"'));

// The top places block on its own.
[$c0] = fetch($baseOff . '/d/lisbon-portugal');
[$c1] = fetch($baseTop . '/d/lisbon-portugal');
ok('top places are off by default', !str_contains($c0, 'v2-top-places'));
ok('RMT_CITY_TOP_PLACES=1 adds top places to the current city page', str_contains($c1, 'v2-top-places') && !str_contains($c1, 'class="v2-header"'));
$added = array_diff(links($c1), links($c0));
ok('top places only adds place links', !array_filter($added, static fn($u) => !str_starts_with($u, '/p/')), implode(', ', $added));
ok('top places keep every SEO signal', seo($c0) === seo($c1));
preg_match_all('#class="v2-place-main" href="[^"]*/p/([^"]+)"#', $c1, $tp);
$noindex = [];
foreach (array_slice($tp[1], 0, 12) as $slug) {
    if (seo(fetch($baseOff . '/p/' . $slug)[0])['robots'] !== 'index, follow') $noindex[] = $slug;
}
ok('every top place is an indexable page', count($tp[1]) > 0 && !$noindex, implode(', ', $noindex));

proc_terminate($off); proc_terminate($on); proc_terminate($top);
echo $fails ? "\ndesign_v2_test: $fails FAILED\n" : "\ndesign_v2_test: all passed\n";
exit($fails ? 1 : 0);
