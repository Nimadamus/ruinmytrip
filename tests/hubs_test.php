<?php
/**
 * Destination hubs (app/hubs.php, migration 125): the content threshold for seeded cities, the
 * warnings directory, the travel partners block, and the seeding script.
 *
 * The rules, as approved by Nima on 2026-11-14:
 *   - the cities that existed before seeding are grandfathered: this code never changes their index rule
 *   - a seeded city is noindex and unlisted until a trip or a check-in in the last 90 days, or
 *     three sourced warnings; the lookback stops it flipping in and out
 *   - the "Is {City} Safe?" title is for the new warnings pages only
 *   - no Event markup, no LocalBusiness markup; FAQ markup only for sourced question and answer pairs
 *   - a batch is 50 cities
 *
 *   php tests/hubs_test.php
 */
putenv('DATABASE_URL');
putenv('RESEND_API_KEY');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }
$root = dirname(__DIR__);

$pdo = db(); $pdo->beginTransaction();
$t0 = time(); $now = date('Y-m-d H:i:s');
$at = static fn(int $days): string => date('Y-m-d H:i:s', $t0 + $days * 86400);
foreach (['hbana', 'hbleo'] as $u) {
    q_run("INSERT INTO users (username,email,password_hash,role,status,birthdate,email_verified_at,created_at) VALUES (?,?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', 'member', 'active', '1990-01-01', $now, $now]);
    $uid = (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];
    if (!q_one('SELECT 1 FROM profiles WHERE user_id=?', [$uid])) q_run('INSERT INTO profiles (user_id, display_name) VALUES (?,?)', [$uid, ucfirst($u)]);
}
$id = fn(string $u): int => (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];
$seed = static function (string $slug, string $name, string $country) use ($now): int {
    q_run('INSERT INTO destinations (slug,name,country,lat,lng,category,seeded_at) VALUES (?,?,?,?,?,?,?)', [$slug, $name, $country, 10.0, 10.0, 'city', $now]);
    return (int) q_one('SELECT id FROM destinations WHERE slug=?', [$slug])['id'];
};
$verdict = static function (string $slug): bool {
    rmt_hub_recent_activity(null, true);
    $d = q_one('SELECT * FROM destinations WHERE slug=?', [$slug]);
    return rmt_indexable('destination', $d + ['place_count' => 0])['ok'];
};
$post = static function (int $uid, int $dest, string $created, string $status = 'open') use ($t0): void {
    q_run("INSERT INTO buddy_posts (user_id,trip_type,title,where_text,destination_id,date_from,date_to,spots,description,status,created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)", [$uid, 'trip', 'Hub test trip', '', $dest, date('Y-m-d', $t0 + 5 * 86400), date('Y-m-d', $t0 + 12 * 86400), 2, 'Come along.', $status, $created]);
};

/* ================= grandfathering ================= */
$old = q_one("SELECT * FROM destinations WHERE seeded_at IS NULL ORDER BY id LIMIT 1");
$before = rmt_indexable('destination', $old + ['place_count' => 5]);
ok($before['ok'] && !rmt_hub_is_seeded($old), 'a city that was here before seeding keeps its index rule, with no trips and no check-ins');
ok(!isset(rmt_hub_hidden_ids()[(int) $old['id']]), 'and is never hidden from a list');
$src = (string) file_get_contents($root . '/app/indexability.php');
ok(strpos($src, 'rmt_hub_is_seeded($e)') !== false && strpos($src, "rmt_hub_is_seeded(\$e)") < strpos($src, 'RMT_IDX_DEST_MIN_PLACES && !$hasText'), 'the threshold branch is reached only by a seeded city, before the old rule runs');

/* ================= the threshold ================= */
$quiet = $seed('hubtest-quiet', 'Quietville', 'Hubland');
$trip  = $seed('hubtest-trip', 'Tripville', 'Hubland');
$chk   = $seed('hubtest-checkin', 'Checkville', 'Hubland');
$stale = $seed('hubtest-stale', 'Staleville', 'Hubland');
rmt_hub_seeded_ids(true);
ok(!$verdict('hubtest-quiet'), 'a seeded city with nothing on it is noindex');
ok(rmt_robots_for(rmt_indexable('destination', q_one('SELECT * FROM destinations WHERE id=?', [$quiet]) + ['place_count' => 99])) !== 'index, follow',
   'even with places: for a seeded city the threshold is the rule');
$post($id('hbana'), $trip, $at(-10));
ok($verdict('hubtest-trip'), 'one trip posted in the last 90 days makes it indexable');
$post($id('hbana'), $stale, $at(-(RMT_HUB_LOOKBACK_DAYS + 5)), 'completed');
ok(!$verdict('hubtest-stale'), 'a trip older than the lookback does not');
$post($id('hbleo'), $quiet, $at(-3), 'removed');
ok(!$verdict('hubtest-quiet'), 'nor a removed trip');
q_run('INSERT INTO active_checkins (user_id,destination_id,created_at,expires_at,ended_at,end_reason) VALUES (?,?,?,?,?,?)',
      [$id('hbleo'), $chk, $at(-40), date('Y-m-d H:i:s', $t0 - 40 * 86400 + 7200), date('Y-m-d H:i:s', $t0 - 40 * 86400 + 7200), 'expired']);
ok($verdict('hubtest-checkin'), 'a check-in from 40 days ago, long expired, still counts: the page does not flip when a check-in runs out');
$th = rmt_hub_threshold($chk, 'hubtest-checkin');
ok($th['ok'] && $th['checkins'] === 1 && $th['trips'] === 0 && str_contains($th['reason'], 'check-in'), 'the reason is reported');
q_run('UPDATE active_checkins SET created_at = ? WHERE destination_id = ?', [$at(-(RMT_HUB_LOOKBACK_DAYS + 1)), $chk]);
ok(!$verdict('hubtest-checkin'), 'after ' . RMT_HUB_LOOKBACK_DAYS . ' days with nothing new it drops out');
q_run('UPDATE active_checkins SET created_at = ? WHERE destination_id = ?', [$at(-40), $chk]);
q_run("UPDATE users SET status='suspended' WHERE id=?", [$id('hbleo')]);
ok(!$verdict('hubtest-checkin'), 'activity from an account that is no longer active does not count');
q_run("UPDATE users SET status='active' WHERE id=?", [$id('hbleo')]);

/* Three sourced warnings qualify a seeded city by themselves. */
$scamSlugs = array_keys(rmt_city_scams_all());
$rich = null; $thin = null;
foreach ($scamSlugs as $s) { $n = count(rmt_city_scams($s)['items']); if ($n >= 3 && !$rich) $rich = $s; if ($n < 3 && !$thin) $thin = $s; }
$richDest = q_one('SELECT * FROM destinations WHERE slug=?', [$rich]);
q_run('UPDATE destinations SET seeded_at = ? WHERE id = ?', [$now, (int) $richDest['id']]);
rmt_hub_seeded_ids(true);
ok($verdict($rich), RMT_HUB_MIN_WARNINGS . ' or more sourced warnings make a seeded city indexable with no trips at all');
q_run('UPDATE destinations SET seeded_at = NULL WHERE id = ?', [(int) $richDest['id']]);
rmt_hub_seeded_ids(true);

/* ================= public lists ================= */
rmt_hub_recent_activity(null, true);
$hidden = rmt_hub_hidden_ids();
ok(isset($hidden[$quiet], $hidden[$stale]) && !isset($hidden[$trip], $hidden[$chk]), 'cities below the threshold are hidden, the ones above it are not');
$near = array_column(rmt_nearby_destinations(['id' => $trip, 'lat' => 10.0, 'lng' => 10.0, 'country' => 'Hubland'], 50), 'slug');
ok(in_array('hubtest-checkin', $near, true) && !in_array('hubtest-quiet', $near, true) && !in_array('hubtest-stale', $near, true), 'nearby cities links leave out the hidden ones');
ini_set('display_errors', '0'); $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = [];
ob_start(); explore([]); $ex = (string) ob_get_clean();
ok(str_contains($ex, 'Tripville') && !str_contains($ex, 'Quietville') && str_contains($ex, e((string) $old['name'])), 'the explore page lists a qualified seeded city and the old ones, not an empty one');
$idx = []; foreach (rmt_index_destinations() as $r) $idx[$r['slug']] = $r['verdict']['ok'];
ok($idx['hubtest-trip'] === true && $idx['hubtest-quiet'] === false, 'the sitemap list follows the same verdict');
ok(q_one('SELECT 1 FROM destinations WHERE id=?', [$quiet]) && in_array('Quietville', array_column(all_dests(), 'name'), true), 'a hidden city can still be picked when posting a trip or checking in');
ok(rmt_checkin_start($id('hbana'), $quiet, 3)['ok'] && $verdict('hubtest-quiet'), 'and checking in to it is what brings it over the line');
rmt_checkin_end($id('hbana'));

/* ================= the travel partners block ================= */
$block = static function (array $d): string { ob_start(); include dirname(__DIR__) . '/views/_hub_partners.php'; return (string) ob_get_clean(); };
$tripCity = q_one('SELECT * FROM destinations WHERE id=?', [$trip]);
$h = $block($tripCity);
ok(str_contains($h, 'Travel partners in Tripville') && str_contains($h, '1 trip is looking for company') && str_contains($h, 'Hub test trip'), 'the block shows the open trip, counted');
ok(!str_contains($h, 'checked in right now'), 'and says nothing about check-ins when nobody is checked in');
ok($block(q_one('SELECT * FROM destinations WHERE id=?', [$stale])) === '', 'a city with no open trip, nobody checked in and no warnings gets no block at all');
$noPhoto = (string) file_get_contents($root . '/views/destination.php') . (string) file_get_contents($root . '/views/destination_v2.php');
ok(substr_count($noPhoto, "<?php if (!empty(\$d['hero_url'])): ?><img") === 2, 'a city with no photograph draws no hero image, so the site share picture never stands in for it');
$h = $block($richDest);
ok(str_contains($h, 'warnings for ' . e((string) $richDest['name'])) && str_contains($h, 'warnings/' . $rich) && str_contains($h, 'Post your dates'), 'a city with sourced warnings links to them, and invites the first trip');
ok(str_contains((string) file_get_contents($root . '/views/destination.php'), "_hub_partners.php"), 'the city page includes the block');
$live = rmt_hub_live($trip);
ok($live['open_trip_count'] === 1 && $live['checked_in'] === 0, 'the numbers are counts of real rows');

/* ================= warnings pages ================= */
$render = static function (array $a): array {
    $GLOBALS['__meta_seen'] = null;
    ob_start(); warnings_city($a); $html = (string) ob_get_clean();
    return [$html];
};
$w = rmt_warnings_city($rich);
$city = (string) $w['d']['name'];
[$h] = $render(['slug' => $rich]);
ok(str_contains($h, '<title>Is ' . e($city) . ' Safe? Tourist Traps, Scams and Travel Partners in ' . e($city)), 'the warnings page carries the approved title');
ok(!preg_match('/<title>[^<]*Verified/i', $h), 'which does not call anybody verified');
ok(str_contains($h, '>Is ' . e($city) . ' safe? Tourist traps and scams to know before you go</h1>'), 'and a plain headline');
ok(str_contains($h, e($w['items'][0]['name'])) && str_contains($h, e($w['items'][0]['sources'][0]['publisher'])) && str_contains($h, 'How to avoid it'), 'every warning is there with its source');
ok(substr_count($h, '<blockquote') >= count($w['items']), 'and the sentence it was taken from');
ok(preg_match('/<meta name="robots" content="index, follow"/', $h) === 1 && str_contains($h, '<link rel="canonical" href="' . e(url('warnings/' . $rich)) . '"'), 'it is indexable, with its own canonical');
ok(str_contains($h, 'Post your dates for ' . e($city)) && !preg_match('/\d+ travelers? (is|are) looking for company/', $h), 'with nobody going, it invites the first trip and shows no number');
$post($id('hbana'), (int) $w['d']['id'], $now); $post($id('hbleo'), (int) $w['d']['id'], $now);
[$h] = $render(['slug' => $rich]);
ok(str_contains($h, '2 travelers are looking for company in ' . e($city) . ' this month'), 'with two travelers going, it says two');
ok(!str_contains($h, '"@type":"Event"') && !str_contains($h, 'LocalBusiness') && !str_contains($h, 'FAQPage'), 'no Event, LocalBusiness or FAQ markup is emitted');
ok(!preg_match('/[\x{2013}\x{2014}]/u', strip_tags(substr($h, (int) strpos($h, '<h1'), 6000))), 'the page copy has no dashes');
if ($thin) {
    [$h] = $render(['slug' => $thin]);
    ok(preg_match('/<meta name="robots" content="noindex,follow"/', $h) === 1, 'a city with fewer than ' . RMT_HUB_MIN_WARNINGS . ' warnings gets a noindex warnings page');
}
ok(rmt_warnings_city('hubtest-quiet') === null, 'a city with no sourced warnings has no warnings page');

/* One kind of warning in one city: only with enough sourced items. */
$typed = null;
foreach ($scamSlugs as $s) { $ws = rmt_warnings_city($s); if ($ws && $ws['type_pages']) { $typed = [$s, $ws['type_pages'][0]]; break; } }
$any = array_key_first($w['types']);
ok(rmt_warning_type_from_slug('restaurant-scams') === 'bill' && rmt_warning_type_from_slug('nope') === null, 'type words in the address map to a known kind');
ok(count(RMT_WARNING_TYPE_SLUGS) === count(RMT_SCAM_TYPES) && !array_diff_key(RMT_SCAM_TYPES, RMT_WARNING_TYPE_SLUGS), 'every kind of warning has an address word');
if ($typed) {
    [$h] = $render(['slug' => $typed[0], 'type' => RMT_WARNING_TYPE_SLUGS[$typed[1]]]);
    ok(str_contains($h, 'index, follow'), 'a kind with enough items has its own page');
} else {
    ok(($w['types'][$any] ?? 0) < RMT_WARNING_TYPE_MIN_ITEMS && !rmt_warnings_indexable($w, $any), 'today no city has ' . RMT_WARNING_TYPE_MIN_ITEMS . ' sourced items of one kind, so no one-kind page exists');
}
$m = rmt_warnings_meta($w, 'taxi');
ok(str_contains($m['title'], 'Taxi scams in ' . $city) && !str_contains($m['title'], 'Is ' . $city . ' Safe'), 'a one-kind page is titled for what it is');

/* FAQ markup only for sourced pairs. */
ok(rmt_hub_faq_jsonld([]) === '' && rmt_hub_faq_jsonld([['q' => 'Is it safe?', 'a' => 'Mostly.', 'source' => '']]) === '', 'no FAQ markup without a sourced question and answer');
$faq = rmt_hub_faq_jsonld([['q' => 'Do taxis use meters?', 'a' => 'Licensed ones do.', 'source' => 'https://www.gov.uk/foreign-travel-advice']]);
ok(str_contains($faq, 'FAQPage') && str_contains($faq, 'Do taxis use meters?'), 'a sourced pair is marked up');

/* City page titles are untouched. */
ok(!str_contains((string) file_get_contents($root . '/app/seo.php'), 'Safe? Tourist Traps'), 'the "Is {City} Safe?" title is not applied to city pages');

/* ================= the country pages that already exist ================= */
$cview = static function (string $slug): string { ob_start(); country_show(['slug' => $slug]); return (string) ob_get_clean(); };
rmt_hub_recent_activity(null, true);
$h = $cview(rmt_country_slug('Hubland'));
ok(str_contains($h, 'Tripville') && !str_contains($h, 'Staleville'), 'a country page lists the cities above the threshold only');
ok(!str_contains($h, 'content="noindex'), 'and keeps its index tag');
$seed('hubtest-lonely', 'Lonelyville', 'Emptyland');
rmt_hub_seeded_ids(true);
$h = $cview(rmt_country_slug('Emptyland'));
ok(str_contains($h, 'Lonelyville') && str_contains($h, 'content="noindex,follow"'), 'a country with only cities below the threshold opens, but noindex');
$h = $cview(rmt_country_slug((string) $old['country']));
ok(str_contains($h, e((string) $old['name'])) && !str_contains($h, 'content="noindex'), 'an existing country page is unchanged');
ok(!str_contains((string) file_get_contents($root . '/public/index.php'), "'#^/destinations/"), 'no second family of country addresses was added');
$lp = array_column(rmt_buddy_landing_cities(['kind' => 'country', 'country' => 'Hubland', 'dests' => []]), 'slug');
ok(in_array('hubtest-trip', $lp, true) && !in_array('hubtest-stale', $lp, true), 'the travel buddies country pages leave out cities below the threshold too');

/* ================= sitemap ================= */
$locs = array_column(rmt_sitemap_group('destinations'), 'loc');
ok(in_array(url('warnings/' . $rich), $locs, true), 'indexable warnings pages are in the sitemap');
ok(!in_array(url('d/hubtest-stale'), $locs, true) && ($thin === null || !in_array(url('warnings/' . $thin), $locs, true)), 'and nothing that is noindex is');

/* ================= the seeding script and the first batch ================= */
$batch = json_decode((string) file_get_contents($root . '/database/hub_seed/batch_001.json'), true);
$cities = $batch['cities'];
ok(count($cities) === 50 && count(array_unique(array_column($cities, 'slug'))) === 50, 'the first batch is exactly 50 different cities');
$existing = array_column(q_all('SELECT slug FROM destinations WHERE seeded_at IS NULL'), 'slug');
ok(!array_intersect(array_column($cities, 'slug'), $existing), 'none of them is a city the site already has');
$badRow = array_filter($cities, static fn(array $c): bool => !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $c['slug'])
    || trim((string) $c['name']) === '' || trim((string) $c['country']) === '' || abs((float) $c['lat']) > 90 || abs((float) $c['lng']) > 180 || isset($c['summary']));
ok(!$badRow, 'each has a slug, a name, a country and coordinates, and no written description');
$script = (string) file_get_contents($root . '/scripts/seed_hub_cities.php');
ok(str_contains($script, "'seeded_at'") && str_contains($script, 'Nothing was written') && str_contains($script, 'count($cities) > 50'), 'the script marks cities as seeded, is a dry run unless told to apply, and refuses a batch over 50');
ok(!is_dir($root . '/database/editorial/hub_seed') && is_file($root . '/database/hub_seed/batch_001.json'), 'the batch file is kept out of database/editorial, where every json file is published as content');

$routes = (string) file_get_contents($root . '/public/index.php');
foreach (['warnings_city'] as $fn) ok(str_contains($routes, "'$fn'") && function_exists($fn), "route handler $fn is wired and exists");

$pdo->rollBack();
echo $fails ? "\nhubs_test: $fails FAILED\n" : "\nhubs_test: all passed\n";
exit($fails ? 1 : 0);
