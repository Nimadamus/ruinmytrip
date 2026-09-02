<?php
declare(strict_types=1);

/* ---------- helpers shared by controllers ---------- */
function dest_by_slug(string $slug): ?array { return q_one('SELECT * FROM destinations WHERE slug = ?', [$slug]); }
function dest_by_id(int $id): ?array { return q_one('SELECT * FROM destinations WHERE id = ?', [$id]); }
function all_dests(): array { return q_all('SELECT * FROM destinations ORDER BY name'); }
function author(int $uid): ?array {
    return q_one('SELECT u.id,u.username,u.role,p.display_name,p.avatar_url,p.credibility_score
                  FROM users u LEFT JOIN profiles p ON p.user_id=u.id WHERE u.id=?', [$uid]);
}
/**
 * Batch version of author(): one query for a whole list's worth of user ids instead of one
 * query per row. Used to fill in $row['author'] on a result set -- see authors_fill() below.
 * @return array<int,array> keyed by user id
 */
function authors_by_ids(array $uids): array {
    $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));
    if (!$uids) return [];
    $ph = implode(',', array_fill(0, count($uids), '?'));
    $rows = q_all("SELECT u.id,u.username,u.role,p.display_name,p.avatar_url,p.credibility_score
                   FROM users u LEFT JOIN profiles p ON p.user_id=u.id WHERE u.id IN ($ph)", $uids);
    $out = [];
    foreach ($rows as $r) $out[(int)$r['id']] = $r;
    return $out;
}
/** Set $row['author'] on every row from a single batched author lookup, in place. */
function authors_fill(array &$rows, string $idField = 'user_id'): void {
    $authors = authors_by_ids(array_column($rows, $idField));
    foreach ($rows as &$row) $row['author'] = $authors[(int)$row[$idField]] ?? null;
    unset($row);
}
function stars(int $n): string { return str_repeat('★', $n) . str_repeat('☆', 5 - $n); }
function not_found(): void { http_response_code(404); view('404', [], ['title'=>'Not found — RuinMyTrip']); exit; }
function forbidden(string $msg = "You don't have permission to do that."): void {
    http_response_code(403);
    view('403', compact('msg'), ['title'=>'Not authorized — RuinMyTrip']);
    exit;
}

