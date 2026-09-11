<?php
/**
 * The place importer: real data, one row per place, and nothing invented.
 *
 * An importer is the one piece of code on this site that can quietly fill a table with rubbish, so
 * the rules are pinned rather than trusted:
 *
 *   - a provider may write a name, a category, a location and contact details, and NOTHING that is
 *     a claim about quality. Ratings and popularity on this site are written by its members; a
 *     number from a provider sitting next to them would be a lie about who said it
 *   - running it twice creates nothing the second time. Three matching passes: the provider's own
 *     record id, the normalised name in that city, an alias, then the same name within sixty metres
 *   - a value a person typed is never overwritten by a provider
 *   - an unknown provider, an unknown type, and a nameless record are all refused
 *
 *   php tests/place_import_test.php
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
require BASE_PATH . '/app/place_import.php';
require BASE_PATH . '/app/place_provider_osm.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$pdo = db();
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, country TEXT,
              lat REAL, lng REAL)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT, slug TEXT,
              name TEXT, name_key TEXT, type TEXT, created_by INT, status TEXT DEFAULT 'active',
              created_at TEXT, updated_at TEXT, category_id INT, street_address TEXT,
              neighborhood TEXT, region TEXT, postal_code TEXT, lat REAL, lng REAL, phone TEXT,
              website_url TEXT, price_level INT, timezone TEXT, data_source TEXT,
              data_source_url TEXT, data_checked_at TEXT, name_norm TEXT, neighborhood_id INT,
              source_ref TEXT, source_updated_at TEXT, source_kind TEXT)");
$pdo->exec("CREATE TABLE place_aliases (id INTEGER PRIMARY KEY AUTOINCREMENT, place_id INT,
              alias TEXT, alias_key TEXT, source TEXT, created_at TEXT)");
$pdo->exec("INSERT INTO destinations VALUES (1,'Lisbon','lisbon','Portugal',38.7223,-9.1393)");

$osm = static fn(array $over): array => $over + [
    'name' => 'Museu do Azulejo', 'type' => 'attraction', 'lat' => 38.7248, 'lng' => -9.1136,
    'data_source' => 'openstreetmap', 'source_ref' => 'node/1', 'data_source_url' => 'https://osm/1',
];

// --- what a provider may and may not write -------------------------------------------------------
$clean = rmt_place_import_clean($osm([
    'rating' => 4.7, 'review_count' => 1200, 'popularity' => 99, 'rank' => 3, 'phone' => '+351 1',
]));
ok(!isset($clean['data']['rating']), 'a rating from a provider is refused');
ok(!isset($clean['data']['review_count']), 'so is a review count');
ok(!isset($clean['data']['popularity']) && !isset($clean['data']['rank']), 'and popularity, and a rank');
ok($clean['data']['phone'] === '+351 1', 'a phone number is a fact and is kept');
ok(count($clean['refused']) === 4, 'and everything refused is reported rather than dropped silently');

/* The whitelist itself, checked by name. A future edit that adds "rating" to the allowed list is
   the failure this catches, and it would otherwise never show up in any other test. */
$banned = ['rating', 'rating_count', 'reviews', 'review_count', 'popularity', 'rank', 'score',
           'stars', 'recommended'];
ok(array_intersect($banned, RMT_PLACE_IMPORT_FIELDS) === [],
   'the allowed field list contains no claim about quality');

// --- creating -------------------------------------------------------------------------------------
$r = rmt_place_import_one(1, $osm([]), true);
ok($r['action'] === 'create', 'a place we do not hold is a create');
ok((int) (q_one('SELECT COUNT(*) c FROM places')['c'] ?? 0) === 0, 'and a dry run writes nothing');

$r = rmt_place_import_one(1, $osm([]));
ok($r['action'] === 'create' && $r['place_id'] > 0, 'a real run creates it');
$row = q_one('SELECT * FROM places WHERE id = ?', [$r['place_id']]);
ok((string) $row['data_source'] === 'openstreetmap' && (string) $row['source_ref'] === 'node/1',
   'the provider and its record id are stored, which is what makes the next run an update');
