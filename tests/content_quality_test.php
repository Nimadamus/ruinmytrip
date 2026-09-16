<?php
/**
 * Early spam controls on public posts and comments (app/content_quality.php).
 *
 *   php tests/content_quality_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
                      'db_driver' => 'sqlite', 'sqlite_path' => ':memory:'];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/content_quality.php';

$pdo = db();
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INT, body TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE comments (id INTEGER PRIMARY KEY, user_id INT, body TEXT, status TEXT, created_at TEXT)");

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "FAIL: $m\n"; } }

$old = ['id' => 1, 'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))];
$new = ['id' => 2, 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))];

// Real travel talk passes.
foreach ([
    'Anyone know if the Augustiner tent takes reservations on the first Saturday?',
    'We hooked up with a walking tour in Alfama, it was great',          // "hooked up" is not "hook up"
    'The tram 28 queue is brutal before 10am, walk to Graça instead.',
    'Cash only at most tascas. ATMs at the station charge a fee.',
    'I booked through https://www.oktoberfest.de and it was fine',
] as $t) ok(rmt_quality_check($t, $old) === null, "travel talk passes: $t");

ok(rmt_quality_check('see https://a.com https://b.com https://c.com', $old) !== null, 'three links is a link farm');
ok(rmt_quality_check('see https://a.com and https://b.com', $old) === null, 'two links are fine');
ok(rmt_quality_check('great guide https://a.com', $new) !== null, 'a brand new account cannot post links');
ok(rmt_quality_check('great guide, no link', $new) === null, 'a brand new account can post words');

ok(rmt_quality_check('Guaranteed returns with my bitcoin plan', $old) !== null, 'crypto pitch refused');
ok(rmt_quality_check('Whatsapp me for the best price', $old) !== null, 'off platform contact pitch refused');
ok(rmt_quality_check('Looking for a girlfriend while in Bangkok', $old) !== null, 'dating solicitation refused');
ok(rmt_quality_check('Use my promo code TRAVEL10 for hostels', $old) !== null, 'promo code refused');
ok(rmt_quality_check('Book here https://x.com/?ref=abc', $old) !== null, 'affiliate parameter refused');

$body = 'Selling tickets for the Hofbraeuhaus tent, message for details please';
$pdo->exec("INSERT INTO posts (user_id, body, status, created_at) VALUES (1, " . $pdo->quote($body) . ", 'published', '" . date('Y-m-d H:i:s') . "')");
ok(rmt_quality_check($body, $old) !== null, 'the same long text twice in a day is refused');
ok(rmt_quality_check($body, ['id' => 3, 'created_at' => $old['created_at']]) === null, 'somebody else may say the same words');
ok(rmt_quality_check('Thank you!', $old) === null, 'short replies are never duplicates');

$posts = (string) file_get_contents(BASE_PATH . '/app/posts.php');
$ctrl  = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok(str_contains($posts, "rmt_quality_check(\$body, \$user, 'post')"), 'posts go through the check');
ok(str_contains($ctrl, "rmt_quality_check(\$body, \$me, 'comment')"), 'comments go through the check');

echo "content_quality_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
