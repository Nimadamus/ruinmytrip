<?php
/**
 * Posts: validation, community membership gate, edit/remove permissions, listing, indexability.
 *
 *   php tests/posts_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/communities.php';
require BASE_PATH . '/app/posts.php';
require BASE_PATH . '/app/indexability.php';

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT, role TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec("CREATE TABLE collections (id INTEGER PRIMARY KEY, user_id INT, slug TEXT, title TEXT,
    status TEXT DEFAULT 'published', join_policy TEXT NOT NULL DEFAULT 'closed', members_can_add INT DEFAULT 0)");
$pdo->exec("CREATE TABLE collection_members (id INTEGER PRIMARY KEY AUTOINCREMENT, collection_id INT, user_id INT,
    role TEXT, status TEXT, joined_at TEXT, removed_at TEXT)");
$pdo->exec("CREATE TABLE comments (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, target_type TEXT,
    target_id INT, body TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, destination_id INT, collection_id INT,
    body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'published', created_at TEXT NOT NULL, updated_at TEXT)");

$pdo->exec("INSERT INTO users (id,username,status,role) VALUES
    (1,'alice','active','user'),(2,'bob','active','user'),(3,'mod','active','mod')");
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (10,'lisbon-portugal','Lisbon')");
$pdo->exec("INSERT INTO collections (id,user_id,slug,title,status,join_policy) VALUES
    (5,1,'slow-travel','Slow travel','published','open'),
    (6,1,'my-list','My list','published','closed')");
$pdo->exec("INSERT INTO collection_members (collection_id,user_id,role,status,joined_at) VALUES
    (5,1,'owner','active','2026-01-01 00:00:00')");

$alice = ['id' => 1, 'role' => 'user'];
$bob   = ['id' => 2, 'role' => 'user'];
$mod   = ['id' => 3, 'role' => 'mod'];

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-62s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

echo "-- validation --\n";
check('too short is rejected', rmt_post_validate(['body' => 'hi'], $alice)['ok'], false);
check('too long is rejected', rmt_post_validate(['body' => str_repeat('a', RMT_POST_MAX + 1)], $alice)['ok'], false);
check('plain post is fine', rmt_post_validate(['body' => 'Lisbon in August is a mistake.'], $alice)['ok'], true);
$v = rmt_post_validate(['body' => 'About a city.', 'destination_id' => 10], $alice);
check('destination kept', (int) $v['data']['destination_id'], 10);
$v = rmt_post_validate(['body' => 'About a city.', 'destination_id' => 999], $alice);
check('unknown destination rejected', $v['ok'], false);

echo "\n-- community gate --\n";
$v = rmt_post_validate(['body' => 'Hello room.', 'collection_id' => 5], $alice);
check('a member may post in their community', $v['ok'], true);
$v = rmt_post_validate(['body' => 'Hello room.', 'collection_id' => 5], $bob);
check('a non-member may not', $v['ok'], false);
check('and the reason says so', str_contains($v['errors'][0], 'Join the community'), true);
$v = rmt_post_validate(['body' => 'Hello room.', 'collection_id' => 6], $alice);
check('a closed list is not a room, even for its owner', $v['ok'], false);
$pdo->exec("INSERT INTO collection_members (collection_id,user_id,role,status,joined_at) VALUES
    (5,2,'member','active','2026-01-02 00:00:00')");
check('joining opens the microphone', rmt_post_validate(['body' => 'Hello room.', 'collection_id' => 5], $bob)['ok'], true);

echo "\n-- create, read, edit, remove --\n";
$id = rmt_post_create(1, ['body' => 'Lisbon in August is a mistake. Go in October.', 'destination_id' => 10, 'collection_id' => null]);
check('created', $id > 0, true);
$p = rmt_post_get($id);
check('city joined in', (string) $p['dest_name'], 'Lisbon');
check('title is the first sentence', rmt_post_title($p), 'Lisbon in August is a mistake.');
check('author may edit', rmt_post_can_edit($p, $alice), true);
check('a stranger may not', rmt_post_can_edit($p, $bob), false);
check('a mod may', rmt_post_can_edit($p, $mod), true);
check('signed out may not', rmt_post_can_edit($p, null), false);
rmt_post_update($id, 'Edited body, still long enough.');
check('edit lands', (string) rmt_post_get($id)['body'], 'Edited body, still long enough.');

$cid = rmt_post_create(2, ['body' => 'Bob talking in the room.', 'destination_id' => null, 'collection_id' => 5]);
$cp = rmt_post_get($cid);
check('founder can remove from their own room', rmt_post_can_remove($cp, $alice), true);
check('founder cannot edit somebody else words', rmt_post_can_edit($cp, $alice), false);
check('a random member cannot remove it', rmt_post_can_remove($cp, ['id' => 9, 'role' => 'user']), false);
check('the author can remove their own', rmt_post_can_remove($cp, $bob), true);

echo "\n-- listing --\n";
check('recent finds both', count(rmt_posts_recent(50)), 2);
check('by city', count(rmt_posts_recent(50, 10)), 1);
check('by community', count(rmt_posts_recent(50, null, 5)), 1);
check('by author', count(rmt_posts_by_user(1)), 1);
rmt_post_delete($cid);
check('removed drops out of listings', count(rmt_posts_recent(50)), 1);
check('and out of the author list', count(rmt_posts_by_user(2)), 0);

echo "\n-- replies --\n";
check('no replies yet', rmt_post_reply_count($id), 0);
$pdo->exec("INSERT INTO comments (user_id,target_type,target_id,body,status,created_at)
            VALUES (2,'post',$id,'Agreed.','published','2026-01-03 00:00:00'),
                   (2,'post',$id,'Deleted one.','removed','2026-01-03 00:00:00')");
check('published replies counted, removed ones not', rmt_post_reply_count($id), 1);
check('the listing carries the count', (int) rmt_posts_recent(50)[0]['reply_count'], 1);

echo "\n-- indexability --\n";
$short = ['status' => 'published', 'body' => 'Anyone in Lisbon in June?', 'reply_count' => 0];
check('chatter stays out of the index', rmt_indexable('post', $short)['ok'], false);
check('and says why', rmt_indexable('post', $short)['reason'], 'noindex_thin');
$short['reply_count'] = 1;
check('a reply earns it a place', rmt_indexable('post', $short)['ok'], true);
$long = ['status' => 'published', 'body' => str_repeat('Real detail about a city. ', 20), 'reply_count' => 0];
check('so does saying something substantial', rmt_indexable('post', $long)['ok'], true);
$long['status'] = 'removed';
check('a removed post is never indexed', rmt_indexable('post', $long)['ok'], false);

echo $fail ? "\nFAILED: $fail\n" : "\nOK\n";
exit($fail ? 1 : 0);