ok((string) $row['status'] === 'active', 'and it is published');

// --- the four ways a second record is recognised as the same place ---------------------------------
$r = rmt_place_import_one(1, $osm([]));
ok($r['action'] === 'update' && $r['how'] === 'source_ref', 'the same record again is an update');
ok((int) (q_one('SELECT COUNT(*) c FROM places')['c'] ?? 0) === 1, 'and there is still one place');

$r = rmt_place_import_one(1, $osm(['source_ref' => 'node/99']));
ok($r['how'] === 'name', 'the same name in the same city is the same place, whatever its id');

rmt_place_alias_add((int) $row['id'], 'Tile Museum', 'openstreetmap');
$r = rmt_place_import_one(1, $osm(['name' => 'Tile Museum', 'source_ref' => 'node/98']));
ok($r['how'] === 'alias', 'a name we already know it by is the same place');

$r = rmt_place_import_one(1, $osm(['name' => 'Museu do Azulejo de Lisboa', 'source_ref' => 'node/97',
                                   'lat' => 38.72481, 'lng' => -9.11362]));
ok($r['how'] === 'nearby', 'a near-identical name in the same doorway is the same place');
ok((int) (q_one('SELECT COUNT(*) c FROM places')['c'] ?? 0) === 1, 'so none of those made a second row');

$r = rmt_place_import_one(1, $osm(['name' => 'Cervejaria Ramiro', 'source_ref' => 'node/96',
                                   'lat' => 38.72481, 'lng' => -9.11362, 'type' => 'restaurant']));
ok($r['action'] === 'create', 'a different place at the same address is a different place');

// --- merging is timid -------------------------------------------------------------------------------
$pdo->exec("UPDATE places SET phone = '+351 HUMAN TYPED THIS' WHERE id = " . (int) $row['id']);
$r = rmt_place_import_one(1, $osm(['phone' => '+351 999 999 999', 'source_ref' => 'node/1']));
$after = q_one('SELECT phone, street_address FROM places WHERE id = ?', [$row['id']]);
ok((string) $after['phone'] === '+351 999 999 999',
   'the provider that owns the row may refresh what it put there');

$pdo->exec("UPDATE places SET data_source = 'somebody_else' WHERE id = " . (int) $row['id']);
$pdo->exec("UPDATE places SET phone = '+351 SOMEBODY ELSE' WHERE id = " . (int) $row['id']);
$r = rmt_place_import_one(1, $osm(['phone' => '+351 000', 'source_ref' => 'node/1']));
$after = q_one('SELECT phone FROM places WHERE id = ?', [$row['id']]);
ok((string) $after['phone'] === '+351 SOMEBODY ELSE',
   'a value another source supplied is left alone rather than fought over');
ok(in_array('phone', $r['kept'], true), 'and the disagreement is reported so a person can look');

// --- refusals ---------------------------------------------------------------------------------------
ok(rmt_place_import_one(1, $osm(['name' => '']))['error'] !== null, 'a record with no name is refused');
ok(rmt_place_import_one(1, $osm(['type' => 'nightclub']))['error'] !== null, 'an unknown type is refused');
ok(rmt_place_import_one(1, $osm(['data_source' => 'tripadvisor']))['error'] !== null,
   'an unregistered provider is refused, whatever it claims to be');
ok(rmt_place_import_one(0, $osm([]))['error'] !== null, 'and a record with no city has nowhere to go');

// --- the OSM mapping --------------------------------------------------------------------------------
$el = ['type' => 'node', 'id' => 4242, 'lat' => 38.71, 'lon' => -9.14, 'tags' => [
    'name' => 'Time Out Market', 'amenity' => 'restaurant', 'addr:street' => 'Avenida 24 de Julho',
    'addr:housenumber' => '49', 'addr:postcode' => '1200-479', 'contact:phone' => '+351 210 607 403',
    'website' => 'timeoutmarket.com', 'alt_name' => 'Mercado da Ribeira',
]];
$m = rmt_osm_to_place($el);
ok($m['row']['type'] === 'restaurant', 'an OSM amenity maps to one of our four types');
ok($m['row']['street_address'] === '49 Avenida 24 de Julho', 'the address is assembled, not invented');
ok($m['row']['website_url'] === 'https://timeoutmarket.com', 'a bare domain becomes a usable link');
ok($m['row']['source_ref'] === 'node/4242', 'the OSM object is recorded so it can be checked');
ok($m['aliases'] === ['Mercado da Ribeira'], 'and the other name it goes by is kept');

