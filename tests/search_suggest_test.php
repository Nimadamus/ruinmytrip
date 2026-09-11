<?php
/**
 * Autocomplete: normalisation, aliases, ranking order, typo tolerance, logging.
 *
 * Ranking is the feature. Most of what follows is not "does it return something" but "does it
 * return the RIGHT thing first", because a suggestion list whose top row is wrong is worse than no
 * suggestion list at all — it is confidently unhelpful, and people stop reading it.
 *
 *   php tests/search_suggest_test.php
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
require BASE_PATH . '/app/search_suggest.php';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-58s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

/** The name of the first suggestion of any type, or null. */
function top(string $q): ?string {
    $r = rmt_search_suggest($q);
    foreach ($r['groups'] as $g) {
        foreach ($g['items'] as $i) return $i['name'];
    }
    return null;
}

/** Every suggestion name, in the order a reader sees them. */
function names(string $q): array {
    $out = [];
    foreach (rmt_search_suggest($q)['groups'] as $g) {
        foreach ($g['items'] as $i) $out[] = $i['name'];
    }
    return $out;
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT)");
$pdo->exec("CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, avatar_url TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, region TEXT, hero_url TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT, slug TEXT UNIQUE,
            name TEXT, name_key TEXT, type TEXT, status TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
            place_id INT, status TEXT, rating INT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY, review_id INT, url TEXT, storage_key TEXT, caption TEXT, sort INT, created_at TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/047_place_attributes.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/050_search_suggest.sqlite.sql'));

$pdo->exec("INSERT INTO users (id,username,role,status) VALUES (1,'wanderjane','user','active'),(2,'ruinmytrip','" . RMT_EDITORIAL_ROLE . "','active')");
$pdo->exec("INSERT INTO profiles (user_id, display_name) VALUES (1,'Jane Traveler')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country,region) VALUES
    (1,'vienna-austria','Vienna','Austria','Vienna'),
    (2,'amsterdam-netherlands','Amsterdam','Netherlands','North Holland'),
    (3,'milan-italy','Milan','Italy','Lombardy'),
    (4,'las-vegas-usa','Las Vegas','United States','Nevada')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,created_at) VALUES
    (1,2,'rijksmuseum-amsterdam','Rijksmuseum','rijksmuseum','attraction','active','2026-08-01'),
    (2,2,'van-gogh-museum-amsterdam','Van Gogh Museum','van gogh museum','attraction','active','2026-08-01'),
    (3,3,'duomo-di-milano','Duomo di Milano','duomo di milano','attraction','active','2026-08-01'),
    (4,1,'cafe-savoy-vienna','Café Savoy','cafe savoy','restaurant','active','2026-08-01'),
    (5,4,'bellagio-las-vegas','Bellagio','bellagio','hotel','active','2026-08-01')");

// Alternative names, exactly as enrichment stores them from OpenStreetMap.
$now = '2026-08-01 00:00:00';
rmt_search_backfill_norm();
rmt_search_add_alias('destination', 1, 'Wien', 'local_name');
rmt_search_add_alias('destination', 4, 'Vegas', 'abbreviation');
rmt_search_add_alias('place', 3, 'Milan Cathedral', 'local_name');
rmt_search_add_alias('place', 2, 'Rijksmuseum Vincent van Gogh', 'local_name');

// Van Gogh is the more reviewed of the two, so popularity has something to try to win with.
for ($i = 0; $i < 40; $i++) $pdo->exec("INSERT INTO reviews (user_id,destination_id,place_id,status,rating) VALUES (1,2,2,'published',5)");
for ($i = 0; $i < 2; $i++)  $pdo->exec("INSERT INTO reviews (user_id,destination_id,place_id,status,rating) VALUES (1,2,1,'published',5)");

echo "-- normalisation --\n";
check('lowercased',            rmt_search_norm('RIJKSMUSEUM'), 'rijksmuseum');
check('accents folded',        rmt_search_norm('Café Savoy'), 'cafe savoy');
check('umlaut folded',         rmt_search_norm('München'), 'munchen');
check('eszett expands',        rmt_search_norm('Straße'), 'strasse');
check('nordic folded',         rmt_search_norm('København'), 'kobenhavn');
check('polish folded',         rmt_search_norm('Kraków'), 'krakow');
check('punctuation collapses', rmt_search_norm("St. Paul's-Cathedral"), 'st paul s cathedral');
check('spaces collapse',       rmt_search_norm('  a   b  '), 'a b');
check('empty stays empty',     rmt_search_norm('   '), '');
check('normalising twice changes nothing', rmt_search_norm(rmt_search_norm('Café Savoy')), 'cafe savoy');

echo "\n-- the name column is a copy, never a replacement --\n";
check('the display name still has its accent',
      (string) q_one("SELECT name FROM places WHERE slug='cafe-savoy-vienna'")['name'], 'Café Savoy');
check('the matched form does not',
      (string) q_one("SELECT name_norm FROM places WHERE slug='cafe-savoy-vienna'")['name_norm'], 'cafe savoy');

echo "\n-- exact, prefix, token --\n";
check('exact name',         top('Rijksmuseum'), 'Rijksmuseum');
check('prefix',             top('rijks'), 'Rijksmuseum');
check('accentless input finds the accented name', top('cafe sav'), 'Café Savoy');
check('accented input works too',                 top('Café Sav'), 'Café Savoy');
check('a word inside the name',                   top('savoy'), 'Café Savoy');
check('destination prefix', top('vien'), 'Vienna');
check('two-letter query is answered',             top('be'), 'Bellagio');
check('one letter is not a query',                rmt_search_suggest('b')['count'], 0);
check('empty is not a query',                     rmt_search_suggest('')['count'], 0);

echo "\n-- intent beats popularity --\n";
// Van Gogh has 40 reviews to Rijksmuseum's 2, and one of its recorded names begins with "rijks".
// The place whose OWN name starts with the query has to win anyway.
check('the right museum leads', top('rijks'), 'Rijksmuseum');
check('...and the popular one is still offered', in_array('Van Gogh Museum', names('rijks'), true), true);
check('an exact name beats a more reviewed alias match', top('rijksmuseum'), 'Rijksmuseum');

echo "\n-- aliases --\n";
check('local name finds the destination', top('wien'), 'Vienna');
check('abbreviation finds it',            top('vegas'), 'Las Vegas');
check('an English name finds a local one', top('milan cathedral'), 'Duomo di Milano');
check('the alias is not what gets shown',  top('milan cathedral') === 'Milan Cathedral', false);
check('adding the same alias twice is a no-op', rmt_search_add_alias('destination', 1, 'wien'), false);
check('an empty alias is refused',              rmt_search_add_alias('destination', 1, '   '), false);

echo "\n-- typo tolerance --\n";
check('a dropped letter still finds it', top('rijksmusem'), 'Rijksmuseum');
check('a transposition still finds it',  top('bellagoi'), 'Bellagio');
check('a short fragment is not fuzzy-matched into noise',
      in_array('Bellagio', names('bel'), true), true);
check('nonsense finds nothing',          rmt_search_suggest('zzzzzzq')['count'], 0);
check('a three-letter query does not fuzzy-match',
      rmt_suggest_fuzzy_portable('places', 'par', 5), []);

echo "\n-- what a suggestion carries --\n";
$res = rmt_search_suggest('rijks');
$first = $res['groups'][0]['items'][0];
check('has a url',        str_contains((string) $first['url'], '/p/rijksmuseum-amsterdam'), true);
check('says what it is',  str_contains((string) $first['subtitle'], 'Amsterdam'), true);
check('and where it is',  str_contains((string) $first['subtitle'], 'Netherlands'), true);
// What actually leaves the server is the projection the endpoint uses, so test that and not the
// internal row: scores and tiers are how ranking works, not something a client should see.
$pub = rmt_suggest_public($res)['groups'][0]['items'][0];
check('the client gets exactly five fields', array_keys($pub), ['type','id','name','subtitle','url']);
check('no score leaves the server', array_key_exists('score', $pub), false);
check('no internal tier leaves it either', array_key_exists('tier', $pub), false);

echo "\n-- grouping --\n";
$vienna = rmt_search_suggest('vien');
$labels = array_column($vienna['groups'], 'label');
check('destinations are grouped', in_array('Destinations', $labels, true), true);
check('no empty group is emitted',
      array_values(array_filter($vienna['groups'], static fn($g) => !$g['items'])), []);
check('the list stays readable', $vienna['count'] <= RMT_SUGGEST_LIMIT + 3, true);

echo "\n-- explore suggestions --\n";
$ams = rmt_search_suggest('amsterdam');
$explore = [];
foreach ($ams['groups'] as $g) { if ($g['label'] === 'Explore') $explore = $g['items']; }
check('a confident destination offers browse links', count($explore) > 0, true);
check('...pointing at a page that exists',
      str_contains((string) ($explore[0]['url'] ?? ''), '/d/amsterdam-netherlands/places'), true);
check('...with a type filter', str_contains((string) ($explore[0]['url'] ?? ''), 'type='), true);
// Vienna has one restaurant and no hotels: the hotel link must not be offered.
$vie = [];
foreach (rmt_search_suggest('vienna')['groups'] as $g) { if ($g['label'] === 'Explore') $vie = $g['items']; }
check('no browse link for a category with nothing in it',
      count(array_filter($vie, static fn($i) => str_contains((string) $i['url'], 'type=hotel'))), 0);

echo "\n-- users --\n";
check('a username is findable',     top('wanderjane'), 'Jane Traveler');
check('a display name is findable', top('jane trav'), 'Jane Traveler');

echo "\n-- injection and abuse --\n";
check("a quote is just text",        rmt_search_suggest("' OR 1=1 --")['count'], 0);
check('a percent is not a wildcard', rmt_search_suggest('%')['count'], 0);
check('an underscore is not a wildcard', rmt_search_suggest('_')['count'], 0);
check('a wildcard cannot list the table',
      rmt_search_suggest(str_repeat('%', 5))['count'], 0);
check('escaping is applied',         rmt_search_like('100%_x'), '100!%!_x');
// The escape character is '!' because a backslash in the SQL confuses PDO's own placeholder
// parser on Postgres: it can read the backslash as escaping the closing quote, think the string
// is still open, and swallow the following ? -- which is how /suggest 500'd in production while
// every local test passed.
check('the escape char escapes itself', rmt_search_like('a!b'), 'a!!b');
check('a backslash is left alone',      rmt_search_like('a\\b'), 'a\\b');

echo "\n-- logging --\n";
rmt_search_log('kyoto ryokan', 0);
rmt_search_log('kyoto ryokan', 0);
rmt_search_log('rijks', 2);
rmt_search_log_click('rijks', 'place', '1', 0);
$zero = rmt_search_zero_results(30, 10);
check('a zero-result query is queued', $zero[0]['query_norm'] ?? null, 'kyoto ryokan');
check('...and counted',                (int) ($zero[0]['searches'] ?? 0), 2);
check('a query that found things is not in the zero list',
      in_array('rijks', array_column($zero, 'query_norm'), true), false);
check('the click was recorded',
      (int) q_one("SELECT COUNT(*) c FROM search_log WHERE clicked_type='place'")['c'], 1);
check('no user column exists to fill in',
      in_array('user_id', array_column($pdo->query("PRAGMA table_info(search_log)")->fetchAll(), 'name'), true), false);
check('an over-long query is not logged',
      (function () { rmt_search_log(str_repeat('a', 200), 0);
                     return (int) q_one("SELECT COUNT(*) c FROM search_log WHERE query_norm LIKE 'aaaa%'")['c']; })(), 0);

echo "\n-- backfill is idempotent --\n";
$again = rmt_search_backfill_norm();
check('a second pass changes nothing', $again, ['destinations' => 0, 'places' => 0, 'aliases' => 0]);

/* ------------------------------------------------------------------ suggestable
   The gate in front of the missing-place queue. A false accept costs one row somebody dismisses;
   a false reject costs a real place we never hear about again -- so this leans toward accepting,
   and the tests pin both the leaning and the floor. */

// Things that look like the name of a venue, including ones we would never guess at ourselves.
foreach ([
    'Kyubey Ginza',
    'The Ivy',
    'St Regis',
    'Nyx Bar',
    'Cafe de Flore',
    'Café de Flore',              // accents survive the shape test
    'Hôtel Étoile du Nord',
    'Museu Nacional de Arte Antiga',
    "Katz's Delicatessen",
    'Bar 1930',                   // digits are fine when letters carry the name
    'Test Kitchen',               // "test" as a real word inside a real name
] as $q) {
    check('suggestable: ' . $q, rmt_search_suggestable($q), true);
}

// Things that are not a name, and would make the human queue unreadable.
foreach ([
    ''            => 'empty',
    'ab'          => 'too short',
    'the'         => 'bare stopword',
    'test'        => 'bare test string',
    'hello'       => 'bare greeting',
    'asdfgh'      => 'keyboard mash, no vowel',
    'bcdfghjk'    => 'consonant run',
    '????'        => 'punctuation only',
    '2024'        => 'digits only',
    '!!! ???'     => 'punctuation with spaces',
    'https://maps.google.com/x' => 'pasted link',
    'www.example.com'           => 'pasted host',
    'someone@example.com'       => 'address, not a place name',
    'a b c d e f g h i j k l m' => 'pasted sentence',
] as $q => $why) {
    $q = (string) $q;   // PHP turns a numeric array key into an int, and "2024" is a real query
    check('not suggestable (' . $why . '): ' . ($q === '' ? '<empty>' : $q), rmt_search_suggestable($q), false);
}

// A name at the length boundary is accepted; a paragraph is not.
check('suggestable: 80 chars is allowed', rmt_search_suggestable(str_repeat('ab', 40)), true);
check('not suggestable: over 80 chars', rmt_search_suggestable(str_repeat('ab', 41)), false);

// Whitespace is not meaning: the same query padded and doubled-spaced decides the same way.
check('suggestable: whitespace normalised', rmt_search_suggestable('   The   Ivy   '), true);

echo "\n-- found by the kind you asked for --\n";
/* "museum tokyo" is not a name, it is a request for a kind, and full text over a name and an
   address answers it with the one museum whose name happens to be in English. The site already
   knows which of its places are museums, so it should say so. */
$pdo->exec("DELETE FROM place_categories");
$pdo->exec("INSERT INTO place_categories (id,slug,name,plural,bucket,status)
            VALUES (1,'museum','Museum','Museums','attraction','active'),
                   (2,'bar','Bar','Bars','restaurant','active')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,category_id)
            VALUES (900,2,'rijks','Rijksmuseum','rijksmuseum','attraction','active',1),
                   (901,2,'stedel','Stedelijk','stedelijk','attraction','active',1),
                   (902,1,'leopold','Leopold Museum','leopold museum','attraction','active',1),
                   (903,2,'brouwerij','Brouwerij','brouwerij','restaurant','active',2)");
$names = static fn(array $rows): array => array_map(static fn($r) => (string) $r['name'], $rows);

$got = $names(rmt_places_by_kind_words('museums amsterdam'));
sort($got);
check('a kind and a city answer with that kind in that city', $got, ['Rijksmuseum', 'Stedelijk']);
check('the singular is the same request', count(rmt_places_by_kind_words('museum amsterdam')), 2);
check('a city in context narrows it without being typed',
      $names(rmt_places_by_kind_words('bars', 2)), ['Brouwerij']);
$att = rmt_places_by_kind_words('attractions amsterdam');
check('a coarse type is a kind too',
      $att !== [] && count(array_filter($att, static fn($r) => (string) $r['type'] !== 'attraction')) === 0, true);
check('a word that names no kind asks for nothing', rmt_places_by_kind_words('rooftop terrace'), []);
check('and neither does an empty query', rmt_places_by_kind_words(''), []);

echo "\n-- which list leads --\n";
/* A fixed section order answers a place search with the sixth heading. The rule is by name, and
   it is deliberately simple, because a ranking nobody can predict is a ranking nobody trusts. */
$order = static fn(string $q): array => rmt_search_section_order($q, [
    'people' => ['Jane Traveler'],
    'dests'  => ['Amsterdam', 'Vienna'],
    'places' => ['Sagrada Familia', 'Rijksmuseum'],
    'talk'   => [],
]);
check('an exact city name leads with cities', $order('Amsterdam')[0], 'dests');
check('a place typed in full leads with places', $order('Sagrada Familia')[0], 'places');
check('a partial place name leads with places too', $order('Rijks')[0], 'places');
check('a person leads with travelers', $order('Jane')[0], 'people');
check('a word nobody is named keeps the order it had', $order('zzzz'), ['people','dests','places','talk']);
check('and so does an empty query', $order(''), ['people','dests','places','talk']);
/* A section matching only in the middle of a word ranks below one matching at a word boundary:
   "museum" is a word in "Rijksmuseum" but it does not begin one. */
check('a word boundary beats a match buried inside a word',
      rmt_search_section_order('museum', ['a' => ['Rijksmuseum'], 'b' => ['Museum of Art']])[0], 'b');
/* Within a tier, how much of the name the query accounts for decides it. Both of these match at a
   word boundary; "sagrada familia" is half of one and a fifth of the other. It is deliberately
   worth less than a whole tier, so it can order two equal matches and can never promote a worse
   one past a better one, which is the property that keeps the ranking predictable. */
check('how much of the name you typed breaks a tie inside a tier',
      rmt_search_section_order('sagrada familia', [
          'long'  => ['A very long name that mentions the Sagrada Familia somewhere in the middle of it'],
          'short' => ['Basilica de la Sagrada Familia'],
      ])[0], 'short');
check('and it never crosses a tier',
      rmt_search_section_order('rijks', [
          'contains' => ['A page about the Rijks'],
          'starts'   => ['Rijksmuseum and a great deal of other text in the title as well'],
      ])[0], 'starts');

echo "\n-- a partial name, and an accent nobody types --\n";
/* Three queries that answered with nothing at all on the results page while the suggestion box
   had been finding them since it shipped: a partial name, the accent left off, and the accent put
   on. Full text lexes whole words and matches the name as written; this matches the normalised
   copy the suggestion box has always used. */
$pdo->exec("UPDATE places SET name = 'Museu Geológico', name_key = 'museu geologico', name_norm = 'museu geologico' WHERE id = 900");
$pdo->exec("UPDATE places SET name_norm = 'stedelijk' WHERE id = 901");
$pdo->exec("UPDATE places SET name_norm = 'brouwerij' WHERE id = 903");
$byName = static fn(string $q): array =>
    array_map(static fn(array $r) => (string) $r['name'], rmt_places_by_name_norm($q, 0, 5));

check('a partial name finds the place', $byName('Stedel'), ['Stedelijk']);
check('the accent left off still finds it', $byName('Museu Geologico'), ['Museu Geológico']);
check('and the accent put on', $byName('Museu Geológico'), ['Museu Geológico']);
check('a word inside the name counts too', $byName('geologico'), ['Museu Geológico']);
check('two letters is a keystroke, not a query', rmt_places_by_name_norm('st', 0, 5), []);
check('and a word nothing is called finds nothing', rmt_places_by_name_norm('zzzqqq', 0, 5), []);
/* A LIKE wildcard somebody typed is a character they typed, not a wildcard. */
check('a percent sign is not a wildcard', rmt_places_by_name_norm('%', 0, 5), []);

echo "\n-- a name that contains a category word --\n";
/* "Park Guell" and "Time Out Market" both contain a word that is also a category. The category
   pass used to run first and fill every slot with parks and markets in alphabetical order, so a
   search for Park Guell led with Amstelpark and never showed Park Guell at all. */
$pdo->exec("INSERT INTO place_categories (id,slug,name,plural,bucket,status)
            VALUES (3,'park','Park','Parks','attraction','active')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,category_id,name_norm)
            VALUES (910,2,'amstelpark','Amstelpark','amstelpark','attraction','active',3,'amstelpark'),
                   (911,2,'park-guell','Park Guell','park guell','attraction','active',3,'park guell')");
$names = static fn(array $rows): array => array_map(static fn(array $r) => (string) $r['name'], $rows);
check('the name pass finds the place itself', $names(rmt_places_by_name_norm('Park Guell', 0, 5)), ['Park Guell']);
$kind = $names(rmt_places_by_kind_words('parks', 0, 5));
check('and the category pass still answers the category', in_array('Amstelpark', $kind, true), true);
/* The controller runs the name pass first for exactly this reason; what is pinned here is that
   the two passes really do return different things, so the order between them decides the answer. */
check('the two passes disagree, which is why their order matters',
      $names(rmt_places_by_name_norm('Park Guell', 0, 5)) !== $kind, true);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
