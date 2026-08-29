<?php
/**
 * Destination discovery: qualification, ranking, the quality/volume split, and empty states.
 *
 * The thing under test is a claim the page makes out loud. "Top things to do in Paris" asserts
 * that these are the top ones, and a ranking that lets a single five-star review win makes that
 * assertion false. Most of what follows is about the cases where a naive implementation would
 * still return something and it would be wrong.
 *
 *   php tests/destination_modules_test.php
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
require BASE_PATH . '/app/places.php';
require BASE_PATH . '/app/place_data.php';
require BASE_PATH . '/app/destination_modules.php';

// authors_fill() lives in controllers.php; the shape of the recent-review rows is what matters.
function authors_fill(array &$rows, string $idField = 'user_id'): void {}

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-60s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, region TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT, slug TEXT UNIQUE,
            name TEXT, name_key TEXT, type TEXT, status TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
            place_id INT, rating INT, title TEXT, body TEXT, slug TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY, review_id INT, url TEXT, storage_key TEXT, caption TEXT, sort INT, created_at TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/047_place_attributes.sqlite.sql'));
// The fallback row prefers places we have written about, so it joins place_editorial.
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/041_place_editorial.sqlite.sql'));

$pdo->exec("INSERT INTO users (id,username,role,status) VALUES
    (1,'a','user','active'),(2,'b','user','active'),(3,'c','user','active'),(4,'d','user','active'),
    (9,'ruinmytrip','" . RMT_EDITORIAL_ROLE . "','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES
    (1,'paris-france','Paris','France'), (2,'quiet-town','Quiet Town','Nowhere')");

// One-hit wonder: 3 reviews, all 5.0. Steady: 12 reviews at 4.7. Thin: 2 reviews, ineligible.
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,created_at,neighborhood) VALUES
    (1,1,'one-hit','One Hit Wonder','one hit','attraction','active','2026-08-01','Le Marais'),
    (2,1,'steady','Steady Favourite','steady','attraction','active','2026-08-01','Le Marais'),
    (3,1,'thin','Barely Reviewed','thin','attraction','active','2026-08-01','Montmartre'),
    (4,1,'bistro','Good Bistro','bistro','restaurant','active','2026-08-01','Le Marais'),
    (5,1,'no-reviews','Nobody Reviewed This','no reviews','hotel','active','2026-08-01',NULL),
    (6,2,'lonely','Lonely Cafe','lonely','restaurant','active','2026-08-01',NULL)");

$rid = 100;
function rev(int $place, int $dest, int $user, int $rating, string $when = '2026-08-01 10:00:00'): void {
    global $rid;
    db()->prepare("INSERT INTO reviews (id,user_id,destination_id,place_id,rating,title,body,slug,status,created_at)
                   VALUES (?,?,?,?,?,?,?,?,'published',?)")
        ->execute([$rid, $user, $dest, $place, $rating, 'T' . $rid, 'A body long enough to excerpt.', 's' . $rid, $when]);
    $rid++;
}

foreach ([1, 2, 3] as $u) rev(1, 1, $u, 5);                       // one-hit: 3 x 5.0
for ($i = 0; $i < 12; $i++) rev(2, 1, ($i % 4) + 1, $i < 9 ? 5 : 4); // steady: 12, avg ~4.75
foreach ([1, 2] as $u) rev(3, 1, $u, 5);                          // thin: 2 x 5.0, ineligible
foreach ([1, 2, 3, 4] as $u) rev(4, 1, $u, 4);                    // bistro: 4 x 4.0
rev(6, 2, 1, 5);                                                  // quiet town: one review

echo "-- qualification --\n";
$rank = rmt_destination_rankings(1);
$topAttractions = array_column($rank['top']['attraction'] ?? [], 'slug');
check('a place with 2 reviews does not qualify as top', in_array('thin', $topAttractions, true), false);
check('a place with 3 reviews does', in_array('one-hit', $topAttractions, true), true);
check('a place with no reviews is nowhere near it',
      in_array('no-reviews', array_column($rank['most_reviewed'], 'slug'), true), false);
check('the threshold is documented as three', RMT_TOP_MIN_REVIEWS, 3);
check('qualified counts only eligible places', $rank['qualified'], 3);

echo "\n-- a small sample cannot run away from the field --\n";
$byId = array_column($rank['top']['attraction'], null, 'slug');
$rawGap = $byId['one-hit']['rating_avg'] - $byId['steady']['rating_avg'];
$wtdGap = $byId['one-hit']['weighted'] - $byId['steady']['weighted'];
check('the one-hit wonder has the higher raw average', $rawGap > 0.15, true);
check('shrinkage all but closes the gap', $wtdGap < 0.02, true);
check('...so raw average is no longer what decides', $wtdGap < $rawGap, true);
check('both are still offered, since both cleared the threshold',
      count($topAttractions) >= 2, true);

echo "\n-- shrinkage behaves --\n";
check('one review pulls almost all the way to the mean',
      round(rmt_weighted_rating(5.0, 1, 4.0), 2), 4.09);
check('forty reviews barely move at all',
      round(rmt_weighted_rating(5.0, 40, 4.0), 2), 4.80);
check('the prior is larger than the eligibility threshold',
      RMT_RATING_PRIOR > RMT_TOP_MIN_REVIEWS, true);
// The case the docblock describes: 3 x 5.0 against 40 x 4.7 in a city averaging 4.6.
$small = rmt_weighted_rating(5.0, 3, 4.6);
$large = rmt_weighted_rating(4.7, 40, 4.6);
check('a quarter-star raw gap compresses to under a tenth', round($small - $large, 2) < 0.10, true);
check('no reviews scores nothing', rmt_weighted_rating(5.0, 0, 4.0), 0.0);

echo "\n-- quality and volume are different lists --\n";
$highest = array_column($rank['highest_rated'], 'slug');
$most    = array_column($rank['most_reviewed'], 'slug');
// Not "the first item is the first item": the leader must actually hold the highest score.
$wt = array_column($rank['highest_rated'], 'weighted');
check('highest rated leads with the best weighted score',
      $rank['highest_rated'][0]['weighted'] === max($wt), true);
check('...and everything in it cleared the threshold',
      array_values(array_filter($rank['highest_rated'], static fn($r) => $r['review_count'] < RMT_TOP_MIN_REVIEWS)), []);
check('most reviewed leads with the most reviews', $most[0] ?? null, 'steady');
check('most reviewed includes places below the top threshold',
      in_array('thin', $most, true), true);
check('highest rated does not', in_array('thin', $highest, true), false);
check('the two lists are not the same ordering', $highest === $most, false);

echo "\n-- kinds are answered separately --\n";
check('restaurants have their own list', array_column($rank['top']['restaurant'] ?? [], 'slug'), ['bistro']);
check('a kind with no qualifying place has no list at all',
      array_key_exists('hotel', $rank['top']), false);

echo "\n-- editorial never counts --\n";
rev(1, 1, 9, 1);                                        // the site rates its own one star
$after = rmt_destination_rankings(1);
$a = array_column($after['top']['attraction'], null, 'slug');
check('an editorial rating does not move a community average',
      $a['one-hit']['rating_avg'], 5.0);
check('...nor the review count', $a['one-hit']['review_count'], 3);

echo "\n-- a thin destination degrades to nothing rather than to noise --\n";
$quiet = rmt_destination_rankings(2);
check('one review qualifies nobody', $quiet['top'], []);
check('...and there is no highest-rated row to show', $quiet['highest_rated'], []);
check('...but the place is still counted as reviewed',
      array_column($quiet['most_reviewed'], 'slug'), ['lonely']);
check('a destination with nothing at all returns empty', rmt_destination_rankings(999)['top'], []);
check('a bad id is not a query', rmt_destination_place_stats(0), []);

echo "\n-- recent reviews --\n";
rev(4, 1, 1, 5, '2026-08-28 09:00:00');
$recent = rmt_destination_recent_reviews(1, 5);
check('newest first', (string) ($recent[0]['created_at'] ?? ''), '2026-08-28 09:00:00');
check('carries the place it is about', $recent[0]['place_name'] ?? null, 'Good Bistro');
check('carries the kind of place', $recent[0]['place_type'] ?? null, 'restaurant');
check('carries the neighborhood when there is one', $recent[0]['neighborhood'] ?? null, 'Le Marais');
check('editorial reviews are not listed as traveler activity',
      in_array(9, array_map(static fn($r) => (int) $r['user_id'], $recent), true), false);

echo "\n-- neighborhoods are read, never invented --\n";
$hoods = rmt_destination_neighborhoods(1);
check('a neighborhood with several places is offered',
      in_array('Le Marais', array_column($hoods, 'name'), true), true);
check('a "neighborhood" naming one place is that place\'s address, not an area',
      in_array('Montmartre', array_column($hoods, 'name'), true), false);
check('counts are real', ($hoods[0]['places'] ?? 0), 3);
check('a destination whose places have no neighborhood shows none', rmt_destination_neighborhoods(2), []);

echo "\n-- the whole section, assembled --\n";
$disc = rmt_destination_discovery(1);
check('counts come back per kind', $disc['counts']['attraction'] ?? null, 3);
check('a kind with no places is absent from the counts', array_key_exists('experience', $disc['counts']), false);
check('cards carry no cover when there is no photograph',
      $disc['highest_rated'][0]['cover_url'], null);
check('cards carry a category name only when one is set',
      $disc['highest_rated'][0]['category_name'], null);

echo "\n-- a destination with no community reviews still links to its places --\n";
// This is the normal state of a young destination, not an edge case, and it was a regression:
// the ranked rows replaced a flat list, so a page with no reviews listed none of its own places.
db()->exec("INSERT INTO destinations (id,slug,name,country) VALUES (3,'new-town','New Town','Nowhere')");
db()->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,created_at) VALUES
    (20,3,'unreviewed-inn','Unreviewed Inn','unreviewed inn','hotel','active','2026-08-01'),
    (21,3,'unreviewed-cafe','Unreviewed Cafe','unreviewed cafe','restaurant','active','2026-08-01')");

$fresh = rmt_destination_discovery(3);
check('nothing is ranked', $fresh['top'], []);
check('...and nothing claims to be highest rated', $fresh['highest_rated'], []);
check('but the places are still listed',
      array_column($fresh['fallback'], 'slug'), ['unreviewed-cafe', 'unreviewed-inn']);
check('the browse counts are still real', $fresh['counts']['hotel'] ?? null, 1);

// And once a destination HAS rankings, the fallback stays out of the way rather than repeating them.
$paris = rmt_destination_discovery(1);
check('a destination with rankings shows no fallback row', $paris['fallback'], []);

echo "\n-- category browsing --\n";
$all = rmt_destination_browse(1, '', 'best');
check('every place is listed, reviewed or not', count($all), 5);
check('a place with no reviews is still listed',
      in_array('no-reviews', array_column($all, 'slug'), true), true);
check('...and carries no rating rather than a zero', $all[count($all) - 1]['rating_avg'], null);

$restaurants = rmt_destination_browse(1, 'restaurant', 'best');
check('a kind filter filters', array_column($restaurants, 'slug'), ['bistro']);
check('an unknown kind is ignored rather than returning nothing',
      count(rmt_destination_browse(1, 'spaceship', 'best')), 5);

$byReviews = array_column(rmt_destination_browse(1, '', 'reviews'), 'slug');
check('most reviewed leads the volume sort', $byReviews[0], 'steady');
$byName = array_column(rmt_destination_browse(1, '', 'name'), 'slug');
check('A to Z is alphabetical by name',
      $byName, ['thin', 'bistro', 'no-reviews', 'one-hit', 'steady']);
check('an unknown sort falls back to the default rather than erroring',
      array_column(rmt_destination_browse(1, '', 'nonsense'), 'slug'),
      array_column(rmt_destination_browse(1, '', 'best'), 'slug'));

// The default order must not imply a ranking where there is none to imply.
$fresh = rmt_destination_browse(3, '', 'best');
check('with no reviews the default order is just the places',
      count($fresh), 2);
check('...and nothing carries a weighted score',
      array_values(array_unique(array_column($fresh, 'weighted'))), [0.0]);
check('the sort vocabulary is closed', array_keys(RMT_BROWSE_SORTS), ['best','reviews','name','newest']);

echo "\n-- the readiness report --\n";
$q = array_column(rmt_destination_quality(50), null, 'slug');
check('places are counted', (int) $q['paris-france']['places'], 5);
check('kinds are broken out', (int) $q['paris-france']['restaurants'], 1);
check('community reviews are counted without editorial',
      (int) $q['paris-france']['reviews'], 22);
check('a destination with one place is reported honestly', (int) $q['quiet-town']['places'], 1);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
