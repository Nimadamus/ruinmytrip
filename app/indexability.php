<?php
/**
 * One place that answers: should this page be in Google's index, and why not?
 *
 * Before this, the answer was scattered. The robots meta was hardcoded to "index, follow" on every
 * page in the site, the sitemap had its own separate opinion expressed as twenty hand-written
 * queries, and canonical URLs were decided per controller. Three mechanisms, no shared rule, and
 * nothing that could be asked what it thought about a given page.
 *
 * That is survivable at 575 URLs and indefensible at ten thousand, because the failure mode of
 * programmatic pages is not that they rank badly -- it is that thousands of near-empty pages get
 * indexed, the site is judged on its thinnest content, and by the time that shows up in Search
 * Console the damage is spread across every page. The defence is a threshold that exists in one
 * place, is testable, and can be READ: rmt_indexable() returns a reason code, not just a boolean,
 * so "why is this page not in the index" has an answer somebody can look up rather than reason out
 * from templates.
 *
 * THE THREE MUST AGREE. robots, canonical and sitemap inclusion all derive from this function. A
 * URL that says noindex while sitting in the sitemap is asking a crawler to spend budget on
 * something it is then told to ignore, and it is a state that only ever arises when two systems
 * hold separate opinions. Here there is one opinion.
 *
 * A NOTE ON WHAT IS NOT REQUIRED. A legitimate place page does not need community reviews to be
 * indexed. It is a real venue with a real address and real hours, and that is useful whether or not
 * anybody has reviewed it yet. Requiring reviews would keep the entire site out of the index while
 * waiting for the reviews that the index is supposed to help us get.
 */

declare(strict_types=1);

/**
 * Why a page is or is not in the index. A closed list because these end up in dashboards, logs and
 * conversations, and a free-text reason would be a different sentence every time.
 */
const RMT_INDEX_REASONS = [
    'indexable'                    => 'Indexable',
    'noindex_admin'                => 'Administrative page',
    'noindex_private'              => 'Not public',
    'noindex_filter'               => 'A filtered or sorted view of another page',
    'noindex_duplicate'            => 'Duplicate of a canonical page',
    'noindex_thin'                 => 'Too little content to be worth a result',
    'noindex_no_content'           => 'No useful content on the page',
    'noindex_insufficient_places'  => 'Not enough real places behind it',
    'noindex_insufficient_density' => 'Enough places, but too little variety to browse',
    'noindex_empty_profile'        => 'A profile with no published contributions',
    'noindex_unlisted_entity'      => 'The entity is not the kind we publish',
];

/* ------------------------------------------------------------------ thresholds
   Every number that decides whether a page exists in search lives here, spelled out, so that
   changing one is a deliberate act with a diff rather than an edit inside a template. */

/** A destination is a real page when it has places to browse OR editorial written about it. */
const RMT_IDX_DEST_MIN_PLACES = 1;

/**
 * A neighborhood page needs enough behind it to beat the destination page it sits under.
 *
 * Four places, and at least two kinds of place. The second condition is the one that matters: four
 * hotels in an area is a hotel list, not a neighborhood guide, and the destination's own hotel
 * browse already does that job better.
 */
const RMT_IDX_NB_MIN_PLACES = 4;
const RMT_IDX_NB_MIN_TYPES  = 2;

/**
 * "Hotels in Paris" has to actually be a list of hotels in Paris.
 *
 * Six is deliberately not three. A page targeting a high-intent query competes with sites listing
 * hundreds, and arriving on a page of three is a worse experience than not arriving at all -- which
 * is a ranking signal as well as a courtesy.
 */
const RMT_IDX_CAT_MIN_PLACES = 6;

/** A list worth a result is one somebody built and described, not two places and a working title. */
const RMT_IDX_LIST_MIN_ITEMS = 4;

/* A community additionally needs people in it. That threshold is RMT_COMMUNITY_MIN_MEMBERS and it
   lives in app/communities.php with the rest of the membership rules, because it decides who is
   shown a community as well as whether it is indexed, and one number cannot live in two files. */

/**
 * The verdict for one entity.
 *
 * @param string $type  destination|place|neighborhood|category|profile|list|guide|blog|review|trip|static|filter|admin
 * @param array  $e     the entity row, plus any counts the caller has already computed
 * @return array{ok:bool,reason:string,detail:string}
 */
