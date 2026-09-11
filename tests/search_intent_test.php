<?php
/**
 * What a person obviously meant, as a permanent set of real queries.
 *
 * Every case below came from typing something into the live site and not getting the thing that
 * was clearly meant. They are kept as CLASSES of behaviour rather than as strings to satisfy:
 * "Park Guell" stands for every name that contains a category word, "Museu Geologico" for every
 * name somebody types without its accents, "Sagrada Familia" for every landmark whose official
 * name buries the part people actually say.
 *
 * The ranking is deliberately explainable and this file is where that is enforced. Two signals,
 * both checkable by looking at the names:
 *
 *   WHERE it matched   being the whole name beats starting with it beats containing it as a word
 *                      beats appearing inside a word
 *   HOW MUCH of it     the query's share of the name it matched
 *
 * There is nothing in here about what KIND of thing is being scored, and a test below fails if
 * anybody adds one.
 *
 *   php tests/search_intent_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/editorial.php';
require BASE_PATH . '/app/places.php';
require BASE_PATH . '/app/place_data.php';
require BASE_PATH . '/app/search_suggest.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

/* The real names, from production. A blog title, a city, and the places those queries should be
   finding instead of whatever the ranking happened to prefer. */
$SECTIONS = [
    'places' => [
        'Basilica de la Sagrada Familia', 'Placa de la Sagrada Familia', 'Park Guell',
        'Amstelpark', 'Museu Geologico', 'Rijksmuseum', 'Louvre Museum', 'Tokyo National Museum',
        'The Cheesecake Factory', 'Empire State Building', 'Anne Frank House',
    ],
    'dests' => ['Lisbon', 'Barcelona', 'Tokyo', 'Amsterdam', 'Miami'],
    'posts' => [
        'Sagrada Familia tickets 2026: 26 euros, 36 with towers, timed entry, now finished',
        'Park Guell ticket price 2026: 18 euros after an 80 percent jump',
        'Barcelona tourist tax 2026: 4.50 euros plus 5 euro city surcharge, still climbing',
    ],
    'people' => ['Maya Wanders', 'Diego Trails'],
];

/** Which section a query leads with. */
function leads(string $q): string {
    global $SECTIONS;
    return rmt_search_section_order($q, $SECTIONS)[0] ?? '';
}

echo "-- the landmark somebody obviously meant --\n";
/* The case this file was written for. The article's title genuinely begins with the query and the
   basilica's does not, so "starts with" alone answered a landmark search with a ticket price
   article. What decides it is that the query is half of the basilica's name and a fifth of the
   article's: the basilica is almost entirely the thing that was typed. */
ok(leads('Sagrada Familia') === 'places', 'a landmark beats an article about its ticket price');
ok(leads('Park Guell') === 'places', 'and so does one whose name contains a category word');

echo "\n-- a name typed the way people type it --\n";
ok(leads('Museu Geologico') === 'places', 'without the accent');
ok(leads('Museu Geológico') === 'places', 'and with it');
ok(leads('Rijksmuseum') === 'places', 'an exact name');
ok(leads('rijksmuseum') === 'places', 'in any case');
ok(leads('Louvre Museum') === 'places', 'a name in the language we hold it in');
ok(leads('Cheesecake Factory') === 'places', 'a name without the "The" nobody says');

echo "\n-- a city is a city --\n";
ok(leads('Lisbon') === 'dests', 'typing a city name answers with the city');
ok(leads('Barcelona') === 'dests', 'even when articles mention it in their titles');
ok(leads('Miami') === 'dests', 'including a new one');

echo "\n-- a person --\n";
ok(leads('Maya Wanders') === 'people', 'a full name finds the traveler');

echo "\n-- what must NOT happen --\n";
/* An article with a tight title beating a place with a rambling one is the CORRECT answer, and
   the day somebody "fixes" it by preferring places is the day this ranking stops being
   explainable. Pinned so that fix fails loudly. */
ok(rmt_search_section_order('tourist tax', [
       'posts'  => ['Tourist tax'],
       'places' => ['A place whose very long name happens to mention tourist tax somewhere'],
   ])[0] === 'posts', 'an exact title still beats a place that merely mentions it');
ok(rmt_search_section_order('museum', [
       'a' => ['Rijksmuseum'],
       'b' => ['Museum of Art'],
   ])[0] === 'b', 'a word boundary still beats a match buried inside a word');
ok(rmt_search_section_order('zzz not a thing', $SECTIONS) === array_keys($SECTIONS),
   'a query nothing matches leaves the order exactly as it was');
ok(rmt_search_section_order('', $SECTIONS) === array_keys($SECTIONS), 'and so does an empty one');

/* The ranking must not know what kind of thing it is scoring. If this ever fails, somebody has
   put a thumb on the scale for one content type, which is the thing we decided not to do. */
$src = (string) file_get_contents(BASE_PATH . '/app/search_suggest.php');
$fn = substr($src, strpos($src, 'function rmt_search_section_order'));
$fn = substr($fn, 0, strpos($fn, "\n}\n") ?: strlen($fn));
foreach (['places', 'dests', 'posts', 'people', 'trips', 'reviews'] as $type) {
    ok(!str_contains($fn, "'" . $type . "'"),
       "the scorer does not name '$type', or any other kind of thing");
}

/* And the two signals it does use are still the two it claims to use. */
ok(str_contains($fn, 'mb_strlen($needle) / max(1, mb_strlen($n))'),
   'completeness is the query share of the name, not a fudge factor');
ok(str_contains($fn, '$tier = 4.0;'), 'an exact name is still its own tier, far above the rest');

echo "\nsearch_intent_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
