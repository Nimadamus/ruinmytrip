<?php
/**
 * The one rule that decides what search engines see, and the sitemap that must agree with it.
 *
 * The failure this guards against is not a page ranking badly. It is thousands of near-empty pages
 * being indexed, the site being judged on its thinnest content, and the damage being spread across
 * everything by the time Search Console reports it. So the thresholds are asserted from both
 * sides -- just under, and just over -- because a threshold only tested from one side is a
 * threshold that can quietly move.
 *
 * The other half is agreement. robots, canonical and sitemap inclusion all come from
 * rmt_indexable(); the tests below assert that a noindex page is absent from the sitemap and an
 * indexable one is present, which is the contradiction that used to be possible when the sitemap
 * held its own opinion in hand-written SQL.
 *
 *   php tests/indexability_test.php
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
require BASE_PATH . '/app/search_suggest.php';
require BASE_PATH . '/app/neighborhoods.php';
require BASE_PATH . '/app/seo.php';
require BASE_PATH . '/app/indexability.php';
require BASE_PATH . '/app/sitemap.php';

function rmt_top_tags(int $n = 10): array { return []; }
function rmt_review_slug(array $r): string { return 'r'; }

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-60s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}
/** Shorthand: the reason code for a verdict. */
function why(string $type, array $e = []): string { return rmt_indexable($type, $e)['reason']; }

/* ============================================================ the rules alone */

echo "\nNever indexed, whatever else is true:\n";
check('admin',   why('admin'),  'noindex_admin');
check('search',  why('search'), 'noindex_filter');
check('filters', why('filter'), 'noindex_filter');
check('private', why('private'), 'noindex_private');
check('an unknown entity type is refused, not waved through',
      why('some_new_page_type'), 'noindex_unlisted_entity');

echo "\nPlaces -- community reviews are deliberately NOT required:\n";
$base = ['status' => 'active', 'destination_id' => 1];
check('a real venue with an address',    why('place', $base + ['street_address' => '1 Rue X']), 'indexable');
check('...or coordinates',               why('place', $base + ['lat' => 48.85]), 'indexable');
check('...or opening hours',             why('place', $base + ['hours_count' => 7]), 'indexable');
check('...or a website',                 why('place', $base + ['website_url' => 'https://x.test']), 'indexable');
check('...or something we wrote',        why('place', $base + ['editorial' => 'What it is']), 'indexable');
check('...or a photo',                   why('place', $base + ['photo_count' => 1]), 'indexable');
check('a place with ZERO reviews is still indexable',
      why('place', $base + ['street_address' => '1 Rue X', 'review_count' => 0]), 'indexable');
check('a name and a type and nothing else is not',
      why('place', $base), 'noindex_no_content');
check('a closed place is never indexed',
      why('place', ['status' => 'closed', 'destination_id' => 1, 'street_address' => '1 Rue X']), 'noindex_private');
check('a place with no destination is not a page',
      why('place', ['status' => 'active', 'street_address' => '1 Rue X']), 'noindex_thin');

echo "\nDestinations -- places OR editorial, not a thin shell:\n";
check('has places',            why('destination', ['place_count' => 3]), 'indexable');
check('no places but written about', why('destination', ['place_count' => 0, 'body' => 'Long text']), 'indexable');
check('neither is a thin shell', why('destination', ['place_count' => 0]), 'noindex_thin');

echo "\nNeighborhoods -- density AND variety, from both sides of the line:\n";
$nb = static fn(int $p, int $t, string $k = 'neighborhood') => why('neighborhood',
    ['kind' => $k, 'place_count' => $p, 'type_count' => $t]);
