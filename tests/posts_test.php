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
$pdo->exec("CREATE TABLE likes (user_id INT, target_type TEXT, target_id INT)");
$pdo->exec("CREATE TABLE comments (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, target_type TEXT,
    target_id INT, body TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, destination_id INT, collection_id INT,
    body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'published', created_at TEXT NOT NULL, updated_at TEXT,
    image_url TEXT, image_key TEXT, image_w INT, image_h INT, repost_of INT, place_id INT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, destination_id INT, status TEXT)");
$pdo->exec("INSERT INTO places (id,slug,name,destination_id,status) VALUES (60,'anne-frank-house-amsterdam','Anne Frank House',10,'active')");

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

echo "\n-- the one picture --\n";
$pdo->exec("UPDATE posts SET image_url='/m/abc', image_key='abc', image_w=800, image_h=600 WHERE id=$id");
check('the listing carries the image', (string) rmt_posts_recent(50)[0]['image_url'], '/m/abc');
check('an empty file input is not an error',
      rmt_post_attach_image($id, ['error' => UPLOAD_ERR_NO_FILE], 1)['ok'], true);

echo "\n-- reposting --\n";
$oid = rmt_post_create(1, ['body' => 'The night bus is a scam, take the ferry.', 'destination_id' => 10, 'collection_id' => null]);
$r = rmt_post_repost(2, $oid);
check('a plain repost is created', $r['ok'], true);
check('the same one twice is refused', rmt_post_repost(2, $oid)['ok'], false);
check('but adding something is not', rmt_post_repost(2, $oid, 'This saved me forty euros.')['ok'], true);
check('your own post is not repostable', rmt_post_repost(1, $oid)['error'], 'That is already yours.');
check('the author sees the count', rmt_post_repost_count($oid), 2);
$rp = rmt_post_get((int) $r['id']);
check('it points at the original', (int) $rp['repost_of'], $oid);
check('it inherits the city', (int) $rp['destination_id'], 10);

$chain = rmt_post_repost(3, (int) $r['id']);
check('reposting a repost points at the original instead',
      (int) rmt_post_get((int) $chain['id'])['repost_of'], $oid);

$rows = rmt_posts_attach_originals([rmt_post_get((int) $r['id'])]);
check('the original is attached for rendering', (string) $rows[0]['original']['body'], 'The night bus is a scam, take the ferry.');
rmt_post_delete($oid);
$gone = rmt_posts_attach_originals([rmt_post_get((int) $r['id'])]);
check('a removed original leaves no quote block', $gone[0]['original'], null);
check('and cannot be reposted again', rmt_post_repost(4, $oid)['ok'], false);

echo "\n-- a bare repost is not a page for the index --\n";
check('nothing added means nothing new',
      rmt_indexable('post', ['status' => 'published', 'body' => '', 'repost_of' => 5, 'reply_count' => 2])['reason'],
      'noindex_duplicate');
check('a quote stands on its own',
      rmt_indexable('post', ['status' => 'published', 'repost_of' => 5, 'reply_count' => 1,
                             'body' => 'Adding a real point of my own here.'])['ok'], true);

echo "\n-- top of the week --\n";
$pdo->exec("DELETE FROM posts");
$pdo->exec("DELETE FROM comments");
$now = date('Y-m-d H:i:s');
$old = date('Y-m-d H:i:s', time() - 30 * 86400);
$pdo->exec("INSERT INTO posts (id,user_id,body,status,created_at) VALUES
    (100,1,'Quiet one.','published','$now'),
    (101,1,'Busy one.','published','$now'),
    (102,1,'Old but loved.','published','$old')");
$pdo->exec("INSERT INTO comments (user_id,target_type,target_id,body,status,created_at)
            VALUES (2,'post',101,'a','published','$now'),(3,'post',101,'b','published','$now')");
$pdo->exec("INSERT INTO likes (user_id,target_type,target_id) VALUES (2,'post',100)");
$pdo->exec("INSERT INTO comments (user_id,target_type,target_id,body,status,created_at)
            VALUES (2,'post',102,'a','published','$old'),(3,'post',102,'b','published','$old')");
$top = rmt_posts_top(10);
check('engagement wins over recency', (int) $top[0]['id'], 101);
check('a like still counts for something', (int) $top[1]['id'], 100);
check('outside the window is out', in_array(102, array_map(static fn($r) => (int) $r['id'], $top), true), false);
check('counts come back with the row', (int) $top[0]['reply_count'], 2);
$pdo->exec("INSERT INTO posts (id,user_id,body,status,created_at,repost_of) VALUES (103,2,'','published','$now',100)");
check('a repost outweighs a like', (int) rmt_posts_top(10)[0]['id'], 101);
check('and shows on the original', (int) rmt_posts_top(10)[1]['repost_count'], 1);

echo "\n-- about one place --\n";
$v = rmt_post_validate(['body' => 'How early do you need to book?', 'place_id' => 60], $alice);
check('a place is accepted', (int) $v['data']['place_id'], 60);
check('and fills in its city', (int) $v['data']['destination_id'], 10);
$v2 = rmt_post_validate(['body' => 'Asking about nothing.', 'place_id' => 999], $alice);
check('an unknown place is refused', $v2['ok'], false);
$pid = rmt_post_create(1, $v['data']);
check('the place comes back on the row', (int) rmt_post_get($pid)['place_id'], 60);
check('and its name for rendering', (string) rmt_post_get($pid)['place_name'], 'Anne Frank House');
check('the place page finds it', count(rmt_posts_for_place(60)), 1);
check('a different place finds nothing', rmt_posts_for_place(61), []);

echo "\n-- structured data --\n";
$qid = rmt_post_create(1, ['body' => 'How early do you need to book tickets?', 'destination_id' => 10,
                           'collection_id' => null, 'place_id' => 60]);
$q = rmt_post_get($qid);
$q['author'] = ['username' => 'alice'];
$unanswered = rmt_post_jsonld($q, [], 0);
check('an unanswered question is not a Q&A page', $unanswered['@type'], 'DiscussionForumPosting');
$answers = [['id' => 5, 'body' => 'A week ahead in summer.', 'created_at' => '2026-08-30 10:00:00', 'username' => 'bob']];
$answered = rmt_post_jsonld($q, $answers, 3);
check('answered, it is', $answered['@type'], 'QAPage');
check('with the question as the main entity', $answered['mainEntity']['@type'], 'Question');
check('the answer count is real', $answered['mainEntity']['answerCount'], 1);
check('likes become upvotes', $answered['mainEntity']['upvoteCount'], 3);
check('no answer is marked accepted', isset($answered['mainEntity']['acceptedAnswer']), false);
check('answers carry their author', $answered['mainEntity']['suggestedAnswer'][0]['author']['name'], '@bob');

$sid = rmt_post_create(1, ['body' => 'Ferries beat night buses.', 'destination_id' => 10,
                           'collection_id' => null, 'place_id' => null]);
$st = rmt_post_get($sid);
$st['author'] = ['username' => 'alice'];
$stmt = rmt_post_jsonld($st, $answers, 0);
check('a statement with replies stays a forum posting', $stmt['@type'], 'DiscussionForumPosting');
check('and carries its replies as comments', count($stmt['comment']), 1);

echo $fail ? "\nFAILED: $fail\n" : "\nOK\n";
exit($fail ? 1 : 0);
