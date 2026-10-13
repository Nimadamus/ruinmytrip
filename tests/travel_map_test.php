<?php
declare(strict_types=1);
/*
 * The travel map (app/travel_map.php): the key is the map, so the key has to be strict about what
 * it accepts and stable about what it produces; a save replaces the set; the card draws.
 */
define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/cards.php';
require BASE_PATH . '/app/travel_map.php';

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-52s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

echo "-- the geometry --\n";
$map = rmt_world_map();
ok('the map file loads', count($map['countries']) > 200, true);
$names = rmt_map_names();
ok('France is pickable by its ISO number', $names['250'] ?? null, 'France');
ok('Kosovo has a key of its own', $names['xk'] ?? null, 'Kosovo');
ok('Singapore is on the map even though it is tiny', $names['702'] ?? null, 'Singapore');
ok('abbreviated names are written out', in_array('Bosnia and Herzegovina', $names, true), true);
$bad = 0;
foreach ($map['countries'] as $c) foreach ($c['rings'] as $r) foreach ($r as $i => $v)
    if ($v < 0 || $v > ($i % 2 ? $map['h'] : $map['w'])) $bad++;
ok('every point is inside the grid', $bad, 0);

echo "\n-- the key --\n";
ok('sorted and deduplicated', rmt_map_codes('380-250-250'), ['250', '380']);
ok('commas and spaces work too', rmt_map_codes('250, 380'), ['250', '380']);
ok('an unknown code is dropped, not fatal', rmt_map_codes('250-999-abc'), ['250']);
ok('an array works', rmt_map_codes(['xk', '250']), ['250', 'xk']);
ok('an empty key is an empty map', rmt_map_codes(''), []);
ok('anything that is not a code is dropped', rmt_map_codes("250'); DROP TABLE users;--"), []);
ok('one set of countries is one key', rmt_map_key(rmt_map_codes('380-250')), rmt_map_key(rmt_map_codes('250,380')));
ok('the headline counts', rmt_map_headline(['250']), "I've been to 1 country");
ok('and plurals', rmt_map_headline(['250', '380']), "I've been to 2 countries");
ok('the card link is absolute', rmt_map_card_url(['250', '380']), 'https://ruinmytrip.com/card/map/250-380.png');
ok('no countries still has a card', rmt_map_card_url([]), 'https://ruinmytrip.com/card/map/none.png');

echo "\n-- saving --\n";
$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
$pdo->exec("INSERT INTO users (id) VALUES (1), (2)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/103_travel_map.sqlite.sql'));
rmt_map_save(1, ['250', '380']);
ok('a save is read back', rmt_map_for_user(1), ['250', '380']);
rmt_map_save(1, ['392']);
ok('a second save replaces the set', rmt_map_for_user(1), ['392']);
ok('one member does not see another', rmt_map_for_user(2), []);
rmt_map_save(1, []);
ok('saving nothing clears it', rmt_map_for_user(1), []);

echo "\n-- the card --\n";
if (rmt_card_available()) {
    $png = rmt_map_card_png(['250', '702']);
    ok('the card is a PNG', substr($png, 1, 3), 'PNG');
    $im = imagecreatefromstring($png);
    ok('1200 wide', imagesx($im), 1200);
    ok('630 high', imagesy($im), 630);
} else {
    echo "  (GD or the font is missing here; card not drawn)\n";
}

echo "-- a city page's country, for its \"I've been\" button --\n";
ok('Portugal', rmt_map_country_code('Portugal'), '620');
ok('case and spaces do not matter', rmt_map_country_code('  portugal '), '620');
ok('United States is the map\'s United States of America', rmt_map_country_code('United States'), '840');
ok('Czechia is the map\'s Czech Republic', rmt_map_country_code('Czechia'), '203');
ok('Türkiye is the map\'s Turkey', rmt_map_country_code('Türkiye'), '792');
ok('an unknown country gets no button', rmt_map_country_code('Atlantis'), null);
ok('no country gets no button', rmt_map_country_code(null), null);
ok('the code it gives is one the map reads back', rmt_map_codes(rmt_map_country_code('Mexico')), [rmt_map_country_code('Mexico')]);

echo "-- the cities to review under the map --\n";
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, name TEXT, country TEXT)");
$pdo->exec("INSERT INTO destinations (id, name, country) VALUES (1, 'Porto', 'Portugal'), (2, 'Lisbon', 'Portugal'), (3, 'Oaxaca', 'Mexico'), (4, 'Nowhere', 'Atlantis'), (5, 'Miami', 'United States')");
$rc = rmt_map_review_cities();
ok('cities are grouped by map country, in name order', $rc['620'] ?? null, [[2, 'Lisbon'], [1, 'Porto']]);
ok('a city whose country is not on the map is left out', in_array('Nowhere', array_merge(...array_map(fn($l) => array_column($l, 1), array_values($rc))), true), false);
ok('an alias country still finds its cities', $rc['840'] ?? null, [[5, 'Miami']]);
ok('one map picks its cities across countries, by name', rmt_map_review_pick(['620', '484']), [[2, 'Lisbon'], [3, 'Oaxaca'], [1, 'Porto']]);
ok('the pick is capped', count(rmt_map_review_pick(['620', '484'], 2)), 2);
ok('no countries, no cities', rmt_map_review_pick([]), []);
ok('a country with no city page gives nothing', rmt_map_review_pick(['392']), []);

echo "\n", $fail ? "FAIL: $fail case(s) failed, $pass passed\n" : "ALL TRAVEL MAP TESTS PASS ($pass)\n";
exit($fail ? 1 : 0);