check('one below the place threshold', $nb(RMT_IDX_NB_MIN_PLACES - 1, 3), 'noindex_insufficient_places');
check('exactly at it',                 $nb(RMT_IDX_NB_MIN_PLACES, 2), 'indexable');
check('one above it',                  $nb(RMT_IDX_NB_MIN_PLACES + 1, 2), 'indexable');
// The variety rule is the one that matters: four hotels in an area is a hotel list, and the
// destination's own hotel page already does that better.
check('enough places but only one kind', $nb(9, 1), 'noindex_insufficient_density');
check('a borough is never a neighborhood page, however big',
      $nb(50, 4, 'borough'), 'noindex_unlisted_entity');
check('nor an administrative unit', $nb(50, 4, 'administrative'), 'noindex_unlisted_entity');

echo "\nCategory landing pages -- real inventory or no page:\n";
check('one below the threshold', why('category', ['place_count' => RMT_IDX_CAT_MIN_PLACES - 1]), 'noindex_insufficient_places');
check('exactly at it',           why('category', ['place_count' => RMT_IDX_CAT_MIN_PLACES]), 'indexable');
check('the reason says how many it has and needs',
      rmt_indexable('category', ['place_count' => 2])['detail'], '2 places, needs ' . RMT_IDX_CAT_MIN_PLACES);
check('the threshold is not absurdly low', RMT_IDX_CAT_MIN_PLACES >= 5, true);

echo "\nProfiles -- an empty profile is a page with a username on it:\n";
check('no contributions',   why('profile', ['status' => 'active']), 'noindex_empty_profile');
check('one review is enough', why('profile', ['status' => 'active', 'review_count' => 1]), 'indexable');
check('a guide counts too',   why('profile', ['status' => 'active', 'guide_count' => 1]), 'indexable');
check('a list counts too',    why('profile', ['status' => 'active', 'list_count' => 1]), 'indexable');
check('a suspended account is never indexed',
      why('profile', ['status' => 'suspended', 'review_count' => 40]), 'noindex_private');

echo "\nPublic lists -- substance, not a working title:\n";
$L = static fn(int $n, string $sum = 'Why these') => why('list', ['status' => 'published', 'item_count' => $n, 'summary' => $sum]);
check('one below the threshold', $L(RMT_IDX_LIST_MIN_ITEMS - 1), 'noindex_thin');
check('exactly at it',           $L(RMT_IDX_LIST_MIN_ITEMS), 'indexable');
check('enough items but no description', $L(10, ''), 'noindex_thin');
check('an unpublished list is private',
      why('list', ['status' => 'draft', 'item_count' => 20, 'summary' => 'x']), 'noindex_private');

echo "\nRobots follows the verdict, and always keeps the links:\n";
check('indexable', rmt_robots_for(['ok' => true]), 'index, follow');
check('not',       rmt_robots_for(['ok' => false]), 'noindex,follow');
check('every reason code has wording', count(array_filter(RMT_INDEX_REASONS)), count(RMT_INDEX_REASONS));

/* ================================================= the sitemap, against real rows */

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT, role TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, summary TEXT, body TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY, destination_id INT, slug TEXT, name TEXT, type TEXT,
            status TEXT, street_address TEXT, lat REAL, website_url TEXT, phone TEXT,
            created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE place_hours (id INTEGER PRIMARY KEY, place_id INT)");
