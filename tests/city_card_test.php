<?php
/**
 * Regression tests for the city share card (rmt_card_spec 'city').
 *
 * Every link this site posts anywhere points at a city's people page, and until now those links
 * shared with the site's default image: the one fact the link was about, the city, was not on the
 * picture. The counts on the card are the reason somebody clicks, so they have to be live and they
 * have to be honest -- a pill reading "0 going" is an advert for an empty room.
 *
 *   php tests/city_card_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
const RMT_MEETUP_STATUSES = ['published', 'cancelled'];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/cards.php';

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT)');
/* Plans live in trips since migration 071, so anything that counts travelers reads this. */
$pdo->exec("CREATE TABLE IF NOT EXISTS trips (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT,
              destination_id INT, title TEXT NOT NULL DEFAULT '', slug TEXT NOT NULL DEFAULT '',
              body TEXT, visited_on TEXT, status TEXT NOT NULL DEFAULT 'published',
              visibility TEXT NOT NULL DEFAULT 'public', date_from TEXT, date_to TEXT,
              created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS trip_members (trip_id INT, user_id INT, role TEXT, state TEXT, invited_by INT, created_at TEXT, decided_at TEXT, PRIMARY KEY (trip_id, user_id))");
$pdo->exec('CREATE TABLE going (id INTEGER PRIMARY KEY, destination_id INT, visibility TEXT, date_to TEXT)');
$pdo->exec('CREATE TABLE meetups (id INTEGER PRIMARY KEY, destination_id INT, status TEXT, date_start TEXT)');
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES
              (1,'lisbon-portugal','Lisbon','Portugal'), (2,'quiet-town','Quiet Town','Nowhere')");
$soon = date('Y-m-d', time() + 86400 * 20);
$past = date('Y-m-d', time() - 86400 * 20);
/* One public plan still running, one that has ended, one visible only to followers, and one in
   another city. Only the first counts on Lisbon's card. */
$pdo->exec("INSERT INTO trips (destination_id,title,slug,status,visibility,date_from,date_to) VALUES
              (1,'a','a','published','public','2020-01-01','$soon'),
              (1,'b','b','published','public','2020-01-01','$past'),
              (1,'c','c','published','followers','2020-01-01','$soon'),
              (2,'d','d','published','public','2020-01-01','$soon')");
$soonTs = date('Y-m-d H:i:s', time() + 86400 * 3);
$pastTs = date('Y-m-d H:i:s', time() - 86400 * 3);
$pdo->exec("INSERT INTO meetups (destination_id,status,date_start) VALUES
              (1,'published','$soonTs'), (1,'published','$pastTs'), (1,'cancelled','$soonTs')");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$spec = rmt_card_spec('city', 'lisbon-portugal');
ok('the card names the city', ($spec['title'] ?? '') === 'Travelers in Lisbon', json_encode($spec['title'] ?? null));
ok('the kicker says what the page is', ($spec['kicker'] ?? '') === 'Travelers');
ok('the meta carries the country', str_contains((string) $spec['meta'], 'Portugal'));
ok('only public, unfinished plans are counted', in_array('1 traveler going', $spec['pills'], true),
   json_encode($spec['pills']));
ok('only upcoming, uncancelled meetups are counted', in_array('1 meetup', $spec['pills'], true),
   json_encode($spec['pills']));

// A quiet city gets a card with no numbers rather than a card advertising zeroes.
$quiet = rmt_card_spec('city', 'quiet-town');
ok('a city with one plan says one', $quiet['pills'] === ['1 traveler going'], json_encode($quiet['pills']));
$pdo->exec('DELETE FROM trips WHERE destination_id = 2');
$empty = rmt_card_spec('city', 'quiet-town');
ok('an empty city claims nothing', $empty['pills'] === [], json_encode($empty['pills']));
ok('an empty city still gets a card', ($empty['title'] ?? '') === 'Travelers in Quiet Town');

ok('a city we do not have has no card', rmt_card_spec('city', 'atlantis') === null);
ok('the card url is the one the route serves',
   rmt_card_url('city', 'lisbon-portugal') === 'https://ruinmytrip.com/card/city/lisbon-portugal.png',
   rmt_card_url('city', 'lisbon-portugal'));

$routes = (string) file_get_contents(BASE_PATH . '/public/index.php');
ok('the route accepts the city kind', str_contains($routes, 'meetup|tag|city'));
$controllers = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('the hub shares with it', str_contains($controllers, "rmt_card_url('city'"));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
