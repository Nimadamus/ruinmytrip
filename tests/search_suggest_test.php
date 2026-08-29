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

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
