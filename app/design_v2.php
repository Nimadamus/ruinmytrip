<?php
declare(strict_types=1);

/**
 * The 2026-10 redesign switches (homepage, navigation, explore, city, profile).
 *
 *   RMT_DESIGN_V2=home,nav     the listed surfaces use the new design for everybody
 *   RMT_DESIGN_V2=all          every surface
 *   unset (the default)        the current site, untouched
 *
 * A preview for one browser: ?design=v2 on any page sets a cookie that shows every new surface on
 * every page until ?design=v1 (which also forces the current design while a surface is switched on
 * for everybody) or ?design=off. A crawler sends no cookie, so it always sees what the switches say;
 * any URL carrying ?design= answers noindex (view()), so a preview can never compete with the page.
 *
 * Titles, descriptions, canonicals and structured data come from the controllers and are the same
 * in both designs. Turning a surface off is a complete rollback for it.
 */

const RMT_DESIGN_SURFACES = ['nav', 'home', 'explore', 'city', 'profile', 'place'];
const RMT_DESIGN_COOKIE = 'rmt_design';

/** Apply a ?design= request to this browser's preview cookie. Called once from bootstrap. */
function rmt_design_init(): void {
    $ask = (string) ($_GET['design'] ?? '');
    if (!in_array($ask, ['v1', 'v2', 'off'], true)) return;
    $_COOKIE[RMT_DESIGN_COOKIE] = $ask === 'off' ? '' : $ask;
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    setcookie(RMT_DESIGN_COOKIE, $ask === 'off' ? '' : $ask, [
        'expires' => $ask === 'off' ? time() - 3600 : time() + 7 * 86400,
        'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    ]);
}

/** The surfaces switched on for everybody by RMT_DESIGN_V2. @return list<string> */
function rmt_design_env_surfaces(): array {
    $raw = strtolower(trim((string) getenv('RMT_DESIGN_V2')));
    if ($raw === '' || $raw === '0') return [];
    if ($raw === 'all' || $raw === '1') return RMT_DESIGN_SURFACES;
    return array_values(array_intersect(RMT_DESIGN_SURFACES, array_map('trim', explode(',', $raw))));
}

/** Is this surface in the new design for this request? */
function rmt_design_v2(string $surface): bool {
    $pref = (string) ($_COOKIE[RMT_DESIGN_COOKIE] ?? '');
    if ($pref === 'v2') return true;
    if ($pref === 'v1') return false;
    return in_array($surface, rmt_design_env_surfaces(), true);
}

/** Any surface in the new design (decides whether design-v2.css loads). */
function rmt_design_v2_any(): bool {
    foreach (RMT_DESIGN_SURFACES as $s) if (rmt_design_v2($s)) return true;
    return false;
}

/** Part of the page cache key, so a cached page is never served in the other design. */
function rmt_design_sig(): string {
    $on = array_filter(RMT_DESIGN_SURFACES, 'rmt_design_v2');
    return $on ? 'v2:' . implode(',', $on) : '';
}

/** This URL asks for a design, so it is a preview and never a page of its own in search. */
function rmt_design_query_preview(): bool {
    return in_array((string) ($_GET['design'] ?? ''), ['v1', 'v2', 'off'], true);
}

/** A member's initial for the avatar fallback: the first letter of the name they go by. */
function rmt_design_initial(string $name): string {
    $name = ltrim($name, '@ ');
    return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1));
}

/**
 * The strongest places in a city: indexable under the site's own rule and with something to read
 * (traveler reviews or our own write up), most first. Used by the city page's "Top places"
 * block, which links them straight from the city (SEO discovery option B).
 *
 * @return list<array<string,mixed>>
 */
function rmt_city_top_places(int $destId, int $limit = 12): array {
    $rows = q_all(
        "SELECT p.id, p.slug, p.name, p.type, p.status, p.destination_id, p.street_address, p.lat,
                p.website_url, p.phone, p.updated_at, p.created_at, p.neighborhood,
                (SELECT COUNT(*) FROM place_hours h WHERE h.place_id = p.id) hours_count,
                (SELECT COUNT(*) FROM place_photos ph WHERE ph.place_id = p.id) photo_count,
                (SELECT COUNT(*) FROM reviews r WHERE r.place_id = p.id AND r.status = 'published') review_count,
                (SELECT COUNT(*) FROM reviews r JOIN users u ON u.id = r.user_id
                  WHERE r.place_id = p.id AND r.status = 'published' AND u.role <> ?) traveler_reviews,
                (SELECT AVG(r.rating) FROM reviews r JOIN users u ON u.id = r.user_id
                  WHERE r.place_id = p.id AND r.status = 'published' AND u.role <> ?) traveler_avg,
                (SELECT COUNT(*) FROM place_editorial pe WHERE pe.place_id = p.id) has_editorial
           FROM places p
          WHERE p.destination_id = ? AND p.status = 'active'",
        [RMT_EDITORIAL_ROLE, RMT_EDITORIAL_ROLE, $destId]);
    $keep = [];
    foreach ($rows as $r) {
        $v = rmt_indexable('place', $r);
        if (empty($v['ok'])) continue;
        // Only places with something to read: travelers' reviews or our own write up. An address
        // and opening hours alone is a page we would rather not spend a city's strongest links on.
        if ((int) $r['traveler_reviews'] === 0 && (int) $r['has_editorial'] === 0) continue;
        $r['score'] = 6 * (int) $r['traveler_reviews'] + 4 * (int) $r['has_editorial']
                    + 2 * min(1, (int) $r['hours_count']) + min(2, (int) $r['photo_count'])
                    + ((string) $r['website_url'] !== '' ? 1 : 0);
        $keep[] = $r;
    }
    usort($keep, static fn(array $a, array $b): int => [$b['score'], $a['name']] <=> [$a['score'], $b['name']]);
    return array_slice($keep, 0, $limit);
}

