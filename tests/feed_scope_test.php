<?php
/**
 * Regression tests for what reaches a member's feed (app/feed_scope.php, rmt_activity_items).
 *
 * The feed answered one question, "who do you follow", while the site had a second follow gesture
 * that fed nothing: saving a city on its destination page. Somebody who saved Lisbon, Porto and
 * Naples had said exactly which conversations they wanted, was counted into a public "wants to
 * visit" number for it, and got an empty feed.
 *
 * What must hold:
 *   - a city you saved brings its activity into your feed, and a city you did not save does not.
 *   - following a person still works, and so does seeing your own posts.
 *   - dates are narrower, because they are the one thing with a visibility setting: a plan reaches
 *     a city follower only when it is public.
 *   - content with no destination (blog posts, lists) binds two placeholders, not three. Getting
 *     this wrong is a PDO argument-count error on every feed load, not a subtle miss.
 *
 * Runs against a throwaway in-memory SQLite DB. No network, no fixtures on disk.
 *
 *   php tests/feed_scope_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/feed_scope.php';

$pdo = db();
$pdo->exec('CREATE TABLE follows (follower_id INT NOT NULL, followee_id INT NOT NULL)');
$pdo->exec('CREATE TABLE saves (user_id INT NOT NULL, target_type TEXT NOT NULL, target_id INT NOT NULL)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec('CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, status TEXT)');
$pdo->exec('CREATE TABLE blog_posts (id INTEGER PRIMARY KEY, user_id INT, status TEXT)');

// Me = 1. I follow 2. I saved Lisbon (10) and not Porto (11).
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (10,'lisbon-portugal','Lisbon'),(11,'porto-portugal','Porto')");
$pdo->exec('INSERT INTO follows (follower_id,followee_id) VALUES (1,2)');
$pdo->exec("INSERT INTO saves (user_id,target_type,target_id) VALUES (1,'destination',10)");
// A save of something that is not a destination must never widen the feed.
$pdo->exec("INSERT INTO saves (user_id,target_type,target_id) VALUES (1,'review',11)");

// 1 = mine in a city I did not save, 2 = somebody I follow, 3 = a stranger in Lisbon which I saved,
// 4 = a stranger in Porto which I did not.
$pdo->exec("INSERT INTO reviews (id,user_id,destination_id,status) VALUES
              (1,1,11,'published'), (2,2,11,'published'), (3,3,10,'published'), (4,3,11,'published')");
$pdo->exec("INSERT INTO blog_posts (id,user_id,status) VALUES (1,2,'published'),(2,3,'published')");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$sql = rmt_feed_scope_sql('r.user_id', 'r.destination_id');
$rows = q_all("SELECT r.id FROM reviews r WHERE r.status='published' AND $sql ORDER BY r.id",
              rmt_feed_scope_args(1));
$ids = array_map(static fn(array $r) => (int) $r['id'], $rows);
ok('own, followed and saved-city reviews reach the feed', $ids === [1, 2, 3], 'got ' . json_encode($ids));
ok('a stranger in a city I did not save stays out', !in_array(4, $ids, true));

// The plain shape: two placeholders, and a stranger's blog post is not in it.
$plain = rmt_feed_scope_sql('user_id');
$rows = q_all("SELECT id FROM blog_posts WHERE status='published' AND $plain ORDER BY id",
              rmt_feed_scope_args(1, false));
$ids = array_map(static fn(array $r) => (int) $r['id'], $rows);
ok('no-destination content is follows-only', $ids === [1], 'got ' . json_encode($ids));
ok('plain scope binds two args', count(rmt_feed_scope_args(1, false)) === 2);
ok('destination scope binds three args', count(rmt_feed_scope_args(1)) === 3);

// Placeholder count and bind count must agree or every feed load is a PDO error.
ok('placeholders match args (with destination)', substr_count($sql, '?') === count(rmt_feed_scope_args(1)));
ok('placeholders match args (plain)', substr_count($plain, '?') === count(rmt_feed_scope_args(1, false)));

// Somebody who saved nothing and follows nobody sees only themselves.
$rows = q_all("SELECT r.id FROM reviews r WHERE r.status='published' AND $sql ORDER BY r.id",
              rmt_feed_scope_args(3));
$ids = array_map(static fn(array $r) => (int) $r['id'], $rows);
ok('a member with no follows and no saved cities sees their own', $ids === [3, 4], 'got ' . json_encode($ids));

// The cities named on the page are the saved destinations, and only those.
$cities = rmt_feed_followed_destinations(1);
ok('followed cities are the saved destinations', count($cities) === 1 && $cities[0]['slug'] === 'lisbon-portugal',
   json_encode($cities));
ok('a member who saved no city has none', rmt_feed_followed_destinations(2) === []);

// The talk query used to be built by str_replace('user_id', 'p.user_id') on the plain condition.
// The scope subquery says "WHERE user_id = ?" inside it, so that patch would now corrupt it.
ok('scope is alias-safe, not string-patched', str_contains(rmt_feed_scope_sql('p.user_id', 'p.destination_id'), 'p.user_id ='));
$controllers = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('the feed no longer str_replaces a column name', !str_contains($controllers, "str_replace('user_id', 'p.user_id'"));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
