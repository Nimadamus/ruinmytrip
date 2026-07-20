<?php
/**
 * Regression tests for the editorial layer's honesty invariants.
 *
 * The whole justification for publishing our own content on a review site is that a reader can
 * always tell it apart from a traveler's, and that it never inflates a community number. Those
 * are product promises, so they get tests. Each case below corresponds to a way the promise
 * could quietly break during a refactor.
 *
 * Runs against a throwaway in-memory SQLite DB. No network, no fixtures on disk.
 *
 *   php tests/editorial_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/seo.php';
require BASE_PATH . '/app/editorial.php';

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT)');
$pdo->exec('CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, destination_id INT,
                                  rating INT, status TEXT)');
$pdo->exec('CREATE TABLE destination_tips (id INTEGER PRIMARY KEY, destination_id INT, body TEXT, sort INT)');
$pdo->exec('CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT)');

// One editorial account, two ordinary members.
$pdo->exec("INSERT INTO users (id,username,role,status) VALUES
              (1,'ruinmytrip','editorial','active'),
              (2,'real_traveler','user','active'),
              (3,'another_one','user','active')");
$pdo->exec("INSERT INTO profiles (user_id,display_name) VALUES (1,'RuinMyTrip Editorial')");

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-58s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

echo "-- rmt_is_editorial() across every row shape the app passes it --\n";
check('author sub-array, editorial',   rmt_is_editorial(['author' => ['role' => 'editorial']]), true);
check('author sub-array, member',      rmt_is_editorial(['author' => ['role' => 'user']]), false);
check('joined author_role column',     rmt_is_editorial(['author_role' => 'editorial']), true);
check('bare user row',                 rmt_is_editorial(['role' => 'editorial']), true);
check('bare user row, admin',          rmt_is_editorial(['role' => 'admin']), false);
check('null row',                      rmt_is_editorial(null), false);
check('row with no role at all',       rmt_is_editorial(['id' => 7]), false);
// A member row nested under an editorial-looking outer key must not leak a true.
check('author beats outer role',       rmt_is_editorial(['role' => 'editorial', 'author' => ['role' => 'user']]), false);

echo "\n-- community average excludes editorial ratings --\n";
// Editorial 5/5 only: the community score must still be empty, not 5.
$pdo->exec("INSERT INTO reviews (user_id,destination_id,rating,status) VALUES (1,10,5,'published')");
$avg = rmt_community_avg(10);
check('editorial-only: count is 0',    (int) $avg['c'], 0);
check('editorial-only: no average',    $avg['a'] === null || (float) $avg['a'] === 0.0, true);

// Add two real reviews. The average must be theirs alone (2 and 4 -> 3.0), not 3.67 with ours.
$pdo->exec("INSERT INTO reviews (user_id,destination_id,rating,status) VALUES (2,10,2,'published')");
$pdo->exec("INSERT INTO reviews (user_id,destination_id,rating,status) VALUES (3,10,4,'published')");
$avg = rmt_community_avg(10);
check('community count is 2',          (int) $avg['c'], 2);
check('community average is 3.0',      (float) $avg['a'], 3.0);

// Drafts and hidden rows are not community opinion either.
$pdo->exec("INSERT INTO reviews (user_id,destination_id,rating,status) VALUES (2,10,1,'draft')");
$pdo->exec("INSERT INTO reviews (user_id,destination_id,rating,status) VALUES (3,10,1,'hidden')");
$avg = rmt_community_avg(10);
check('unpublished excluded',          (int) $avg['c'], 2);

echo "\n-- labelling --\n";
$badge = rmt_editorial_badge('review');
check('review badge says Official Review', str_contains($badge, 'Official Review'), true);
check('badge links to the policy',         str_contains($badge, '/editorial-policy'), true);
check('generic badge says Editorial',      str_contains(rmt_editorial_badge(), 'Editorial'), true);
check('disclosure denies a personal trip',
      str_contains(rmt_editorial_disclosure(), 'not from a personal trip'), true);
check('disclosure denies community counting',
      str_contains(rmt_editorial_disclosure(), 'never counted in'), true);

echo "\n-- splitting a mixed list keeps order and loses nothing --\n";
[$ed, $co] = rmt_split_editorial([
    ['id' => 1, 'author' => ['role' => 'user']],
    ['id' => 2, 'author' => ['role' => 'editorial']],
    ['id' => 3, 'author' => ['role' => 'user']],
]);
check('one editorial item',            count($ed), 1);
check('two community items',           count($co), 2);
check('editorial item is the right one', (int) $ed[0]['id'], 2);
check('community order preserved',     array_column($co, 'id'), [1, 3]);

echo "\n-- JSON-LD never asserts a null rating --\n";
// A destination with no community reviews must omit aggregateRating entirely. Emitting
// "aggregateRating":null is invalid structured data and reads as a broken claim.
$json = jsonld(['@type' => 'TouristDestination', 'name' => 'Nowhere', 'aggregateRating' => null]);
check('null property pruned',          str_contains($json, 'aggregateRating'), false);
check('real properties survive',       str_contains($json, 'Nowhere'), true);
$json = jsonld(['@type' => 'X', 'aggregateRating' => ['ratingValue' => '3.0', 'reviewCount' => 2]]);
check('present rating kept',           str_contains($json, 'ratingValue'), true);

echo "\n-- referral ids are validated, not trusted --\n";
check('unknown username rejected',     rmt_referrer_username('does_not_exist'), null);
check('real member accepted',          rmt_referrer_username('real_traveler'), 'real_traveler');
check('empty rejected',                rmt_referrer_username(''), null);
check('sql-ish junk rejected',         rmt_referrer_username("' OR 1=1--"), null);
check('over-long rejected',            rmt_referrer_username(str_repeat('a', 60)), null);

echo "\n" . ($fail === 0 ? "ALL EDITORIAL TESTS PASS\n" : "{$fail} EDITORIAL TEST(S) FAILED\n");
exit($fail === 0 ? 0 : 1);
