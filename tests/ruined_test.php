<?php
/**
 * The "what ruined it" wall: only published reviews with a line, only active authors, city filter,
 * newest first, count matches the list.
 *
 *   php tests/ruined_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/reviews.php';

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT, role TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INT, avatar_url TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec('CREATE TABLE places (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, place_id INT, subject_name TEXT, rating INT, title TEXT, body TEXT, what_ruined TEXT, status TEXT, created_at TEXT, slug TEXT)");
$pdo->exec("INSERT INTO users VALUES (1,'ana','active','user'),(2,'gone','suspended','user'),(3,'ed','active','editorial')");
$pdo->exec("INSERT INTO destinations VALUES (1,'lisbon-portugal','Lisbon'),(2,'porto-portugal','Porto')");
$pdo->exec("INSERT INTO places VALUES (1,'Tram 28','tram-28-lisbon')");
$pdo->exec("INSERT INTO reviews VALUES
 (1,1,1,1,'Tram 28',2,'t','b','Ninety minute queue at 9am','published','2026-01-03','a'),
 (2,1,2,NULL,'Hotel X',3,'t','b','','published','2026-01-04','b'),
 (3,1,2,NULL,'Hotel Y',1,'t','b','No hot water for three days','published','2026-01-05','c'),
 (4,2,1,NULL,'Hotel Z',1,'t','b','Suspended author line','published','2026-01-06','d'),
 (5,1,1,NULL,'Hotel W',4,'t','b','Draft line','draft','2026-01-07','e'),
 (6,3,1,NULL,'Museum',5,'t','b','   ','published','2026-01-08','f'),
 (7,3,1,NULL,'Museum',5,'t','b','Editorial line','published','2026-01-09','g')");

$pass = 0; $fail = 0;
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $msg\n"; } }

$rows = rmt_reviews_ruined(50);
ok(array_column($rows, 'id') === [7, 3, 1], 'published + non-blank line + active author, newest first');
ok($rows[2]['place_name'] === 'Tram 28' && $rows[2]['dest_name'] === 'Lisbon' && $rows[2]['username'] === 'ana', 'joins place, city, author');
ok(rmt_reviews_ruined_count() === 3, 'count matches the list');
ok(array_column(rmt_reviews_ruined(50, 2), 'id') === [3], 'city filter');
ok(rmt_reviews_ruined_count(2) === 1, 'city count');
ok(array_column(rmt_reviews_ruined(1), 'id') === [7], 'limit');

echo "ruined_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
