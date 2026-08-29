<?php
/**
 * Similar places: what the module claims, and what it must refuse to claim.
 *
 * The heading says "Similar hotels". Every assertion here is about making that sentence true --
 * that a museum never appears under it, that a venue in another city never does, that a closer
 * place of the wrong kind never outranks a matching one, and that a row which matched on nothing
 * is left out rather than padded in to fill the grid.
 *
 *   php tests/similar_places_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/places.php';
require BASE_PATH . '/app/place_data.php';
require BASE_PATH . '/app/similar_places.php';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-58s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY, destination_id INT, slug TEXT, name TEXT,
            type TEXT, status TEXT, category_id INT, neighborhood_id INT, neighborhood TEXT,
            price_level INT, lat REAL, lng REAL)");
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES
    (1,'paris-france','Paris','France'), (2,'lyon-france','Lyon','France')");

// The subject: a mid-priced hotel in area 10, category 5, on the Ile de la Cite.
// Everything else is placed relative to it so each signal can be isolated.
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,type,status,category_id,neighborhood_id,neighborhood,price_level,lat,lng) VALUES
    (1, 1,'subject','Subject Hotel','hotel','active',5,10,'1st Arrondissement',2, 48.8550, 2.3450),
    (2, 1,'twin','Twin Hotel','hotel','active',5,10,'1st Arrondissement',2, 48.8560, 2.3460),
    (3, 1,'same-area','Same Area Hotel','hotel','active',9,10,'1st Arrondissement',3, 48.8555, 2.3455),
    (4, 1,'same-cat','Same Category Hotel','hotel','active',5,11,'6th Arrondissement',4, 48.8700, 2.3600),
    (5, 1,'nothing','Unrelated Hotel','hotel','active',9,11,'6th Arrondissement',NULL, 48.9000, 2.4000),
    (6, 1,'museum','A Museum','attraction','active',5,10,'1st Arrondissement',2, 48.8551, 2.3451),
    (7, 2,'lyon','Lyon Hotel','hotel','active',5,10,'1st Arrondissement',2, 48.8552, 2.3452),
    (8, 1,'closed','Closed Hotel','hotel','closed',5,10,'1st Arrondissement',2, 48.8553, 2.3453),
    (9, 1,'adjacent-price','Adjacent Price Hotel','hotel','active',9,11,'6th Arrondissement',3, 48.9100, 2.4100)");

$subject = q_one("SELECT * FROM places WHERE id = 1");
$sim = rmt_similar_places($subject, 10);
$ids = array_map(static fn(array $r) => (int) $r['id'], $sim);

/* ------------------------------------------------------ what must never appear */

check('a museum is never a similar hotel',   in_array(6, $ids, true), false);
check('another city is never an alternative', in_array(7, $ids, true), false);
check('a closed place is never offered',      in_array(8, $ids, true), false);
check('the place itself is not its own alternative', in_array(1, $ids, true), false);
// Matching on nothing at all is not similarity. Including it would make the heading a lie in
// exactly the case where the module has least to say.
check('a hotel matching on nothing is left out', in_array(5, $ids, true), false);

/* ------------------------------------------------------------------ ordering */

check('the twin ranks first', $ids[0] ?? null, 2);
check('everything that matched is offered', count($ids), 4);

$score = [];
foreach ($sim as $r) $score[(int) $r['id']] = (float) $r['similarity'];

// Area and category are the load-bearing signals; either alone beats neither.
check('same area + same category beats same area alone', $score[2] > $score[3], true);
check('same area alone still scores',                    $score[3] > 0, true);
check('same category in another area still scores',      $score[4] > 0, true);

// Distance is a tiebreak, never a verdict. Place 3 is 60m away and shares the area but not the
// category; place 4 is 1.9km away and shares the category. Neither may win merely on distance.
check('distance does not decide between different matches',
      $score[3] !== $score[4], true);
check('a near miss never outranks a full match', $score[2] > max($score[3], $score[4]), true);

// An adjacent price band is a partial match, not a mismatch: somebody looking at a mid-priced
// hotel is usually still interested in the cheaper one.
check('an adjacent price band scores something', ($score[9] ?? 0.0) > 0, true);
check('but less than an exact band', ($score[9] ?? 0.0) < $score[4], true);

/* ------------------------------------------------------ missing data is silence */

// A place we hold no price for must not be treated as agreeing OR disagreeing on price.
$noPrice = q_one("SELECT * FROM places WHERE id = 5");
$simNoPrice = rmt_similar_places($noPrice, 10);
check('a place with no price still gets alternatives', count($simNoPrice) > 0, true);
check('and none of them scored on a price we do not hold',
      count(array_filter($simNoPrice, static fn(array $r) => (float) $r['similarity'] > RMT_SIM_W_NEIGHBORHOOD + RMT_SIM_W_CATEGORY + RMT_SIM_W_PROXIMITY)), 0);

// No coordinates at all: everything else still works, distance simply contributes nothing.
$pdo->exec("UPDATE places SET lat = NULL, lng = NULL WHERE id = 1");
$unlocated = rmt_similar_places(q_one("SELECT * FROM places WHERE id = 1"), 10);
check('an unlocated place still gets alternatives', count($unlocated) > 0, true);
check('and none of them claim a distance',
      count(array_filter($unlocated, static fn(array $r) => $r['distance_m'] !== null)), 0);

/* ----------------------------------------------------------------- redundancy */

$a = [['id' => 1], ['id' => 2], ['id' => 3]];
check('identical lists are redundant',        rmt_similar_is_redundant($a, $a), true);
check('two of three shared is redundant',     rmt_similar_is_redundant($a, [['id' => 1], ['id' => 2], ['id' => 9]]), true);
check('one of three shared is not',           rmt_similar_is_redundant($a, [['id' => 1], ['id' => 8], ['id' => 9]]), false);
check('disjoint lists are not redundant',     rmt_similar_is_redundant($a, [['id' => 7], ['id' => 8], ['id' => 9]]), false);
// Nothing to compare is not a duplicate. An empty side must never suppress the other module.
check('an empty similar list is not redundant', rmt_similar_is_redundant([], $a), false);
check('an empty nearby list is not redundant',  rmt_similar_is_redundant($a, []), false);

/* ------------------------------------------------------------------- headings */

check('hotel heading',      rmt_similar_heading('hotel'), 'Similar hotels');
check('restaurant heading', rmt_similar_heading('restaurant'), 'Similar restaurants');
check('attraction heading', rmt_similar_heading('attraction'), 'Other things to do');
check('unknown type still reads as English', rmt_similar_heading('spaceport'), 'Similar places');

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
