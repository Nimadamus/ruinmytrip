<?php
/**
 * An adversarial pass: the things somebody would actually try.
 *
 * The other privacy tests check that the right page shows the right thing. This one starts from the
 * other end and tries to get at what it should not have: guessing ids, holding an old link, keeping
 * a right after losing the standing that granted it, and reading a notification about something
 * that has since been taken away.
 *
 * Every case here is written as an attempt and an expected refusal, so a regression reads as
 * "somebody got in" rather than as "an assertion changed".
 *
 * It runs a real server against the dev database, like tests/pages_render_test.php, and skips
 * itself when there is no dev database to attack.
 *
 *   php tests/adversarial_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$db = $root . '/database/dev.sqlite';
if (!is_file($db) || filesize($db) < 1024) { echo "SKIP  no dev database\n"; exit(0); }

$php = PHP_BINARY;
$port = 8126;
$ini = is_file($root . '/php.local.ini') ? ['-c', $root . '/php.local.ini'] : [];
$cmd = array_merge([$php], $ini, ['-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php']);
$log = $root . '/tests/.adversarial_server.log';
$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root);
if (!is_resource($proc)) { echo "SKIP  could not start a server\n"; exit(0); }

$base = 'http://127.0.0.1:' . $port;
$up = false;
for ($i = 0; $i < 40; $i++) {
    if (@file_get_contents($base . '/healthz', false,
        stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]])) !== false) { $up = true; break; }
    usleep(250000);
}
if (!$up) { proc_terminate($proc); echo "SKIP  server did not come up\n"; exit(0); }

$fails = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $fails;
    if ($cond) { echo "PASS  $what\n"; return; }
    $fails++;
    echo "FAIL  $what" . ($detail !== '' ? " -- $detail" : '') . "\n";
}

/** GET a path, optionally with a session cookie. Returns [status, body]. */
$get = static function (string $path, ?string $cookie = null) use ($base): array {
    $head = "Accept: text/html\r\n" . ($cookie ? "Cookie: $cookie\r\n" : '');
    $body = @file_get_contents($base . $path, false, stream_context_create(['http' => [
        'timeout' => 20, 'ignore_errors' => true, 'follow_location' => 0, 'header' => $head,
    ]]));
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $status = (int) $m[1]; break; }
    }
    return [$status, (string) $body];
};

$pdo = new PDO('sqlite:' . $db);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$owner = $pdo->query("SELECT id, username FROM users WHERE status='active' ORDER BY id LIMIT 1")->fetch();
$other = $pdo->query("SELECT id, username FROM users WHERE status='active' ORDER BY id DESC LIMIT 1")->fetch();
$dest  = $pdo->query("SELECT id, slug FROM destinations ORDER BY id LIMIT 1")->fetch();
if (!$owner || !$dest) { proc_terminate($proc); echo "SKIP  dev database has no users or cities\n"; exit(0); }

$mark = 'ADVERSARY' . bin2hex(random_bytes(4));
$now = date('Y-m-d H:i:s');

/* The bait: a private trip with a plan on it, a meeting point, and a photograph. Everything a
   stranger would want and must not have. */
