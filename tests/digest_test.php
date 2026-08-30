<?php
/**
 * The digest decides who gets an email and what it says. An empty one is spam, so "nothing
 * happened" has to be exactly right.
 *
 *   php tests/digest_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/digest.php';

/** rmt_review_path() lives with the review rendering; the digest only needs a URL back. */
if (!function_exists('rmt_review_path')) {
    function rmt_review_path(array $r): string { return '/review/' . (int) $r['id']; }
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT, role TEXT DEFAULT 'user')");
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT, created_at TEXT)');
$pdo->exec('CREATE TABLE review_votes (review_id INT, user_id INT, created_at TEXT)');
$pdo->exec('CREATE TABLE compliments (to_user_id INT, from_user_id INT, created_at TEXT)');
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
    subject_name TEXT, slug TEXT, status TEXT, created_at TEXT)");
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
$pdo->exec('CREATE TABLE saves (user_id INT, target_type TEXT, target_id INT)');
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INT, collection_id INT, body TEXT,
    status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE comments (id INTEGER PRIMARY KEY, user_id INT, target_type TEXT, target_id INT,
    body TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE collections (id INTEGER PRIMARY KEY, title TEXT)");
$pdo->exec("CREATE TABLE collection_members (collection_id INT, user_id INT, status TEXT)");
$pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INT, type TEXT, target_id INT, created_at TEXT)");
$pdo->exec("CREATE TABLE meetups (id INTEGER PRIMARY KEY, title TEXT, date_start TEXT, status TEXT)");
$pdo->exec('CREATE TABLE conversations (id INTEGER PRIMARY KEY, user_lo_id INT, user_hi_id INT)');
$pdo->exec('CREATE TABLE messages (id INTEGER PRIMARY KEY, conversation_id INT, sender_id INT, read_at TEXT, created_at TEXT)');

$pdo->exec("INSERT INTO users (id,username,status) VALUES (1,'alice','active'),(2,'bob','active'),(3,'cara','active')");
$pdo->exec("INSERT INTO collections (id,title) VALUES (7,'Slow travel')");
$pdo->exec("INSERT INTO collection_members (collection_id,user_id,status) VALUES (7,1,'active'),(7,2,'active')");

$since = '2026-08-01 00:00:00';
$after = '2026-08-10 00:00:00';
$before = '2026-07-01 00:00:00';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-60s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

echo "-- silence --\n";
$a = rmt_digest_activity(1, $since);
check('nothing happened means no email', $a['any'], false);
check('and every counter is honest', $a['followers'] + $a['votes'] + $a['matches'] + $a['unread_messages'], 0);

echo "\n-- replies to your posts --\n";
$pdo->exec("INSERT INTO posts (id,user_id,body,status,created_at) VALUES (1,1,'Lisbon in August?','published','$since')");
$pdo->exec("INSERT INTO comments (id,user_id,target_type,target_id,body,status,created_at)
            VALUES (1,2,'post',1,'Go in October.','published','$after'),
                   (2,1,'post',1,'Answering myself.','published','$after'),
                   (3,2,'post',1,'Old one.','published','$before'),
                   (4,2,'post',1,'Deleted.','removed','$after')");
$a = rmt_digest_activity(1, $since);
check('one reply counted', count($a['replies']), 1);
check('by the other person', $a['replies'][0]['author'], 'bob');
check('links to the post', $a['replies'][0]['url'], 'https://example.test/post/1');
check('so there is something to send', $a['any'], true);
check('the author gets nothing for their own reply', count(rmt_digest_activity(2, $since)['replies']), 0);

echo "\n-- your communities --\n";
$pdo->exec("INSERT INTO posts (id,user_id,collection_id,body,status,created_at)
            VALUES (2,2,7,'Anyone done the ferry?','published','$after'),
                   (3,1,7,'My own post.','published','$after'),
                   (4,2,7,'Before the window.','published','$before')");
$a = rmt_digest_activity(1, $since);
check('a room I am in, by somebody else', count($a['community']), 1);
check('named so I know which room', $a['community'][0]['community'], 'Slow travel');
check('somebody not in the room hears nothing', count(rmt_digest_activity(3, $since)['community']), 0);

echo "\n-- matches and meetups come from what the site already decided --\n";
$pdo->exec("INSERT INTO notifications (id,user_id,type,target_id,created_at) VALUES
    (1,1,'trip_match',5,'$after'),(2,1,'trip_match',6,'$after'),(3,1,'trip_match',7,'$before')");
$pdo->exec("INSERT INTO meetups (id,title,date_start,status) VALUES
    (9,'Coffee and a walk','2026-09-02 10:00:00','published'),
    (10,'Cancelled one','2026-09-03 10:00:00','cancelled')");
$pdo->exec("INSERT INTO notifications (id,user_id,type,target_id,created_at) VALUES
    (4,1,'meetup_nearby',9,'$after'),(5,1,'meetup_nearby',10,'$after')");
$a = rmt_digest_activity(1, $since);
check('matches inside the window only', $a['matches'], 2);
check('a cancelled meetup is not offered', count($a['meetups']), 1);
check('and it reads like a date', $a['meetups'][0]['when'], 'Wed Sep 2');

echo "\n-- unread messages --\n";
$pdo->exec('INSERT INTO conversations (id,user_lo_id,user_hi_id) VALUES (1,1,2)');
$pdo->exec("INSERT INTO messages (id,conversation_id,sender_id,read_at,created_at)
            VALUES (1,1,2,NULL,'$after'),(2,1,2,'$after','$after'),(3,1,1,NULL,'$after')");
$a = rmt_digest_activity(1, $since);
check('unread, from the other person only', $a['unread_messages'], 1);
check('the other side counts only what was sent to them', rmt_digest_activity(2, $since)['unread_messages'], 1);

echo "\n-- the old signals still work --\n";
$pdo->exec("INSERT INTO follows (follower_id,followee_id,created_at) VALUES (2,3,'$after')");
$pdo->exec("INSERT INTO compliments (to_user_id,from_user_id,created_at) VALUES (3,1,'$after')");
$c = rmt_digest_activity(3, $since);
check('followers counted', $c['followers'], 1);
check('and named', $c['follower_names'], ['bob']);
check('compliments counted', $c['compliments'], 1);
check('summary line mentions everything', str_contains(rmt_digest_summary($c), '1 follower(s)'), true);

echo $fail ? "\nFAILED: $fail\n" : "\nOK\n";
exit($fail ? 1 : 0);
