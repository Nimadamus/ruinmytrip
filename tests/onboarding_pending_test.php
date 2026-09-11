<?php
/**
 * Regression tests for held onboarding work (app/onboarding_pending.php).
 *
 * The welcome screen asked a brand new member for the three things that decide whether they come
 * back -- where they are going, which rooms to join, and a first sentence -- and then enforced the
 * publish gate by throwing the submission away. Every member is unverified thirty seconds after
 * signing up, so the most valuable answer on the screen was also the one guaranteed to be lost,
 * along with everything after it in the same POST.
 *
 * What must hold:
 *   - held work survives until the address is confirmed, and is applied exactly once.
 *   - the gate is not bypassed: nothing is written while the account is unverified.
 *   - the two halves are independent -- a rejected first post must not take the travel dates with it.
 *   - only the shapes we put there are kept; anything else in the session slot is ignored.
 *
 *   php tests/onboarding_pending_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
session_start();

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/onboarding_pending.php';

$pdo = db();
$pdo->exec('CREATE TABLE going (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
              date_from TEXT, date_to TEXT, visibility TEXT, note TEXT, created_at TEXT)');
$pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, body TEXT, status TEXT)');

// The two validators and the two writers, standing in for the real modules, which need the whole app.
$GLOBALS['calls'] = ['going' => 0, 'notify' => 0, 'post' => 0];
function rmt_going_validate(array $in): array {
    $from = (string) ($in['date_from'] ?? '');
    $to   = (string) ($in['date_to'] ?? '');
    if ($from === '' || $to === '' || $to < $from) return ['ok' => false, 'errors' => ['bad dates'], 'data' => []];
    return ['ok' => true, 'errors' => [], 'data' => ['destination_id' => (int) ($in['destination_id'] ?? 0),
            'date_from' => $from, 'date_to' => $to, 'visibility' => (string) ($in['visibility'] ?? 'public')]];
}
function rmt_going_upsert(int $uid, array $d): int {
    $GLOBALS['calls']['going']++;
    q_run('INSERT INTO going (user_id,destination_id,date_from,date_to,visibility,created_at) VALUES (?,?,?,?,?,?)',
          [$uid, $d['destination_id'], $d['date_from'], $d['date_to'], $d['visibility'], date('Y-m-d H:i:s')]);
    return (int) q_one('SELECT MAX(id) m FROM going')['m'];
}
function rmt_going_notify_followers(int $uid, int $gid, string $vis): void { $GLOBALS['calls']['notify']++; }
function rmt_post_validate(array $in, ?array $user): array {
    $body = trim((string) ($in['body'] ?? ''));
    if ($body === '' || mb_strlen($body) < 3) return ['ok' => false, 'errors' => ['too short'], 'data' => []];
    return ['ok' => true, 'errors' => [], 'data' => ['body' => $body]];
}
function rmt_post_create(int $uid, array $d): int {
    $GLOBALS['calls']['post']++;
    q_run("INSERT INTO posts (user_id,body,status) VALUES (?,?,'published')", [$uid, $d['body']]);
    return (int) q_one('SELECT MAX(id) m FROM posts')['m'];
}

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}
$me = ['id' => 7];
$dates = ['destination_id' => 1, 'date_from' => '2027-03-01', 'date_to' => '2027-03-09', 'visibility' => 'public'];

ok('nothing waiting to begin with', !rmt_pending_has());
rmt_pending_stash(['going' => $dates, 'hello' => ['body' => 'First trip in years, any tips?']]);
ok('something is waiting', rmt_pending_has());
ok('holding writes nothing yet', (int) q_one('SELECT COUNT(*) c FROM going')['c'] === 0
    && (int) q_one('SELECT COUNT(*) c FROM posts')['c'] === 0);

$applied = rmt_pending_apply($me);
ok('the dates went live', $applied['going'] === true && (int) q_one('SELECT COUNT(*) c FROM going')['c'] === 1);
ok('the first post went live', $applied['hello'] === true && (int) q_one('SELECT COUNT(*) c FROM posts')['c'] === 1);
ok('followers were told about the dates', $GLOBALS['calls']['notify'] === 1);
ok('the slot is empty afterwards', !rmt_pending_has());

// Applied exactly once: a second confirmation must not post the same sentence again.
$again = rmt_pending_apply($me);
ok('applying twice does nothing', $again === ['going' => false, 'hello' => false, 'trip' => false]
    && (int) q_one('SELECT COUNT(*) c FROM posts')['c'] === 1);

// The halves are independent.
rmt_pending_stash(['going' => $dates, 'hello' => ['body' => 'x']]);
$mixed = rmt_pending_apply($me);
ok('a rejected post does not take the dates down', $mixed['going'] === true && $mixed['hello'] === false);
ok('the rejected post was not written', (int) q_one('SELECT COUNT(*) c FROM posts')['c'] === 1);

rmt_pending_stash(['going' => ['date_from' => '2027-03-09', 'date_to' => '2027-03-01']]);
$bad = rmt_pending_apply($me);
ok('invalid dates are dropped, not written', $bad['going'] === false
    && (int) q_one('SELECT COUNT(*) c FROM going')['c'] === 2);

// Only the shapes we put there survive.
rmt_pending_stash(['going' => $dates, 'role' => 'admin', 'uid' => 1]);
ok('anything else is ignored', array_keys((array) $_SESSION[RMT_PENDING_KEY]) === ['going']);
rmt_pending_apply($me);
ok('an empty stash is not a stash', !rmt_pending_has());
rmt_pending_stash([]);
ok('nothing to hold holds nothing', !rmt_pending_has());

/* A trip posted before the address came back.
   This is now the most likely thing to be lost, because the first screen a new member sees sends
   them straight at the trip form. The gate is unchanged: nothing is written while unverified. */