ok(rmt_osm_to_place(['type' => 'node', 'id' => 1, 'tags' => ['amenity' => 'bench']])['row'] === null,
   'a bench is not a place');
ok(rmt_osm_to_place(['type' => 'node', 'id' => 1, 'tags' => ['amenity' => 'restaurant']])['row'] === null,
   'and neither is an unnamed restaurant');

// Attribution is a condition of the licence, so the provider has to carry one.
foreach (rmt_place_providers() as $slug => $prov) {
    ok(trim((string) ($prov['attribution'] ?? '')) !== '', "$slug states how it must be credited");
    ok(trim((string) ($prov['url'] ?? '')) !== '', "$slug links its licence");
}

/* Categories. The four types are what the database calls things; a category is what a person
   calls them, and a cafe and a steakhouse are both restaurants to the database and are not the
   same thing to somebody deciding where to have breakfast. */
$pdo->exec("CREATE TABLE place_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, bucket TEXT,
              slug TEXT, name TEXT, plural TEXT, sort INT, status TEXT DEFAULT 'active')");
$pdo->exec("INSERT INTO place_categories (bucket,slug,name,plural,sort,status) VALUES
              ('restaurant','cafe','Cafe','Cafes',1,'active'),
              ('attraction','museum','Museum','Museums',2,'active'),
              ('experience','stadium','Stadium','Stadiums',3,'active')");

ok(rmt_osm_category_slug('cafe') === 'cafe', 'a cafe is a cafe, not just a restaurant');
/* The mappings that are deliberately absent, because a precise word that is wrong is worse than a
   coarse one that is right. Every one of these was in the first version and made a page lie. */
ok(rmt_osm_category_slug('restaurant') === null, 'a plain restaurant is not a bistro');
ok(rmt_osm_category_slug('hotel') === null, 'and a hotel is not a luxury hotel');
ok(rmt_osm_category_slug('cinema') === null, 'a cinema is not a theatre');
ok(rmt_osm_category_slug('artwork') === null, 'and a piece of public art is not a landmark');
ok(rmt_osm_category_slug('memorial') === 'landmark', 'a memorial is a landmark a person would look for');
ok(rmt_osm_category_slug('stadium') === 'stadium', 'and a stadium has a word of its own now');
ok(rmt_osm_category_slug('bench') === null, 'a tag with no good category gets none rather than a guess');

$el = ['type' => 'node', 'id' => 7001, 'lat' => 38.7, 'lon' => -9.1,
       'tags' => ['name' => 'A Brasileira', 'amenity' => 'cafe']];
$m = rmt_osm_to_place($el);
ok($m['row']['type'] === 'restaurant', 'the coarse bucket is still the coarse bucket');
ok($m['row']['source_kind'] === 'cafe', "and the provider's own word is kept, so this can be redone later");
ok($m['row']['category_slug'] === 'cafe', 'with the category a reader would use');

$r = rmt_place_import_one(1, $m['row']);
$row = q_one('SELECT category_id, source_kind FROM places WHERE id = ?', [$r['place_id']]);
ok((string) $row['source_kind'] === 'cafe', 'the raw kind lands in the row');
$cat = q_one('SELECT slug FROM place_categories WHERE id = ?', [(int) $row['category_id']]);
ok($cat && $cat['slug'] === 'cafe', 'and the category is resolved to the taxonomy this site has');

/* A category the taxonomy does not define is dropped rather than stored as a dangling id. */
$el2 = $el;
$el2['id'] = 7002;
$el2['tags'] = ['name' => 'Teatro Nacional', 'amenity' => 'theatre'];
$m2 = rmt_osm_to_place($el2);
$r2 = rmt_place_import_one(1, $m2['row']);
$row2 = q_one('SELECT category_id, source_kind FROM places WHERE id = ?', [$r2['place_id']]);
ok((string) $row2['source_kind'] === 'theatre', 'the kind is still kept');
ok($row2['category_id'] === null, 'and an undefined category is left empty rather than invented');

/* Records that are not fit to be a page. Every rule here is about the RECORD, never about the
   venue: a place is refused for being malformed, never for being obscure. A refusal carries a
   reason so a person can decide whether the rule or the data is wrong. */
$bad = static fn(array $over): ?string => rmt_place_record_fault($osm($over), 1);

ok($bad([]) === null, 'a good record passes');
ok($bad(['name' => 'X']) !== null, 'a one character name is not a name');
ok($bad(['name' => 'https://example.com']) !== null, 'a URL in the name field is refused');
ok($bad(['name' => '---']) !== null, 'and so is a name with no letters in it');
ok($bad(['name' => 'CafÃ© Central']) !== null, 'a name decoded through the wrong charset is refused');
ok($bad(['lat' => 91.0]) !== null, 'a coordinate off the planet is refused');
ok($bad(['lat' => 0.0, 'lng' => 0.0]) !== null, 'and so is null island, which is what a zero looks like');
ok($bad(['lat' => 38.72, 'lng' => null]) !== null, 'half a coordinate is not a location');
ok($bad(['lat' => 55.95, 'lng' => -3.19]) !== null, 'Edinburgh is not in Lisbon, whatever the record says');
ok($bad(['website_url' => 'not a url']) !== null, 'a website that is not a URL is refused');
ok($bad(['website_url' => 'https://ok.example']) === null, 'and a real one is fine');

/* And the refusal reaches the caller rather than being swallowed. */
$r = rmt_place_import_one(1, $osm(['name' => 'https://spam.example', 'source_ref' => 'node/500']));
ok($r['error'] !== null && str_contains($r['error'], 'URL in the name'),
   'the reason a record was refused is reported');
ok((int) (q_one("SELECT COUNT(*) c FROM places WHERE source_ref = 'node/500'")['c'] ?? 0) === 0,
   'and nothing was written for it');

/* The data quality audit, as a set of rules rather than a habit. Each of these is a shape of bad
   record that a real import has produced somewhere, and every one is about the RECORD: a place is
   quarantined for being malformed, never for being unpopular or obscure. */
$quarantined = [];
foreach ([
    ['name' => '', 'why' => 'blank name'],
    ['name' => ' ', 'why' => 'whitespace name'],
    ['name' => 'http://spam.example', 'why' => 'a URL in the name'],
    ['name' => '???', 'why' => 'no letters'],
    ['name' => 'Ã‰glise', 'why' => 'mojibake'],
    ['lat' => 200.0, 'why' => 'impossible latitude'],
    ['lng' => 999.0, 'why' => 'impossible longitude'],
    ['lat' => 0.0, 'lng' => 0.0, 'why' => 'null island'],
    ['website_url' => 'javascript:alert(1)', 'why' => 'a website that is not http'],
] as $case) {
    $why = $case['why'];
    unset($case['why']);
    $case['source_ref'] = 'node/' . mt_rand(100000, 999999);
    $r = rmt_place_import_one(1, $osm($case));
    if ($r['error'] === null) $quarantined[] = $why;
}
ok($quarantined === [], 'every malformed shape is quarantined at the door'
   . ($quarantined ? ': ' . implode(', ', $quarantined) : ''));

/* And a good record is not caught by any of them, which is the half that matters: an audit that
   refuses everything is not an audit. */
$r = rmt_place_import_one(1, $osm(['name' => 'Cantina Zé dos Cornos', 'source_ref' => 'node/771',
                                   'lat' => 38.715, 'lng' => -9.137, 'website_url' => 'https://ok.example']));
ok($r['error'] === null, 'a real name with accents and a real website is accepted');

echo "place_import_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
