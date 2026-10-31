<?php
/**
 * Taking an account back has to sign the other party out.
 *
 *   1. A completed password reset ends every other session signed in as that member. Before this,
 *      somebody who had stolen the old password stayed signed in after the owner reset it.
 *   2. A Google sign in that takes over an account whose address was never confirmed ends the
 *      sessions of whoever registered that address first (pre-registration takeover).
 *   3. A reset link is claimed atomically: of two submissions racing on one link, one wins.
 *
 * Sessions are matched in the exact format PHP's session serializer writes, generated here by PHP
 * itself rather than typed by hand, so a change of serializer turns this red instead of quietly
 * matching nothing.
 *
 *   php tests/session_takeover_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/tokens.php';
// session.php declares the handler class and the helper; nothing in it starts a session.
require BASE_PATH . '/app/session.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if ($cond) { echo "  PASS  $name\n"; return; }
    $fails++;
    echo "  FAIL  $name" . ($detail !== '' ? "  ($detail)" : '') . "\n";
}

db()->exec("CREATE TABLE sessions (id TEXT PRIMARY KEY, data TEXT NOT NULL DEFAULT '', updated_at INTEGER NOT NULL)");
db()->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)");
db()->exec("CREATE TABLE auth_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, kind TEXT,
            token_hash TEXT UNIQUE, expires_at TEXT, used_at TEXT, created_at TEXT)");
db()->exec("INSERT INTO users (id, email) VALUES (7, 'a@example.test'), (77, 'b@example.test')");

/** What PHP itself writes for a session holding $vars. */
function encoded(array $vars): string {
    $_SESSION = $vars;
    return (string) session_encode();
}
ini_set('session.use_cookies', '0');
ini_set('session.save_path', sys_get_temp_dir());
@session_start();

$rows = [
    'thief'      => encoded(['uid' => 7]),
    'thief2'     => encoded(['_csrf' => str_repeat('a', 64), 'flash' => 'Welcome back.', 'uid' => 7]),
    'after_arr'  => encoded(['seen' => ['x' => 1], 'uid' => 7]),
    'owner_now'  => encoded(['uid' => 7]),
    'other'      => encoded(['uid' => 77]),
    'other_pre'  => encoded(['uid' => 70]),
    'guest'      => encoded(['_csrf' => 'b', 'note' => 'uid 7']),
];
session_write_close();
foreach ($rows as $id => $data) {
    db()->prepare('INSERT INTO sessions (id, data, updated_at) VALUES (?,?,?)')->execute([$id, $data, time()]);
}
ok('the serializer writes the shape the helper looks for', str_contains($rows['thief'], 'uid|i:7;'), $rows['thief']);

$ended = rmt_sessions_end_for_user(7, 'owner_now');
$left = array_column(q_all('SELECT id FROM sessions ORDER BY id'), 'id');
ok('every other session of that member is ended', $ended === 3, 'ended=' . $ended);
ok('the session in hand is kept', in_array('owner_now', $left, true));
ok('another member (uid 77) is untouched', in_array('other', $left, true));
ok('a member whose id starts the same (uid 70) is untouched', in_array('other_pre', $left, true));
ok('a guest session is untouched', in_array('guest', $left, true));
ok('nothing happens for a missing id', rmt_sessions_end_for_user(0) === 0);

// --- The callers ------------------------------------------------------------------------------
$ctl = file_get_contents(BASE_PATH . '/app/controllers.php');
$qj  = file_get_contents(BASE_PATH . '/app/quick_join.php');
$start = strpos($ctl, 'function reset_submit(');
$reset = substr($ctl, $start, strpos($ctl, "\nfunction ", $start + 10) - $start);
ok('reset_submit ends the other sessions', str_contains($reset, 'rmt_sessions_end_for_user($uid, session_id())'));
ok('reset_submit claims the link before changing the password',
   strpos($reset, 'rmt_token_consume') < strpos($reset, 'SET password_hash'));
ok('reset_submit refuses when the claim is lost', str_contains($reset, 'if (!rmt_token_consume('));
ok('the Google takeover of an unconfirmed account ends the old sessions',
   (bool) preg_match('/password_hash = \? WHERE id = \?.*?rmt_sessions_end_for_user\(\(int\) \$u\[\'id\'\]/s', $qj));

// --- Single use under a race ------------------------------------------------------------------
$raw = rmt_token_issue(7, 'reset');
$row = rmt_token_lookup($raw, 'reset');
ok('a fresh link is live', $row !== null);
// Two requests both looked the link up before either consumed it.
$first  = rmt_token_consume((int) $row['id']);
$second = rmt_token_consume((int) $row['id']);
ok('the first claim wins', $first === true);
ok('the second claim on the same link loses', $second === false);
ok('the link is dead afterwards', rmt_token_lookup($raw, 'reset') === null);

echo $fails ? "\n$fails FAILED\n" : "\nAll session takeover tests passed.\n";
exit($fails ? 1 : 0);
