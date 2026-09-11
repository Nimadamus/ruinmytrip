<?php
/**
 * The photographs on a profile (rmt_profile_photos).
 *
 * The profile counted photos in its stat row and then showed none of them, which is the least
 * useful place a number can sit. They live in three tables because they were added in three places
 * -- a trip, a review, a short post -- and a reader does not care which, so the profile shows one
 * grid and each picture links back to the thing it belongs to.
 *
 * The rule with teeth: a private or followers-only trip's photographs must never appear on a public
 * profile. The visibility work earlier today fixed the trip page and the sitemap; this is the third
 * door into the same content.
 *
 *   php tests/profile_photos_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/reviews.php';
require BASE_PATH . '/app/profiles.php';

$pdo = db();
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, title TEXT, slug TEXT,
              status TEXT, visibility TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS trip_members (trip_id INT, user_id INT, role TEXT, state TEXT, invited_by INT, created_at TEXT, decided_at TEXT, PRIMARY KEY (trip_id, user_id))");
$pdo->exec("CREATE TABLE trip_photos (id INTEGER PRIMARY KEY, trip_id INT, url TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, title TEXT, subject_name TEXT,
              slug TEXT, status TEXT, place_id INT, destination_id INT, created_at TEXT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY, review_id INT, url TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INT, body TEXT, status TEXT,
              image_url TEXT, created_at TEXT)");
$pdo->exec("INSERT INTO trips (id,user_id,title,slug,status,visibility,created_at) VALUES
              (1,1,'Public trip','pt','published','public','2026-01-01 10:00:00'),
              (2,1,'Private trip','xt','published','private','2026-01-02 10:00:00'),
              (3,1,'Followers trip','ft','published','followers','2026-01-03 10:00:00')");
$pdo->exec("INSERT INTO trip_photos (trip_id,url,created_at) VALUES
              (1,'/media/a.jpg','2026-01-01 10:00:00'),
              (2,'/media/secret.jpg','2026-01-02 10:00:00'),
              (3,'/media/followers.jpg','2026-01-03 10:00:00')");
$pdo->exec("INSERT INTO reviews (id,user_id,title,subject_name,slug,status,created_at) VALUES
              (7,1,'A review','A place','a-review','published','2026-02-01 10:00:00'),
              (8,1,'A draft','A place','a-draft','draft','2026-02-02 10:00:00')");
$pdo->exec("INSERT INTO review_photos (review_id,url,created_at) VALUES
              (7,'/media/r.jpg','2026-02-01 10:00:00'), (8,'/media/draft.jpg','2026-02-02 10:00:00')");
$pdo->exec("INSERT INTO posts (id,user_id,body,status,image_url,created_at) VALUES
              (20,1,'A post with a picture','published','/media/p.jpg','2026-03-01 10:00:00'),
              (21,1,'A post with none','published',NULL,'2026-03-02 10:00:00'),
              (22,1,'A removed post','removed','/media/gone.jpg','2026-03-03 10:00:00')");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$wall = rmt_profile_photos(1);
$urls = array_column($wall, 'url');

ok('photographs come from all three places', count($wall) === 3, json_encode($urls));
ok('a private trip keeps its photographs', !in_array('/media/secret.jpg', $urls, true));
ok('so does a followers-only trip', !in_array('/media/followers.jpg', $urls, true));
ok('a draft review shows nothing', !in_array('/media/draft.jpg', $urls, true));
ok('a removed post shows nothing', !in_array('/media/gone.jpg', $urls, true));
ok('newest first', $urls === ['/media/p.jpg', '/media/r.jpg', '/media/a.jpg'], json_encode($urls));
ok('each one links back to what it belongs to',
   str_contains($wall[0]['href'], '/post/20') && str_contains($wall[2]['href'], '/trip/1/'),
   json_encode(array_column($wall, 'href')));
ok('the limit is honoured', count(rmt_profile_photos(1, 2)) === 2);
ok('somebody with none gets none', rmt_profile_photos(99) === []);

$view = (string) file_get_contents(BASE_PATH . '/views/profile.php');
ok('the profile renders the grid', str_contains($view, '$photoWall'));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
