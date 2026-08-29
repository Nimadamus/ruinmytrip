<?php
/**
 * The first reviews: what happens on the day this stops being an empty site.
 *
 * Every ranking module on RuinMyTrip is currently dormant, truthfully, because production has no
 * traveler reviews at all. That means the most important path in the product -- the one where real
 * reviews arrive and the site starts making claims about places -- has never once executed against
 * real data, and will not until it does so in front of the first person who bothered to write
 * something.
 *
 * So it is tested here instead, one review at a time, asserting at each step both what should now
 * be true AND what must still not be. The failure this is built to catch is the eager one: a
 * module that wakes up on the first review and announces a "Top" list of one, which would make the
 * site's loudest claim false on the exact day it acquires its first honest contributor.
 *
 *   php tests/activation_test.php
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

function authors_fill(array &$rows, string $idField = 'user_id'): void {}

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-62s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT, email_verified_at TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, region TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT, slug TEXT UNIQUE,
            name TEXT, name_key TEXT, type TEXT, status TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
            place_id INT, rating INT, title TEXT, body TEXT, slug TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY, review_id INT, url TEXT, storage_key TEXT, caption TEXT, sort INT, created_at TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/047_place_attributes.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/041_place_editorial.sqlite.sql'));
// rmt_place_stats() reads the per-aspect columns, so the review table needs them.
$pdo->exec("ALTER TABLE reviews ADD COLUMN safety_rating INT");
$pdo->exec("ALTER TABLE reviews ADD COLUMN value_rating INT");
$pdo->exec("ALTER TABLE reviews ADD COLUMN cleanliness_rating INT");

$pdo->exec("INSERT INTO users (id,username,role,status,email_verified_at) VALUES
    (1,'ada','user','active','2026-08-01'), (2,'bo','user','active','2026-08-01'),
    (3,'cy','user','active',NULL),          (4,'dee','user','active','2026-08-01'),
    (9,'ruinmytrip','" . RMT_EDITORIAL_ROLE . "','active','2026-08-01')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'lisbon-portugal','Lisbon','Portugal')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,created_at) VALUES
    (1,1,'time-out-market','Time Out Market','time out market','restaurant','active','2026-08-01'),
    (2,1,'castelo','Castelo de Sao Jorge','castelo','attraction','active','2026-08-01')");

$n = 0;
/** One review, from a real traveler. Returns nothing; the assertions read the world after it. */
function review(int $userId, int $placeId, int $rating, string $when = '2026-08-20'): void {
    global $n; $n++;
    q_run("INSERT INTO reviews (user_id,destination_id,place_id,rating,title,body,slug,status,created_at)
           VALUES (?,1,?,?,?,?,?,'published',?)",
          [$userId, $placeId, $rating, 'Visit ' . $n,
           'A real account of going there, long enough to be a review rather than a rating.',
           'r-' . $n, $when]);
}

// highest_rated is the flat quality ranking; top is the same rows split by place type.
$rank  = static fn(): array => array_column(rmt_destination_rankings(1)['highest_rated'] ?? [], 'slug');
$board = static fn(string $k) => rmt_community_scoreboard()[$k];

/* ---------------------------------------------------------------- day zero */

echo "\nDay zero: no traveler has written anything.\n";
check('scoreboard: community reviews', $board('reviews'), 0);
check('scoreboard: unique reviewers', $board('reviewers'), 0);
check('scoreboard: rankable places', $board('places_rankable'), 0);
check('no destination is community active', $board('destinations_active'), 0);
check('nothing is ranked', $rank(), []);
check('registered accounts are counted even with no reviews', $board('users'), 5);

// Editorial writing about a place is not the community reviewing it. This is the single assertion
// that keeps the site honest: our own words must never make a module look populated.
review(9, 1, 5);
echo "\nEditorial publishes. Nothing about the community changes.\n";
check('editorial review does not count', $board('reviews'), 0);
check('editorial reviewer does not count', $board('reviewers'), 0);
check('editorial does not make a place rankable', $board('places_rankable'), 0);
check('editorial does not rank anything', $rank(), []);
check('editorial does not activate the destination', $board('destinations_active'), 0);

/* ---------------------------------------------------------------- the first review */

