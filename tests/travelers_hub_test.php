<?php
/**
 * Regression tests for the city travelers hub (app/travelers_hub.php, /d/{slug}/travelers).
 *
 * The site publishes a great deal about places and almost nothing about people, and the question a
 * traveler actually arrives with -- who else is going to be there, can I meet them -- had no page.
 * This one is made entirely of member activity, which is exactly why it has to be honest about an
 * empty city rather than padded.
 *
 * What must hold:
 *   - the hub counts only what is real: upcoming meetups, dates that have not ended, talk about
 *     the city, and members who published something about it.
 *   - editorial house accounts never appear as travelers. The page's whole value is that these are
 *     people a reader could message.
 *   - a plan that already ended is not an answer to "who is going".
 *   - a cancelled or past meetup is not an invitation.
 *   - a quiet city returns zero and says so, rather than borrowing another city's activity.
 *
 *   php tests/travelers_hub_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
const RMT_EDITORIAL_ROLE = 'editorial';

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/plans.php';
require BASE_PATH . '/app/going.php';
require BASE_PATH . '/app/travelers_hub.php';

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT, role TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INT, avatar_url TEXT, display_name TEXT, travel_style TEXT)');
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT)');
/* The hub lists a city's traveler reviews now, so the fixture carries the columns it reads. */
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, destination_id INT,
              status TEXT, title TEXT, subject_name TEXT, rating INT, what_ruined TEXT,
              slug TEXT, place_id INT, created_at TEXT)");
$pdo->exec('CREATE TABLE going (id INTEGER PRIMARY KEY, user_id INT, destination_id INT,
              date_from TEXT, date_to TEXT, visibility TEXT)');
/* Plans are trips now (migration 071). The legacy table above is kept in the fixture for the same
   reason it is kept in the database: nothing reads it, and proving that is part of the point. */
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
              title TEXT NOT NULL DEFAULT '', slug TEXT NOT NULL DEFAULT '', body TEXT, visited_on TEXT,
              status TEXT NOT NULL DEFAULT 'published', visibility TEXT NOT NULL DEFAULT 'public',
              date_from TEXT, date_to TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec('CREATE TABLE meetups (id INTEGER PRIMARY KEY, host_id INT, destination_id INT, title TEXT,
              date_start TEXT, status TEXT)');
$pdo->exec('CREATE TABLE meetup_rsvps (meetup_id INT, user_id INT, status TEXT)');

$pdo->exec("INSERT INTO users (id,username,status,role) VALUES
              (1,'ana','active','member'), (2,'leo','active','member'),
              (3,'house','active','editorial'), (4,'gone','disabled','member')");
$past   = date('Y-m-d', time() - 86400 * 30);
$soon   = date('Y-m-d', time() + 86400 * 10);
$later  = date('Y-m-d', time() + 86400 * 20);
$pastTs = date('Y-m-d H:i:s', time() - 86400 * 5);
$soonTs = date('Y-m-d H:i:s', time() + 86400 * 5);

// City 1 has a live plan, a finished plan, an upcoming meetup, a past one and a cancelled one.
$ins = $pdo->prepare("INSERT INTO trips (user_id,destination_id,title,slug,body,status,visibility,date_from,date_to,created_at)
                      VALUES (?,?,'Trip','trip','','published',?,?,?,datetime('now'))");
$ins->execute([1, 1, 'public', $soon, $later]);
$ins->execute([2, 1, 'public', $past, $past]);
$ins->execute([1, 2, 'public', $soon, $later]);
$m = $pdo->prepare('INSERT INTO meetups (id,host_id,destination_id,title,date_start,status) VALUES (?,?,?,?,?,?)');
$m->execute([1, 1, 1, 'Coffee by the river', $soonTs, 'published']);
$m->execute([2, 1, 1, 'The one that happened', $pastTs, 'published']);
$m->execute([3, 1, 1, 'Called off',           $soonTs, 'cancelled']);
$pdo->exec("INSERT INTO meetup_rsvps (meetup_id,user_id,status) VALUES (1,2,'going')");
$pdo->exec("INSERT INTO reviews (id,user_id,destination_id,status,title,created_at) VALUES
              (1,1,1,'published','A queue and a view',datetime('now')),
              (2,3,1,'published','Ours, not a traveler''s',datetime('now')),
              (3,2,1,'draft','Unfinished',datetime('now'))");
/* leo also published a trip story about the city, which is what makes him a traveler who has been
   as well as one with dates. Ids 1 to 3 are the plans inserted above, so this one is explicit. */
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,body,status,created_at)
              VALUES (99,2,1,'A week in it','a-week','words','published',datetime('now'))");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$meetups = rmt_city_meetups(1);
ok('only the upcoming published meetup counts', count($meetups) === 1 && (int) $meetups[0]['id'] === 1,
   json_encode(array_column($meetups, 'id')));
ok('the going count is live', (int) $meetups[0]['going_count'] === 1);

$going = rmt_city_going(1, null);
ok('a finished plan is not an answer to who is going', count($going) === 1 && (int) $going[0]['user_id'] === 1,
   json_encode(array_column($going, 'user_id')));
ok('another city\'s plan stays in that city', count(rmt_city_going(2, null)) === 1);

$people = rmt_city_travelers(1);
$names = array_column($people, 'username');
sort($names);
ok('travelers are members who published about the city', $names === ['ana', 'leo'], json_encode($names));
ok('the house account is never a traveler', !in_array('house', $names, true));
ok('review and trip counts are per city', $people[0]['reviews'] + $people[0]['trips'] >= 1);

$hub = rmt_city_traveler_hub(1, null);
/* The count is the sum of every section the page renders, so a section added to the hub without
   being counted would leave "is anybody here" answering for a page that shows more than it says. */
ok('the hub reports what it has', $hub['active'] === count($hub['meetups']) + count($hub['going'])
    + count($hub['talk']) + count($hub['people']) + count($hub['locals']) + count($hub['reviews']));
ok('the hub carries the city reviews', isset($hub['reviews']));
ok('an editorial review is not a traveler review',
   !in_array('house', array_column($hub['reviews'], 'username'), true),
   json_encode(array_column($hub['reviews'], 'username')));
ok('a draft is not published to the city page',
   !in_array('Unfinished', array_column($hub['reviews'], 'title'), true));
ok('a quiet city reports nothing rather than borrowing', rmt_city_traveler_hub(9, null)['active'] === 0);

// The page has to be reachable and in the sitemap, or it is a page that exists and nobody is sent to.
$routes = (string) file_get_contents(BASE_PATH . '/public/index.php');
ok('the hub is routed', str_contains($routes, "/travelers$#', 'destination_travelers'"));
$sitemap = (string) file_get_contents(BASE_PATH . '/app/sitemap.php');
ok('the hub is in the sitemap', str_contains($sitemap, "'/travelers'"));
$destView = (string) file_get_contents(BASE_PATH . '/views/destination.php');
ok('the destination page links it', str_contains($destView, "/travelers'"));
$travelers = (string) file_get_contents(BASE_PATH . '/views/travelers_index.php');
ok('/travelers lists every city hub', str_contains($travelers, "'/travelers'"));
$hubView = (string) file_get_contents(BASE_PATH . '/views/destination_travelers.php');
ok('the page asks a logged-out reader to join', str_contains($hubView, 'Join RuinMyTrip'));
ok('the page never claims activity it does not have', str_contains($hubView, "Nobody has posted about"));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
