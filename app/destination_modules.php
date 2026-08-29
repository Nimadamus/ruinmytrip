<?php
declare(strict_types=1);

/**
 * The data behind a destination page's discovery modules.
 *
 * Every module here answers a question a traveler actually asks — where to stay, where to eat,
 * what is worth doing, what people are saying right now — and every one is built from rows this
 * site holds. There is no module that exists to fill space, and a module with nothing behind it
 * returns an empty array so the page can leave it out entirely.
 *
 * Two rules shape the ranking:
 *
 *   1. ONE FIVE-STAR REVIEW IS NOT "TOP". A place needs RMT_TOP_MIN_REVIEWS community reviews
 *      before it can appear in a quality ranking at all, and even then its rating is pulled toward
 *      the destination's own average in proportion to how little evidence there is. What that
 *      guarantees is a compressed gap, not a reversal: a 5.0 from three people and a 4.7 from
 *      forty end up close together instead of a quarter-star apart, and the raw average stops
 *      being the thing that decides. It does not claim the 4.7 must win -- three people agreeing
 *      on 5.0 is real evidence too, and pretending otherwise would be its own distortion.
 *   2. QUALITY AND VOLUME ARE DIFFERENT STORIES. "Highest rated" and "most reviewed" are separate
 *      modules with separate orderings. A popular mediocre place and a quiet excellent one are
 *      both worth knowing about, and collapsing them into one list loses both.
 *
 * Editorial ratings never enter any of it. They are the site's own opinion, they are excluded by
 * role exactly as they are everywhere else, and a ranking that quietly included them would be the
 * site recommending itself.
 */

/**
 * How many community reviews a place needs before it can be called "top" or "highest rated".
 *
 * Three. Two people agreeing is a coincidence; three is the smallest number that reads as a
 * pattern, and it is the same threshold the aspect subratings use, so the page does not apply two
 * different standards to the same evidence. Places below it still appear in the ordinary lists
 * and on their own pages — the threshold governs the word "top", not visibility.
 */
const RMT_TOP_MIN_REVIEWS = 3;

/** How many cards a discovery row holds before it needs a "see all" link instead. */
const RMT_MODULE_SIZE = 6;

/**
 * Per-place community statistics for one destination, in a single grouped query.
 *
 * Everything the ranking modules need comes from here: count, average, most recent review. One
 * query for the whole page rather than one per card, which is the difference between a destination
 * page that stays fast at ten thousand places and one that quietly does not.
 *
 * @return list<array<string,mixed>>
 */
function rmt_destination_place_stats(int $destId): array {
    if ($destId <= 0) return [];
    return q_all(
        "SELECT p.id, p.slug, p.name, p.type, p.category_id, p.neighborhood, p.price_level,
                COUNT(r.id) review_count,
                AVG(r.rating * 1.0) rating_avg,
                MAX(r.created_at) last_review_at
           FROM places p
           JOIN reviews r ON r.place_id = p.id AND r.status = 'published'
           JOIN users u   ON u.id = r.user_id AND u.role <> ?
          WHERE p.destination_id = ? AND p.status = 'active'
          GROUP BY p.id, p.slug, p.name, p.type, p.category_id, p.neighborhood, p.price_level",
        [RMT_EDITORIAL_ROLE, $destId]);
}

/**
 * How many reviews before a place's own average is trusted on its own terms.
 *
 * Ten, and deliberately not the same number as the eligibility threshold: those answer different
 * questions. Three reviews is enough to be worth ranking at all; ten is roughly where an average
 * stops swinging on one more opinion. Using the eligibility threshold as the prior made the
 * shrinkage almost inert -- a 5.0 from three barely moved -- which is the failure this constant
 * exists to avoid.
 */
const RMT_RATING_PRIOR = 10;

/**
 * A rating that accounts for how little we might know.
 *
 * The weighted form (v/(v+m))·R + (m/(v+m))·C: a place's own average R pulled toward the
 * destination's mean C, with the pull decided by how the review count v compares to the prior m.
 * Three reviews at 5.0 land most of the way back toward the city's average; forty at 4.7 land
 * nearly at 4.7. This is the whole reason "top" means something here rather than "was reviewed
 * once by a friend".
 */
function rmt_weighted_rating(float $avg, int $count, float $destMean, int $m = RMT_RATING_PRIOR): float {
    if ($count <= 0) return 0.0;
    return (($count / ($count + $m)) * $avg) + (($m / ($count + $m)) * $destMean);
}

/** The mean community rating across a destination's reviewed places; the value ratings shrink toward. */
function rmt_destination_mean(array $stats): float {
    $n = 0; $sum = 0.0;
    foreach ($stats as $s) {
        $n += (int) $s['review_count'];
        $sum += (float) $s['rating_avg'] * (int) $s['review_count'];
    }
    return $n > 0 ? $sum / $n : 0.0;
}

