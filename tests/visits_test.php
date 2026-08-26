<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/editorial.php';
require BASE_PATH . '/app/visits.php';

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT, role TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT)');
$pdo->exec("CREATE TABLE visits (user_id INT NOT NULL, destination_id INT NOT NULL, created_at TEXT NOT NULL, PRIMARY KEY (user_id, destination_id))");
$pdo->exec("CREATE TABLE saves (user_id INT, target_type TEXT, target_id INT, created_at TEXT)");
$pdo->exec("INSERT INTO users VALUES (1,'alice','active','user'),(2,'ed','active','editorial')");
$pdo->exec("INSERT INTO destinations VALUES (10,'lisbon-portugal','Lisbon','Portugal')");

$fail = 0;
function check(string $n, $g, $e): void {
    global $fail;
    $ok = $g === $e;
    if (!$ok) $fail++;
    printf("  [%s] %s\n", $ok ? 'PASS' : 'FAIL', $n);
}

$on = rmt_visit_toggle(1, 10);
check('first stamp is on', $on, true);
check('count 1', rmt_visit_count(10), 1);
$on = rmt_visit_toggle(1, 10);
check('toggle off', $on, false);
check('count 0', rmt_visit_count(10), 0);
rmt_visit_toggle(1, 10);
rmt_visit_toggle(2, 10);
check('editorial visit excluded from count', rmt_visit_count(10), 1);
$list = rmt_visits_for_destination(10);
check('list excludes editorial', count($list) === 1 && $list[0]['username'] === 'alice', true);

echo $fail ? "$fail FAIL(S)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
