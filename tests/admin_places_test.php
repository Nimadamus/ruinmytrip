<?php
/**
 * Admin place editor: the hours grid the form posts, and the completeness checklist.
 *
 * The grid is the piece worth pinning. A blank day and a closed day look identical in a form and
 * mean opposite things, and getting that backwards would print "Closed" on a page for a day nobody
 * ever told us about.
 *
 *   php tests/admin_places_test.php
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
require BASE_PATH . '/app/admin_places.php';
require BASE_PATH . '/app/storage.php';

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
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, region TEXT, hero_url TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT, slug TEXT UNIQUE,
            name TEXT, name_key TEXT, type TEXT, created_by INT, status TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, place_id INT, status TEXT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, review_id INT, url TEXT, storage_key TEXT, caption TEXT, sort INT, created_at TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/047_place_attributes.sqlite.sql'));
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'porto-portugal','Porto','Portugal')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,created_at)
            VALUES (1,1,'a-bar-porto','A Bar','a bar','restaurant','active','2026-08-01')");

echo "-- the hours grid --\n";
$g = rmt_admin_parse_hours_grid([
    'opens'  => [0 => ['12:00', '19:00', ''], 3 => ['21:00', '', '']],
    'closes' => [0 => ['15:00', '23:30', ''], 3 => ['02:00', '', '']],
    'closed' => [1 => '1'],
]);
check('no errors on a clean grid', $g['errors'], []);
check('two intervals on Monday',
      count(array_filter($g['intervals'], static fn($i) => $i['day_of_week'] === 0)), 2);
check('Tuesday is one closed row',
      array_values(array_filter($g['intervals'], static fn($i) => $i['day_of_week'] === 1)),
      [['day_of_week' => 1, 'closed' => true]]);
check('an overnight interval survives as written',
      array_values(array_filter($g['intervals'], static fn($i) => $i['day_of_week'] === 3))[0]['closes'], '02:00');
check('days nobody filled in produce nothing',
      array_values(array_unique(array_column($g['intervals'], 'day_of_week'))), [0, 1, 3]);
check('blank slots are not intervals', count($g['intervals']), 4);

echo "\n-- a blank day is not a closed day --\n";
$blank = rmt_admin_parse_hours_grid(['opens' => [5 => ['', '', '']], 'closes' => [5 => ['', '', '']]]);
check('an empty Saturday stores nothing at all', $blank['intervals'], []);
$shut = rmt_admin_parse_hours_grid(['closed' => [5 => '1']]);
check('a ticked Saturday stores an explicit closed row',
      $shut['intervals'], [['day_of_week' => 5, 'closed' => true]]);

echo "\n-- half-filled and out of range --\n";
$half = rmt_admin_parse_hours_grid(['opens' => [0 => ['12:00']], 'closes' => [0 => ['']]]);
check('one time without the other is an error', count($half['errors']), 1);
check('...and the interval is not kept', $half['intervals'], []);
$badday = rmt_admin_parse_hours_grid(['opens' => [9 => ['12:00']], 'closes' => [9 => ['15:00']]]);
check('a day index outside 0-6 is an error', count($badday['errors']), 1);

echo "\n-- Closed wins over stray times --\n";
$both = rmt_admin_parse_hours_grid([
    'opens'  => [2 => ['09:00', '', '']],
    'closes' => [2 => ['17:00', '', '']],
    'closed' => [2 => '1'],
]);
check('ticking Closed discards the times on that day',
      $both['intervals'], [['day_of_week' => 2, 'closed' => true]]);

echo "\n-- round trip through the database --\n";
check('saving the parsed grid succeeds', rmt_place_set_hours(1, $g['intervals']), []);
$grid = rmt_admin_hours_grid(1);
check('Monday comes back with both intervals',
      [$grid['slots'][0][0]['opens'], $grid['slots'][0][1]['opens']], ['12:00', '19:00']);
check('Monday is padded to three slots', count($grid['slots'][0]), 3);
check('Tuesday comes back closed', $grid['closed'][1], true);
check('Wednesday comes back empty and not closed',
      [$grid['closed'][2], $grid['slots'][2][0]['opens']], [false, '']);
check('the overnight close survives the round trip', $grid['slots'][3][0]['closes'], '02:00');
check('every day is present in the grid', count($grid['slots']), 7);

echo "\n-- completeness --\n";
$empty = ['street_address' => null, 'lat' => null, 'phone' => null, 'website_url' => null,
          'price_level' => null, 'category_id' => null, 'hours_rows' => 0, 'photo_rows' => 0];
check('a place we know nothing about is 0%', rmt_place_completeness($empty), 0);
check('one field of eight is 13%', rmt_place_completeness(['street_address' => 'X'] + $empty), 13);
check('everything filled is 100%', rmt_place_completeness([
    'street_address' => 'X', 'lat' => 1.0, 'phone' => '+1 555 0100', 'website_url' => 'https://x.com',
    'price_level' => 2, 'category_id' => 3, 'hours_rows' => 4, 'photo_rows' => 1]), 100);

echo "\n-- the listing --\n";
check('a place with no filter is listed', count(rmt_admin_places('')), 1);
check('a matching filter finds it', count(rmt_admin_places('a bar')), 1);
check('a filter on the city finds it', count(rmt_admin_places('porto')), 1);
check('a filter that matches nothing finds nothing', rmt_admin_places('zzzz'), []);
$row = rmt_admin_places('')[0];
check('the listing carries the counts completeness needs',
      [isset($row['hours_rows']), isset($row['photo_rows'])], [true, true]);
check('hours are counted', (int) $row['hours_rows'], 4);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