function rmt_trip_validate(array $in): array {
    $dest = (int) ($in['destination_id'] ?? 0);
    if ($dest < 1) return ['ok' => false, 'errors' => ['no city'], 'data' => []];
    return ['ok' => true, 'errors' => [], 'data' => ['destination_id' => $dest,
            'title' => (string) ($in['title'] ?? 'A trip'), 'body' => (string) ($in['body'] ?? '')]];
}
function rmt_trip_create_row(int $uid, array $d): int {
    $GLOBALS['calls']['trip']++;
    q_run('INSERT INTO trips (user_id, destination_id, title) VALUES (?,?,?)',
          [$uid, $d['destination_id'], $d['title']]);
    return (int) q_one('SELECT MAX(id) m FROM trips')['m'];
}
db()->exec('CREATE TABLE trips (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT, title TEXT)');
$GLOBALS['calls']['trip'] = 0;

rmt_pending_stash(['trip' => ['destination_id' => 4, 'title' => 'Lisbon in March', 'body' => 'Five days.']]);
ok('a trip can be held', rmt_pending_has());
ok('and nothing is written while it waits', (int) q_one('SELECT COUNT(*) c FROM trips')['c'] === 0);
$t = rmt_pending_apply($me);
ok('confirming writes the trip', $t['trip'] === true && (int) q_one('SELECT COUNT(*) c FROM trips')['c'] === 1);
ok('and only once', rmt_pending_apply($me)['trip'] === false
    && (int) q_one('SELECT COUNT(*) c FROM trips')['c'] === 1);
rmt_pending_stash(['trip' => ['title' => 'No city named']]);
ok('a trip that does not validate is dropped rather than written',
   rmt_pending_apply($me)['trip'] === false && (int) q_one('SELECT COUNT(*) c FROM trips')['c'] === 1);

// The trip form must hold the work rather than discard it at the gate.
ok('trip_create holds instead of bouncing',
   str_contains((string) file_get_contents(BASE_PATH . '/app/controllers.php'),
                "rmt_pending_stash(['trip' => \$_POST])"));

// The welcome screen must no longer bounce the whole submission at the gate.
$controllers = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('welcome no longer redirects to verify-email mid-form',
   !str_contains($controllers, "flash('Confirm your email before sharing travel dates.');"));
ok('confirming the address applies what was held', str_contains($controllers, 'rmt_pending_apply('));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
