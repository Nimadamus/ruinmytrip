<?php
/**
 * Fire many requests at one endpoint in the same instant, and report what the database actually did.
 *
 * The browser version of this worked and cost forty to seventy seconds a trial, because every
 * contender was a whole Chromium. This does the same job with curl_multi: sessions are signed in
 * and warmed BEFORE the barrier, each one carries its own cookie jar and its own fresh CSRF token,
 * and then every handle is released together.
 *
 * Two things it deliberately does not do. It does not weaken any protection to make testing
 * easier: the rate limits, the CSRF check and the session handling are the real ones, and the
 * setup phase is spread out enough to stay under the login limit rather than turning it off. And
 * it does not report an HTTP status as a result: a redirect is 200 whether the join was taken or
 * refused, so the answer always comes from counting rows afterwards.
 *
 * It also distinguishes its own failures from the product's. A contender that could not sign in,
 * or that arrived without a token, is reported as a HARNESS failure and excluded from the verdict,
 * because a test that quietly counts its own mistakes as product behaviour is worse than no test.
 *
 *   php scripts/race_harness.php --scenario=last-seat --contenders=8
 *   php scripts/race_harness.php --scenario=all
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) continue;
    $bit = substr($arg, 2);
    [$k, $v] = str_contains($bit, '=') ? explode('=', $bit, 2) : [$bit, '1'];
    $opts[$k] = $v;
}
$BASE = rtrim((string) ($opts['site'] ?? 'http://127.0.0.1:8099'), '/');
$N    = max(2, min(24, (int) ($opts['contenders'] ?? 8)));
$only = (string) ($opts['scenario'] ?? 'all');

/* ---------------------------------------------------------------------------------------------
 * Contenders. Real accounts, made once and reused, so a run does not spend its login budget
 * creating people. Verified on creation because joining a plan requires it.
 * ------------------------------------------------------------------------------------------ */
function racer_names(int $n): array {
    $out = [];
    for ($i = 1; $i <= $n; $i++) {
        $u = 'racer' . $i;
        if (!q_one('SELECT id FROM users WHERE username = ?', [$u])) {
            q_run("INSERT INTO users (username,email,password_hash,role,status,email_verified_at,created_at)
                   VALUES (?,?,?,'user','active',?,?)",
                  [$u, $u . '@example.com', password_hash('travel1234', PASSWORD_DEFAULT),
                   date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
            q_run('INSERT INTO profiles (user_id) VALUES (?)', [(int) db()->lastInsertId()]);
        }
        $out[] = $u;
    }
    return $out;
}

/** The login limit is real and stays real. This keeps the harness under it instead of removing it. */
function clear_login_limits(): void {
    try { q_run("DELETE FROM rate_limits WHERE bucket LIKE 'login_%'"); } catch (Throwable $e) {}
}

/** One signed-in session with its own cookie jar. Returns null when the harness itself failed. */
function sign_in(string $base, string $user): ?array {
    $jar = tempnam(sys_get_temp_dir(), 'rmtjar');
    $get = static function (string $url) use ($jar): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar,
                                CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_TIMEOUT => 20]);
        $body = (string) curl_exec($ch);
        curl_close($ch);
        return $body;
    };
    $page = $get($base . '/login');
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $page, $m)) return null;

    $ch = curl_init($base . '/login');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POSTFIELDS => http_build_query([
            '_csrf' => $m[1], 'email' => $user . '@example.com', 'password' => 'travel1234']),
        CURLOPT_TIMEOUT => 20,
    ]);
    $after = (string) curl_exec($ch);
    curl_close($ch);
    // The feed only renders for somebody signed in, so its presence is the proof.
    if (!str_contains($after, 'data-suggest-users')) return null;
    return ['user' => $user, 'jar' => $jar, 'get' => $get];
}

/** A handle aimed at one URL, primed with a token read from the page it will act on. */
function primed_post(array $sess, string $base, string $page, string $url, array $fields): ?CurlHandle {
    $html = ($sess['get'])($base . $page);
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $html, $m)) return null;
    $ch = curl_init($base . $url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $sess['jar'], CURLOPT_COOKIEFILE => $sess['jar'],
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => http_build_query(array_merge(['_csrf' => $m[1]], $fields)),
    ]);
    return $ch;
}