/** Is the "Top places" block on the current city page design? RMT_CITY_TOP_PLACES=1, or city v2. */
function rmt_city_top_places_enabled(): bool {
    return getenv('RMT_CITY_TOP_PLACES') === '1' || rmt_design_v2('city');
}

/** A short glyph for a place type, for cards that have no photograph. */
function rmt_design_type_glyph(string $type): string {
    return match ($type) {
        'restaurant', 'food' => '🍽', 'cafe' => '☕', 'bar', 'nightlife' => '🍸', 'hotel', 'hostel' => '🛏',
        'museum' => '🏛', 'attraction', 'landmark' => '📍', 'park', 'nature', 'beach' => '🌿',
        'tour', 'experience', 'activity' => '🧭', 'market', 'shop', 'shopping' => '🛍',
        default => '📍',
    };
}

/**
 * The places on the redesigned home page: ones Google already shows (most search impressions over
 * the 90 days to 2026-10-09, Search Console), so a visitor who has been there can rate it in one
 * tap. Any that are missing or no longer indexable are skipped, and the list is topped up from the
 * strongest places in the featured cities.
 */
const RMT_HOME_V2_PLACES = [
    // Search impressions and a full write up of our own (docs/seo/SITEMAP_LIST_VERIFICATION_20261011.md):
    // the home page's strongest links go to pages with something to read, never to thin ones.
    'vasa-museum-stockholm', 'zeughauskeller-zurich', 'ekstedt-stockholm', 'ratana-milan',
    'au-pied-de-cochon-paris', 'book-of-kells-experience-at-trinity-college-dublin',
    'hungarian-national-museum-budapest', 'anne-frank-house-amsterdam',
];

/** @return list<array<string,mixed>> */
function rmt_home_v2_places(int $limit = 6): array {
    $out = [];
    $sql = "SELECT p.id, p.slug, p.name, p.type, p.neighborhood, d.name dest_name, d.slug dest_slug, d.hero_url,
                   (SELECT COUNT(*) FROM reviews r JOIN users u ON u.id = r.user_id
                     WHERE r.place_id = p.id AND r.status = 'published' AND u.role <> ?) traveler_reviews,
                   (SELECT AVG(r.rating) FROM reviews r JOIN users u ON u.id = r.user_id
                     WHERE r.place_id = p.id AND r.status = 'published' AND u.role <> ?) traveler_avg
              FROM places p JOIN destinations d ON d.id = p.destination_id
             WHERE p.status = 'active' AND p.slug = ?";
    foreach (RMT_HOME_V2_PLACES as $slug) {
        if (count($out) >= $limit) break;
        $row = q_one($sql, [RMT_EDITORIAL_ROLE, RMT_EDITORIAL_ROLE, $slug]);
        if ($row) $out[$row['id']] = $row;
    }
    if (count($out) < $limit) {
        foreach (q_all("SELECT id FROM destinations ORDER BY (SELECT COUNT(*) FROM places p WHERE p.destination_id = destinations.id) DESC LIMIT 4") as $d) {
            foreach (rmt_city_top_places((int) $d['id'], $limit) as $tp) {
                if (count($out) >= $limit) break 2;
                if (isset($out[$tp['id']])) continue;
                $row = q_one($sql, [RMT_EDITORIAL_ROLE, RMT_EDITORIAL_ROLE, $tp['slug']]);
                if ($row) $out[$row['id']] = $row;
            }
        }
    }
    return array_values($out);
}

/**
 * Proof for a first time visitor: a few of our own Official Reviews, one per city, the most critical
 * first, skipping places the rating cards already show. They are editorial and the home page says so;
 * nothing here is counted as, or dressed up as, a traveler review.
 *
 * @param list<int> $skipPlaceIds
 * @return list<array<string,mixed>>
 */
function rmt_home_v2_official(array $skipPlaceIds = [], int $limit = 3): array {
    $rows = q_all("SELECT r.id, r.title, r.rating, p.id place_id, p.slug, p.name, p.type, d.name dest_name
                     FROM reviews r JOIN users u ON u.id = r.user_id
                     JOIN places p ON p.id = r.place_id JOIN destinations d ON d.id = p.destination_id
                    WHERE r.status = 'published' AND u.role = ? AND p.status = 'active'
                    ORDER BY r.rating ASC, r.id ASC LIMIT 60", [RMT_EDITORIAL_ROLE]);
    $out = []; $cities = [];
    foreach ($rows as $r) {
        if (in_array((int) $r['place_id'], $skipPlaceIds, true) || isset($cities[$r['dest_name']])) continue;
        $cities[$r['dest_name']] = true;
        $out[] = $r;
        if (count($out) >= $limit) break;
    }
    return $out;
}
