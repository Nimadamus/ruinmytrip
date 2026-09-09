<?php
/**
 * Who may read a trip (rmt_trip_visible_to, rmt_trip_has_substance).
 *
 * This is a defect I introduced today and it is the kind worth a test of its own. Before migration
 * 071 every trip was a public story, so trip_show never needed to ask who was reading. Plans became
 * trips in that migration, and a plan can be marked "followers" or "only you" -- so for a few hours
 * a trip somebody had kept to themselves was readable by anyone holding the link, and the sitemap
 * would have handed it to Google.
 *
 * What must hold:
 *   - a private trip is visible to its owner and to nobody else, including logged-out readers.
 *   - a followers-only trip is visible to followers, and not to strangers.
 *   - a public trip is visible to everybody, as it always was.
 *   - the sitemap only ever contains public trips, and only ones with something on them: a bare
 *     plan stays reachable but is not offered to a crawler while it is still two dates and a city.
 *
 *   php tests/trip_visibility_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
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
$pdo->exec('CREATE TABLE trip_photos (id INTEGER PRIMARY KEY, trip_id INT)');
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, trip_id INT, status TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, body TEXT, status TEXT, visibility TEXT, date_from TEXT, date_to TEXT)");
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,body,status,visibility) VALUES
              (1,1,7,'Public','p1','A week of words','published','public'),
              (2,1,7,'Followers','p2','Shared with mine','published','followers'),
              (3,1,7,'Private','p3','Just for me','published','private'),
              (4,1,7,'Bare plan','p4','','published','public')");
$pdo->exec('INSERT INTO follows (follower_id, followee_id) VALUES (2,1)');

$owner = ['id' => 1];
$follower = ['id' => 2];
$stranger = ['id' => 3];
$get = static fn(int $id) => q_one('SELECT * FROM trips WHERE id=?', [$id]);

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

ok('a public trip is public to a stranger', rmt_trip_visible_to($get(1), $stranger));
ok('a public trip is public logged out', rmt_trip_visible_to($get(1), null));

ok('a private trip is visible to its owner', rmt_trip_visible_to($get(3), $owner));
ok('a private trip is not visible to a stranger', !rmt_trip_visible_to($get(3), $stranger));
ok('a private trip is not visible logged out', !rmt_trip_visible_to($get(3), null));
ok('a private trip is not visible to a follower', !rmt_trip_visible_to($get(3), $follower));

ok('a followers trip is visible to a follower', rmt_trip_visible_to($get(2), $follower));
ok('a followers trip is visible to its owner', rmt_trip_visible_to($get(2), $owner));
ok('a followers trip is not visible to a stranger', !rmt_trip_visible_to($get(2), $stranger));
ok('a followers trip is not visible logged out', !rmt_trip_visible_to($get(2), null));

// Substance: what earns a place in the sitemap.
ok('a trip with words has substance', rmt_trip_has_substance($get(1)));
ok('a bare plan has none yet', !rmt_trip_has_substance($get(4)));
q_run('INSERT INTO trip_photos (trip_id) VALUES (4)');
ok('a photo is substance', rmt_trip_has_substance($get(4)));
q_run('DELETE FROM trip_photos');
q_run("INSERT INTO posts (id,trip_id,status) VALUES (1,4,'published')");
ok('an update is substance', rmt_trip_has_substance($get(4)));
q_run("UPDATE posts SET status='removed'");
ok('a removed update is not substance', !rmt_trip_has_substance($get(4)));

// The page and the sitemap must both ask, and they must ask the same function.
$controllers = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('the trip page checks visibility', str_contains($controllers, 'rmt_trip_visible_to($t, current_user())'));
$sitemap = (string) file_get_contents(BASE_PATH . '/app/sitemap.php');
ok('the sitemap only takes public trips', str_contains($sitemap, "COALESCE(visibility,'public')='public'"));
ok('the sitemap skips empty plans', str_contains($sitemap, 'rmt_trip_has_substance'));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