function rmt_indexable(string $type, array $e = []): array {
    $yes = static fn(): array => ['ok' => true, 'reason' => 'indexable', 'detail' => ''];
    $no  = static fn(string $r, string $d = ''): array => ['ok' => false, 'reason' => $r, 'detail' => $d];

    switch ($type) {
        // Never, under any circumstances. These are actions, private views, or infinite.
        case 'admin':
            return $no('noindex_admin');
        case 'filter':
        case 'search':
            return $no('noindex_filter', 'sorted and filtered views canonicalise to the unfiltered page');
        case 'private':
            return $no('noindex_private');

        case 'static':
            return $yes();

        case 'destination':
            if (($e['status'] ?? 'active') !== 'active') return $no('noindex_private');
            $places = (int) ($e['place_count'] ?? 0);
            $hasText = trim((string) ($e['body'] ?? '')) !== '' || trim((string) ($e['summary'] ?? '')) !== '';
            if ($places < RMT_IDX_DEST_MIN_PLACES && !$hasText) {
                return $no('noindex_thin', 'no places and nothing written about it');
            }
            return $yes();

        case 'place':
            $pstatus = rmt_place_status((string) ($e['status'] ?? ''));
            if (!in_array($pstatus, RMT_PLACE_PUBLIC_STATUSES, true)) return $no('noindex_private');
            // A closed place is a real answer to a real search -- somebody typing the name of a
            // restaurant that shut deserves to be told it shut, by us, rather than to find a page
            // that still says "Open now" somewhere else. But it earns that only if there is
            // something here: reviews or our own writing. A closed listing with nothing but a name
            // is a dead end wearing a page's clothes.
            if ($pstatus === 'permanently_closed') {
                $hasHistory = (int) ($e['review_count'] ?? 0) > 0
                           || trim((string) ($e['editorial'] ?? '')) !== ''
                           || (int) ($e['photo_count'] ?? 0) > 0;
                if (!$hasHistory) return $no('noindex_thin', 'closed, and nothing was ever written about it');
            }
            if (empty($e['destination_id'])) return $no('noindex_thin', 'not attached to a destination');
            // Useful content is anything that answers a question somebody would arrive with: where
            // it is, when it is open, what it costs, what we wrote about it, or what a traveler
            // said. Community reviews are one of these, deliberately not required.
            $useful = !empty($e['street_address']) || !empty($e['lat']) || !empty($e['website_url'])
                   || !empty($e['phone']) || (int) ($e['hours_count'] ?? 0) > 0
                   || (int) ($e['review_count'] ?? 0) > 0 || (int) ($e['photo_count'] ?? 0) > 0
                   || trim((string) ($e['editorial'] ?? '')) !== '';
            if (!$useful) return $no('noindex_no_content', 'a name and a type, and nothing else');
            return $yes();

        case 'neighborhood':
            if (!in_array((string) ($e['kind'] ?? ''), RMT_NB_BROWSABLE, true)) {
                return $no('noindex_unlisted_entity', 'a borough or administrative unit, not a neighborhood');
            }
            $places = (int) ($e['place_count'] ?? 0);
            if ($places < RMT_IDX_NB_MIN_PLACES) {
                return $no('noindex_insufficient_places',
                           sprintf('%d place%s, needs %d', $places, $places === 1 ? '' : 's', RMT_IDX_NB_MIN_PLACES));
            }
            if ((int) ($e['type_count'] ?? 0) < RMT_IDX_NB_MIN_TYPES) {
                return $no('noindex_insufficient_density', 'only one kind of place in it');
            }
            return $yes();

        case 'category':
            $places = (int) ($e['place_count'] ?? 0);
            if ($places < RMT_IDX_CAT_MIN_PLACES) {
                return $no('noindex_insufficient_places',
                           sprintf('%d place%s, needs %d', $places, $places === 1 ? '' : 's', RMT_IDX_CAT_MIN_PLACES));
            }
            return $yes();

        case 'profile':
            if (($e['status'] ?? '') !== 'active') return $no('noindex_private');
            $contrib = (int) ($e['review_count'] ?? 0) + (int) ($e['guide_count'] ?? 0)
                     + (int) ($e['trip_count'] ?? 0) + (int) ($e['list_count'] ?? 0);
            if ($contrib < 1) return $no('noindex_empty_profile', 'nothing published yet');
            return $yes();

        case 'list':
            if (($e['status'] ?? '') !== 'published') return $no('noindex_private');
            $items = (int) ($e['item_count'] ?? 0);
            if ($items < RMT_IDX_LIST_MIN_ITEMS) {
                return $no('noindex_thin', sprintf('%d item%s, needs %d', $items, $items === 1 ? '' : 's', RMT_IDX_LIST_MIN_ITEMS));
            }
            // A list with no words is a set of links. Somebody's ordering is the content, and
            // without a description there is nothing on the page a search result could quote.
            if (trim((string) ($e['summary'] ?? '')) === '') {
                return $no('noindex_thin', 'no description of what the list is for');
            }
            // A community is a room, and a room with only its founder in it is an empty room. It
            // keeps its URL, so an invite link always works and a member can always reach it; what
            // it does not get is a stranger arriving from a search result to find nobody there.
            // Same judgement the category pilot makes about places, applied to people.
            if (in_array($e['join_policy'] ?? 'closed', ['open', 'invite'], true)) {
                $members = (int) ($e['member_count'] ?? 0);
                if ($members < RMT_COMMUNITY_MIN_MEMBERS) {
                    return $no('noindex_thin', sprintf('%d member%s, needs %d',
                        $members, $members === 1 ? '' : 's', RMT_COMMUNITY_MIN_MEMBERS));
                }
            }
            return $yes();

        case 'guide':
        case 'blog':
        case 'review':
        case 'trip':
            if (($e['status'] ?? '') !== 'published') return $no('noindex_private');
            return $yes();

        /* A post is chatter until it is not. "anyone in Lisbon in June?" is a fine thing to say to
           other members and a terrible search result, so a post earns a place in the index by
           being substantial on its own or by having drawn an actual conversation. */
        case 'post':
            if (($e['status'] ?? '') !== 'published') return $no('noindex_private');
            $len = mb_strlen(trim(strip_tags((string) ($e['body'] ?? ''))));
            $replies = (int) ($e['reply_count'] ?? 0);
            if ($len < RMT_POST_INDEX_MIN && $replies < 1) {
                return $no('noindex_thin', sprintf('%d chars, %d replies, needs %d chars or a reply',
                    $len, $replies, RMT_POST_INDEX_MIN));
            }
            return $yes();

        default:
            return $no('noindex_unlisted_entity', $type);
    }
}

