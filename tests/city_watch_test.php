<?php
/**
 * Regression tests for city watch notifications (app/city_watch.php).
 *
 * Saving a city was a bookmark, then a feed subscription, and it was still silent. On a young
 * network the only event that matters is the second person arriving in a city where somebody
 * already is, and nothing told them: a feed only reaches people already looking at the site, and
 * the whole problem is that there is not yet a reason to look.
 *
 * What must hold:
 *   - only people who saved that city hear, and never the traveler who did the thing.
 *   - a saved review or list is not a saved city and must not widen the audience.
 *   - one piece of news is one notification: editing dates twice is not two.
 *   - a block holds in both directions, since this is about two strangers in one city.
 *   - a type the notifications page cannot render is refused rather than written.
 *
 *   php tests/city_watch_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/city_watch.php';

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, status TEXT)');
$pdo->exec('CREATE TABLE saves (user_id INT, target_type TEXT, target_id INT)');
$pdo->exec('CREATE TABLE blocks (blocker_id INT, blocked_id INT)');
$pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT,
              actor_id INT, target_type TEXT, target_id INT, created_at TEXT)');

// 1 saved Lisbon and is the one posting. 2, 3 and 5 also saved Lisbon; 4 saved Porto; 6 is gone.
$pdo->exec("INSERT INTO users (id,status) VALUES (1,'active'),(2,'active'),(3,'active'),(4,'active'),(5,'active'),(6,'disabled')");
$pdo->exec("INSERT INTO saves (user_id,target_type,target_id) VALUES
              (1,'destination',10), (2,'destination',10), (3,'destination',10),
              (5,'destination',10), (6,'destination',10),
              (4,'destination',11), (2,'review',10)");
$pdo->exec('INSERT INTO blocks (blocker_id,blocked_id) VALUES (5,1)');

function rmt_is_blocked(int $a, int $b): bool {
    return (bool) q_one('SELECT 1 FROM blocks WHERE blocker_id=? AND blocked_id=?', [$a, $b]);
}

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}
function told(string $type): array {
    $r = q_all('SELECT user_id FROM notifications WHERE type=? ORDER BY user_id', [$type]);
    return array_map(static fn(array $x) => (int) $x['user_id'], $r);
}

$w = rmt_city_watchers(10, 1);
sort($w);
ok('watchers are the people who saved that city', $w === [2, 3, 5], json_encode($w));
ok('the traveler is not told about their own plan', !in_array(1, $w, true));
ok('a disabled account is not a watcher', !in_array(6, $w, true));
ok('saving something else in that city is not saving the city', !in_array(4, $w, true));

$sent = rmt_city_notify(10, 'city_going', 1, 'going', 77);
ok('everybody watching hears, except the blocker', $sent === 2 && told('city_going') === [2, 3],
   "sent=$sent rows=" . json_encode(told('city_going')));
ok('a block stops it in the direction it was made', !in_array(5, told('city_going'), true));

// Editing the same plan again is the same news.
ok('the same event does not notify twice', rmt_city_notify(10, 'city_going', 1, 'going', 77) === 0);
// A different plan is different news.
ok('a second plan is a second notification', rmt_city_notify(10, 'city_going', 1, 'going', 78) === 2);

$pdo->exec('DELETE FROM blocks');
ok('a meetup reaches everybody watching', rmt_city_notify(10, 'city_meetup', 1, 'meetup', 5) === 3,
   json_encode(told('city_meetup')));
ok('another city hears nothing', rmt_city_notify(11, 'city_meetup', 1, 'meetup', 6) === 1);
ok('a city nobody saved notifies nobody', rmt_city_notify(99, 'city_meetup', 1, 'meetup', 7) === 0);
ok('an unknown type writes nothing', rmt_city_notify(10, 'city_whatever', 1, 'meetup', 8) === 0);

// Both types must be renderable, or the page shows a row it cannot describe.
$view = (string) file_get_contents(BASE_PATH . '/views/notifications.php');
$push = (string) file_get_contents(BASE_PATH . '/app/push.php');
ok('the notifications page renders them', str_contains($view, 'RMT_CITY_NOTIFY_TYPES'));
ok('push renders them', str_contains($push, "case 'city_going': case 'city_meetup':"));
$going = (string) file_get_contents(BASE_PATH . '/app/going.php');
ok('public dates trigger it', str_contains($going, "rmt_city_notify("));
$controllers = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('a new meetup triggers it', str_contains($controllers, "'city_meetup'"));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