$pdo->prepare("INSERT INTO trips (user_id, destination_id, title, slug, body, status, visibility, date_from, date_to, created_at)
               VALUES (?,?,?,?,'','published','private',?,?,?)")
    ->execute([(int) $owner['id'], (int) $dest['id'], 'bait trip ' . $mark, 'bait-' . strtolower($mark),
               date('Y-m-d', strtotime('+3 days')), date('Y-m-d', strtotime('+9 days')), $now]);
$tripId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO trip_activities (trip_id, user_id, destination_id, day, title, category,
                 visibility, join_mode, meeting_point, status, created_at)
               VALUES (?,?,?,?,?,'other','trip','ask',?,'published',?)")
    ->execute([$tripId, (int) $owner['id'], (int) $dest['id'], date('Y-m-d', strtotime('+4 days')),
               'bait plan ' . $mark, 'MEETPOINT' . $mark, $now]);
$actId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO trip_photos (trip_id, user_id, url, caption, sort, status, created_at)
               VALUES (?,?,?,?,0,'published',?)")
    ->execute([$tripId, (int) $owner['id'], '/media/nonexistent.jpg', 'bait caption ' . $mark, $now]);
$photoId = (int) $pdo->lastInsertId();

// --- guessing ids while signed out -----------------------------------------------------------------
foreach ([
    "a private trip by id"              => '/trip/' . $tripId,
    "the same trip with a wrong slug"   => '/trip/' . $tripId . '/holiday',
    "its plan by id"                    => '/activity/' . $actId,
    "its photograph by id"              => '/photo/trip/' . $photoId,
] as $what => $path) {
    [$st, $body] = $get($path);
    ok("signed out, $what is refused", $st === 404 || $st === 403 || $st === 302, "status $st");
    ok("signed out, $what leaks nothing", !str_contains($body, $mark));
}

/* A plan id one either side of a real one. Enumeration is the cheapest attack there is, and the
   answer for a plan that does not exist must look the same as for one that is not yours. */
[$stA] = $get('/activity/' . ($actId + 99999));
[$stB] = $get('/activity/' . $actId);
ok('a plan that does not exist and one you cannot see answer the same way', $stA === $stB,
   "missing=$stA private=$stB");

// --- the crawler ------------------------------------------------------------------------------------
[$stS, $sitemap] = $get('/sitemap-community.xml');
ok('a private trip is in no sitemap', !str_contains($sitemap, '/trip/' . $tripId));
ok('and neither is its plan', !str_contains($sitemap, '/activity/' . $actId));

// --- a share card is a public route ------------------------------------------------------------------
[$stC, $card] = $get('/card/trip/' . $tripId . '.png');
ok('a private trip draws no share card', $stC !== 200, "status $stC");
[$stC2] = $get('/card/activity/' . $actId . '.png');
ok('and neither does a plan on one', $stC2 !== 200, "status $stC2");

// --- somebody who asked and was told no ----------------------------------------------------------------
if ($other && (int) $other['id'] !== (int) $owner['id']) {
    $otherId = (int) $other['id'];
    /* Make the bait trip public so the plan page is reachable, then give this account a declined
       request. Being told no must not leave somebody holding the meeting point. */
    $pdo->prepare("UPDATE trips SET visibility = 'public' WHERE id = ?")->execute([$tripId]);
    $pdo->prepare("INSERT INTO activity_joins (activity_id, user_id, state, created_at)
                   VALUES (?,?,'declined',?)")->execute([$actId, $otherId, $now]);

    /* Sign in the hard way: the dev fixtures all share one password, and a session cookie is the
       only honest way to test what a signed-in stranger can see. */
    $cookie = null;
    $loginBody = @file_get_contents($base . '/login', false,
        stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]));
    if (preg_match('/name="_csrf"\s+value="([^"]+)"/', (string) $loginBody, $m)) {
        $token = $m[1];
        $sid = '';
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $h, $c)) { $sid = $c[1]; break; }
        }
        $email = $pdo->query("SELECT email FROM users WHERE id = $otherId")->fetch()['email'] ?? '';
        $post = http_build_query(["_csrf" => $token, 'email' => $email, 'password' => 'travel1234']);
        @file_get_contents($base . '/login', false, stream_context_create(['http' => [
            'method' => 'POST', 'timeout' => 15, 'ignore_errors' => true, 'follow_location' => 0,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $sid\r\n",
            'content' => $post,
        ]]));
        $cookie = $sid;
    }

    if ($cookie) {
        [$stD, $bodyD] = $get('/activity/' . $actId, $cookie);
        ok('somebody told no can still open the public plan', $stD === 200, "status $stD");
        ok('but does not hold the meeting point', !str_contains($bodyD, 'MEETPOINT' . $mark));

        // The trip goes private under them. An old link must stop working immediately.
        $pdo->prepare("UPDATE trips SET visibility = 'private' WHERE id = ?")->execute([$tripId]);
        [$stE, $bodyE] = $get('/trip/' . $tripId, $cookie);
        ok('a link held from before the trip went private stops working', $stE !== 200, "status $stE");
        ok('and the page it used to show is gone', !str_contains($bodyE, $mark));

        [$stF, $bodyF] = $get('/activity/' . $actId, $cookie);
        ok('and so does the plan on it', $stF !== 200 || !str_contains($bodyF, $mark), "status $stF");
    } else {
        echo "SKIP  could not sign in as a second account\n";
    }
}

// --- content that was taken down ----------------------------------------------------------------------
$pdo->prepare("UPDATE trips SET visibility = 'public', status = 'removed' WHERE id = ?")->execute([$tripId]);
[$stG, $bodyG] = $get('/trip/' . $tripId);
ok('a removed trip is gone even though it is public', $stG !== 200 && !str_contains($bodyG, $mark), "status $stG");
[$stH, $bodyH] = $get('/activity/' . $actId);
ok('and its plan goes with it', $stH !== 200 || !str_contains($bodyH, $mark), "status $stH");

// --- clean up the bait ----------------------------------------------------------------------------------
$pdo->prepare('DELETE FROM activity_joins WHERE activity_id = ?')->execute([$actId]);
$pdo->prepare('DELETE FROM trip_activities WHERE trip_id = ?')->execute([$tripId]);
$pdo->prepare('DELETE FROM trip_photos WHERE trip_id = ?')->execute([$tripId]);
$pdo->prepare('DELETE FROM trips WHERE id = ?')->execute([$tripId]);

proc_terminate($proc);
echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
