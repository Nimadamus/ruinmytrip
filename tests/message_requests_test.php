<?php
/**
 * A first message from a stranger is a request, not a conversation.
 *
 * The rule is derived, never stored: a conversation is a request until the member has said
 * something in it. Nothing about it is a flag, because a flag is a thing that goes out of step
 * with the messages that are actually there.
 *
 * What this pins down:
 *   - a request does not light up the navigation, and an answered thread does
 *   - answering once moves it, permanently, both ways
 *   - a conversation the member started is never a request to them, even unanswered
 *   - a blocked or deleted account is not counted as anything
 *
 *   php tests/message_requests_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/messages.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active')");
$pdo->exec("CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT)");
$pdo->exec("CREATE TABLE conversations (id INTEGER PRIMARY KEY, user_lo_id INT, user_hi_id INT, last_message_at TEXT)");
$pdo->exec("CREATE TABLE messages (id INTEGER PRIMARY KEY, conversation_id INT, sender_id INT, body TEXT, read_at TEXT, created_at TEXT)");
$pdo->exec("INSERT INTO users (id,username) VALUES (1,'ana'),(2,'ben'),(3,'cara')");

// Ben writes to Ana and she has never answered. Cara and Ana have a real conversation.
$pdo->exec("INSERT INTO conversations (id,user_lo_id,user_hi_id,last_message_at) VALUES (1,1,2,'2026-09-10 10:00:00')");
$pdo->exec("INSERT INTO messages (conversation_id,sender_id,body,read_at,created_at)
            VALUES (1,2,'Are you in Lisbon on the 12th?',NULL,'2026-09-10 10:00:00')");
$pdo->exec("INSERT INTO conversations (id,user_lo_id,user_hi_id,last_message_at) VALUES (2,1,3,'2026-09-10 11:00:00')");
$pdo->exec("INSERT INTO messages (conversation_id,sender_id,body,read_at,created_at)
            VALUES (2,3,'Coffee tomorrow?',NULL,'2026-09-10 11:00:00'),
                   (2,1,'Yes',NULL,'2026-09-10 11:05:00')");

ok(rmt_message_request_count(1) === 1, 'a stranger who has not been answered is one request');
ok(rmt_unread_message_count(1) === 1, 'and the badge counts only the conversation she is in');

// She answers. It stops being a request, in both directions.
$pdo->exec("INSERT INTO messages (conversation_id,sender_id,body,read_at,created_at)
            VALUES (1,1,'I am, yes',NULL,'2026-09-10 12:00:00')");
ok(rmt_message_request_count(1) === 0, 'answering once moves it out of requests');
ok(rmt_unread_message_count(1) === 2, 'and the message she was sent joins the badge');

/* Ben started it, so it was never a request to him, answered or not. The asymmetry is the whole
   point: a request is about who has not spoken, not about who is newer. */
ok(rmt_message_request_count(2) === 0, 'a conversation you started is never a request to you');

// An account that is gone is not a request from anybody.
$pdo->exec("INSERT INTO conversations (id,user_lo_id,user_hi_id,last_message_at) VALUES (3,1,4,'2026-09-10 13:00:00')");
$pdo->exec("INSERT INTO users (id,username,status) VALUES (4,'gone','deleted')");
$pdo->exec("INSERT INTO messages (conversation_id,sender_id,body,read_at,created_at)
            VALUES (3,4,'hello',NULL,'2026-09-10 13:00:00')");
ok(rmt_message_request_count(1) === 0, 'a deleted account is not waiting on an answer');

// An empty conversation row, which the site creates when somebody opens a thread and says nothing.
$pdo->exec("INSERT INTO conversations (id,user_lo_id,user_hi_id,last_message_at) VALUES (4,1,3,NULL)");
ok(rmt_message_request_count(1) === 0, 'an empty conversation is not a request');

echo "message_requests_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
