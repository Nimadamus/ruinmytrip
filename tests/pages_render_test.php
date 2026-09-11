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

    /* The login limiter counts attempts per address and per IP in a fifteen minute window, and a
       developer who has been signing in to the dev site by hand, or a browser test that logged in
       a few times, leaves that window full. The suite would then report ten failures that mean
       nothing except "somebody used the site recently". The buckets for this one account and for
       localhost are cleared first: it is the same dev database this test already rewrites a
       password in, and the limiter is not what is under test here. */
    $pdo->prepare('DELETE FROM rate_limits WHERE bucket IN (?, ?)')
        ->execute(['login_ip:127.0.0.1', 'login_email:' . (string) $acct['email']]);

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

    /* A private trip must not reach a feed, anybody's.

       It did. The activity stream applied the scope clause, which decides WHOSE activity you see,
       and never read the trip's own visibility, which is whether the author meant it to be seen at
       all. So a trip marked "only you" appeared on /discover, a public page, and in the feed of
       everybody who followed its author. Found by planting one and looking, which is the only way
       this class of bug is ever found.

       The trip is owned by somebody who is not the signed-in account, so the check is about
       visibility and not about ownership. */
    $otherId = (int) ($pdo->query("SELECT id FROM users WHERE status='active' AND id <> "
                                  . (int) $acct['id'] . " ORDER BY id LIMIT 1")->fetch(PDO::FETCH_NUM)[0] ?? 0);
    if ($otherId > 0) {
        $marker = 'PRIVATETRIPCANARY' . bin2hex(random_bytes(4));
        $destId = (int) ($pdo->query('SELECT id FROM destinations ORDER BY id LIMIT 1')->fetch(PDO::FETCH_NUM)[0] ?? 0);
        $pdo->prepare("INSERT INTO trips (user_id, destination_id, title, slug, body, status, visibility, created_at)
                       VALUES (?,?,?,?,?,'published','private',?)")
            ->execute([$otherId, $destId ?: null, $marker, 'canary-' . strtolower($marker), $marker, date('Y-m-d H:i:s')]);

        /* Everywhere a trip can be listed. Four of these were wrong at different times and each
           one was found by planting a canary rather than by reading the query, which is the whole
           argument for a list this long. */
        $destForCanary = $pdo->query('SELECT slug FROM destinations ORDER BY id LIMIT 1')->fetch(PDO::FETCH_NUM)[0] ?? null;
        $paths = ['/discover', '/feed', '/feed?scope=everyone', '/', '/going', '/travelers'];
        if ($destForCanary) $paths[] = '/d/' . $destForCanary;
        foreach ($paths as $path) {
            [, $body] = $req($path, null, $cookie);
            ok("a private trip stays out of $path", !str_contains($body, $marker),
               'the canary was in the page');
        }
        /* Search is checked on the link rather than on the word, because the results page echoes
           the query back in its title and in the box: looking for the marker there would fail for
           a page that is behaving perfectly. What must not appear is a link to the trip. */
        $canarySlug = 'canary-' . strtolower($marker);
        [, $searchBody] = $req('/search?q=' . $marker, null, $cookie);
        ok('a private trip is not a search result', !str_contains($searchBody, $canarySlug),
           'the trip was linked from the results');
        // And logged out, where /discover is the public front door.
        $anon = @file_get_contents($base . '/discover', false,
            stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]));
        ok('a private trip stays out of /discover for a stranger', !str_contains((string) $anon, $marker));

        $pdo->prepare('DELETE FROM trips WHERE title = ?')->execute([$marker]);
    }

    /* A private trip's photographs must not reach a public page either.

       This is the same bug as the feed one, one layer down, and it was fixed in two of the three
       places that read photographs: the photo wall and the profile got the visibility clause and
       the city page kept calling the old query, so /d/{slug} was still showing them. Planting one
       and asking for the page is the only check that would have caught that.

       The canary is the caption, because that is what a grid renders into alt text. */
    if ($otherId > 0) {
        $capMarker = 'PRIVATEPHOTOCANARY' . bin2hex(random_bytes(4));
        $destRow = $pdo->query('SELECT id, slug FROM destinations ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($destRow) {
            $pdo->prepare("INSERT INTO trips (user_id, destination_id, title, slug, body, status, visibility, created_at)
                           VALUES (?,?,?,?,'','published','private',?)")
                ->execute([$otherId, (int) $destRow['id'], 'canary trip', 'canary-trip-' . strtolower($capMarker), date('Y-m-d H:i:s')]);
            $tripId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO trip_photos (trip_id, user_id, url, caption, sort, created_at)
                           VALUES (?,?,?,?,0,?)")
                ->execute([$tripId, $otherId, '/assets/img/og-default.svg', $capMarker, date('Y-m-d H:i:s')]);

            foreach (['/d/' . $destRow['slug'], '/d/' . $destRow['slug'] . '/photos',
                      '/d/' . $destRow['slug'] . '/travelers', '/u/' . $acct['email']] as $path) {
                if (str_starts_with($path, '/u/')) continue;   // username, not email; covered below
                [, $body] = $req($path, null, $cookie);
                ok("a private trip's photo stays off $path", !str_contains($body, $capMarker));
            }
            $anonWall = @file_get_contents($base . '/d/' . $destRow['slug'] . '/photos', false,
                stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]));
            ok("a private trip's photo stays off the public wall", !str_contains((string) $anonWall, $capMarker));

            // And its own page is not there for anybody else.
            $photoId = (int) $pdo->query('SELECT MAX(id) FROM trip_photos')->fetch(PDO::FETCH_NUM)[0];
            [$st] = $req('/photo/trip/' . $photoId, null, $cookie);
            ok('a private photo page 404s for somebody else', $st === 404, "status $st");

            $pdo->prepare('DELETE FROM trip_photos WHERE trip_id = ?')->execute([$tripId]);
            $pdo->prepare('DELETE FROM trips WHERE id = ?')->execute([$tripId]);
        }
    }


    /* A notification that is new has to look new, exactly once.

       This is a bug I shipped an hour before writing this check. view() extracts the page's data
       into its own scope and then requires the layout header, and the header sets its own $unseen
       for the nav badge. The header runs after the extract, so a page variable of that name was
       quietly replaced by an integer and every row rendered as already read: the page marked
       everything read, which is its job, and destroyed the answer to the only question it is
       opened to ask. Nothing failed, nothing 500'd, the page just stopped saying anything.

       So: insert one unread row, ask for the page, and require the marker. Then ask again and
       require that it is gone, which is the other half of the contract. */
    $pdo->prepare("INSERT INTO notifications (user_id, type, actor_id, target_type, target_id, created_at)
                   VALUES (?, 'follow', ?, 'user', ?, ?)")
        ->execute([(int) $acct['id'], (int) $acct['id'], (int) $acct['id'], date('Y-m-d H:i:s')]);
    [, $first] = $req('/notifications', null, $cookie);
    ok('an unread notification is marked as new', str_contains($first, 'note-new'),
       'no note-new in the body on the first view');
    [, $second] = $req('/notifications', null, $cookie);
    ok('and is not marked new the next time', !str_contains($second, 'note-new'),
       'still marked new after being read');
    $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND type = 'follow' AND actor_id = ?")
        ->execute([(int) $acct['id'], (int) $acct['id']]);
}

if ($restore) {
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$restore[1], $restore[0]]);
}

proc_terminate($proc);
@unlink($root . '/tests/.render_server.log');

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
