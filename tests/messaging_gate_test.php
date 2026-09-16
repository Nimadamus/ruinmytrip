<?php
/**
 * Who may write to whom.
 *
 * This is a security test wearing a product test's clothes. The whole value of the messaging on
 * this site is the promise that a stranger cannot write to you, and a promise enforced in a view
 * is a promise with a door next to it: anybody can craft a POST. So the rule lives in exactly one
 * function, rmt_message_allowed(), the endpoint asks it before it reads the request body, and this
 * file asserts the function directly rather than through any page.
 *
 * The four states, and what each one means for a message:
 *
 *   none       nobody has asked           -> no
 *   requested  I asked, they have not answered -> no, and the page says "waiting on them"
 *   incoming   they asked me              -> no, and the page offers me the answer
 *   declined   they said no               -> no, permanently, and they are never told again
 *   accepted   both said yes              -> yes
 *
 * Two deliberate exceptions, both stated rather than implied. A block beats everything, in either
 * direction. And a conversation that already has messages in it stays open, because this feature
 * tightens something that used to be open to anybody and cutting live threads in half would punish
 * the people who were already talking for a policy they had no part in.
 *
 *   php tests/messaging_gate_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/connects.php';

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-52s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

/* The three functions rmt_message_allowed() leans on, and nothing else from messages.php: pulling
   in the whole file would pull in the controllers with it. Same bodies as the real ones. */
function rmt_is_blocked(int $a, int $b): bool {
    return (bool) q_one('SELECT 1 FROM blocks WHERE (blocker_id=? AND blocked_id=?) OR (blocker_id=? AND blocked_id=?)',
                        [$a, $b, $b, $a]);
}
function rmt_conversation_pair(int $a, int $b): array { return $a < $b ? [$a, $b] : [$b, $a]; }
function rmt_find_conversation(int $a, int $b): ?int {
    [$lo, $hi] = rmt_conversation_pair($a, $b);
    $r = q_one('SELECT id FROM conversations WHERE user_lo_id=? AND user_hi_id=?', [$lo, $hi]);
    return $r ? (int) $r['id'] : null;
}
/* The function under test, lifted by name out of the module so this suite tests the shipped code
   rather than a copy of it. */
$src = (string) file_get_contents(BASE_PATH . '/app/messages.php');
preg_match('/function rmt_message_allowed\(int \$meId, int \$themId\): array \{.*?\n\}/s', $src, $m);
if (!$m) { echo "FAIL: rmt_message_allowed() not found in app/messages.php\n"; exit(1); }
eval($m[0]);

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active')");
$pdo->exec("CREATE TABLE blocks (blocker_id INT, blocked_id INT, PRIMARY KEY (blocker_id, blocked_id))");
$pdo->exec("CREATE TABLE conversations (id INTEGER PRIMARY KEY AUTOINCREMENT, user_lo_id INT, user_hi_id INT, last_message_at TEXT)");
$pdo->exec("CREATE TABLE messages (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INT, sender_id INT,
              body TEXT, created_at TEXT, read_at TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/092_trip_connects.sqlite.sql'));
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
              status TEXT DEFAULT 'published', visibility TEXT DEFAULT 'public',
              date_from TEXT, date_to TEXT, open_to_meeting INT)");
foreach ([[1,'me'],[2,'stranger'],[3,'asked'],[4,'asker'],[5,'refused'],[6,'friend'],[7,'blocked_one'],[8,'old_thread']] as [$id,$n]) {
    $pdo->exec("INSERT INTO users (id,username) VALUES ($id,'$n')");
}
$pdo->exec('INSERT INTO blocks (blocker_id, blocked_id) VALUES (7, 1)');

$connect = static function (int $from, int $to, string $state) use ($pdo): int {
    $pdo->exec("INSERT INTO trips (id, user_id, date_from, date_to) VALUES (NULL, $to, '2026-10-12', '2026-10-20')");
    $trip = (int) $pdo->lastInsertId();
    $st = $pdo->prepare('INSERT INTO trip_connects (trip_id, from_user_id, to_user_id, state, created_at) VALUES (?,?,?,?,?)');
    $st->execute([$trip, $from, $to, $state, '2026-09-15 12:00:00']);
    return (int) $pdo->lastInsertId();
};

echo "-- the four states --\n";
ok('a stranger cannot be messaged', rmt_message_allowed(1, 2)['ok'], false);
ok('...and the reason is that nobody asked', rmt_message_allowed(1, 2)['reason'], 'none');

$connect(1, 3, 'interested');
ok('asking is not being accepted', rmt_message_allowed(1, 3)['ok'], false);
ok('...and the page can say we are waiting', rmt_message_allowed(1, 3)['reason'], 'requested');