/** The robots meta a verdict implies. noindex,follow always: the links are still worth crawling. */
function rmt_robots_for(array $verdict): string {
    return $verdict['ok'] ? 'index, follow' : 'noindex,follow';
}

/** Human wording for a reason code, for dashboards and logs. */
function rmt_index_reason_label(string $code): string {
    return RMT_INDEX_REASONS[$code] ?? $code;
}

/* ------------------------------------------------------------------ batch verdicts

   The sitemap and the readiness dashboard both need a verdict for every entity of a kind. Asking
   per row would be a query per page; each of these is ONE query for the whole set. */

/**
 * How much a destination actually has behind it, and what it is short of.
 *
 * An internal prioritisation tool, not a public ranking and not a score anybody is shown. It exists
 * to answer one question before editorial work starts: which cities are worth deepening, and what
 * is missing from them. Every figure is a count of real rows, so a destination cannot rise up this
 * list by being described enthusiastically.
 *
 * Deliberately built from the queries that already exist rather than a new scoring service. The
 * "depth" number is a crude sum and is meant to be -- it orders a list of eighty-four cities so a
 * person can look at the top ten, and nothing downstream reads it.
 *
 * @return list<array>
 */
function rmt_destination_depth(): array {
    $rows = q_all(
        "SELECT d.id, d.slug, d.name, d.country,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active') places,
                (SELECT COUNT(DISTINCT p.type) FROM places p WHERE p.destination_id = d.id AND p.status = 'active') kinds,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active' AND p.type = 'hotel') hotels,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active' AND p.type = 'restaurant') restaurants,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active' AND p.type = 'attraction') attractions,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active'
                   AND (p.street_address IS NOT NULL AND p.street_address <> '')) located,
                (SELECT COUNT(*) FROM neighborhoods n WHERE n.destination_id = d.id) areas,
                (SELECT COUNT(*) FROM guides g WHERE g.destination_id = d.id AND g.status = 'published') guides,
                (SELECT COUNT(*) FROM destination_tips t WHERE t.destination_id = d.id) tips,
                (SELECT COUNT(*) FROM reviews r JOIN users u ON u.id = r.user_id
                  WHERE r.destination_id = d.id AND r.status = 'published' AND u.role <> ?) community
           FROM destinations d", [RMT_EDITORIAL_ROLE]);

    foreach ($rows as &$r) {
        foreach (['places','kinds','hotels','restaurants','attractions','located','areas','guides','tips','community'] as $k) {
            $r[$k] = (int) $r[$k];
        }
        // Weighted toward the things that make a hub useful rather than long: real places first,
        // then variety, then areas to browse by, then what we have written.
        $r['depth'] = $r['places'] * 3 + $r['kinds'] * 4 + $r['areas'] * 2
                    + $r['guides'] * 3 + min($r['tips'], 6) + $r['community'] * 2;

        // Gaps, in the words a person would use to commission the work.
        $gaps = [];
        if ($r['places'] < 6)        $gaps[] = 'thin inventory (' . $r['places'] . ' places)';
        if ($r['hotels'] === 0)      $gaps[] = 'no hotels';
        if ($r['restaurants'] < 3)   $gaps[] = 'few restaurants (' . $r['restaurants'] . ')';
        if ($r['attractions'] < 3)   $gaps[] = 'few things to do (' . $r['attractions'] . ')';
        if ($r['areas'] === 0)       $gaps[] = 'no neighborhoods mapped';
        if ($r['guides'] === 0)      $gaps[] = 'no guide written';
        if ($r['located'] < $r['places']) {
            $gaps[] = ($r['places'] - $r['located']) . ' places with no address';
        }
        if ($r['community'] === 0)   $gaps[] = 'no traveler reviews';
        $r['gaps'] = $gaps;
    }
    unset($r);

    usort($rows, static fn($a, $b) => [$b['depth'], $b['places']] <=> [$a['depth'], $a['places']]);
    return $rows;
}

