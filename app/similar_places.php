<?php
/**
 * Similar places: alternatives to the one you are looking at.
 *
 * "Nearby" and "Similar" answer different questions and this file exists because collapsing them
 * into one list answers neither. Nearby is geographic -- what else is within walking distance,
 * whatever it is. Similar is about the entity: another hotel of roughly this kind, in this city,
 * at roughly this price. A traveler reading a hotel page wants both, and wants them labelled, and
 * would notice immediately if a module called "Similar hotels" were really "things near here that
 * happen to be hotels".
 *
 * SCORING is over signals we actually hold, and each contributes only when the value exists on
 * BOTH places. A missing price level is not a mismatch and not a match; it is silence, and silence
 * scores nothing rather than being read as agreement.
 *
 *   same type          required, not scored -- a restaurant is never similar to a museum
 *   same destination   required -- an alternative in another city is not an alternative
 *   same neighborhood  strong: the practical question is usually "somewhere else around here"
 *   same category      strong: a sushi counter and a brasserie are both restaurants
 *   same price band    moderate, and adjacent bands score partially -- budget and mid overlap
 *   proximity          mild, and only as a tiebreak: this is the SIMILAR list, not the near one
 *
 * Community rating and review volume are deliberately absent. Ranking alternatives by rating would
 * present an ordering as a recommendation, and with zero community reviews it would be an ordering
 * of nothing. When real reviews exist that becomes a real signal and can be added then.
 *
 * DUPLICATION is checked, not assumed. In a small city the similar list and the nearby list can be
 * the same three venues, and showing one list twice under two headings is worse than showing it
 * once: rmt_similar_is_redundant() answers that so the page can drop a module rather than repeat
 * itself.
 */

declare(strict_types=1);

/** How much each signal is worth. Tuned so category and neighborhood dominate and distance breaks ties. */
const RMT_SIM_W_NEIGHBORHOOD = 40.0;
const RMT_SIM_W_CATEGORY     = 35.0;
const RMT_SIM_W_PRICE_EXACT  = 15.0;
const RMT_SIM_W_PRICE_NEAR   = 7.0;
const RMT_SIM_W_PROXIMITY    = 10.0;

/** Beyond this, proximity contributes nothing: everything in the city is "somewhere else". */
const RMT_SIM_PROXIMITY_M = 3000;

/**
 * Alternatives to one place, best first.
 *
 * @param array $place a place row: id, type, destination_id, and whatever else we hold
 * @return list<array> place rows with a `similarity` score and, when both are located, `distance_m`
 */
function rmt_similar_places(array $place, int $limit = 6): array {
    $type = (string) ($place['type'] ?? '');
    $destId = (int) ($place['destination_id'] ?? 0);
    if ($type === '' || $destId <= 0) return [];

    $rows = q_all(
        "SELECT p.id, p.slug, p.name, p.type, p.category_id, p.neighborhood_id, p.neighborhood,
                p.price_level, p.lat, p.lng, d.name dest_name, d.slug dest_slug
           FROM places p JOIN destinations d ON d.id = p.destination_id
          WHERE p.status = 'active' AND p.destination_id = ? AND p.type = ? AND p.id <> ?
          LIMIT 300",
        [$destId, $type, (int) ($place['id'] ?? 0)]);
    if (!$rows) return [];

    $co = rmt_place_normalize_coords($place['lat'] ?? null, $place['lng'] ?? null);
    $myCat   = isset($place['category_id']) && $place['category_id'] !== null ? (int) $place['category_id'] : null;
    $myNb    = isset($place['neighborhood_id']) && $place['neighborhood_id'] !== null ? (int) $place['neighborhood_id'] : null;
    $myPrice = isset($place['price_level']) && $place['price_level'] !== null ? (int) $place['price_level'] : null;

    foreach ($rows as &$r) {
        $score = 0.0;

        if ($myNb !== null && $r['neighborhood_id'] !== null && (int) $r['neighborhood_id'] === $myNb) {
            $score += RMT_SIM_W_NEIGHBORHOOD;
        }
        if ($myCat !== null && $r['category_id'] !== null && (int) $r['category_id'] === $myCat) {
            $score += RMT_SIM_W_CATEGORY;
        }
        if ($myPrice !== null && $r['price_level'] !== null) {
            $gap = abs((int) $r['price_level'] - $myPrice);
            // Adjacent bands are a partial match on purpose: somebody looking at a mid-priced
            // restaurant is usually still interested in the cheaper one next door.
            if ($gap === 0)      $score += RMT_SIM_W_PRICE_EXACT;
            elseif ($gap === 1)  $score += RMT_SIM_W_PRICE_NEAR;
        }

        $r['distance_m'] = null;
        if ($co) {
            $rco = rmt_place_normalize_coords($r['lat'] ?? null, $r['lng'] ?? null);
            if ($rco) {
                $m = (int) round(rmt_geo_distance_m($co[0], $co[1], $rco[0], $rco[1]));
                $r['distance_m'] = $m;
                // Linear falloff, and only ever a tiebreak: this is the similar list, not the near
                // one, and a closer venue of the wrong kind must never outrank a matching one.
                if ($m < RMT_SIM_PROXIMITY_M) {
                    $score += RMT_SIM_W_PROXIMITY * (1.0 - $m / RMT_SIM_PROXIMITY_M);
                }
            }
        }
        $r['similarity'] = round($score, 2);
    }
    unset($r);

    // A row that matched on nothing at all is not an alternative, it is just another venue of the
    // same kind in the same city. Offering it under "Similar" would make the heading a lie.
    $rows = array_values(array_filter($rows, static fn(array $r) => $r['similarity'] > 0.0));

    usort($rows, static fn(array $a, array $b) => [$b['similarity'], -($a['distance_m'] ?? PHP_INT_MAX)]
                                              <=> [$a['similarity'], -($b['distance_m'] ?? PHP_INT_MAX)]);
    return array_slice($rows, 0, max(1, $limit));
}

/**
 * Would showing both lists show the same thing twice?
 *
 * In a city where we hold six places, "similar" and "nearby" are frequently the same three venues.
 * Two headings over one list is not two modules, it is one module and a wasted screen, so the page
 * asks this and drops the weaker one.
 *
 * The test is overlap of what would actually be DISPLAYED, not of the full candidate sets: two
 * lists that agree on their first three rows look identical to a reader no matter how they were
 * computed.
 */
function rmt_similar_is_redundant(array $similar, array $nearby, float $threshold = 0.6): bool {
    if (!$similar || !$nearby) return false;
    $a = array_map(static fn(array $r) => (int) $r['id'], $similar);
    $b = array_map(static fn(array $r) => (int) $r['id'], $nearby);
    $shared = count(array_intersect($a, $b));
    return $shared / (float) min(count($a), count($b)) >= $threshold;
}

/** "Similar hotels", "Other things to do nearby" -- the heading says which question it answers. */
function rmt_similar_heading(string $type): string {
    return match ($type) {
        'hotel'      => 'Similar hotels',
        'restaurant' => 'Similar restaurants',
        'attraction' => 'Other things to do',
        default      => 'Similar places',
    };
}