$pdo->exec("CREATE TABLE place_photos (id INTEGER PRIMARY KEY, place_id INT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, place_id INT, destination_id INT,
            slug TEXT, title TEXT, subject_name TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY, review_id INT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, slug TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE trip_photos (id INTEGER PRIMARY KEY, trip_id INT)");
$pdo->exec("CREATE TABLE guides (id INTEGER PRIMARY KEY, user_id INT, slug TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE blog_posts (id INTEGER PRIMARY KEY, user_id INT, slug TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE collections (id INTEGER PRIMARY KEY, user_id INT, slug TEXT, title TEXT, summary TEXT,
            status TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE collection_items (id INTEGER PRIMARY KEY, collection_id INT, destination_id INT, place_id INT)");
$pdo->exec("CREATE TABLE meetups (id INTEGER PRIMARY KEY, status TEXT)");
$pdo->exec("CREATE TABLE going (id INTEGER PRIMARY KEY, visibility TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/055_neighborhoods.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/057_sitemap_cache.sqlite.sql'));

$pdo->exec("INSERT INTO users (id,username,status,role) VALUES
    (1,'contributor','active','user'), (2,'lurker','active','user'), (3,'gone','disabled','user')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country,summary,body) VALUES
    (1,'paris-france','Paris','France','A city','Long text'),
    (2,'nowhere','Nowhere','Elsewhere','','')");
// Paris: 7 hotels (over the category threshold), 2 restaurants (under it).
for ($i = 1; $i <= 7; $i++) {
    q_run("INSERT INTO places (id,destination_id,slug,name,type,status,street_address,updated_at)
           VALUES (?,1,?,?,'hotel','active','1 Rue X','2026-08-20 10:00:00')", [$i, 'h'.$i, 'Hotel '.$i]);
}
q_run("INSERT INTO places (id,destination_id,slug,name,type,status,street_address) VALUES
    (20,1,'r1','Rest 1','restaurant','active','2 Rue Y'), (21,1,'r2','Rest 2','restaurant','active','3 Rue Y')");
// A place we know nothing about, and one that is closed.
q_run("INSERT INTO places (id,destination_id,slug,name,type,status) VALUES
    (30,1,'bare','Bare Place','hotel','active'), (31,1,'shut','Shut Place','hotel','closed')");
q_run("INSERT INTO reviews (id,user_id,place_id,destination_id,slug,title,status,created_at)
       VALUES (1,1,1,1,'good','Good','published','2026-08-01 09:00:00')");
q_run("INSERT INTO collections (id,user_id,slug,title,summary,status,created_at,updated_at)
       VALUES (1,1,'big','Big List','Why these','published','2026-08-01','2026-08-02'),
              (2,1,'tiny','Tiny List','Why these','published','2026-08-01','2026-08-02')");
q_run("INSERT INTO collection_items (collection_id,place_id) VALUES (1,1),(1,2),(1,3),(1,4),(2,1)");

echo "\nBatch verdicts against real rows:\n";
$places = rmt_index_places();
$byslug = [];
foreach ($places as $p) $byslug[$p['slug']] = $p['verdict'];
check('an enriched place is indexable', $byslug['h1']['reason'], 'indexable');
check('a bare place is not',            $byslug['bare']['reason'], 'noindex_no_content');
check('a closed place is not even considered', isset($byslug['shut']), false);

$cats = [];
foreach (rmt_index_categories() as $c) $cats[$c['dest_slug'] . '/' . $c['type']] = $c;
check('7 hotels in Paris qualifies',       $cats['paris-france/hotel']['verdict']['ok'], true);
check('2 restaurants in Paris does not',   $cats['paris-france/restaurant']['verdict']['ok'], false);
check('and says why',                      $cats['paris-france/restaurant']['verdict']['reason'], 'noindex_insufficient_places');

$profiles = [];
foreach (rmt_index_profiles() as $u) $profiles[$u['username']] = $u['verdict'];
check('a contributor is indexable',  $profiles['contributor']['reason'], 'indexable');
check('an empty profile is not',     $profiles['lurker']['reason'], 'noindex_empty_profile');
check('a disabled account is absent', isset($profiles['gone']), false);

$lists = [];
foreach (rmt_index_lists() as $c) $lists[$c['slug']] = $c['verdict'];
check('a list of four with a description', $lists['big']['reason'], 'indexable');
check('a list of one is thin',             $lists['tiny']['reason'], 'noindex_thin');

/* ---------------------------------------------------------------- generation */

echo "\nGeneration, partitioning and agreement:\n";
$counts = rmt_sitemap_generate();
$parts = rmt_sitemap_parts();
check('every group produced a file or nothing', count($parts) > 0, true);

$all = [];
foreach ($parts as $pt) {
    preg_match_all('#<loc>(.*?)</loc>#', (string) q_one("SELECT xml FROM sitemap_cache WHERE group_key=? AND part=?",
        [$pt['group_key'], $pt['part']])['xml'], $m);
    $all = array_merge($all, $m[1]);
}
check('no URL appears twice across the whole sitemap', count($all), count(array_unique($all)));

$has = static fn(string $path): bool => in_array('https://example.test/' . ltrim($path, '/'), $all, true);
check('the qualifying category page is listed',    $has('d/paris-france/hotels'), true);
check('the thin one is NOT',                       $has('d/paris-france/restaurants'), false);
check('the enriched place is listed',              $has('p/h1'), true);
check('the bare place is NOT',                     $has('p/bare'), false);
check('the closed place is NOT',                   $has('p/shut'), false);
check('the contributor profile is listed',         $has('u/contributor'), true);
check('the empty profile is NOT',                  $has('u/lurker'), false);
check('the substantial list is listed',            $has('c/big'), true);
check('the thin list is NOT',                      $has('c/tiny'), false);
check('the thin destination is NOT',               $has('d/nowhere'), false);
check('the real destination is',                   $has('d/paris-france'), true);

// Nothing that is not a page, and nothing that is an action or a private view.
$forbidden = ['/admin', '/login', '/register', '/search', '/suggest', '/logout', '/settings'];
$leaked = [];
foreach ($all as $u) {
    foreach ($forbidden as $f) if (str_contains($u, $f)) $leaked[] = $u;
}
check('no admin, auth, search or action URLs', $leaked, []);
// A sort parameter is not a page. If one ever reaches the sitemap it means a filtered view was
// treated as an entity.
check('no query strings at all', count(array_filter($all, static fn(string $u) => str_contains($u, '?'))), 0);

echo "\nPartitioning, at a size we can actually reach in a test:\n";
$rows = array_map(static fn(int $i) => ['loc' => 'https://example.test/x' . $i, 'lastmod' => null], range(1, 12));
$chunks = array_chunk($rows, 5);
check('12 urls at 5 per file is 3 files', count($chunks), 3);
check('the last file holds the remainder', count($chunks[2]), 2);
$xml = rmt_sitemap_render($chunks[0]);
check('a rendered child is a urlset', str_contains($xml, '<urlset'), true);
check('and holds its 5', substr_count($xml, '<loc>'), 5);

echo "\nlastmod is only claimed where we hold one:\n";
$placesXml = (string) q_one("SELECT xml FROM sitemap_cache WHERE group_key='places' AND part=1")['xml'];
check('a place with an updated_at claims it', str_contains($placesXml, '<lastmod>2026-08-20</lastmod>'), true);
$destXml = (string) q_one("SELECT xml FROM sitemap_cache WHERE group_key='destinations' AND part=1")['xml'];
check('a destination, which has no timestamp, claims none', str_contains($destXml, '<lastmod>'), false);
check('and today is never invented', str_contains($destXml, gmdate('Y-m-d')), false);

echo "\nRegeneration is idempotent and does not leave stale parts:\n";
$before = count(rmt_sitemap_parts());
rmt_sitemap_generate();
check('a second run produces the same files', count(rmt_sitemap_parts()), $before);
// A group that shrinks must not leave a part behind advertising URLs that no longer qualify.
q_run("UPDATE places SET status='closed' WHERE type='hotel'");
rmt_sitemap_generate();
$catRow = q_one("SELECT url_count FROM sitemap_cache WHERE group_key='categories' AND part=1");
check('the category file is gone once nothing qualifies', $catRow, null);

echo "\nWell-formed XML:\n";
foreach (rmt_sitemap_parts() as $pt) {
    $x = (string) q_one("SELECT xml FROM sitemap_cache WHERE group_key=? AND part=?", [$pt['group_key'], $pt['part']])['xml'];
    $prev = libxml_use_internal_errors(true);
    $ok = simplexml_load_string($x) !== false;
    libxml_use_internal_errors($prev);
    check('valid XML: ' . $pt['group_key'], $ok, true);
}

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
