<?php
/**
 * Every main page renders to the end.
 *
 * This exists because of a bug I shipped on 2026-09-09. A view helper was typed to return a string
 * and returned null for most rows, so /feed printed the first few entries and then died with a
 * TypeError. The response was still 200, because the header had already gone out, and the check I
 * ran afterwards grepped for a phrase that appeared in the rows that HAD printed. Status codes and
 * happy-path greps both said fine while the page was broken in the middle.
 *
 * So this asks the only question that would have caught it: does the whole page come out clean?
 * It starts a real server against the dev database, walks the routes a visitor can reach, and fails
 * on any PHP error text anywhere in the body -- not on the status line.
 *
 * Skips itself if there is no dev database, so a checkout without one is not a failure.
 *
 *   php tests/pages_render_test.php   -> PASS/FAIL per route, exits non-zero on any failure.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$db = $root . '/database/dev.sqlite';
if (!is_file($db) || filesize($db) < 1024) {
    echo "SKIP  no dev database at database/dev.sqlite\n";
    exit(0);
}

$php = PHP_BINARY;
$port = 8123;
$ini = is_file($root . '/php.local.ini') ? ['-c', $root . '/php.local.ini'] : [];

// A server of our own on a port nothing else uses, so this never reads whatever a stray process
// left running: that mistake once had a whole page sweep reporting on a two-batch-old database.
$cmd = array_merge([$php], $ini, ['-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php']);
$spec = [0 => ['pipe', 'r'], 1 => ['file', $root . '/tests/.render_server.log', 'a'], 2 => ['file', $root . '/tests/.render_server.log', 'a']];
$proc = proc_open($cmd, $spec, $pipes, $root);
if (!is_resource($proc)) {
    echo "SKIP  could not start a server\n";
    exit(0);
}

$base = 'http://127.0.0.1:' . $port;
$up = false;
for ($i = 0; $i < 40; $i++) {
    $c = @file_get_contents($base . '/healthz', false, stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]));
    if ($c !== false) { $up = true; break; }
    usleep(250000);
}

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

if (!$up) {
    proc_terminate($proc);
    echo "SKIP  server did not come up\n";
    exit(0);
}

/** Anything PHP prints when a page falls over. A page may not contain any of it. */
const BROKEN = ['Fatal error', 'Uncaught', 'SQLSTATE', 'Parse error', 'Warning:', 'Deprecated:'];

$routes = ['/', '/explore', '/travelers', '/going', '/meetups', '/talk', '/discover', '/reviews',
           '/guides', '/blog', '/collections', '/communities', '/tags', '/ruined', '/contribute',
           '/leaderboard', '/about', '/safety', '/register', '/login', '/sitemap.xml', '/feed.xml'];

// Plus one of each entity that exists, so the templates that read a row are actually exercised.
$pdo = new PDO('sqlite:' . $db);
$one = static function (string $sql) use ($pdo): ?string {
    $r = $pdo->query($sql)->fetch(PDO::FETCH_NUM);
    return $r ? (string) $r[0] : null;
};
foreach ([
    "SELECT '/d/' || slug FROM destinations LIMIT 1",
    "SELECT '/d/' || slug || '/travelers' FROM destinations LIMIT 1",
    "SELECT '/d/' || slug || '/places' FROM destinations LIMIT 1",
    "SELECT '/p/' || slug FROM places WHERE status='active' LIMIT 1",
    "SELECT '/u/' || username FROM users WHERE status='active' LIMIT 1",
    "SELECT '/review/' || id || '/' || COALESCE(slug,'') FROM reviews WHERE status='published' LIMIT 1",
    "SELECT '/trip/' || id || '/' || COALESCE(slug,'') FROM trips WHERE status='published' AND COALESCE(visibility,'public')='public' LIMIT 1",
    "SELECT '/post/' || id FROM posts WHERE status='published' LIMIT 1",
    "SELECT '/blog/' || slug FROM blog_posts WHERE status='published' LIMIT 1",
    "SELECT '/g/' || slug FROM guides WHERE status='published' LIMIT 1",
] as $sql) {
    $p = $one($sql);
    if ($p !== null && $p !== '') $routes[] = $p;
}

