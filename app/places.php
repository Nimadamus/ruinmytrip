<?php
declare(strict_types=1);

/**
 * Places — the hotels, restaurants, attractions and experiences a review is actually about.
 *
 * A review has always carried `subject_name` as free text. That is still what the author typed and
 * still what renders on the review, but on save it is now also resolved to a `places` row scoped to
 * the destination, so every review of the same thing collects on one page with one honest average.
 *
 * Three rules hold this together:
 *
 *   1. RESOLUTION IS SERVER-SIDE AND FORGIVING. rmt_place_resolve() matches on a normalised
 *      `name_key`, so "Hotel Arts", "hotel arts" and "The Hotel Arts." are one place. Normalisation
 *      is deliberately shallow — merging two places that are genuinely different is a far worse
 *      failure than leaving two spellings apart, and only the former is invisible to the reader.
 *   2. NO BORROWED CREDIBILITY, SAME AS EVERYWHERE. rmt_place_stats() excludes the editorial
 *      account by role, exactly as rmt_community_avg() does for destinations. A place's star rating
 *      always means "what travelers said", never what the site said.
 *   3. NO EMPTY ENTITIES. A place only exists because somebody reviewed something; nothing in the
 *      app creates one any other way. A place whose reviews are all unpublished shows an honest
 *      empty state and is kept out of the sitemap rather than padding the index with thin pages.
 */

/** What can be a place. `destination` is excluded on purpose — the destination IS the container. */
const RMT_PLACE_TYPES = ['hotel', 'restaurant', 'attraction', 'experience'];

const RMT_PLACE_NAME_MAX = 200;

/** Plural, human labels for the type filter and headings. */
function rmt_place_type_label(string $type, bool $plural = false): string {
    $one = ['hotel'=>'Hotel', 'restaurant'=>'Restaurant', 'attraction'=>'Attraction', 'experience'=>'Experience'];
    $many = ['hotel'=>'Hotels', 'restaurant'=>'Restaurants', 'attraction'=>'Attractions', 'experience'=>'Experiences'];
    return ($plural ? $many : $one)[$type] ?? ucfirst($type);
}

/**
 * The dedupe key for a place name within one destination.
 *
 * Lowercase, drop a leading article, collapse everything that is not a letter or digit to a single
 * space. Accents are NOT folded (é and e stay distinct) because doing it without ext/intl means a
 * hand-rolled table that is wrong for some language, and a wrong fold silently merges two real
 * places. Two spellings sitting side by side is the safe failure.
 */
function rmt_place_name_key(string $name): string {
    $k = mb_strtolower(trim($name));
    $k = preg_replace('/^(the|le|la|el|il)\s+/u', '', $k) ?? $k;
    $k = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $k) ?? $k;
    return trim((string) $k);
}

/**
 * A globally unique slug. The destination is folded into it ("hotel-arts-barcelona") so the URL
 * reads as a real place rather than a bare name that could be in any of eighty cities, and so two
 * "Old Town Walking Tour"s in different countries do not fight over one slug.
 */
function rmt_place_unique_slug(string $name, string $destName, int $excludeId = 0): string {
    // Travelers routinely type the city into the name themselves ("Skyline Gondola, Queenstown").
    // Appending it again would give "skyline-gondola-queenstown-queenstown", so only add the
    // destination when the name does not already carry it.
    $nameSlug = slugify($name);
    $destSlug = slugify($destName);
    $base = str_contains($nameSlug, $destSlug) ? $nameSlug : $nameSlug . '-' . $destSlug;
    $base = mb_substr($base, 0, 80);
    $slug = $base;
    $n = 1;
    while (true) {
        $row = q_one('SELECT id FROM places WHERE slug = ?', [$slug]);
        if (!$row || (int) $row['id'] === $excludeId) return $slug;
        $slug = $base . '-' . (++$n);
    }
}

