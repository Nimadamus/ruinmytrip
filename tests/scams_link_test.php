<?php
/**
 * "N scams to know in <city>" on place and review pages (views/_scams_link.php, 2026-11-07):
 * present, pointing at the city's #scams section, only for a city that has a sourced section, and
 * changing no robots or canonical tag. GET only against a local server on database/dev.sqlite.
 *   php tests/scams_link_test.php
 */
declare(strict_types=1);
$root = dirname(__DIR__);
define('BASE_PATH', $root);
require $root . '/app/city_scams.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) { $pass++; echo "PASS  $m\n"; } else { $fail++; echo "FAIL  $m\n"; } }

$db = $root . '/database/dev.sqlite';
if (!is_file($db) || filesize($db) < 1024) { echo "SKIP  no dev database\n"; exit(0); }
$pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$with = array_keys(rmt_city_scams_all());
ok(count($with) > 0, 'some cities have a sourced scams section');
$in = implode(',', array_map([$pdo, 'quote'], $with));
$one = fn(string $sql) => $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: null;
$place   = $one("SELECT p.slug, d.slug ds, d.name dn FROM places p JOIN destinations d ON d.id = p.destination_id WHERE d.slug IN ($in) ORDER BY p.id LIMIT 1");
$plain   = $one("SELECT p.slug, d.slug ds FROM places p JOIN destinations d ON d.id = p.destination_id WHERE d.slug NOT IN ($in) ORDER BY p.id LIMIT 1");
$review  = $one("SELECT r.id, r.slug, d.slug ds, d.name dn FROM reviews r JOIN destinations d ON d.id = r.destination_id
                  JOIN users u ON u.id = r.user_id WHERE r.status = 'published' AND u.status = 'active' AND d.slug IN ($in) ORDER BY r.id LIMIT 1");

$port = 8131;
$nul = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$env = getenv(); unset($env['DATABASE_URL'], $env['RMT_DESIGN_V2']);
$proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $nul, 'a'], 2 => ['file', $nul, 'a']], $pipes, $root, $env + ['RMT_PAGE_CACHE' => '0']);
$base = 'http://127.0.0.1:' . $port;
for ($i = 0; $i < 40 && @file_get_contents($base . '/healthz') === false; $i++) usleep(250000);
$get = function (string $path) use ($base): string {
    return (string) @file_get_contents($base . $path, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30]]));
};
$link = fn(string $html, string $ds) => (bool) preg_match('#href="[^"]*/d/' . preg_quote($ds, '#') . '\#scams"[^>]*>\d+ scams? to know in#', $html);
$head = function (string $html): string {
    preg_match('#<meta name="robots" content="([^"]*)"#', $html, $r); preg_match('#<link rel="canonical" href="([^"]*)"#', $html, $c);
    return ($r[1] ?? '-') . ' | ' . ($c[1] ?? '-');
};

if ($place) {
    $h = $get('/p/' . $place['slug']);
    ok($link($h, $place['ds']), "place /p/{$place['slug']}: links to /d/{$place['ds']}#scams");
    ok(str_contains($h, 'data-cta="place_scams"'), 'place link is counted as place_scams');
    ok(str_contains($head($h), 'index, follow') || !str_contains($head($h), 'noindex'), 'place page carries no noindex: ' . $head($h));
} else ok(false, 'a place in a city with scams exists in dev.sqlite');
if ($plain) ok(!str_contains($get('/p/' . $plain['slug']), 'to know in'), "place in a city without a section (/p/{$plain['slug']}) shows no link");
if ($review) {
    $h = $get('/review/' . $review['id'] . '/' . $review['slug']);
    ok($link($h, $review['ds']), "review /review/{$review['id']}: links to /d/{$review['ds']}#scams");
    ok(str_contains($h, 'data-cta="review_scams"'), 'review link is counted as review_scams');
    ok(!str_contains($head($h), 'noindex'), 'review page carries no noindex: ' . $head($h));
} else ok(false, 'a published review in a city with scams exists in dev.sqlite');
$city = $get('/d/' . ($place['ds'] ?? 'rome-italy'));
ok(str_contains($city, 'id="scams"'), 'the link target #scams exists on the city page');

proc_terminate($proc);
echo "scams_link_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