foreach ($routes as $path) {
    $ctx = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true, 'follow_location' => 0]]);
    $body = @file_get_contents($base . $path, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $status = (int) $m[1]; break; }
    }
    if ($body === false) { ok("GET $path", false, 'no response'); continue; }
    $hit = '';
    foreach (BROKEN as $needle) {
        if (str_contains($body, $needle)) { $hit = $needle; break; }
    }
    // 200 or a redirect are both fine; what is never fine is error text inside the body.
    $okStatus = $status === 200 || ($status >= 300 && $status < 400);
    ok("GET $path", $hit === '' && $okStatus,
       ($hit !== '' ? "contains '$hit'" : '') . ($okStatus ? '' : " status $status"));
}

/* The pages that actually broke are behind a login, and a sweep that only sees the logged-out site
   would have walked straight past the bug this file exists for: /feed answers 302 to a stranger, so
   nobody ever rendered the row that threw.

   Done with streams rather than by shelling out to curl. The first version of this called curl and
   curl was not where the test thought it was, so every request came back empty -- and an empty body
   contains no error text, so every check "passed". That is the same false pass, one layer down, and
   it is why each page below must also come back with a real body and a marker only a member sees.

   It borrows an existing dev account rather than creating one, and puts the password hash back. */
$restore = null;
$acct = $pdo->query("SELECT id, email, password_hash FROM users WHERE status='active' AND email IS NOT NULL
                      AND email <> '' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($acct) {
    $restore = [$acct['id'], $acct['password_hash']];
    $pw = 'render-test-' . bin2hex(random_bytes(4));
    $pdo->prepare('UPDATE users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?')
        ->execute([password_hash($pw, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $acct['id']]);

    /** One request, returning [status, body, cookies]. */
    $req = static function (string $path, ?array $post, string $cookie) use ($base): array {
        $opts = ['http' => ['timeout' => 20, 'ignore_errors' => true, 'follow_location' => 0,
                            'header' => "Accept: text/html\r\n" . ($cookie ? "Cookie: $cookie\r\n" : '')]];
        if ($post !== null) {
            $opts['http']['method'] = 'POST';
            $opts['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $opts['http']['content'] = http_build_query($post);
        }
        $body = @file_get_contents($base . $path, false, stream_context_create($opts));
        $status = 0; $jar = [];
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int) $m[1];
            if (preg_match('#^Set-Cookie:\s*([^;]+)#i', $h, $m)) $jar[] = trim($m[1]);
        }
        return [$status, (string) $body, implode('; ', $jar)];
    };

    [, $loginPage, $cookie] = $req('/login', null, '');
    preg_match('/name="_csrf" value="([^"]+)"/', $loginPage, $m);
    [$code, , $cookie2] = $req('/login', ['_csrf' => $m[1] ?? '', 'email' => $acct['email'], 'password' => $pw], $cookie);
    $cookie = $cookie2 ?: $cookie;
    ok('signed in as an existing member', $code === 302, "http $code");

    /* /settings is deliberately a redirect to the canonical profile editor, so the editor itself is
       what gets rendered here. A route that is meant to redirect is not evidence of anything. */
    $editor = '/u/' . (string) $pdo->query('SELECT username FROM users WHERE id = ' . (int) $acct['id'])
                                   ->fetch(PDO::FETCH_NUM)[0] . '/edit';
    foreach (['/feed', '/matches', '/notifications', '/saved', '/messages', $editor,
              '/trip/new', '/review/new', '/invite'] as $path) {
        [$st, $body] = $req($path, null, $cookie);
        $hit = '';
        foreach (BROKEN as $needle) {
            if (str_contains($body, $needle)) { $hit = $needle; break; }
        }
        // A page that came back empty, or bounced to the login form, proves nothing about rendering.
        $real = strlen($body) > 500 && !str_contains($body, 'name="password"');
        ok("GET $path (signed in)", $hit === '' && $st === 200 && $real,
           ($hit !== '' ? "contains '$hit'" : '') . ($st === 200 ? '' : " status $st")
           . ($real ? '' : ' body ' . strlen($body) . 'B or bounced to login'));
    }
}

if ($restore) {
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$restore[1], $restore[0]]);
}

proc_terminate($proc);
@unlink($root . '/tests/.render_server.log');

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
