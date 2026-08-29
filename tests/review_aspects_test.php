<?php
/**
 * Category-specific review subratings, traveler type, and place aggregation.
 *
 * The form only renders the aspects that apply, which is a statement about the browser and not
 * about what arrives in $_POST. Everything below is the server deciding for itself.
 *
 *   php tests/review_aspects_test.php
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
require BASE_PATH . '/app/reviews.php';
require BASE_PATH . '/app/review_aspects.php';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-62s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, region TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT, slug TEXT,
            name TEXT, name_key TEXT, type TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
            place_id INT, subject_type TEXT, subject_name TEXT, rating INT, title TEXT, body TEXT,
            safety_rating INT, value_rating INT, status TEXT, created_at TEXT)");

// The migration is the schema under test.
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/049_review_aspects.sqlite.sql'));

$pdo->exec("INSERT INTO users (id,username,role,status) VALUES
            (1,'ana','user','active'),(2,'ben','user','active'),(3,'cal','user','active'),
            (4,'dee','user','active'),(9,'ruinmytrip','" . RMT_EDITORIAL_ROLE . "','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'lisbon-portugal','Lisbon','Portugal')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,created_at) VALUES
            (1,1,'grand-hotel-lisbon','Grand Hotel','grand hotel','hotel','active','2026-08-01'),
            (2,1,'the-bistro-lisbon','The Bistro','the bistro','restaurant','active','2026-08-01'),
            (3,1,'castle-lisbon','The Castle','the castle','attraction','active','2026-08-01')");

$rid = 0;
function mk_review(int $userId, int $placeId, string $type, int $rating = 4): int {
    db()->prepare("INSERT INTO reviews (user_id,destination_id,place_id,subject_type,subject_name,rating,title,body,status,created_at)
                   VALUES (?,1,?,?,'X',?,'T','B','published','2026-08-02')")
        ->execute([$userId, $placeId, $type, $rating]);
    return (int) db()->lastInsertId();
}

echo "-- vocabulary --\n";
check('hotel aspects',      rmt_aspects_for_category('hotel'), ['rooms','cleanliness','service','location','value','safety']);
check('restaurant aspects', rmt_aspects_for_category('restaurant'), ['food','service','atmosphere','value','safety']);
check('attraction aspects', rmt_aspects_for_category('attraction'), ['experience','crowds','accessibility','value','safety']);
check('a restaurant is never asked about rooms', rmt_aspect_applies('restaurant', 'rooms'), false);
check('a hotel is never asked about food',       rmt_aspect_applies('hotel', 'food'), false);
check('an unknown category has no aspects',      rmt_aspects_for_category('spaceship'), []);
check('every category aspect is a real aspect',
      array_values(array_filter(array_unique(array_merge(...array_values(RMT_ASPECTS_BY_CATEGORY))),
                   static fn($a) => !rmt_aspect_exists($a))), []);
check('every category in the review vocabulary has aspects',
      array_values(array_filter(RMT_REVIEW_CATEGORIES, static fn($c) => !rmt_aspects_for_category($c))), []);

echo "\n-- parsing a hotel submission --\n";
$p = rmt_review_parse_aspects(['aspect' => ['rooms'=>'5','cleanliness'=>'4','service'=>'3','location'=>'5','value'=>'2','safety'=>'4']], 'hotel');
check('valid hotel set is accepted', $p['ok'], true);
check('values kept', $p['values']['rooms'], 5);
check('nothing dropped', $p['dropped'], []);

echo "\n-- rejections --\n";
$bad = rmt_review_parse_aspects(['aspect' => ['not_a_real_aspect' => '4']], 'hotel');
check('an aspect nobody defined is an error', $bad['ok'], false);
check('...and is not stored', array_key_exists('not_a_real_aspect', $bad['values']), false);

$oor = rmt_review_parse_aspects(['aspect' => ['rooms' => '9']], 'hotel');
check('a rating of 9 is an error', $oor['ok'], false);
$neg = rmt_review_parse_aspects(['aspect' => ['rooms' => '-1']], 'hotel');
check('a negative rating is an error', $neg['ok'], false);
$zero = rmt_review_parse_aspects(['aspect' => ['rooms' => '0']], 'hotel');
check('a rating of 0 is an error', $zero['ok'], false);
$txt = rmt_review_parse_aspects(['aspect' => ['rooms' => 'five']], 'hotel');
check('a word is an error', $txt['ok'], false);
$notArr = rmt_review_parse_aspects(['aspect' => 'rooms=5'], 'hotel');
check('aspect posted as a scalar is an error', $notArr['ok'], false);
$nested = rmt_review_parse_aspects(['aspect' => ['rooms' => ['5']]], 'hotel');
check('aspect posted as an array is an error', $nested['ok'], false);

echo "\n-- category mismatch --\n";
$mix = rmt_review_parse_aspects(['aspect' => ['food'=>'5','rooms'=>'5']], 'restaurant');
check('a mismatched aspect does not fail the review', $mix['ok'], true);
check('...it is dropped',            $mix['dropped'], ['rooms']);
check('...and never reaches storage', array_key_exists('rooms', $mix['values']), false);
check('the matching aspect survives', $mix['values']['food'], 5);

echo "\n-- writing, updating, clearing --\n";
$h = mk_review(1, 1, 'hotel');
rmt_review_save_aspects($h, rmt_review_parse_aspects(['aspect'=>['rooms'=>'5','cleanliness'=>'4','value'=>'3']], 'hotel')['values']);
$stored = rmt_review_aspect_values($h); ksort($stored);
check('stored', $stored, ['cleanliness'=>4,'rooms'=>5,'value'=>3]);
check('one row per aspect', (int) $pdo->query("SELECT COUNT(*) FROM review_ratings WHERE review_id=$h")->fetchColumn(), 3);

rmt_review_save_aspects($h, rmt_review_parse_aspects(['aspect'=>['rooms'=>'2','cleanliness'=>'4','value'=>'3']], 'hotel')['values']);
check('a changed rating updates in place', rmt_review_aspect_values($h)['rooms'], 2);
check('no duplicate row was created', (int) $pdo->query("SELECT COUNT(*) FROM review_ratings WHERE review_id=$h AND aspect='rooms'")->fetchColumn(), 1);

rmt_review_save_aspects($h, rmt_review_parse_aspects(['aspect'=>['rooms'=>'2','cleanliness'=>'','value'=>'3']], 'hotel')['values']);
check('a cleared aspect is deleted', array_key_exists('cleanliness', rmt_review_aspect_values($h)), false);
check('no stale child row is left', (int) $pdo->query("SELECT COUNT(*) FROM review_ratings WHERE review_id=$h")->fetchColumn(), 2);

// The unique index is the real guarantee, not the upsert helper.
$dupFailed = false;
try { $pdo->exec("INSERT INTO review_ratings (review_id, aspect, value) VALUES ($h,'rooms',5)"); }
catch (Throwable $e) { $dupFailed = true; }
check('the database refuses a duplicate aspect', $dupFailed, true);

$rangeFailed = false;
try { $pdo->exec("INSERT INTO review_ratings (review_id, aspect, value) VALUES ($h,'service',9)"); }
catch (Throwable $e) { $rangeFailed = true; }
check('the database refuses a value outside 1-5', $rangeFailed, true);

echo "\n-- the legacy mirror columns --\n";
$row = $pdo->query("SELECT safety_rating, value_rating FROM reviews WHERE id=$h")->fetch();
check('value_rating mirrors the value aspect', (int) $row['value_rating'], 3);
check('safety_rating is null when unrated',    $row['safety_rating'], null);
rmt_review_save_aspects($h, ['safety' => 5]);
check('setting the aspect sets the column',
      (int) $pdo->query("SELECT safety_rating FROM reviews WHERE id=$h")->fetchColumn(), 5);
rmt_review_save_aspects($h, ['safety' => null]);
check('clearing the aspect clears the column',
      $pdo->query("SELECT safety_rating FROM reviews WHERE id=$h")->fetchColumn(), null);

echo "\n-- reviews written before any of this existed --\n";
$old = mk_review(2, 1, 'hotel', 3);
$pdo->exec("UPDATE reviews SET safety_rating=4, value_rating=2 WHERE id=$old");
check('an old review has no aspect rows', rmt_review_aspect_values($old), []);
check('...and still renders its overall rating', (int) $pdo->query("SELECT rating FROM reviews WHERE id=$old")->fetchColumn(), 3);
check('...and its traveler type is simply absent', $pdo->query("SELECT traveler_type FROM reviews WHERE id=$old")->fetchColumn(), null);
// The 049 backfill is what moves those two columns into aspect rows; re-running it is idempotent.
$pdo->exec("INSERT OR IGNORE INTO review_ratings (review_id, aspect, value)
            SELECT id,'safety',safety_rating FROM reviews WHERE safety_rating IS NOT NULL");
$pdo->exec("INSERT OR IGNORE INTO review_ratings (review_id, aspect, value)
            SELECT id,'value',value_rating FROM reviews WHERE value_rating IS NOT NULL");
check('the backfill lifts old columns into aspects', rmt_review_aspect_values($old), ['safety'=>4,'value'=>2]);
$pdo->exec("INSERT OR IGNORE INTO review_ratings (review_id, aspect, value)
            SELECT id,'safety',safety_rating FROM reviews WHERE safety_rating IS NOT NULL");
check('running it twice changes nothing',
      (int) $pdo->query("SELECT COUNT(*) FROM review_ratings WHERE review_id=$old")->fetchColumn(), 2);

echo "\n-- traveler type --\n";
foreach (RMT_TRAVELER_TYPES as $t) check("'$t' accepted", rmt_traveler_type_clean($t), $t);
check('case is normalised',        rmt_traveler_type_clean('SOLO'), 'solo');
check('whitespace is trimmed',     rmt_traveler_type_clean('  couple '), 'couple');
check('an invented value is null', rmt_traveler_type_clean('astronaut'), null);
check('empty is null',             rmt_traveler_type_clean(''), null);
check('an array is null',          rmt_traveler_type_clean(['solo']), null);
check('it is optional',            rmt_traveler_type_clean(null), null);
check('every allowed value has a label',
      array_values(array_filter(RMT_TRAVELER_TYPES, static fn($t) => rmt_traveler_type_label($t) === null)), []);

echo "\n-- place aggregation --\n";
// Three travelers rate the bistro; one rates only food. Editorial rates it too and must not count.
$r1 = mk_review(1, 2, 'restaurant', 5);
$r2 = mk_review(2, 2, 'restaurant', 4);
$r3 = mk_review(3, 2, 'restaurant', 3);
$r4 = mk_review(4, 2, 'restaurant', 5);
$re = mk_review(9, 2, 'restaurant', 5);
rmt_review_save_aspects($r1, ['food'=>5, 'service'=>4, 'atmosphere'=>5]);
rmt_review_save_aspects($r2, ['food'=>4, 'service'=>4]);
rmt_review_save_aspects($r3, ['food'=>3, 'service'=>1]);
rmt_review_save_aspects($r4, ['food'=>4]);
rmt_review_save_aspects($re, ['food'=>1, 'service'=>1, 'atmosphere'=>1]);

$agg = rmt_place_aspect_averages(2);
$by = array_column($agg, null, 'aspect');
check('food averages the four travelers', $by['food']['avg'], 4.0);
check('food counts four',                 $by['food']['count'], 4);
check('editorial is excluded from food',  $by['food']['count'] < 5, true);
check('service averages three',           $by['service']['avg'], 3.0);
check('atmosphere has one rating',        $by['atmosphere']['count'], 1);
// A restaurant page lists food, service, atmosphere -- the order its own form asks them in.
check('aspects come back in the order the form asks them', array_column($agg, 'aspect'), ['food','service','atmosphere']);

echo "\n-- the display threshold --\n";
check('three ratings may be published',    $by['food']['show'], true);
check('three ratings may be published (service)', $by['service']['show'], true);
check('one rating may not',                $by['atmosphere']['show'], false);
check('the one rating is still stored',    $by['atmosphere']['avg'], 5.0);
$shown = array_column(rmt_place_aspect_averages_shown(2), 'aspect');
check('only aspects above the threshold are offered to the page', $shown, ['food','service']);
check('an aspect from another category still sorts after the ones that apply', (function () {
    // The bistro is a restaurant; a stray 'rooms' rating from before it was recategorised must not
    // vanish and must not push a restaurant aspect down the list.
    db()->exec("INSERT INTO review_ratings (review_id, aspect, value)
                SELECT id,'rooms',4 FROM reviews WHERE place_id=2 AND user_id IN (1,2,3)");
    $order = array_column(rmt_place_aspect_averages(2), 'aspect');
    db()->exec("DELETE FROM review_ratings WHERE aspect='rooms' AND review_id IN (SELECT id FROM reviews WHERE place_id=2)");
    return $order;
})(), ['food','service','atmosphere','rooms']);
check('the threshold is documented as three', RMT_ASPECT_MIN_SAMPLE, 3);
check('a place nobody rated aggregates to nothing', rmt_place_aspect_averages(3), []);
check('a place id of zero is not a query',           rmt_place_aspect_averages(0), []);

echo "\n-- no N+1 --\n";
$map = rmt_review_aspect_map([$r1, $r2, $r3, $r4]);
check('one call returns every review', count($map), 4);
check('...with the right values', $map[$r1]['atmosphere'], 5);
check('an empty list does not query', rmt_review_aspect_map([]), []);
check('junk ids do not query',        rmt_review_aspect_map([0, -3]), []);

echo "\n-- re-rendering after a validation error --\n";
$posted = rmt_posted_aspect_values(['aspect' => ['rooms'=>'4','food'=>'2','bogus'=>'3','service'=>'']]);
check('what the writer typed comes back', $posted, ['rooms'=>4,'food'=>2]);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