/* ---------- public pages ---------- */
function home(array $a): void {
    $trending = q_all('SELECT d.*, (SELECT COUNT(*) FROM trips t WHERE t.destination_id=d.id) AS trips
                       FROM destinations d ORDER BY trips DESC, d.name LIMIT 6');
    $stories = q_all("SELECT t.*, d.name dest_name, d.slug dest_slug FROM trips t
                      LEFT JOIN destinations d ON d.id=t.destination_id
                      WHERE t.status='published' ORDER BY t.created_at DESC, t.id DESC LIMIT 4");
    $meetups = q_all("SELECT m.*, d.name dest_name, d.slug dest_slug FROM meetups m
                      LEFT JOIN destinations d ON d.id=m.destination_id
                      WHERE m.status='published' ORDER BY m.date_start ASC LIMIT 3");
    $guides = q_all("SELECT g.*, d.name dest_name FROM guides g
                     LEFT JOIN destinations d ON d.id=g.destination_id
                     WHERE g.status='published' ORDER BY g.id DESC LIMIT 6");
    // Homepage reviews are destination-level: place reviews belong on /p/ pages, and the
    // query-shaped titles (tourist tax, tickets, what nearly ruins it) are the destination ones.
    $reviews = q_all("SELECT r.*, d.slug dest_slug FROM reviews r
                      LEFT JOIN destinations d ON d.id=r.destination_id
                      WHERE r.status='published' AND r.place_id IS NULL
                      ORDER BY r.id DESC LIMIT 4");
    authors_fill($stories);
    authors_fill($reviews);
    authors_fill($guides);
    $stat_destinations = (int)(q_one('SELECT COUNT(*) c FROM destinations')['c'] ?? 0);
    $stat_community_reviews = (int)(q_one("SELECT COUNT(*) c FROM reviews r JOIN users u ON u.id=r.user_id
                                            WHERE r.status='published' AND u.role <> ?", [RMT_EDITORIAL_ROLE])['c'] ?? 0);
    $stat_editorial_reviews = (int)(q_one("SELECT COUNT(*) c FROM reviews r JOIN users u ON u.id=r.user_id
                                            WHERE r.status='published' AND u.role = ?", [RMT_EDITORIAL_ROLE])['c'] ?? 0);
    $stat_travelers = (int)(q_one("SELECT COUNT(*) c FROM users WHERE status='active' AND role <> ?", [RMT_EDITORIAL_ROLE])['c'] ?? 0);
    $taxPost = q_one("SELECT slug, title FROM blog_posts WHERE slug = 'tourist-taxes-2026' AND status = 'published'");
    $latestPosts = q_all("SELECT slug, title, summary, cover_url, category, created_at FROM blog_posts WHERE status='published' ORDER BY created_at DESC, id DESC LIMIT 3");
    $refUser = current_user() ? null : rmt_invite_referrer();
    $ruinedLines = rmt_reviews_ruined(3);
    $ruinedTotal = rmt_reviews_ruined_count();
    $askDests = all_dests();
    view('home', compact('trending','stories','reviews','meetups','guides','stat_destinations','stat_community_reviews','stat_editorial_reviews','stat_travelers','taxPost','latestPosts','refUser','ruinedLines','ruinedTotal','askDests'), [
        'title' => 'RuinMyTrip — 2026 travel costs, tourist taxes, tickets and honest reviews',
        'description' => 'What a trip actually costs in 2026: tourist taxes, ticket prices, scams and new rules, researched from official sources. No fake travelers. No invented reviews.',
        'jsonld' => jsonld(['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'RuinMyTrip','url'=>cfg('app_url'),
            'potentialAction'=>['@type'=>'SearchAction','target'=>url('search?q={q}'),'query-input'=>'required name=q']]),
    ]);
}

function explore(array $a): void {
    $qs = trim((string)($_GET['q'] ?? '')); $cat = trim((string)($_GET['category'] ?? ''));
    $sortIn = (string) ($_GET['sort'] ?? '');
    $sort = in_array($sortIn, ['popular','rating'], true) ? $sortIn : 'name';
    // "reviews" on a destination card means TRAVELER reviews. Counting our own editorial review
    // here would put "1 review" on every card while the community section is empty, which is
    // exactly the impression this site exists not to give. avg_rating uses the same
    // role-exclusion as rmt_community_avg() so "highest rated" never reflects our own opinion.
    $sql = "SELECT d.*,
              (SELECT COUNT(*) FROM reviews r JOIN users u ON u.id=r.user_id
                WHERE r.destination_id=d.id AND r.status='published' AND u.role <> ?) reviews,
              (SELECT COUNT(*) FROM reviews r JOIN users u ON u.id=r.user_id
                WHERE r.destination_id=d.id AND r.status='published' AND u.role  = ?) editorial,
              (SELECT COUNT(*) FROM trips t WHERE t.destination_id=d.id AND t.status='published') trips,
              (SELECT COUNT(*) FROM saves s WHERE s.target_type='destination' AND s.target_id=d.id) wants,
              (SELECT AVG(r.rating) FROM reviews r JOIN users u ON u.id=r.user_id
                WHERE r.destination_id=d.id AND r.status='published' AND u.role <> ?) avg_rating
            FROM destinations d WHERE 1=1";
    $args = [RMT_EDITORIAL_ROLE, RMT_EDITORIAL_ROLE, RMT_EDITORIAL_ROLE];
    // LOWER() on both sides, not bare LIKE: LIKE is case-insensitive on SQLite (local dev) but
    // case-SENSITIVE on Postgres (production), so this silently returned zero results in
    // production for any search that didn't match the stored capitalization exactly.
    if ($qs !== '') {
        $sql .= ' AND (LOWER(d.name) LIKE ? OR LOWER(d.country) LIKE ? OR LOWER(d.summary) LIKE ?)';
        $needle = '%'.mb_strtolower($qs).'%';
        $args[]=$needle;$args[]=$needle;$args[]=$needle;
    }
    if ($cat !== '') { $sql .= ' AND d.category = ?'; $args[] = $cat; }
    // Postgres resolves a non-trivial ORDER BY expression (anything beyond a bare column/alias)
    // against the FROM-clause tables, not the SELECT list -- so `ORDER BY avg_rating IS NULL`
    // fails with "column avg_rating does not exist" even though `avg_rating` is a real output
    // column, because that expression isn't a bare reference. SQLite has no such restriction,
    // which is exactly why this passed local testing and then 500'd in production. Wrapping the
    // whole query as a derived table makes every output column, including subquery aliases like
    // avg_rating, a real column of `x` that any ORDER BY expression can reference safely.
    $sql = "SELECT * FROM ($sql) x";
    $sql .= match ($sort) {
        'popular' => ' ORDER BY wants DESC, name',
        // A single five-star review should not be able to outrank a destination with fifty
        // honest ones -- `reviews < 2` pushes anything below that sample size out of the ranked
        // tier (still sorted among itself by whatever rating it has, just never above real
        // consensus). `IS NULL`/`< 2` both evaluate to 0/1 in both drivers, valid ORDER BY keys.
        'rating'  => ' ORDER BY (avg_rating IS NULL OR reviews < 2), avg_rating DESC, name',
        default   => ' ORDER BY name',
    };
    $dests = q_all($sql, $args);
    $cats = q_all('SELECT DISTINCT category FROM destinations WHERE category IS NOT NULL ORDER BY category');
    $countries = q_all('SELECT country, COUNT(*) n FROM destinations WHERE country IS NOT NULL AND country <> \'\' GROUP BY country ORDER BY country');
    $topTags = rmt_top_tags(14);
    view('explore', compact('dests','cats','qs','cat','sort','topTags','countries'), [
        'title' => 'Explore destinations: 2026 costs, taxes and tickets | RuinMyTrip',
        'description' => 'Browse traveler-reviewed destinations. Filter by style — culture, adventure, nature, food, city.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Explore','url'=>url('explore')]],
    ]);
}

function country_show(array $a): void {
    $country = rmt_country_from_slug((string) ($a['slug'] ?? ''));
    if (!$country) not_found();
    $slug = rmt_country_slug($country);
    $dests = q_all('SELECT * FROM destinations WHERE country = ? ORDER BY name', [$country]);
    if (!$dests) not_found();
    $guides = q_all("SELECT g.*, d.name dest_name FROM guides g JOIN destinations d ON d.id=g.destination_id
                     WHERE d.country = ? AND g.status='published' ORDER BY g.id DESC", [$country]);
    authors_fill($guides);
    $n = count($dests);
    view('country_show', compact('country','slug','dests','guides'), [
        'title' => $country.' 2026: costs, tickets, taxes and what nearly ruins it | RuinMyTrip',
        'description' => $n.' destination'.($n===1?'':'s').' in '.$country.' with 2026 prices, tourist taxes, tickets and the friction that catches visitors off guard.',
        'og_image' => abs_url($dests[0]['hero_url'] ?? ''),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Explore','url'=>url('explore')],
                          ['name'=>$country,'url'=>url('in/'.$slug)]],
    ]);
}

function destination(array $a): void {
    $d = dest_by_slug($a['slug']); if (!$d) not_found();
    $id = (int)$d['id'];
    $trips = q_all("SELECT t.* FROM trips t WHERE t.destination_id=? AND t.status='published' ORDER BY t.id DESC LIMIT 8", [$id]);
    authors_fill($trips);
    // $trips is capped at 8 for the page grid -- the badge next to "Trip stories" must show the
    // true total, not silently cap at 8 the way the reviews count did before rmt_community_avg().
    $tripCount = (int) q_one("SELECT COUNT(*) c FROM trips WHERE destination_id=? AND status='published'", [$id])['c'];
    // Editorial always sorts first regardless of id, so it can never be pushed out by LIMIT once
    // a destination has 30+ community reviews -- there is exactly one editorial review per
    // destination, so this never crowds out real ones. Within the rest, verified still wins the
    // tie it always has, but the next tiebreaker is now how many travelers actually found the
    // review useful, not just recency -- a well-vetted review from last year should not get
    // buried under a same-day one-liner.
    // Editorial reviews OF PLACES are excluded here. Community reviews of places are not: a
    // traveler's review of a hotel in this city belongs on the city's page. But there is now more
    // than one editorial review touching a destination, and letting them all through would turn the
    // page into a wall of our own writing and break the "exactly one Official Review" lead the
    // section is built around. The place ones are read on their own /p/ pages.
    $reviews = q_all("SELECT r.*,
                        (SELECT COUNT(*) FROM review_votes rv WHERE rv.review_id=r.id AND rv.vote_type='useful') useful_count
                      FROM reviews r JOIN users u ON u.id=r.user_id
                      WHERE r.destination_id=? AND r.status='published'
                        AND NOT (u.role=? AND r.place_id IS NOT NULL)
                      ORDER BY (u.role=?) DESC, r.verified DESC, useful_count DESC, r.id DESC LIMIT 30",
                     [$id, RMT_EDITORIAL_ROLE, RMT_EDITORIAL_ROLE]);
    authors_fill($reviews);
    // Editorial and community reviews are rendered in separate, separately labelled sections.
    [$editorial, $reviews] = rmt_split_editorial($reviews);
    $tips = rmt_destination_tips($id);
    $guides = q_all("SELECT g.* FROM guides g WHERE g.destination_id=? AND g.status='published' ORDER BY g.id DESC", [$id]);
    authors_fill($guides);
    $meetups = q_all("SELECT m.* FROM meetups m WHERE m.destination_id=? AND m.status='published' ORDER BY m.date_start", [$id]);
    // Community score only. An editorial rating is the site's own opinion and must never be
    // presented, or marked up for search engines, as traveler consensus.
    $avg = rmt_community_avg($id);
    $avgByCategory = rmt_community_avg_by_category($id);
    $me = current_user();
    $going = rmt_going_list_for_destination($id, $me);
    $myGoing = $me ? rmt_going_for_user_dest((int)$me['id'], $id) : null;
    $saved = $me ? (bool) q_one("SELECT 1 FROM saves WHERE user_id=? AND target_type='destination' AND target_id=?", [(int)$me['id'], $id]) : false;
    $wantCount = (int) (q_one("SELECT COUNT(*) c FROM saves WHERE target_type='destination' AND target_id=?", [$id])['c'] ?? 0);
    // Top places teaser: the destination page shows the best-rated few, /d/{slug}/places has them all.
    $topPlaces = rmt_places_for_destination($id, '', 6);
    $placeCount = array_sum(rmt_place_type_counts($id));
    // The category landing pages this city actually qualifies for. A destination is where a
    // reader looking for "hotels in Paris" starts, and until now those pages were reachable
    // only from the sitemap and from each other -- present in the index we submit, and
    // unreachable by following links from the city they are about.
    $categoryPages = [];
    foreach (rmt_indexable_type_counts($id) as $t => $n) {
        if (!rmt_indexable('category', ['place_count' => $n])['ok']) continue;
        if (($slug = rmt_category_slug((string) $t)) === null) continue;
        $categoryPages[] = ['slug' => $slug, 'label' => rmt_category_heading((string) $t, (string) $d['name']), 'n' => $n];
    }
    $photos = rmt_destination_photos($id, 12);
    $photoCount = (int) (q_one("SELECT
            (SELECT COUNT(*) FROM trip_photos tp JOIN trips t ON t.id=tp.trip_id WHERE t.destination_id=? AND t.status='published') +
            (SELECT COUNT(*) FROM review_photos rp JOIN reviews r ON r.id=rp.review_id WHERE r.destination_id=? AND r.status='published') c",
        [$id, $id])['c'] ?? 0);
    $relatedPosts = rmt_blog_posts_for_destination((string) $d['slug']);
    $been = $me ? (bool) rmt_visit_get((int)$me['id'], $id) : false;
    $beenCount = rmt_visit_count($id);
    $beenPeople = rmt_visits_for_destination($id, 8);
    $wantPeople = rmt_wanters_for_destination($id, 8);
    $comments = q_all("SELECT c.*, u.username, p.avatar_url FROM comments c JOIN users u ON u.id=c.user_id
                       LEFT JOIN profiles p ON p.user_id=u.id
                       WHERE c.target_type='destination' AND c.target_id=? AND c.status='published' ORDER BY c.id", [$id]);

    // Discovery: top by kind, highest rated, most reviewed, what is being reviewed now, and which
    // neighborhoods have more than one place in them. Assembled in one call because the modules
    // share a stats query, a cover lookup and a category lookup -- building them separately would
    // run each of those several times over for one page.
    $discovery = rmt_destination_discovery($id);
    // What travelers are saying about the city right now, above the archive of finished writing.
    $talk = rmt_posts_for_destination($id, 3);
    view('destination', compact('d','trips','tripCount','reviews','editorial','tips','guides','meetups','going','myGoing','avg','avgByCategory','me','saved','wantCount','photos','photoCount','topPlaces','placeCount','categoryPages','relatedPosts','been','beenCount','beenPeople','wantPeople','comments','discovery','talk'), [
        'title' => rmt_destination_page_title($d),
        'description' => $d['summary'],
        'robots' => rmt_robots_for(rmt_indexable('destination', $d + ['place_count' => (int) $placeCount])),
        'og_image' => abs_url($d['hero_url']),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Explore','url'=>url('explore')],
                          ['name'=>$d['country'],'url'=>url('in/'.rmt_country_slug((string)$d['country']))],
                          ['name'=>$d['name'],'url'=>url('d/'.$d['slug'])]],
        'jsonld' => jsonld(['@context'=>'https://schema.org','@type'=>'TouristDestination','name'=>$d['name'],
            'description'=>$d['summary'],'url'=>url('d/'.$d['slug']),
            // No aggregateRating: TouristDestination is not one of the types Google accepts for a
            // review rich result, so a rating here is reported as an invalid reviewed-item type.
            // The visible community score on the page is unaffected.
            'geo'=>['@type'=>'GeoCoordinates','latitude'=>$d['lat'],'longitude'=>$d['lng']]]),
    ]);
}

/** GET /d/{slug}/photos — the full gallery; the destination page itself only teases 12. */
function destination_photos(array $a): void {
    $d = dest_by_slug($a['slug']); if (!$d) not_found();
    $photos = rmt_destination_photos((int)$d['id'], 300);
    view('destination_photos', compact('d','photos'), [
        'title' => 'Photos of '.$d['name'].', '.$d['country'].' — RuinMyTrip',
        'description' => 'Real traveler photos from trips and reviews in '.$d['name'].'.',
        'og_image' => $photos ? abs_url($photos[0]['url']) : abs_url($d['hero_url']),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Explore','url'=>url('explore')],
                           ['name'=>$d['name'],'url'=>url('d/'.$d['slug'])],['name'=>'Photos','url'=>url('d/'.$d['slug'].'/photos')]],
    ]);
}

/**
 * GET /d/{slug}/places — everything reviewable in one destination, best-rated first.
 *
 * An unknown ?type is treated as "all" rather than 404ing: the filter is a view preference, and a
 * stale bookmark should show the page, not an error.
 */
/**
 * GET /d/<city>/n/<area> - one neighborhood.
 *
 * NOINDEX for now, deliberately. The page is real and useful and every link on it is crawlable,
 * but whether an area has enough behind it to deserve a place in the index is a question with an
 * answer -- density, uniqueness, real inventory -- and that answer belongs in one central rule
 * rather than in this function. Until that rule exists, the honest default for a new page type is
 * to be discoverable by people and invisible to the index.
 */
function neighborhood_show(array $a): void {
    $d = q_one("SELECT * FROM destinations WHERE slug = ?", [(string) $a['slug']]);
    if (!$d) { not_found(); return; }
    $nb = rmt_nb_find((int) $d['id'], (string) $a['nb']);
    if (!$nb) { not_found(); return; }

    $byType = rmt_nb_type_counts((int) $nb['id']);
    $total  = array_sum($byType);
    // The page is always real and always crawlable; whether it belongs in the index is decided in
    // one place, against the density it actually has, and not by a constant written here.
    $nbVerdict = rmt_indexable('neighborhood',
        ['kind' => (string) $nb['kind'], 'place_count' => $total, 'type_count' => count($byType)]);
    $type   = (string) input('type');
    if ($type !== '' && !isset($byType[$type])) $type = '';      // a filter that returns nothing is not offered
    $places = rmt_nb_places((int) $nb['id'], $type ?: null);
    // Two batched lookups for the whole page, never one per card -- the same shape the browse page
    // uses, so the cards here cannot drift from the cards there.
    $me = current_user();
    $ids = array_map(static fn(array $p) => (int) $p['id'], $places);
    $savedMap = rmt_saved_place_map($me ? (int) $me['id'] : null, $ids);
    $saveCounts = rmt_place_save_counts($ids);

    view('neighborhood', [
        'd' => $d, 'nb' => $nb, 'byType' => $byType, 'total' => $total,
        'type' => $type === '' ? null : $type, 'places' => $places,
        'me' => $me, 'savedMap' => $savedMap, 'saveCounts' => $saveCounts,
    ], [
        'title' => $nb['canonical_name'] . ' in ' . $d['name'] . ' — RuinMyTrip',
        'description' => 'Places in ' . $nb['canonical_name'] . ', ' . $d['name'] . ': hotels, restaurants and things to do, with what we actually know about each.',
        'robots' => rmt_robots_for($nbVerdict),
        'canonical' => url('d/' . $d['slug'] . '/n/' . $nb['slug']),
    ]);
}

function destination_places(array $a): void {
    $d = dest_by_slug($a['slug']); if (!$d) not_found();
    $id = (int) $d['id'];
    $type = (string) ($_GET['type'] ?? '');
    if (!in_array($type, RMT_PLACE_TYPES, true)) $type = '';
    $sort = (string) ($_GET['sort'] ?? 'best');
    if (!isset(RMT_BROWSE_SORTS[$sort])) $sort = 'best';
    $places = rmt_destination_browse($id, $type, $sort);
    $counts = rmt_place_type_counts($id);
    $total = array_sum($counts);
    $label = $type === '' ? 'Places' : rmt_place_type_label($type, true);
    // Two batched lookups for the whole page, never one per card.
    $me = current_user();
    $ids = array_map(static fn(array $p) => (int) $p['id'], $places);
    $savedMap = rmt_saved_place_map($me ? (int) $me['id'] : null, $ids);
    $saveCounts = rmt_place_save_counts($ids);
    // The sort is a way to read the same list, not a different page. Every ordering canonicalises
    // to the unsorted URL so the four of them cannot compete with each other in an index.
    // Where this view's authority belongs. A type filter that has its own landing page points at
    // it -- two URLs listing the same hotels in the same city is one page and one duplicate, and
    // the landing page is the one written for the query. Filters without a landing page (below the
    // threshold, or a sort) canonicalise to the unfiltered browse page.
    $catSlug = $type !== '' ? rmt_category_slug($type) : null;
    $hasLanding = $catSlug !== null
        && rmt_indexable('category', ['place_count' => (int) ($counts[$type] ?? 0)])['ok'];
    if ($hasLanding) {
        $canonical = url(ltrim('/d/'.$d['slug'].'/'.$catSlug, '/'));
        $robots = 'noindex,follow';
    } else {
        $canonical = url(ltrim('/d/'.$d['slug'].'/places', '/'));
        // A sorted or filtered view of a page is not a second page. The unfiltered browse page
        // itself stays indexable; every permutation of it does not.
        $robots = ($type === '' && $sort === 'best') ? 'index, follow' : 'noindex,follow';
    }
    view('destination_places', compact('d','places','counts','total','type','label','me','savedMap','saveCounts','sort'), [
        'canonical' => $canonical,
        'robots' => $robots,
        'title' => $label.' in '.$d['name'].' 2026: tickets, prices and reviews | RuinMyTrip',
        'description' => 'Hotels, restaurants, attractions and experiences in '.$d['name'].', '.$d['country'].', with current 2026 prices and official reviews.',
        'og_image' => abs_url($d['hero_url']),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Explore','url'=>url('explore')],
                          ['name'=>$d['name'],'url'=>url('d/'.$d['slug'])],['name'=>'Places','url'=>url('d/'.$d['slug'].'/places')]],
    ]);
}

/**
 * GET /p/{slug} — one hotel/restaurant/attraction/experience, every review of it, one average.
 *
 * The aggregateRating in the markup is the community number and excludes editorial by role (see
 * rmt_place_stats), so the rating Google shows is the same one a reader sees and neither includes
 * the site's own opinion. When nobody has published a review yet there is no rating property at
 * all — an empty aggregate would be a rating claim with nothing behind it.
 */
function place_show(array $a): void {
    $p = rmt_place_by_slug($a['slug']);
    if (!$p) {
        // The slug may be one this place used to have. Identity is the row id, so a rename is a
        // permanent redirect to wherever that row lives now -- never a 404, and never a chain,
        // because the target is always read fresh from the place rather than from history.
        // Any status the public may read, not only active. A place that was renamed and later
        // closed still has the same row, the same reviews and the same inbound links; 404ing its
        // old URL would throw away both the redirect and the page it points at.
        $prev = rmt_place_for_retired_slug((string) $a['slug']);
        if ($prev && rmt_place_is_public((string) $prev['status'])) redirect_permanent(url('p/'.$prev['slug']));
        not_found();
    }
    $id = (int) $p['id'];
    $stats = rmt_place_stats($id);
    $breakdown = rmt_place_rating_breakdown($id);
    // One grouped query for every aspect, not one per review. Only the aspects enough people have
    // rated are handed to the view; see RMT_ASPECT_MIN_SAMPLE.
    $aspectAverages = rmt_place_aspect_averages_shown($id);
    $reviews = rmt_place_reviews($id);
    [$editorial, $reviews] = rmt_split_editorial($reviews);
    $photos = rmt_place_gallery($id, 12);
    $photoCount = rmt_place_photo_count($id);
    $hours = rmt_place_hours($id);
    $hoursByDay = rmt_place_hours_by_day($hours);
    $openNow = rmt_place_open_now($hours, $p['timezone'] ?? null);
    $address = rmt_place_address($p);
    $coords = rmt_place_normalize_coords($p['lat'] ?? null, $p['lng'] ?? null);
    $category = rmt_place_category(isset($p['category_id']) ? (int) $p['category_id'] : null);
    $priceLabel = rmt_place_price_label(isset($p['price_level']) && $p['price_level'] !== null ? (int) $p['price_level'] : null);
    $cover = rmt_place_cover_url($id);
    $me = current_user();
    $typeLabel = rmt_place_type_label((string) $p['type']);

    $ed = rmt_place_editorial($id);
    // What is actually near this place, by distance, when we hold its coordinates. The older
    // list -- editorially covered places sharing a destination row -- stays as the fallback for a
    // place we have not located yet, so nothing regresses for the ones still unenriched.
    $nearbyGeo = rmt_places_nearby($p, '', 6);
    $nearby = $nearbyGeo ?: rmt_place_nearby($id, (int)$p['destination_id']);
    // Alternatives, which is a different question from what is close. In a city where we hold six
    // places the two lists are frequently the same three venues, and two headings over one list is
    // one module and a wasted screen -- so the weaker one is dropped rather than repeated.
    $placeArea = rmt_nb_of_place($p);
    // Guides of this city that actually name this venue. The other direction of the same
    // relationship: somebody on the Louvre page wondering what the ticket change means
    // should be able to reach the guide that explains it.
    $inGuides = rmt_guides_mentioning_place($p);
    $similar = rmt_similar_places($p, 6);
    if (rmt_similar_is_redundant($similar, $nearby)) $similar = [];
    // The lists this reader could add this place to, and whether it is already on one. Only their
    // own: a list belongs to the person who made it.
    $myLists = $me ? q_all(
        "SELECT c.id, c.title,
                (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id = c.id AND ci.place_id = ?) has
           FROM collections c
          WHERE c.user_id = ? AND c.status <> 'deleted'
          ORDER BY c.updated_at DESC, c.id DESC LIMIT 25", [$id, (int) $me['id']]) : [];
    $saved = rmt_place_is_saved($id, $me ? (int) $me['id'] : null);
    $saveCount = rmt_place_save_count($id);
    $canonical = url(ltrim(rmt_place_path($p), '/'));

    // Address, geo, phone, price band and opening hours are all safe on any schema.org Place and
    // are emitted only for the values we actually hold -- see rmt_place_schema_attributes.
    $ld = ['@context'=>'https://schema.org', '@type'=>rmt_place_schema_type((string) $p['type']), 'name'=>$p['name'],
           'url'=>$canonical]
        + rmt_place_schema_attributes($p, $hours);
    if ($cover) $ld['image'] = abs_url($cover);
    if ($ed && !empty($ed['what_it_is'])) $ld['description'] = mb_strimwidth(strip_tags((string)$ed['what_it_is']), 0, 300, '…');
    // aggregateRating is COMMUNITY ONLY and omitted entirely at zero. An empty aggregate, or one
    // padded with our own rating, would be a consensus claim with nothing behind it.
    // ...and only on a type Google accepts as a reviewed item. An attraction is a schema.org
    // Place, which is not on that list, so hanging a rating off it is rejected as
    // "Invalid object type for field itemReviewed" (see rmt_place_review_type).
    $ratingEligible = rmt_place_review_type((string) $p['type']) !== null;
    if ($ratingEligible && $stats['c'] > 0 && $stats['a'] !== null) {
        $ld['aggregateRating'] = ['@type'=>'AggregateRating','ratingValue'=>$stats['a'],
                                  'reviewCount'=>$stats['c'],'bestRating'=>5,'worstRating'=>1];
    }
    // The editorial review is marked up as what it actually is: one Review, authored by the
    // organisation, never folded into an aggregate. That is semantically correct and it is the
    // opposite of inventing review counts.
    if ($ratingEligible && $editorial) {
        $er = $editorial[0];
        $ld['review'] = ['@type'=>'Review',
            'author'=>['@type'=>'Organization','name'=>rmt_editorial_name()],
            'name'=>$er['title'],
            'reviewRating'=>['@type'=>'Rating','ratingValue'=>(int)$er['rating'],'bestRating'=>5,'worstRating'=>1],
            'datePublished'=>substr((string)$er['created_at'], 0, 10),
            'reviewBody'=>mb_strimwidth(strip_tags((string)$er['body']), 0, 500, '…')];
    }

    // Description priority: the hand-written one, then the editorial opener, then the honest
    // "nothing here yet" line. Never a generated template sentence dressed up as a summary.
    $desc = $ed['meta_description'] ?? null;
    if (!$desc && $ed && !empty($ed['what_it_is'])) $desc = mb_strimwidth(strip_tags((string)$ed['what_it_is']), 0, 155, '…');
    if (!$desc) {
        $desc = $stats['c'] > 0
            ? $p['name'].' in '.$p['dest_name'].': '.$stats['a'].'/5 from '.$stats['c'].' traveler '.($stats['c']===1?'review':'reviews').'.'
            : $p['name'].' in '.$p['dest_name'].'. No traveler reviews yet, be the first to write one.';
    }

    // Robots from the same rule the sitemap uses, so the two can never disagree about this page.
    // Open now is a claim about today, so a closed place must not make it however complete its
    // hours are. Suppressed here rather than in the view: any other surface asking the same
    // question gets the same answer.
    if (!rmt_place_is_trading((string) $p['status'])) $openNow = null;

    $placeVerdict = rmt_indexable('place', $p + [
        'hours_count'  => count($hours),
        'photo_count'  => (int) $photoCount,
        'review_count' => (int) ($stats['c'] ?? 0),
        'editorial'    => $ed['what_it_is'] ?? '',
    ]);
    // The question a traveler actually types belongs on the page about the thing they are asking
    // about, not two clicks away on the city page.
    $talk = rmt_posts_for_place((int) $p['id'], 3);
    view('place_show', compact('p','stats','breakdown','aspectAverages','reviews','editorial','photos','photoCount','me','typeLabel','ed','nearby','nearbyGeo','similar','myLists','placeArea','inGuides','saved','saveCount','hours','hoursByDay','openNow','address','coords','category','priceLabel','cover','talk'), [
        'title' => rmt_place_page_title($p),
        'description' => $desc,
        'canonical' => $canonical,
        'robots' => rmt_robots_for($placeVerdict),
        'og_image' => $cover ? abs_url($cover) : abs_url($p['dest_hero']),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Explore','url'=>url('explore')],
                          ['name'=>$p['dest_name'],'url'=>url('d/'.$p['dest_slug'])],
                          ['name'=>'Places','url'=>url('d/'.$p['dest_slug'].'/places')],
                          ['name'=>$p['name'],'url'=>$canonical]],
        'jsonld' => jsonld($ld),
    ]);
}

function profile(array $a): void {
    $u = q_one('SELECT u.*, p.display_name,p.bio,p.home_city,p.avatar_url,p.cover_url,p.credibility_score
                FROM users u LEFT JOIN profiles p ON p.user_id=u.id WHERE u.username=?', [$a['username']]);
    if (!$u) not_found();
    $uid = (int)$u['id'];
    $me = current_user();
    $isMe = $me && (int)$me['id'] === $uid;

    $trips = q_all("SELECT t.*, d.name dest_name FROM trips t LEFT JOIN destinations d ON d.id=t.destination_id
                    WHERE t.user_id=? AND t.status='published' ORDER BY t.id DESC", [$uid]);
    $reviews = q_all("SELECT * FROM reviews WHERE user_id=? AND status='published' ORDER BY id DESC", [$uid]);
    $guides = q_all("SELECT * FROM guides WHERE user_id=? AND status='published' ORDER BY id DESC", [$uid]);
    // Split by kind, because a list can hold places as well as cities and "3 destinations" over a
    // list of three restaurants is the site not paying attention.
    $collections = q_all("SELECT c.*,
                            (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id=c.id AND ci.destination_id IS NOT NULL) dest_count,
                            (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id=c.id AND ci.place_id IS NOT NULL) place_count,
                            (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id=c.id) item_count
                          FROM collections c WHERE c.user_id=? AND c.status='published' ORDER BY c.id DESC", [$uid]);
    $wishlist = q_all("SELECT d.id, d.slug, d.name, d.country FROM saves s JOIN destinations d ON d.id=s.target_id
                       WHERE s.user_id=? AND s.target_type='destination' ORDER BY d.name", [$uid]);

    // Every stat is a live COUNT — never a stored counter, never a placeholder.
    $stats = rmt_profile_stats($uid);
    $followers = $stats['followers'];
    $following = $stats['following'];
    $badges = rmt_user_badges($uid);
    $compliments = rmt_compliments_received($uid);
    $myCompliments = ($me && !$isMe) ? rmt_compliments_sent_by((int)$me['id'], $uid) : [];

    // Hosting is public: the host is already named on the meetup page and the index, and somebody
    // deciding whether to meet a stranger looks here. Attending is NOT -- see rmt_meetups_* .
    $hostedMeetups = rmt_meetups_hosted_upcoming($uid);
    $attendingMeetups = $isMe ? rmt_meetups_attending_upcoming($uid) : [];
    $plans = rmt_going_list_for_profile($uid, $me);
    $beenPlaces = rmt_visits_for_user($uid);

    $is_following = $me ? (bool) q_one('SELECT 1 FROM follows WHERE follower_id=? AND followee_id=?', [(int)$me['id'],$uid]) : false;
    $i_blocked_them = ($me && !$isMe) ? (bool) q_one('SELECT 1 FROM blocks WHERE blocker_id=? AND blocked_id=?', [(int)$me['id'],$uid]) : false;
    $is_blocked = ($me && !$isMe) ? rmt_is_blocked((int)$me['id'], $uid) : false;
    // What they have been saying lately, which on most profiles is the only recent thing there is.
    $talkPosts = rmt_posts_by_user($uid, 10);
    view('profile', compact('talkPosts','u','trips','reviews','guides','collections','followers','following','is_following','me','stats','badges','isMe','compliments','myCompliments','is_blocked','i_blocked_them','wishlist','hostedMeetups','attendingMeetups','plans','beenPlaces'), [
        'robots' => rmt_robots_for(rmt_indexable('profile', $u + [
            'review_count' => (int) ($stats['reviews'] ?? 0),
            'guide_count'  => (int) ($stats['guides'] ?? 0),
            'trip_count'   => (int) ($stats['trips'] ?? 0),
            'list_count'   => count($collections),
        ])),
        'title' => ($u['display_name'] ?: $u['username']).' (@'.$u['username'].') — RuinMyTrip',
        'description' => $u['bio'] ?: ('Traveler profile for @'.$u['username'].' on RuinMyTrip.'),
        'og_image' => rmt_card_url('u', (string) $u['username']),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'@'.$u['username'],'url'=>url('u/'.$u['username'])]],
        'jsonld' => jsonld(['@context'=>'https://schema.org','@type'=>'ProfilePage',
            'mainEntity'=>['@type'=>'Person','name'=>$u['display_name'] ?: $u['username'],
                           'alternateName'=>'@'.$u['username'],
                           'description'=>$u['bio'] ?: null,
                           'url'=>url('u/'.$u['username'])]]),
    ]);
}

/** GET /u/{username}/followers */
function profile_followers(array $a): void {
    $u = q_one('SELECT id, username FROM users WHERE username = ?', [$a['username']]);
    if (!$u) not_found();
    $people = rmt_followers((int)$u['id']);
    view('people_list', ['u'=>$u, 'people'=>$people, 'mode'=>'followers', 'me'=>current_user()], [
        'title' => 'Followers of @'.$u['username'].' — RuinMyTrip',
        'description' => 'Travelers following @'.$u['username'].' on RuinMyTrip.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'@'.$u['username'],'url'=>url('u/'.$u['username'])],
                          ['name'=>'Followers','url'=>url('u/'.$u['username'].'/followers')]],
    ]);
}

/** GET /u/{username}/following */
function profile_following(array $a): void {
    $u = q_one('SELECT id, username FROM users WHERE username = ?', [$a['username']]);
    if (!$u) not_found();
    $people = rmt_following((int)$u['id']);
    view('people_list', ['u'=>$u, 'people'=>$people, 'mode'=>'following', 'me'=>current_user()], [
        'title' => 'Travelers @'.$u['username'].' follows — RuinMyTrip',
        'description' => 'Travelers followed by @'.$u['username'].' on RuinMyTrip.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'@'.$u['username'],'url'=>url('u/'.$u['username'])],
                          ['name'=>'Following','url'=>url('u/'.$u['username'].'/following')]],
    ]);
}

/** GET /u/{username}/edit — owner only. The canonical edit-profile page. */
function profile_edit_form(array $a): void {
    require_login();
    $me = current_user();
    if ($me['username'] !== $a['username']) { forbidden('You can only edit your own profile.'); }
    view('profile_edit', ['me'=>$me, 'errors'=>[], 'p'=>$me], ['title'=>'Edit your profile — RuinMyTrip']);
}

/** POST /u/{username}/edit */
function profile_edit_submit(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    if ($me['username'] !== $a['username']) { forbidden('You can only edit your own profile.'); }

    $v = rmt_profile_validate($_POST);
    if (!$v['ok']) {
        view('profile_edit', ['me'=>$me, 'errors'=>$v['errors'], 'p'=>array_merge($me, $_POST)],
             ['title'=>'Edit your profile — RuinMyTrip']); return;
    }
    $d = $v['data'];

    // An uploaded photo wins over the URL field.
    if (!empty($_FILES['avatar']['name'] ?? '')) {
        $res = rmt_upload_image($_FILES['avatar'], (int)$me['id']);
        if (!$res['ok']) {
            view('profile_edit', ['me'=>$me, 'errors'=>[$res['error']], 'p'=>array_merge($me, $_POST)],
                 ['title'=>'Edit your profile — RuinMyTrip']); return;
        }
        $d['avatar_url'] = $res['url'];
        $old = q_one('SELECT avatar_key FROM profiles WHERE user_id=?', [(int)$me['id']]);
        db()->prepare('UPDATE profiles SET avatar_key=? WHERE user_id=?')->execute([$res['key'], (int)$me['id']]);
        if (!empty($old['avatar_key'])) rmt_storage_delete((string)$old['avatar_key']);
    }

    db()->prepare('UPDATE profiles SET display_name=?, bio=?, home_city=?, avatar_url=? WHERE user_id=?')
        ->execute([$d['display_name'], $d['bio'], $d['home_city'], $d['avatar_url'], (int)$me['id']]);
    flash('Profile updated.');
    redirect('/u/'.$me['username']);
}

/**
 * Fetch a merged, chronological activity stream across all five publishable content types
 * (trips, reviews, guides, blog posts, collections). $scopeUid null -> everyone (public
 * discover). $scopeUid set -> that user's own content plus everyone they follow (personal feed).
 * Each content type is fetched separately (their schemas differ too much for one clean UNION),
 * tagged with a $kind the view uses to link/label/excerpt correctly, then merged and sorted in
 * PHP.
 */
function rmt_activity_items(?int $scopeUid, int $limitEach = 40): array {
    if ($scopeUid !== null) {
        $args = [$scopeUid, $scopeUid];
        $followedT = '(t.user_id=? OR t.user_id IN (SELECT followee_id FROM follows WHERE follower_id=?))';
        $followedR = '(r.user_id=? OR r.user_id IN (SELECT followee_id FROM follows WHERE follower_id=?))';
        $followedG = '(g.user_id=? OR g.user_id IN (SELECT followee_id FROM follows WHERE follower_id=?))';
        $followedPlain = '(user_id=? OR user_id IN (SELECT followee_id FROM follows WHERE follower_id=?))';
    } else {
        $args = [];
        $followedT = $followedR = $followedG = $followedPlain = '1=1';
    }

    $trips = q_all("SELECT t.*, d.name dest_name, d.slug dest_slug FROM trips t
                    LEFT JOIN destinations d ON d.id=t.destination_id
                    WHERE t.status='published' AND $followedT
                    ORDER BY t.created_at DESC, t.id DESC LIMIT $limitEach", $args);
    foreach ($trips as &$row) {
        $row['kind'] = 'trip';
        $row['feed_url'] = url('trip/'.$row['id'].'/'.$row['slug']);
        $row['feed_excerpt'] = mb_strimwidth(strip_tags((string)$row['body']), 0, 180, '…');
    }
    unset($row);

    // The place is the point of a review, so the feed has to know which one. Without pl.name and
    // pl.slug the row could say who reviewed something and when, and not what -- a travel feed
    // whose entries do not name the venue.
    $reviews = q_all("SELECT r.*, d.name dest_name, pl.name place_name, pl.slug place_slug
                        FROM reviews r
                        LEFT JOIN destinations d ON d.id=r.destination_id
                        LEFT JOIN places pl ON pl.id=r.place_id AND pl.status='active'
                       WHERE r.status='published' AND $followedR
                       ORDER BY r.created_at DESC, r.id DESC LIMIT $limitEach", $args);
    foreach ($reviews as &$row) {
        $row['kind'] = 'review';
        $row['subject'] = $row['place_name'] ?: $row['subject_name'];
        $row['subject_url'] = $row['place_slug'] ? url('p/' . $row['place_slug']) : null;
        $row['title'] = $row['title'] ?: $row['subject_name'];
        $row['feed_url'] = url(ltrim(rmt_review_path($row), '/'));
        $row['feed_excerpt'] = mb_strimwidth(strip_tags((string)$row['body']), 0, 180, '…');
    }
    unset($row);

    $guides = q_all("SELECT g.*, d.name dest_name FROM guides g LEFT JOIN destinations d ON d.id=g.destination_id
                     WHERE g.status='published' AND $followedG
                     ORDER BY g.created_at DESC, g.id DESC LIMIT $limitEach", $args);
    foreach ($guides as &$row) {
        $row['kind'] = 'guide';
        $row['feed_url'] = url('g/'.$row['slug']);
        $row['feed_excerpt'] = mb_strimwidth(strip_tags((string)$row['summary']), 0, 180, '…');
    }
    unset($row);

    $posts = q_all("SELECT * FROM blog_posts WHERE status='published' AND $followedPlain
                    ORDER BY created_at DESC, id DESC LIMIT $limitEach", $args);
    foreach ($posts as &$row) {
        $row['kind'] = 'blog_post';
        $row['dest_name'] = null;
        $row['feed_url'] = url('blog/'.$row['slug']);
        $row['feed_excerpt'] = mb_strimwidth(strip_tags((string)$row['summary']), 0, 180, '…');
    }
    unset($row);

    // Lists can hold places as well as cities, so the cover has to look for the first CITY rather
    // than the first item -- and the summary has to count what is actually on the list. Before
    // this, a list of three restaurants had no cover and described itself as "3 destinations".
    $collections = q_all("SELECT c.*,
            (SELECT d.hero_url FROM collection_items ci JOIN destinations d ON d.id=ci.destination_id
              WHERE ci.collection_id=c.id AND ci.destination_id IS NOT NULL
              ORDER BY ci.sort, ci.id LIMIT 1) cover_url,
            (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id=c.id AND ci.destination_id IS NOT NULL) dest_count,
            (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id=c.id AND ci.place_id IS NOT NULL) place_count
          FROM collections c WHERE c.status='published' AND $followedPlain
          ORDER BY c.created_at DESC, c.id DESC LIMIT $limitEach", $args);
    foreach ($collections as &$row) {
        $row['kind'] = 'collection';
        $row['subject'] = $row['title'];
        $row['subject_url'] = url('c/' . $row['slug']);
        $row['dest_name'] = null;
        $row['feed_url'] = url('c/'.$row['slug']);
        $row['item_count'] = (int) $row['dest_count'] + (int) $row['place_count'];
        $row['feed_excerpt'] = $row['summary'] ?: rmt_collection_summary((int) $row['dest_count'], (int) $row['place_count']);
    }
    unset($row);

    if ($scopeUid !== null) {
        $goingSql = "(g.user_id=? OR (
                        g.user_id IN (SELECT followee_id FROM follows WHERE follower_id=?)
                        AND (g.visibility='public' OR g.visibility='followers')
                     ))";
        $goingArgs = [$scopeUid, $scopeUid];
    } else {
        $goingSql = "g.visibility='public'";
        $goingArgs = [];
    }
    $goings = q_all("SELECT g.*, d.name dest_name, d.slug dest_slug FROM going g
                     JOIN destinations d ON d.id=g.destination_id
                     WHERE $goingSql
                     ORDER BY g.created_at DESC, g.id DESC LIMIT $limitEach", $goingArgs);
    foreach ($goings as &$row) {
        $row['kind'] = 'going';
        $row['title'] = 'Heading to '.$row['dest_name'];
        $row['cover_url'] = null;
        $row['feed_url'] = url('d/'.$row['dest_slug']);
        $row['feed_excerpt'] = date('M j', strtotime((string)$row['date_from'])).' – '.date('M j, Y', strtotime((string)$row['date_to'])).'. Destination and dates only.';
    }
    unset($row);

    /* Talk belongs in the same stream as everything else. A conversation that only exists on its
       own page is a forum bolted to the side of the site; in the feed it is what makes the site
       look alive on a day when nobody published an article. */
    $talk = q_all("SELECT p.*, d.name dest_name, d.slug dest_slug,
                          o.body orig_body, ou.username orig_username
                     FROM posts p
                LEFT JOIN destinations d ON d.id=p.destination_id
                LEFT JOIN posts o ON o.id = p.repost_of AND o.status='published'
                LEFT JOIN users ou ON ou.id = o.user_id
                    WHERE p.status='published' AND " . str_replace('user_id', 'p.user_id', $followedPlain) . "
                 ORDER BY p.created_at DESC, p.id DESC LIMIT $limitEach", $args);
    foreach ($talk as &$row) {
        $row['kind'] = 'post';
        // A repost carries the original's words, credited, so the feed row says something.
        if (!empty($row['repost_of']) && trim((string) $row['body']) === '' && $row['orig_body'] !== null) {
            $row['body'] = $row['orig_body'];
            $row['subject'] = '@' . $row['orig_username'];
            $row['subject_url'] = url('u/' . $row['orig_username']);
        }
        $row['title'] = rmt_post_title($row);
        $row['cover_url'] = null;
        $row['subject'] = $row['dest_name'] ?: null;
        $row['subject_url'] = $row['dest_slug'] ? url('d/' . $row['dest_slug']) : null;
        $row['feed_url'] = url('post/' . (int) $row['id']);
        $row['feed_excerpt'] = mb_strimwidth(strip_tags((string) $row['body']), 0, 180, '…');
    }
    unset($row);

    $items = array_merge($trips, $reviews, $guides, $posts, $collections, $goings, $talk);
    usort($items, fn($x, $y) => strcmp((string)$y['created_at'], (string)$x['created_at']));
    $items = array_slice($items, 0, $limitEach);
    authors_fill($items);
    return $items;
}

function feed(array $a): void {
    require_login(); $me = current_user(); $uid = (int)$me['id'];
    /* Two scopes, named. Following is the point of following somebody; Everyone is what makes the
       site readable on the day you join and on any day your follows are quiet. */
    $scope = input('scope') === 'everyone' ? 'everyone' : 'following';
    $items = $scope === 'everyone' ? rmt_activity_items(null) : rmt_activity_items($uid);
    /* A feed scoped to who you follow is empty on the day you join, which is the day it matters
       most. Falling back to the whole site is not a lie as long as the page says so: the member
       sees a living place and can start following from it, instead of a blank page that reads as
       "nobody is here". */
    $isEveryone = $scope === 'everyone';
    if (!$items && $scope === 'following') {
        $items = rmt_activity_items(null);
        $isEveryone = (bool) $items;
    }
    view('feed', compact('items','me','isEveryone','scope'), [
        'title' => 'Your feed — RuinMyTrip',
        'description' => 'Latest trips, reviews, guides, collections and blog posts from travelers you follow.',
    ]);
}

/** Public, no-login activity stream across the whole site -- what a logged-out visitor or a
 *  logged-in user with zero follows sees instead of an empty feed. */
function discover(array $a): void {
    $items = rmt_activity_items(null);
    $topTags = rmt_top_tags(14);
    /* The public front door for anybody who arrived without an account. It showed a stream and
       nothing to join, so the two things a stranger can actually act on -- the conversation and
       the rooms -- now sit on it. */
    $topTalk = rmt_posts_top(5);
    $communities = rmt_community_browse(4);
    view('discover', ['items'=>$items, 'me'=>current_user(), 'topTags'=>$topTags,
                      'topTalk'=>$topTalk, 'communities'=>$communities], [
        'title' => 'Discover — RuinMyTrip',
        'description' => 'The latest trips, reviews, guides, collections and blog posts from every traveler on RuinMyTrip.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Discover','url'=>url('discover')]],
    ]);
}

/** GET /tags — every topic in use on published content, biggest first. */
function tags_index(array $a): void {
    $tags = rmt_top_tags(100);
    view('tags_index', ['tags'=>$tags], [
        'title' => 'Topics — RuinMyTrip',
        'description' => 'Browse every topic travelers are tagging: budget travel, solo trips, scams to avoid, and more.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Topics','url'=>url('tags')]],
    ]);
}

/** GET /tag/{name} — published trips, reviews, guides and blog posts carrying one hashtag. */
function tag_show(array $a): void {
    $name = strtolower((string)$a['name']);
    $tag = q_one('SELECT * FROM tags WHERE name=?', [$name]);
    if (!$tag) not_found();
    $items = rmt_tag_items((int)$tag['id']);
    view('tag_show', ['tag'=>$tag, 'items'=>$items, 'me'=>current_user()], [
        'title' => '#'.$tag['name'].' — RuinMyTrip',
        'description' => 'Trips, reviews, guides and blog posts tagged #'.$tag['name'].' by real travelers.',
        'og_image' => rmt_card_url('tag', (string) $tag['name']),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Topics','url'=>url('tags')],
                          ['name'=>'#'.$tag['name'],'url'=>url('tag/'.$tag['name'])]],
    ]);
}

function trip_show(array $a): void {
    $t = q_one("SELECT t.*, d.name dest_name, d.slug dest_slug FROM trips t
                LEFT JOIN destinations d ON d.id=t.destination_id WHERE t.id=?", [(int)$a['id']]);
    if (!$t || $t['status']!=='published') not_found();
    $t['author'] = author((int)$t['user_id']);
    $photos = q_all('SELECT * FROM trip_photos WHERE trip_id=? ORDER BY sort,id', [(int)$t['id']]);
    $comments = q_all("SELECT c.*, u.username, p.avatar_url FROM comments c JOIN users u ON u.id=c.user_id
                       LEFT JOIN profiles p ON p.user_id=u.id
                       WHERE c.target_type='trip' AND c.target_id=? AND c.status='published' ORDER BY c.id", [(int)$t['id']]);
    $me = current_user();
    $likeCount = (int) q_one("SELECT COUNT(*) n FROM likes WHERE target_type='trip' AND target_id=?", [(int)$t['id']])['n'];
    $saveCount = (int) q_one("SELECT COUNT(*) n FROM saves WHERE target_type='trip' AND target_id=?", [(int)$t['id']])['n'];
    $liked = $me && q_one('SELECT 1 FROM likes WHERE user_id=? AND target_type=? AND target_id=?', [(int)$me['id'],'trip',(int)$t['id']]);
    $saved = $me && q_one('SELECT 1 FROM saves WHERE user_id=? AND target_type=? AND target_id=?', [(int)$me['id'],'trip',(int)$t['id']]);
    $tags = rmt_tags_for('trip', (int)$t['id']);
    view('trip_show', compact('t','photos','comments','likeCount','saveCount','liked','saved','tags'), [
        'title' => rmt_meta_title((string) $t['title']),
        'description' => rmt_meta_description((string) $t['body']),
        'og_image' => abs_url($t['cover_url']),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>$t['dest_name']?:'Trips','url'=>$t['dest_slug']?url('d/'.$t['dest_slug']):url('explore')],['name'=>$t['title'],'url'=>url('trip/'.$t['id'])]],
        'jsonld' => jsonld(['@context'=>'https://schema.org','@type'=>'Article','headline'=>$t['title'],
            'datePublished'=>$t['created_at'],'author'=>['@type'=>'Person','name'=>$t['author']['display_name']??$t['author']['username']]]),
    ]);
}

function reviews_index(array $a): void {
    $me = current_user();
    $mine = input('mine') === '1';
    $cat  = input('category');
    $sort = input('sort') === 'helpful' ? 'helpful' : 'new';

    if ($mine) {
        // A user's own reviews INCLUDING drafts — the only place drafts are listed.
        require_login();
        $reviews = q_all("SELECT r.*, d.name dest_name, d.slug dest_slug FROM reviews r
                          LEFT JOIN destinations d ON d.id=r.destination_id
                          WHERE r.user_id = ? AND r.status <> 'removed'
                          ORDER BY CASE r.status WHEN 'draft' THEN 0 ELSE 1 END, r.id DESC",
                         [(int)$me['id']]);
    } else {
        $sql = "SELECT r.*, d.name dest_name, d.slug dest_slug,
                  (SELECT COUNT(*) FROM review_votes rv WHERE rv.review_id=r.id AND rv.vote_type='useful') useful_count
                FROM reviews r LEFT JOIN destinations d ON d.id=r.destination_id
                WHERE r.status='published'";
        $args = [];
        if (in_array($cat, RMT_REVIEW_CATEGORIES, true)) { $sql .= ' AND r.subject_type = ?'; $args[] = $cat; }
        $sql .= $sort === 'helpful' ? ' ORDER BY useful_count DESC, r.id DESC LIMIT 50' : ' ORDER BY r.id DESC LIMIT 50';
        $reviews = q_all($sql, $args);
    }
    authors_fill($reviews);
    view('reviews_index', compact('reviews','mine','cat','sort','me'), [
        'title'=>$mine ? 'Your reviews — RuinMyTrip' : '2026 destination reviews: taxes, tickets, what nearly ruins it | RuinMyTrip',
        'description'=>'Honest 2026 reviews of destinations, hotels, restaurants and attractions: current prices, tourist taxes, and the part that nearly ruins the trip.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Reviews','url'=>url('reviews')]],
    ]);
}

function guides_index(array $a): void {
    $guides = q_all("SELECT g.*, d.name dest_name FROM guides g LEFT JOIN destinations d ON d.id=g.destination_id
                     WHERE g.status='published' ORDER BY g.id DESC");
    authors_fill($guides);
    view('guides_index', compact('guides'), [
        'title'=>'2026 travel guides: costs, tickets, tourist taxes and scams | RuinMyTrip',
        'description'=>'Practical 2026 city guides with current tourist taxes, ticket prices, transit fares and the friction that catches visitors off guard.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Guides','url'=>url('guides')]],
    ]);
}

function guide_show(array $a): void {
    $g = q_one("SELECT g.*, d.name dest_name, d.slug dest_slug FROM guides g
                LEFT JOIN destinations d ON d.id=g.destination_id WHERE g.slug=?", [$a['slug']]);
    if (!$g || $g['status']!=='published') not_found();
    $g['author'] = author((int)$g['user_id']);
    $me = current_user();
    $gid = (int) $g['id'];
    $comments = q_all("SELECT c.*, u.username, p.avatar_url FROM comments c JOIN users u ON u.id=c.user_id
                       LEFT JOIN profiles p ON p.user_id=u.id
                       WHERE c.target_type='guide' AND c.target_id=? AND c.status='published' ORDER BY c.id", [$gid]);
    $likeCount = (int) q_one("SELECT COUNT(*) n FROM likes WHERE target_type='guide' AND target_id=?", [$gid])['n'];
    $saveCount = (int) q_one("SELECT COUNT(*) n FROM saves WHERE target_type='guide' AND target_id=?", [$gid])['n'];
    $liked = $me && q_one('SELECT 1 FROM likes WHERE user_id=? AND target_type=? AND target_id=?', [(int)$me['id'],'guide',$gid]);
    $saved = $me && q_one('SELECT 1 FROM saves WHERE user_id=? AND target_type=? AND target_id=?', [(int)$me['id'],'guide',$gid]);
    $tags = rmt_tags_for('guide', $gid);
    // What this guide actually names, in its own destination. Computed from the text as
    // written rather than stored, so an edit changes the links immediately.
    $mentions = rmt_editorial_entities((int) ($g['destination_id'] ?? 0),
                                       (string) ($g['body'] ?? '') . ' ' . (string) ($g['summary'] ?? ''));

    view('guide_show', compact('mentions','g','me','comments','likeCount','saveCount','liked','saved','tags'), [
        'title'=>rmt_meta_title((string) $g['title']),
        'description'=>rmt_meta_description((string) $g['summary']),
        'og_image'=>abs_url($g['cover_url']),
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Guides','url'=>url('guides')],['name'=>$g['title'],'url'=>url('g/'.$g['slug'])]],
        'jsonld'=>jsonld(['@context'=>'https://schema.org','@type'=>'Article','headline'=>$g['title'],'datePublished'=>$g['created_at']]),
    ]);
}

/**
 * Validate a submitted guide. Body is stored and rendered as plain text for traveler-authored
 * guides (see guide_show.php) -- only editorial/seed content is trusted with raw HTML -- so the
 * length floor is generous enough to require an actually useful guide, not a one-liner.
 * @return array{ok:bool, errors:string[], data:array<string,mixed>}
 */
function rmt_guide_validate(array $in): array {
    $errors = [];
    $title = trim((string) ($in['title'] ?? ''));
    $summary = trim((string) ($in['summary'] ?? ''));
    $body = (string) ($in['body'] ?? '');
    $dest = (int) ($in['destination_id'] ?? 0);
    $cover = trim((string) ($in['cover_url'] ?? ''));

    if (mb_strlen($title) < 5) $errors[] = 'Give your guide a title (5+ characters).';
    if (mb_strlen($title) > 140) $errors[] = 'That title is too long.';
    if (mb_strlen($summary) < 10) $errors[] = 'Add a one-line summary (10+ characters).';
    if (mb_strlen($summary) > 300) $errors[] = 'That summary is too long (300 characters max).';
    if (strlen($body) < 100) $errors[] = 'A guide needs real detail -- write at least 100 characters.';
    if (mb_strlen($body) > 20000) $errors[] = 'That guide is too long.';
    if ($cover !== '' && (!filter_var($cover, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $cover))) {
        $errors[] = 'Cover photo URL must be a full https:// web address.';
    }
    if ($dest > 0 && !dest_by_id($dest)) $errors[] = 'That destination does not exist.';

    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'title' => $title, 'summary' => $summary, 'body' => $body,
        'destination_id' => $dest ?: null, 'cover_url' => $cover,
    ]];
}

/** Only the author may edit or delete a guide. Editorial guides are edited via the DB, not here. */
function rmt_guide_can_edit(array $g, ?array $user): bool {
    return $user !== null && (int) $g['user_id'] === (int) $user['id'];
}

function guide_new_form(array $a): void {
    require_login();
    view('guide_new', ['dests'=>all_dests(),'errors'=>[]], ['title'=>'Write a guide — RuinMyTrip','description'=>'Share a detailed, practical travel guide.']);
}

function guide_create(array $a): void {
    require_verified_email(); csrf_check(); $me = current_user();
    if (!rmt_submit_ok('guide_new', input('_submit'))) {
        flash('That guide was already submitted.'); redirect('/guides'); return;
    }
    if (!rmt_rate_ok('guide_create', (string)$me['id'], 10, 3600)) {
        view('guide_new', ['dests'=>all_dests(),'errors'=>['You are posting very fast. Try again later.']],
             ['title'=>'Write a guide — RuinMyTrip']); return;
    }
    $v = rmt_guide_validate($_POST);
    if (!$v['ok']) {
        view('guide_new', ['dests'=>all_dests(),'errors'=>$v['errors']], ['title'=>'Write a guide — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $dest = $d['destination_id'] ? dest_by_id($d['destination_id']) : null;
    $cover = $d['cover_url'] ?: ($dest['hero_url'] ?? '');
    $slug = rmt_guide_unique_slug($d['title']);
    q_run("INSERT INTO guides (user_id,destination_id,slug,title,summary,body,cover_url,premium,status,created_at)
           VALUES (?,?,?,?,?,?,?,0,'published',?)",
        [(int)$me['id'], $d['destination_id'], $slug, $d['title'], $d['summary'], $d['body'], $cover, date('Y-m-d H:i:s')]);
    $gid = (int) q_one('SELECT id FROM guides WHERE slug=?', [$slug])['id'];
    rmt_sync_tags('guide', $gid, $d['title'], $d['summary'], $d['body']);
    rmt_notify_mentions('guide', $gid, (int)$me['id'], [], $d['title'], $d['summary'], $d['body']);
    flash('Guide published.');
    rmt_seo_announce('/g/'.$slug);
    redirect('/g/'.$slug);
}

/** A unique slug, appending -2/-3/... on collision. Guides (unlike trips/reviews) look up by slug alone. */
function rmt_guide_unique_slug(string $title, int $excludeId = 0): string {
    $base = slugify($title) ?: 'guide';
    $slug = $base; $n = 1;
    while (true) {
        $row = q_one('SELECT id FROM guides WHERE slug=?', [$slug]);
        if (!$row || (int)$row['id'] === $excludeId) return $slug;
        $n++; $slug = $base.'-'.$n;
    }
}

function guide_edit_form(array $a): void {
    require_login();
    $g = q_one('SELECT * FROM guides WHERE id=?', [(int)$a['id']]);
    if (!$g) not_found();
    if (!rmt_guide_can_edit($g, current_user())) { forbidden('That is not your guide.'); }
    view('guide_edit', ['g'=>$g, 'dests'=>all_dests(), 'errors'=>[]], ['title'=>'Edit guide — RuinMyTrip']);
}

function guide_edit_submit(array $a): void {
    require_login(); csrf_check();
    $g = q_one('SELECT * FROM guides WHERE id=?', [(int)$a['id']]);
    if (!$g) not_found();
    if (!rmt_guide_can_edit($g, current_user())) { forbidden('That is not your guide.'); }

    $v = rmt_guide_validate($_POST);
    if (!$v['ok']) {
        view('guide_edit', ['g'=>array_merge($g, $_POST), 'dests'=>all_dests(), 'errors'=>$v['errors']],
             ['title'=>'Edit guide — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $dest = $d['destination_id'] ? dest_by_id($d['destination_id']) : null;
    $cover = $d['cover_url'] ?: ($dest['hero_url'] ?? $g['cover_url']);
    $slug = rmt_guide_unique_slug($d['title'], (int)$g['id']);
    db()->prepare("UPDATE guides SET destination_id=?, title=?, slug=?, summary=?, body=?, cover_url=?, updated_at=? WHERE id=?")
        ->execute([$d['destination_id'], $d['title'], $slug, $d['summary'], $d['body'], $cover,
                   date('Y-m-d H:i:s'), (int)$g['id']]);
    rmt_sync_tags('guide', (int)$g['id'], $d['title'], $d['summary'], $d['body']);
    rmt_notify_mentions('guide', (int)$g['id'], (int)current_user()['id'], [], $d['title'], $d['summary'], $d['body']);
    flash('Guide updated.');
    redirect('/g/'.$slug);
}

/** POST /guide/{id}/delete — soft delete. Rows are never destroyed. */
function guide_delete(array $a): void {
    require_login(); csrf_check();
    $g = q_one('SELECT * FROM guides WHERE id=?', [(int)$a['id']]);
    if (!$g) not_found();
    if (!rmt_guide_can_edit($g, current_user())) { forbidden('That is not your guide.'); }
    db()->prepare("UPDATE guides SET status='removed', updated_at=? WHERE id=?")
        ->execute([date('Y-m-d H:i:s'), (int)$g['id']]);
    flash('Guide deleted.');
    redirect('/u/'.current_user()['username']);
}

/* ---------- blog ---------- */
const RMT_BLOG_CATEGORIES = ['stories', 'tips', 'safety', 'budget', 'gear', 'news'];

function blog_index(array $a): void {
    $cat = input('category');
    $sql = "SELECT p.* FROM blog_posts p WHERE p.status='published'";
    $args = [];
    if (in_array($cat, RMT_BLOG_CATEGORIES, true)) { $sql .= ' AND p.category = ?'; $args[] = $cat; }
    $sql .= ' ORDER BY p.id DESC LIMIT 50';
    $posts = q_all($sql, $args);
    authors_fill($posts);
    view('blog_index', ['posts'=>$posts,'cat'=>(string)$cat], [
        'title'=>'2026 travel notes: tourist taxes, ticket prices and rules | RuinMyTrip',
        'description'=>'Current 2026 tourist taxes, ticket prices, access fees and travel rules, plus stories from travelers who actually went.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Blog','url'=>url('blog')]],
    ]);
}

function blog_show(array $a): void {
    $p = q_one("SELECT * FROM blog_posts WHERE slug=?", [$a['slug']]);
    if (!$p || $p['status']!=='published') not_found();
    $p['author'] = author((int)$p['user_id']);
    $me = current_user();
    $pid = (int) $p['id'];
    $comments = q_all("SELECT c.*, u.username, p2.avatar_url FROM comments c JOIN users u ON u.id=c.user_id
                       LEFT JOIN profiles p2 ON p2.user_id=u.id
                       WHERE c.target_type='blog_post' AND c.target_id=? AND c.status='published' ORDER BY c.id", [$pid]);
    $likeCount = (int) q_one("SELECT COUNT(*) n FROM likes WHERE target_type='blog_post' AND target_id=?", [$pid])['n'];
    $saveCount = (int) q_one("SELECT COUNT(*) n FROM saves WHERE target_type='blog_post' AND target_id=?", [$pid])['n'];
    $liked = $me && q_one('SELECT 1 FROM likes WHERE user_id=? AND target_type=? AND target_id=?', [(int)$me['id'],'blog_post',$pid]);
    $saved = $me && q_one('SELECT 1 FROM saves WHERE user_id=? AND target_type=? AND target_id=?', [(int)$me['id'],'blog_post',$pid]);
    $tags = rmt_tags_for('blog_post', $pid);
    $isEd = rmt_is_editorial($p);
    $ld = ['@context'=>'https://schema.org','@type'=>'Article','headline'=>$p['title'],
           'description'=>$p['summary'],'datePublished'=>$p['created_at'],
           'url'=>url('blog/'.$p['slug'])];
    if ($isEd) $ld['author'] = ['@type'=>'Organization','name'=>rmt_editorial_name()];
    $askDests = all_dests();
    view('blog_show', compact('p','me','comments','likeCount','saveCount','liked','saved','tags','askDests'), [
        'title' => $p['title'].' | RuinMyTrip',
        'description' => $p['summary'],
        'og_image' => $p['cover_url'] ? abs_url($p['cover_url']) : url('assets/img/og-default.svg'),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Blog','url'=>url('blog')],['name'=>$p['title'],'url'=>url('blog/'.$p['slug'])]],
        'jsonld' => jsonld($ld),
    ]);
}

/** @return array{ok:bool, errors:string[], data:array<string,mixed>} */
function rmt_blog_validate(array $in): array {
    $errors = [];
    $title = trim((string) ($in['title'] ?? ''));
    $summary = trim((string) ($in['summary'] ?? ''));
    $body = (string) ($in['body'] ?? '');
    $category = (string) ($in['category'] ?? '');
    $cover = trim((string) ($in['cover_url'] ?? ''));

    if (mb_strlen($title) < 5) $errors[] = 'Give your post a title (5+ characters).';
    if (mb_strlen($title) > 140) $errors[] = 'That title is too long.';
    if (mb_strlen($summary) < 10) $errors[] = 'Add a one-line summary (10+ characters).';
    if (mb_strlen($summary) > 300) $errors[] = 'That summary is too long (300 characters max).';
    if (strlen($body) < 200) $errors[] = 'A blog post needs real substance -- write at least 200 characters.';
    if (mb_strlen($body) > 20000) $errors[] = 'That post is too long.';
    if (!in_array($category, RMT_BLOG_CATEGORIES, true)) $errors[] = 'Choose a category.';
    if ($cover !== '' && (!filter_var($cover, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $cover))) {
        $errors[] = 'Cover photo URL must be a full https:// web address.';
    }

    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'title' => $title, 'summary' => $summary, 'body' => $body,
        'category' => $category, 'cover_url' => $cover,
    ]];
}

/** Only the author may edit or delete a post. */
function rmt_blog_can_edit(array $p, ?array $user): bool {
    return $user !== null && (int) $p['user_id'] === (int) $user['id'];
}

/** A unique slug, appending -2/-3/... on collision. Blog posts, like guides, look up by slug alone. */
function rmt_blog_unique_slug(string $title, int $excludeId = 0): string {
    $base = slugify($title) ?: 'post';
    $slug = $base; $n = 1;
    while (true) {
        $row = q_one('SELECT id FROM blog_posts WHERE slug=?', [$slug]);
        if (!$row || (int)$row['id'] === $excludeId) return $slug;
        $n++; $slug = $base.'-'.$n;
    }
}

function blog_new_form(array $a): void {
    require_login();
    view('blog_new', ['errors'=>[]], ['title'=>'Write a blog post — RuinMyTrip','description'=>'Share a travel story, tip, or safety note with the RuinMyTrip community.']);
}

function blog_create(array $a): void {
    require_verified_email(); csrf_check(); $me = current_user();
    if (!rmt_submit_ok('blog_new', input('_submit'))) {
        flash('That post was already submitted.'); redirect('/blog'); return;
    }
    if (!rmt_rate_ok('blog_create', (string)$me['id'], 10, 3600)) {
        view('blog_new', ['errors'=>['You are posting very fast. Try again later.']],
             ['title'=>'Write a blog post — RuinMyTrip']); return;
    }
    $v = rmt_blog_validate($_POST);
    if (!$v['ok']) {
        view('blog_new', ['errors'=>$v['errors']], ['title'=>'Write a blog post — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $slug = rmt_blog_unique_slug($d['title']);
    q_run("INSERT INTO blog_posts (user_id,slug,title,summary,body,cover_url,category,status,created_at)
           VALUES (?,?,?,?,?,?,?,'published',?)",
        [(int)$me['id'], $slug, $d['title'], $d['summary'], $d['body'], $d['cover_url'], $d['category'], date('Y-m-d H:i:s')]);
    $pid = (int) q_one('SELECT id FROM blog_posts WHERE slug=?', [$slug])['id'];
    rmt_sync_tags('blog_post', $pid, $d['title'], $d['summary'], $d['body']);
    rmt_notify_mentions('blog_post', $pid, (int)$me['id'], [], $d['title'], $d['summary'], $d['body']);
    flash('Post published.');
    redirect('/blog/'.$slug);
}

function blog_edit_form(array $a): void {
    require_login();
    $p = q_one('SELECT * FROM blog_posts WHERE id=?', [(int)$a['id']]);
    if (!$p) not_found();
    if (!rmt_blog_can_edit($p, current_user())) { forbidden('That is not your post.'); }
    view('blog_edit', ['p'=>$p, 'errors'=>[]], ['title'=>'Edit post — RuinMyTrip']);
}

function blog_edit_submit(array $a): void {
    require_login(); csrf_check();
    $p = q_one('SELECT * FROM blog_posts WHERE id=?', [(int)$a['id']]);
    if (!$p) not_found();
    if (!rmt_blog_can_edit($p, current_user())) { forbidden('That is not your post.'); }

    $v = rmt_blog_validate($_POST);
    if (!$v['ok']) {
        view('blog_edit', ['p'=>array_merge($p, $_POST), 'errors'=>$v['errors']],
             ['title'=>'Edit post — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $slug = rmt_blog_unique_slug($d['title'], (int)$p['id']);
    db()->prepare("UPDATE blog_posts SET title=?, slug=?, summary=?, body=?, cover_url=?, category=?, updated_at=? WHERE id=?")
        ->execute([$d['title'], $slug, $d['summary'], $d['body'], $d['cover_url'], $d['category'],
                   date('Y-m-d H:i:s'), (int)$p['id']]);
    rmt_sync_tags('blog_post', (int)$p['id'], $d['title'], $d['summary'], $d['body']);
    rmt_notify_mentions('blog_post', (int)$p['id'], (int)current_user()['id'], [], $d['title'], $d['summary'], $d['body']);
    flash('Post updated.');
    redirect('/blog/'.$slug);
}

/** POST /blog/{id}/delete — soft delete. Rows are never destroyed. */
function blog_delete(array $a): void {
    require_login(); csrf_check();
    $p = q_one('SELECT * FROM blog_posts WHERE id=?', [(int)$a['id']]);
    if (!$p) not_found();
    if (!rmt_blog_can_edit($p, current_user())) { forbidden('That is not your post.'); }
    db()->prepare("UPDATE blog_posts SET status='removed', updated_at=? WHERE id=?")
        ->execute([date('Y-m-d H:i:s'), (int)$p['id']]);
    flash('Post deleted.');
    redirect('/u/'.current_user()['username']);
}

/* ---------- collections ---------- */

function collections_index(array $a): void {
    $collections = q_all("SELECT c.*,
                            (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id=c.id) item_count
                           FROM collections c WHERE c.status='published' ORDER BY c.id DESC LIMIT 50");
    authors_fill($collections);

    // Your own lists, drafts included. Without this a list you had not published yet was reachable
    // only from a URL you no longer had -- you could make one, leave, and never find it again.
    $me = current_user();
    $mine = $me ? q_all("SELECT c.*,
                           (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id=c.id) item_count
                          FROM collections c
                         WHERE c.user_id = ? AND c.status <> 'deleted'
                         ORDER BY c.updated_at DESC, c.id DESC LIMIT 24", [(int) $me['id']]) : [];

    view('collections_index', ['collections'=>$collections, 'mine'=>$mine], [
        'title' => 'Collections — RuinMyTrip',
        'description' => 'Traveler-curated lists of destinations, with the honest reasoning behind each pick.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Collections','url'=>url('collections')]],
    ]);
}

function collection_show(array $a): void {
    $c = q_one("SELECT * FROM collections WHERE slug=?", [$a['slug']]);
    if (!$c || $c['status']!=='published') not_found();
    $c['author'] = author((int)$c['user_id']);
    $me = current_user();
    $cid = (int) $c['id'];
    // LEFT JOINs, and a row is one kind or the other: the inner join here silently dropped every
    // place from a list the moment lists could hold places.
    $items = q_all("SELECT ci.*, d.slug dest_slug, d.name dest_name, d.country dest_country, d.hero_url dest_hero,
                           pl.slug place_slug, pl.name place_name, pl.type place_type, pl.neighborhood place_area,
                           pd.slug place_dest_slug, pd.name place_dest_name
                    FROM collection_items ci
                    LEFT JOIN destinations d ON d.id = ci.destination_id
                    LEFT JOIN places pl ON pl.id = ci.place_id AND pl.status = 'active'
                    LEFT JOIN destinations pd ON pd.id = pl.destination_id
                    WHERE ci.collection_id = ?
                    ORDER BY ci.pinned DESC, ci.sort, ci.id", [$cid]);
    // Who put each thing here. On a personal list this is always the owner and the view says
    // nothing; in a community it is the difference between a room and a shelf.
    $contributors = authors_by_ids(array_column($items, 'added_by'));
    foreach ($items as &$__it) $__it['contributor'] = $contributors[(int) $__it['added_by']] ?? null;
    unset($__it);
    // A place that was removed from the site leaves an item pointing at nothing. Dropping it on
    // read is better than rendering a blank card, and better than deleting somebody's list item
    // behind their back.
    $items = array_values(array_filter($items, static fn(array $i) =>
        ($i['destination_id'] !== null && $i['dest_slug'] !== null) ||
        ($i['place_id'] !== null && $i['place_slug'] !== null)));
    $comments = q_all("SELECT cm.*, u.username, p2.avatar_url FROM comments cm JOIN users u ON u.id=cm.user_id
                       LEFT JOIN profiles p2 ON p2.user_id=u.id
                       WHERE cm.target_type='collection' AND cm.target_id=? AND cm.status='published' ORDER BY cm.id", [$cid]);
    $likeCount = (int) q_one("SELECT COUNT(*) n FROM likes WHERE target_type='collection' AND target_id=?", [$cid])['n'];
    $saveCount = (int) q_one("SELECT COUNT(*) n FROM saves WHERE target_type='collection' AND target_id=?", [$cid])['n'];
    $liked = $me && q_one('SELECT 1 FROM likes WHERE user_id=? AND target_type=? AND target_id=?', [(int)$me['id'],'collection',$cid]);
    $saved = $me && q_one('SELECT 1 FROM saves WHERE user_id=? AND target_type=? AND target_id=?', [(int)$me['id'],'collection',$cid]);
    $canEdit = rmt_collection_can_edit($c, $me);
    $tags = rmt_tags_for('collection', $cid);
    $isCommunity  = rmt_is_community($c);
    $members      = $isCommunity ? rmt_community_members($cid) : [];
    $memberCount  = $isCommunity ? count($members) : 1;
    $myRole       = $isCommunity ? rmt_community_role($cid, $me ? (int) $me['id'] : null) : null;
    $canAdd       = rmt_community_can_add($c, $me);
    // The token rides in the URL so an invite link lands on the community itself, showing what is
    // inside before asking anyone to commit to joining it.
    $inviteToken  = trim((string) ($_GET['invite'] ?? '')) ?: null;
    $joinState    = $isCommunity ? rmt_community_join_state($c, $me, $inviteToken) : 'not_a_community';
    $invite       = ($isCommunity && $canEdit) ? rmt_community_invite($cid) : null;
    // The room's conversation. A community whose only content is a list of places is a shelf.
    $talk         = $isCommunity ? rmt_posts_recent(15, null, $cid) : [];
    view('collection_show', compact('c','me','items','comments','likeCount','saveCount','liked','saved','canEdit','tags',
                                    'isCommunity','members','memberCount','myRole','canAdd','joinState','invite','inviteToken','talk'), [
        'robots' => rmt_robots_for(rmt_indexable('list', $c + ['item_count' => count($items),
                                                               'member_count' => $memberCount])),
        'title' => $c['title'].' — RuinMyTrip Collections',
        'description' => $c['summary'] ?: ('A curated destination list on RuinMyTrip: '.$c['title']),
        'og_image' => $isCommunity ? rmt_card_url('c', (string) $c['slug'])
                                   : ($items ? abs_url($items[0]['dest_hero']) : url('assets/img/og-default.svg')),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Collections','url'=>url('collections')],['name'=>$c['title'],'url'=>url('c/'.$c['slug'])]],
        'jsonld' => jsonld(['@context'=>'https://schema.org','@type'=>'ItemList','name'=>$c['title'],
            'description'=>$c['summary'],
            'itemListElement'=>array_map(fn($it,$i)=>['@type'=>'ListItem','position'=>$i+1,'name'=>$it['dest_name'],'url'=>url('d/'.$it['dest_slug'])], $items, array_keys($items))]),
    ]);
}

/** @return array{ok:bool, errors:string[], data:array<string,mixed>} */
function rmt_collection_validate(array $in): array {
    $errors = [];
    $title = trim((string) ($in['title'] ?? ''));
    $summary = trim((string) ($in['summary'] ?? ''));
    if (mb_strlen($title) < 5) $errors[] = 'Give your collection a title (5+ characters).';
    if (mb_strlen($title) > 140) $errors[] = 'That title is too long.';
    if (mb_strlen($summary) > 500) $errors[] = 'That summary is too long (500 characters max).';
    return ['ok' => !$errors, 'errors' => $errors, 'data' => ['title' => $title, 'summary' => $summary]];
}

/** Only the author may edit or delete a collection. */
function rmt_collection_can_edit(array $c, ?array $user): bool {
    return $user !== null && (int) $c['user_id'] === (int) $user['id'];
}

/** A unique slug, appending -2/-3/... on collision. */
function rmt_collection_unique_slug(string $title, int $excludeId = 0): string {
    $base = slugify($title) ?: 'collection';
    $slug = $base; $n = 1;
    while (true) {
        $row = q_one('SELECT id FROM collections WHERE slug=?', [$slug]);
        if (!$row || (int)$row['id'] === $excludeId) return $slug;
        $n++; $slug = $base.'-'.$n;
    }
}

function collection_new_form(array $a): void {
    require_login();
    view('collection_new', ['errors'=>[]], ['title'=>'Start a collection — RuinMyTrip','description'=>'Curate a list of destinations for other travelers.']);
}

function collection_create(array $a): void {
    require_verified_email(); csrf_check(); $me = current_user();
    if (!rmt_submit_ok('collection_new', input('_submit'))) {
        flash('That list was already created.'); redirect('/collections'); return;
    }
    if (!rmt_rate_ok('collection_create', (string)$me['id'], 10, 3600)) {
        view('collection_new', ['errors'=>['You are creating collections very fast. Try again later.']],
             ['title'=>'Start a collection — RuinMyTrip']); return;
    }
    $v = rmt_collection_validate($_POST);
    if (!$v['ok']) {
        view('collection_new', ['errors'=>$v['errors']], ['title'=>'Start a collection — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $slug = rmt_collection_unique_slug($d['title']);
    q_run("INSERT INTO collections (user_id,slug,title,summary,status,created_at) VALUES (?,?,?,?,'published',?)",
        [(int)$me['id'], $slug, $d['title'], $d['summary'], date('Y-m-d H:i:s')]);
    $cid = (int) q_one('SELECT id FROM collections WHERE slug=?', [$slug])['id'];
    rmt_sync_tags('collection', $cid, $d['title'], $d['summary']);
    flash('List created. Add the places and cities that belong on it.');
    redirect('/collection/'.$cid.'/edit');
}

function collection_edit_form(array $a): void {
    require_login();
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int)$a['id']]);
    if (!$c) not_found();
    if (!rmt_collection_can_edit($c, current_user())) { forbidden('That is not your collection.'); }
    // LEFT JOINs for the same reason as the public page: an inner join on destinations hides every
    // place the moment a list can hold one, and the owner would be looking at an editor that had
    // quietly lost half their list.
    $items = q_all("SELECT ci.*, d.name dest_name, d.country dest_country,
                           pl.name place_name, pl.slug place_slug, pl.type place_type,
                           pd.name place_dest_name
                      FROM collection_items ci
                      LEFT JOIN destinations d ON d.id = ci.destination_id
                      LEFT JOIN places pl ON pl.id = ci.place_id
                      LEFT JOIN destinations pd ON pd.id = pl.destination_id
                     WHERE ci.collection_id = ? ORDER BY ci.sort, ci.id", [(int)$c['id']]);
    $usedIds = array_values(array_filter(array_column($items, 'destination_id'), static fn($v) => $v !== null));
    $available = $usedIds
        ? q_all('SELECT id,name,country FROM destinations WHERE id NOT IN ('.implode(',', array_fill(0, count($usedIds), '?')).') ORDER BY name', $usedIds)
        : all_dests();
    view('collection_edit', ['c'=>$c, 'items'=>$items, 'available'=>$available, 'errors'=>[]], ['title'=>'Edit collection — RuinMyTrip']);
}

function collection_edit_submit(array $a): void {
    require_login(); csrf_check();
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int)$a['id']]);
    if (!$c) not_found();
    if (!rmt_collection_can_edit($c, current_user())) { forbidden('That is not your collection.'); }

    $v = rmt_collection_validate($_POST);
    if (!$v['ok']) {
        $items = q_all('SELECT ci.*, d.name dest_name, d.country dest_country FROM collection_items ci
                        JOIN destinations d ON d.id=ci.destination_id WHERE ci.collection_id=? ORDER BY ci.sort, ci.id', [(int)$c['id']]);
        view('collection_edit', ['c'=>array_merge($c, $_POST), 'items'=>$items, 'available'=>[], 'errors'=>$v['errors']],
             ['title'=>'Edit collection — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $slug = rmt_collection_unique_slug($d['title'], (int)$c['id']);
    // The two decisions that turn a list into a community, and they are separate on purpose.
    $policy = (string) input('join_policy');
    if (!in_array($policy, RMT_JOIN_POLICIES, true)) $policy = (string) $c['join_policy'];
    $canAdd = input('members_can_add') ? 1 : 0;
    db()->prepare("UPDATE collections SET title=?, slug=?, summary=?, join_policy=?, members_can_add=?, updated_at=? WHERE id=?")
        ->execute([$d['title'], $slug, $d['summary'], $policy, $canAdd, date('Y-m-d H:i:s'), (int)$c['id']]);
    rmt_sync_tags('collection', (int)$c['id'], $d['title'], $d['summary']);
    flash('Collection updated.');
    redirect('/collection/'.(int)$c['id'].'/edit');
}

/** POST /collection/{id}/delete — soft delete. Items rows are left in place; the show page 404s
 *  once status is no longer 'published', same as every other content type here. */
function collection_delete(array $a): void {
    require_login(); csrf_check();
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int)$a['id']]);
    if (!$c) not_found();
    if (!rmt_collection_can_edit($c, current_user())) { forbidden('That is not your collection.'); }
    db()->prepare("UPDATE collections SET status='removed', updated_at=? WHERE id=?")
        ->execute([date('Y-m-d H:i:s'), (int)$c['id']]);
    flash('Collection deleted.');
    redirect('/u/'.current_user()['username']);
}

/** POST /collection/{id}/items — owner-only, adds one destination (+ optional note) to the list. */
function collection_item_add(array $a): void {
    require_login(); csrf_check();
    // The list is named in the URL when editing a list, and chosen from a select when adding from
    // a place page. Same permission check either way -- which is the reason this is one function
    // and not two.
    $listId = (int) ($a['id'] ?? 0) ?: (int) input('list_id');
    $c = q_one('SELECT * FROM collections WHERE id=?', [$listId]);
    if (!$c) not_found();
    // Was owner-only, and stays owner-only for a personal list. A community widens it to members
    // the founder has handed the pen to, which is the whole difference between a list and a place
    // people contribute to.
    if (!rmt_community_can_add($c, current_user())) { forbidden('You cannot add to that collection.'); }

    // A list item is a city or a venue, exactly one. The database says the same thing with a
    // check constraint; this is the readable half of that rule.
    $did = (int) input('destination_id');
    $pid = (int) input('place_id');
    $note = trim((string) input('note'));
    $back = rmt_safe_return_path((string) input('return')) ?: '/collection/'.(int)$c['id'].'/edit';
    if (mb_strlen($note) > 500) {
        flash('That note is too long (500 characters max).'); redirect($back); return;
    }
    if (($did > 0) === ($pid > 0)) redirect($back);          // neither, or both
    if ($did > 0 && !dest_by_id($did)) redirect($back);
    if ($pid > 0 && !q_one("SELECT 1 FROM places WHERE id = ? AND status = 'active'", [$pid])) redirect($back);

    $count = (int) (q_one('SELECT COUNT(*) n FROM collection_items WHERE collection_id=?', [(int)$c['id']])['n'] ?? 0);
    if ($count >= 50) {
        flash('A list can hold at most 50 items.'); redirect($back); return;
    }
    $nextSort = (int) (q_one('SELECT COALESCE(MAX(sort),-1) n FROM collection_items WHERE collection_id=?', [(int)$c['id']])['n'] ?? -1) + 1;
    try {
        q_run('INSERT INTO collection_items (collection_id,destination_id,place_id,note,sort,added_by) VALUES (?,?,?,?,?,?)',
            [(int)$c['id'], $did > 0 ? $did : null, $pid > 0 ? $pid : null, $note !== '' ? $note : null, $nextSort,
             (int) current_user()['id']]);
        flash($pid > 0 ? 'Added to your list.' : 'Added.');
    } catch (\PDOException $e) {
        // The unique index means it is already in the list, which is not an error worth a 500.
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        flash('That is already on the list.');
    }
    redirect(rmt_community_can_manage($c, current_user()) ? '/collection/'.(int)$c['id'].'/edit' : '/c/'.$c['slug']);
}

/** POST /collection/{id}/items/{item_id}/delete — owner-only. */
function collection_item_remove(array $a): void {
    require_login(); csrf_check();
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int)$a['id']]);
    if (!$c) not_found();
    $me = current_user();
    $item = q_one('SELECT * FROM collection_items WHERE id=? AND collection_id=?', [(int)$a['item_id'], (int)$c['id']]);
    if (!$item) not_found();
    // A member may retract what they contributed. They may not edit the room.
    if (!rmt_community_can_remove_item($c, $me, $item)) { forbidden('That is not yours to remove.'); }
    db()->prepare('DELETE FROM collection_items WHERE id=? AND collection_id=?')->execute([(int)$a['item_id'], (int)$c['id']]);
    redirect(rmt_community_can_manage($c, $me) ? '/collection/'.(int)$c['id'].'/edit' : '/c/'.$c['slug']);
}

function meetups_index(array $a): void {
    $meetups = q_all("SELECT m.*, d.name dest_name, d.slug dest_slug,
                      (SELECT COUNT(*) FROM meetup_rsvps r WHERE r.meetup_id=m.id AND r.status='going') going
                      FROM meetups m LEFT JOIN destinations d ON d.id=m.destination_id
                      WHERE m.status='published' ORDER BY m.date_start");
    $hosts = authors_by_ids(array_column($meetups, 'host_id'));
    foreach ($meetups as &$m) $m['host'] = $hosts[(int)$m['host_id']] ?? null; unset($m);
    $me = current_user();
    $canHost = can_host_meetups($me);
    view('meetups_index', compact('meetups', 'me', 'canHost'), [
        'title'=>'Public travel meetups — RuinMyTrip',
        'description'=>'Optional, public, safety-first travel meetups. Meet fellow travelers in a destination — never dating, never precise location sharing.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Meetups','url'=>url('meetups')]],
    ]);
}

function meetup_show(array $a): void {
    $m = q_one("SELECT m.*, d.name dest_name, d.slug dest_slug FROM meetups m
                LEFT JOIN destinations d ON d.id=m.destination_id WHERE m.id=?", [(int)$a['id']]);
    // A cancelled meetup still renders. People RSVPed to it and are holding the link; 404ing them
    // tells them nothing, and "it vanished" is the worst version of "it is off".
    if (!$m || !in_array($m['status'], RMT_MEETUP_STATUSES, true)) not_found();
    $m['host'] = author((int)$m['host_id']);
    $rsvps = q_all("SELECT r.*, u.username, p.avatar_url, p.display_name FROM meetup_rsvps r
                    JOIN users u ON u.id=r.user_id LEFT JOIN profiles p ON p.user_id=u.id
                    WHERE r.meetup_id=? AND r.status='going'", [(int)$m['id']]);
    $me = current_user();
    $mine = $me ? (bool) q_one('SELECT 1 FROM meetup_rsvps WHERE meetup_id=? AND user_id=?', [(int)$m['id'],(int)$me['id']]) : false;
    $isHost = rmt_meetup_can_edit($m, $me);
    $going = count($rsvps);
    $isFull = rmt_meetup_is_full($m, $going);
    $isPast = rmt_meetup_is_past($m);
    // A stranger deciding whether to turn up should be able to see who is asking without leaving
    // the page. Every number here is a live count of things the host actually posted -- there is
    // no self-declared reputation on this site and there is not going to be one.
    $hostStats = rmt_profile_stats((int) $m['host_id']);
    $hostBadges = rmt_user_badges((int) $m['host_id']);
    $hostSince = q_one('SELECT created_at FROM users WHERE id=?', [(int)$m['host_id']])['created_at'] ?? null;
    view('meetup_show', compact('m','rsvps','me','mine','isHost','going','isFull','isPast','hostStats','hostBadges','hostSince'), [
        'title'=>$m['title'].' — RuinMyTrip meetup',
        'description'=>mb_substr((string)$m['description'],0,150),
        'og_image'=>rmt_card_url('meetup', (string) (int) $m['id']),
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Meetups','url'=>url('meetups')],['name'=>$m['title'],'url'=>url('meetup/'.$m['id'])]],
    ]);
}

/**
 * GET /meetup/new -- the form. 18+ only, the same gate RSVP already applies, checked here too so
 * somebody under 18 is told before writing the whole thing rather than after submitting it.
 */
function meetup_new_form(array $a): void {
    require_login();
    if (!can_host_meetups(current_user())) {
        flash('You must be 18+ to host a meetup.');
        redirect('/meetups');
    }
    /* Arriving from a trip match, where the city and the days are already known. Making somebody
       retype what the page they came from just told them is how a good idea dies between screens. */
    $pre = [];
    $dSlug = trim((string) input('d'));
    if ($dSlug !== '') {
        $d = q_one('SELECT id FROM destinations WHERE slug=?', [$dSlug]);
        if ($d) $pre['destination_id'] = (int) $d['id'];
    }
    $start = trim((string) input('start'));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $pre['date_start'] = $start . ' 18:00:00';
    view('meetup_new', ['dests' => all_dests(), 'errors' => [], 'm' => $pre], [
        'title' => 'Host a meetup — RuinMyTrip',
        'description' => 'Host a public, optional, safety-first travel meetup in a destination.',
    ]);
}

function meetup_create(array $a): void {
    require_verified_email(); csrf_check(); $me = current_user();
    if (!can_host_meetups($me)) { flash('You must be 18+ to host a meetup.'); redirect('/meetups'); }

    $opts = ['title' => 'Host a meetup — RuinMyTrip'];
    if (!rmt_submit_ok('meetup_new', input('_submit'))) {
        flash('That meetup was already created.'); redirect('/meetups'); return;
    }
    if (!rmt_rate_ok('meetup_create', (string) $me['id'], 5, 3600)) {
        view('meetup_new', ['dests'=>all_dests(), 'errors'=>['You are creating meetups very fast. Try again later.'], 'm'=>$_POST], $opts);
        return;
    }
    $v = rmt_meetup_validate($_POST);
    if (!$v['ok']) {
        view('meetup_new', ['dests'=>all_dests(), 'errors'=>$v['errors'], 'm'=>$_POST], $opts);
        return;
    }
    $d = $v['data'];
    $id = (int) q_run("INSERT INTO meetups (host_id,destination_id,title,description,date_start,date_end,
                                            visibility,capacity,safety_ack,status,created_at)
                       VALUES (?,?,?,?,?,?, 'public', ?,?, 'published', ?)",
        [(int)$me['id'], $d['destination_id'], $d['title'], $d['description'], $d['date_start'],
         $d['date_end'], $d['capacity'], $d['safety_ack'], date('Y-m-d H:i:s')]);

    // The host is going. Leaving them out means a brand new meetup reads "0 going" while the
    // person who organised it is standing there, and it makes capacity off by one.
    try {
        db()->prepare("INSERT INTO meetup_rsvps (meetup_id,user_id,status) VALUES (?,?, 'going')")
            ->execute([$id, (int)$me['id']]);
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
    }
    // The people most likely to come are the ones who already said they will be in that city on
    // that day. The site knew who they were and, until this, never told them.
    rmt_meetup_notify_travelers($id, (int) $me['id'], (int) $d['destination_id'], (string) $d['date_start']);
    flash('Meetup published. Travelers with dates in town have been told.');
    redirect('/meetup/' . $id);
}

function meetup_edit_form(array $a): void {
    require_login();
    $m = q_one('SELECT * FROM meetups WHERE id=?', [(int)$a['id']]);
    if (!$m) not_found();
    if (!rmt_meetup_can_edit($m, current_user())) forbidden('That is not your meetup.');
    view('meetup_edit', ['m' => $m, 'dests' => all_dests(), 'errors' => []], ['title' => 'Edit meetup — RuinMyTrip']);
}

function meetup_edit_submit(array $a): void {
    require_login(); csrf_check();
    $m = q_one('SELECT * FROM meetups WHERE id=?', [(int)$a['id']]);
    if (!$m) not_found();
    if (!rmt_meetup_can_edit($m, current_user())) forbidden('That is not your meetup.');

    // The stored start is passed through so a meetup that has already happened can still be
    // corrected. Only a CHANGED date has to be in the future.
    $v = rmt_meetup_validate($_POST, (string) $m['date_start']);
    if (!$v['ok']) {
        view('meetup_edit', ['m' => array_merge($m, $_POST), 'dests' => all_dests(), 'errors' => $v['errors']],
             ['title' => 'Edit meetup — RuinMyTrip']);
        return;
    }
    $d = $v['data'];
    // Capacity is not allowed to drop below the people already holding a place. Nobody gets
    // silently un-invited by a number, and there is no un-RSVP-someone-else action on this site.
    $going = rmt_meetup_going_count((int) $m['id']);
    if ($d['capacity'] > 0 && $d['capacity'] < $going) {
        view('meetup_edit', ['m' => array_merge($m, $_POST), 'dests' => all_dests(),
             'errors' => ["$going people have already RSVPed. Capacity cannot be lower than that."]],
             ['title' => 'Edit meetup — RuinMyTrip']);
        return;
    }
    db()->prepare('UPDATE meetups SET destination_id=?, title=?, description=?, date_start=?, date_end=?,
                                      capacity=?, updated_at=? WHERE id=?')
        ->execute([$d['destination_id'], $d['title'], $d['description'], $d['date_start'], $d['date_end'],
                   $d['capacity'], date('Y-m-d H:i:s'), (int)$m['id']]);
    // Only a move in TIME notifies. Fixing a typo in the title should not ping everybody who
    // RSVPed; moving the start by three hours has to, or they arrive to an empty corner.
    $told = 0;
    if (rmt_meetup_time_changed($m, $d)) {
        $told = rmt_meetup_notify(rmt_meetup_going_user_ids((int)$m['id'], (int)$m['host_id']),
                                  'meetup_changed', (int)$m['host_id'], (int)$m['id']);
    }
    flash($told > 0
        ? "Meetup updated. $told " . ($told === 1 ? 'person has' : 'people have') . ' been told the time changed.'
        : 'Meetup updated.');
    redirect('/meetup/' . (int)$m['id']);
}

/**
 * POST /meetup/{id}/cancel -- called off, not deleted.
 *
 * Guides and trips soft-delete to 'removed' and disappear. A meetup cannot: people have arranged
 * their day around it and are holding the link. It keeps its page, says plainly that it is
 * cancelled, and drops out of the index. That is the difference between withdrawing something you
 * wrote and calling off something other people are turning up to.
 */
function meetup_cancel(array $a): void {
    require_login(); csrf_check();
    $m = q_one('SELECT * FROM meetups WHERE id=?', [(int)$a['id']]);
    if (!$m) not_found();
    if (!rmt_meetup_can_edit($m, current_user())) forbidden('That is not your meetup.');
    db()->prepare("UPDATE meetups SET status='cancelled', updated_at=? WHERE id=?")
        ->execute([date('Y-m-d H:i:s'), (int)$m['id']]);
    // Told, not left to find out by turning up. This is the whole reason the page stays readable.
    $told = rmt_meetup_notify(rmt_meetup_going_user_ids((int)$m['id'], (int)$m['host_id']),
                              'meetup_cancelled', (int)$m['host_id'], (int)$m['id']);
    flash($told > 0
        ? "Meetup cancelled. $told " . ($told === 1 ? 'person has' : 'people have') . ' been notified.'
        : 'Meetup cancelled. Everyone who RSVPed can still see the page and that it is off.');
    redirect('/meetup/' . (int)$m['id']);
}

function going_index(array $a): void {
    $me = current_user();
    [$visSql, $visArgs] = rmt_going_visibility_sql('g', $me);
    $rows = q_all("SELECT g.*, d.name dest_name, d.slug dest_slug, u.username, p.avatar_url, p.display_name
                   FROM going g JOIN destinations d ON d.id=g.destination_id JOIN users u ON u.id=g.user_id
                   LEFT JOIN profiles p ON p.user_id=u.id
                   WHERE u.status='active' AND $visSql
                   ORDER BY g.date_from", $visArgs);
    $dests = all_dests();
    view('going_index', compact('rows','me','dests'), [
        'title'=>"Who's going — find travelers by destination & date | RuinMyTrip",
        'description'=>'Discover travelers heading to the same destination in your date range. Destination and date-range only — never precise location.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>"Who's going",'url'=>url('going')]],
    ]);
}

function going_save(array $a): void {
    require_verified_email();
    csrf_check();
    $me = current_user();
    if (!rmt_rate_ok('going', (string)$me['id'], 30, 3600)) {
        flash('You are posting plans very fast. Try again shortly.');
        redirect(rmt_return_to('/going'));
    }
    $v = rmt_going_validate($_POST);
    if (!$v['ok']) {
        flash($v['errors'][0]);
        redirect(rmt_return_to('/going'));
    }
    $prev = rmt_going_for_user_dest((int)$me['id'], (int)$v['data']['destination_id']);
    $gid = rmt_going_upsert((int)$me['id'], $v['data']);
    if ($v['data']['visibility'] === 'public' && (!$prev || $prev['visibility'] !== 'public')) {
        rmt_going_notify_followers((int)$me['id'], $gid, 'public');
    }
    // The people already holding dates in that city are the ones this plan is news to.
    rmt_match_notify((int)$me['id'], $gid, (int)$v['data']['destination_id'],
                     (string)$v['data']['date_from'], (string)$v['data']['date_to'], $v['data']['visibility']);
    flash('Saved. Destination and dates only — never a precise location.');
    $d = dest_by_id((int)$v['data']['destination_id']);
    redirect($d ? '/d/'.$d['slug'] : '/going');
}

function going_delete(array $a): void {
    require_login();
    csrf_check();
    $me = current_user();
    $did = (int) input('destination_id');
    if ($did > 0) rmt_going_delete((int)$me['id'], $did);
    flash('Plan removed.');
    $d = $did ? dest_by_id($did) : null;
    redirect($d ? '/d/'.$d['slug'] : '/going');
}

function travelers_index(array $a): void {
    $people = q_all("SELECT u.id, u.username, u.role, p.display_name, p.bio, p.home_city, p.avatar_url,
                        (SELECT COUNT(*) FROM reviews r WHERE r.user_id=u.id AND r.status='published') AS reviews,
                        (SELECT COUNT(*) FROM trips t WHERE t.user_id=u.id AND t.status='published') AS trips
                     FROM users u LEFT JOIN profiles p ON p.user_id=u.id
                     WHERE u.status='active' AND u.role <> ?
                     ORDER BY reviews DESC, trips DESC, u.id DESC LIMIT 80", [RMT_EDITORIAL_ROLE]);
    $me = current_user();
    // Who is here, for you. A directory sorted by review count answers a different question.
    $suggested = $me ? rmt_follow_suggestions((int) $me['id'], 8) : [];
    view('travelers_index', ['people'=>$people, 'me'=>$me, 'suggested'=>$suggested], [
        'title' => 'Travelers on RuinMyTrip',
        'description' => 'Real traveler profiles on RuinMyTrip. Follow people whose trips and reviews you trust.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Travelers','url'=>url('travelers')]],
    ]);
}

function welcome_form(array $a): void {
    require_login();
    $me = current_user();
    $dests = q_all('SELECT id, slug, name, country FROM destinations ORDER BY name');
    // Rooms that already have somebody in them. An empty one is a worse first experience than none.
    $communities = rmt_community_browse(6);
    // And people. A social site whose first screen asks only about places is still a directory.
    $suggested = rmt_follow_suggestions((int) $me['id'], 5);
    $saved = [];
    foreach (q_all("SELECT target_id FROM saves WHERE user_id=? AND target_type='destination'", [(int)$me['id']]) as $r) {
        $saved[(int)$r['target_id']] = true;
    }
    view('welcome', compact('dests','saved','me','communities','suggested'), [
        'title' => 'Start your traveler profile — RuinMyTrip',
        'description' => 'Pick places you want to visit and optionally share an upcoming trip. Destination and dates only.',
    ]);
}

function welcome_submit(array $a): void {
    require_login();
    csrf_check();
    $me = current_user();
    $uid = (int)$me['id'];
    $wants = array_slice(array_unique(array_map('intval', (array)($_POST['want'] ?? []))), 0, 12);
    $now = date('Y-m-d H:i:s');
    foreach ($wants as $did) {
        if ($did < 1 || !dest_by_id($did)) continue;
        $has = q_one("SELECT 1 FROM saves WHERE user_id=? AND target_type='destination' AND target_id=?", [$uid, $did]);
        if ($has) continue;
        try {
            db()->prepare("INSERT INTO saves (user_id,target_type,target_id,created_at) VALUES (?,'destination',?,?)")
               ->execute([$uid, $did, $now]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        }
    }
    if (trim((string)($_POST['date_from'] ?? '')) !== '' || trim((string)($_POST['date_to'] ?? '')) !== '') {
        if (!email_is_verified($me)) {
            flash('Confirm your email before sharing travel dates.');
            redirect('/verify-email');
        }
        $v = rmt_going_validate($_POST);
        if ($v['ok']) {
            $gid = rmt_going_upsert($uid, $v['data']);
            rmt_going_notify_followers($uid, $gid, $v['data']['visibility']);
        } else {
            flash($v['errors'][0]);
            redirect('/welcome');
        }
    }
    /* Joining rooms and saying something are the two actions that decide whether somebody comes
       back, and both were left for later. Both are optional here, and both fail quietly: a new
       member's first screen is not the place to bounce them to an error page. */
    $joined = 0;
    foreach (array_slice(array_unique(array_map('intval', (array) ($_POST['join'] ?? []))), 0, 6) as $cidJoin) {
        if ($cidJoin < 1) continue;
        $c = q_one("SELECT * FROM collections WHERE id=? AND status='published'", [$cidJoin]);
        if ($c && rmt_community_join($c, $me) === 'joined') $joined++;
    }

    $followed = 0;
    foreach (array_slice(array_unique(array_map('intval', (array) ($_POST['follow'] ?? []))), 0, 10) as $fid) {
        if ($fid < 1 || $fid === $uid) continue;
        if (rmt_is_blocked($uid, $fid)) continue;
        try {
            db()->prepare('INSERT INTO follows (follower_id, followee_id, created_at) VALUES (?,?,?)')
               ->execute([$uid, $fid, $now]);
            $followed++;
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        }
    }

    $said = false;
    $hello = trim((string) ($_POST['hello'] ?? ''));
    if ($hello !== '' && email_is_verified($me)) {
        $pv = rmt_post_validate(['body' => $hello], $me);
        if ($pv['ok']) {
            rmt_post_create($uid, $pv['data']);
            $said = true;
        } else {
            flash($pv['errors'][0]);
        }
    }

    // Land them where their own answers point: dates mean matches, words mean the conversation.
    if ($wants && (trim((string) ($_POST['date_from'] ?? '')) !== '')) {
        flash('Profile started. Here is who else will be there.');
        redirect('/matches');
    }
    if ($said || $joined || $followed) {
        flash($joined ? 'You are in. This is what people are saying.' : 'Posted. This is what people are saying.');
        redirect('/talk');
    }
    flash('Profile started. Add a trip or a review whenever you are ready.');
    redirect('/feed');
}

function leaderboard(array $a): void {
    $slug = trim((string)($_GET['d'] ?? ''));
    $dest = $slug !== '' ? q_one('SELECT * FROM destinations WHERE slug=?', [$slug]) : null;
    $rows = rmt_top_reviewers($dest ? (int)$dest['id'] : null, 25);
    foreach ($rows as &$row) $row['badges'] = rmt_user_badges((int)$row['id']);
    unset($row);
    $destinations = q_all('SELECT slug, name FROM destinations ORDER BY name');

    $breadcrumbs = [['name'=>'Home','url'=>url()]];
    if ($dest) $breadcrumbs[] = ['name'=>$dest['name'],'url'=>url('d/'.$dest['slug'])];
    $breadcrumbs[] = ['name'=>'Top Reviewers','url'=>url('leaderboard'.($dest?('?d='.$dest['slug']):''))];

    view('leaderboard', ['rows'=>$rows, 'dest'=>$dest, 'destinations'=>$destinations], [
        'title' => ($dest ? 'Top Reviewers in '.$dest['name'] : 'Top Reviewers').' — RuinMyTrip',
        'description' => $dest
            ? 'The most trusted travel reviewers writing about '.$dest['name'].' on RuinMyTrip, ranked by published reviews and community votes.'
            : 'The most trusted travel reviewers on RuinMyTrip, ranked by published reviews, community votes, and compliments.',
        'breadcrumbs' => $breadcrumbs,
    ]);
}

/**
 * Real full-text search (migration 015): Postgres tsvector+GIN in prod, SQLite FTS5 locally —
 * see app/controllers.php's search() and database/migrations/015_fulltext_search.*. Ranked by
 * relevance, not just "contains the substring", and now covers reviews and people too (the old
 * LIKE search never searched review content or usernames at all).
 */
/**
 * GET /suggest?q= — the autocomplete endpoint. JSON, public, rate limited.
 *
 * One endpoint for every entity type rather than four the browser has to fan out to and stitch
 * together: the ranking that decides what actually leads the list can only be done where all the
 * candidates are, and doing it in the client would mean shipping the scoring rules to it.
 */
function suggest_json(array $a): void {
    $q = trim((string) ($_GET['q'] ?? ''));

    // A public endpoint that runs several queries per call is worth a limit. Keyed by IP, generous
    // enough that a fast typist with a 180ms debounce never sees it.
    $who = (string) ($_SERVER['REMOTE_ADDR'] ?? 'anon');
    if (!rmt_rate_ok('suggest', $who, 240, 60)) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['groups' => [], 'count' => 0, 'throttled' => true]);
        return;
    }

    $res = rmt_search_suggest($q);

    // Logged only for a query long enough to mean something, and only the normalised text: no user,
    // no session, no address. A zero-result query is the most useful row in the table.
    if (mb_strlen($res['query']) >= RMT_SUGGEST_MIN_CHARS) {
        rmt_search_log($res['query'], (int) $res['count']);
    }

    header('Content-Type: application/json; charset=utf-8');
    // Private and short: suggestions are cheap to recompute and a shared cache would hand one
    // person's typing to the next.
    header('Cache-Control: private, max-age=30');
    echo json_encode(rmt_suggest_public($res), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * POST /suggest/click — which suggestion was taken, and where it sat.
 *
 * Fire and forget: the browser sends it with sendBeacon and navigates immediately. Analytics does
 * not get to stand between a person and the page they asked for.
 */
function suggest_click(array $a): void {
    // A beacon can carry a form field, so it carries the same CSRF token every other POST does.
    // Without it this is an open endpoint for writing rows into an analytics table from any page
    // on the internet, which is a small hole but a pointless one to leave.
    csrf_check();
    if (!rmt_rate_ok('suggest_click', (string) ($_SERVER['REMOTE_ADDR'] ?? 'anon'), 120, 60)) {
        http_response_code(204);
        return;
    }
    $q = rmt_search_norm((string) ($_POST['q'] ?? ''));
    $type = (string) ($_POST['type'] ?? '');
    $id = (string) ($_POST['id'] ?? '');
    $pos = (int) ($_POST['position'] ?? 0);
    if ($q !== '' && $type !== '') rmt_search_log_click($q, $type, $id, $pos);
    http_response_code(204);
}

function search(array $a): void {
    $qs = trim((string)($_GET['q'] ?? ''));
    $dests=$trips=$guides=$reviews=$people=$posts=$collections=$places=$talk=[];
    if ($qs !== '') {
        $driver = $GLOBALS['config']['db_driver'];
        if ($driver === 'pgsql') {
            $tsq = "plainto_tsquery('english', ?)";
            $dests = q_all("SELECT * FROM destinations WHERE search_vector @@ $tsq
                            ORDER BY ts_rank(search_vector, $tsq) DESC LIMIT 10", [$qs,$qs]);
            $trips = q_all("SELECT t.*,d.slug dest_slug FROM trips t LEFT JOIN destinations d ON d.id=t.destination_id
                            WHERE t.status='published' AND t.search_vector @@ $tsq
                            ORDER BY ts_rank(t.search_vector, $tsq) DESC LIMIT 10", [$qs,$qs]);
            $guides = q_all("SELECT * FROM guides WHERE status='published' AND search_vector @@ $tsq
                             ORDER BY ts_rank(search_vector, $tsq) DESC LIMIT 10", [$qs,$qs]);
            $reviews = q_all("SELECT r.*,d.slug dest_slug,d.name dest_name FROM reviews r LEFT JOIN destinations d ON d.id=r.destination_id
                              WHERE r.status='published' AND r.search_vector @@ $tsq
                              ORDER BY ts_rank(r.search_vector, $tsq) DESC LIMIT 10", [$qs,$qs]);
            $posts = q_all("SELECT * FROM blog_posts WHERE status='published' AND search_vector @@ $tsq
                            ORDER BY ts_rank(search_vector, $tsq) DESC LIMIT 10", [$qs,$qs]);
            $collections = q_all("SELECT * FROM collections WHERE status='published' AND search_vector @@ $tsq
                                  ORDER BY ts_rank(search_vector, $tsq) DESC LIMIT 10", [$qs,$qs]);
            $places = q_all("SELECT p.*, d.name dest_name, d.country dest_country FROM places p
                             JOIN destinations d ON d.id=p.destination_id
                             WHERE p.status='active' AND p.search_vector @@ $tsq
                             ORDER BY ts_rank(p.search_vector, $tsq) DESC LIMIT 10", [$qs,$qs]);
        } else {
            $dests = q_all("SELECT d.* FROM destinations d JOIN destinations_fts f ON f.rowid=d.id
                            WHERE destinations_fts MATCH ? ORDER BY rank LIMIT 10", [$qs]);
            $trips = q_all("SELECT t.*,dd.slug dest_slug FROM trips t JOIN trips_fts f ON f.rowid=t.id
                            LEFT JOIN destinations dd ON dd.id=t.destination_id
                            WHERE trips_fts MATCH ? AND t.status='published' ORDER BY rank LIMIT 10", [$qs]);
            $guides = q_all("SELECT g.* FROM guides g JOIN guides_fts f ON f.rowid=g.id
                             WHERE guides_fts MATCH ? AND g.status='published' ORDER BY rank LIMIT 10", [$qs]);
            $reviews = q_all("SELECT r.*,dd.slug dest_slug,dd.name dest_name FROM reviews r JOIN reviews_fts f ON f.rowid=r.id
                              LEFT JOIN destinations dd ON dd.id=r.destination_id
                              WHERE reviews_fts MATCH ? AND r.status='published' ORDER BY rank LIMIT 10", [$qs]);
            $posts = q_all("SELECT p.* FROM blog_posts p JOIN blog_posts_fts f ON f.rowid=p.id
                            WHERE blog_posts_fts MATCH ? AND p.status='published' ORDER BY rank LIMIT 10", [$qs]);
            $collections = q_all("SELECT c.* FROM collections c JOIN collections_fts f ON f.rowid=c.id
                                  WHERE collections_fts MATCH ? AND c.status='published' ORDER BY rank LIMIT 10", [$qs]);
            $places = q_all("SELECT p.*, dd.name dest_name, dd.country dest_country FROM places p
                             JOIN places_fts f ON f.rowid=p.id
                             JOIN destinations dd ON dd.id=p.destination_id
                             WHERE places_fts MATCH ? AND p.status='active' ORDER BY rank LIMIT 10", [$qs]);
        }
        // People: usernames/display names are short strings where substring matching is what
        // users actually expect ("mar" finding "maya_wanders") — full-text stemming would miss
        // that, so this stays LIKE-based on purpose.
        $like = '%'.mb_strtolower($qs).'%';
        $people = q_all("SELECT u.id, u.username, p.display_name, p.avatar_url, p.home_city FROM users u
                         LEFT JOIN profiles p ON p.user_id=u.id
                         WHERE u.status='active' AND (LOWER(u.username) LIKE ? OR LOWER(p.display_name) LIKE ?)
                         LIMIT 10", [$like,$like]);
        /* Talk is searched with LIKE rather than the full-text index the long-form types use. A
           post is a few sentences somebody typed in a hurry: stemming buys little on that length,
           and a missing FTS row would silently hide a whole content type from search. */
        $talk = q_all("SELECT p.id, p.body, p.created_at, u.username, d.name dest_name
                         FROM posts p JOIN users u ON u.id=p.user_id
                    LEFT JOIN destinations d ON d.id=p.destination_id
                        WHERE p.status='published' AND u.status='active' AND LOWER(p.body) LIKE ?
                     ORDER BY p.created_at DESC LIMIT 10", [$like]);
    }
    // A search results page is a view of the index we already have, in somebody's words.
    view('search', compact('qs','dests','places','trips','guides','reviews','people','posts','collections','talk'), [
        'title'=>($qs!==''?('Search: '.$qs.' — '):'Search — ').'RuinMyTrip',
        'description'=>'Search destinations, places, trips, reviews, guides, collections, blog posts, and travelers across RuinMyTrip.',
        // Never a page in the index. A results page is a view of content we already publish, in
        // somebody else's words, and one per query is an infinite set of near-duplicates.
        'robots' => rmt_robots_for(rmt_indexable('search')),
    ]);
}

/** POST /push/subscribe — a device registers for push. Form-encoded, CSRF like every other write. */
function push_subscribe(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    header('Content-Type: application/json');
    if (!rmt_push_enabled()) { echo json_encode(['ok' => false, 'error' => 'push_disabled']); exit; }
    $ok = rmt_push_subscribe((int) $me['id'], input('endpoint'), input('p256dh'), input('auth'),
                             (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    echo json_encode(['ok' => $ok]); exit;
}

/** POST /push/unsubscribe */
function push_unsubscribe(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    rmt_push_unsubscribe(input('endpoint'), (int) $me['id']);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]); exit;
}

/** GET /cron/push?key= — sweep anything the request-time flush did not reach (GET-created rows, retries). */
function cron_push(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = (string) input('key');
    if ($key === '' || $given === '' || !hash_equals($key, $given)) not_found();
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo 'sent=' . rmt_push_flush(200) . "\n";
    exit;
}

/** GET /invite — a member's personal link, who it brought, and the words to send with it. */
function invite_page(array $a): void {
    require_login(); $me = current_user();
    view('invite', [
        'me' => $me, 'link' => rmt_invite_link($me), 'message' => rmt_invite_message($me),
        'count' => rmt_invite_count((int) $me['id']), 'recent' => rmt_invite_recent((int) $me['id']),
    ], ['title' => 'Invite a traveler — RuinMyTrip', 'description' => 'Bring somebody who has a story about a trip that went sideways.',
        'robots' => 'noindex, follow']);
}

function notifications(array $a): void {
    require_login(); $me = current_user();
    $items = q_all("SELECT n.*, u.username actor FROM notifications n LEFT JOIN users u ON u.id=n.actor_id
                    WHERE n.user_id=? ORDER BY n.id DESC LIMIT 50", [(int)$me['id']]);
    db()->prepare("UPDATE notifications SET read_at=? WHERE user_id=? AND read_at IS NULL")->execute([date('Y-m-d H:i:s'),(int)$me['id']]);
    view('notifications', compact('items','me'), ['title'=>'Notifications — RuinMyTrip','description'=>'Your RuinMyTrip activity.']);
}

/**
 * Unsubscribe from the weekly digest email. Token-based (rmt_unsubscribe_verify), not
 * login-gated -- a signed-out recipient clicking a link in their inbox is exactly the case
 * this exists for.
 */
function unsubscribe_action(array $a): void {
    $uid = (int) ($_GET['u'] ?? 0);
    $token = (string) ($_GET['t'] ?? '');
    $ok = $uid > 0 && rmt_unsubscribe_verify($uid, $token);
    if ($ok) {
        db()->prepare('UPDATE profiles SET digest_opt_out=1 WHERE user_id=?')->execute([$uid]);
    }
    view('unsubscribe', ['ok' => $ok], [
        'title' => 'Unsubscribe — RuinMyTrip',
        'description' => 'Manage RuinMyTrip email preferences.',
    ]);
}

/* ---------- forms & writes ---------- */
function trip_new_form(array $a): void {
    require_login();
    view('trip_new', ['dests'=>all_dests(),'errors'=>[]], ['title'=>'Share a trip — RuinMyTrip','description'=>'Post a trip story with photos.']);
}
/**
 * Validate a submitted trip. Shared by trip_create and trip_edit_submit so the two rule sets
 * can never drift apart.
 * @return array{ok:bool, errors:string[], data:array<string,mixed>}
 */
function rmt_trip_validate(array $in): array {
    $errors = [];
    $title = (string) ($in['title'] ?? '');
    $body = (string) ($in['body'] ?? '');
    $dest = (int) ($in['destination_id'] ?? 0);
    $cover = trim((string) ($in['cover_url'] ?? ''));
    $visited = (string) ($in['visited_on'] ?? '');

    if (strlen($title) < 5) $errors[] = 'Give your trip a title (5+ characters).';
    if (strlen($body) < 20) $errors[] = 'Add a bit more to your story (20+ characters).';
    if (mb_strlen($title) > 140) $errors[] = 'That title is too long.';
    if (mb_strlen($body) > 20000) $errors[] = 'That story is too long.';
    // Same restriction as profile photos: an unvalidated URL rendered into <img src> is a
    // javascript:/data: delivery vector.
    if ($cover !== '' && (!filter_var($cover, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $cover))) {
        $errors[] = 'Cover photo URL must be a full https:// web address.';
    }
    if ($dest > 0 && !dest_by_id($dest)) $errors[] = 'That destination does not exist.';
    if ($visited !== '' && (strtotime($visited) === false || strtotime($visited) > time())) {
        $errors[] = 'That trip date is not valid.';
    }
    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'title' => $title, 'body' => $body, 'destination_id' => $dest ?: null,
        'cover_url' => $cover, 'visited_on' => $visited ?: null,
    ]];
}

/** Only the author may edit or delete a trip. */
function rmt_trip_can_edit(array $t, ?array $user): bool {
    return $user !== null && (int) $t['user_id'] === (int) $user['id'];
}

function trip_create(array $a): void {
    require_verified_email(); csrf_check(); $me = current_user();
    if (!rmt_submit_ok('trip_new', input('_submit'))) {
        flash('That trip was already submitted.'); redirect('/'); return;
    }
    if (!rmt_rate_ok('trip_create', (string)$me['id'], 20, 3600)) {
        view('trip_new', ['dests'=>all_dests(),'errors'=>['You are posting very fast. Try again later.']],
             ['title'=>'Share a trip — RuinMyTrip']); return;
    }
    $v = rmt_trip_validate($_POST);
    if (!$v['ok']) {
        view('trip_new', ['dests'=>all_dests(),'errors'=>$v['errors']], ['title'=>'Share a trip — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $dest = $d['destination_id'] ? dest_by_id($d['destination_id']) : null;
    $cover = $d['cover_url'] ?: ($dest['hero_url'] ?? '');
    $id = (int) q_run("INSERT INTO trips (user_id,destination_id,title,slug,body,cover_url,visited_on,verified,status,created_at)
                 VALUES (?,?,?,?,?,?,?,?, 'published', ?)",
        [(int)$me['id'], $d['destination_id'], $d['title'], slugify($d['title']), $d['body'], $cover,
         $d['visited_on'], 0, date('Y-m-d H:i:s')]);
    rmt_sync_tags('trip', $id, $d['title'], $d['body']);
    rmt_notify_mentions('trip', $id, (int)$me['id'], [], $d['title'], $d['body']);
    // Photo failures must never be silent, and must never cost the user their written story --
    // same rule as reviews (rmt_attach_review_photos).
    $photoErrors = rmt_attach_trip_photos($id, (int)$me['id']);
    $msg = 'Trip published.';
    if ($photoErrors) $msg .= ' Some photos were not added: ' . implode(' ', array_unique($photoErrors));
    flash($msg);
    rmt_seo_announce('/trip/'.$id.'/'.slugify($d['title']));
    redirect('/trip/'.$id.'/'.slugify($d['title']));
}

function trip_edit_form(array $a): void {
    require_login();
    $t = q_one('SELECT * FROM trips WHERE id=?', [(int)$a['id']]);
    if (!$t) not_found();
    if (!rmt_trip_can_edit($t, current_user())) { forbidden('That is not your trip.'); }
    $photos = q_all('SELECT * FROM trip_photos WHERE trip_id=? ORDER BY sort, id', [(int)$t['id']]);
    view('trip_edit', ['t'=>$t, 'dests'=>all_dests(), 'errors'=>[], 'photos'=>$photos],
         ['title'=>'Edit trip — RuinMyTrip']);
}

function trip_edit_submit(array $a): void {
    require_login(); csrf_check();
    $t = q_one('SELECT * FROM trips WHERE id=?', [(int)$a['id']]);
    if (!$t) not_found();
    if (!rmt_trip_can_edit($t, current_user())) { forbidden('That is not your trip.'); }

    $v = rmt_trip_validate($_POST);
    if (!$v['ok']) {
        $photos = q_all('SELECT * FROM trip_photos WHERE trip_id=? ORDER BY sort, id', [(int)$t['id']]);
        view('trip_edit', ['t'=>array_merge($t, $_POST), 'dests'=>all_dests(), 'errors'=>$v['errors'], 'photos'=>$photos],
             ['title'=>'Edit trip — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $dest = $d['destination_id'] ? dest_by_id($d['destination_id']) : null;
    $cover = $d['cover_url'] ?: ($dest['hero_url'] ?? $t['cover_url']);
    $slug = slugify($d['title']);
    db()->prepare("UPDATE trips SET destination_id=?, title=?, slug=?, body=?, cover_url=?, visited_on=?, updated_at=? WHERE id=?")
        ->execute([$d['destination_id'], $d['title'], $slug, $d['body'], $cover, $d['visited_on'],
                   date('Y-m-d H:i:s'), (int)$t['id']]);
    rmt_sync_tags('trip', (int)$t['id'], $d['title'], $d['body']);
    rmt_notify_mentions('trip', (int)$t['id'], (int)current_user()['id'], [], $d['title'], $d['body']);
    $photoErrors = rmt_attach_trip_photos((int)$t['id'], (int)current_user()['id']);

    // Remove any photos the author unticked.
    foreach ((array)($_POST['remove_photo'] ?? []) as $pid) {
        $ph = q_one('SELECT * FROM trip_photos WHERE id=? AND trip_id=?', [(int)$pid, (int)$t['id']]);
        if ($ph) {
            db()->prepare('DELETE FROM trip_photos WHERE id=?')->execute([(int)$ph['id']]);
            if (!empty($ph['storage_key'])) rmt_storage_delete((string)$ph['storage_key']);
        }
    }

    $msg = 'Trip updated.';
    if ($photoErrors) $msg .= ' Some photos were not added: ' . implode(' ', array_unique($photoErrors));
    flash($msg);
    redirect('/trip/'.(int)$t['id'].'/'.$slug);
}

/** POST /trip/{id}/delete — soft delete. Rows are never destroyed. */
function trip_delete(array $a): void {
    require_login(); csrf_check();
    $t = q_one('SELECT * FROM trips WHERE id=?', [(int)$a['id']]);
    if (!$t) not_found();
    if (!rmt_trip_can_edit($t, current_user())) { forbidden('That is not your trip.'); }
    db()->prepare("UPDATE trips SET status='removed', updated_at=? WHERE id=?")
        ->execute([date('Y-m-d H:i:s'), (int)$t['id']]);
    // The trip row itself is soft-deleted (matches every other content type), but the uploaded
    // photo BLOBS in the media table are not cheap DB rows -- left alone, they stayed in storage
    // and stayed reachable at their direct /media/{key} URL forever, even after the owner
    // "deleted" the trip that showed them. Same cleanup trip_edit_submit already does per-photo
    // when a photo is unticked; a deleted trip just does it for all of them at once.
    foreach (q_all('SELECT storage_key FROM trip_photos WHERE trip_id=?', [(int)$t['id']]) as $ph) {
        if (!empty($ph['storage_key'])) rmt_storage_delete((string)$ph['storage_key']);
    }
    flash('Trip deleted.');
    redirect('/u/'.current_user()['username']);
}

/**
 * Store any photos submitted with a trip. Same shape as rmt_attach_review_photos(): upload
 * failures are reported but never discard the trip itself.
 * @return string[] error messages
 */
function rmt_attach_trip_photos(int $tripId, int $ownerId): array {
    $errors = [];
    if (empty($_FILES['photos']) || !is_array($_FILES['photos']['name'] ?? null)) return $errors;

    $existing = (int)(q_one('SELECT COUNT(*) c FROM trip_photos WHERE trip_id=?', [$tripId])['c'] ?? 0);
    $slots = max(0, 6 - $existing);   // cap photos per trip, same as reviews

    $n = count($_FILES['photos']['name']);
    for ($i = 0; $i < $n; $i++) {
        if ((int)$_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($slots <= 0) { $errors[] = 'You can attach up to 6 photos per trip.'; break; }
        if (!rmt_rate_ok('upload', (string)$ownerId, 40, 3600)) { $errors[] = 'Too many uploads. Try again later.'; break; }

        $file = [
            'name'     => $_FILES['photos']['name'][$i],
            'type'     => $_FILES['photos']['type'][$i],
            'tmp_name' => $_FILES['photos']['tmp_name'][$i],
            'error'    => $_FILES['photos']['error'][$i],
            'size'     => $_FILES['photos']['size'][$i],
        ];
        $res = rmt_upload_image($file, $ownerId);
        if (!$res['ok']) { $errors[] = $res['error']; continue; }

        q_run('INSERT INTO trip_photos (trip_id, url, storage_key, caption, width, height, bytes, sort, created_at)
               VALUES (?,?,?,?,?,?,?,?,?)',
              [$tripId, $res['url'], $res['key'], null, $res['w'], $res['h'], $res['bytes'],
               $existing + $i, date('Y-m-d H:i:s')]);
        $slots--;
    }
    return $errors;
}

/**
 * GET /contribute — a way in for somebody who has a trip in their head rather than a URL.
 *
 * Most reviews will start from a place page. This is for the rest: a traveler who just got back
 * and wants to write about four things, none of which they are currently looking at. Without it,
 * the only path to writing is remembering how to find each place first, and that is enough
 * friction to lose the review.
 */
/**
 * POST /event — a funnel event the browser is in a position to see and the server is not.
 *
 * Only clicks and restores: a CTA press, a suggestion picked, a saved draft put back. Everything
 * the server can observe for itself is recorded there instead, because a client-reported step is
 * one a client can decline to report.
 *
 * CSRF-checked and rate limited like any other write. Answers 204 always: analytics is never worth
 * an error the person can see.
 */
function contribution_event(array $a): void {
    csrf_check();
    if (rmt_rate_ok('contrib_event', (string) ($_SERVER['REMOTE_ADDR'] ?? 'anon'), 180, 60)) {
        rmt_track((string) input('event'), [
            'source'         => (string) input('source'),
            'place_id'       => (int) input('place_id'),
            'destination_id' => (int) input('destination_id'),
        ]);
    }
    http_response_code(204);
}

function contribute_page(array $a): void {
    $me = current_user();
    $myReviews = $me ? (int) (q_one("SELECT COUNT(*) c FROM reviews WHERE user_id = ? AND status = 'published'",
                                    [(int) $me['id']])['c'] ?? 0) : 0;
    // Destinations that actually have places in them, biggest first. Not a "popular" list: we have
    // no popularity signal worth the name yet, and inventing one would be the first lie on a page
    // whose whole purpose is honest contribution.
    $recentDestinations = q_all(
        "SELECT d.slug, d.name,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active') places
           FROM destinations d
          ORDER BY places DESC, d.name
          LIMIT 12");
    $recentDestinations = array_values(array_filter($recentDestinations, static fn($r) => (int) $r['places'] > 0));

    // Arriving from a search that found nothing: the traveler has already typed the name of the
    // thing we are missing, so ask them to type it a second time only if we lost it.
    $prefillName = trim((string) ($_GET['name'] ?? ''));
    if ($prefillName !== '' && !rmt_search_suggestable($prefillName)) $prefillName = '';
    $prefillName = mb_substr($prefillName, 0, 200);

    view('contribute', compact('me', 'recentDestinations', 'myReviews', 'prefillName'), [
        'title' => 'Review a place you went to — RuinMyTrip',
        'description' => 'Write about a hotel, restaurant or attraction you actually visited. Real traveler reviews, not imported listings.',
    ]);
}

/**
 * POST /contribute/suggest-place — a traveler tells us about a place we do not have.
 *
 * Creates a QUEUE ROW, never a place. Nothing here becomes public, nothing becomes indexable, and
 * nothing skips the deduplication that keeps one venue on one page. A suggestion that matches
 * something we already hold is answered with that page instead, on the spot, because the person
 * wanting to review the Louvre under a slightly different name should be writing their review a
 * second later rather than waiting on a queue.
 */
function contribute_suggest_place(array $a): void {
    require_login(); csrf_check();
    $me = current_user();

    if (!rmt_rate_ok('suggest_place', (string) $me['id'], 10, 3600)) {
        flash('That is a lot of suggestions at once. Try again a bit later.');
        redirect('/contribute');
    }

    $name = trim((string) input('name'));
    $city = trim((string) input('city'));
    $type = (string) input('type');
    if (!in_array($type, RMT_PLACE_TYPES, true)) $type = 'attraction';
    $website = rmt_place_normalize_website((string) input('website_url'));

    if ($name === '' || $city === '' || mb_strlen($name) > 200 || mb_strlen($city) > 120) {
        flash('Tell us at least the name and the city.');
        redirect('/contribute');
    }

    // Already here under this or a near name? Send them to it rather than into a queue.
    $key = rmt_place_name_key($name);
    $existing = q_one("SELECT p.slug FROM places p JOIN destinations d ON d.id = p.destination_id
                        WHERE p.status = 'active' AND p.name_key = ? AND LOWER(d.name) = LOWER(?)",
                      [$key, $city]);
    if ($existing) {
        flash('We already have that one — here it is.');
        redirect('/review/new?place=' . (int) (q_one('SELECT id FROM places WHERE slug = ?', [$existing['slug']])['id'] ?? 0));
    }

    q_run("INSERT INTO place_suggestions (name, city, type, website_url, suggested_by, status, created_at)
           VALUES (?,?,?,?,?,'pending',?)",
          [$name, $city, $type, $website, (int) $me['id'], date('Y-m-d H:i:s')]);
    rmt_track('place_suggested', ['source' => 'contribute']);
    flash('Thanks — we will check it and add it. Places are added by hand, so it is not instant.');
    redirect('/contribute');
}

/**
 * GET /ruined — every "what ruined it" line on one page, and the box that asks for yours.
 * The page a stranger lands on from a shared quote, and the page that turns them into a writer.
 */
function ruined_page(array $a): void {
    $slug = trim((string) input('d'));
    $dest = $slug !== '' ? q_one('SELECT * FROM destinations WHERE slug=?', [$slug]) : null;
    $destId = $dest ? (int) $dest['id'] : null;
    $rows = rmt_reviews_ruined(90, $destId);
    $total = rmt_reviews_ruined_count($destId);
    $dests = all_dests();
    $crumbs = [['name' => 'Home', 'url' => url()], ['name' => 'What ruined it', 'url' => url('ruined')]];
    if ($dest) $crumbs[] = ['name' => (string) $dest['name'], 'url' => url('ruined?d=' . $dest['slug'])];
    view('ruined', compact('rows', 'dests', 'dest', 'total'), [
        'title' => ($dest ? 'What ruined trips to ' . $dest['name'] : 'What ruined the trip') . ' — RuinMyTrip',
        'description' => $dest
            ? 'One sentence each from travelers about what nearly ruined ' . $dest['name'] . ', and a place to add yours.'
            : 'The thing travelers wish somebody had warned them about, one sentence each. Read them before you go, add yours when you get back.',
        'robots' => rmt_robots_for(rmt_indexable($dest ? 'filter' : 'static')),
        'breadcrumbs' => $crumbs,
    ]);
}

function review_new_form(array $a): void {
    // Recorded BEFORE require_login sends an anonymous visitor away, because "wanted the form and
    // had no account" is the single most important step in this funnel and it is invisible after
    // the redirect.
    if (!is_logged_in()) {
        rmt_track('review_signup_required', [
            'source' => (string) (input('src') ?: 'place'),
            'place_id' => (int) input('place'),
            'destination_id' => (int) input('destination'),
        ]);
    }
    require_login();
    // A "Share your experience" link from a destination page should not dump the writer back
    // into an empty type-ahead they have to re-search -- that extra step is exactly the kind of
    // friction that keeps a real review from ever getting written.
    // A review started from a place page is bound to that place outright: its destination, type and
    // exact name are filled in and locked, so the one thing that decides which page the review lands
    // on is not left to the writer re-spelling a name in a free-text box.
    $bound = rmt_place_by_id((int) input('place'));
    if ($bound) {
        $r = ['destination_id' => (int) $bound['destination_id'],
              'subject_type'   => $bound['type'],
              'subject_name'   => $bound['name']];
        if (($ruined = trim((string) input('ruined'))) !== '') $r['what_ruined'] = mb_substr($ruined, 0, 2000);
        rmt_track('review_form_start', ['source' => (string) (input('src') ?: 'place'),
                                        'place_id' => (int) $bound['id'],
                                        'destination_id' => (int) $bound['destination_id']]);
        view('review_new', ['dests'=>all_dests(), 'errors'=>[], 'r'=>$r, 'placeOptions'=>[], 'boundPlace'=>$bound, 'aspectValues'=>[]],
             ['title'=>'Review '.$bound['name'].' — RuinMyTrip']);
        return;
    }
    $preselect = (int) input('destination');
    $r = ($preselect && dest_by_id($preselect)) ? ['destination_id' => $preselect] : null;
    // The front-door question ("What ruined your trip?") arrives here as ?ruined=; it opens the
    // form with the sentence already in the box, which is the whole point of asking for it first.
    if (($ruined = trim((string) input('ruined'))) !== '') $r = ($r ?? []) + ['what_ruined' => mb_substr($ruined, 0, 2000)];
    rmt_track('review_form_start', ['source' => (string) (input('src') ?: 'contribute'),
                                    'destination_id' => $preselect]);
    view('review_new', ['dests'=>all_dests(), 'errors'=>[], 'r'=>$r, 'placeOptions'=>rmt_place_suggestions(), 'boundPlace'=>null, 'aspectValues'=>[]],
         ['title'=>'Write a review — RuinMyTrip']);
}

function review_create(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    if (!rmt_submit_ok('review_new', input('_submit'))) {
        flash('That review was already submitted.'); redirect('/'); return;
    }
    // Re-renders below must keep the place binding, or a writer who tripped one validation error
    // would be handed back the unbound form and lose the page they started from.
    $bound = rmt_place_by_id((int) input('place_id'));
    $opts  = static fn(array $extra) => $extra + ['dests'=>all_dests(), 'placeOptions'=>$bound ? [] : rmt_place_suggestions(),
                                                 'boundPlace'=>$bound, 'aspectValues'=>rmt_posted_aspect_values($_POST)];
    if (!rmt_rate_ok('review_create', (string)$me['id'], 20, 3600)) {
        rmt_track('review_publish_failure', ['reason' => 'rate_limit']);
        view('review_new', $opts(['errors'=>['You are posting very fast. Try again later.'], 'r'=>null]),
             ['title'=>'Write a review — RuinMyTrip']); return;
    }
    $isDraft = input('action') === 'draft';
    // Publishing requires a confirmed email. It used to redirect to the verification page here,
    // which threw away everything the person had just written -- and it did it to the one group
    // that matters most, somebody publishing their FIRST review minutes after signing up. Now the
    // review is saved as their draft and they are told so; confirming the address and pressing
    // publish is all that is left.
    $holdForVerification = !$isDraft && !email_is_verified($me);
    if ($holdForVerification) {
        $isDraft = true;
        rmt_track('review_verification_required', ['reason' => 'verification']);
    }
    rmt_track('review_submit_attempt', ['source' => (string) (input('src') ?: 'place'),
                                        'place_id' => $bound ? (int) $bound['id'] : 0]);
    $v = rmt_review_validate($_POST, $isDraft);
    // Aspect ratings are parsed against the category being submitted, not against whatever the
    // browser happened to render, and a malformed set stops the save alongside any other error.
    $asp = rmt_review_parse_aspects($_POST, (string) ($_POST['subject_type'] ?? ''));
    if (!$v['ok'] || !$asp['ok']) {
        rmt_track('review_publish_failure', ['reason' => 'validation']);
        view('review_new', $opts(['errors'=>array_merge($v['errors'], $asp['errors']), 'r'=>$_POST]),
             ['title'=>'Write a review — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $travelerType = rmt_traveler_type_clean($_POST['traveler_type'] ?? null);
    $now = date('Y-m-d H:i:s');
    $status = $isDraft ? 'draft' : 'published';
    // Resolve what was reviewed to a real place row so every review of the same hotel collects on
    // one page. Returns null for destination-level reviews and for drafts with no name yet — the
    // column is nullable and the review renders from subject_name either way.
    $placeId = ($bound ? rmt_place_bound_id((int)$bound['id'], $d['destination_id'], $d['subject_name']) : null)
        ?? rmt_place_resolve($d['destination_id'], $d['subject_type'], $d['subject_name'], (int)$me['id']);
    $id = (int) q_run("INSERT INTO reviews
        (user_id,destination_id,place_id,subject_type,subject_name,rating,title,body,what_great,what_ruined,
         visited_on,safety_rating,value_rating,traveler_type,verified,status,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?)",
        [(int)$me['id'], $d['destination_id'], $placeId, $d['subject_type'], $d['subject_name'], $d['rating'],
         $d['title'], $d['body'], $d['what_great'], $d['what_ruined'], $d['visited_on'],
         $d['safety_rating'], $d['value_rating'], $travelerType, $status, $now, $now]);

    // Written after the insert because they need the review id. rmt_review_save_aspects() also
    // re-derives safety_rating and value_rating from the aspect rows, so the two legacy columns end
    // up correct whether the submission came from the new form or an older one.
    rmt_review_save_aspects($id, $asp['values']);

    $slug = rmt_review_slug($d + ['id'=>$id]);
    db()->prepare('UPDATE reviews SET slug = ? WHERE id = ?')->execute([$slug, $id]);
    rmt_sync_tags('review', $id, $d['title'], $d['body'], $d['what_great'], $d['what_ruined']);
    // Drafts must not ping anyone; the mention fires when the review later publishes via edit.
    if ($status === 'published') {
        rmt_notify_mentions('review', $id, (int)$me['id'], [], $d['title'], $d['body'], $d['what_great'], $d['what_ruined']);
    }

    // Photo failures must never be silent: the review still publishes (losing written text
    // because one image failed would be worse), but the user is told exactly what happened.
    $photoErrors = rmt_attach_review_photos($id, (int)$me['id']);

    // Badges are evaluated against real activity, never granted by hand.
    if (!$isDraft) rmt_award_badges((int)$me['id']);

    $msg = $isDraft ? 'Draft saved. Only you can see it.' : 'Your review is live.';
    if ($holdForVerification) {
        $msg = 'Saved as a draft — nothing was lost. Confirm your email address and you can publish it.';
    }
    if ($photoErrors) $msg .= ' Some photos were not added: ' . implode(' ', array_unique($photoErrors));
    flash($msg);
    // ?published=1 asks the review page for the "what next" panel. Landing on your own review and
    // being shown two useful things to do next is the difference between one review and a habit;
    // a bare redirect back to the page is where a first-time contributor stops.
    if (!$isDraft) {
        rmt_track('review_publish_success', ['place_id' => (int) $placeId,
                                             'destination_id' => (int) $d['destination_id']]);
        // A published review ends this attempt; the next one is counted separately.
        rmt_journey_rotate();
    }
    if ($holdForVerification) redirect('/verify-email');
    if (!$isDraft) rmt_seo_announce('/review/'.$id.'/'.$slug);
    redirect($isDraft ? '/reviews?mine=1' : '/review/'.$id.'/'.$slug.'?published=1');
}

/**
 * Store any photos submitted with a review. Upload failures are reported to the user but never
 * discard the review itself — losing written text because one image failed would be worse than
 * a missing photo.
 * @return string[] error messages
 */
function rmt_attach_review_photos(int $reviewId, int $ownerId): array {
    $errors = [];
    if (empty($_FILES['photos']) || !is_array($_FILES['photos']['name'] ?? null)) return $errors;

    $existing = (int)(q_one('SELECT COUNT(*) c FROM review_photos WHERE review_id=?', [$reviewId])['c'] ?? 0);
    $slots = max(0, 6 - $existing);   // cap photos per review

    $n = count($_FILES['photos']['name']);
    for ($i = 0; $i < $n; $i++) {
        if ((int)$_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($slots <= 0) { $errors[] = 'You can attach up to 6 photos per review.'; break; }
        if (!rmt_rate_ok('upload', (string)$ownerId, 40, 3600)) { $errors[] = 'Too many uploads. Try again later.'; break; }

        $file = [
            'name'     => $_FILES['photos']['name'][$i],
            'type'     => $_FILES['photos']['type'][$i],
            'tmp_name' => $_FILES['photos']['tmp_name'][$i],
            'error'    => $_FILES['photos']['error'][$i],
            'size'     => $_FILES['photos']['size'][$i],
        ];
        $res = rmt_upload_image($file, $ownerId);
        if (!$res['ok']) { $errors[] = $res['error']; continue; }

        q_run('INSERT INTO review_photos (review_id, url, storage_key, caption, width, height, bytes, sort, created_at)
               VALUES (?,?,?,?,?,?,?,?,?)',
              [$reviewId, $res['url'], $res['key'], null, $res['w'], $res['h'], $res['bytes'],
               $existing + $i, date('Y-m-d H:i:s')]);
        $slots--;
    }
    return $errors;
}

/** GET /review/{id}/{slug} — public permalink. */
function review_show(array $a): void {
    $r = rmt_review_get((int)$a['id']);
    if (!$r) not_found();
    $me = current_user();
    if (!rmt_review_can_view($r, $me)) not_found();

    // Canonicalise: a wrong or missing slug redirects to the real one rather than serving the
    // same content on many URLs.
    $slug = $r['slug'] ?: rmt_review_slug($r);
    if (($a['slug'] ?? '') !== $slug) redirect(rmt_review_path($r));

    $author = author((int)$r['user_id']);
    $photos = q_all('SELECT * FROM review_photos WHERE review_id = ? ORDER BY sort, id', [(int)$r['id']]);
    $voteCounts = rmt_review_vote_counts((int)$r['id']);
    $myVotes = $me ? rmt_review_my_votes((int)$r['id'], (int)$me['id']) : [];
    $comments = q_all("SELECT c.*, u.username, p.avatar_url FROM comments c JOIN users u ON u.id=c.user_id
                       LEFT JOIN profiles p ON p.user_id=u.id
                       WHERE c.target_type='review' AND c.target_id=? AND c.status='published' ORDER BY c.id", [(int)$r['id']]);
    $tags = rmt_tags_for('review', (int)$r['id']);
    // No robots directive: a draft/hidden review 404s for anyone but its author (see
    // rmt_review_can_view), so crawlers cannot reach it. Access control, not noindex.
    $aspectValues = rmt_review_aspect_values((int)$r['id']);
    // Shown once, straight after publishing, and only to the person who wrote it.
    $justPublished = input('published') === '1' && $me && (int) $me['id'] === (int) $r['user_id']
                     && $r['status'] === 'published';
    // Whether this is their FIRST. A first review is a different moment from a fiftieth: somebody
    // has just done a thing they had never done before, on a site that had nothing from them, and
    // saying so once is worth more than saying "thanks" identically forever.
    $isFirstReview = $justPublished && (int) (q_one(
        "SELECT COUNT(*) c FROM reviews WHERE user_id = ? AND status = 'published'",
        [(int) $r['user_id']])['c'] ?? 0) === 1;
    /* A review that ends in nothing is a leaf: the reader arrived from a search result and has
       nowhere to go but back to it. Two neighbours -- what else people said about this city, and
       what they are asking about this exact place -- keep the visit going and give the crawler a
       reason to walk deeper than one page. */
    $moreReviews = $r['destination_id'] ? q_all(
        "SELECT r2.id, r2.slug, r2.title, r2.subject_name, r2.rating, u.username
           FROM reviews r2 JOIN users u ON u.id = r2.user_id
          WHERE r2.destination_id = ? AND r2.status='published' AND r2.id <> ? AND u.status='active'
       ORDER BY r2.created_at DESC, r2.id DESC LIMIT 4",
        [(int) $r['destination_id'], (int) $r['id']]) : [];
    $placeTalk = $r['place_id'] ? rmt_posts_for_place((int) $r['place_id'], 3) : [];

    view('review_show', compact('r','author','photos','me','voteCounts','myVotes','comments','tags','aspectValues','justPublished','isFirstReview','moreReviews','placeTalk'), [
        'title' => rmt_meta_title((string) ($r['title'] ?: $r['subject_name'])),
        'description' => rmt_meta_description((string) $r['body']),
        'og_image' => $photos ? abs_url((string) $photos[0]['url']) : rmt_card_url('review', (string) (int) $r['id']),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Reviews','url'=>url('reviews')],
                          ['name'=>$r['title'] ?: $r['subject_name'],'url'=>url(ltrim(rmt_review_path($r),'/'))]],
        'jsonld' => rmt_review_jsonld($r),
    ]);
}

function review_edit_form(array $a): void {
    require_login();
    $r = rmt_review_get((int)$a['id']);
    if (!$r) not_found();
    if (!rmt_review_can_edit($r, current_user())) { forbidden('That is not your review.'); }
    $photos = q_all('SELECT * FROM review_photos WHERE review_id=? ORDER BY sort, id', [(int)$r['id']]);
    view('review_edit', ['r'=>$r, 'dests'=>all_dests(), 'errors'=>[], 'photos'=>$photos, 'placeOptions'=>rmt_place_suggestions(),
                         'aspectValues'=>rmt_review_aspect_values((int)$r['id'])],
         ['title'=>'Edit review — RuinMyTrip']);
}

function review_edit_submit(array $a): void {
    require_login(); csrf_check();
    $r = rmt_review_get((int)$a['id']);
    if (!$r) not_found();
    if (!rmt_review_can_edit($r, current_user())) { forbidden('That is not your review.'); }

    $isDraft = input('action') === 'draft';
    // Same rule as creating one: hold it as a draft rather than discarding the edit.
    $holdForVerification = !$isDraft && !email_is_verified(current_user());
    if ($holdForVerification) $isDraft = true;
    $v = rmt_review_validate($_POST, $isDraft);
    $asp = rmt_review_parse_aspects($_POST, (string) ($_POST['subject_type'] ?? ''));
    if (!$v['ok'] || !$asp['ok']) {
        $photos = q_all('SELECT * FROM review_photos WHERE review_id=? ORDER BY sort, id', [(int)$r['id']]);
        view('review_edit', ['r'=>array_merge($r, $_POST), 'dests'=>all_dests(), 'errors'=>array_merge($v['errors'], $asp['errors']),
                             'photos'=>$photos, 'placeOptions'=>rmt_place_suggestions(),
                             'aspectValues'=>rmt_posted_aspect_values($_POST)],
             ['title'=>'Edit review — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $travelerType = rmt_traveler_type_clean($_POST['traveler_type'] ?? null);
    // A hidden/removed review stays that way on edit — editing must not let a user undo moderation.
    $status = in_array($r['status'], ['hidden','removed'], true)
        ? $r['status']
        : ($isDraft ? 'draft' : 'published');
    $slug = rmt_review_slug($d + ['id'=>(int)$r['id']]);
    // Re-resolve on every edit: renaming the subject, or moving the review to another destination,
    // must move the review to the place it now describes rather than leaving it counted against the
    // old one. Re-resolving to null (subject cleared) correctly detaches it.
    $placeId = rmt_place_resolve($d['destination_id'], $d['subject_type'], $d['subject_name'], (int)current_user()['id']);
    db()->prepare("UPDATE reviews SET destination_id=?, place_id=?, subject_type=?, subject_name=?, rating=?, title=?,
                   body=?, what_great=?, what_ruined=?, visited_on=?, safety_rating=?, value_rating=?,
                   traveler_type=?, status=?, slug=?, updated_at=? WHERE id=?")
        ->execute([$d['destination_id'], $placeId, $d['subject_type'], $d['subject_name'], $d['rating'], $d['title'],
                   $d['body'], $d['what_great'], $d['what_ruined'], $d['visited_on'], $d['safety_rating'],
                   $d['value_rating'], $travelerType, $status, $slug, date('Y-m-d H:i:s'), (int)$r['id']]);

    // Inserts new ratings, updates changed ones and deletes the ones the author cleared. Aspects
    // belonging to a category this review is no longer filed under are left alone rather than
    // half-deleted, and the mirror columns are re-derived from whatever survives.
    rmt_review_save_aspects((int)$r['id'], $asp['values']);
    rmt_sync_tags('review', (int)$r['id'], $d['title'], $d['body'], $d['what_great'], $d['what_ruined']);
    if ($status === 'published') {
        rmt_notify_mentions('review', (int)$r['id'], (int)current_user()['id'], [], $d['title'], $d['body'], $d['what_great'], $d['what_ruined']);
    }
    $photoErrors = rmt_attach_review_photos((int)$r['id'], (int)current_user()['id']);

    // Remove any photos the author unticked.
    foreach ((array)($_POST['remove_photo'] ?? []) as $pid) {
        $ph = q_one('SELECT * FROM review_photos WHERE id=? AND review_id=?', [(int)$pid, (int)$r['id']]);
        if ($ph) {
            db()->prepare('DELETE FROM review_photos WHERE id=?')->execute([(int)$ph['id']]);
            rmt_storage_delete((string)$ph['storage_key']);
        }
    }

    if ($status === 'published') rmt_award_badges((int)current_user()['id']);
    $msg = $holdForVerification
        ? 'Saved as a draft — your changes are kept. Confirm your email address and you can publish it.'
        : 'Review updated.';
    if ($photoErrors) $msg .= ' Some photos were not added: ' . implode(' ', array_unique($photoErrors));
    flash($msg);
    if ($holdForVerification) redirect('/verify-email');
    redirect($status === 'draft' ? '/reviews?mine=1' : '/review/'.(int)$r['id'].'/'.$slug);
}

/** POST /review/{id}/delete — soft delete. Rows are never destroyed. */
function review_delete(array $a): void {
    require_login(); csrf_check();
    $r = rmt_review_get((int)$a['id']);
    if (!$r) not_found();
    if (!rmt_review_can_edit($r, current_user())) { forbidden('That is not your review.'); }
    db()->prepare("UPDATE reviews SET status='removed', updated_at=? WHERE id=?")
        ->execute([date('Y-m-d H:i:s'), (int)$r['id']]);
    // Same gap as trip_delete() had: the review row soft-deletes, but its uploaded photo BLOBS
    // in the media table stayed reachable at their direct /media/{key} URL forever otherwise.
    foreach (q_all('SELECT storage_key FROM review_photos WHERE review_id=?', [(int)$r['id']]) as $ph) {
        if (!empty($ph['storage_key'])) rmt_storage_delete((string)$ph['storage_key']);
    }
    flash('Review deleted.');
    redirect('/u/'.current_user()['username']);
}

function follow_action(array $a): void {
    require_login(); csrf_check(); $me=current_user();
    $target=(int)input('user_id');
    if (!$target || $target===(int)$me['id']) redirect(rmt_return_to());
    // The target must be a real, active user. Without this, following a bogus id throws an
    // uncaught FK violation (500) — which also happens naturally if the followee deleted their
    // account between the page loading and the click.
    if (!q_one("SELECT 1 FROM users WHERE id=? AND status='active'", [$target])) {
        flash('That traveler is no longer available.'); redirect(rmt_return_to());
    }
    if (rmt_is_blocked((int)$me['id'], $target)) redirect(rmt_return_to());
    // Follows create notifications, so cap them to blunt notification-spam.
    if (!rmt_rate_ok('follow', (string)$me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.'); redirect(rmt_return_to());
    }
    $exists = q_one('SELECT 1 FROM follows WHERE follower_id=? AND followee_id=?', [(int)$me['id'],$target]);
    if ($exists) db()->prepare('DELETE FROM follows WHERE follower_id=? AND followee_id=?')->execute([(int)$me['id'],$target]);
    else {
        // Same double-click race as react_action: the (follower_id,followee_id) primary key
        // stops a duplicate follow row, but without this catch the loser of the race got an
        // uncaught PDOException (500 page) instead of a no-op.
        try {
            q_run('INSERT INTO follows (follower_id,followee_id,created_at) VALUES (?,?,?)', [(int)$me['id'],$target,date('Y-m-d H:i:s')]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
            redirect(rmt_return_to());
        }
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
            [$target,'follow',(int)$me['id'],'user',(int)$me['id'],date('Y-m-d H:i:s')]);
    }
    redirect(rmt_return_to());
}

/**
 * Interactable content types -> table. Same allow-list discipline as reports: a type that
 * reaches a table name is never taken raw from the request.
 */
const RMT_INTERACT_TARGETS = [
    'trip'        => 'trips',
    'review'      => 'reviews',
    'guide'       => 'guides',
    'meetup'      => 'meetups',
    'blog_post'   => 'blog_posts',
    'collection'  => 'collections',
    'destination' => 'destinations',
    'post'        => 'posts',
];

/**
 * Is $tt#$tid something $user is allowed to interact with (comment on, like, save)?
 *
 * You may only touch content you can actually SEE. Without this, a stranger could comment on and
 * like another user's unpublished draft purely by guessing its id — the draft 404s for them, but
 * the interaction endpoints never checked. Proven before this fix: @snoop landed a comment and a
 * like on a draft they could not view.
 */
/**
 * Which column names the owner, for each interactable type.
 *
 * Meetups call theirs `host_id`. Everything else uses `user_id`, and the code that reads an owner
 * assumed that everywhere -- so `SELECT user_id FROM meetups` threw a PDOException, which is a 500
 * page, on every attempt to comment on or like a meetup. Confirmed locally before this fix:
 * rmt_can_interact('meetup', 1, $user) threw SQLSTATE[HY000] no such column: user_id.
 */
const RMT_INTERACT_OWNER_COLUMN = ['meetup' => 'host_id'];

function rmt_interact_owner_column(string $tt): string {
    return RMT_INTERACT_OWNER_COLUMN[$tt] ?? 'user_id';
}

/** The id of whoever owns $tt#$tid, or 0 if there is no such row or no such type. */
function rmt_content_owner_id(string $tt, int $tid): int {
    if ($tt === 'destination') return 0; // destinations are not owned by a member
    $table = RMT_INTERACT_TARGETS[$tt] ?? null;
    if (!$table || $tid < 1) return 0;
    $col = rmt_interact_owner_column($tt);
    return (int) (q_one("SELECT {$col} AS owner_id FROM {$table} WHERE id = ?", [$tid])['owner_id'] ?? 0);
}

/**
 * Is $userId blocked from ADDING an interaction to $tt#$tid?
 *
 * rmt_is_blocked() is symmetric: it does not matter who blocked whom, the two of them have stopped
 * interacting. Following, complimenting and messaging already honoured that. Commenting, liking,
 * saving, voting and RSVPing did not, so a block stopped somebody sending you a message and left
 * them free to turn up in the comments under your review -- or at your meetup.
 *
 * Only the ADD direction is gated. Someone who liked your review before you blocked them must
 * still be able to unlike it, and someone who RSVPed must still be able to withdraw; being stuck
 * holding an interaction you cannot take back is the wrong way for this to fail.
 */
function rmt_blocked_from(int $userId, string $tt, int $tid): bool {
    $owner = rmt_content_owner_id($tt, $tid);
    return $owner > 0 && $owner !== $userId && rmt_is_blocked($userId, $owner);
}

function rmt_can_interact(string $tt, int $tid, ?array $user): bool {
    $table = RMT_INTERACT_TARGETS[$tt] ?? null;
    if (!$table || $tid < 1) return false;
    // Destinations are global rows with no owner/status. Talking on a city page is the
    // social action that exists before anyone has written a trip or review.
    if ($tt === 'destination') {
        return (bool) q_one('SELECT id FROM destinations WHERE id = ?', [$tid]);
    }

    $col = rmt_interact_owner_column($tt);
    $row = q_one("SELECT {$col} AS user_id, status FROM {$table} WHERE id = ?", [$tid]);
    if (!$row) return false;                       // must exist — no ghost interactions
    if (($row['status'] ?? '') === 'published') return true;
    if (!$user) return false;
    if ((int) ($row['user_id'] ?? 0) === (int) $user['id']) return true;   // own draft
    return in_array($user['role'] ?? '', ['admin', 'mod'], true);
}

function react_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $kind = input('kind', 'like') === 'save' ? 'save' : 'like';
    $tbl  = $kind === 'save' ? 'saves' : 'likes';
    $tt   = (string) input('target_type');
    $tid  = (int) input('target_id');

    if (!rmt_can_interact($tt, $tid, $me)) redirect(rmt_return_to());
    if (!rmt_rate_ok('react', (string)$me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect(rmt_return_to());
    }

    $has = q_one("SELECT 1 FROM $tbl WHERE user_id=? AND target_type=? AND target_id=?", [(int)$me['id'],$tt,$tid]);
    // Un-liking and un-saving stay open either way; only the add branch below is gated.
    if ($has) db()->prepare("DELETE FROM $tbl WHERE user_id=? AND target_type=? AND target_id=?")->execute([(int)$me['id'],$tt,$tid]);
    else {
        if (rmt_blocked_from((int)$me['id'], $tt, $tid)) redirect(rmt_return_to());
        // A double-click can fire two near-simultaneous requests that both see $has as false; the
        // table's (user_id,target_type,target_id) primary key stops a duplicate row, but the
        // loser previously surfaced as an uncaught PDOException (500 page) instead of just no-op'ing
        // into the same end state the winner already produced.
        try { db()->prepare("INSERT INTO $tbl (user_id,target_type,target_id" . ($kind === 'save' ? ',created_at' : '') . ") VALUES (?,?,?" . ($kind === 'save' ? ',?' : '') . ")")
                  ->execute($kind === 'save' ? [(int)$me['id'],$tt,$tid,date('Y-m-d H:i:s')] : [(int)$me['id'],$tt,$tid]); }
        catch (\PDOException $e) { if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e; }

        /* Somebody liking what you wrote was, until now, invisible to you. It is the smallest
           signal the site produces and the one people come back for. Saves stay silent: a save is
           a note to yourself about somebody else's page, and telling them turns a private
           bookmark into a public act. */
        if ($kind === 'like') rmt_notify_like((int) $me['id'], $tt, $tid);
    }
    redirect(rmt_return_to());
}

/**
 * Tell an author their work was liked, once per person per thing.
 *
 * Once ever, not once per like: unliking and liking again is a thing people do by accident with a
 * double tap, and it must not ring somebody's bell twice.
 */
function rmt_notify_like(int $actorId, string $tt, int $tid): void {
    $owner = rmt_content_owner_id($tt, $tid);
    if ($owner < 1 || $owner === $actorId) return;
    $seen = q_one("SELECT 1 x FROM notifications
                    WHERE user_id=? AND type='like' AND actor_id=? AND target_type=? AND target_id=?",
                  [$owner, $actorId, $tt, $tid]);
    if ($seen) return;
    q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
          [$owner, 'like', $actorId, $tt, $tid, date('Y-m-d H:i:s')]);
}

/** POST /destination/been — self-asserted "I've been". Not a review, not a rating. */
function destination_been_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    if (rmt_is_editorial($me)) { flash('Editorial accounts do not stamp visits.'); redirect(rmt_return_to()); }
    $did = (int) input('destination_id');
    if (!$did || !dest_by_id($did)) redirect(rmt_return_to());
    if (!rmt_rate_ok('react', (string)$me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect(rmt_return_to());
    }
    $on = rmt_visit_toggle((int)$me['id'], $did);
    flash($on ? "Marked as a place you've been. This is not a review and is not a rating." : 'Removed the been stamp.');
    redirect(rmt_return_to());
}

function start_page(array $a): void {
    view('start', [], [
        'title' => 'How to launch RuinMyTrip: join, stamp cities, write a review',
        'description' => 'The site is already live. Create an account, confirm email, mark cities you have been to, and publish one honest review. That is the launch.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Start','url'=>url('start')]],
    ]);
}

function founding(array $a): void {
    $n = (int) (q_one("SELECT COUNT(*) c FROM users WHERE status='active' AND role <> ?", [RMT_EDITORIAL_ROLE])['c'] ?? 0);
    $left = max(0, 100 - $n);
    view('founding', compact('n','left'), [
        'title' => 'Founding Traveler — first 100 reviewers on RuinMyTrip',
        'description' => 'Join RuinMyTrip as one of the first 100 travelers to publish a review and earn the Founding Traveler badge. No fake members. No invented reviews.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Founding Traveler','url'=>url('founding')]],
    ]);
}

function destination_save_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $did = (int) input('destination_id');
    if (!$did || !dest_by_id($did)) redirect(rmt_return_to());
    if (!rmt_rate_ok('react', (string)$me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect(rmt_return_to());
    }
    $uid = (int) $me['id'];
    $has = q_one("SELECT 1 FROM saves WHERE user_id=? AND target_type='destination' AND target_id=?", [$uid, $did]);
    if ($has) {
        db()->prepare("DELETE FROM saves WHERE user_id=? AND target_type='destination' AND target_id=?")->execute([$uid, $did]);
    } else {
        try {
            db()->prepare("INSERT INTO saves (user_id,target_type,target_id,created_at) VALUES (?, 'destination', ?, ?)")
                ->execute([$uid, $did, date('Y-m-d H:i:s')]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        }
    }
    redirect(rmt_return_to());
}

/**
 * POST /place/save -- collect a place, or un-collect it. Same toggle shape and the same reasoning
 * as destination_save_action above: places are global rows shared by everyone, not user-owned
 * drafts, so rmt_can_interact()'s ownership/visibility model does not apply to them.
 *
 * Only an active place can be saved. A hidden one is not something a visitor can see, and a save
 * is a public-facing count, so it must not be possible to run one up on a page nobody can reach.
 */
function place_save_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $pid = (int) input('place_id');
    $p   = $pid ? rmt_place_by_id($pid) : null;      // any place a visitor can see, closed included

    // Nothing posted means the place's own page, which is where the button lives.
    $return = rmt_return_to($p ? rmt_place_path($p) : '/');

    if (!$p) redirect($return);

    if (!rmt_rate_ok('react', (string) $me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect($return);
    }

    $uid = (int) $me['id'];
    if (rmt_place_is_saved($pid, $uid)) {
        db()->prepare('DELETE FROM saves WHERE user_id=? AND target_type=? AND target_id=?')
            ->execute([$uid, RMT_SAVE_PLACE, $pid]);
        flash('Removed from your saved places.');
    } else {
        // The primary key (user_id,target_type,target_id) already makes a second row impossible.
        // A double-tap -- easy on mobile -- races two requests that both read "not saved"; the
        // loser must land on the same end state as the winner, not on a 500.
        try {
            db()->prepare('INSERT INTO saves (user_id,target_type,target_id,created_at) VALUES (?,?,?,?)')
                ->execute([$uid, RMT_SAVE_PLACE, $pid, date('Y-m-d H:i:s')]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        }
        flash('Saved. Find it any time under Saved.');
    }
    redirect($return);
}

/**
 * GET /saved -- everything this user has collected, in one place.
 *
 * Three groups because they answer three different questions: places are specific spots to go to,
 * destinations are the older "want to visit" bucket list, and the reading list is saved writing.
 * Until now only the destination bucket had anywhere to appear (buried on the profile) and a saved
 * guide or blog post was effectively write-only -- the save button worked and the save was never
 * shown back to the person who made it.
 *
 * Unpublished and deleted targets fall out via each join's status filter, so a list never links
 * to something the reader cannot open.
 */
function saved_index(array $a): void {
    require_login(); $me = current_user(); $uid = (int) $me['id'];

    $places = rmt_saved_places($uid);

    $dests = q_all("SELECT d.id, d.slug, d.name, d.country, s.created_at saved_at
                      FROM saves s JOIN destinations d ON d.id = s.target_id
                     WHERE s.user_id = ? AND s.target_type = 'destination'
                     ORDER BY COALESCE(s.created_at,'') DESC, d.name", [$uid]);

    // Each query returns the same three columns -- kind, title, and the id/slug the path is built
    // from -- so the view renders one list instead of five near-identical blocks. The path itself is
    // assembled in PHP: SQL string concatenation is not portable (`||` concatenates on Postgres and
    // SQLite but is logical OR on MySQL), and a URL built by the database is a URL nothing tests.
    $reading = [];
    $sources = [
        "SELECT 'guide' kind, g.title, g.id, g.slug, s.created_at saved_at, g.user_id
           FROM saves s JOIN guides g ON g.id = s.target_id AND g.status = 'published'
          WHERE s.user_id = ? AND s.target_type = 'guide'",
        "SELECT 'blog_post' kind, b.title, b.id, b.slug, s.created_at saved_at, b.user_id
           FROM saves s JOIN blog_posts b ON b.id = s.target_id AND b.status = 'published'
          WHERE s.user_id = ? AND s.target_type = 'blog_post'",
        "SELECT 'collection' kind, c.title, c.id, c.slug, s.created_at saved_at, c.user_id
           FROM saves s JOIN collections c ON c.id = s.target_id AND c.status = 'published'
          WHERE s.user_id = ? AND s.target_type = 'collection'",
        "SELECT 'trip' kind, t.title, t.id, t.slug, s.created_at saved_at, t.user_id
           FROM saves s JOIN trips t ON t.id = s.target_id AND t.status = 'published'
          WHERE s.user_id = ? AND s.target_type = 'trip'",
        "SELECT 'review' kind, COALESCE(NULLIF(r.title,''), r.subject_name) title, r.id, r.slug,
                s.created_at saved_at, r.user_id
           FROM saves s JOIN reviews r ON r.id = s.target_id AND r.status = 'published'
          WHERE s.user_id = ? AND s.target_type = 'review'",
        // Saving a post has worked since posts existed; the saved page just never showed them,
        // which is the version of a bug where the button lies.
        "SELECT 'post' kind, p.body title, p.id, '' slug, s.created_at saved_at, p.user_id
           FROM saves s JOIN posts p ON p.id = s.target_id AND p.status = 'published'
          WHERE s.user_id = ? AND s.target_type = 'post'",
    ];
    foreach ($sources as $sql) {
        foreach (q_all($sql, [$uid]) as $row) {
            $row['path'] = rmt_saved_path((string) $row['kind'], (int) $row['id'], (string) ($row['slug'] ?? ''));
            $reading[] = $row;
        }
    }
    // Sorted in PHP: five separate queries cannot be ordered against each other in SQL without a
    // UNION that would have to agree on column types across drivers for no real gain at this size.
    usort($reading, static fn(array $x, array $y) => strcmp((string)($y['saved_at'] ?? ''), (string)($x['saved_at'] ?? '')));
    authors_fill($reading);

    view('saved', compact('me', 'places', 'dests', 'reading'), [
        'title' => 'Saved — RuinMyTrip',
        'description' => 'The places, destinations and travel writing you have saved on RuinMyTrip.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Saved','url'=>url('saved')]],
    ]);
}

/**
 * POST /review/{id}/vote — Yelp-style useful/funny/cool. A user can hold any subset of the
 * three at once (three independent toggles), which is why this isn't routed through react_action:
 * that endpoint's tables key on (user,target_type,target_id) with no room for a vote flavor.
 */
function review_vote_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $rid  = (int) $a['id'];
    $type = (string) input('vote_type');

    if (!in_array($type, RMT_REVIEW_VOTE_TYPES, true)) redirect(rmt_return_to());
    if (!rmt_can_interact('review', $rid, $me)) redirect(rmt_return_to());

    $r = q_one('SELECT user_id FROM reviews WHERE id=?', [$rid]);
    if (!$r) redirect(rmt_return_to());
    // Voting your own review up is not a signal from another traveler — it's not one at all.
    if ((int) $r['user_id'] === (int) $me['id']) redirect(rmt_return_to());
    if (rmt_blocked_from((int) $me['id'], 'review', $rid)) redirect(rmt_return_to());

    if (!rmt_rate_ok('review_vote', (string) $me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect(rmt_return_to());
    }

    $has = q_one('SELECT 1 FROM review_votes WHERE review_id=? AND user_id=? AND vote_type=?', [$rid, (int) $me['id'], $type]);
    if ($has) {
        db()->prepare('DELETE FROM review_votes WHERE review_id=? AND user_id=? AND vote_type=?')
            ->execute([$rid, (int) $me['id'], $type]);
    } else {
        try {
            q_run('INSERT INTO review_votes (review_id,user_id,vote_type,created_at) VALUES (?,?,?,?)',
                  [$rid, (int) $me['id'], $type, date('Y-m-d H:i:s')]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
            redirect(rmt_return_to());
        }
        rmt_award_badges((int) $r['user_id']); // votes received can newly qualify the author for Elite Traveler
    }
    redirect(rmt_return_to());
}

/**
 * POST /compliment — send another traveler's profile a Yelp-style compliment. Add-only (there is
 * no "un-compliment"): a duplicate of the same type from the same sender is just a silent no-op,
 * same spirit as the report-dedup check, so double-clicking the button can't inflate the count.
 */
function compliment_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $toId = (int) input('user_id');
    $type = (string) input('type');

    if (!isset(RMT_COMPLIMENT_TYPES[$type])) redirect(rmt_return_to());
    if (!$toId || $toId === (int) $me['id']) redirect(rmt_return_to());
    if (!q_one("SELECT 1 FROM users WHERE id=? AND status='active'", [$toId])) {
        flash('That traveler is no longer available.'); redirect(rmt_return_to());
    }
    if (rmt_is_blocked((int) $me['id'], $toId)) redirect(rmt_return_to());
    if (!rmt_rate_ok('compliment', (string) $me['id'], 40, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect(rmt_return_to());
    }

    try {
        q_run('INSERT INTO compliments (from_user_id,to_user_id,type,created_at) VALUES (?,?,?,?)',
              [(int) $me['id'], $toId, $type, date('Y-m-d H:i:s')]);
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
              [$toId, 'compliment', (int) $me['id'], 'user', (int) $me['id'], date('Y-m-d H:i:s')]);
        flash('Compliment sent.');
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        flash('You already sent that compliment.');
    }
    redirect(rmt_return_to());
}

function comment_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $tt   = (string) input('target_type');
    $tid  = (int) input('target_id');
    $body = trim((string) input('body'));

    if ($body === '' || !rmt_can_interact($tt, $tid, $me)) redirect(rmt_return_to());
    // A block stopped somebody messaging you and left them free to turn up in your comments.
    if (rmt_blocked_from((int)$me['id'], $tt, $tid)) {
        flash('You cannot comment on that.');
        redirect(rmt_return_to());
    }
    // An over-limit comment used to be silently truncated at 2000 chars (mb_substr) with no
    // indication to the author -- silent data loss instead of the validation error every other
    // body-length limit in the app (trip/guide/review) gives.
    if (mb_strlen($body) > 2000) {
        flash('That comment is too long (2000 characters max). Please shorten it and try again.');
        redirect(rmt_return_to());
    }
    if (!rmt_submit_ok('comment_'.$tt.'_'.$tid, input('_submit'))) {
        flash('That comment was already posted.'); redirect(rmt_return_to());
    }
    if (!rmt_rate_ok('comment', (string)$me['id'], 30, 3600)) {
        flash('You are commenting very fast. Try again shortly.');
        redirect(rmt_return_to());
    }

    /* A reply belongs to one comment on the same thing. Anything else -- a parent from another
       post, a removed one, a reply to a reply -- is flattened into a plain comment rather than
       refused: the words are what the person came to say. */
    $parentId = (int) input('parent_id');
    if ($parentId > 0) {
        $parent = q_one("SELECT id, target_type, target_id, parent_id, status FROM comments WHERE id=?", [$parentId]);
        $parentId = ($parent && $parent['status'] === 'published' && empty($parent['parent_id'])
                     && $parent['target_type'] === $tt && (int) $parent['target_id'] === $tid)
            ? (int) $parent['id'] : 0;
    }

    q_run("INSERT INTO comments (user_id,target_type,target_id,body,status,created_at,parent_id) VALUES (?,?,?,?, 'published', ?,?)",
        [(int)$me['id'], $tt, $tid, $body, date('Y-m-d H:i:s'), $parentId ?: null]);

    // Tell the content's author someone commented (follows and compliments already notified, but
    // comments never did). Skip self-comments; @mentions in the body ping their own recipients,
    // minus the author if they were both mentioned and would get this comment notification.
    $owner = rmt_content_owner_id($tt, $tid);
    if ($owner && $owner !== (int)$me['id']) {
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
            [$owner, 'comment', (int)$me['id'], $tt, $tid, date('Y-m-d H:i:s')]);
        $href = rmt_notification_target_url($tt, $tid, $owner);
        if ($href) {
            rmt_notify_email_direct($owner, 'Somebody replied to you on RuinMyTrip',
                '@' . $me['username'] . ' replied to something you wrote.', $href);
        }
    }
    // The person actually being answered. Skipped when they wrote the thing anyway: one
    // notification for one event, not two.
    if ($parentId > 0) {
        $parentAuthor = (int) (q_one('SELECT user_id FROM comments WHERE id=?', [$parentId])['user_id'] ?? 0);
        if ($parentAuthor > 0 && $parentAuthor !== (int) $me['id'] && $parentAuthor !== $owner) {
            q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
                [$parentAuthor, 'comment', (int)$me['id'], $tt, $tid, date('Y-m-d H:i:s')]);
        }
    }
    rmt_notify_mentions($tt, $tid, (int)$me['id'], [$owner], $body);
    redirect(rmt_return_to());
}

/** POST /comment/{id}/delete — author only. Soft delete, same as reviews and trips. */
function comment_delete(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $c = q_one('SELECT * FROM comments WHERE id=?', [(int)$a['id']]);
    if (!$c) not_found();
    if ((int)$c['user_id'] !== (int)$me['id']) { forbidden('That is not your comment.'); }
    db()->prepare("UPDATE comments SET status='removed' WHERE id=?")->execute([(int)$c['id']]);
    redirect(rmt_return_to());
}

function meetup_rsvp(array $a): void {
    require_login(); csrf_check(); $me=current_user();
    if (!can_host_meetups($me)) { flash('You must be 18+ to RSVP to meetups.'); redirect('/meetup/'.(int)$a['id']); }
    $mid=(int)$a['id']; $m=q_one('SELECT * FROM meetups WHERE id=?', [$mid]); if(!$m) not_found();
    $has = q_one('SELECT 1 FROM meetup_rsvps WHERE meetup_id=? AND user_id=?', [$mid,(int)$me['id']]);
    // Withdrawing is always allowed -- including from something cancelled or in the past, because
    // being stuck on the going list of a meetup you are not attending is the annoying direction of
    // this check. Only joining is gated.
    if ($has) db()->prepare('DELETE FROM meetup_rsvps WHERE meetup_id=? AND user_id=?')->execute([$mid,(int)$me['id']]);
    else {
        // A meetup is the one place a block has to hold physically: it puts the two of them in
        // the same spot. Withdrawing above is still allowed; only joining is refused.
        if (rmt_blocked_from((int)$me['id'], 'meetup', $mid)) {
            flash('You cannot RSVP to that meetup.'); redirect('/meetup/'.$mid);
        }
        if ($m['status'] === 'cancelled') { flash('That meetup was cancelled.'); redirect('/meetup/'.$mid); }
        if (rmt_meetup_is_past($m))       { flash('That meetup has already happened.'); redirect('/meetup/'.$mid); }
        // Capacity was a published number nothing enforced: a meetup that said 8 accepted forty.
        // Checked here rather than only in the template, because the button is not the door.
        if (rmt_meetup_is_full($m))       { flash('That meetup is full.'); redirect('/meetup/'.$mid); }
        // Same double-click race as react_action/follow_action: the (meetup_id,user_id) primary
        // key stops a duplicate RSVP row, but without this catch the loser of the race got an
        // uncaught PDOException (500 page) instead of a no-op. This one was missed when the other
        // three toggle actions were fixed.
        try {
            db()->prepare("INSERT INTO meetup_rsvps (meetup_id,user_id,status) VALUES (?,?, 'going')")->execute([$mid,(int)$me['id']]);
            // The host had no way of knowing anyone had signed up. Only on a new row, so the
            // loser of a double-tap race does not send a second notification for one RSVP.
            rmt_meetup_notify([(int)$m['host_id']], 'meetup_rsvp', (int)$me['id'], $mid);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        }
    }
    redirect('/meetup/'.$mid);
}

/* ---------- auth ---------- */
function login_form(array $a): void {
    if (is_logged_in()) redirect('/feed');
    view('auth/login', ['errors'=>[], 'return'=>rmt_safe_return_path((string) input('return'))], ['title'=>'Sign in — RuinMyTrip']);
}
function login_submit(array $a): void {
    csrf_check();
    $email = input('email');
    $return = rmt_safe_return_path((string) input('return'));
    // Two limits: per-IP stops broad credential stuffing, per-email stops a targeted attack on
    // one account from a botnet. Either tripping blocks the attempt.
    if (!rmt_rate_ok('login_ip', rmt_client_ip(), 20, 900) || !rmt_rate_ok('login_email', $email, 10, 900)) {
        $mins = (int)ceil(rmt_rate_retry_after(900) / 60);
        view('auth/login', ['errors'=>["Too many sign-in attempts. Try again in about {$mins} minute(s)."], 'return'=>$return],
             ['title'=>'Sign in — RuinMyTrip']); return;
    }
    // A logged-out visit to a protected route redirects here with ?return= set (see
    // require_login()) so signing in lands back where the user was actually headed, not always
    // on /feed -- most noticeable on mobile, where re-finding a page by hand is worse.
    if (attempt_login($email, input('password'))) {
        // Only interesting when it was a review the person was trying to reach.
        if (str_contains($return, '/review/new')) {
            rmt_track('review_login_completed');
            rmt_track('review_return_after_auth');
        }
        flash('Welcome back.');
        redirect($return);
    }
    view('auth/login', ['errors'=>['Incorrect email or password.'], 'return'=>$return], ['title'=>'Sign in — RuinMyTrip']);
}
/**
 * GET /register
 *
 * Carries `return` the way /login does. Somebody who clicked "Write a review" on a place page and
 * had no account was being handed a signup form that had forgotten which place they meant, and
 * landed on the email-verification page afterwards with no way back to it. Intent that survives a
 * sign-in and not a sign-up is intent lost for exactly the people we most need: the ones who have
 * never written a review here before.
 */
function register_form(array $a): void {
    if (is_logged_in()) redirect('/feed');
    view('auth/register', ['errors' => [], 'return' => rmt_safe_return_path((string) input('return'))],
         ['title' => 'Join RuinMyTrip']);
}
function register_submit(array $a): void {
    csrf_check();
    $return = rmt_safe_return_path((string) input('return'));
    if (!rmt_rate_ok('register_ip', rmt_client_ip(), 5, 3600)) {
        view('auth/register', ['errors'=>['Too many accounts created from this connection. Try again later.'], 'return'=>$return],
             ['title'=>'Join RuinMyTrip']); return;
    }
    $r = register_user(input('username'), input('email'), input('password'), input('birthdate'));
    if ($r['ok']) {
        $mailed = (bool) ($r['mail_ok'] ?? false);
        // Somebody who came here to write a review goes back to writing it. Publishing still needs
        // a confirmed email, and the flash says so rather than the redirect silently deciding it:
        // being bounced to a verification page you did not ask for, from a form you were halfway
        // through, is how a first review stops being written.
        $heading = $mailed
            ? 'Welcome to RuinMyTrip. Check your email to confirm your address.'
            : 'Welcome to RuinMyTrip. We could not send the confirmation email — request a new link from /verify-email.';
        if ($return !== '' && $return !== '/feed') {
            if (str_contains($return, '/review/new')) {
                rmt_track('review_signup_completed');
                rmt_track('review_return_after_auth');
            }
            flash($heading . ' You can write now and save a draft; confirming lets you publish.');
            redirect($return);
        }
        flash($heading);
        redirect('/verify-email');
    }
    view('auth/register', ['errors'=>$r['errors'], 'return'=>$return], ['title'=>'Join RuinMyTrip']);
}
function logout_action(array $a): void { logout(); flash('Signed out.'); redirect('/'); }

/* ---------- email verification ---------- */

/** GET /verify-email — with ?token= consumes it; without, shows the "check your inbox" page. */
/**
 * GET /verify-email
 *
 * With ?token=, this ONLY validates the token and renders a confirm page — it does NOT consume
 * it. A GET must be safe: email security scanners and link-preview bots (Gmail, Outlook Safe
 * Links, corporate proxies) prefetch every URL in a message, so a GET that burned a single-use
 * token verified the account as the BOT and left the human with "invalid or expired". The
 * destructive step is POST /verify-email/confirm, which bots do not issue. Reproduced on prod:
 * a GoogleImageProxy GET consumed the token before the user clicked.
 */
function verify_email(array $a): void {
    $raw = (string) input('token');
    if ($raw === '') {
        $me = current_user();
        view('auth/verify_notice', ['me'=>$me, 'verified'=>email_is_verified($me)],
             ['title'=>'Confirm your email — RuinMyTrip']);
        return;
    }
    $row = rmt_token_lookup($raw, 'verify');
    if (!$row) {
        // Token missing/used/expired. If it was already used the account is likely verified, so
        // point them at sign-in rather than a dead-end error.
        view('auth/verify_notice', ['me'=>current_user(), 'verified'=>false,
             'errors'=>['This confirmation link has already been used or has expired. '
                      . 'If you already confirmed, just sign in. Otherwise request a new link below.']],
             ['title'=>'Confirm your email — RuinMyTrip']);
        return;
    }
    // Valid token — show a one-click confirm page. Nothing is consumed on GET.
    view('auth/verify_confirm', ['token'=>$raw, 'email'=>$row['email'] ?? null],
         ['title'=>'Confirm your email — RuinMyTrip']);
}

/** POST /verify-email/confirm — the actual, human-triggered verification. */
function verify_email_confirm(array $a): void {
    csrf_check();
    $raw = (string) input('token');
    $row = rmt_token_lookup($raw, 'verify');
    if (!$row) {
        view('auth/verify_notice', ['me'=>current_user(), 'verified'=>false,
             'errors'=>['This confirmation link has already been used or has expired. '
                      . 'If you already confirmed, just sign in. Otherwise request a new link below.']],
             ['title'=>'Confirm your email — RuinMyTrip']);
        return;
    }
    db()->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), (int)$row['user_id']]);
    rmt_token_consume((int)$row['id']);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$row['user_id'];
    flash('Email confirmed. Welcome to RuinMyTrip.');
    redirect('/welcome');
}

/** POST /verify-email/resend */
function verify_email_resend(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    if (email_is_verified($me)) { flash('Your email is already confirmed.'); redirect('/feed'); }
    if (!rmt_rate_ok('verify_resend', (string)$me['email'], 3, 3600)) {
        flash('Too many emails requested. Try again in an hour.'); redirect('/verify-email');
    }
    [$ok, $detail] = send_verification_email($me);
    flash($ok ? 'Confirmation email sent. Check your inbox.'
              : 'We could not send that email right now. Please try again shortly.');
    redirect('/verify-email');
}

/* ---------- password reset ---------- */

function forgot_form(array $a): void {
    view('auth/forgot', ['errors'=>[], 'sent'=>false], ['title'=>'Reset your password — RuinMyTrip']);
}

/**
 * POST /forgot-password
 * Always renders the same "if that address exists, we sent a link" result — revealing whether
 * an email is registered would leak membership, which for a travel/meetup product is a privacy
 * problem, not just an auth one.
 */
function forgot_submit(array $a): void {
    csrf_check();
    $email = strtolower(trim(input('email')));
    $allowed = rmt_rate_ok('forgot_ip', rmt_client_ip(), 10, 3600)
            && rmt_rate_ok('forgot_email', $email, 3, 3600);
    if ($allowed && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($u && $u['status'] !== 'suspended') send_password_reset_email($u);
    }
    view('auth/forgot', ['errors'=>[], 'sent'=>true], ['title'=>'Reset your password — RuinMyTrip']);
}

function reset_form(array $a): void {
    $raw = (string) input('token');
    $row = rmt_token_lookup($raw, 'reset');
    view('auth/reset', ['token'=>$raw, 'valid'=>(bool)$row, 'errors'=>[]],
         ['title'=>'Choose a new password — RuinMyTrip']);
}

function reset_submit(array $a): void {
    csrf_check();
    $raw = (string) input('token');
    $row = rmt_token_lookup($raw, 'reset');
    if (!$row) {
        view('auth/reset', ['token'=>$raw, 'valid'=>false, 'errors'=>['That reset link is invalid or has expired.']],
             ['title'=>'Choose a new password — RuinMyTrip']); return;
    }
    $pw = (string) input('password');
    $pw2 = (string) input('password_confirm');
    $errors = [];
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== $pw2)    $errors[] = 'Those passwords do not match.';
    if ($errors) {
        view('auth/reset', ['token'=>$raw, 'valid'=>true, 'errors'=>$errors],
             ['title'=>'Choose a new password — RuinMyTrip']); return;
    }
    $uid = (int) $row['user_id'];
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($pw, PASSWORD_BCRYPT), $uid]);
    rmt_token_consume((int)$row['id']);
    rmt_token_burn_all($uid, 'reset');   // any other outstanding reset links die with this one

    // Completing a reset proves control of the mailbox, so it also confirms the address.
    db()->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $uid]);

    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    flash('Password updated. You are signed in.');
    redirect('/feed');
}

function settings_form(array $a): void {
    // /settings predates /u/{username}/edit and is still linked from older pages. Keep it working
    // by sending it to the canonical editor rather than maintaining two forms that can drift.
    require_login();
    redirect('/u/'.current_user()['username'].'/edit');
}
function settings_save(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $v = rmt_profile_validate($_POST);
    if (!$v['ok']) {
        view('profile_edit', ['me'=>$me, 'errors'=>$v['errors'], 'p'=>array_merge($me, $_POST)],
             ['title'=>'Edit your profile — RuinMyTrip']); return;
    }
    $d = $v['data'];
    db()->prepare('UPDATE profiles SET display_name=?, bio=?, home_city=?, avatar_url=? WHERE user_id=?')
        ->execute([$d['display_name'], $d['bio'], $d['home_city'], $d['avatar_url'], (int)$me['id']]);
    flash('Profile updated.'); redirect('/u/'.$me['username']);
}

/* ---------- report & admin ---------- */
/**
 * Reportable content types -> the table each lives in. An allow-list, not a free-text field:
 * target_type reaching a table name must never be attacker-controlled.
 */
const RMT_REPORT_TARGETS = [
    'review'     => 'reviews',
    'trip'       => 'trips',
    'guide'      => 'guides',
    'blog_post'  => 'blog_posts',
    'meetup'     => 'meetups',
    'comment'    => 'comments',
    'user'       => 'users',
    'collection' => 'collections',
    'post'       => 'posts',
];
const RMT_REPORT_REASONS = ['abuse', 'spam', 'misinformation', 'unsafe', 'off_topic', 'other'];

function report_form(array $a): void {
    require_login();
    view('report', ['tt'=>input('target_type'),'tid'=>input('target_id'),'errors'=>[]],
         ['title'=>'Report content — RuinMyTrip']);
}

/**
 * POST /report
 *
 * A report queue is only useful if what lands in it is real. Without these checks the queue is
 * trivially floodable: before this, one account filed 31 reports on the same review, plus a
 * report against a nonexistent id and one with an invented target_type.
 */
function report_submit(array $a): void {
    require_login(); csrf_check(); $me = current_user();

    $tt     = (string) input('target_type');
    $tid    = (int) input('target_id');
    $reason = (string) input('reason');
    $details= trim((string) input('details'));
    $errors = [];

    if (!isset(RMT_REPORT_TARGETS[$tt])) $errors[] = 'That is not something you can report.';
    if (!in_array($reason, RMT_REPORT_REASONS, true)) $errors[] = 'Choose a reason for the report.';
    if (mb_strlen($details) > 2000) $errors[] = 'Please keep the details under 2000 characters.';

    // The target must actually exist — a queue full of reports against nothing wastes the one
    // resource moderation has, which is attention.
    if (!$errors) {
        $table = RMT_REPORT_TARGETS[$tt];
        if (!q_one("SELECT 1 FROM {$table} WHERE id = ?", [$tid])) {
            $errors[] = 'That content no longer exists.';
        }
    }

    // You cannot report your own content, and you cannot report the same thing twice while the
    // first report is still open.
    if (!$errors && $tt !== 'user') {
        $table = RMT_REPORT_TARGETS[$tt];
        $owner = q_one("SELECT user_id FROM {$table} WHERE id = ?", [$tid]);
        if ($owner && (int)($owner['user_id'] ?? 0) === (int)$me['id']) {
            $errors[] = 'You cannot report your own content. Edit or delete it instead.';
        }
    }
    if (!$errors && $tt === 'user' && $tid === (int)$me['id']) {
        $errors[] = 'You cannot report yourself.';
    }
    if (!$errors) {
        $dupe = q_one("SELECT 1 FROM reports WHERE reporter_id=? AND target_type=? AND target_id=? AND status='open'",
                      [(int)$me['id'], $tt, $tid]);
        if ($dupe) $errors[] = 'You have already reported this. Our moderators are looking at it.';
    }

    // Rate limit regardless of outcome, so probing for what exists is also throttled.
    if (!rmt_rate_ok('report', (string)$me['id'], 10, 3600)) {
        $errors[] = 'You have sent a lot of reports. Try again later.';
    }

    if ($errors) {
        view('report', ['tt'=>$tt, 'tid'=>$tid, 'errors'=>$errors],
             ['title'=>'Report content — RuinMyTrip']); return;
    }

    q_run("INSERT INTO reports (reporter_id,target_type,target_id,reason,details,status,created_at)
           VALUES (?,?,?,?,?, 'open', ?)",
        [(int)$me['id'], $tt, $tid, $reason, $details ?: null, date('Y-m-d H:i:s')]);
    flash('Thanks — our moderators will review this.');
    redirect('/');
}

function admin_dashboard(array $a): void {
    require_role('admin','mod');
    $reports = q_all("SELECT r.*, u.username reporter FROM reports r JOIN users u ON u.id=r.reporter_id
                      WHERE r.status='open' ORDER BY r.id DESC");
    $stats = [
        'users'=>(int)(q_one('SELECT COUNT(*) c FROM users')['c']??0),
        'trips'=>(int)(q_one('SELECT COUNT(*) c FROM trips')['c']??0),
        'reviews'=>(int)(q_one('SELECT COUNT(*) c FROM reviews')['c']??0),
        'meetups'=>(int)(q_one('SELECT COUNT(*) c FROM meetups')['c']??0),
        'open_reports'=>count($reports),
    ];
    view('admin', compact('reports','stats'), ['title'=>'Moderation — RuinMyTrip']);
}
/* ===========================================================================
 * Admin place editor
 * ======================================================================== */

/** GET /admin/places — everything an editor might want to fill in, with a filter. */
function admin_places_index(array $a): void {
    require_role('admin', 'mod');
    $q = trim((string) input('q'));
    $rows = rmt_admin_places($q);
    foreach ($rows as &$r) $r['completeness'] = rmt_place_completeness($r);
    unset($r);
    view('admin_places', ['rows' => $rows, 'q' => $q,
                          'coverage' => rmt_place_coverage(),
                          'refusals' => rmt_enrichment_refusals(),
                          'stale'    => rmt_stale_places(180, 50)],
         ['title' => 'Places — RuinMyTrip admin']);
}

/**
 * GET /admin/search — what people looked for and what they found.
 *
 * Internal. A query that returns nothing is the clearest statement a traveler can make about what
 * this site does not have yet, and it costs nothing to listen to. Nothing here identifies anybody:
 * the log holds a normalised query, a count and a timestamp.
 */
function admin_search_report(array $a): void {
    require_role('admin', 'mod');
    $days = max(1, min(365, (int) (input('days') ?: 90)));
    view('admin_search', [
        'days'     => $days,
        'zero'     => rmt_search_zero_results($days, 60),
        'low'      => rmt_search_low_results($days, 30, 2),
        'top'      => q_all("SELECT query_norm, COUNT(*) searches, MAX(result_count) best
                               FROM search_log WHERE result_count >= 0 AND created_at >= ?
                              GROUP BY query_norm ORDER BY searches DESC LIMIT 25",
                            [date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]),
        'clicks'   => q_all("SELECT clicked_type, COUNT(*) clicks, ROUND(AVG(clicked_position * 1.0), 2) avg_position
                               FROM search_log WHERE clicked_type IS NOT NULL AND created_at >= ?
                              GROUP BY clicked_type ORDER BY clicks DESC",
                            [date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]),
        'total'    => (int) (q_one('SELECT COUNT(*) c FROM search_log WHERE created_at >= ?',
                            [date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))])['c'] ?? 0),
    ], ['title' => 'Search — RuinMyTrip admin']);
}

/**
 * GET /admin/destinations — where the data actually is.
 *
 * Internal. This is the view that will eventually decide which destinations can carry a category
 * landing page without it being thin, so it counts the things that make a page worth having:
 * places by kind, how many are located, how many neighborhoods emerged, community reviews, and
 * when somebody last wrote one.
 */
function admin_destinations_report(array $a): void {
    require_role('admin', 'mod');
    $rows = rmt_destination_quality(400);
    foreach ($rows as &$r) {
        foreach (['places','hotels','restaurants','attractions','located','neighborhoods','reviews',
                  'reviewers','places_reviewed','places_rankable','photos'] as $k) {
            $r[$k] = (int) ($r[$k] ?? 0);
        }
        // A destination is "ready" when there is enough to fill a discovery page honestly: places
        // across more than one kind, coordinates on most of them, and reviews behind them.
        $kinds = ($r['hotels'] > 0 ? 1 : 0) + ($r['restaurants'] > 0 ? 1 : 0) + ($r['attractions'] > 0 ? 1 : 0);
        $r['ready'] = $r['places'] >= 5 && $kinds >= 2 && $r['reviews'] >= 5
                      && $r['located'] >= (int) ceil($r['places'] * 0.6);
        // Separately: is there enough COMMUNITY here for a ranked page to say anything? Three
        // rankable places from at least three different people. Place data readiness and community
        // readiness are different questions and a destination can pass one and fail the other --
        // today every destination does exactly that.
        $r['community_ready'] = $r['places_rankable'] >= 3 && $r['reviewers'] >= 3;
    }
    unset($r);
    usort($rows, static fn($x, $y) => [$y['ready'], $y['places']] <=> [$x['ready'], $x['places']]);
    view('admin_destinations', ['rows' => $rows], ['title' => 'Destinations — RuinMyTrip admin']);
}

/**
 * GET /admin/funnel — where people give up between wanting to write and having written.
 *
 * Internal. Counts attempts, not people: nothing here can name anybody, and the questions it
 * answers do not need it to.
 */
/**
 * GET /admin/seo - what is in the index, what is not, and why not.
 *
 * Built so expansion is a decision made against a number rather than a guess. Every row comes from
 * the same rmt_indexable() the sitemap and the page's robots tag use, so this view cannot show a
 * verdict that differs from the one crawlers actually get.
 */
/** GET /about - what this site is, in the site's own words, with live numbers. */
function page_about(array $a): void {
    $one = static fn(string $sql, array $args = []): int => (int) (q_one($sql, $args)['c'] ?? 0);
    $counts = [
        'destinations' => $one('SELECT COUNT(*) c FROM destinations'),
        'places'       => $one("SELECT COUNT(*) c FROM places WHERE status = 'active'"),
        // The number that matters, counted the same way every other honest count on this site is:
        // published, and not ours.
        'community_reviews' => $one("SELECT COUNT(*) c FROM reviews r JOIN users u ON u.id = r.user_id
                                      WHERE r.status = 'published' AND u.role <> ?", [RMT_EDITORIAL_ROLE]),
        'reviewers' => $one("SELECT COUNT(DISTINCT r.user_id) c FROM reviews r JOIN users u ON u.id = r.user_id
                              WHERE r.status = 'published' AND u.role <> ?", [RMT_EDITORIAL_ROLE]),
    ];
    view('legal/about', ['counts' => $counts], [
        'title' => 'About RuinMyTrip - what this site is and what it promises',
        'description' => 'A travel discovery and review community built on honest traveler experiences. What we promise, what we are not, and how editorial content is kept separate.',
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'About', 'url' => url('about')]],
    ]);
}

/** GET /contact - where to send what, with the in-product routes first. */
function page_contact(array $a): void {
    view('legal/contact', ['errors' => [], 'sent' => false], [
        'title' => 'Contact RuinMyTrip',
        'description' => 'How to report a wrong address or opening hours, report a review, tell us about a missing place, or reach us about anything else.',
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Contact', 'url' => url('contact')]],
    ]);
}

/**
 * GET /p/<slug>/correct - tell us something on this place page is wrong.
 *
 * No account needed. A correction we never hear about because the form wanted a login is worse for
 * the next reader than one we hear about anonymously and have to check ourselves.
 */
function place_correct_form(array $a): void {
    $p = rmt_place_by_slug((string) $a['slug']);
    if (!$p || $p['status'] !== 'active') { not_found(); return; }
    view('place_correct', ['p' => $p, 'errors' => [], 'sent' => false], [
        'title' => 'Suggest a correction to ' . $p['name'] . ' - RuinMyTrip',
        'description' => 'Tell us if the address, opening hours or details for ' . $p['name'] . ' are wrong.',
        // A form is not a page anybody should arrive at from a search result.
        'robots' => rmt_robots_for(rmt_indexable('filter')),
        'canonical' => url(ltrim(rmt_place_path($p), '/')),
    ]);
}

/** POST /p/<slug>/correct */
function place_correct_submit(array $a): void {
    csrf_check();
    $p = rmt_place_by_slug((string) $a['slug']);
    if (!$p || $p['status'] !== 'active') { not_found(); return; }
    $me = current_user();

    // Rate limited by connection rather than by account, because the form deliberately does not
    // need one. Generous: a person correcting several places in a sitting is doing us a favour.
    if (!rmt_rate_ok('feedback', $me ? (string) $me['id'] : (string) ($_SERVER['REMOTE_ADDR'] ?? 'anon'), 20, 3600)) {
        view('place_correct', ['p' => $p, 'sent' => false,
                               'errors' => ['That is a lot of corrections at once. Try again shortly.']], [
            'title' => 'Suggest a correction - RuinMyTrip', 'robots' => 'noindex,follow']);
        return;
    }

    $r = rmt_feedback_submit((string) input('kind'), (int) $p['id'], (string) input('message'),
                             $me ? (int) $me['id'] : null, (string) input('contact_email'));
    if (!$r['ok']) {
        view('place_correct', ['p' => $p, 'sent' => false, 'errors' => [$r['error']]], [
            'title' => 'Suggest a correction - RuinMyTrip', 'robots' => 'noindex,follow']);
        return;
    }
    view('place_correct', ['p' => $p, 'errors' => [], 'sent' => true], [
        'title' => 'Thank you - RuinMyTrip', 'robots' => 'noindex,follow']);
}

/** POST /contact - the general form, for things that are not about one place. */
function page_contact_submit(array $a): void {
    csrf_check();
    $me = current_user();
    if (!rmt_rate_ok('feedback', $me ? (string) $me['id'] : (string) ($_SERVER['REMOTE_ADDR'] ?? 'anon'), 20, 3600)) {
        view('legal/contact', ['errors' => ['That is a lot of messages at once. Try again shortly.'], 'sent' => false],
             ['title' => 'Contact RuinMyTrip', 'robots' => 'noindex,follow']);
        return;
    }
    $kind = (string) input('kind');
    if (in_array($kind, RMT_FEEDBACK_PLACE_KINDS, true)) $kind = 'general';   // no place attached here
    $r = rmt_feedback_submit($kind, null, (string) input('message'),
                             $me ? (int) $me['id'] : null, (string) input('contact_email'));
    view('legal/contact', ['errors' => $r['ok'] ? [] : [$r['error']], 'sent' => $r['ok']],
         ['title' => 'Contact RuinMyTrip', 'robots' => 'noindex,follow']);
}

/** GET /admin/feedback - the queue. */
function admin_feedback(array $a): void {
    require_role('admin', 'mod');
    $status = (string) (input('status') ?: 'pending');
    if (!in_array($status, RMT_FEEDBACK_STATUSES, true)) $status = 'pending';
    view('admin_feedback', [
        'rows'    => rmt_feedback_queue($status),
        'status'  => $status,
        'pending' => rmt_feedback_pending_count(),
    ], ['title' => 'Feedback and corrections - RuinMyTrip', 'robots' => 'noindex,follow']);
}

/** POST /admin/feedback/resolve - close one, without touching the place. */
function admin_feedback_resolve(array $a): void {
    require_role('admin', 'mod');
    csrf_check();
    $me = current_user();
    $ok = rmt_feedback_resolve((int) input('id'), (int) $me['id'],
                               (string) input('status'), (string) input('note'));
    flash($ok ? 'Marked. The place itself is unchanged -- edit it if the correction was right.'
              : 'Could not update that item.');
    redirect('/admin/feedback');
}

function admin_seo(array $a): void {
    require_role('admin', 'mod');

    $groups = [];
    $groups['destinations'] = [
        'label' => 'Destinations',
        'rule'  => 'Indexable with at least ' . RMT_IDX_DEST_MIN_PLACES . ' place, or editorial written about it.',
        'rows'  => array_map(static fn(array $r) => [
            'label' => $r['name'], 'metric' => $r['place_count'] . ' places', 'verdict' => $r['verdict'],
        ], rmt_index_destinations()),
    ];
    $groups['categories'] = [
        'label' => 'Category landing pages',
        'rule'  => 'Indexable at ' . RMT_IDX_CAT_MIN_PLACES . '+ places of that kind in the city.',
        'rows'  => array_map(static fn(array $r) => [
            'label' => rmt_category_heading((string) $r['type'], (string) $r['dest_name']),
            'metric' => $r['place_count'] . ' places', 'verdict' => $r['verdict'],
        ], rmt_index_categories()),
    ];
    $groups['neighborhoods'] = [
        'label' => 'Neighborhoods',
        'rule'  => 'Indexable at ' . RMT_IDX_NB_MIN_PLACES . '+ places and ' . RMT_IDX_NB_MIN_TYPES . '+ kinds. Boroughs never.',
        'rows'  => array_map(static fn(array $r) => [
            'label' => $r['name'] . ', ' . $r['dest_name'],
            'metric' => $r['place_count'] . ' places / ' . $r['type_count'] . ' kinds',
            'verdict' => $r['verdict'],
        ], rmt_index_neighborhoods()),
    ];
    $groups['profiles'] = [
        'label' => 'Profiles',
        'rule'  => 'Indexable once the traveler has published something.',
        'rows'  => array_map(static fn(array $r) => [
            'label' => '@' . $r['username'],
            'metric' => ((int) $r['review_count'] + (int) $r['guide_count'] + (int) $r['trip_count'] + (int) $r['list_count']) . ' published',
            'verdict' => $r['verdict'],
        ], rmt_index_profiles()),
    ];
    $groups['lists'] = [
        'label' => 'Public lists',
        'rule'  => 'Indexable at ' . RMT_IDX_LIST_MIN_ITEMS . '+ items with a description.',
        'rows'  => array_map(static fn(array $r) => [
            'label' => $r['title'], 'metric' => $r['item_count'] . ' items', 'verdict' => $r['verdict'],
        ], rmt_index_lists()),
    ];
    $places = rmt_index_places();
    $groups['places'] = [
        'label' => 'Places',
        'rule'  => 'Indexable when we hold something useful about them. Community reviews are NOT required.',
        'rows'  => array_map(static fn(array $r) => [
            'label' => $r['name'], 'metric' => '', 'verdict' => $r['verdict'],
        ], $places),
    ];

    $sitemap = rmt_sitemap_parts();
    // Where the editorial work should go next, on the page that already answers "what is indexed".
    // The two questions are the same one asked twice: a destination is worth deepening for the same
    // reasons its pages are worth indexing.
    $depth = array_slice(rmt_destination_depth(), 0, 12);
    view('admin_seo', [
        'depth'     => $depth,
        'groups'    => $groups,
        'sitemap'   => $sitemap,
        'totalUrls' => array_sum(array_map(static fn(array $r) => (int) $r['url_count'], $sitemap)),
        'generated' => $sitemap ? (string) $sitemap[0]['generated_at'] : null,
    ], ['title' => 'SEO readiness - RuinMyTrip', 'robots' => 'noindex,follow']);
}

function admin_funnel(array $a): void {
    require_role('admin', 'mod');
    $days = (int) (input('days') !== '' ? input('days') : 30);
    if (!in_array($days, [1, 7, 30, 0], true)) $days = 30;
    view('admin_funnel', [
        'days'      => $days,
        'board'     => rmt_community_scoreboard(),
        'steps'     => rmt_funnel_steps($days),
        'byAuth'    => rmt_funnel_by_auth($days),
        'bySource'  => rmt_funnel_by_source($days),
        'failures'  => rmt_funnel_failures($days),
        'counts'    => rmt_funnel_counts($days),
    ], ['title' => 'Contribution funnel — RuinMyTrip admin']);
}

/**
 * GET /admin/moderation — the queue, with enough of each item to decide on it.
 *
 * Reports about the same thing are grouped: five reports about one review is one decision. The
 * count is information, never a rule.
 */
function admin_moderation(array $a): void {
    require_role('admin', 'mod');
    view('admin_moderation', [
        'queue'   => rmt_moderation_queue(100),
        'history' => rmt_moderation_history(40),
    ], ['title' => 'Moderation queue — RuinMyTrip admin']);
}

/**
 * POST /admin/moderation/act — act on something without a report attached.
 *
 * A moderator who finds spam themselves should not have to report it first in order to remove it.
 * Same single path, same log.
 */
function admin_moderation_act(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $res = rmt_moderate((int) current_user()['id'], (string) input('target_type'),
                        (int) input('target_id'), (string) input('action'), null, (string) input('note'));
    flash($res['ok'] ? 'Done, and recorded.' : ($res['error'] ?? 'That could not be done.'));
    redirect('/admin/moderation');
}

/** GET /admin/suggestions — places travelers have asked us to add. */
function admin_suggestions(array $a): void {
    require_role('admin', 'mod');
    $rows = q_all("SELECT ps.*, u.username FROM place_suggestions ps
                    LEFT JOIN users u ON u.id = ps.suggested_by
                    ORDER BY (ps.status = 'pending') DESC, ps.created_at DESC LIMIT 200");
    view('admin_suggestions', ['rows' => $rows], ['title' => 'Suggested places — RuinMyTrip admin']);
}

/**
 * POST /admin/suggestions/resolve — mark one handled.
 *
 * Deliberately does NOT create the place. Adding it is a decision with a destination, a type, a
 * name and a dedupe check behind it, and that is what the place editor is for; a one-click "accept"
 * here would be exactly the unreviewed entity creation the queue exists to prevent.
 */
function admin_suggestions_resolve(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $id = (int) input('id');
    $status = (string) input('status');
    if (!in_array($status, ['pending', 'added', 'rejected', 'duplicate'], true)) $status = 'pending';
    q_run('UPDATE place_suggestions SET status = ?, resolved_by = ?, resolved_at = ? WHERE id = ?',
          [$status, (int) current_user()['id'], date('Y-m-d H:i:s'), $id]);
    flash('Suggestion marked ' . $status . '.');
    redirect('/admin/suggestions');
}

/** GET /admin/place/{id} — the editor. */
function admin_place_form(array $a): void {
    require_role('admin', 'mod');
    $p = rmt_place_by_id((int) $a['id']);
    if (!$p) not_found();
    admin_place_render($p, [], []);
}

/** Shared render so a failed save comes back with the values that were typed, not a blank form. */
function admin_place_render(array $p, array $errors, array $posted): void {
    $id = (int) $p['id'];
    view('admin_place_edit', [
        'p'          => $posted ? array_merge($p, $posted) : $p,
        'orig'       => $p,
        'errors'     => $errors,
        'categories' => rmt_place_categories_for((string) $p['type']),
        'grid'       => rmt_admin_hours_grid($id),
        'photos'     => q_all("SELECT * FROM place_photos WHERE place_id = ? ORDER BY is_cover DESC, sort, id", [$id]),
        'reviewPhotos' => q_all("SELECT rp.id, rp.url, rp.storage_key, rp.caption
                                   FROM review_photos rp JOIN reviews r ON r.id = rp.review_id
                                  WHERE r.place_id = ? AND r.status = 'published'
                                  ORDER BY rp.id DESC LIMIT 24", [$id]),
    ], ['title' => 'Edit ' . $p['name'] . ' — RuinMyTrip admin']);
}

/**
 * POST /admin/place/{id} — save attributes and hours together.
 *
 * Both halves are validated before either is written, and the write runs in one transaction, so a
 * bad closing time cannot leave an address saved and the hours half-applied.
 */
function admin_place_save(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $p = rmt_place_by_id((int) $a['id']);
    if (!$p) not_found();
    $id = (int) $p['id'];

    $hours = rmt_admin_parse_hours_grid((array) ($_POST['hours'] ?? []));
    $errors = $hours['errors'];

    $name = trim((string) input('name'));
    $renameTo = ($name !== '' && $name !== $p['name']) ? $name : null;

    $attrs = [];
    foreach (['street_address','neighborhood','region','postal_code','phone','website_url',
              'timezone','data_source','data_source_url','lat','lng','price_level','category_id'] as $f) {
        if (array_key_exists($f, $_POST)) $attrs[$f] = $_POST[$f];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $attrErrors = rmt_place_update_attributes($id, $attrs);
        $errors = array_merge($errors, array_values($attrErrors));
        if (!$errors) {
            $hoursErrors = rmt_place_set_hours($id, $hours['intervals']);
            $errors = array_merge($errors, array_values($hoursErrors));
        }
        // Closing a place is an editor's deliberate act, never a consequence of a report. The
        // correction queue can tell us a restaurant has shut; only this line acts on it, and only
        // when a person chose the value.
        if (!$errors && array_key_exists('status', $_POST)) {
            $newStatus = rmt_place_status((string) $_POST['status']);
            if ($newStatus !== rmt_place_status((string) $p['status'])) {
                q_run('UPDATE places SET status = ?, updated_at = ? WHERE id = ?',
                      [$newStatus, date('Y-m-d H:i:s'), $id]);
            }
        }
        if (!$errors && $renameTo !== null) {
            // Renaming retires the old slug into place_slug_history, so the URL this place has been
            // published under keeps working as a 301. The row id never changes.
            rmt_place_rename($id, $renameTo);
        }
        if ($errors) { $pdo->rollBack(); }
        else { $pdo->commit(); }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    if ($errors) {
        admin_place_render($p, array_values(array_unique($errors)), $_POST);
        return;
    }
    flash('Saved.');
    redirect('/admin/place/' . $id);
}

/**
 * POST /admin/place/{id}/photo — set a cover, adopt a traveler photo, or remove one.
 *
 * Adopting a review photo stores a REFERENCE (its storage key and review_photo_id), never a copy:
 * one blob, two rows pointing at it, and deleting the review takes the reference with it.
 */
function admin_place_photo(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $p = rmt_place_by_id((int) $a['id']);
    if (!$p) not_found();
    $id = (int) $p['id'];
    $action = (string) input('do');

    if ($action === 'adopt') {
        $rp = q_one("SELECT rp.* FROM review_photos rp JOIN reviews r ON r.id = rp.review_id
                      WHERE rp.id = ? AND r.place_id = ? AND r.status = 'published'",
                    [(int) input('review_photo_id'), $id]);
        if ($rp) {
            $already = q_one('SELECT id FROM place_photos WHERE review_photo_id = ?', [(int) $rp['id']]);
            if ($already) {
                q_run('UPDATE place_photos SET is_cover = 0 WHERE place_id = ?', [$id]);
                q_run('UPDATE place_photos SET is_cover = 1 WHERE id = ?', [(int) $already['id']]);
            } else {
                rmt_place_photo_add($id, [
                    'review_photo_id' => (int) $rp['id'],
                    'storage_key' => $rp['storage_key'] ?: null,
                    'url' => $rp['url'] ?: null,
                    'caption' => $rp['caption'] ?: null,
                    'alt_text' => trim((string) input('alt_text')) ?: null,
                    'credit' => 'Traveler photo',
                    'is_cover' => true,
                    'uploaded_by' => (int) current_user()['id'],
                ]);
            }
            flash('Cover set from a traveler photo.');
        } else {
            flash('That photo does not belong to this place.');
        }
    } elseif ($action === 'cover') {
        $ph = q_one('SELECT id FROM place_photos WHERE id = ? AND place_id = ?', [(int) input('photo_id'), $id]);
        if ($ph) {
            q_run('UPDATE place_photos SET is_cover = 0 WHERE place_id = ?', [$id]);
            q_run('UPDATE place_photos SET is_cover = 1 WHERE id = ?', [(int) $ph['id']]);
            flash('Cover updated.');
        }
    } elseif ($action === 'remove') {
        $ph = q_one('SELECT * FROM place_photos WHERE id = ? AND place_id = ?', [(int) input('photo_id'), $id]);
        if ($ph) {
            // Only delete the underlying blob when this row OWNS it. A photo adopted from a review
            // shares the review's media, and destroying it would blank the review too.
            if (empty($ph['review_photo_id']) && !empty($ph['storage_key'])) {
                rmt_storage_delete((string) $ph['storage_key']);
            }
            q_run('DELETE FROM place_photos WHERE id = ?', [(int) $ph['id']]);
            flash('Photo removed.');
        }
    }
    redirect('/admin/place/' . $id);
}

function admin_resolve(array $a): void {
    require_role('admin','mod'); csrf_check(); $me = current_user();
    $rid = (int) input('report_id');
    $action = (string) input('action');
    $rep = q_one('SELECT * FROM reports WHERE id=?', [$rid]);
    if (!$rep) redirect('/admin');

    // One path for every moderation decision, so every one of them is logged. Nothing here reads
    // a report count and nothing reads a rating: volume is not a verdict and criticism is not a
    // violation.
    if (!in_array($action, RMT_MOD_ACTIONS, true)) $action = 'dismiss';
    $res = rmt_moderate((int) $me['id'], (string) $rep['target_type'], (int) $rep['target_id'],
                        $action, $rid, (string) input('note'));
    if (!$res['ok']) { flash($res['error'] ?? 'That could not be done.'); redirect('/admin'); }

    // Every open report about the same thing is settled by the one decision -- a moderator should
    // not have to press the same button five times for five reports of one review.
    db()->prepare("UPDATE reports SET status='resolved', resolved_by=?
                    WHERE status='open' AND target_type=? AND target_id=?")
        ->execute([(int)$me['id'], (string) $rep['target_type'], (int) $rep['target_id']]);

    flash(match ($action) {
        'hide'    => 'Content hidden and the reports resolved.',
        'remove'  => 'Content removed and the reports resolved.',
        'restore' => 'Content restored and the reports resolved.',
        default   => 'Reports dismissed. Nothing was changed.',
    });
    redirect('/admin/moderation');
}

/* ---------- media ---------- */
/**
 * GET /media/{key} — serve a stored file.
 *
 * Content-Type is taken from the DB (set when we re-encoded the image), never from the request,
 * and X-Content-Type-Options: nosniff stops a browser second-guessing it. Together with the
 * re-encode on upload, a stored file cannot be coerced into executing as HTML or script.
 */
function media_show(array $a): void {
    $key = (string) ($a['key'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $key)) not_found();
    $m = rmt_storage_get($key);
    if (!$m) not_found();

    header('Content-Type: ' . $m['mime']);
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'none\'; sandbox');
    header('Content-Length: ' . strlen($m['bytes']));
    // Keys are random and content-addressed in practice, so a key's bytes never change.
    header('Cache-Control: public, max-age=31536000, immutable');
    echo $m['bytes'];
}

/* ---------- admin diagnostics ---------- */
/**
 * GET /admin/mail-check — admin-only. Reports whether this container can actually send mail and
 * which transport it would use. Exposes no secret values (key length only, never the key).
 */
function admin_mail_check(array $a): void {
    require_role('admin');
    header('Content-Type: text/plain');
    foreach (rmt_mail_diagnostics() as $k => $v) {
        printf("%-16s %s
", $k, is_bool($v) ? ($v ? 'yes' : 'NO') : (string)$v);
    }
    // Optional: /admin/mail-check?smtp=1 probes whether this host can open an outbound SMTP
    // connection at all. Render is documented (and previously measured on another service) to
    // block outbound SMTP; this measures it from THIS container rather than assuming.
    if (input('smtp') === '1') {
        foreach ([['smtp.gmail.com',465],['smtp.gmail.com',587],['smtp.gmail.com',2525]] as [$h,$port]) {
            $t0 = microtime(true);
            $fp = @fsockopen($h, $port, $errno, $errstr, 8);
            $ms = (int)round((microtime(true)-$t0)*1000);
            if ($fp) { $banner = trim((string)@fgets($fp, 128)); fclose($fp);
                       printf("smtp %s:%-5d OPEN  %dms  banner=%s
", $h, $port, $ms, substr($banner,0,40)); }
            else     { printf("smtp %s:%-5d BLOCKED %dms  (%d %s)
", $h, $port, $ms, $errno, substr($errstr,0,40)); }
        }
    }

    // Optional live probe: /admin/mail-check?send=1 sends one email to the admin's own address.
    if (input('send') === '1') {
        $me = current_user();
        [$ok, $detail] = rmt_mail_send((string)$me['email'], 'RuinMyTrip mail check',
            '<p>Transport probe from production. If you received this, outbound mail works.</p>');
        printf("
%-16s %s
%-16s %s
", 'probe_sent', $ok ? 'yes' : 'NO', 'probe_detail', $detail);
    }
}

/* ---------- legal / safety ---------- */
function page_terms(array $a): void { view('legal/terms', [], ['title'=>'Terms of Service — RuinMyTrip']); }
function page_privacy(array $a): void { view('legal/privacy', [], ['title'=>'Privacy Policy — RuinMyTrip']); }
function page_guidelines(array $a): void { view('legal/guidelines', [], ['title'=>'Community Guidelines — RuinMyTrip']); }
function page_affiliate(array $a): void { view('legal/affiliate', [], ['title'=>'Affiliate Disclosure — RuinMyTrip']); }
function page_safety(array $a): void { view('legal/safety', [], ['title'=>'Meetup Safety — RuinMyTrip']); }
function page_editorial(array $a): void {
    view('legal/editorial', [], [
        'title' => 'Editorial policy — how RuinMyTrip labels its own content',
        'description' => 'RuinMyTrip publishes researched editorial reviews under one official account, always labelled, never counted in community ratings, and never presented as a traveler visit.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Editorial policy','url'=>url('editorial-policy')]],
    ]);
}

/* ---------- health check (Render) ---------- */
// Liveness only — NO DB call, so health never flaps on DB latency (that caused Render edge 404s).
function healthz(array $a): void {
    header('Content-Type: text/plain');
    echo 'ok';
}
// Separate readiness probe that DOES check the DB (for manual/diagnostic use, not the Render health path).
function readyz(array $a): void {
    header('Content-Type: text/plain');
    try { db()->query('SELECT 1'); echo 'ready db=ok'; }
    catch (Throwable $e) { http_response_code(503); echo 'db=down'; }
}

/* ---------- sitemap ---------- */
/**
 * GET /sitemap.xml - the index. Children are generated and cached; this lists them.
 *
 * Regeneration on a stale read is a fallback, not the plan: the maintenance script runs at deploy
 * and on a schedule. It exists so a crawler arriving at a cold cache gets a correct sitemap
 * rather than an empty one.
 */
function sitemap(array $a): void {
    if (rmt_sitemap_is_stale()) rmt_sitemap_generate();
    /* A crawler asking what we have is the moment to tell the ones that take a push. The cost of
       the call lands here, on a robot, rather than on a member mid-publish. */
    rmt_seo_flush_if_due();
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach (rmt_sitemap_parts() as $row) {
        echo '  <sitemap><loc>'
           . e(url(rmt_sitemap_filename((string) $row['group_key'], (int) $row['part'])))
           . '</loc>';
        $mod = rmt_sitemap_day((string) $row['generated_at']);
        if ($mod) echo '<lastmod>'.e($mod).'</lastmod>';
        echo "</sitemap>\n";
    }
    echo '</sitemapindex>';
}

/** GET /sitemap-<group>[-<part>].xml - one cached child, served exactly as stored. */
function sitemap_child(array $a): void {
    $group = (string) $a['group'];
    $part  = isset($a['part']) && $a['part'] !== '' ? (int) $a['part'] : 1;
    if (!in_array($group, RMT_SITEMAP_GROUPS, true)) { not_found(); return; }
    $row = q_one("SELECT xml FROM sitemap_cache WHERE group_key = ? AND part = ?", [$group, $part]);
    if (!$row && rmt_sitemap_is_stale()) {
        rmt_sitemap_generate();
        $row = q_one("SELECT xml FROM sitemap_cache WHERE group_key = ? AND part = ?", [$group, $part]);
    }
    // A group with nothing in it has no file. Serving an empty <urlset> would put a URL in the
    // index that promises content and delivers none.
    if (!$row) { not_found(); return; }
    header('Content-Type: application/xml; charset=utf-8');
    echo $row['xml'];
}

/**
 * GET /d/<city>/<category> - "Hotels in Paris", and the pilot's entire footprint.
 *
 * A landing page rather than a filtered view: it exists only where rmt_indexable() says the
 * inventory is real, and below that threshold the route 404s rather than serving a thin page that
 * happens to be reachable. The value is the inventory, so there is no introductory paragraph about
 * how Paris is a beautiful city -- the reader came for hotels.
 */
function destination_category(array $a): void {
    $d = dest_by_slug((string) $a['slug']);
    if (!$d) { not_found(); return; }
    $type = rmt_category_type((string) $a['cat']);
    if ($type === null) { not_found(); return; }

    $id = (int) $d['id'];
    // Indexable places only, and the same numbers the threshold used -- a page that counts one way
    // and is judged another will eventually print a count that contradicts its own existence.
    $counts = rmt_indexable_type_counts($id);
    $verdict = rmt_indexable('category', ['place_count' => (int) ($counts[$type] ?? 0)]);
    // Below the threshold this is not a page. The destination browse view still shows the same
    // places, so nothing becomes unreachable -- it stops being a separate URL.
    if (!$verdict['ok']) { not_found(); return; }

    $sort = (string) ($_GET['sort'] ?? 'best');
    if (!isset(RMT_BROWSE_SORTS[$sort])) $sort = 'best';
    $places = rmt_destination_browse($id, $type, $sort);

    $me = current_user();
    $ids = array_map(static fn(array $p) => (int) $p['id'], $places);
    $savedMap = rmt_saved_place_map($me ? (int) $me['id'] : null, $ids);
    $saveCounts = rmt_place_save_counts($ids);
    $areas = rmt_nb_for_destination($id);
    $heading = rmt_category_heading($type, (string) $d['name']);

    // One canonical per landing page. A sort is a way to read the same list, so every ordering
    // points back at the unsorted URL instead of competing with it.
    $canonical = url(ltrim('/d/'.$d['slug'].'/'.$a['cat'], '/'));

    view('destination_category', compact('d','places','counts','type','sort','heading','me',
                                         'savedMap','saveCounts','areas','verdict'), [
        'title' => $heading . ' 2026: prices, hours and honest reviews | RuinMyTrip',
        'description' => $heading . ': ' . (int) ($counts[$type] ?? 0) . ' places with addresses, opening hours and what they actually cost.',
        'canonical' => $canonical,
        'robots' => $sort === 'best' ? 'index, follow' : 'noindex,follow',
        'breadcrumbs' => [
            ['name' => 'Home', 'url' => url()],
            ['name' => 'Explore', 'url' => url('explore')],
            ['name' => (string) $d['name'], 'url' => url('d/'.$d['slug'])],
            ['name' => $heading, 'url' => $canonical],
        ],
    ]);
}

/**
 * GET /feed.xml — site-wide RSS 2.0 of the public activity stream (same data as /discover:
 * trips, reviews, guides, blog posts, collections from every traveler). Reuses
 * rmt_activity_items() rather than re-querying each content type, so a feed entry's title/link/
 * excerpt can never drift from what the discover page shows for the same item.
 */
function feed_rss(array $a): void {
    header('Content-Type: application/rss+xml; charset=utf-8');
    $items = rmt_activity_items(null, 60);
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    echo '<rss version="2.0"><channel>'."\n";
    echo '<title>RuinMyTrip</title>'."\n";
    echo '<link>'.e(url()).'</link>'."\n";
    echo '<description>Honest, real traveler trips, reviews, guides, collections and blog posts.</description>'."\n";
    echo '<language>en-us</language>'."\n";
    foreach ($items as $it) {
        $who = $it['author']['username'] ?? '';
        echo '<item>'."\n";
        echo '  <title>'.e($it['title']).'</title>'."\n";
        echo '  <link>'.e($it['feed_url']).'</link>'."\n";
        echo '  <guid isPermaLink="true">'.e($it['feed_url']).'</guid>'."\n";
        echo '  <pubDate>'.e(date(DATE_RSS, strtotime((string)$it['created_at']))).'</pubDate>'."\n";
        if ($who !== '') echo '  <author>'.e($who).'</author>'."\n";
        echo '  <description>'.e($it['feed_excerpt']).'</description>'."\n";
        echo '</item>'."\n";
    }
    echo '</channel></rss>';
}

/* ============================================================================ communities
 *
 * A collection with a door. Everything below is the door, who holds the key, and the founder
 * tools that make a room worth walking into. The collection itself -- its page, its items, its
 * comments, its moderation -- is unchanged and lives above.
 */

/** POST /c/{slug}/join */
function community_join(array $a): void {
    require_login(); csrf_check();
    $c = q_one('SELECT * FROM collections WHERE slug=?', [$a['slug']]);
    if (!$c) not_found();
    $token = trim((string) input('invite')) ?: null;
    $state = rmt_community_join($c, current_user(), $token);
    flash(match ($state) {
        'joined'          => 'You are in. Say something.',
        'already_member'  => 'You are already a member.',
        'invite_required' => 'That community is invite only.',
        'removed'         => 'You are not able to rejoin that community.',
        default           => 'That community cannot be joined.',
    });
    redirect('/c/'.$c['slug']);
}

/** POST /c/{slug}/leave */
function community_leave(array $a): void {
    require_login(); csrf_check();
    $c = q_one('SELECT * FROM collections WHERE slug=?', [$a['slug']]);
    if (!$c) not_found();
    $state = rmt_community_leave($c, current_user());
    flash(match ($state) {
        'left'               => 'You have left the community.',
        'owner_cannot_leave' => 'You founded this community, so you cannot leave it. Delete it instead.',
        default              => 'You were not a member.',
    });
    redirect('/c/'.$c['slug']);
}

/** GET /c/{slug}/members */
function community_members(array $a): void {
    $c = q_one('SELECT * FROM collections WHERE slug=?', [$a['slug']]);
    if (!$c || $c['status'] !== 'published') not_found();
    $me = current_user();
    $members = rmt_community_members((int) $c['id']);
    $canEdit = rmt_community_can_manage($c, $me);
    // The founder is the only one who needs to see who was removed, and only so they can undo it.
    $removed = $canEdit
        ? q_all("SELECT m.*, u.username FROM collection_members m JOIN users u ON u.id=m.user_id
                  WHERE m.collection_id=? AND m.status='removed' ORDER BY m.removed_at DESC", [(int) $c['id']])
        : [];
    view('community_members', compact('c','me','members','removed','canEdit'), [
        'robots' => rmt_robots_for(rmt_indexable('private')),   // a member list is for the community, not for search
        'title'  => 'Members of '.$c['title'].' — RuinMyTrip',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Communities','url'=>url('communities')],
                          ['name'=>$c['title'],'url'=>url('c/'.$c['slug'])],
                          ['name'=>'Members','url'=>url('c/'.$c['slug'].'/members')]],
    ]);
}

/** POST /collection/{id}/members/{user_id}/remove */
function community_member_remove(array $a): void {
    require_login(); csrf_check();
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int) $a['id']]);
    if (!$c) not_found();
    $state = rmt_community_remove_member($c, current_user(), (int) $a['user_id']);
    if ($state === 'forbidden') forbidden('That is not your community.');
    flash($state === 'removed' ? 'Member removed.' : 'Nothing to remove.');
    redirect('/c/'.$c['slug'].'/members');
}

/** POST /collection/{id}/members/{user_id}/reinstate */
function community_member_reinstate(array $a): void {
    require_login(); csrf_check();
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int) $a['id']]);
    if (!$c) not_found();
    $state = rmt_community_reinstate_member($c, current_user(), (int) $a['user_id']);
    if ($state === 'forbidden') forbidden('That is not your community.');
    flash($state === 'reinstated' ? 'Member reinstated.' : 'Nothing to reinstate.');
    redirect('/c/'.$c['slug'].'/members');
}

/** POST /collection/{id}/invite — issue a link, which retires the previous one. */
function community_invite_rotate(array $a): void {
    require_login(); csrf_check();
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int) $a['id']]);
    if (!$c) not_found();
    if (rmt_community_rotate_invite($c, current_user()) === null) forbidden('That is not your community.');
    flash('New invite link created. The previous link no longer works.');
    redirect('/collection/'.(int) $c['id'].'/edit');
}

/** POST /collection/{id}/invite/revoke */
function community_invite_revoke(array $a): void {
    require_login(); csrf_check();
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int) $a['id']]);
    if (!$c) not_found();
    if (!rmt_community_revoke_invite($c, current_user())) forbidden('That is not your community.');
    flash('Invite link revoked.');
    redirect('/collection/'.(int) $c['id'].'/edit');
}

/** GET /join/{token} — an invite link. Lands on the community, carrying the token. */
function community_invite_landing(array $a): void {
    $c = rmt_community_by_invite((string) $a['token']);
    if (!$c) {
        flash('That invite link is no longer active.');
        redirect('/communities'); return;
    }
    redirect('/c/'.$c['slug'].'?invite='.urlencode((string) $a['token']));
}

/** POST /collection/{id}/items/{item_id}/pin */
function community_item_pin(array $a): void {
    require_login(); csrf_check();
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int) $a['id']]);
    if (!$c) not_found();
    $pin = (string) input('pinned') === '1';
    if (!rmt_community_set_pinned($c, current_user(), (int) $a['item_id'], $pin)) forbidden('That is not your community.');
    flash($pin ? 'Pinned to the top.' : 'Unpinned.');
    redirect('/c/'.$c['slug']);
}

/** GET /communities — only the ones a stranger should be shown. */
function communities_index(array $a): void {
    $communities = rmt_community_browse(30);
    $me = current_user();
    $mine = $me ? rmt_community_memberships((int) $me['id']) : [];
    view('communities_index', compact('communities','mine','me'), [
        'title' => 'Communities — RuinMyTrip',
        'description' => 'Travel communities started by travelers. Join one, or start your own.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Communities','url'=>url('communities')]],
    ]);
}

/**
 * Trip matches: the answer to "who else will be there when I am".
 *
 * Private by construction -- it is a list of other people's plans assembled for one viewer, so it
 * is never indexed and never shown signed out.
 */
function matches_index(array $a): void {
    require_login();
    $me = current_user();
    $uid = (int) $me['id'];
    $matches = rmt_trip_matches($uid);

    /* Grouped by city, because that is the unit a traveler acts on: they are not deciding about a
       person, they are deciding what to do about one trip. */
    $byDest = [];
    foreach ($matches as $m) {
        $byDest[(string) $m['dest_slug']]['dest'] = ['slug' => $m['dest_slug'], 'name' => $m['dest_name'],
                                                     'my_from' => $m['my_from'], 'my_to' => $m['my_to']];
        $byDest[(string) $m['dest_slug']]['people'][] = $m;
    }

    // What is already happening while they are there, so the next step is not "message a stranger".
    foreach ($byDest as $slug => $g) {
        $d = $g['dest'];
        $byDest[$slug]['meetups'] = rmt_meetups_in_window((int) $g['people'][0]['dest_id'],
                                                          (string) $d['my_from'], (string) $d['my_to']);
    }

    $wishlist = rmt_wishlist_matches($uid);
    $shared = rmt_match_shared_destinations($uid, array_column($wishlist, 'user_id'));
    $myPlans = rmt_going_list_for_profile($uid, $me);

    view('matches', compact('byDest', 'wishlist', 'shared', 'myPlans', 'me'), [
        'title' => 'Your trip matches — RuinMyTrip',
        'description' => 'Travelers whose dates overlap yours, and people who want to go where you want to go.',
        'robots' => rmt_robots_for(rmt_indexable('private')),  // other people's plans, assembled for one reader
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Matches', 'url' => url('matches')]],
    ]);
}

/* ------------------------------------------------------------------ posts / talk */

/**
 * GET /talk — the conversation surface.
 *
 * Public on purpose. Somebody who found the site through a search result should be able to read
 * what travelers are saying before deciding whether to join, and the fastest way to lose them is
 * a sign-in wall over a conversation.
 */
function posts_index(array $a): void {
    $me = current_user();
    $destSlug = trim((string) input('d'));
    $comSlug  = trim((string) input('c'));
    $placeSlug = trim((string) input('p'));
    $place = $placeSlug !== '' ? q_one("SELECT * FROM places WHERE slug=? AND status='active'", [$placeSlug]) : null;
    $dest = $destSlug !== '' ? q_one('SELECT * FROM destinations WHERE slug=?', [$destSlug]) : null;
    $community = $comSlug !== '' ? q_one("SELECT * FROM collections WHERE slug=? AND status='published'", [$comSlug]) : null;

    /* Two orders, both honest. Latest is the default because a conversation surface that hides
       the newest thing feels broken; Top answers "I have five minutes, what did I miss". */
    $sort = input('sort') === 'top' ? 'top' : 'latest';
    $destId = $dest ? (int) $dest['id'] : null;
    $comId  = $community ? (int) $community['id'] : null;
    $placeId = $place ? (int) $place['id'] : null;
    $posts = ($sort === 'top' && !$placeId)
        ? rmt_posts_top(50, $destId, $comId)
        : rmt_posts_recent(50, $destId, $comId, $placeId);
    // A quiet week has nothing inside the window; showing an empty Top tab would read as breakage.
    if ($sort === 'top' && !$posts) $posts = rmt_posts_recent(50, $destId, $comId, $placeId);
    $dests = $me ? all_dests() : [];
    $myCommunities = $me ? rmt_community_memberships((int) $me['id']) : [];

    $title = 'Travel talk';
    if ($place) $title = 'Questions about ' . $place['name'];
    elseif ($dest) $title = 'Travelers talking about ' . $dest['name'];
    if ($community) $title = $community['title'] . ' — discussion';

    $topTags = rmt_top_tags(12);
    $polls = rmt_polls_for_posts(array_column($posts, 'id'), $me ? (int) $me['id'] : null);
    view('posts_index', compact('posts', 'me', 'dests', 'myCommunities', 'dest', 'community', 'topTags', 'sort', 'place', 'polls'), [
        'title' => $title . ' — RuinMyTrip',
        'description' => 'What travelers are saying right now: questions, warnings and what a place is actually like.',
        // A filtered view of a stream is a filter, and the unfiltered one is the page worth indexing.
        'robots' => rmt_robots_for(rmt_indexable($dest || $community || $place ? 'filter' : 'static')),
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Talk', 'url' => url('talk')]],
    ]);
}

function post_show(array $a): void {
    $p = rmt_post_get((int) $a['id']);
    if (!$p || $p['status'] !== 'published') not_found();
    $author = q_one("SELECT status FROM users WHERE id=?", [(int) $p['user_id']]);
    if (!$author || $author['status'] !== 'active') not_found();
    $p['author'] = author((int) $p['user_id']);
    $me = current_user();

    $comments = q_all("SELECT c.*, u.username, pr.avatar_url FROM comments c
                         JOIN users u ON u.id=c.user_id
                    LEFT JOIN profiles pr ON pr.user_id=u.id
                        WHERE c.target_type='post' AND c.target_id=? AND c.status='published'
                     ORDER BY c.id", [(int) $p['id']]);
    $likeCount = (int) q_one("SELECT COUNT(*) n FROM likes WHERE target_type='post' AND target_id=?", [(int) $p['id']])['n'];
    $saveCount = (int) q_one("SELECT COUNT(*) n FROM saves WHERE target_type='post' AND target_id=?", [(int) $p['id']])['n'];
    $liked = $me && q_one('SELECT 1 FROM likes WHERE user_id=? AND target_type=? AND target_id=?', [(int) $me['id'], 'post', (int) $p['id']]);
    $saved = $me && q_one('SELECT 1 FROM saves WHERE user_id=? AND target_type=? AND target_id=?', [(int) $me['id'], 'post', (int) $p['id']]);

    $original = !empty($p['repost_of']) ? rmt_post_get((int) $p['repost_of']) : null;
    if ($original && $original['status'] !== 'published') $original = null;
    if ($original) $original['author'] = author((int) $original['user_id']);
    $repostCount = rmt_post_repost_count((int) $p['id']);

    $crumbs = [['name' => 'Home', 'url' => url()], ['name' => 'Talk', 'url' => url('talk')]];
    if ($p['dest_slug']) $crumbs[] = ['name' => (string) $p['dest_name'], 'url' => url('d/' . $p['dest_slug'])];

    $related = rmt_posts_related($p, 4);
    $poll = rmt_poll_for_post((int) $p['id'], $me ? (int) $me['id'] : null);
    view('post_show', compact('p', 'comments', 'likeCount', 'saveCount', 'liked', 'saved', 'me', 'original', 'repostCount', 'related', 'poll'), [
        'title' => rmt_meta_title(rmt_post_title($p)),
        'description' => rmt_meta_description((string) $p['body']),
        // A post with a photo previews as the photo; one without previews as what it says.
        'og_image' => !empty($p['image_url']) ? abs_url((string) $p['image_url']) : rmt_card_url('post', (string) (int) $p['id']),
        'robots' => rmt_robots_for(rmt_indexable('post', $p + ['reply_count' => count($comments)])),
        'breadcrumbs' => $crumbs,
        /* Two shapes for two different things; see rmt_post_jsonld(). A question with answers is
           a Q&A page, which is what the place pages now collect and what Google shows question
           results from. */
        'jsonld' => jsonld(rmt_post_jsonld($p, $comments, $likeCount)),
    ]);
}

function post_create(array $a): void {
    require_verified_email(); csrf_check();
    $me = current_user();
    if (!rmt_rate_ok('post', (string) $me['id'], 20, 3600)) {
        flash('You are posting very fast. Try again shortly.');
        redirect(rmt_return_to('/talk'));
    }
    if (!rmt_submit_ok('post_new', input('_submit'))) {
        flash('That post was already published.');
        redirect(rmt_return_to('/talk'));
    }
    $v = rmt_post_validate($_POST, $me);
    $poll = rmt_poll_validate($_POST);
    if (!$v['ok'] || !$poll['ok']) {
        flash($v['ok'] ? $poll['errors'][0] : $v['errors'][0]);
        redirect(rmt_return_to('/talk'));
    }
    $id = rmt_post_create((int) $me['id'], $v['data']);
    if ($poll['options']) rmt_poll_create($id, $poll['options'], $poll['days']);
    rmt_sync_tags('post', $id, (string) $v['data']['body']);
    if (!empty($_FILES['photo'])) {
        $img = rmt_post_attach_image($id, $_FILES['photo'], (int) $me['id']);
        if (!$img['ok']) flash($img['error']);   // the words are already posted; say what happened
    }
    rmt_notify_mentions('post', $id, (int) $me['id'], [], (string) $v['data']['body']);
    rmt_seo_announce('/post/' . $id);
    redirect('/post/' . $id);
}

function post_edit_form(array $a): void {
    require_login();
    $p = rmt_post_get((int) $a['id']);
    if (!$p || $p['status'] !== 'published') not_found();
    if (!rmt_post_can_edit($p, current_user())) forbidden('That is not your post.');
    view('post_edit', ['p' => $p, 'me' => current_user()], [
        'title' => 'Edit post — RuinMyTrip',
        'robots' => rmt_robots_for(rmt_indexable('private')),
    ]);
}

function post_edit_submit(array $a): void {
    require_login(); csrf_check();
    $p = rmt_post_get((int) $a['id']);
    if (!$p || $p['status'] !== 'published') not_found();
    if (!rmt_post_can_edit($p, current_user())) forbidden('That is not your post.');
    $body = trim((string) input('body'));
    if (mb_strlen($body) < RMT_POST_MIN || mb_strlen($body) > RMT_POST_MAX) {
        flash('A post is between ' . RMT_POST_MIN . ' and ' . RMT_POST_MAX . ' characters.');
        redirect('/post/' . (int) $p['id'] . '/edit');
    }
    rmt_post_update((int) $p['id'], $body);
    rmt_sync_tags('post', (int) $p['id'], $body);
    flash('Post updated.');
    redirect('/post/' . (int) $p['id']);
}

function post_delete(array $a): void {
    require_login(); csrf_check();
    $p = rmt_post_get((int) $a['id']);
    if (!$p) not_found();
    $me = current_user();
    if (!rmt_post_can_remove($p, $me)) forbidden('That is not your post.');
    rmt_post_drop_image($p);
    rmt_post_delete((int) $p['id']);
    flash('Post removed.');
    redirect($p['community_slug'] ? '/c/' . $p['community_slug'] : '/talk');
}

/**
 * GET /communities/new — starting a community as one step.
 *
 * It took three before: make a collection, find the edit page, discover that a join policy exists
 * and change it. Every one of those was a place to lose somebody who arrived wanting to start a
 * group, and "start your own" was the whole invitation on the browse page.
 */
function community_new_form(array $a): void {
    require_login();
    view('community_new', ['errors' => [], 'me' => current_user()], [
        'title' => 'Start a community — RuinMyTrip',
        'description' => 'Start a group other travelers can join, about how you travel rather than where.',
        'breadcrumbs' => [['name' => 'Home', 'url' => url()],
                          ['name' => 'Communities', 'url' => url('communities')],
                          ['name' => 'Start one', 'url' => url('communities/new')]],
    ]);
}

function community_create(array $a): void {
    require_verified_email(); csrf_check(); $me = current_user();
    $opts = ['title' => 'Start a community — RuinMyTrip'];
    if (!rmt_submit_ok('community_new', input('_submit'))) {
        flash('That community was already created.'); redirect('/communities'); return;
    }
    if (!rmt_rate_ok('collection_create', (string) $me['id'], 10, 3600)) {
        view('community_new', ['errors' => ['You are creating these very fast. Try again later.'], 'me' => $me], $opts);
        return;
    }
    $v = rmt_collection_validate($_POST);
    if (!$v['ok']) { view('community_new', ['errors' => $v['errors'], 'me' => $me], $opts); return; }

    $policy = (string) input('join_policy', 'open');
    if (!in_array($policy, ['open', 'invite'], true)) $policy = 'open';   // a closed one is a list, not a community
    $membersCanAdd = input('members_can_add') ? 1 : 0;

    $d = $v['data'];
    $slug = rmt_collection_unique_slug($d['title']);
    $now = date('Y-m-d H:i:s');
    q_run("INSERT INTO collections (user_id,slug,title,summary,status,created_at,join_policy,members_can_add)
           VALUES (?,?,?,?,'published',?,?,?)",
          [(int) $me['id'], $slug, $d['title'], $d['summary'], $now, $policy, $membersCanAdd]);
    $cid = (int) q_one('SELECT id FROM collections WHERE slug=?', [$slug])['id'];
    // The founder is a member. Without this the room has an owner and nobody in it, and every
    // membership check treats the person who made it as a stranger.
    q_run('INSERT INTO collection_members (collection_id,user_id,role,status,joined_at) VALUES (?,?,?,?,?)',
          [$cid, (int) $me['id'], 'owner', 'active', $now]);
    rmt_sync_tags('collection', $cid, $d['title'], $d['summary']);
    rmt_seo_announce('/c/' . $slug);
    flash('Your community exists. Put a few places in it and say something, then invite people.');
    redirect('/c/' . $slug);
}

/**
 * GET /cron/indexnow — flush the announce queue.
 *
 * The web service already holds the only credentials that reach the database, so a scheduled job
 * that curls this needs none: no firewall to open, no connection string to hand out, nothing that
 * has to be re-locked afterwards if the run dies halfway.
 *
 * Unset CRON_KEY means the endpoint does not exist. A wrong one gets the same 404 as a wrong path,
 * because telling a scanner that it found a real endpoint with the wrong key is free information.
 */
function cron_indexnow(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = (string) input('key');
    if ($key === '' || $given === '' || !hash_equals($key, $given)) not_found();
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $pending = count(rmt_seo_pending(500));
    $sent = rmt_seo_flush(500);
    echo "pending={$pending} submitted={$sent}\n";
}

/** POST /post/{id}/repost — pass it on, with or without a comment of your own. */
/** POST /post/{id}/vote — cast or move a poll vote. A vote is one click; it needs an account, not a confirmed email. */
/**
 * GET /card/{kind}/{key}.png — the link preview image for a post, review, community, profile,
 * meetup or topic. See app/cards.php. Public, cached a day, 404 for anything the page would 404.
 */
function share_card(array $a): void {
    if (!rmt_card_available()) not_found();
    $spec = rmt_card_spec((string) $a['kind'], (string) $a['key']);
    if (!$spec) not_found();
    $etag = '"' . substr(sha1(json_encode($spec) ?: ''), 0, 20) . '"';
    header('Cache-Control: public, max-age=86400');
    header('ETag: ' . $etag);
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
    header('Content-Type: image/png');
    header('X-Content-Type-Options: nosniff');
    echo rmt_card_render($spec);
    exit;
}

function post_vote(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $p = rmt_post_get((int) $a['id']);
    if (!$p || $p['status'] !== 'published') not_found();
    if (!rmt_rate_ok('react', (string) $me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect(rmt_return_to('/post/' . (int) $p['id']));
    }
    $r = rmt_poll_vote((int) $p['id'], (int) input('option_id'), (int) $me['id']);
    if (!$r['ok']) flash($r['error']);
    redirect(rmt_return_to('/post/' . (int) $p['id']));
}

function post_repost(array $a): void {
    require_verified_email(); csrf_check();
    $me = current_user();
    if (!rmt_rate_ok('post', (string) $me['id'], 20, 3600)) {
        flash('You are posting very fast. Try again shortly.');
        redirect(rmt_return_to('/talk'));
    }
    $orig = rmt_post_get((int) $a['id']);
    if (!$orig) not_found();
    // A block stops somebody amplifying the person who blocked them, the same way it stops them
    // commenting. Reposting is a louder act than a comment, not a quieter one.
    if (rmt_is_blocked((int) $me['id'], (int) $orig['user_id'])) {
        flash('You cannot repost that.');
        redirect(rmt_return_to('/talk'));
    }
    $res = rmt_post_repost((int) $me['id'], (int) $a['id'], (string) input('body'));
    if (!$res['ok']) {
        flash($res['error']);
        redirect(rmt_return_to('/post/' . (int) $a['id']));
    }
    $id = (int) $res['id'];
    rmt_sync_tags('post', $id, (string) input('body'));
    // The author hears about it. Being passed on is the thing worth knowing.
    $ownerId = (int) $orig['user_id'];
    if ($ownerId !== (int) $me['id']) {
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
              [$ownerId, 'repost', (int) $me['id'], 'post', $id, date('Y-m-d H:i:s')]);
    }
    rmt_notify_mentions('post', $id, (int) $me['id'], [$ownerId], (string) input('body'));
    rmt_seo_announce('/post/' . $id);
    flash('Reposted to your followers.');
    redirect('/post/' . $id);
}

/**
 * GET /suggest/users?q= — who you might be about to @mention.
 *
 * Signed in only. The list of every member with a prefix search over it is exactly the shape a
 * scraper wants, and a mention box is only useful to somebody who can post anyway.
 */
function suggest_users_json(array $a): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $me = current_user();
    if (!$me) { http_response_code(403); echo json_encode(['users' => []]); return; }
    if (!rmt_rate_ok('suggest_users', (string) $me['id'], 240, 60)) {
        http_response_code(429); echo json_encode(['users' => [], 'throttled' => true]); return;
    }
    $q = trim((string) input('q'));
    if (mb_strlen($q) < 1) { echo json_encode(['users' => []]); return; }

    /* Prefix first, then anywhere: typing "ma" should offer @maya before @normanmartin, and offer
       @normanmartin at all rather than pretending it does not exist. */
    $like = mb_strtolower($q) . '%';
    $anywhere = '%' . mb_strtolower($q) . '%';
    $rows = q_all("SELECT u.id, u.username, p.display_name, p.avatar_url
                     FROM users u LEFT JOIN profiles p ON p.user_id = u.id
                    WHERE u.status='active' AND u.id <> ?
                      AND (LOWER(u.username) LIKE ? OR LOWER(COALESCE(p.display_name,'')) LIKE ?)
                      AND NOT EXISTS (SELECT 1 FROM blocks b
                                       WHERE (b.blocker_id = ? AND b.blocked_id = u.id)
                                          OR (b.blocker_id = u.id AND b.blocked_id = ?))
                 ORDER BY CASE WHEN LOWER(u.username) LIKE ? THEN 0 ELSE 1 END, u.username
                    LIMIT 8",
                  [(int) $me['id'], $anywhere, $anywhere, (int) $me['id'], (int) $me['id'], $like]);
    echo json_encode(['users' => array_map(static fn(array $r): array => [
        'username' => (string) $r['username'],
        'name' => (string) ($r['display_name'] ?? ''),
    ], $rows)]);
}
