<?php
/**
 * Titles, sitemap emptiness rules, review slug trim.
 *
 *   php tests/seo_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/editorial.php';
require BASE_PATH . '/app/reviews.php';
require BASE_PATH . '/app/seo.php';

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT)');
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, destination_id INT, slug TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE trip_photos (id INTEGER PRIMARY KEY, trip_id INT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, place_id INT, slug TEXT, title TEXT, subject_name TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY, review_id INT)");
$pdo->exec("CREATE TABLE guides (id INTEGER PRIMARY KEY, slug TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE blog_posts (id INTEGER PRIMARY KEY, slug TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE collections (id INTEGER PRIMARY KEY, slug TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE meetups (id INTEGER PRIMARY KEY, status TEXT)");
$pdo->exec("CREATE TABLE going (id INTEGER PRIMARY KEY, visibility TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY, destination_id INT, slug TEXT, status TEXT)");
$pdo->exec("INSERT INTO users (id,username,role,status) VALUES (1,'ruinmytrip','editorial','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'barcelona-spain','Barcelona','Spain')");
$pdo->exec("INSERT INTO reviews (id,user_id,destination_id,place_id,slug,title,subject_name,status,created_at)
            VALUES (1,1,1,NULL,'barcelona-2026','Barcelona 2026 tax','Barcelona','published','2026-08-01')");
$pdo->exec("INSERT INTO guides (slug,status,created_at) VALUES ('barcelona-spain-travel-guide','published','2026-08-01')");

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-62s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

echo "-- titles --\n";
$t = rmt_destination_page_title(['name' => 'Barcelona', 'country' => 'Spain']);
check('destination title is 2026-cost shaped', str_contains($t, '2026') && str_contains($t, 'taxes') && !str_contains($t, 'meetups'), true);
check('destination title includes city', str_starts_with($t, 'Barcelona 2026'), true);

$pt = rmt_place_page_title(['name' => 'Park Guell', 'dest_name' => 'Barcelona', 'type' => 'attraction']);
check('place title is ticket-shaped', str_contains($pt, 'tickets') && str_contains($pt, 'Park Guell'), true);
check('place title does not claim traveler reviews', str_contains($pt, 'reviewed by travelers'), false);

echo "\n-- sitemap --\n";
$locs = array_column(rmt_sitemap_entries(), 'loc');
check('home is in', in_array('https://example.test/', $locs, true), true);
check('destination is in', in_array('https://example.test/d/barcelona-spain', $locs, true), true);
check('guide is in', in_array('https://example.test/g/barcelona-spain-travel-guide', $locs, true), true);
check('empty meetups is out', in_array('https://example.test/meetups', $locs, true), false);
check('empty blog index is out', in_array('https://example.test/blog', $locs, true), false);
check('empty leaderboard is out', in_array('https://example.test/leaderboard', $locs, true), false);
check('empty going is out', in_array('https://example.test/going', $locs, true), false);
check('discover in when editorial content exists', in_array('https://example.test/discover', $locs, true), true);

$pdo->exec("INSERT INTO blog_posts (slug,status,created_at) VALUES ('tourist-taxes-2026','published','2026-08-26')");
$locs = array_column(rmt_sitemap_entries(), 'loc');
check('blog index in once a post exists', in_array('https://example.test/blog', $locs, true), true);
check('blog post is in', in_array('https://example.test/blog/tourist-taxes-2026', $locs, true), true);

$withMod = array_values(array_filter(rmt_sitemap_entries(), static fn($r) => $r['loc'] === 'https://example.test/g/barcelona-spain-travel-guide'));
check('guide lastmod is a date', $withMod && $withMod[0]['lastmod'] === '2026-08-01', true);

echo "\n-- review slug --\n";
check('trailing hyphen stripped', rmt_review_slug(['title' => 'Barcelona 2026: Gaudi Glory Behind a Doubled Tourist Tax and an Airbnb Countdown to Zero']),
      rtrim(mb_substr(slugify('Barcelona 2026: Gaudi Glory Behind a Doubled Tourist Tax and an Airbnb Countdown to Zero'), 0, 70), '-'));

echo "\n-- sitemap day --\n";
check('null stays null', rmt_sitemap_day(null), null);
check('datetime to date', rmt_sitemap_day('2026-08-26T12:00:00Z'), '2026-08-26');

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