/** @return list<array{slug:string,name:string,verdict:array,place_count:int}> */
function rmt_index_destinations(): array {
    // destinations has no body column -- the written content lives in destination_tips and in the
    // editorial reviews attached to the city. Reading a column that only existed in a test fixture
    // is how the first version of this aborted sitemap generation in production while passing
    // every test locally.
    $rows = q_all(
        "SELECT d.id, d.slug, d.name, d.country, d.summary,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active') place_count,
                (SELECT COUNT(*) FROM destination_tips t WHERE t.destination_id = d.id) tip_count,
                (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = d.id AND r.status = 'published') dest_review_count
           FROM destinations d ORDER BY d.name");
    foreach ($rows as &$r) {
        $r['place_count'] = (int) $r['place_count'];
        // "Written about" means tips, an editorial review, or a summary -- whichever exists.
        $r['body'] = ((int) $r['tip_count'] + (int) $r['dest_review_count']) > 0 ? 'y' : '';
        $r['verdict'] = rmt_indexable('destination', $r);
    }
    return $rows;
}

/** @return list<array{slug:string,name:string,verdict:array}> */
function rmt_index_places(): array {
    $rows = q_all(
        "SELECT p.id, p.slug, p.name, p.type, p.status, p.destination_id, p.street_address, p.lat,
                p.website_url, p.phone, p.updated_at, p.created_at,
                (SELECT COUNT(*) FROM place_hours h WHERE h.place_id = p.id) hours_count,
                (SELECT COUNT(*) FROM place_photos ph WHERE ph.place_id = p.id) photo_count,
                (SELECT COUNT(*) FROM reviews r WHERE r.place_id = p.id AND r.status = 'published') review_count
           FROM places p WHERE p.status IN ('active','temporarily_closed','permanently_closed','closed')
          ORDER BY p.name");
    // Closed places are handed to the rule rather than filtered out before it, or the decision
    // about whether a closed page belongs in the index would be made here, silently, by a WHERE
    // clause -- which is exactly the second opinion this layer exists to abolish.
    foreach ($rows as &$r) $r['verdict'] = rmt_indexable('place', $r);
    return $rows;
}

/** @return list<array{slug:string,dest_slug:string,name:string,verdict:array}> */
function rmt_index_neighborhoods(): array {
    $rows = q_all(
        "SELECT n.id, n.slug, n.canonical_name name, n.kind, d.slug dest_slug, d.name dest_name,
                (SELECT COUNT(*) FROM places p WHERE p.neighborhood_id = n.id AND p.status = 'active') place_count,
                (SELECT COUNT(DISTINCT p.type) FROM places p WHERE p.neighborhood_id = n.id AND p.status = 'active') type_count
           FROM neighborhoods n JOIN destinations d ON d.id = n.destination_id
          ORDER BY d.name, n.canonical_name");
    foreach ($rows as &$r) {
        $r['place_count'] = (int) $r['place_count'];
        $r['type_count'] = (int) $r['type_count'];
        $r['verdict'] = rmt_indexable('neighborhood', $r);
    }
    return $rows;
}

/**
 * Every destination-and-category combination, with its verdict.
 *
 * This is the set the SEO pilot draws from, so it exists whether or not a page is live for each
 * row: knowing that "Restaurants in Vienna" has four places and does not qualify is the point.
 *
 * @return list<array{dest_slug:string,type:string,place_count:int,verdict:array}>
 */
function rmt_index_categories(): array {
    // Counted from places that are THEMSELVES indexable, not from every active row. Six empty
    // shells is not six places, and a threshold that can be met by rows carrying nothing but a
    // name is not a threshold -- it is a counter. This is the whole reason the number exists.
    $byDest = [];
    foreach (rmt_index_places() as $p) {
        if (!$p['verdict']['ok']) continue;
        // Indexable is not the same as open. A closed place can deserve its own page and still
        // must not be counted as inventory on "Restaurants in Paris", which is a promise about
        // where you can eat tonight.
        if (!rmt_place_is_trading((string) $p['status'])) continue;
        $byDest[(int) $p['destination_id']][(string) $p['type']] =
            ($byDest[(int) $p['destination_id']][(string) $p['type']] ?? 0) + 1;
    }
    $dests = [];
    foreach (q_all("SELECT id, slug, name FROM destinations") as $d) $dests[(int) $d['id']] = $d;

    $rows = [];
    foreach ($byDest as $destId => $types) {
        if (!isset($dests[$destId])) continue;
        foreach ($types as $type => $n) {
            $row = ['dest_slug' => (string) $dests[$destId]['slug'],
                    'dest_name' => (string) $dests[$destId]['name'],
                    'type' => $type, 'place_count' => (int) $n];
            $row['verdict'] = rmt_indexable('category', $row);
            $rows[] = $row;
        }
    }
    usort($rows, static fn($a, $b) => [$b['place_count'], $a['dest_name'], $a['type']]
                                  <=> [$a['place_count'], $b['dest_name'], $b['type']]);
    return $rows;
}

/**
 * Indexable places in one destination, by type -- the count a category page is judged on.
 *
 * The landing page and the threshold have to be looking at the same number, or the page says
 * "7 hotels in Paris" above a verdict that counted six.
 *
 * @return array<string,int>
 */
function rmt_indexable_type_counts(int $destId): array {
    $out = [];
    foreach (rmt_index_places() as $p) {
        if (!$p['verdict']['ok'] || (int) $p['destination_id'] !== $destId) continue;
        if (!rmt_place_is_trading((string) $p['status'])) continue;
        $out[(string) $p['type']] = ($out[(string) $p['type']] ?? 0) + 1;
    }
    return $out;
}

/** @return list<array{username:string,verdict:array}> */
function rmt_index_profiles(): array {
    $rows = q_all(
        "SELECT u.id, u.username, u.status,
                (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id AND r.status = 'published') review_count,
                (SELECT COUNT(*) FROM guides g WHERE g.user_id = u.id AND g.status = 'published') guide_count,
                (SELECT COUNT(*) FROM trips t WHERE t.user_id = u.id AND t.status = 'published') trip_count,
                (SELECT COUNT(*) FROM collections c WHERE c.user_id = u.id AND c.status = 'published') list_count
           FROM users u WHERE u.status = 'active' ORDER BY u.username");
    foreach ($rows as &$r) $r['verdict'] = rmt_indexable('profile', $r);
    return $rows;
}

/** @return list<array{slug:string,title:string,verdict:array}> */
function rmt_index_lists(): array {
    $rows = q_all(
        "SELECT c.id, c.slug, c.title, c.summary, c.status, c.updated_at, c.created_at, c.join_policy,
                (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id = c.id) item_count,
                (SELECT COUNT(*) FROM collection_members m
                  WHERE m.collection_id = c.id AND m.status = 'active') member_count
           FROM collections c WHERE c.status = 'published' ORDER BY c.title");
    foreach ($rows as &$r) {
        $r['item_count'] = (int) $r['item_count'];
        // The sitemap has to ask the same question the page asks, with the same facts in hand, or
        // the two hold separate opinions and a noindex URL ends up listed.
        $r['member_count'] = (int) $r['member_count'];
        $r['verdict'] = rmt_indexable('list', $r);
    }
    return $rows;
}