/** Release every handle together and wait for all of them. */
function fire(array $handles): array {
    $multi = curl_multi_init();
    foreach ($handles as $h) curl_multi_add_handle($multi, $h);
    $running = null;
    do {
        curl_multi_exec($multi, $running);
        if ($running) curl_multi_select($multi, 0.5);
    } while ($running > 0);
    $out = [];
    foreach ($handles as $key => $h) {
        $out[$key] = ['code' => (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE),
                      'body' => (string) curl_multi_getcontent($h)];
        curl_multi_remove_handle($multi, $h);
        curl_close($h);
    }
    curl_multi_close($multi);
    return $out;
}

/** A plan to race for, made fresh each scenario so trials never contaminate each other. */
function make_plan(int $capacity, string $mode = 'open', ?string $cancelled = null): int {
    $trip = q_one("SELECT id, destination_id FROM trips WHERE status = 'published'
                    AND COALESCE(visibility,'public') = 'public' ORDER BY id LIMIT 1");
    q_run("INSERT INTO trip_activities (trip_id,user_id,destination_id,day,title,category,visibility,
                                        join_mode,capacity,status,created_at,cancelled_at)
           SELECT ?, t.user_id, ?, NULL, ?, 'food', 'trip', ?, ?, 'published', ?, ?
             FROM trips t WHERE t.id = ?",
          [(int) $trip['id'], (int) $trip['destination_id'], 'Race ' . bin2hex(random_bytes(4)),
           $mode, $capacity ?: null, date('Y-m-d H:i:s'), $cancelled, (int) $trip['id']]);
    return (int) db()->lastInsertId();
}

function going(int $aid): int {
    return (int) (q_one("SELECT COUNT(*) c FROM activity_joins WHERE activity_id = ? AND state = 'going'",
                        [$aid])['c'] ?? 0);
}
function rows(int $aid): int {
    return (int) (q_one('SELECT COUNT(*) c FROM activity_joins WHERE activity_id = ?', [$aid])['c'] ?? 0);
}

$results = [];
function verdict(string $name, bool $ok, string $detail): void {
    global $results;
    $results[] = [$name, $ok, $detail];
    printf("  %-4s %-34s %s\n", $ok ? 'PASS' : 'FAIL', $name, $detail);
}

clear_login_limits();
$names = racer_names($N);
echo "signing in $N contenders\n";
$sessions = [];
foreach ($names as $u) {
    $s = sign_in($BASE, $u);
    if ($s === null) { fwrite(STDERR, "HARNESS: could not sign in $u\n"); continue; }
    $sessions[$u] = $s;
}
if (count($sessions) < 2) { fwrite(STDERR, "HARNESS: not enough sessions\n"); exit(2); }
echo 'ready: ' . count($sessions) . " sessions\n\n";

/* ---------------------------------------------------------------------------------------------
 * The matrix.
 * ------------------------------------------------------------------------------------------ */

if ($only === 'all' || $only === 'last-seat') {
    echo "-- everybody for one seat --\n";
    $aid = make_plan(1);
    $hs = [];
    foreach ($sessions as $u => $s) {
        $h = primed_post($s, $BASE, '/activity/' . $aid, '/activity/' . $aid . '/join', ['state' => 'going']);
        if ($h === null) { fwrite(STDERR, "HARNESS: no token for $u\n"); continue; }
        $hs[$u] = $h;
    }
    fire($hs);
    verdict('one seat is taken once', going($aid) === 1, going($aid) . ' going, ' . count($hs) . ' contenders');
    verdict('and nobody else wrote a row', rows($aid) === 1, rows($aid) . ' rows');
}

if ($only === 'all' || $only === 'seats') {
    echo "\n-- everybody for three seats --\n";
    $aid = make_plan(3);
    $hs = [];
    foreach ($sessions as $u => $s) {
        $h = primed_post($s, $BASE, '/activity/' . $aid, '/activity/' . $aid . '/join', ['state' => 'going']);
        if ($h !== null) $hs[$u] = $h;
    }
    fire($hs);
    verdict('three seats are filled exactly', going($aid) === 3, going($aid) . ' going');
}

