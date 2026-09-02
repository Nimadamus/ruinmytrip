<?php
/**
 * Polls: validation, one vote per member, changing a vote, closing, batch loading.
 *
 *   php tests/polls_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/polls.php';

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT, role TEXT)');
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, body TEXT, status TEXT DEFAULT 'published', created_at TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/067_post_polls.sqlite.sql'));
$pdo->exec("INSERT INTO users VALUES (1,'ana','active','user'),(2,'ben','active','user'),(3,'cy','active','user')");
$pdo->exec("INSERT INTO posts (user_id, body, created_at) VALUES (1,'Lisbon or Porto?', '2026-01-01 00:00:00'),(1,'no poll here','2026-01-01 00:00:00')");

$pass = 0; $fail = 0;
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $msg\n"; } }

// validation
$v = rmt_poll_validate(['poll' => ['', '', '', '']]);
ok($v['ok'] && $v['options'] === [], 'blank poll is no poll');
$v = rmt_poll_validate(['poll' => ['Lisbon']]);
ok(!$v['ok'], 'one option rejected');
$v = rmt_poll_validate(['poll' => ['Lisbon', 'lisbon']]);
ok(!$v['ok'], 'duplicate options rejected case-insensitively');
$v = rmt_poll_validate(['poll' => ['a','b','c','d','e']]);
ok(!$v['ok'], 'five options rejected');
$v = rmt_poll_validate(['poll' => [str_repeat('x', 61), 'b']]);
ok(!$v['ok'], 'long label rejected');
$v = rmt_poll_validate(['poll' => [' Lisbon ', '', 'Porto  ', ''], 'poll_days' => '99']);
ok($v['ok'] && $v['options'] === ['Lisbon', 'Porto'] && $v['days'] === 3, 'blanks dropped, trimmed, bad days falls back to 3');

// create + load
rmt_poll_create(1, ['Lisbon', 'Porto'], 3);
$poll = rmt_poll_for_post(1, 2);
ok($poll !== null && count($poll['options']) === 2 && $poll['total'] === 0 && !$poll['closed'] && $poll['my_option_id'] === null, 'fresh poll loads with zero votes');
ok(rmt_poll_for_post(2) === null, 'post without poll returns null');
$opts = $poll['options'];

// vote, one per member, movable
ok(rmt_poll_vote(1, $opts[0]['id'], 2)['ok'], 'ben votes Lisbon');
ok(rmt_poll_vote(1, $opts[0]['id'], 3)['ok'], 'cy votes Lisbon');
ok(rmt_poll_vote(1, $opts[1]['id'], 2)['ok'], 'ben changes to Porto');
$poll = rmt_poll_for_post(1, 2);
ok($poll['total'] === 2, 'two members, two votes, not three');
ok($poll['my_option_id'] === $opts[1]['id'], 'ben now shows Porto');
ok($poll['options'][0]['pct'] === 50 && $poll['options'][1]['pct'] === 50, 'percentages from live counts');
ok(!rmt_poll_vote(1, 999, 2)['ok'], 'foreign option refused');
ok(!rmt_poll_vote(2, $opts[0]['id'], 2)['ok'], 'voting on a post with no poll refused');
ok(!rmt_poll_vote(1, $opts[0]['id'], 1)['ok'] === false, 'author can vote too');

// batch load
$polls = rmt_polls_for_posts([1, 2, 1], 3);
ok(count($polls) === 1 && isset($polls[1]) && $polls[1]['my_option_id'] === $opts[0]['id'], 'batch keyed by post, my vote attached');
ok(rmt_polls_for_posts([], 3) === [], 'empty batch');

// closing
$pdo->exec("UPDATE post_polls SET closes_at='2000-01-01 00:00:00' WHERE post_id=1");
$poll = rmt_poll_for_post(1, 2);
ok($poll['closed'] && rmt_poll_closes_label($poll) === 'Final', 'past closes_at means closed');
ok(!rmt_poll_vote(1, $opts[0]['id'], 2)['ok'], 'closed poll refuses votes');
ok(str_starts_with(rmt_poll_closes_label(['closed' => false, 'closes_at' => date('Y-m-d H:i:s', time() + 2 * 86400)]), 'Closes in 2 days'), 'closes label');

echo "polls_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
