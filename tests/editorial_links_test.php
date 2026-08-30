<?php
/**
 * Linking an article to the entities it talks about, without turning it into keyword spam.
 *
 * The failure mode of automatic internal linking is worse than the gap it fills: an article that
 * links every occurrence of "museum" and "restaurant" is spam that happens to be internal, and it
 * teaches a reader to stop trusting every link on the site. So most of what follows asserts what is
 * NOT linked -- a venue in another city, a name too ordinary to be a name, a partial word.
 *
 *   php tests/editorial_links_test.php
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
require BASE_PATH . '/app/editorial_links.php';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-58s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY, destination_id INT, slug TEXT, name TEXT, type TEXT, status TEXT)");
$pdo->exec("CREATE TABLE guides (id INTEGER PRIMARY KEY, destination_id INT, slug TEXT, title TEXT, summary TEXT, body TEXT, status TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/055_neighborhoods.sqlite.sql'));

$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (1,'paris-france','Paris'), (2,'prague-czechia','Prague')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,type,status) VALUES
    (1,1,'louvre-museum-paris','Louvre Museum','attraction','active'),
    (2,1,'musee-d-orsay-paris','Musee d''Orsay','attraction','active'),
    (3,1,'bouillon-chartier-paris','Bouillon Chartier','restaurant','active'),
    (4,1,'rules-paris','Rules','restaurant','active'),
    (5,1,'shut-paris','Closed Brasserie','restaurant','permanently_closed'),
    (6,2,'cafe-savoy-prague','Cafe Savoy','restaurant','active'),
    (7,1,'le-procope-paris','Le Procope','restaurant','active')");
rmt_nb_upsert(1, '1st Arrondissement', ['Louvre district']);
rmt_nb_upsert(1, 'Montmartre', []);
rmt_nb_upsert(2, 'Stare Mesto', []);

$body = "The Louvre Museum now charges non-EU visitors more, and the Musee d'Orsay has followed. "
      . "For dinner, Bouillon Chartier still serves at bouillon prices. Montmartre is a walk. "
      . "The rules changed again in March, and every museum in the city posts its own notice.";

$m = rmt_editorial_entities(1, $body);
$slugs = array_column($m['places'], 'slug');
$areas = array_column($m['areas'], 'slug');

echo "\nWhat the guide actually names:\n";
check('the Louvre is linked',        in_array('louvre-museum-paris', $slugs, true), true);
check('the Orsay is linked, accents and all', in_array('musee-d-orsay-paris', $slugs, true), true);
check('Bouillon Chartier is linked', in_array('bouillon-chartier-paris', $slugs, true), true);
check('Montmartre is linked as an area', in_array('montmartre', $areas), true);

echo "\nWhat it must NOT link:\n";
// "Rules" is a real London restaurant and an ordinary English word. The text says "the rules
// changed", and linking that is the exact failure this whole file is defending against.
check('a venue whose name is a common word is not linked',
      in_array('rules-paris', $slugs, true), false);
// Same reasoning, generic nouns: the text says "every museum in the city".
check('a bare category word links nothing', count(array_filter($slugs, static fn($x) => $x === 'museum')), 0);
check('a venue in another city is never linked',
      in_array('cafe-savoy-prague', $slugs, true), false);
check("nor is another city's area", in_array('stare-mesto', $areas), false);
check('a closed place is not offered as a link', in_array('shut-paris', $slugs, true), false);
check('a venue the text never names is not linked', in_array('le-procope-paris', $slugs, true), false);

echo "\nMatching rules:\n";
$hay = rmt_link_norm('We ate at the Cafe Savoy and it was fine.');
check('accents fold both ways', rmt_link_mentions($hay, 'Café Savoy'), true);
check('a partial word does not match', rmt_link_mentions($hay, 'Cafe Savoyard'), false);
check('a substring of a longer word does not match',
      rmt_link_mentions(rmt_link_norm('The Procopes family lived here.'), 'Le Procope'), false);
check('a short name is skipped entirely', rmt_link_mentions(rmt_link_norm('The bar was open.'), 'Bar'), false);
check('no destination, nothing linked', rmt_editorial_entities(0, $body), ['places' => [], 'areas' => []]);
check('empty text, nothing linked', rmt_editorial_entities(1, ''), ['places' => [], 'areas' => []]);

echo "\nThe list stays a list:\n";
$many = '';
for ($i = 1; $i <= 20; $i++) {
    q_run("INSERT INTO places (id,destination_id,slug,name,type,status) VALUES (?,1,?,?,'restaurant','active')",
          [100 + $i, 'filler-' . $i . '-paris', 'Restaurant Number ' . $i]);
    $many .= 'We also liked Restaurant Number ' . $i . '. ';
}
check('a guide naming twenty venues is capped', count(rmt_editorial_entities(1, $many)['places']), RMT_LINK_MAX);

echo "\nAnd the other direction:\n";
$pdo->exec("INSERT INTO guides (id,destination_id,slug,title,summary,body,status) VALUES
    (1,1,'paris-guide','Paris Practical Guide','How not to overpay',
     'The Louvre Museum now charges non-EU visitors more.','published'),
    (2,1,'paris-draft','Draft','x','The Louvre Museum is shut on Tuesdays.','draft'),
    (3,2,'prague-guide','Prague Guide','x','The Louvre Museum is not in Prague.','published')");
$louvre = q_one("SELECT * FROM places WHERE id = 1");
$g = rmt_guides_mentioning_place($louvre);
check('the guide that names it is found', array_column($g, 'slug'), ['paris-guide']);
check('an unpublished guide is not', in_array('paris-draft', array_column($g, 'slug'), true), false);
check('nor a guide from another destination', in_array('prague-guide', array_column($g, 'slug'), true), false);
$procope = q_one("SELECT * FROM places WHERE id = 7");
check('a place no guide names gets nothing', rmt_guides_mentioning_place($procope), []);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
