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
ok('a post of no kind gets nothing', rmt_blog_related('how-to-find-a-travel-buddy-safely') === []);
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
