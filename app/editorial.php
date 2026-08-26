<?php
declare(strict_types=1);

/**
 * Editorial content: what it is, and the rules that keep it honest.
 *
 * RuinMyTrip sells honest reviews. Seeding it with invented traveler accounts to look busy would
 * be the exact fraud the product claims to oppose (and, in the US, an FTC violation). So the
 * launch content is EDITORIAL: real destinations, researched facts, written by the RuinMyTrip
 * team under one clearly identified official account.
 *
 * Three invariants, all enforced here rather than by convention:
 *
 *   1. AUTHORSHIP IS THE LABEL. Editorial means users.role = 'editorial'. Every render path asks
 *      this module, so there is no way to publish editorial content that renders unlabelled, and
 *      no per-row flag that can drift away from who actually wrote the words.
 *   2. NO BORROWED CREDIBILITY. Editorial ratings are excluded from every community average
 *      (see rmt_community_avg). The site never quotes its own opinion back as traveler consensus.
 *   3. NO CLAIMED VISITS. Editorial reviews carry no visited_on date and no "Verified visit"
 *      badge, because nobody from the team necessarily went. rmt_editorial_disclosure() is the
 *      sentence shown on every editorial item saying exactly that.
 */

const RMT_EDITORIAL_ROLE     = 'editorial';
const RMT_EDITORIAL_USERNAME = 'ruinmytrip';

/**
 * Is this row editorial?
 *
 * Accepts any row shape used across the app: one with an ['author'] sub-array (list pages), one
 * with a joined `role` / `author_role` column, or a bare user row.
 */
function rmt_is_editorial(?array $row): bool {
    if (!$row) return false;
    foreach ([$row['author']['role'] ?? null, $row['author_role'] ?? null, $row['role'] ?? null] as $r) {
        if ($r !== null) return $r === RMT_EDITORIAL_ROLE;
    }
    return false;
}