/**
 * Find or create the place a review is about. Returns null when there is nothing to resolve —
 * a destination-level review, a blank name, or a destination that does not exist — and the review
 * then simply has no place, which is a valid state for every row written before this shipped.
 *
 * The unique index on (destination_id, name_key) is the real guard: two people publishing the first
 * review of the same hotel in the same second both pass the SELECT, one INSERT loses, and the loser
 * re-reads the winner's row instead of erroring out.
 */
function rmt_place_resolve(?int $destId, string $type, string $name, ?int $userId): ?int {
    $name = trim($name);
    if (!$destId || $name === '' || !in_array($type, RMT_PLACE_TYPES, true)) return null;
    if (mb_strlen($name) > RMT_PLACE_NAME_MAX) return null;

    $dest = dest_by_id($destId);
    if (!$dest) return null;

    $key = rmt_place_name_key($name);
    if ($key === '') return null;

    $existing = q_one('SELECT id FROM places WHERE destination_id = ? AND name_key = ?', [$destId, $key]);
    if ($existing) return (int) $existing['id'];

    $now = date('Y-m-d H:i:s');
    $slug = rmt_place_unique_slug($name, (string) $dest['name']);
    try {
        return (int) q_run('INSERT INTO places (destination_id, slug, name, name_key, type, created_by, status, created_at, updated_at)
                            VALUES (?,?,?,?,?,?,?,?,?)',
                           [$destId, $slug, $name, $key, $type, $userId, 'active', $now, $now]);
    } catch (Throwable $e) {
        // Lost the race (or the slug was taken between the check and the insert). Whoever won wrote
        // the same place; use theirs.
        $row = q_one('SELECT id FROM places WHERE destination_id = ? AND name_key = ?', [$destId, $key]);
        if ($row) return (int) $row['id'];
        throw $e;
    }
}

