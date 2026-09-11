<?php
/**
 * Trip updates (migration 072, rmt_post_validate trip_id, rmt_posts_for_trip).
 *
 * A trip is the container this product is built around: the dates first, then what happens while
 * you are there, then the story afterwards. The middle part had nowhere to live, so a trip page was
 * a title and a body written once and never touched again.
 *
 * An update is a post rather than a new object, because posts already carry photos, hashtags,
 * likes, comments, moderation and a place in the feed. What has to hold is whose trip it is:
 *
 *   - you may only post updates to your own trip. Posting into somebody else's trip page would put
 *     words on their story that they cannot edit.
 *   - an update inherits the trip's city, so it is findable from the city page where other
 *     travelers are, without the author having to say it twice.
 *   - a trip reads forwards: updates come back oldest first, unlike every feed on the site.
 *
 *   php tests/trip_updates_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/posts.php';

// posts.php leans on these two for communities and reposts; neither is exercised here.
function rmt_community_role(int $cid, int $uid) { return null; }

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INT, avatar_url TEXT, display_name TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec('CREATE TABLE places (id INTEGER PRIMARY KEY, destination_id INT, status TEXT, slug TEXT, name TEXT)');
$pdo->exec('CREATE TABLE collections (id INTEGER PRIMARY KEY, status TEXT, slug TEXT, title TEXT)');
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, status TEXT, date_from TEXT, date_to TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS trip_members (trip_id INT, user_id INT, role TEXT, state TEXT, invited_by INT, created_at TEXT, decided_at TEXT, PRIMARY KEY (trip_id, user_id))");
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
              collection_id INT, place_id INT, trip_id INT, body TEXT, status TEXT,
              image_url TEXT, image_key TEXT, image_w INT, image_h INT, repost_of INT,
              created_at TEXT, updated_at TEXT)");
$pdo->exec('CREATE TABLE comments (id INTEGER PRIMARY KEY, target_type TEXT, target_id INT, status TEXT)');
$pdo->exec("INSERT INTO users (id,username,status) VALUES (1,'ana','active'),(2,'leo','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (7,'cape-town-south-africa','Cape Town')");
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,status,date_from,date_to) VALUES
              (10,1,7,'Cape Town','cape-town-10','published','2027-08-01','2027-08-09'),
              (11,2,7,'Leo goes too','leo-11','published','2027-08-03','2027-08-06'),
              (12,1,7,'A draft','draft-12','draft','2027-09-01','2027-09-05')");

$ana = ['id' => 1, 'username' => 'ana'];
$leo = ['id' => 2, 'username' => 'leo'];

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$v = rmt_post_validate(['body' => 'Booked the flights today, nine days.', 'trip_id' => 10], $ana);
ok('an update on your own trip is accepted', $v['ok'], json_encode($v['errors']));
ok('it is attached to the trip', $v['data']['trip_id'] === 10);
ok('it inherits the city', $v['data']['destination_id'] === 7);

$v2 = rmt_post_validate(['body' => 'Sneaking onto your trip page.', 'trip_id' => 11], $ana);
ok('you cannot post to somebody else\'s trip', !$v2['ok'], json_encode($v2['errors']));
ok('and the trip is not attached anyway', ($v2['data']['trip_id'] ?? null) === null);

$v3 = rmt_post_validate(['body' => 'Posting to a draft trip.', 'trip_id' => 12], $ana);
ok('an unpublished trip is not a trip yet', !$v3['ok'], json_encode($v3['errors']));

$v4 = rmt_post_validate(['body' => 'Posting to nothing at all.', 'trip_id' => 999], $ana);
ok('a trip that does not exist is refused', !$v4['ok']);

$v5 = rmt_post_validate(['body' => 'Just a normal post about the city.', 'destination_id' => 7], $ana);
ok('an ordinary post still works and carries no trip', $v5['ok'] && $v5['data']['trip_id'] === null);

// Writing and reading them back.
$id1 = rmt_post_create(1, rmt_post_validate(['body' => 'Landed, it is raining.', 'trip_id' => 10], $ana)['data']);
sleep(1);
$id2 = rmt_post_create(1, rmt_post_validate(['body' => 'Table Mountain, finally clear.', 'trip_id' => 10], $ana)['data']);
$id3 = rmt_post_create(1, rmt_post_validate(['body' => 'Unrelated thought.', 'destination_id' => 7], $ana)['data']);

$ups = rmt_posts_for_trip(10);
ok('only the trip\'s own updates come back', count($ups) === 2, (string) count($ups));
ok('a trip reads forwards, oldest first',
   (int) $ups[0]['id'] === $id1 && (int) $ups[1]['id'] === $id2,
   json_encode(array_column($ups, 'id')));
ok('a post about the city is not an update on the trip',
   !in_array($id3, array_map('intval', array_column($ups, 'id')), true));

q_run("UPDATE posts SET status='removed' WHERE id=?", [$id2]);
ok('a removed update leaves the trip', count(rmt_posts_for_trip(10)) === 1);
ok('a trip with nothing posted returns nothing', rmt_posts_for_trip(11) === []);
ok('no trip is not a trip', rmt_posts_for_trip(0) === []);

// Both drivers must have the column, and neither migration may rewrite the posts table.
foreach (['pgsql', 'sqlite'] as $driver) {
    $sql = (string) file_get_contents(BASE_PATH . "/database/migrations/072_trip_updates.$driver.sql");
    ok("$driver migration adds trip_id", str_contains($sql, 'ADD COLUMN') && str_contains($sql, 'trip_id'));
    ok("$driver migration indexes it", str_contains($sql, 'idx_posts_trip'));
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