review(1, 1, 5);
echo "\nThe first real review arrives.\n";
check('the scoreboard moves', $board('reviews'), 1);
check('one reviewer', $board('reviewers'), 1);
check('the place has a review', $board('places_reviewed'), 1);
check('the destination is active', $board('destinations_active'), 1);
// The important half. One person's five stars is not a ranking, and a "Top" list of one is a lie
// told confidently.
check('one review does NOT rank the place', $rank(), []);
check('one review does NOT make it rankable', $board('places_rankable'), 0);

$stats = rmt_place_stats(1);
check('the place shows its rating honestly', round((float) $stats['a'], 2), 5.0);
check('and its real count', (int) $stats['c'], 1);

/* ---------------------------------------------------------------- the second */

review(2, 1, 4);
echo "\nA second traveler, disagreeing slightly.\n";
check('two reviews', $board('reviews'), 2);
check('two reviewers', $board('reviewers'), 2);
check('still not rankable at two', $board('places_rankable'), 0);
check('still nothing ranked', $rank(), []);
check('the average is the real one', round((float) rmt_place_stats(1)['a'], 2), 4.5);

// The same person again is not a third opinion. A place must not become rankable because one
// enthusiast came back twice.
review(1, 1, 5);
echo "\nThe first traveler writes a second review of the same place.\n";
check('three reviews', $board('reviews'), 3);
check('but still only two people', $board('reviewers'), 2);

/* ---------------------------------------------------------------- activation */

review(3, 1, 4);
echo "\nA third distinct traveler. The place wakes up.\n";
check('four reviews', $board('reviews'), 4);
check('three reviewers', $board('reviewers'), 3);
check('the place is now rankable', $board('places_rankable'), 1);
check('and it is ranked', $rank(), ['time-out-market']);

$rows = rmt_destination_rankings(1)['highest_rated'];
check('the ranked row carries the real count', (int) $rows[0]['review_count'], 4);
// The displayed average is always the true one; only the ORDER uses a shrunken score. With a
// single qualified place there is nothing to shrink toward -- the local mean IS this place -- so
// the two agree, and that is the correct answer rather than a missing feature.
check('the displayed average is the true one', round((float) $rows[0]['rating_avg'], 2), 4.5);
check('with nothing to compare against, no shrinkage',
      round((float) $rows[0]['weighted'], 2), 4.5);

/* ---------------------------------------------------------------- second place, ordering */

review(1, 2, 5); review(2, 2, 5); review(4, 2, 5);
echo "\nA second place reaches three reviews, all perfect.\n";
check('both places rankable', $board('places_rankable'), 2);
check('the unanimous one leads', $rank()[0], 'castelo');
check('but the other is still listed', in_array('time-out-market', $rank(), true), true);

// Now there is a mean to pull toward, and shrinkage becomes visible: three perfect reviews do not
// get to present themselves as a settled 5.0 next to a place with four. The gap between them
// narrows; the displayed averages do not move.
$byslug = [];
foreach (rmt_destination_rankings(1)['highest_rated'] as $r) $byslug[$r['slug']] = $r;
check('the perfect average is pulled down for ranking',
      (float) $byslug['castelo']['weighted'] < 5.0, true);
check('the lower one is pulled up',
      (float) $byslug['time-out-market']['weighted'] > 4.5, true);
check('but the displayed averages are untouched',
      [round((float) $byslug['castelo']['rating_avg'], 1), round((float) $byslug['time-out-market']['rating_avg'], 1)],
      [5.0, 4.5]);
check('and the order still favours the better one',
      (float) $byslug['castelo']['weighted'] > (float) $byslug['time-out-market']['weighted'], true);

// Hiding is not deleting, and a hidden review must leave every aggregate as if it were never
// written -- including the count that decides whether a place is allowed to be ranked at all.
q_run("UPDATE reviews SET status='hidden' WHERE place_id=2 AND user_id=4");
echo "\nOne of the three is hidden by a moderator.\n";
check('the place drops back below the threshold', $board('places_rankable'), 1);
check('and out of the ranking', in_array('castelo', $rank(), true), false);
check('the community total drops too', $board('reviews'), 6);

q_run("UPDATE reviews SET status='published' WHERE place_id=2 AND user_id=4");
check('restoring brings it back', in_array('castelo', $rank(), true), true);
check('and the total returns', $board('reviews'), 7);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