/**
 * The destination page's ranked modules, all derived from the one stats query.
 *
 * @return array{
 *   top:array<string,list<array<string,mixed>>>,
 *   highest_rated:list<array<string,mixed>>,
 *   most_reviewed:list<array<string,mixed>>,
 *   qualified:int, mean:float
 * }
 */
function rmt_destination_rankings(int $destId, int $size = RMT_MODULE_SIZE): array {
    $stats = rmt_destination_place_stats($destId);
    $empty = ['top' => [], 'highest_rated' => [], 'most_reviewed' => [], 'qualified' => 0, 'mean' => 0.0];
    if (!$stats) return $empty;

    $mean = rmt_destination_mean($stats);
    foreach ($stats as &$s) {
        $s['review_count'] = (int) $s['review_count'];
        $s['rating_avg']   = round((float) $s['rating_avg'], 1);
        $s['weighted']     = rmt_weighted_rating((float) $s['rating_avg'], $s['review_count'], $mean);
    }
    unset($s);

    // Only places with enough reviews behind them may be ranked on quality.
    $qualified = array_values(array_filter($stats, static fn($s) => $s['review_count'] >= RMT_TOP_MIN_REVIEWS));

    $byWeighted = $qualified;
    usort($byWeighted, static fn($a, $b) => [$b['weighted'], $b['review_count']] <=> [$a['weighted'], $a['review_count']]);

    // Volume is its own story and needs no quality threshold: "most reviewed" is a fact about how
    // much has been written, not a recommendation.
    $byVolume = $stats;
    usort($byVolume, static fn($a, $b) => [$b['review_count'], $b['weighted']] <=> [$a['review_count'], $a['weighted']]);

    // Per kind of place, so "where should I stay" and "where should I eat" are separate answers.
    $top = [];
    foreach (RMT_PLACE_TYPES as $type) {
        $rows = array_values(array_filter($byWeighted, static fn($s) => $s['type'] === $type));
        if ($rows) $top[$type] = array_slice($rows, 0, $size);
    }

    return [
        'top'           => $top,
        'highest_rated' => array_slice($byWeighted, 0, $size),
        'most_reviewed' => array_slice($byVolume, 0, $size),
        'qualified'     => count($qualified),
        'mean'          => round($mean, 2),
    ];
}

/**
 * Cover images for a set of places, in one query.
 *
 * A place's own cover first, then any traveler photo attached to a review of it. Returns only the
 * places that actually have an image: a card with no photo renders without one, and never with a
 * placeholder standing in for a photograph nobody took.
 *
 * @param  list<int> $placeIds
 * @return array<int,string> place id => url
 */
function rmt_place_cover_map(array $placeIds): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $placeIds), static fn(int $i) => $i > 0)));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));

    $out = [];
    foreach (q_all("SELECT place_id, storage_key, url, is_cover FROM place_photos
                     WHERE place_id IN ({$in}) AND status = 'published'
                     ORDER BY is_cover DESC, sort, id", $ids) as $r) {
        $pid = (int) $r['place_id'];
        if (isset($out[$pid])) continue;
        $u = rmt_place_photo_url($r);
        if ($u !== '') $out[$pid] = $u;
    }

    $missing = array_values(array_diff($ids, array_keys($out)));
    if ($missing) {
        $in2 = implode(',', array_fill(0, count($missing), '?'));
        foreach (q_all("SELECT r.place_id, rp.storage_key, rp.url
                          FROM review_photos rp JOIN reviews r ON r.id = rp.review_id
                         WHERE r.place_id IN ({$in2}) AND r.status = 'published'
                         ORDER BY rp.created_at DESC, rp.id DESC", $missing) as $r) {
            $pid = (int) $r['place_id'];
            if (isset($out[$pid])) continue;
            $u = rmt_place_photo_url($r);
            if ($u !== '') $out[$pid] = $u;
        }
    }
    return $out;
}

/** Subcategory names for a set of category ids, in one query. */
function rmt_category_name_map(array $categoryIds): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $categoryIds), static fn(int $i) => $i > 0)));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $out = [];
    foreach (q_all("SELECT id, name FROM place_categories WHERE id IN ({$in})", $ids) as $r) {
        $out[(int) $r['id']] = (string) $r['name'];
    }
    return $out;
}

/**
 * What travelers are saying about this destination's places right now.
 *
 * Reviews OF PLACES, newest first, with enough context to be worth reading: which place, what kind,
 * the rating, an excerpt, who wrote it and when. Editorial excluded. Destination-level reviews are
 * not here — they already have their own section further down the page, and repeating them would
 * make this read like a log rather than a signal.
 */
