<?php
/**
 * Neighborhood identity: normalisation, aliases, scoping, and what must NOT happen.
 *
 * The interesting assertions here are the negative ones. This code exists to merge spellings, and
 * the failure mode of anything that merges is merging two things that were never the same -- an
 * Altstadt in Munich with an Altstadt in Zurich, a borough presented as a neighborhood, a place
 * quietly reassigned out of the area an editor put it in. Each of those is pinned below.
 *
 *   php tests/neighborhoods_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/search_suggest.php';
require BASE_PATH . '/app/neighborhoods.php';

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
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT, slug TEXT,
            name TEXT, type TEXT, status TEXT, neighborhood TEXT,
            price_level INT, street_address TEXT, lat REAL, lng REAL, category_id INT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/055_neighborhoods.sqlite.sql'));

$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES
    (1,'paris-france','Paris','France'),
    (2,'munich-germany','Munich','Germany'),
    (3,'zurich-switzerland','Zurich','Switzerland'),
    (4,'new-york-city-usa','New York City','United States')");

/* ------------------------------------------------------------------ the key */

// The case that motivated the whole model: one area, three sources, three spellings.
$k = static fn(string $s, string $city = 'Paris') => rmt_nb_key($s, $city);
check('key: "1st Arrondissement"',            $k('1st Arrondissement'), '1');
check('key: "Paris 1er Arrondissement"',      $k('Paris 1er Arrondissement'), '1');
check('key: "1er"',                           $k('1er'), '1');
check('key: "1ER ARRONDISSEMENT"',            $k('1ER ARRONDISSEMENT'), '1');
check('key: the 6th is not the 1st',          $k('6th Arrondissement') === $k('1st Arrondissement'), false);

// Accents and case are spelling, not identity.
check('key: accents fold',                    $k('Smíchov', 'Prague'), $k('Smichov', 'Prague'));
check('key: case folds',                      $k('LE MARAIS'), $k('le marais'));
check('key: punctuation folds',               $k('Saint-Germain-des-Pres'), $k('Saint Germain des Pres'));

// The city prefix goes only when something is left behind it.
check('key: city prefix dropped',             $k('Munich Old Town', 'Munich'), $k('Old Town', 'Munich'));
check('key: an area named after its city survives',
      rmt_nb_key('Vatican City', 'Vatican City'), 'vatican city');
check('key: empty stays empty',               $k(''), '');

/* ------------------------------------------------------------ upsert, aliases */

$paris = rmt_nb_upsert(1, '1st Arrondissement',
    ['Paris 1er Arrondissement', '1er', 'Louvre'], ['local_name' => '1er arrondissement']);
check('upsert: created', $paris['created'], true);

// Every spelling now points at one area.
foreach (['1st Arrondissement', 'Paris 1er Arrondissement', '1er', 'Louvre', '1er arrondissement'] as $variant) {
    $r = rmt_nb_resolve(1, $variant, 'Paris');
    check('resolves: ' . $variant, $r ? (int) $r['id'] : null, $paris['id']);
}
check('does not resolve an unknown area', rmt_nb_resolve(1, 'Belleville', 'Paris'), null);

// Re-applying the seed must be a no-op, or every deploy would accumulate duplicates.
$again = rmt_nb_upsert(1, '1st Arrondissement',
    ['Paris 1er Arrondissement', '1er', 'Louvre'], ['local_name' => '1er arrondissement']);
check('upsert: second run creates nothing', $again['created'], false);
check('upsert: second run adds no aliases', $again['aliases_added'], 0);
check('upsert: still one area in Paris',
      (int) q_one("SELECT COUNT(*) c FROM neighborhoods WHERE destination_id = 1")['c'], 1);

// A spelling that already means something else is a curation mistake and must be loud, because
// accepting it would silently move places between areas on the next attach pass.
$threw = false;
try { rmt_nb_upsert(1, '2nd Arrondissement', ['Louvre']); } catch (RuntimeException $e) { $threw = true; }
check('upsert: a clashing alias throws', $threw, true);

