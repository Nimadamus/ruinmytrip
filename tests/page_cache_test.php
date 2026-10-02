<?php
/**
 * The signed out city page cache (app/page_cache.php, Q5 2026-10-01).
 *
 * Starts a real server against the dev database and checks what makes the cache safe: the second
 * signed out read is a hit, every reader still gets their OWN csrf token in the cached copy, a
 * query string is never cached, and a cached page is byte for byte the built one apart from that
 * token. Skips without a dev database, like pages_render_test.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$db = $root . '/database/dev.sqlite';
if (!is_file($db) || filesize($db) < 1024) { echo "SKIP  no dev database\n"; exit(0); }

$dir = rtrim(sys_get_temp_dir(), '/' . chr(92)) . '/rmt_page_cache';
foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);

$port = 8124;
$ini = is_file($root . '/php.local.ini') ? ['-c', $root . '/php.local.ini'] : [];
$cmd = array_merge([PHP_BINARY], $ini, ['-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php']);
$log = $root . '/tests/.render_server.log';
$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root);
$base = 'http://127.0.0.1:' . $port;
$up = false;
for ($i = 0; $i < 40 && !$up; $i++) {
    $up = @file_get_contents($base . '/healthz', false, stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]])) !== false;
    if (!$up) usleep(250000);
}
if (!$up) { proc_terminate($proc); echo "SKIP  server did not come up\n"; exit(0); }

$fails = 0;
function ok(string $n, bool $c): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }

/** GET with an optional cookie; returns [headers, body, set cookie]. */
function get(string $url, string $cookie = ''): array {
    $ctx = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true,
        'header' => $cookie !== '' ? "Cookie: $cookie\r\n" : '']]);
    $body = (string) @file_get_contents($url, false, $ctx);
    $h = $http_response_header ?? [];
    $cache = ''; $set = '';
    foreach ($h as $line) {
        if (stripos($line, 'X-RMT-Cache:') === 0) $cache = trim(substr($line, 12));
        if (stripos($line, 'Set-Cookie:') === 0 && preg_match('/(rmt_sess=[^;]+)/', $line, $m)) $set = $m[1];
    }
    return [$cache, $body, $set];
}
function token(string $html): string {
    return preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

$slug = (string) (new PDO('sqlite:' . $db))->query("SELECT slug FROM destinations LIMIT 1")->fetchColumn();
$url = $base . '/d/' . $slug;

[$c1, $b1, $ck1] = get($url);
ok('the first signed out read builds the page', $c1 === 'miss');
[$c2, $b2] = get($url, $ck1);
ok('the second is served from the cache', $c2 === 'hit');
ok('the reader gets their own token back', token($b1) !== '' && token($b1) === token($b2));
ok('no placeholder ever reaches a page', !str_contains($b2, '__RMT_CSRF_TOKEN__'));
[$c3, $b3, $ck3] = get($url);
ok('a different reader, also a hit', $c3 === 'hit');
ok('and gets a different token', token($b3) !== '' && token($b3) !== token($b1));
ok('the cached page is the built page apart from the token',
   str_replace(token($b1), 'T', $b1) === str_replace(token($b3), 'T', $b3));
[$c4] = get($url . '?ask=avoid');
ok('a query string is never cached', $c4 === '');
[$c5, $b5] = get($base . '/d/' . $slug . '/travelers');
[$c6, $b6] = get($base . '/d/' . $slug . '/travelers');
ok('the travelers page caches too', $c5 === 'miss' && $c6 === 'hit');
ok('and renders clean', !preg_match('/Fatal error|Uncaught|Warning:/', $b6));

[$c7, $b7] = get($base . '/e/day-of-the-dead-oaxaca');
[$c8, $b8] = get($base . '/e/day-of-the-dead-oaxaca');
ok('an event guide caches too', $c7 === 'miss' && $c8 === 'hit');
ok('and keeps its alert form with a real token', str_contains($b8, 'name="_csrf"') && !str_contains($b8, '__RMT_CSRF_TOKEN__'));
$pdo = new PDO('sqlite:' . $db);
$place = (string) $pdo->query("SELECT slug FROM places WHERE status = 'active' ORDER BY id LIMIT 1")->fetchColumn();
if ($place !== '') {
    [$c9] = get($base . '/p/' . $place);
    [$c10, $b10] = get($base . '/p/' . $place);
    ok('a place page caches too', $c9 === 'miss' && $c10 === 'hit');
    ok('and renders clean', !preg_match('/Fatal error|Uncaught|Warning:/', $b10) && !str_contains($b10, '__RMT_CSRF_TOKEN__'));
}

proc_terminate($proc);
foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
echo $fails ? "$fails FAIL(S)\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
