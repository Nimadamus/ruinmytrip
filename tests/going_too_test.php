<?php
/**
 * "I am going too" (rmt_plan_join, POST /trip/{id}/going-too).
 *
 * The site could tell you a stranger's trip overlapped yours and then left you to type the same
 * dates into a different form somewhere else. This is that form, pressed once: it copies the city
 * and the dates you are looking at into a plan of your own and tells the person whose trip it was.
 *
 * It is deliberately NOT a shared trip. Their page stays theirs and yours is yours, because a trip
 * is the one thing here that ends with two strangers in the same place and nobody should end up on
 * somebody else's page without saying so themselves.
 *
 *   php tests/going_too_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/plans.php';

function dest_by_id(int $id): ?array { return q_one('SELECT * FROM destinations WHERE id=?', [$id]); }

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT)');
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
              title TEXT NOT NULL DEFAULT '', slug TEXT NOT NULL DEFAULT '', body TEXT,
              status TEXT NOT NULL DEFAULT 'published', visibility TEXT NOT NULL DEFAULT 'public',
              date_from TEXT, date_to TEXT, visited_on TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT,
              actor_id INT, target_type TEXT, target_id INT, created_at TEXT)');
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (1,'prague-czechia','Prague')");
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,body,status,visibility,date_from,date_to,created_at)
            VALUES (10,1,1,'Prague in October','p-10','words','published','public','2027-10-02','2027-10-09',datetime('now')),
                   (11,1,1,'An undated story','p-11','words','published','public',NULL,NULL,datetime('now'))");

$ana = ['id' => 1];
$leo = ['id' => 2];
$get = static fn(int $id) => q_one('SELECT * FROM trips WHERE id=?', [$id]);

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$r = rmt_plan_join($get(10), $leo);
ok('pressing it makes a plan of your own', $r['ok'] && $r['trip_id'] > 0, json_encode($r));
$mine = q_one('SELECT * FROM trips WHERE id=?', [$r['trip_id']]);
ok('it is your trip, not theirs', (int) $mine['user_id'] === 2);
ok('the city is copied', (int) $mine['destination_id'] === 1);
ok('the dates are copied', $mine['date_from'] === '2027-10-02' && $mine['date_to'] === '2027-10-09',
   $mine['date_from'] . '..' . $mine['date_to']);
ok('it is public, because being findable is the point', $mine['visibility'] === 'public');
ok('their trip is untouched', (int) $get(10)['user_id'] === 1 && $get(10)['visibility'] === 'public');

$n = q_all("SELECT * FROM notifications WHERE type='going_too'");
ok('the person whose trip it was is told', count($n) === 1 && (int) $n[0]['user_id'] === 1
    && (int) $n[0]['actor_id'] === 2 && (int) $n[0]['target_id'] === 10, json_encode($n));

// Pressing twice is one plan and one notification: it is a statement, not a counter.
$again = rmt_plan_join($get(10), $leo);
ok('pressing again moves the same plan rather than adding one', $again['ok']
    && (int) $again['trip_id'] === (int) $r['trip_id']);
ok('and does not notify twice', count(q_all("SELECT * FROM notifications WHERE type='going_too'")) === 1);
ok('there is still one plan for that city', count(q_all('SELECT * FROM trips WHERE user_id=2')) === 1);

// The refusals.
$own = rmt_plan_join($get(10), $ana);
ok('you cannot go too on your own trip', !$own['ok'], $own['error']);
$undated = rmt_plan_join($get(11), $leo);
ok('a trip with no dates has nothing to copy', !$undated['ok'], $undated['error']);

/* The other half of pressing it: when they post an update from that trip, the people who will be
   there on the same days hear about it. Without this, saying "I am going too" bought you nothing
   but a row in a table. */
$pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, status TEXT)");
$pdo->exec("INSERT OR IGNORE INTO users (id,status) VALUES (1,'active'),(2,'active')");
$pdo->exec("CREATE TABLE IF NOT EXISTS blocks (blocker_id INT, blocked_id INT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY, user_id INT, trip_id INT,
              destination_id INT, status TEXT)");
$pdo->exec("INSERT INTO posts (id,user_id,trip_id,destination_id,status) VALUES (500,1,10,1,'published')");
require_once BASE_PATH . '/app/matching.php';
$told = rmt_trip_update_notify($get(10), 500, 1);
ok('the traveler on the same days hears about the update', $told === 1, (string) $told);
ok('the notification points at the update',
   (bool) q_one("SELECT 1 FROM notifications WHERE type='trip_update' AND target_type='post' AND target_id=500 AND user_id=2"));
ok('the same update never notifies twice', rmt_trip_update_notify($get(10), 500, 1) === 0);
ok('an undated trip has nobody to tell', rmt_trip_update_notify($get(11), 501, 1) === 0);

// Wiring: the route, the button, and both renderers.
$routes = (string) file_get_contents(BASE_PATH . '/public/index.php');
ok('the action is routed', str_contains($routes, "going-too$#', 'trip_going_too'"));
$controllers = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('the controller checks it may be read at all', str_contains($controllers, 'rmt_trip_visible_to($t, $me)'));
ok('the controller honours a block', str_contains($controllers, "rmt_blocked_from((int) \$me['id'], 'trip'"));
$view = (string) file_get_contents(BASE_PATH . '/views/trip_show.php');
ok('the button is only on somebody else\'s upcoming trip',
   str_contains($view, "!\$isOwner && in_array(\$phase, ['upcoming', 'current'], true)"));
ok('a logged-out reader is asked to join instead', str_contains($view, 'Join to say you are going too'));
$notif = (string) file_get_contents(BASE_PATH . '/views/notifications.php');
ok('the notification renders', str_contains($notif, "'going_too'"));
$push = (string) file_get_contents(BASE_PATH . '/app/push.php');
ok('push renders it too', str_contains($push, "case 'going_too':"));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
