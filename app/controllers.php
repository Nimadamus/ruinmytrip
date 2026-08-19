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
    $reviews = q_all("SELECT r.*, d.slug dest_slug FROM reviews r
                      LEFT JOIN destinations d ON d.id=r.destination_id
                      WHERE r.status='published' ORDER BY r.verified DESC, r.id DESC LIMIT 4");
    $meetups = q_all("SELECT m.*, d.name dest_name, d.slug dest_slug FROM meetups m
                      LEFT JOIN destinations d ON d.id=m.destination_id
                      WHERE m.status='published' ORDER BY m.date_start ASC LIMIT 3");
    $guides = q_all("SELECT g.*, d.name dest_name FROM guides g
                     LEFT JOIN destinations d ON d.id=g.destination_id
                     WHERE g.status='published' ORDER BY g.id DESC LIMIT 3");
    authors_fill($stories);
    authors_fill($reviews);
    authors_fill($guides);
    // Real total, not count($trending) — $trending is LIMIT 6 and would print "6" forever.
    $stat_destinations = (int)(q_one('SELECT COUNT(*) c FROM destinations')['c'] ?? 0);
    // Real community total (editorial excluded). Drives the homepage copy: while it is 0 the page
    // says so plainly rather than dressing editorial content up as community activity.
    $stat_community_reviews = (int)(q_one("SELECT COUNT(*) c FROM reviews r JOIN users u ON u.id=r.user_id
                                            WHERE r.status='published' AND u.role <> ?", [RMT_EDITORIAL_ROLE])['c'] ?? 0);
    view('home', compact('trending','stories','reviews','meetups','guides','stat_destinations','stat_community_reviews'), [
        'title' => 'RuinMyTrip — Real trips, honest reviews, safe travel meetups',
        'description' => 'Join a trustworthy travel community. Share trips, review destinations and stays, follow travelers you trust, and find safe public meetups — RuinMyTrip.',
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
    $topTags = rmt_top_tags(14);
    view('explore', compact('dests','cats','qs','cat','sort','topTags'), [
        'title' => 'Explore destinations — RuinMyTrip',
        'description' => 'Browse traveler-reviewed destinations. Filter by style — culture, adventure, nature, food, city.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Explore','url'=>url('explore')]],
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
    $going = q_all("SELECT g.*, u.username, p.avatar_url, p.display_name FROM going g
                    JOIN users u ON u.id=g.user_id LEFT JOIN profiles p ON p.user_id=u.id
                    WHERE g.destination_id=? AND g.visibility='public' ORDER BY g.date_from", [$id]);
    // Community score only. An editorial rating is the site's own opinion and must never be
    // presented, or marked up for search engines, as traveler consensus.
    $avg = rmt_community_avg($id);
    $avgByCategory = rmt_community_avg_by_category($id);
    $me = current_user();
    $saved = $me ? (bool) q_one("SELECT 1 FROM saves WHERE user_id=? AND target_type='destination' AND target_id=?", [(int)$me['id'], $id]) : false;
    $wantCount = (int) (q_one("SELECT COUNT(*) c FROM saves WHERE target_type='destination' AND target_id=?", [$id])['c'] ?? 0);
    // Top places teaser: the destination page shows the best-rated few, /d/{slug}/places has them all.
    $topPlaces = rmt_places_for_destination($id, '', 6);
    $placeCount = array_sum(rmt_place_type_counts($id));
    $photos = rmt_destination_photos($id, 12);
    $photoCount = (int) (q_one("SELECT
            (SELECT COUNT(*) FROM trip_photos tp JOIN trips t ON t.id=tp.trip_id WHERE t.destination_id=? AND t.status='published') +
            (SELECT COUNT(*) FROM review_photos rp JOIN reviews r ON r.id=rp.review_id WHERE r.destination_id=? AND r.status='published') c",
        [$id, $id])['c'] ?? 0);
    view('destination', compact('d','trips','tripCount','reviews','editorial','tips','guides','meetups','going','avg','avgByCategory','me','saved','wantCount','photos','photoCount','topPlaces','placeCount'), [
        'title' => $d['name'].', '.$d['country'].' — travel guide, reviews & meetups | RuinMyTrip',
        'description' => $d['summary'],
        'og_image' => abs_url($d['hero_url']),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Explore','url'=>url('explore')],['name'=>$d['name'],'url'=>url('d/'.$d['slug'])]],
        'jsonld' => jsonld(['@context'=>'https://schema.org','@type'=>'TouristDestination','name'=>$d['name'],
            'description'=>$d['summary'],'url'=>url('d/'.$d['slug']),
            'geo'=>['@type'=>'GeoCoordinates','latitude'=>$d['lat'],'longitude'=>$d['lng']],
            'aggregateRating'=>$avg && $avg['c']>0 ? ['@type'=>'AggregateRating','ratingValue'=>$avg['a'],'reviewCount'=>$avg['c']] : null]),
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
function destination_places(array $a): void {
    $d = dest_by_slug($a['slug']); if (!$d) not_found();
    $id = (int) $d['id'];
    $type = (string) ($_GET['type'] ?? '');
    if (!in_array($type, RMT_PLACE_TYPES, true)) $type = '';
    $places = rmt_places_for_destination($id, $type);
    $counts = rmt_place_type_counts($id);
    $total = array_sum($counts);
    $label = $type === '' ? 'Places' : rmt_place_type_label($type, true);
    view('destination_places', compact('d','places','counts','total','type','label'), [
        'title' => $label.' in '.$d['name'].', '.$d['country'].' — reviewed by travelers | RuinMyTrip',
        'description' => 'Hotels, restaurants, attractions and experiences in '.$d['name'].', rated by travelers who actually went.',
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
    $p = rmt_place_by_slug($a['slug']); if (!$p) not_found();
    $id = (int) $p['id'];
    $stats = rmt_place_stats($id);
    $breakdown = rmt_place_rating_breakdown($id);
    $reviews = rmt_place_reviews($id);
    [$editorial, $reviews] = rmt_split_editorial($reviews);
    $photos = rmt_place_photos($id, 12);
    $me = current_user();
    $typeLabel = rmt_place_type_label((string) $p['type']);

    $ed = rmt_place_editorial($id);
    $nearby = rmt_place_nearby($id, (int)$p['destination_id']);
    $saved = rmt_place_is_saved($id, $me ? (int) $me['id'] : null);
    $saveCount = rmt_place_save_count($id);
    $canonical = url(ltrim(rmt_place_path($p), '/'));

    $ld = ['@context'=>'https://schema.org', '@type'=>rmt_place_schema_type((string) $p['type']), 'name'=>$p['name'],
           'url'=>$canonical,
           'address'=>['@type'=>'PostalAddress','addressLocality'=>$p['dest_name'],'addressCountry'=>$p['dest_country']]];
    if ($ed && !empty($ed['what_it_is'])) $ld['description'] = mb_strimwidth(strip_tags((string)$ed['what_it_is']), 0, 300, '…');
    // aggregateRating is COMMUNITY ONLY and omitted entirely at zero. An empty aggregate, or one
    // padded with our own rating, would be a consensus claim with nothing behind it.
    if ($stats['c'] > 0 && $stats['a'] !== null) {
        $ld['aggregateRating'] = ['@type'=>'AggregateRating','ratingValue'=>$stats['a'],
                                  'reviewCount'=>$stats['c'],'bestRating'=>5,'worstRating'=>1];
    }
    // The editorial review is marked up as what it actually is: one Review, authored by the
    // organisation, never folded into an aggregate. That is semantically correct and it is the
    // opposite of inventing review counts.
    if ($editorial) {
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

    view('place_show', compact('p','stats','breakdown','reviews','editorial','photos','me','typeLabel','ed','nearby','saved','saveCount'), [
        'title' => $p['name'].', '.$p['dest_name'].' — '.rmt_place_title_question((string) $p['type']).' | RuinMyTrip',
        'description' => $desc,
        'canonical' => $canonical,
        'og_image' => $photos ? abs_url($photos[0]['url']) : abs_url($p['dest_hero']),
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
    $collections = q_all("SELECT c.*, (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id=c.id) item_count
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

    $is_following = $me ? (bool) q_one('SELECT 1 FROM follows WHERE follower_id=? AND followee_id=?', [(int)$me['id'],$uid]) : false;
    $i_blocked_them = ($me && !$isMe) ? (bool) q_one('SELECT 1 FROM blocks WHERE blocker_id=? AND blocked_id=?', [(int)$me['id'],$uid]) : false;
    $is_blocked = ($me && !$isMe) ? rmt_is_blocked((int)$me['id'], $uid) : false;
    view('profile', compact('u','trips','reviews','guides','collections','followers','following','is_following','me','stats','badges','isMe','compliments','myCompliments','is_blocked','i_blocked_them','wishlist'), [
        'title' => ($u['display_name'] ?: $u['username']).' (@'.$u['username'].') — RuinMyTrip',
        'description' => $u['bio'] ?: ('Traveler profile for @'.$u['username'].' on RuinMyTrip.'),
        'og_image' => abs_url($u['avatar_url']),
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

    $reviews = q_all("SELECT r.*, d.name dest_name FROM reviews r LEFT JOIN destinations d ON d.id=r.destination_id
                      WHERE r.status='published' AND $followedR
                      ORDER BY r.created_at DESC, r.id DESC LIMIT $limitEach", $args);
    foreach ($reviews as &$row) {
        $row['kind'] = 'review';
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

    $collections = q_all("SELECT c.*,
            (SELECT d.hero_url FROM collection_items ci JOIN destinations d ON d.id=ci.destination_id
              WHERE ci.collection_id=c.id ORDER BY ci.sort, ci.id LIMIT 1) cover_url,
            (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id=c.id) item_count
          FROM collections c WHERE c.status='published' AND $followedPlain
          ORDER BY c.created_at DESC, c.id DESC LIMIT $limitEach", $args);
    foreach ($collections as &$row) {
        $row['kind'] = 'collection';
        $row['dest_name'] = null;
        $row['feed_url'] = url('c/'.$row['slug']);
        $count = (int)$row['item_count'];
        $row['feed_excerpt'] = $row['summary'] ?: ($count.' '.($count===1?'destination':'destinations'));
    }
    unset($row);

    $items = array_merge($trips, $reviews, $guides, $posts, $collections);
    usort($items, fn($x, $y) => strcmp((string)$y['created_at'], (string)$x['created_at']));
    $items = array_slice($items, 0, $limitEach);
    authors_fill($items);
    return $items;
}

function feed(array $a): void {
    require_login(); $me = current_user(); $uid = (int)$me['id'];
    $items = rmt_activity_items($uid);
    view('feed', compact('items','me'), [
        'title' => 'Your feed — RuinMyTrip',
        'description' => 'Latest trips, reviews, guides, collections and blog posts from travelers you follow.',
    ]);
}

/** Public, no-login activity stream across the whole site -- what a logged-out visitor or a
 *  logged-in user with zero follows sees instead of an empty feed. */
function discover(array $a): void {
    $items = rmt_activity_items(null);
    $topTags = rmt_top_tags(14);
    view('discover', ['items'=>$items, 'me'=>current_user(), 'topTags'=>$topTags], [
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
        'title' => $t['title'].' — RuinMyTrip',
        'description' => mb_substr(strip_tags((string)$t['body']),0,150),
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
        'title'=>$mine ? 'Your reviews — RuinMyTrip' : 'Traveler reviews — RuinMyTrip',
        'description'=>'Honest traveler reviews of destinations, hotels, restaurants, attractions and experiences.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Reviews','url'=>url('reviews')]],
    ]);
}

function guides_index(array $a): void {
    $guides = q_all("SELECT g.*, d.name dest_name FROM guides g LEFT JOIN destinations d ON d.id=g.destination_id
                     WHERE g.status='published' ORDER BY g.id DESC");
    authors_fill($guides);
    view('guides_index', compact('guides'), [
        'title'=>'Travel guides & itineraries — RuinMyTrip',
        'description'=>'Detailed, traveler-written guides and day-by-day itineraries you can trust.',
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
    view('guide_show', compact('g','me','comments','likeCount','saveCount','liked','saved','tags'), [
        'title'=>$g['title'].' — RuinMyTrip',
        'description'=>$g['summary'],
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
        'title'=>'Blog — RuinMyTrip',
        'description'=>'Travel tips, safety notes, budget breakdowns and real stories from the RuinMyTrip community.',
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
    view('blog_show', compact('p','me','comments','likeCount','saveCount','liked','saved','tags'), [
        'title' => $p['title'].' — RuinMyTrip Blog',
        'description' => $p['summary'],
        'og_image' => $p['cover_url'] ? abs_url($p['cover_url']) : url('assets/img/og-default.svg'),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Blog','url'=>url('blog')],['name'=>$p['title'],'url'=>url('blog/'.$p['slug'])]],
        'jsonld' => jsonld(['@context'=>'https://schema.org','@type'=>'Article','headline'=>$p['title'],'datePublished'=>$p['created_at']]),
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
    view('collections_index', ['collections'=>$collections], [
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
    $items = q_all("SELECT ci.*, d.slug dest_slug, d.name dest_name, d.country dest_country, d.hero_url dest_hero
                    FROM collection_items ci JOIN destinations d ON d.id=ci.destination_id
                    WHERE ci.collection_id=? ORDER BY ci.sort, ci.id", [$cid]);
    $comments = q_all("SELECT cm.*, u.username, p2.avatar_url FROM comments cm JOIN users u ON u.id=cm.user_id
                       LEFT JOIN profiles p2 ON p2.user_id=u.id
                       WHERE cm.target_type='collection' AND cm.target_id=? AND cm.status='published' ORDER BY cm.id", [$cid]);
    $likeCount = (int) q_one("SELECT COUNT(*) n FROM likes WHERE target_type='collection' AND target_id=?", [$cid])['n'];
    $saveCount = (int) q_one("SELECT COUNT(*) n FROM saves WHERE target_type='collection' AND target_id=?", [$cid])['n'];
    $liked = $me && q_one('SELECT 1 FROM likes WHERE user_id=? AND target_type=? AND target_id=?', [(int)$me['id'],'collection',$cid]);
    $saved = $me && q_one('SELECT 1 FROM saves WHERE user_id=? AND target_type=? AND target_id=?', [(int)$me['id'],'collection',$cid]);
    $canEdit = rmt_collection_can_edit($c, $me);
    $tags = rmt_tags_for('collection', $cid);
    view('collection_show', compact('c','me','items','comments','likeCount','saveCount','liked','saved','canEdit','tags'), [
        'title' => $c['title'].' — RuinMyTrip Collections',
        'description' => $c['summary'] ?: ('A curated destination list on RuinMyTrip: '.$c['title']),
        'og_image' => $items ? abs_url($items[0]['dest_hero']) : url('assets/img/og-default.svg'),
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
        flash('That collection was already created.'); redirect('/collections'); return;
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
    flash('Collection created. Now add a few destinations to it.');
    redirect('/collection/'.$cid.'/edit');
}

function collection_edit_form(array $a): void {
    require_login();
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int)$a['id']]);
    if (!$c) not_found();
    if (!rmt_collection_can_edit($c, current_user())) { forbidden('That is not your collection.'); }
    $items = q_all("SELECT ci.*, d.name dest_name, d.country dest_country FROM collection_items ci
                    JOIN destinations d ON d.id=ci.destination_id WHERE ci.collection_id=? ORDER BY ci.sort, ci.id", [(int)$c['id']]);
    $usedIds = array_column($items, 'destination_id');
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
    db()->prepare("UPDATE collections SET title=?, slug=?, summary=?, updated_at=? WHERE id=?")
        ->execute([$d['title'], $slug, $d['summary'], date('Y-m-d H:i:s'), (int)$c['id']]);
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
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int)$a['id']]);
    if (!$c) not_found();
    if (!rmt_collection_can_edit($c, current_user())) { forbidden('That is not your collection.'); }

    $did = (int) input('destination_id');
    $note = trim((string) input('note'));
    if (mb_strlen($note) > 500) {
        flash('That note is too long (500 characters max).'); redirect('/collection/'.(int)$c['id'].'/edit'); return;
    }
    if (!$did || !dest_by_id($did)) redirect('/collection/'.(int)$c['id'].'/edit');
    $count = (int) (q_one('SELECT COUNT(*) n FROM collection_items WHERE collection_id=?', [(int)$c['id']])['n'] ?? 0);
    if ($count >= 50) {
        flash('A collection can hold at most 50 destinations.'); redirect('/collection/'.(int)$c['id'].'/edit'); return;
    }
    $nextSort = (int) (q_one('SELECT COALESCE(MAX(sort),-1) n FROM collection_items WHERE collection_id=?', [(int)$c['id']])['n'] ?? -1) + 1;
    try {
        q_run('INSERT INTO collection_items (collection_id,destination_id,note,sort) VALUES (?,?,?,?)',
            [(int)$c['id'], $did, $note !== '' ? $note : null, $nextSort]);
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
    }
    redirect('/collection/'.(int)$c['id'].'/edit');
}

/** POST /collection/{id}/items/{item_id}/delete — owner-only. */
function collection_item_remove(array $a): void {
    require_login(); csrf_check();
    $c = q_one('SELECT * FROM collections WHERE id=?', [(int)$a['id']]);
    if (!$c) not_found();
    if (!rmt_collection_can_edit($c, current_user())) { forbidden('That is not your collection.'); }
    db()->prepare('DELETE FROM collection_items WHERE id=? AND collection_id=?')->execute([(int)$a['item_id'], (int)$c['id']]);
    redirect('/collection/'.(int)$c['id'].'/edit');
}

function meetups_index(array $a): void {
    $meetups = q_all("SELECT m.*, d.name dest_name, d.slug dest_slug,
                      (SELECT COUNT(*) FROM meetup_rsvps r WHERE r.meetup_id=m.id AND r.status='going') going
                      FROM meetups m LEFT JOIN destinations d ON d.id=m.destination_id
                      WHERE m.status='published' ORDER BY m.date_start");
    $hosts = authors_by_ids(array_column($meetups, 'host_id'));
    foreach ($meetups as &$m) $m['host'] = $hosts[(int)$m['host_id']] ?? null; unset($m);
    view('meetups_index', compact('meetups'), [
        'title'=>'Public travel meetups — RuinMyTrip',
        'description'=>'Optional, public, safety-first travel meetups. Meet fellow travelers in a destination — never dating, never precise location sharing.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Meetups','url'=>url('meetups')]],
    ]);
}

function meetup_show(array $a): void {
    $m = q_one("SELECT m.*, d.name dest_name, d.slug dest_slug FROM meetups m
                LEFT JOIN destinations d ON d.id=m.destination_id WHERE m.id=?", [(int)$a['id']]);
    if (!$m || $m['status']!=='published') not_found();
    $m['host'] = author((int)$m['host_id']);
    $rsvps = q_all("SELECT r.*, u.username, p.avatar_url, p.display_name FROM meetup_rsvps r
                    JOIN users u ON u.id=r.user_id LEFT JOIN profiles p ON p.user_id=u.id
                    WHERE r.meetup_id=? AND r.status='going'", [(int)$m['id']]);
    $me = current_user();
    $mine = $me ? (bool) q_one('SELECT 1 FROM meetup_rsvps WHERE meetup_id=? AND user_id=?', [(int)$m['id'],(int)$me['id']]) : false;
    view('meetup_show', compact('m','rsvps','me','mine'), [
        'title'=>$m['title'].' — RuinMyTrip meetup',
        'description'=>mb_substr((string)$m['description'],0,150),
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Meetups','url'=>url('meetups')],['name'=>$m['title'],'url'=>url('meetup/'.$m['id'])]],
    ]);
}

function going_index(array $a): void {
    $rows = q_all("SELECT g.*, d.name dest_name, d.slug dest_slug, u.username, p.avatar_url, p.display_name
                   FROM going g JOIN destinations d ON d.id=g.destination_id JOIN users u ON u.id=g.user_id
                   LEFT JOIN profiles p ON p.user_id=u.id
                   WHERE g.visibility='public' ORDER BY g.date_from");
    view('going_index', compact('rows'), [
        'title'=>"Who's going — find travelers by destination & date | RuinMyTrip",
        'description'=>'Discover travelers heading to the same destination in your date range. Destination and date-range only — never precise location.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>"Who's going",'url'=>url('going')]],
    ]);
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
function search(array $a): void {
    $qs = trim((string)($_GET['q'] ?? ''));
    $dests=$trips=$guides=$reviews=$people=$posts=$collections=$places=[];
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
    }
    view('search', compact('qs','dests','places','trips','guides','reviews','people','posts','collections'), [
        'title'=>($qs!==''?('Search: '.$qs.' — '):'Search — ').'RuinMyTrip',
        'description'=>'Search destinations, places, trips, reviews, guides, collections, blog posts, and travelers across RuinMyTrip.',
    ]);
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

function review_new_form(array $a): void {
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
        view('review_new', ['dests'=>all_dests(), 'errors'=>[], 'r'=>$r, 'placeOptions'=>[], 'boundPlace'=>$bound],
             ['title'=>'Review '.$bound['name'].' — RuinMyTrip']);
        return;
    }
    $preselect = (int) input('destination');
    $r = ($preselect && dest_by_id($preselect)) ? ['destination_id' => $preselect] : null;
    view('review_new', ['dests'=>all_dests(), 'errors'=>[], 'r'=>$r, 'placeOptions'=>rmt_place_suggestions(), 'boundPlace'=>null],
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
    $opts  = static fn(array $extra) => $extra + ['dests'=>all_dests(), 'placeOptions'=>$bound ? [] : rmt_place_suggestions(), 'boundPlace'=>$bound];
    if (!rmt_rate_ok('review_create', (string)$me['id'], 20, 3600)) {
        view('review_new', $opts(['errors'=>['You are posting very fast. Try again later.'], 'r'=>null]),
             ['title'=>'Write a review — RuinMyTrip']); return;
    }
    $isDraft = input('action') === 'draft';
    // Publishing requires a confirmed email; saving a private draft does not, so an unverified
    // user can still write and keep their work.
    if (!$isDraft && !email_is_verified($me)) {
        flash('Confirm your email to publish. Your draft tools still work in the meantime.');
        redirect('/verify-email');
    }
    $v = rmt_review_validate($_POST, $isDraft);
    if (!$v['ok']) {
        view('review_new', $opts(['errors'=>$v['errors'], 'r'=>$_POST]),
             ['title'=>'Write a review — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $now = date('Y-m-d H:i:s');
    $status = $isDraft ? 'draft' : 'published';
    // Resolve what was reviewed to a real place row so every review of the same hotel collects on
    // one page. Returns null for destination-level reviews and for drafts with no name yet — the
    // column is nullable and the review renders from subject_name either way.
    $placeId = ($bound ? rmt_place_bound_id((int)$bound['id'], $d['destination_id'], $d['subject_name']) : null)
        ?? rmt_place_resolve($d['destination_id'], $d['subject_type'], $d['subject_name'], (int)$me['id']);
    $id = (int) q_run("INSERT INTO reviews
        (user_id,destination_id,place_id,subject_type,subject_name,rating,title,body,what_great,what_ruined,
         visited_on,safety_rating,value_rating,verified,status,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?)",
        [(int)$me['id'], $d['destination_id'], $placeId, $d['subject_type'], $d['subject_name'], $d['rating'],
         $d['title'], $d['body'], $d['what_great'], $d['what_ruined'], $d['visited_on'],
         $d['safety_rating'], $d['value_rating'], $status, $now, $now]);

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

    $msg = $isDraft ? 'Draft saved. Only you can see it.' : 'Review published.';
    if ($photoErrors) $msg .= ' Some photos were not added: ' . implode(' ', array_unique($photoErrors));
    flash($msg);
    redirect($isDraft ? '/reviews?mine=1' : '/review/'.$id.'/'.$slug);
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
    view('review_show', compact('r','author','photos','me','voteCounts','myVotes','comments','tags'), [
        'title' => ($r['title'] ?: $r['subject_name']).' — review by @'.$r['username'].' | RuinMyTrip',
        'description' => mb_strimwidth(strip_tags((string)$r['body']), 0, 155, '…'),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Reviews','url'=>url('reviews')],
                          ['name'=>$r['title'] ?: $r['subject_name'],'url'=>url(ltrim(rmt_review_path($r),'/'))]],
        'jsonld' => $r['status']==='published' ? jsonld(['@context'=>'https://schema.org','@type'=>'Review',
            'itemReviewed'=>array_filter(['@type'=>'Place','name'=>$r['subject_name'],
                'url'=>$r['place_slug'] ? url('p/'.$r['place_slug']) : null]),
            'reviewRating'=>['@type'=>'Rating','ratingValue'=>(int)$r['rating'],'bestRating'=>5,'worstRating'=>1],
            'author'=>['@type'=>'Person','name'=>'@'.$r['username']],
            'datePublished'=>substr((string)$r['created_at'],0,10),
            'reviewBody'=>mb_strimwidth(strip_tags((string)$r['body']),0,500,'…')]) : '',
    ]);
}

function review_edit_form(array $a): void {
    require_login();
    $r = rmt_review_get((int)$a['id']);
    if (!$r) not_found();
    if (!rmt_review_can_edit($r, current_user())) { forbidden('That is not your review.'); }
    $photos = q_all('SELECT * FROM review_photos WHERE review_id=? ORDER BY sort, id', [(int)$r['id']]);
    view('review_edit', ['r'=>$r, 'dests'=>all_dests(), 'errors'=>[], 'photos'=>$photos, 'placeOptions'=>rmt_place_suggestions()],
         ['title'=>'Edit review — RuinMyTrip']);
}

function review_edit_submit(array $a): void {
    require_login(); csrf_check();
    $r = rmt_review_get((int)$a['id']);
    if (!$r) not_found();
    if (!rmt_review_can_edit($r, current_user())) { forbidden('That is not your review.'); }

    $isDraft = input('action') === 'draft';
    if (!$isDraft && !email_is_verified(current_user())) {
        flash('Confirm your email to publish this review.');
        redirect('/verify-email');
    }
    $v = rmt_review_validate($_POST, $isDraft);
    if (!$v['ok']) {
        $photos = q_all('SELECT * FROM review_photos WHERE review_id=? ORDER BY sort, id', [(int)$r['id']]);
        view('review_edit', ['r'=>array_merge($r, $_POST), 'dests'=>all_dests(), 'errors'=>$v['errors'], 'photos'=>$photos, 'placeOptions'=>rmt_place_suggestions()],
             ['title'=>'Edit review — RuinMyTrip']); return;
    }
    $d = $v['data'];
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
                   status=?, slug=?, updated_at=? WHERE id=?")
        ->execute([$d['destination_id'], $placeId, $d['subject_type'], $d['subject_name'], $d['rating'], $d['title'],
                   $d['body'], $d['what_great'], $d['what_ruined'], $d['visited_on'], $d['safety_rating'],
                   $d['value_rating'], $status, $slug, date('Y-m-d H:i:s'), (int)$r['id']]);
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
    $msg = 'Review updated.';
    if ($photoErrors) $msg .= ' Some photos were not added: ' . implode(' ', array_unique($photoErrors));
    flash($msg);
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
    if (!$target || $target===(int)$me['id']) redirect(input('return','/'));
    // The target must be a real, active user. Without this, following a bogus id throws an
    // uncaught FK violation (500) — which also happens naturally if the followee deleted their
    // account between the page loading and the click.
    if (!q_one("SELECT 1 FROM users WHERE id=? AND status='active'", [$target])) {
        flash('That traveler is no longer available.'); redirect(input('return','/'));
    }
    if (rmt_is_blocked((int)$me['id'], $target)) redirect(input('return','/'));
    // Follows create notifications, so cap them to blunt notification-spam.
    if (!rmt_rate_ok('follow', (string)$me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.'); redirect(input('return','/'));
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
            redirect(input('return','/'));
        }
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
            [$target,'follow',(int)$me['id'],'user',(int)$me['id'],date('Y-m-d H:i:s')]);
    }
    redirect(input('return','/'));
}

/**
 * Interactable content types -> table. Same allow-list discipline as reports: a type that
 * reaches a table name is never taken raw from the request.
 */
const RMT_INTERACT_TARGETS = [
    'trip'       => 'trips',
    'review'     => 'reviews',
    'guide'      => 'guides',
    'meetup'     => 'meetups',
    'blog_post'  => 'blog_posts',
    'collection' => 'collections',
];

/**
 * Is $tt#$tid something $user is allowed to interact with (comment on, like, save)?
 *
 * You may only touch content you can actually SEE. Without this, a stranger could comment on and
 * like another user's unpublished draft purely by guessing its id — the draft 404s for them, but
 * the interaction endpoints never checked. Proven before this fix: @snoop landed a comment and a
 * like on a draft they could not view.
 */
function rmt_can_interact(string $tt, int $tid, ?array $user): bool {
    $table = RMT_INTERACT_TARGETS[$tt] ?? null;
    if (!$table || $tid < 1) return false;

    $row = q_one("SELECT user_id, status FROM {$table} WHERE id = ?", [$tid]);
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

    if (!rmt_can_interact($tt, $tid, $me)) redirect(input('return', '/'));
    if (!rmt_rate_ok('react', (string)$me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect(input('return', '/'));
    }

    $has = q_one("SELECT 1 FROM $tbl WHERE user_id=? AND target_type=? AND target_id=?", [(int)$me['id'],$tt,$tid]);
    if ($has) db()->prepare("DELETE FROM $tbl WHERE user_id=? AND target_type=? AND target_id=?")->execute([(int)$me['id'],$tt,$tid]);
    else {
        // A double-click can fire two near-simultaneous requests that both see $has as false; the
        // table's (user_id,target_type,target_id) primary key stops a duplicate row, but the
        // loser previously surfaced as an uncaught PDOException (500 page) instead of just no-op'ing
        // into the same end state the winner already produced.
        try { db()->prepare("INSERT INTO $tbl (user_id,target_type,target_id" . ($kind === 'save' ? ',created_at' : '') . ") VALUES (?,?,?" . ($kind === 'save' ? ',?' : '') . ")")
                  ->execute($kind === 'save' ? [(int)$me['id'],$tt,$tid,date('Y-m-d H:i:s')] : [(int)$me['id'],$tt,$tid]); }
        catch (\PDOException $e) { if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e; }
    }
    redirect(input('return','/'));
}

/**
 * POST /destination/save — a "want to visit" bucket list, toggled on/off. Kept outside
 * react_action/RMT_INTERACT_TARGETS deliberately: destinations are global editorial rows with no
 * user_id/status columns (they are never a draft, never owned by a user), so the generic
 * rmt_can_interact() ownership/visibility check does not apply and would need a special case for
 * the one content type it was never meant to cover. Reuses the same `saves` table other saves
 * already use, just with target_type='destination'.
 */
function destination_save_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $did = (int) input('destination_id');
    if (!$did || !dest_by_id($did)) redirect(input('return', '/'));
    if (!rmt_rate_ok('react', (string)$me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect(input('return', '/'));
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
    redirect(input('return', '/'));
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
    $p   = $pid ? rmt_place_by_id($pid) : null;      // rmt_place_by_id already filters status='active'

    // `return` comes from the request, so it is normalised to a same-origin path before it is
    // followed -- an absolute URL to another host would turn this button into an open redirect.
    // Nothing posted means the place's own page, which is where the button lives.
    $posted = trim((string) input('return'));
    $return = $posted !== '' ? rmt_safe_return_path($posted) : ($p ? rmt_place_path($p) : '/');

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

    if (!in_array($type, RMT_REVIEW_VOTE_TYPES, true)) redirect(input('return', '/'));
    if (!rmt_can_interact('review', $rid, $me)) redirect(input('return', '/'));

    $r = q_one('SELECT user_id FROM reviews WHERE id=?', [$rid]);
    if (!$r) redirect(input('return', '/'));
    // Voting your own review up is not a signal from another traveler — it's not one at all.
    if ((int) $r['user_id'] === (int) $me['id']) redirect(input('return', '/'));

    if (!rmt_rate_ok('review_vote', (string) $me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect(input('return', '/'));
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
            redirect(input('return', '/'));
        }
        rmt_award_badges((int) $r['user_id']); // votes received can newly qualify the author for Elite Traveler
    }
    redirect(input('return', '/'));
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

    if (!isset(RMT_COMPLIMENT_TYPES[$type])) redirect(input('return', '/'));
    if (!$toId || $toId === (int) $me['id']) redirect(input('return', '/'));
    if (!q_one("SELECT 1 FROM users WHERE id=? AND status='active'", [$toId])) {
        flash('That traveler is no longer available.'); redirect(input('return', '/'));
    }
    if (rmt_is_blocked((int) $me['id'], $toId)) redirect(input('return', '/'));
    if (!rmt_rate_ok('compliment', (string) $me['id'], 40, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect(input('return', '/'));
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
    redirect(input('return', '/'));
}

function comment_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $tt   = (string) input('target_type');
    $tid  = (int) input('target_id');
    $body = trim((string) input('body'));

    if ($body === '' || !rmt_can_interact($tt, $tid, $me)) redirect(input('return','/'));
    // An over-limit comment used to be silently truncated at 2000 chars (mb_substr) with no
    // indication to the author -- silent data loss instead of the validation error every other
    // body-length limit in the app (trip/guide/review) gives.
    if (mb_strlen($body) > 2000) {
        flash('That comment is too long (2000 characters max). Please shorten it and try again.');
        redirect(input('return','/'));
    }
    if (!rmt_submit_ok('comment_'.$tt.'_'.$tid, input('_submit'))) {
        flash('That comment was already posted.'); redirect(input('return','/'));
    }
    if (!rmt_rate_ok('comment', (string)$me['id'], 30, 3600)) {
        flash('You are commenting very fast. Try again shortly.');
        redirect(input('return','/'));
    }

    q_run("INSERT INTO comments (user_id,target_type,target_id,body,status,created_at) VALUES (?,?,?,?, 'published', ?)",
        [(int)$me['id'], $tt, $tid, $body, date('Y-m-d H:i:s')]);

    // Tell the content's author someone commented (follows and compliments already notified, but
    // comments never did). Skip self-comments; @mentions in the body ping their own recipients,
    // minus the author if they were both mentioned and would get this comment notification.
    $owner = (int) (q_one('SELECT user_id FROM ' . RMT_INTERACT_TARGETS[$tt] . ' WHERE id=?', [$tid])['user_id'] ?? 0);
    if ($owner && $owner !== (int)$me['id']) {
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
            [$owner, 'comment', (int)$me['id'], $tt, $tid, date('Y-m-d H:i:s')]);
    }
    rmt_notify_mentions($tt, $tid, (int)$me['id'], [$owner], $body);
    redirect(input('return','/'));
}

/** POST /comment/{id}/delete — author only. Soft delete, same as reviews and trips. */
function comment_delete(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $c = q_one('SELECT * FROM comments WHERE id=?', [(int)$a['id']]);
    if (!$c) not_found();
    if ((int)$c['user_id'] !== (int)$me['id']) { forbidden('That is not your comment.'); }
    db()->prepare("UPDATE comments SET status='removed' WHERE id=?")->execute([(int)$c['id']]);
    redirect(input('return','/'));
}

function meetup_rsvp(array $a): void {
    require_login(); csrf_check(); $me=current_user();
    if (!can_host_meetups($me)) { flash('You must be 18+ to RSVP to meetups.'); redirect('/meetup/'.(int)$a['id']); }
    $mid=(int)$a['id']; $m=q_one('SELECT * FROM meetups WHERE id=?', [$mid]); if(!$m) not_found();
    $has = q_one('SELECT 1 FROM meetup_rsvps WHERE meetup_id=? AND user_id=?', [$mid,(int)$me['id']]);
    if ($has) db()->prepare('DELETE FROM meetup_rsvps WHERE meetup_id=? AND user_id=?')->execute([$mid,(int)$me['id']]);
    else {
        // Same double-click race as react_action/follow_action: the (meetup_id,user_id) primary
        // key stops a duplicate RSVP row, but without this catch the loser of the race got an
        // uncaught PDOException (500 page) instead of a no-op. This one was missed when the other
        // three toggle actions were fixed.
        try {
            db()->prepare("INSERT INTO meetup_rsvps (meetup_id,user_id,status) VALUES (?,?, 'going')")->execute([$mid,(int)$me['id']]);
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
    if (attempt_login($email, input('password'))) { flash('Welcome back.'); redirect($return); }
    view('auth/login', ['errors'=>['Incorrect email or password.'], 'return'=>$return], ['title'=>'Sign in — RuinMyTrip']);
}
function register_form(array $a): void { if (is_logged_in()) redirect('/feed'); view('auth/register', ['errors'=>[]], ['title'=>'Join RuinMyTrip']); }
function register_submit(array $a): void {
    csrf_check();
    if (!rmt_rate_ok('register_ip', rmt_client_ip(), 5, 3600)) {
        view('auth/register', ['errors'=>['Too many accounts created from this connection. Try again later.']],
             ['title'=>'Join RuinMyTrip']); return;
    }
    $r = register_user(input('username'), input('email'), input('password'), input('birthdate'));
    if ($r['ok']) {
        flash(($r['mail_ok'] ?? false)
            ? 'Welcome to RuinMyTrip. Check your email to confirm your address.'
            : 'Welcome to RuinMyTrip. We could not send the confirmation email — request a new link below.');
        redirect('/verify-email');
    }
    view('auth/register', ['errors'=>$r['errors']], ['title'=>'Join RuinMyTrip']);
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
    redirect('/feed');
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
function admin_resolve(array $a): void {
    require_role('admin','mod'); csrf_check(); $me = current_user();
    $rid = (int) input('report_id');
    $action = (string) input('action');
    $rep = q_one('SELECT * FROM reports WHERE id=?', [$rid]);
    if (!$rep) redirect('/admin');

    $tt = (string) $rep['target_type'];
    $table = RMT_REPORT_TARGETS[$tt] ?? null;

    // 'user' has no status column of this kind; suspending an account is a separate action.
    if ($table && $tt !== 'user' && in_array($action, ['hide','restore'], true)) {
        $newStatus = $action === 'hide' ? 'hidden' : 'published';
        db()->prepare("UPDATE {$table} SET status=? WHERE id=?")->execute([$newStatus, (int)$rep['target_id']]);
    }
    db()->prepare("UPDATE reports SET status='resolved', resolved_by=? WHERE id=?")
        ->execute([(int)$me['id'], $rid]);
    flash($action === 'hide' ? 'Content hidden and report resolved.'
         : ($action === 'restore' ? 'Content restored and report resolved.' : 'Report dismissed.'));
    redirect('/admin');
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
function sitemap(array $a): void {
    header('Content-Type: application/xml; charset=utf-8');
    $urls = [url(), url('explore'), url('discover'), url('reviews'), url('guides'), url('collections'), url('blog'), url('meetups'), url('going'),
             url('leaderboard'), url('tags'), url('editorial-policy'), url('terms'), url('privacy'),
             url('guidelines'), url('affiliate'), url('safety')];
    foreach (rmt_top_tags(100) as $t) $urls[] = url('tag/'.$t['name']);
    foreach (q_all('SELECT slug FROM destinations') as $d) $urls[] = url('d/'.$d['slug']);
    // Only destinations with at least one real traveler photo get a /photos page indexed --
    // an empty gallery is thin content, not a page worth ranking.
    foreach (q_all("SELECT DISTINCT d.slug FROM destinations d
                    WHERE EXISTS (SELECT 1 FROM trip_photos tp JOIN trips t ON t.id=tp.trip_id WHERE t.destination_id=d.id AND t.status='published')
                       OR EXISTS (SELECT 1 FROM review_photos rp JOIN reviews r ON r.id=rp.review_id WHERE r.destination_id=d.id AND r.status='published')") as $d) {
        $urls[] = url('d/'.$d['slug'].'/photos');
    }
    // Places index pages, and only places that actually have a published review on them. A place
    // whose reviews are all drafts is a real row but an empty page, and thin pages do not belong
    // in the sitemap (same rule the /photos galleries follow above).
    foreach (q_all("SELECT DISTINCT d.slug FROM destinations d JOIN places p ON p.destination_id=d.id
                    WHERE p.status='active'") as $d) $urls[] = url('d/'.$d['slug'].'/places');
    foreach (q_all("SELECT DISTINCT p.slug FROM places p JOIN reviews r ON r.place_id=p.id
                    WHERE p.status='active' AND r.status='published'") as $p) $urls[] = url('p/'.$p['slug']);
    foreach (q_all("SELECT id,slug FROM trips WHERE status='published'") as $t) $urls[] = url('trip/'.$t['id'].'/'.$t['slug']);
    foreach (q_all("SELECT slug FROM guides WHERE status='published'") as $g) $urls[] = url('g/'.$g['slug']);
    foreach (q_all("SELECT slug FROM collections WHERE status='published'") as $c) $urls[] = url('c/'.$c['slug']);
    foreach (q_all("SELECT slug FROM blog_posts WHERE status='published'") as $bp) $urls[] = url('blog/'.$bp['slug']);
    // Published reviews only — drafts/hidden/removed are never listed. Rows missing a slug
    // (pre-Phase-4) fall back to a generated one so the URL still resolves.
    foreach (q_all("SELECT id, slug, title, subject_name FROM reviews WHERE status='published'") as $rv) {
        $urls[] = url('review/'.$rv['id'].'/'.($rv['slug'] ?: rmt_review_slug($rv)));
    }
    foreach (q_all("SELECT username FROM users WHERE status='active'") as $u) $urls[] = url('u/'.$u['username']);
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach ($urls as $u) echo '  <url><loc>'.e($u).'</loc></url>'."\n";
    echo '</urlset>';
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
