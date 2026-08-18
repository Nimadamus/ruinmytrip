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
    // A name can be mostly punctuation. slugify() drops every non-alphanumeric character, so the
    // Hong Kong museum "M+" became the slug "m", giving /p/m-hong-kong: an unreadable URL for one
    // of the more significant museums on the site. Symbols that carry the name have to be spoken
    // before they are stripped. Done here rather than in slugify() because that helper generates
    // destination, review, guide and forum slugs across the whole site, and this is a place-URL
    // problem, not a sitewide one.
    $spoken = strtr($name, ['+' => ' plus ', '&' => ' and ', '@' => ' at ']);
    $nameSlug = slugify($spoken);
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

    // Queried directly rather than through dest_by_id(), which lives in controllers.php. Resolution
    // has to work from CLI publishers and backfills that never load the web controllers.
    $dest = q_one('SELECT id, name FROM destinations WHERE id = ?', [$destId]);
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

/** One place with its destination, by id. */
function rmt_place_by_id(int $id): ?array {
    if ($id <= 0) return null;
    return q_one('SELECT p.*, d.name dest_name, d.slug dest_slug, d.country dest_country, d.hero_url dest_hero
                    FROM places p JOIN destinations d ON d.id = p.destination_id
                   WHERE p.id = ? AND p.status = ?', [$id, 'active']);
}

/**
 * A review written from a place page carries that place's id in a hidden field, so the writer never
 * has to retype a name and hope the resolver matches it.
 *
 * The id alone is not trusted. It only holds when the submitted destination and name still describe
 * that same row -- a tampered form must not file a review under an unrelated place, and a writer who
 * genuinely edited the name meant a different place, which then resolves the ordinary way.
 */
function rmt_place_bound_id(int $postedId, ?int $destId, string $name): ?int {
    $p = rmt_place_by_id($postedId);
    if (!$p || !$destId || (int) $p['destination_id'] !== $destId) return null;
    return rmt_place_name_key($name) === $p['name_key'] ? (int) $p['id'] : null;
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

    // `editorial_count` is tracked separately and never folded into review_count or the average.
    // A place with an Official Review but no traveler reviews has something to read, and telling
    // the reader "No published reviews yet" would send them past a page that is not empty. Counting
    // it as a review instead would be the far worse error: it would put the site's own opinion into
    // a number the reader takes for traveler consensus.
    array_unshift($args, RMT_EDITORIAL_ROLE);
    return q_all("SELECT p.*,
                         COUNT(r.id) review_count,
                         ROUND(AVG(r.rating), 1) avg_rating,
                         (SELECT COUNT(*) FROM reviews er JOIN users eu ON eu.id = er.user_id
                           WHERE er.place_id = p.id AND er.status = 'published' AND eu.role = ?) editorial_count,
                         (SELECT pe.meta_description FROM place_editorial pe WHERE pe.place_id = p.id) snippet
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
 * The ordered sections of a place's structured editorial, as [column => heading].
 *
 * Order is the reading order on the page and is deliberate: what it is, then why anyone goes, then
 * the honest good and bad, then who it suits, then everything you need to actually turn up. The
 * verdict is last because a verdict before the evidence is just an opinion.
 */
const RMT_PLACE_EDITORIAL_SECTIONS = [
    'what_it_is'       => 'What it is',
    'why_go'           => 'Why travelers go',
    'the_good'         => 'What is genuinely good',
    'the_downsides'    => 'Downsides and tourist traps',
    'best_for'         => 'Best for',
    'skip_if'          => 'Consider skipping if',
    'practical'        => 'Practical advice',
    'tickets'          => 'Tickets and reservations',
    'getting_there'    => 'Getting there',
    'location_context' => 'Where it sits',
    'time_needed'      => 'How long to allow',
    'accessibility'    => 'Accessibility',
    'verdict'          => 'The RuinMyTrip verdict',
];

/**
 * The same thirteen columns, headed the way the thing being described is actually talked about.
 *
 * A hotel page under the heading "Tickets and reservations" and "How long to allow" reads as an
 * attraction template with a hotel dropped into it, which is exactly how a bolted-on second
 * database announces itself. The columns do not change, because splitting the schema per type would
 * fork every query, aggregate and honesty rule in this file for a cosmetic gain. Only the words
 * change.
 */
function rmt_place_editorial_sections(string $type = 'attraction'): array {
    $s = RMT_PLACE_EDITORIAL_SECTIONS;
    if ($type === 'hotel') {
        $s['why_go']        = 'Why travelers stay here';
        $s['tickets']       = 'Rates and booking';
        $s['time_needed']   = 'How long to stay';
        $s['skip_if']       = 'Consider staying elsewhere if';
        $s['the_downsides'] = 'Downsides';
    } elseif ($type === 'restaurant') {
        $s['why_go']        = 'Why travelers eat here';
        $s['tickets']       = 'Prices and reservations';
        $s['time_needed']   = 'How long to allow';
        $s['skip_if']       = 'Consider eating elsewhere if';
        $s['the_downsides'] = 'Downsides';
    }
    return $s;
}

/**
 * The schema.org type for a place. A restaurant marked up as a TouristAttraction is simply wrong,
 * and search engines read this markup literally.
 */
function rmt_place_schema_type(string $type): string {
    return ['hotel' => 'Hotel', 'restaurant' => 'Restaurant'][$type] ?? 'TouristAttraction';
}

/** The question the page title promises to answer. Nobody asks whether a hotel is worth visiting. */
function rmt_place_title_question(string $type): string {
    return [
        'hotel'      => 'review, tips and is it worth staying',
        'restaurant' => 'review, tips and is it worth eating at',
    ][$type] ?? 'review, tips and is it worth visiting';
}

/** Structured editorial for a place, or null when the team has not written one. */
function rmt_place_editorial(int $placeId): ?array {
    $row = q_one('SELECT * FROM place_editorial WHERE place_id = ?', [$placeId]);
    if (!$row) return null;
    $row['sources'] = json_decode((string) ($row['sources'] ?? ''), true) ?: [];
    return $row;
}

/**
 * Other editorially covered attractions in the same destination, for the "nearby" links.
 *
 * Only places that actually have editorial are offered: linking to a bare place page with nothing
 * on it would be the doorway-page pattern this content exists to avoid.
 */
function rmt_place_nearby(int $placeId, int $destId, int $limit = 6): array {
    return q_all('SELECT p.id, p.slug, p.name, p.type, pe.meta_description
                    FROM places p JOIN place_editorial pe ON pe.place_id = p.id
                   WHERE p.destination_id = ? AND p.id <> ? AND p.status = ?
                   ORDER BY p.name LIMIT ' . max(1, $limit), [$destId, $placeId, 'active']);
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