/* -------------------------------------------------------------- scoping by city */

$munich = rmt_nb_upsert(2, 'Altstadt', ['Old Town']);
$zurich = rmt_nb_upsert(3, 'Altstadt', ['Old Town', 'Kreis 1']);
check('same name in two cities is two areas', $munich['id'] === $zurich['id'], false);
check('Munich Old Town resolves to Munich',
      (int) rmt_nb_resolve(2, 'Old Town', 'Munich')['id'], $munich['id']);
check('Zurich Old Town resolves to Zurich',
      (int) rmt_nb_resolve(3, 'Old Town', 'Zurich')['id'], $zurich['id']);
check('Paris does not have an Old Town', rmt_nb_resolve(1, 'Old Town', 'Paris'), null);

/* ---------------------------------------------------------------- attaching places */

$manhattan = rmt_nb_upsert(4, 'Manhattan', ['New York County'], ['kind' => 'borough']);

$pdo->exec("INSERT INTO places (destination_id,slug,name,type,status,neighborhood) VALUES
    (1,'p1','Place One','restaurant','active','Paris 1er Arrondissement'),
    (1,'p2','Place Two','hotel','active','1st Arrondissement'),
    (1,'p3','Place Three','attraction','active','Belleville'),
    (2,'p4','Place Four','restaurant','active','Old Town'),
    (2,'p5','Place Five','hotel','active','Altstadt'),
    (4,'p6','Place Six','hotel','active','Manhattan'),
    (4,'p7','Place Seven','hotel','active','Manhattan'),
    (1,'p8','Closed One','hotel','closed','1er')");

$dry = rmt_nb_attach_places(false);
check('dry run matches without writing', $dry['matched'], 6);
check('dry run wrote nothing',
      (int) q_one("SELECT COUNT(*) c FROM places WHERE neighborhood_id IS NOT NULL")['c'], 0);
check('dry run reports the unmapped one', $dry['unresolved']['Belleville'] ?? 0, 1);

$run = rmt_nb_attach_places(true);
check('apply attaches the same 6', $run['matched'], 6);
check('two spellings landed in ONE area',
      (int) q_one("SELECT COUNT(*) c FROM places WHERE neighborhood_id = ?", [$paris['id']])['c'], 2);
check('the unmapped place is still unattached',
      q_one("SELECT neighborhood_id FROM places WHERE slug = 'p3'")['neighborhood_id'], null);
check('and it KEEPS its raw text',
      (string) q_one("SELECT neighborhood FROM places WHERE slug = 'p3'")['neighborhood'], 'Belleville');
// An inactive place is not part of any count and should not be attached by a maintenance pass.
check('a closed place is left alone',
      q_one("SELECT neighborhood_id FROM places WHERE slug = 'p8'")['neighborhood_id'], null);

// An editor's assignment outranks a later import. Attach only ever fills a null.
q_run("UPDATE places SET neighborhood_id = ? WHERE slug = 'p3'", [$paris['id']]);
q_run("UPDATE places SET neighborhood = 'Old Town' WHERE slug = 'p3'");
rmt_nb_attach_places(true);
check('an existing assignment is never overwritten',
      (int) q_one("SELECT neighborhood_id FROM places WHERE slug = 'p3'")['neighborhood_id'], $paris['id']);

/* ------------------------------------------------------------------- browsing */

// Paris now has 3 places in one area; Munich has 2 split across two areas that are the SAME area.
$parisBrowse = rmt_nb_for_destination(1);
check('Paris offers its one area', count($parisBrowse), 1);
check('with a real count', (int) $parisBrowse[0]['places'], 3);
check('named canonically', (string) $parisBrowse[0]['name'], '1st Arrondissement');

$munichBrowse = rmt_nb_for_destination(2);
check('Munich merged both spellings into one area', count($munichBrowse), 1);
check('Munich Altstadt holds both places', (int) $munichBrowse[0]['places'], 2);

// A borough is not a way to browse a city by neighborhood, however many places sit in it.
$nyc = rmt_nb_for_destination(4);
check('a borough is not offered as a neighborhood', $nyc, []);
check('but it still exists as an entity',
      (int) q_one("SELECT COUNT(*) c FROM neighborhoods WHERE destination_id = 4")['c'], 1);
$dormant = rmt_nb_dormant(4);
check('and is visible as dormant, labelled', (string) $dormant[0]['kind'], 'borough');

// One place is an address, not an area.
rmt_nb_upsert(1, '7th Arrondissement', []);
q_run("INSERT INTO places (destination_id,slug,name,type,status,neighborhood) VALUES (1,'p9','Nine','hotel','active','7th Arrondissement')");
rmt_nb_attach_places(true);
check('an area with one place is not offered', count(rmt_nb_for_destination(1)), 1);
check('and appears in the dormant list', count(rmt_nb_dormant(1)) >= 1, true);

/* --------------------------------------------------------------- the page's data */

check('area places, all types', count(rmt_nb_places($paris['id'])), 3);
check('area places, filtered', count(rmt_nb_places($paris['id'], 'hotel')), 1);
$counts = rmt_nb_type_counts($paris['id']);
check('type counts are real', $counts, ['attraction' => 1, 'hotel' => 1, 'restaurant' => 1]);

// The working queue: what a human still has to decide about.
check('unmapped queue is empty once everything resolved', rmt_nb_unmapped(), []);

/* ------------------------------------------------------- non-Latin scripts */

// Half the world does not write in the Latin alphabet, and the source we import from uses the
// local script. Before this, "Πετράλωνα" normalised to the empty string: the area could not even
// be stored as an alias, let alone matched, and it failed silently as an unmapped value.
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (5,'athens-greece','Athens','Greece')");
$athens = rmt_nb_upsert(5, 'Petralona', ['Ano Petralona'], ['local_name' => 'Πετράλωνα']);
check('a Greek local name is keyable', rmt_nb_key('Πετράλωνα') !== '', true);
check('and resolves to its area',
      (int) rmt_nb_resolve(5, 'Πετράλωνα', 'Athens')['id'], $athens['id']);
check('the Latin form resolves to the same area',
      (int) rmt_nb_resolve(5, 'Petralona', 'Athens')['id'], $athens['id']);
// The pairing is a fact about Greek, not something the normaliser should invent. It works because
// a human wrote both spellings down, which is exactly what the alias table is for.
check('the two scripts are NOT merged by normalisation alone',
      rmt_nb_key('Πετράλωνα') === rmt_nb_key('Petralona'), false);
check('an unlisted Greek name still does not resolve',
      rmt_nb_resolve(5, 'Εξάρχεια', 'Athens'), null);

/* ------------------------------------------------- the link back from a place

   The graph ran one way: a destination linked to its areas and an area linked to its places, but
   a place named its area in plain text and linked nowhere, so neither a reader nor a crawler could
   walk back up. Found by crawling the live site, not by reading the templates. */

$p1 = q_one("SELECT * FROM places WHERE slug = 'p1'");
$back = rmt_nb_of_place($p1);
check('a place links back to its area', $back ? (int) $back['id'] : null, $paris['id']);
check('and carries the city slug the URL needs', (string) $back['dest_slug'], 'paris-france');

// An unresolved place has nowhere to send anyone, and says so by returning null rather than
// guessing a destination for a link.
q_run("UPDATE places SET neighborhood_id = NULL WHERE slug = 'p1'");
check('an unresolved place links nowhere',
      rmt_nb_of_place(q_one("SELECT * FROM places WHERE slug = 'p1'")), null);

// A borough is not a page we send people to as a neighborhood, so a place inside one gets no link
// from this function either -- the destination page offers it under its own heading instead.
$nycPlace = q_one("SELECT * FROM places WHERE slug = 'p6'");
check('a place in a borough gets no neighborhood link', rmt_nb_of_place($nycPlace), null);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
