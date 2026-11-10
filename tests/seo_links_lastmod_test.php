<?php
/**
 * Internal links between articles and guides, and the destinations sitemap date (2026-11-10).
 *
 * What must hold:
 *   - rmt_blog_topic() reads tax, ticket or nothing from a slug, tax first.
 *   - rmt_blog_related() returns only published posts of the same kind, never the post itself,
 *     never a skipped slug, never a repeat, at most n, and the same answer twice.
 *   - within a kind, every post is linked from about as many others as it links to (no favourites).
 *   - a post of no kind gets no related block.
 *   - rmt_sitemap_destination_mod() is a real past day or null: the newest of a city's published
 *     review dates and its scam items' checked dates, never today's date by default.
 *   - the destinations sitemap gives each city that date and nothing invented.
 *
 *   php tests/seo_links_lastmod_test.php   (reads database/dev.sqlite, writes nothing)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/database/dev.sqlite')) { echo "SKIP  no dev database\n"; exit(0); }
putenv('DATABASE_URL');
require $root . '/app/bootstrap.php';
require_once $root . '/app/controllers.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

/* ---- topic ---- */
ok('a city tax is tax', rmt_blog_topic('berlin-city-tax-2026') === 'tax');
ok('a levy is tax', rmt_blog_topic('accra-tourism-levy-2026') === 'tax');
ok('a cruise fee is tax, not ticket', rmt_blog_topic('santorini-cruise-fee-2026') === 'tax');
ok('tickets are ticket', rmt_blog_topic('berlin-zoo-tickets-2026') === 'ticket');
ok('a park pass is ticket', rmt_blog_topic('banff-park-pass-2026') === 'ticket');
ok('a story is neither', rmt_blog_topic('how-to-find-a-travel-buddy-safely') === '');
ok('"taxi" is not tax', rmt_blog_topic('rome-taxi-scams') === '');

/* ---- related ---- */
$posts = q_all("SELECT slug FROM blog_posts WHERE status = 'published' ORDER BY id");
$byTopic = ['tax' => [], 'ticket' => [], '' => []];
foreach ($posts as $r) $byTopic[rmt_blog_topic((string) $r['slug'])][] = (string) $r['slug'];
$published = array_flip(array_column($posts, 'slug'));

foreach (['tax', 'ticket'] as $topic) {
    $ring = $byTopic[$topic];
    if (count($ring) < 6) { echo "SKIP  fewer than 6 $topic posts in the dev database\n"; continue; }
    $in = array_fill_keys($ring, 0);
    $bad = [];
    foreach ($ring as $slug) {
        $rel = array_column(rmt_blog_related($slug), 'slug');
        if (count($rel) !== 4) $bad[] = "$slug: " . count($rel) . ' links';
        if (in_array($slug, $rel, true)) $bad[] = "$slug links itself";
        if (count(array_unique($rel)) !== count($rel)) $bad[] = "$slug repeats";
        foreach ($rel as $r) {
            if (!isset($published[$r])) $bad[] = "$slug links unpublished $r";
            if (rmt_blog_topic($r) !== $topic) $bad[] = "$slug links other kind $r";
            if (isset($in[$r])) $in[$r]++;
        }
        if ($rel !== array_column(rmt_blog_related($slug), 'slug')) $bad[] = "$slug not stable";
    }
    ok("$topic: four same-kind links each, no self, no repeats, stable", !$bad, implode('; ', array_slice($bad, 0, 3)));
    ok("$topic: every post is linked from exactly four others", min($in) === 4 && max($in) === 4, 'min ' . min($in) . ' max ' . max($in));
    $first = $ring[0];
    $skip = array_slice(array_column(rmt_blog_related($first), 'slug'), 0, 2);
    $rel2 = array_column(rmt_blog_related($first, $skip), 'slug');
    ok("$topic: skipped slugs are never returned", !array_intersect($skip, $rel2) && count($rel2) === 4, json_encode($rel2));
    ok("$topic: n is respected", count(rmt_blog_related($first, [], 2)) === 2);
}
/* Posts of no kind are matched with each other (2026-11-10), never with a tax or ticket note. */
$plain = $byTopic[''];
if (count($plain) >= 6) {
    $rel = array_column(rmt_blog_related($plain[0]), 'slug');
    ok('a post of no kind gets four others of no kind', count($rel) === 4 && !array_filter($rel, static fn(string $x): bool => rmt_blog_topic($x) !== '') && !in_array($plain[0], $rel, true), json_encode($rel));
} else {
    echo "SKIP  fewer than 6 posts of no kind in the dev database\n";
}

/* ---- homepage notes ---- */
$notes = rmt_home_recent_notes(3);
ok('the homepage gets at most three notes', count($notes) <= 3 && count($notes) === count(array_unique(array_column($notes, 'slug'))));
$nb = [];
foreach ($notes as $nt) {
    if (!isset($published[$nt['slug']])) $nb[] = $nt['slug'] . ' unpublished';
    if (rmt_blog_topic($nt['slug']) === '') $nb[] = $nt['slug'] . ' is not a dated note';
    if (trim($nt['title']) === '') $nb[] = $nt['slug'] . ' has no title';
}
ok('each is a published tax or ticket note with a title', !$nb, implode('; ', $nb));
ok('asking for none gives none', rmt_home_recent_notes(0) === []);
if (count($notes) === 3) {
    $when = [];
    foreach ($notes as $nt) {
        $r = q_one('SELECT COALESCE(updated_at, created_at) t FROM blog_posts WHERE slug = ?', [$nt['slug']]);
        $when[] = (int) strtotime((string) $r['t']);
    }
    ok('newest real change first', $when[0] >= $when[1] && $when[1] >= $when[2], json_encode($when));
}