function rmt_destination_recent_reviews(int $destId, int $limit = 5): array {
    $rows = q_all(
        "SELECT r.id, r.slug, r.title, r.body, r.rating, r.created_at, r.user_id,
                p.slug place_slug, p.name place_name, p.type place_type, p.neighborhood
           FROM reviews r
           JOIN places p ON p.id = r.place_id AND p.status = 'active'
           JOIN users u  ON u.id = r.user_id AND u.role <> ?
          WHERE p.destination_id = ? AND r.status = 'published'
          ORDER BY r.created_at DESC, r.id DESC
          LIMIT " . max(1, $limit), [RMT_EDITORIAL_ROLE, $destId]);
    if (function_exists('authors_fill')) authors_fill($rows);
    return $rows;
}

/**
 * Neighborhoods a destination's places actually sit in, with counts.
 *
 * Read from the places themselves, never invented, and only ones with more than one place behind
 * them: a "neighborhood" naming exactly one venue is that venue's address, not an area worth
 * offering as a way to browse. No neighborhood pages exist yet and these do not link to any; this
 * is the entity relationship arriving before the routes that will use it.
 *
 * @return list<array{name:string,places:int}>
 */
function rmt_destination_neighborhoods(int $destId, int $limit = 12): array {
    $rows = q_all(
        "SELECT p.neighborhood name, COUNT(*) places
           FROM places p
          WHERE p.destination_id = ? AND p.status = 'active'
            AND p.neighborhood IS NOT NULL AND p.neighborhood <> ''
          GROUP BY p.neighborhood
         HAVING COUNT(*) > 1
          ORDER BY COUNT(*) DESC, p.neighborhood
          LIMIT " . max(1, $limit), [$destId]);
    foreach ($rows as &$r) $r['places'] = (int) $r['places'];
    return $rows;
}

/**
 * Everything a destination page's discovery section needs, assembled once.
 *
 * Deliberately one entry point: the modules share a stats query, a cover-image lookup and a
 * category lookup, and building them separately would run each of those several times over. Six
 * bounded queries for the whole section, whatever the destination's size.
 */
function rmt_destination_discovery(int $destId, int $size = RMT_MODULE_SIZE): array {
    $rank = rmt_destination_rankings($destId, $size);

    // One cover lookup and one category lookup for every card on the page, not per module.
    $ids = [];
    $cats = [];
    foreach (array_merge([$rank['highest_rated'], $rank['most_reviewed']], array_values($rank['top'])) as $list) {
        foreach ($list as $row) {
            $ids[] = (int) $row['id'];
            $cats[] = (int) ($row['category_id'] ?? 0);
        }
    }
    $covers = rmt_place_cover_map($ids);
    $catNames = rmt_category_name_map($cats);

    $decorate = static function (array $list) use ($covers, $catNames): array {
        foreach ($list as &$row) {
            $row['cover_url'] = $covers[(int) $row['id']] ?? null;
            $row['category_name'] = $catNames[(int) ($row['category_id'] ?? 0)] ?? null;
        }
        unset($row);
        return $list;
    };

    $top = [];
    foreach ($rank['top'] as $type => $list) $top[$type] = $decorate($list);

    return [
        'top'           => $top,
        'highest_rated' => $decorate($rank['highest_rated']),
        'most_reviewed' => $decorate($rank['most_reviewed']),
        'qualified'     => $rank['qualified'],
        'mean'          => $rank['mean'],
        'recent'        => rmt_destination_recent_reviews($destId, 5),
        'neighborhoods' => rmt_destination_neighborhoods($destId),
        'counts'        => rmt_place_type_counts($destId),
    ];
}

/**
 * Per-destination completeness, for deciding where the data is strong enough to build on.
 *
 * Internal. It is a map of where the material is, not a scoreboard, and it is the thing that will
 * eventually say which destinations can carry a category landing page without it being thin.
 */
function rmt_destination_quality(int $limit = 300): array {
    return q_all(
        "SELECT d.id, d.slug, d.name, d.country,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active') places,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active' AND p.type = 'hotel') hotels,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active' AND p.type = 'restaurant') restaurants,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active' AND p.type = 'attraction') attractions,
                (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active' AND p.lat IS NOT NULL) located,
                (SELECT COUNT(DISTINCT p.neighborhood) FROM places p
                  WHERE p.destination_id = d.id AND p.status = 'active'
                    AND p.neighborhood IS NOT NULL AND p.neighborhood <> '') neighborhoods,
                (SELECT COUNT(*) FROM reviews r JOIN users u ON u.id = r.user_id
                  WHERE r.destination_id = d.id AND r.status = 'published' AND u.role <> ?) reviews,
                (SELECT MAX(r.created_at) FROM reviews r
                  WHERE r.destination_id = d.id AND r.status = 'published') last_review_at
           FROM destinations d
          ORDER BY places DESC, d.name
          LIMIT " . max(1, $limit), [RMT_EDITORIAL_ROLE]);
}