$connect(4, 1, 'interested');
ok('somebody asking me does not open the door either', rmt_message_allowed(1, 4)['ok'], false);
ok('...and the page can offer me the answer', rmt_message_allowed(1, 4)['reason'], 'incoming');

$connect(1, 5, 'declined');
ok('a no is a no', rmt_message_allowed(1, 5)['ok'], false);
ok('...and stays a no', rmt_message_allowed(1, 5)['reason'], 'declined');

$connect(1, 6, 'accepted');
ok('both saying yes opens it', rmt_message_allowed(1, 6)['ok'], true);
ok('...and it opens from their side too', rmt_message_allowed(6, 1)['ok'], true);

echo "\n-- a block beats everything --\n";
$connect(1, 7, 'accepted');
ok('an accepted connect does not survive a block', rmt_message_allowed(1, 7)['ok'], false);
ok('...and says so', rmt_message_allowed(1, 7)['reason'], 'blocked');
ok('the block holds in the other direction', rmt_message_allowed(7, 1)['ok'], false);

echo "\n-- nobody talks to themselves --\n";
ok('a thread with yourself is not a thread', rmt_message_allowed(1, 1)['ok'], false);
ok('...and is named as such', rmt_message_allowed(1, 1)['reason'], 'self');

echo "\n-- a conversation that already exists stays open --\n";
/* The tightening does not reach backwards. Two people already talking keep their thread; either
   of them can still block, and either can stop replying. */
$pdo->exec('INSERT INTO conversations (id, user_lo_id, user_hi_id) VALUES (99, 1, 8)');
ok('an empty conversation row is not a conversation', rmt_message_allowed(1, 8)['ok'], false);
$pdo->exec("INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (99, 8, 'hello', '2026-09-01')");
ok('one with messages in it stays open', rmt_message_allowed(1, 8)['ok'], true);
ok('...and says why', rmt_message_allowed(1, 8)['reason'], 'existing');

echo "\n-- the endpoint asks before it writes --\n";
/* The gate has to be the first thing messages_send() does with two user ids, and it has to come
   before the body is read: a refusal that happens after a write is not a refusal. */
$sendSrc = (string) $src;
$fn = substr($sendSrc, (int) strpos($sendSrc, 'function messages_send'));
$fn = substr($fn, 0, (int) strpos($fn, "\n}\n"));
$posGate   = strpos($fn, 'rmt_message_allowed(');
$posBody   = strpos($fn, "input('body')");
$posInsert = strpos($fn, 'INSERT INTO messages');
ok('messages_send() asks the gate', $posGate !== false, true);
ok('...before it reads the message', $posGate !== false && $posBody !== false && $posGate < $posBody, true);
ok('...and long before it writes one', $posGate !== false && $posInsert !== false && $posGate < $posInsert, true);
ok('it still checks CSRF', str_contains($fn, 'csrf_check()'), true);
ok('and it still has a length limit', str_contains($fn, 'RMT_MESSAGE_BODY_MAX'), true);

echo "\n-- what telemetry is allowed to know about a message --\n";
/* Both calls take no arguments at all, which is the simplest possible guarantee that no private
   message can end up in an analytics table: there is nowhere to put one. */
ok('a sent message is counted', str_contains($sendSrc, "rmt_track('message_sent')"), true);
ok('...with nothing attached to it', str_contains($sendSrc, "rmt_track('message_sent', "), false);
ok('a thread being read is counted', str_contains($sendSrc, "rmt_track('message_thread_viewed')"), true);
ok('...with nothing attached either', str_contains($sendSrc, "rmt_track('message_thread_viewed', "), false);
ok('no message body is ever passed to the tracker',
   (bool) preg_match('/rmt_track\([^)]*\$body/', $sendSrc), false);

echo "\n-- a thread is addressed by person, never by id --\n";
/* There is no conversation id in any URL, so there is no id to change. The thread route resolves a
   username to the pair, which can only ever be a conversation the caller is in. */
$routes = (string) file_get_contents(BASE_PATH . '/public/index.php');
ok('the thread route takes a username', str_contains($routes, "#^/messages/(?<username>[A-Za-z0-9_]+)$#"), true);
ok('no route accepts a conversation id', (bool) preg_match('#/messages/\(\?<id>#', $routes), false);

echo "\n-- message text is escaped where it is drawn --\n";
$view = (string) file_get_contents(BASE_PATH . '/views/messages_thread.php');
ok('the bubble escapes the body', str_contains($view, "e(\$m['body'])"), true);
ok('and never prints it raw', (bool) preg_match('/<\?=\s*\$m\[.body.\]/', $view), false);
ok('the composer only appears when the gate says yes', str_contains($view, "\$gate['ok']"), true);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL MESSAGING GATE TESTS PASS ({$pass})\n";
