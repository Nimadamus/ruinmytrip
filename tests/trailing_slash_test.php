<?php
/**
 * Trailing-slash canonicalisation (public/index.php): one 301 from /x/ to /x, never a loop, never
 * off-site, never on a POST, query and percent-encoding kept, and every URL the sitemap lists is
 * untouched (200, no redirect) while its slash twin lands on it in exactly one hop.
 *
 *   php tests/trailing_slash_test.php                       starts its own server on the dev DB
 *   RMT_TEST_BASE=http://127.0.0.1:8097 php tests/trailing_slash_test.php   any running server,
 *       e.g. the production Docker image (Apache + .htaccess), which is the one that matters
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$base = rtrim((string) getenv('RMT_TEST_BASE'), '/');
$proc = null;
if ($base === '') {
    $db = $root . '/database/dev.sqlite';
    if (!is_file($db) || filesize($db) < 1024) { echo "SKIP  no dev database\n"; exit(0); }
    $port = 8124;
    $nul = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'; // /dev/null does not exist on Windows
    $proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $nul, 'a'], 2 => ['file', $nul, 'a']], $pipes, $root);
    $base = 'http://127.0.0.1:' . $port;
    for ($i = 0; $i < 40 && @file_get_contents($base . '/healthz') === false; $i++) usleep(250000);
}

$pass = 0; $fail = 0;
function ok(bool $c, string $m, string $d = ''): void {
    global $pass, $fail;
    if ($c) { $pass++; } else { $fail++; echo "FAIL  $m" . ($d !== '' ? "  -- $d" : '') . "\n"; }
}
/** One request, no redirect following. @return array{0:int,1:string,2:string} status, Location, body */
function req(string $url, string $method = 'GET', ?string $body = null): array {
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30, CURLOPT_PATH_AS_IS => true,
        CURLOPT_NOBODY => $method === 'HEAD']);
    if ($body !== null) curl_setopt($c, CURLOPT_POSTFIELDS, $body);
    $raw = (string) curl_exec($c);
    $code = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    $hs = (int) curl_getinfo($c, CURLINFO_HEADER_SIZE);
    curl_close($c);
    $loc = preg_match('/^Location:\s*(\S+)/mi', substr($raw, 0, $hs), $m) ? $m[1] : '';
    return [$code, $loc, substr($raw, $hs)];
}
/** Follow up to 6 hops. @return array{0:int,1:list<string>} final status, the chain */
function chain(string $base, string $path): array {
    $seen = []; $url = $base . $path;
    for ($i = 0; $i < 6; $i++) {
        [$code, $loc] = req($url);
        $seen[] = $code . ' ' . substr($url, strlen($base));
        if ($code < 300 || $code > 399 || $loc === '') return [$code, $seen];
        $url = str_starts_with($loc, 'http') ? $loc : $base . $loc;
    }
    return [-1, $seen];
}
$path = static fn(string $loc): string => (string) preg_replace('#^https?://[^/]+#', '', $loc);

// The cases a person or a crawler actually sends.
[$c, $l] = req($base . '/');
ok($c === 200, 'the home page is not redirected', "$c $l");
foreach (['/explore/' => '/explore', '/about/' => '/about', '/d/paris-france/' => '/d/paris-france',
          '/blog/' => '/blog', '/d/paris-france//' => '/d/paris-france', '/buddies/' => '/buddies'] as $from => $to) {
    [$c, $l] = req($base . $from);
    ok($c === 301 && $path($l) === $to, "$from -> 301 $to", "$c $l");
}
[$c, $l] = req($base . '/d/paris-france/?ask=1&x=a%20b');
ok($c === 301 && $path($l) === '/d/paris-france?ask=1&x=a%20b', 'query string kept exactly', $l);
[$c, $l] = req($base . '/p/%E6%B8%85%E6%B0%B4%E5%AF%BA/');
ok($c === 301 && $path($l) === '/p/%E6%B8%85%E6%B0%B4%E5%AF%BA', 'percent-encoded path kept as sent', $l);
[$c, $l] = req($base . '/d/paris-france/', 'HEAD');
ok($c === 301 && $path($l) === '/d/paris-france', 'HEAD redirects too', "$c $l");

// Never off-site.
foreach (['//evil.com/', '///evil.com/x/', '/\\evil.com/', '/%5Cevil.com/', '/%2F%2Fevil.com/'] as $p) {
    [$c, $l] = req($base . $p);
    $lp = $path($l);
    ok($l === '' || (str_starts_with($lp, '/') && !str_starts_with($lp, '//') && !str_contains($lp, '\\') && !preg_match('#^https?://(?!127\.0\.0\.1|localhost|ruinmytrip\.com)#', $l)),
       "no off-site Location for $p", "$c $l");
}

// POST is left alone: a form posting to a slash URL keeps its body (the router answers it).
[$c, $l] = req($base . '/login/', 'POST', 'email=a%40b.c&password=x');
ok($c !== 301 && $c !== 308, 'POST to a slash URL is not redirected', "$c $l");

// Real directories and files: Apache's own handling, no loop with ours.
[$c] = req($base . '/assets/css/app.css');
ok($c === 200, 'a static file is served', (string) $c);
foreach (['/assets/', '/assets', '/uploads/'] as $p) {
    [$fc, $hops] = chain($base, $p);
    ok($fc !== -1 && count($hops) <= 3, "no redirect loop for $p", implode(' > ', $hops));
}
[$fc, $hops] = chain($base, '/no-such-page/');
ok($fc === 404 && count($hops) === 2, 'an unknown slash URL: one hop, then a real 404', implode(' > ', $hops));

// Every sitemap URL: untouched itself, and its slash twin lands on it in one hop.
$idx = (string) @file_get_contents($base . '/sitemap.xml');
preg_match_all('#<loc>([^<]+)</loc>#', $idx, $m);
$urls = [];
foreach ($m[1] as $child) {
    $x = (string) @file_get_contents($base . $path(html_entity_decode($child)));
    preg_match_all('#<loc>([^<]+)</loc>#', $x, $mm);
    foreach ($mm[1] as $u) $urls[] = $path(html_entity_decode($u));
}
$urls = array_values(array_unique(array_filter($urls, static fn($u) => $u !== '' && $u !== '/')));
ok(count($urls) > 50, 'the sitemap lists pages to check', (string) count($urls));
$bad = 0;
foreach ($urls as $u) {
    [$c] = req($base . $u);
    [$c2, $l2] = req($base . $u . '/');
    if ($c !== 200 || $c2 !== 301 || $path($l2) !== $u) { $bad++; if ($bad <= 5) echo "      $u: $c, slash twin $c2 -> $l2\n"; }
}
ok($bad === 0, count($urls) . ' sitemap URLs answer 200 and their slash twins 301 to them in one hop', "$bad bad");

if ($proc) proc_terminate($proc);
echo "trailing_slash_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
