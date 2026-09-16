<?php
/**
 * "Hide this": one member stops seeing one item, and nobody else is affected.
 *
 *   php tests/hide_content_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require_once BASE_PATH . '/app/helpers.php';
require_once BASE_PATH . '/app/messages.php';

$pdo = db();
$pdo->exec((string) file_get_contents(BASE_PATH . '/database/migrations/097_hidden_content.sqlite.sql'));

$pass = 0; $fail = 0;
function ok(string $what, bool $c): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $what\n"; } }

$feed = [['kind' => 'post', 'id' => 5], ['kind' => 'going', 'id' => 9], ['kind' => 'review', 'id' => 5], ['kind' => 'trip', 'id' => 2]];
ok('nothing hidden keeps everything', count(rmt_without_hidden($feed, 1, '', 'id', 'kind')) === 4);
ok('a signed out reader keeps everything', count(rmt_without_hidden($feed, null, '', 'id', 'kind')) === 4);

rmt_hide_content(1, 'post', 5);
rmt_hide_content(1, 'post', 5);   // twice is still one row
ok('hiding twice is one row', (int) q_one('SELECT COUNT(*) n FROM hidden_content')['n'] === 1);
$left = rmt_without_hidden($feed, 1, '', 'id', 'kind');
ok('the hidden post is gone and a review with the same id is not', array_column($left, 'kind') === ['going', 'review', 'trip']);

rmt_hide_content(1, 'trip', 9);
ok('a going row is hidden by hiding its trip', array_column(rmt_without_hidden($feed, 1, '', 'id', 'kind'), 'kind') === ['review', 'trip']);
ok('another member sees everything', count(rmt_without_hidden($feed, 2, '', 'id', 'kind')) === 4);
ok('a fixed type list works', rmt_without_hidden([['id' => 5], ['id' => 6]], 1, 'post') === [['id' => 6]]);

$ctrl  = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
$index = (string) file_get_contents(BASE_PATH . '/public/index.php');
$posts = (string) file_get_contents(BASE_PATH . '/app/posts.php');
ok('the route exists and is POST', str_contains($index, "['POST', '#^/hide$#'"));
ok('the action checks login and csrf', (bool) preg_match('/function hide_action.*?require_login\(\); csrf_check\(\);/s', $ctrl));
ok('your own content cannot be hidden', str_contains($ctrl, "rmt_content_owner_id(\$tt, \$tid) !== (int) \$me['id']"));
ok('the feed drops hidden items', str_contains($ctrl, "rmt_without_hidden(\$items, \$uid, '', 'id', 'kind')"));
ok('post lists drop hidden posts', str_contains($posts, "rmt_without_hidden(\$rows, (int) \$viewer['id'], 'post')"));

echo "hide_content_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
