<?php
/**
 * Travel buddies (app/buddies.php, migration 099).
 *
 * A buddy post is somebody's cruise or trip asking for company. The rules with teeth: dates are
 * real and in the future, a hand can be raised once and taken back, only the poster decides, a
 * block holds, and acceptance (and nothing short of it) is what opens a private message.
 *
 *   php tests/travel_buddies_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/buddies.php';

$blocked = [];
function rmt_is_blocked(int $a, int $b): bool { global $blocked; return isset($blocked["$a:$b"]) || isset($blocked["$b:$a"]); }

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT NOT NULL DEFAULT 'active')");
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT,
              actor_id INT, target_type TEXT, target_id INT, created_at TEXT)');
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/099_travel_buddies.sqlite.sql'));
$pdo->exec("INSERT INTO users (id,username) VALUES (1,'ana'),(2,'leo'),(3,'kim')");
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (1,'miami','Miami')");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$today = '2026-09-16';
$good = ['trip_type' => 'cruise', 'title' => 'Cabin mate for a Caribbean cruise', 'where_text' => 'Miami to Cozumel',
         'destination_id' => '1', 'date_from' => '2026-12-01', 'date_to' => '2026-12-08', 'spots' => '1',
         'budget' => 'mid', 'description' => 'Booked an inside cabin and would like someone to split it with.', 'safety_ack' => '1'];

$v = rmt_buddy_validate($good, $today);
ok('a complete cruise post validates', $v['ok'], json_encode($v['errors']));
ok('an unknown destination is dropped, not trusted', rmt_buddy_validate(['destination_id' => '99'] + $good, $today)['data']['destination_id'] === null);
ok('a trip in the past is refused', !rmt_buddy_validate(['date_from' => '2026-01-01'] + $good, $today)['ok']);
ok('ending before it starts is refused', !rmt_buddy_validate(['date_to' => '2026-11-01'] + $good, $today)['ok']);
ok('an unknown trip type is refused', !rmt_buddy_validate(['trip_type' => 'dating'] + $good, $today)['ok']);
$noAck = $good; unset($noAck['safety_ack']);
ok('the safety terms are required', !rmt_buddy_validate($noAck, $today)['ok']);

$d = $v['data'];
$id = (int) q_run("INSERT INTO buddy_posts (user_id,trip_type,title,where_text,destination_id,date_from,date_to,flexible,spots,budget,description,status,created_at)
                   VALUES (1,?,?,?,?,?,?,?,?,?,?, 'open', ?)",
    [$d['trip_type'], $d['title'], $d['where_text'], $d['destination_id'], $d['date_from'], $d['date_to'], $d['flexible'], $d['spots'], $d['budget'], $d['description'], date('Y-m-d H:i:s')]);
$post = rmt_buddy_get($id);

ok('an open future post is listed', count(rmt_buddy_posts_open()) === 1);
ok('filtering by cruise finds it', count(rmt_buddy_posts_open('cruise')) === 1);
ok('filtering by road trip does not', count(rmt_buddy_posts_open('road_trip')) === 0);

ok('the poster cannot put a hand up on their own post', !rmt_buddy_toggle_interest($post, 1)['ok']);
$r = rmt_buddy_toggle_interest($post, 2, 'Hi, I am in for December');
ok('someone else can', $r['ok'] && $r['action'] === 'interested');
ok('the poster is notified', (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=1 AND type='buddy_interest'")['n'] === 1);
ok('interest alone does not open messages', !rmt_buddy_mutual(1, 2));

ok('only the poster can accept', !rmt_buddy_decide($post, 3, 2, 'accepted'));
ok('the poster accepts', rmt_buddy_decide($post, 1, 2, 'accepted'));
ok('acceptance opens messages both ways', rmt_buddy_mutual(1, 2) && rmt_buddy_mutual(2, 1));
ok('it does not open them for anyone else', !rmt_buddy_mutual(1, 3) && !rmt_buddy_mutual(2, 3));
ok('the accepted person is told', (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=2 AND type='buddy_accepted'")['n'] === 1);
ok('accepting twice is a no op', !rmt_buddy_decide($post, 1, 2, 'accepted'));

$blocked['3:1'] = true;
ok('a block holds', !rmt_buddy_toggle_interest($post, 3)['ok']);
unset($blocked['3:1']);

$r = rmt_buddy_toggle_interest($post, 2);
ok('withdrawing removes the hand', $r['action'] === 'withdrawn' && !rmt_buddy_mutual(1, 2));

q_run("UPDATE buddy_posts SET status='closed' WHERE id=?", [$id]);
ok('a closed post takes no new interest', !rmt_buddy_toggle_interest(rmt_buddy_get($id), 3)['ok']);
ok('and is not listed', count(rmt_buddy_posts_open()) === 0);

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
