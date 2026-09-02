<?php
/**
 * Personal invite links: capture, attach at signup, self-invite refused, count, notification.
 *
 *   php tests/invites_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/invites.php';

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT, role TEXT, created_at TEXT)");
$pdo->exec($sql = file_get_contents(BASE_PATH . '/database/migrations/068_user_invited_by.sqlite.sql'));
$pdo->exec("CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT)");
$pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT, actor_id INT, target_type TEXT, target_id INT, read_at TEXT, created_at TEXT)");
$pdo->exec("INSERT INTO users (id,username,status,role,created_at) VALUES (1,'ana','active','user','2026-01-01'),(2,'ben','active','user','2026-01-02'),(3,'gone','suspended','user','2026-01-03'),(4,'cy','active','user','2026-01-04')");

$pass = 0; $fail = 0;
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $msg\n"; } }
$_SESSION = [];

// capture
$_GET['ref'] = 'ANA'; rmt_invite_capture();
ok(($_SESSION['ref'] ?? null) === 'ana', 'ref captured case-insensitively, stored canonical');
$_SESSION = []; $_GET['ref'] = 'gone'; rmt_invite_capture();
ok(!isset($_SESSION['ref']), 'suspended member is not a referrer');
$_SESSION = []; $_GET['ref'] = '../x'; rmt_invite_capture();
ok(!isset($_SESSION['ref']), 'junk ref ignored');
$_SESSION = []; $_GET['ref'] = 'nobody'; rmt_invite_capture();
ok(!isset($_SESSION['ref']), 'unknown ref ignored');
unset($_GET['ref']);

// attach
$_SESSION['ref'] = 'ana';
ok(rmt_invite_referrer()['id'] === 1, 'referrer resolves');
ok(rmt_invite_attach(2), 'ben attached to ana');
ok((int) q_one('SELECT invited_by FROM users WHERE id=2')['invited_by'] === 1, 'invited_by written');
ok(!isset($_SESSION['ref']), 'ref forgotten after signup');
$n = q_one("SELECT * FROM notifications WHERE user_id=1 AND type='invite_joined'");
ok($n && (int) $n['actor_id'] === 2 && $n['target_type'] === 'user' && (int) $n['target_id'] === 2, 'ana notified with ben as actor');
ok(!rmt_invite_attach(2, 4), 'already-attached account keeps its first inviter');
ok((int) q_one('SELECT invited_by FROM users WHERE id=2')['invited_by'] === 1, 'still ana');
ok(!rmt_invite_attach(4, 4), 'self invite refused');
ok(q_one('SELECT invited_by FROM users WHERE id=4')['invited_by'] === null, 'cy has no inviter');
ok(!rmt_invite_attach(4, 3), 'suspended inviter refused');
ok(!rmt_invite_attach(4, null) && q_one('SELECT invited_by FROM users WHERE id=4')['invited_by'] === null, 'no ref, no inviter');
ok(rmt_invite_attach(4, 1), 'explicit referrer id works');

// counts + list + link
ok(rmt_invite_count(1) === 2, 'ana brought two');
$pdo->exec("UPDATE users SET status='suspended' WHERE id=4");
ok(rmt_invite_count(1) === 1, 'suspended invitee does not count');
$recent = rmt_invite_recent(1);
ok(count($recent) === 1 && $recent[0]['username'] === 'ben', 'recent lists active invitees');
ok(rmt_invite_link(['username' => 'ana']) === 'https://example.test/?ref=ana', 'link shape');
ok(str_contains(rmt_invite_message(['username' => 'ana']), '?ref=ana'), 'message carries the link');
ok(count(q_all("SELECT 1 FROM notifications WHERE type='invite_joined'")) === 2, 'one notification per attach');

echo "invites_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
