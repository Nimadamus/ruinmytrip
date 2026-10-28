<?php
/**
 * The light acquisition scoreboard (app/acquire_board.php).
 *
 * A signup, a finished profile, a review and a buddy post must be countable by the utm source,
 * campaign and content stored on that action, without reading every page view. Our own checks
 * are left out. House accounts are left out of the table counts.
 *
 *   php tests/acquire_board_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/growth_scorecard.php';
require BASE_PATH . '/app/contribution_events.php';
require BASE_PATH . '/app/acquire_board.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$pdo = db();
$pdo->exec("CREATE TABLE contribution_events (id INTEGER PRIMARY KEY AUTOINCREMENT, event TEXT, source TEXT,
            journey TEXT, visitor TEXT, cookied INTEGER, place_id INTEGER, destination_id INTEGER,
            is_authed INTEGER, reason TEXT, acq_source TEXT, acq_medium TEXT, acq_campaign TEXT,
            acq_content TEXT, path TEXT, detail TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT, created_at TEXT, email_verified_at TEXT, email TEXT, invited_by INTEGER)");
$pdo->exec("CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, bio TEXT, home_city TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE buddy_posts (id INTEGER PRIMARY KEY, user_id INT, status TEXT, date_to TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, status TEXT, visibility TEXT, date_from TEXT, date_to TEXT, created_at TEXT)");

$now = date('Y-m-d H:i:s');
$ins = $pdo->prepare('INSERT INTO contribution_events (event, journey, visitor, acq_source, acq_campaign, acq_content, created_at) VALUES (?,?,?,?,?,?,?)');
$ins->execute(['join_created', 'j1', 'v1', 'facebook', 'solo-lisbon', 'group-a', $now]);
$ins->execute(['profile_completed', 'j1', 'v1', 'facebook', 'solo-lisbon', 'group-a', $now]);
$ins->execute(['buddy_post_created', 'j1', 'v1', 'facebook', 'solo-lisbon', 'group-a', $now]);
$ins->execute(['review_publish_success', 'j2', 'v2', 'search', '', '', $now]);
$ins->execute(['join_created', 'j9', 'v9', 'facebook', 'solo-lisbon', 'selfcheck', $now]);
$ins->execute(['landing_view', 'j3', 'v3', 'facebook', 'solo-lisbon', 'group-a', $now]);
$ins->execute(['join_created', 'j4', 'v4', 'referral', 'member-share', 'ana', $now]);

$pdo->exec("INSERT INTO users VALUES
    (1,'ada','user','active','$now','$now','ada@example.com', NULL),
    (2,'house','editorial','active','$now','$now','house@example.com', 1),
    (3,'qa','user','active','$now','$now','qa+rmtqa@example.com', 1),
    (4,'bea','user','active','$now','$now','bea@example.com', 1)");
$pdo->exec("INSERT INTO profiles VALUES (1,'Ada','I travel solo and walk cities.', 'Lisbon'), (2,'House','Editorial account bio here.', 'Rome')");
$pdo->exec("INSERT INTO reviews VALUES (1,1,'published','$now'), (2,2,'published','$now')");
$pdo->exec("INSERT INTO buddy_posts VALUES (1,1,'open','2099-01-01','$now'), (2,2,'open','2099-01-01','$now')");
$pdo->exec("INSERT INTO trips VALUES (1,1,'published','public','2099-01-01','2099-01-08','$now')");

$b = rmt_acquire_board(31);
$by = [];
foreach ($b['events'] as $e) $by[$e['source'] . '|' . $e['campaign'] . '|' . $e['content'] . '|' . $e['event']] = $e['n'];

ok('a facebook campaign signup is attributed', ($by['facebook|solo-lisbon|group-a|join_created'] ?? 0) === 1, json_encode($by));
ok('the same link gets the profile and the buddy post', ($by['facebook|solo-lisbon|group-a|profile_completed'] ?? 0) === 1
    && ($by['facebook|solo-lisbon|group-a|buddy_post_created'] ?? 0) === 1);
ok('a search review is its own row', ($by['search|||review_publish_success'] ?? 0) === 1);
ok('a selfcheck signup is left out', !isset($by['facebook|solo-lisbon|selfcheck|join_created']));
ok('a page view is not an acquisition row', !isset($by['facebook|solo-lisbon|group-a|landing_view']));
ok('the house review is not a published review', $b['product']['reviews_published'] === 1, (string) $b['product']['reviews_published']);
ok('the house buddy post is not a listing', $b['product']['buddy_posts'] === 1 && $b['product']['buddy_open'] === 1);
ok('a finished profile is counted and the house one is not', $b['product']['profiles_completed'] === 1, (string) $b['product']['profiles_completed']);
ok('the qa account is not a signup', $b['product']['signups'] === 2 && $b['product']['confirmed'] === 2);
ok('a member invite is a referral and the house invite is not', $b['product']['referrals'] === 1, (string) $b['product']['referrals']);
ok('a member-share signup is its own row', ($by['referral|member-share|ana|join_created'] ?? 0) === 1);
ok('the real upcoming trip is counted', $b['product']['trips_public_upcoming'] === 1);
ok('a failed query is not hidden as a zero', $b['errors'] === []);
$json = json_encode($b);
ok('the board has no email address', !str_contains((string) $json, '@'));
ok('the board has no visitor id', !str_contains((string) $json, 'v1') && !str_contains((string) $json, 'visitor'));

$welcome = (string) file_get_contents(BASE_PATH . '/views/welcome.php');
ok('welcome can collect a name and a sentence', str_contains($welcome, 'name="display_name"') && str_contains($welcome, 'name="bio"'));
ok('welcome says when the trip list is empty', str_contains($welcome, 'first listing'));
ok('welcome offers the map without an account step in the copy', str_contains($welcome, "url('map')"));

echo $fails ? "\nacquire_board_test: $fails FAILED\n" : "\nacquire_board_test: all passed\n";
exit($fails ? 1 : 0);