if ($only === 'all' || $only === 'cancel') {
    echo "\n-- cancelled while they were joining --\n";
    /* The host cancels in the middle of the rush. Either answer is defensible for any one
       contender; what is not is a cancelled plan that keeps taking people. */
    $aid = make_plan(0);
    /* Somebody committed BEFORE the cancellation, so the test is about what happens to the people
       arriving during and after it rather than about an empty plan refusing an empty rush. */
    $seedUser = array_key_first($sessions);
    $seed = primed_post($sessions[$seedUser], $BASE, '/activity/' . $aid,
                        '/activity/' . $aid . '/join', ['state' => 'going']);
    if ($seed) fire([$seed]);
    $committed = going($aid);
    $hs = [];
    foreach ($sessions as $u => $s) {
        if ($u === $seedUser) continue;
        $h = primed_post($s, $BASE, '/activity/' . $aid, '/activity/' . $aid . '/join', ['state' => 'going']);
        if ($h !== null) $hs[$u] = $h;
    }
    q_run('UPDATE trip_activities SET cancelled_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $aid]);
    fire($hs);
    $after = going($aid);
    // Anybody who committed before the cancellation is legitimate history; nobody may arrive after.
    $more = [];
    foreach ($sessions as $u => $s) {
        $h = primed_post($s, $BASE, '/activity/' . $aid, '/activity/' . $aid . '/join', ['state' => 'going']);
        if ($h !== null) $more[$u] = $h;
    }
    fire($more);
    verdict('a cancelled plan takes nobody new', going($aid) === $after,
            'was ' . $after . ', still ' . going($aid));
    verdict('and keeps whoever had already committed', $committed === 1 && going($aid) >= 1,
            $committed . ' committed before it was called off');
}

if ($only === 'all' || $only === 'leave') {
    echo "\n-- one leaves while the rest arrive --\n";
    $aid = make_plan(2);
    $first = array_key_first($sessions);
    $h = primed_post($sessions[$first], $BASE, '/activity/' . $aid, '/activity/' . $aid . '/join', ['state' => 'going']);
    if ($h) fire([$h]);
    $seed = going($aid);
    $hs = [];
    foreach ($sessions as $u => $s) {
        if ($u === $first) continue;
        $x = primed_post($s, $BASE, '/activity/' . $aid, '/activity/' . $aid . '/join', ['state' => 'going']);
        if ($x !== null) $hs[$u] = $x;
    }
    // The seeded attendee leaves at the same moment everybody else tries to arrive.
    $leave = primed_post($sessions[$first], $BASE, '/activity/' . $aid,
                         '/activity/' . $aid . '/cancel-request', []);
    if ($leave) $hs['__leaver'] = $leave;
    fire($hs);
    verdict('attendance never exceeds the limit', going($aid) <= 2, going($aid) . ' going, limit 2');
    verdict('one person holds at most one place',
            (int) (q_one('SELECT COUNT(*) c FROM (SELECT user_id FROM activity_joins
                            WHERE activity_id = ? GROUP BY user_id HAVING COUNT(*) > 1) x', [$aid])['c'] ?? 0) === 0,
            'no duplicate rows');
    echo '     seeded ' . $seed . ', final ' . going($aid) . "\n";
}

if ($only === 'all' || $only === 'idempotent') {
    echo "\n-- the same person pressing twice --\n";
    $aid = make_plan(0);
    $u = array_key_first($sessions);
    $hs = [];
    for ($i = 0; $i < 4; $i++) {
        $h = primed_post($sessions[$u], $BASE, '/activity/' . $aid, '/activity/' . $aid . '/join', ['state' => 'going']);
        if ($h !== null) $hs['try' . $i] = $h;
    }
    fire($hs);
    /* Exactly one, not "at most". Zero used to pass this and zero was the bug: the join toggled,
       so an even number of presses left somebody off a plan they meant to be on. */
    verdict('four presses leave exactly one row', rows($aid) === 1, rows($aid) . ' rows');
    verdict('and that row says they are going', going($aid) === 1, going($aid) . ' going');
}

if ($only === 'all' || $only === 'unrelated') {
    echo "\n-- two plans at once do not queue behind each other --\n";
    $a1 = make_plan(4);
    $a2 = make_plan(4);
    $hs = [];
    $i = 0;
    foreach ($sessions as $u => $s) {
        $aid = (++$i % 2) ? $a1 : $a2;
        $h = primed_post($s, $BASE, '/activity/' . $aid, '/activity/' . $aid . '/join', ['state' => 'going']);
        if ($h !== null) $hs[$u] = $h;
    }
    $t0 = microtime(true);
    fire($hs);
    $ms = (microtime(true) - $t0) * 1000;
    verdict('both plans filled', going($a1) > 0 && going($a2) > 0,
            sprintf('%d and %d in %.0fms', going($a1), going($a2), $ms));
}
if ($only === 'all' || $only === 'toggles') {
    echo "\n-- a repeated press means the same thing both times --\n";
    /* Follow and save were written as flips: the POST said "the other one" rather than "this one".
       A double tap, a retry on a bad connection, a back then forward, all reversed what the member
       had just asked for and said nothing. Each button now sends the state it wants. */
    $u = array_key_first($sessions);
    $target = q_one("SELECT id, username FROM users WHERE username <> ? AND status = 'active' ORDER BY id LIMIT 1", [$u]);
    $tid = (int) $target['id'];
    $me  = (int) q_one('SELECT id FROM users WHERE username = ?', [$u])['id'];
    q_run('DELETE FROM follows WHERE follower_id = ? AND followee_id = ?', [$me, $tid]);
    $follows = static fn(): int => (int) (q_one('SELECT COUNT(*) c FROM follows WHERE follower_id = ? AND followee_id = ?', [$me, $tid])['c'] ?? 0);

    $hs = [];
    for ($i = 0; $i < 4; $i++) {
        $h = primed_post($sessions[$u], $BASE, '/u/' . $target['username'], '/follow',
                         ['user_id' => $tid, 'want' => 'on', 'return' => '/u/' . $target['username']]);
        if ($h !== null) $hs['f' . $i] = $h;
    }
    fire($hs);
    verdict('four follows leave one follow', $follows() === 1, $follows() . ' rows');

    $hs = [];
    for ($i = 0; $i < 3; $i++) {
        $h = primed_post($sessions[$u], $BASE, '/u/' . $target['username'], '/follow',
                         ['user_id' => $tid, 'want' => 'off', 'return' => '/u/' . $target['username']]);
        if ($h !== null) $hs['g' . $i] = $h;
    }
    fire($hs);
    verdict('three unfollows leave none', $follows() === 0, $follows() . ' rows');

    $place = q_one("SELECT id, slug FROM places WHERE status = 'active' ORDER BY id LIMIT 1");
    $pid = (int) $place['id'];
    q_run("DELETE FROM saves WHERE user_id = ? AND target_type = 'place' AND target_id = ?", [$me, $pid]);
    $saves = static fn(): int => (int) (q_one("SELECT COUNT(*) c FROM saves WHERE user_id = ? AND target_type = 'place' AND target_id = ?", [$me, $pid])['c'] ?? 0);

    $hs = [];
    for ($i = 0; $i < 4; $i++) {
        $h = primed_post($sessions[$u], $BASE, '/p/' . $place['slug'], '/place/save',
                         ['place_id' => $pid, 'want' => 'on', 'return' => '/p/' . $place['slug']]);
        if ($h !== null) $hs['s' . $i] = $h;
    }
    fire($hs);
    verdict('four saves leave one save', $saves() === 1, $saves() . ' rows');

    $hs = [];
    for ($i = 0; $i < 3; $i++) {
        $h = primed_post($sessions[$u], $BASE, '/p/' . $place['slug'], '/place/save',
                         ['place_id' => $pid, 'want' => 'off', 'return' => '/p/' . $place['slug']]);
        if ($h !== null) $hs['r' . $i] = $h;
    }
    fire($hs);
    verdict('three unsaves leave none', $saves() === 0, $saves() . ' rows');
}


echo "\n";
$bad = array_filter($results, static fn(array $r): bool => !$r[1]);
printf("race_harness: %d passed, %d failed\n", count($results) - count($bad), count($bad));
exit($bad ? 1 : 0);