/** The official editorial account, or null if it has not been created yet. */
function rmt_editorial_user(): ?array {
    static $cached = false; static $user = null;
    if ($cached) return $user;
    $cached = true;
    $user = q_one('SELECT u.*, p.display_name, p.avatar_url FROM users u
                   LEFT JOIN profiles p ON p.user_id = u.id
                   WHERE u.role = ? ORDER BY u.id LIMIT 1', [RMT_EDITORIAL_ROLE]);
    return $user;
}

/** Display name for editorial bylines. */
function rmt_editorial_name(): string {
    return (string) (rmt_editorial_user()['display_name'] ?? 'RuinMyTrip Editorial');
}

/**
 * The label chip. Reviews say "Official Review" because that is what the reader is looking at;
 * everything else says "Editorial".
 */
function rmt_editorial_badge(string $kind = 'editorial', bool $link = true): string {
    $text = $kind === 'review' ? 'Official Review' : 'Editorial';
    $title = 'Written by the RuinMyTrip team, not a community member. Read the editorial policy.';
    if (!$link) {
        return '<span class="ed-badge" title="' . e($title) . '">' . e($text) . '</span>';
    }
    return '<a class="ed-badge" href="' . e(url('editorial-policy')) . '" title="' . e($title) . '">'
         . e($text) . '</a>';
}

/** The one-line honesty statement shown wherever editorial content is read in full. */
function rmt_editorial_disclosure(): string {
    return 'Written by the ' . rmt_editorial_name() . ' team from published research and official '
         . 'sources, not from a personal trip. It is not a traveler review and is never counted in '
         . 'the community rating.';
}

/**
 * Community rating for a destination: published reviews from real members only.
 * Editorial ratings are excluded by role, so the number always means "what travelers said".
 *
 * Safety/value are optional per review (the write form labels them "optional"), so their counts
 * are tracked separately from the overall review count `c` -- a destination with 20 reviews but
 * only 6 safety ratings should say "from 6", not silently average the 14 blanks as zero (AVG()
 * over a nullable column already skips NULLs correctly; this just surfaces the real denominator).
 *
 * @return array{a:?string,c:int,safety_a:?string,safety_c:int,value_a:?string,value_c:int}
 */
function rmt_community_avg(int $destId): array {
    $row = q_one("SELECT ROUND(AVG(r.rating), 1) a, COUNT(*) c,
                         ROUND(AVG(r.safety_rating), 1) safety_a, COUNT(r.safety_rating) safety_c,
                         ROUND(AVG(r.value_rating), 1) value_a, COUNT(r.value_rating) value_c
                    FROM reviews r JOIN users u ON u.id = r.user_id
                   WHERE r.destination_id = ? AND r.status = 'published' AND u.role <> ?",
                 [$destId, RMT_EDITORIAL_ROLE]);
    return [
        'a' => $row['a'] ?? null, 'c' => (int) ($row['c'] ?? 0),
        'safety_a' => $row['safety_a'] ?? null, 'safety_c' => (int) ($row['safety_c'] ?? 0),
        'value_a' => $row['value_a'] ?? null, 'value_c' => (int) ($row['value_c'] ?? 0),
    ];
}

/**
 * Community rating broken out by what was actually reviewed (destination/hotel/restaurant/
 * attraction/experience), not just blended into one number. A destination can be a great place
 * to visit and a bad place to eat at the same time -- one average hides that; this doesn't.
 * Editorial excluded, same role filter as rmt_community_avg(). Only categories with at least one
 * community review are returned, in RMT_REVIEW_CATEGORIES display order.
 *
 * @return array<int,array{subject_type:string,a:string,c:int}>
 */
function rmt_community_avg_by_category(int $destId): array {
    $rows = q_all("SELECT r.subject_type, ROUND(AVG(r.rating), 1) a, COUNT(*) c
                     FROM reviews r JOIN users u ON u.id = r.user_id
                    WHERE r.destination_id = ? AND r.status = 'published' AND u.role <> ?
                    GROUP BY r.subject_type", [$destId, RMT_EDITORIAL_ROLE]);
    $byType = [];
    foreach ($rows as $row) $byType[$row['subject_type']] = $row;
    $out = [];
    foreach (RMT_REVIEW_CATEGORIES as $type) {
        if (isset($byType[$type])) $out[] = $byType[$type];
    }
    return $out;
}

/** Practical tips for a destination, in display order. */
function rmt_destination_tips(int $destId): array {
    return q_all('SELECT * FROM destination_tips WHERE destination_id = ? ORDER BY sort, id', [$destId]);
}

/**
 * Every real traveler photo for a destination -- trip photos and review photos merged, newest
 * first. Two separately-bounded subqueries (LIMIT 200 each, portable across both drivers) rather
 * than one unbounded UNION, then merged and re-sliced to $limit in PHP -- a popular destination
 * could otherwise pull thousands of rows just to show a 12-photo teaser grid.
 */
function rmt_destination_photos(int $destId, int $limit = 0): array {
    $tripPhotos = q_all("SELECT tp.url, tp.caption, tp.created_at, t.id parent_id, t.slug parent_slug,
                                t.user_id, 'trip' AS kind
                         FROM trip_photos tp JOIN trips t ON t.id = tp.trip_id
                         WHERE t.destination_id = ? AND t.status = 'published'
                         ORDER BY tp.created_at DESC, tp.id DESC LIMIT 200", [$destId]);
    $reviewPhotos = q_all("SELECT rp.url, rp.caption, rp.created_at, r.id parent_id, r.slug parent_slug,
                                  r.user_id, 'review' AS kind
                           FROM review_photos rp JOIN reviews r ON r.id = rp.review_id
                           WHERE r.destination_id = ? AND r.status = 'published'
                           ORDER BY rp.created_at DESC, rp.id DESC LIMIT 200", [$destId]);
    $photos = array_merge($tripPhotos, $reviewPhotos);
    usort($photos, fn($x, $y) => strcmp((string)$y['created_at'], (string)$x['created_at']));
    if ($limit > 0) $photos = array_slice($photos, 0, $limit);
    authors_fill($photos);
    return $photos;
}

/**
 * Split a review list into [editorial, community] preserving order.
 * @return array{0:array,1:array}
 */
function rmt_split_editorial(array $rows): array {
    $ed = $co = [];
    foreach ($rows as $r) { if (rmt_is_editorial($r)) $ed[] = $r; else $co[] = $r; }
    return [$ed, $co];
}

/** Photo attribution line for a destination hero, or '' when the image has no recorded licence. */
function rmt_photo_credit_html(?array $d): string {
    if (empty($d['hero_credit'])) return '';
    $txt = 'Photo: ' . e((string) $d['hero_credit']);
    if (!empty($d['hero_license'])) $txt .= ' (' . e((string) $d['hero_license']) . ')';
    if (!empty($d['hero_source_url'])) {
        $txt = '<a href="' . e((string) $d['hero_source_url']) . '" rel="nofollow noopener" target="_blank">' . $txt . '</a>';
    }
    return '<p class="photo-credit">' . $txt . '</p>';
}