/* ---- guide dates (scripts/fix_guide_dates.php), inside a transaction that is rolled back ---- */
require_once $root . '/scripts/fix_guide_dates.php';
$gs = q_all("SELECT id, slug, created_at FROM guides WHERE status = 'published' ORDER BY id LIMIT 3");
if (count($gs) === 3) {
    db()->beginTransaction();
    try {
        // a: restamped, really changed long before. b: restamped, real day is later (left alone).
        // c: edited after the restamp day (left alone).
        q_run('UPDATE guides SET created_at = ?, updated_at = ? WHERE id = ?', ['2026-07-01 00:00:00', '2026-10-31 05:00:00', (int) $gs[0]['id']]);
        q_run('UPDATE guides SET created_at = ?, updated_at = ? WHERE id = ?', ['2026-07-01 00:00:00', '2026-10-31 05:00:00', (int) $gs[1]['id']]);
        q_run('UPDATE guides SET created_at = ?, updated_at = ? WHERE id = ?', ['2026-07-01 00:00:00', '2026-11-05 05:00:00', (int) $gs[2]['id']]);
        $plan = rmt_guide_date_plan([$gs[0]['slug'] => '2026-08-01', $gs[1]['slug'] => '2026-11-02', $gs[2]['slug'] => '2026-08-01']);
        $bySlug = array_column($plan, 'to', 'slug');
        ok('a restamped guide goes back to its real day', ($bySlug[$gs[0]['slug']] ?? null) === '2026-08-01', json_encode($plan));
        ok('a date is never moved forward', !isset($bySlug[$gs[1]['slug']]));
        ok('a guide edited since the restamp is left alone', !isset($bySlug[$gs[2]['slug']]));
        q_run('UPDATE guides SET created_at = ? WHERE id = ?', ['2026-09-15 00:00:00', (int) $gs[0]['id']]);
        $plan2 = array_column(rmt_guide_date_plan([$gs[0]['slug'] => '2026-08-01']), 'to', 'slug');
        ok('never earlier than the day the guide was created', ($plan2[$gs[0]['slug']] ?? null) === '2026-09-15', json_encode($plan2));
        ok('a guide with no known day is left alone', rmt_guide_date_plan([]) === []);
    } finally {
        db()->rollBack();
    }
    $datesFile = (array) (json_decode((string) file_get_contents($root . '/database/guide_content_dates.json'), true)['guides'] ?? []);
    $badDates = array_filter($datesFile, static fn($d): bool => !is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d > gmdate('Y-m-d'));
    ok('the dates file holds real past days only', $datesFile && !$badDates, json_encode(array_slice($badDates, 0, 3)));
    ok('the dates file is not where the publisher reads content', !is_file($root . '/database/editorial/guide_content_dates.json'));
} else {
    echo "SKIP  fewer than 3 guides in the dev database\n";
}
ok('an unknown slug gets nothing', rmt_blog_related('no-such-tax-2026') === []);

/* ---- destination date ---- */
$today = gmdate('Y-m-d');
$dests = q_all('SELECT id, slug FROM destinations');
$dated = 0; $bad = [];
foreach ($dests as $d) {
    $mod = rmt_sitemap_destination_mod((int) $d['id'], (string) $d['slug']);
    if ($mod === null) continue;
    $dated++;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mod) || $mod > $today) $bad[] = $d['slug'] . ' ' . $mod;
    $rev = q_one("SELECT MAX(created_at) m FROM reviews WHERE status = 'published' AND destination_id = ?", [(int) $d['id']]);
    $want = [];
    if (!empty($rev['m'])) $want[] = gmdate('Y-m-d', (int) strtotime((string) $rev['m']));
    foreach ((array) (rmt_city_scams((string) $d['slug'])['items'] ?? []) as $it) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $it['checked']) && $it['checked'] <= $today) $want[] = (string) $it['checked'];
    }
    if (!$want || max($want) !== $mod) $bad[] = $d['slug'] . " got $mod want " . ($want ? max($want) : 'null');
}
ok('every city date is the newest real review or scam check, never in the future', !$bad, implode('; ', array_slice($bad, 0, 3)));
ok('some cities have a date', $dated > 0, (string) $dated);
ok('a city with nothing dated gets null', rmt_sitemap_destination_mod(-1, 'no-such-city') === null);

$rows = rmt_sitemap_group('destinations');
$mis = [];
foreach ($rows as $row) {
    if (!preg_match('#/d/([a-z0-9\-]+)$#', (string) $row['loc'], $m)) {
        if ($row['lastmod'] !== null) $mis[] = $row['loc'] . ' (travelers page should carry no date)';
        continue;
    }
    $d = q_one('SELECT id FROM destinations WHERE slug = ?', [$m[1]]);
    if ($row['lastmod'] !== rmt_sitemap_destination_mod((int) $d['id'], $m[1])) $mis[] = $row['loc'];
}
ok('the destinations sitemap carries exactly that date', !$mis && $rows, implode('; ', array_slice($mis, 0, 3)));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
