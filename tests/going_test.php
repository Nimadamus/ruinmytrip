<?php
/**
 * Who's going: destination + dates only, visibility enforced, one plan per city.
 *
 *   php tests/going_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/going.php';

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT, PRIMARY KEY (follower_id, followee_id))');
$pdo->exec("CREATE TABLE going (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, destination_id INT NOT NULL,
    date_from TEXT, date_to TEXT, visibility TEXT NOT NULL DEFAULT 'public', created_at TEXT NOT NULL
)");
$pdo->exec('CREATE UNIQUE INDEX idx_going_user_dest ON going (user_id, destination_id)');
$pdo->exec("INSERT INTO users (id,username,status) VALUES (1,'alice','active'),(2,'bob','active'),(3,'cara','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (10,'lisbon-portugal','Lisbon')");
$pdo->exec("INSERT INTO follows (follower_id, followee_id) VALUES (2,1)");

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-64s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

echo "-- validate --\n";
$v = rmt_going_validate(['destination_id' => 10, 'date_from' => '1999-01-01', 'date_to' => '1999-01-05', 'visibility' => 'public']);
check('past trip rejected', $v['ok'], false);
$v = rmt_going_validate(['destination_id' => 10, 'date_from' => '2099-06-10', 'date_to' => '2099-06-01', 'visibility' => 'public']);
check('end before start rejected', $v['ok'], false);
$v = rmt_going_validate(['destination_id' => 10, 'date_from' => '2099-06-01', 'date_to' => '2099-06-10', 'visibility' => 'public']);
check('future range ok', $v['ok'], true);
$v = rmt_going_validate(['destination_id' => 99, 'date_from' => '2099-06-01', 'date_to' => '2099-06-10']);
check('missing dest rejected', $v['ok'], false);

echo "\n-- upsert one per dest --\n";
$id1 = rmt_going_upsert(1, ['destination_id' => 10, 'date_from' => '2099-06-01', 'date_to' => '2099-06-10', 'visibility' => 'public']);
$id2 = rmt_going_upsert(1, ['destination_id' => 10, 'date_from' => '2099-07-01', 'date_to' => '2099-07-08', 'visibility' => 'followers']);
check('second save is an update', $id1 === $id2 && $id1 > 0, true);
$row = rmt_going_for_user_dest(1, 10);
check('dates replaced', $row['date_from'] === '2099-07-01' && $row['visibility'] === 'followers', true);

echo "\n-- visibility --\n";
$alice = ['id' => 1];
$bob = ['id' => 2];
$cara = ['id' => 3];
$forAlice = rmt_going_list_for_destination(10, $alice);
$forBob = rmt_going_list_for_destination(10, $bob);
$forCara = rmt_going_list_for_destination(10, $cara);
$forAnon = rmt_going_list_for_destination(10, null);
check('owner sees own followers plan', count($forAlice), 1);
check('follower sees followers plan', count($forBob), 1);
check('stranger does not', count($forCara), 0);
check('logged-out does not', count($forAnon), 0);

rmt_going_upsert(1, ['destination_id' => 10, 'date_from' => '2099-07-01', 'date_to' => '2099-07-08', 'visibility' => 'public']);
check('public visible logged-out', count(rmt_going_list_for_destination(10, null)), 1);

rmt_going_delete(1, 10);
check('deleted', rmt_going_for_user_dest(1, 10), null);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