/** One place with its destination, by slug. */
function rmt_place_by_slug(string $slug): ?array {
    return q_one('SELECT p.*, d.name dest_name, d.slug dest_slug, d.country dest_country, d.hero_url dest_hero
                    FROM places p JOIN destinations d ON d.id = p.destination_id
                   WHERE p.slug = ? AND p.status = ?', [$slug, 'active']);
}

/** Canonical path for a place. */
function rmt_place_path(array $p): string { return '/p/' . $p['slug']; }

/**
 * Community rating for one place: published reviews from real members only, editorial excluded by
 * role exactly as rmt_community_avg() does for destinations.
 *
 * @return array{a:?string,c:int,safety_a:?string,safety_c:int,value_a:?string,value_c:int}
 */
function rmt_place_stats(int $placeId): array {
    $row = q_one("SELECT ROUND(AVG(r.rating), 1) a, COUNT(*) c,
                         ROUND(AVG(r.safety_rating), 1) safety_a, COUNT(r.safety_rating) safety_c,
                         ROUND(AVG(r.value_rating), 1) value_a, COUNT(r.value_rating) value_c
                    FROM reviews r JOIN users u ON u.id = r.user_id
                   WHERE r.place_id = ? AND r.status = 'published' AND u.role <> ?",
                 [$placeId, RMT_EDITORIAL_ROLE]);
    return [
        'a' => $row['a'] ?? null, 'c' => (int) ($row['c'] ?? 0),
        'safety_a' => $row['safety_a'] ?? null, 'safety_c' => (int) ($row['safety_c'] ?? 0),
        'value_a' => $row['value_a'] ?? null, 'value_c' => (int) ($row['value_c'] ?? 0),
    ];
}

/** How the rating counts break down 5→1, for the distribution bars. Editorial excluded. */
function rmt_place_rating_breakdown(int $placeId): array {
    $rows = q_all("SELECT r.rating, COUNT(*) c FROM reviews r JOIN users u ON u.id = r.user_id
                    WHERE r.place_id = ? AND r.status = 'published' AND u.role <> ?
                    GROUP BY r.rating", [$placeId, RMT_EDITORIAL_ROLE]);
    $out = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
    foreach ($rows as $r) {
        $n = (int) $r['rating'];
        if (isset($out[$n])) $out[$n] = (int) $r['c'];
    }
    return $out;
}

/**
 * Every review of a place, editorial first (it is labelled as such and never counted in the
 * average), then the reviews other travelers found most useful.
 */
function rmt_place_reviews(int $placeId, int $limit = 50): array {
    $rows = q_all("SELECT r.*,
                          (SELECT COUNT(*) FROM review_votes rv WHERE rv.review_id=r.id AND rv.vote_type='useful') useful_count
                     FROM reviews r JOIN users u ON u.id = r.user_id
                    WHERE r.place_id = ? AND r.status = 'published'
                    ORDER BY (u.role = ?) DESC, useful_count DESC, r.id DESC
                    LIMIT " . max(1, $limit), [$placeId, RMT_EDITORIAL_ROLE]);
    authors_fill($rows);
    return $rows;
}

/** Traveler photos of a place, taken from the reviews that are about it. */
function rmt_place_photos(int $placeId, int $limit = 12): array {
    $photos = q_all("SELECT rp.url, rp.caption, rp.created_at, r.id parent_id, r.slug parent_slug,
                            r.user_id, 'review' AS kind
                       FROM review_photos rp JOIN reviews r ON r.id = rp.review_id
                      WHERE r.place_id = ? AND r.status = 'published'
                      ORDER BY rp.created_at DESC, rp.id DESC LIMIT " . max(1, $limit), [$placeId]);
    authors_fill($photos);
    return $photos;
}

/**
 * Places in a destination with their community aggregates, best-rated first.
 *
 * Rated places sort above unrated ones so a page of "no reviews yet" rows never buries the ones
 * with something to say; within the rated group it is average then review count, so a 5.0 from one
 * person does not permanently outrank a 4.8 from forty. Places with zero published reviews are
 * still listed (somebody wrote about them; the reviews may be drafts) but always last.
 *
 * @param string $type '' for all, otherwise one of RMT_PLACE_TYPES
 */
function rmt_places_for_destination(int $destId, string $type = '', int $limit = 200): array {
    $args = [RMT_EDITORIAL_ROLE, $destId];
    $where = '';
    if (in_array($type, RMT_PLACE_TYPES, true)) { $where = ' AND p.type = ?'; $args[] = $type; }

    return q_all("SELECT p.*,
                         COUNT(r.id) review_count,
                         ROUND(AVG(r.rating), 1) avg_rating
                    FROM places p
                    LEFT JOIN reviews r ON r.place_id = p.id AND r.status = 'published'
                                       AND r.user_id IN (SELECT id FROM users WHERE role <> ?)
                   WHERE p.destination_id = ? AND p.status = 'active'{$where}
                   GROUP BY p.id
                   ORDER BY (COUNT(r.id) = 0), AVG(r.rating) DESC, COUNT(r.id) DESC, p.name
                   LIMIT " . max(1, $limit), $args);
}

/** How many places a destination has, by type — drives the filter chips and the section count. */
function rmt_place_type_counts(int $destId): array {
    $rows = q_all("SELECT type, COUNT(*) c FROM places WHERE destination_id = ? AND status = 'active' GROUP BY type", [$destId]);
    $out = [];
    foreach ($rows as $r) $out[(string) $r['type']] = (int) $r['c'];
    return $out;
}

/**
 * Names to offer as suggestions on the write form, so a traveler reviewing a place somebody has
 * already reviewed picks the existing one instead of typing a near-miss that resolves to a second
 * row. Capped: the form embeds these as a datalist, and an unbounded list would bloat every page
 * load of /review/new.
 */
function rmt_place_suggestions(int $limit = 400): array {
    return q_all('SELECT p.id, p.name, p.type, p.destination_id FROM places p
                   WHERE p.status = ? ORDER BY p.destination_id, p.name LIMIT ' . max(1, $limit), ['active']);
}
