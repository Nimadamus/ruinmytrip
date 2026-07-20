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
function rmt_editorial_badge(string $kind = 'editorial'): string {
    $text = $kind === 'review' ? 'Official Review' : 'Editorial';
    return '<a class="ed-badge" href="' . e(url('editorial-policy')) . '"'
         . ' title="Written by the RuinMyTrip team, not a community member. Read the editorial policy.">'
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
 * @return array{a:?string,c:int}
 */
function rmt_community_avg(int $destId): array {
    $row = q_one("SELECT ROUND(AVG(r.rating), 1) a, COUNT(*) c
                    FROM reviews r JOIN users u ON u.id = r.user_id
                   WHERE r.destination_id = ? AND r.status = 'published' AND u.role <> ?",
                 [$destId, RMT_EDITORIAL_ROLE]);
    return ['a' => $row['a'] ?? null, 'c' => (int) ($row['c'] ?? 0)];
}

/** Practical tips for a destination, in display order. */
function rmt_destination_tips(int $destId): array {
    return q_all('SELECT * FROM destination_tips WHERE destination_id = ? ORDER BY sort, id', [$destId]);
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

/**
 * The invite link a member shares. `ref` is only ever a username, and nothing is gated on it,
 * so a forged or missing ref costs nothing.
 */
function rmt_invite_link(?array $user): string {
    return $user ? url('register?ref=' . rawurlencode((string) $user['username'])) : url('register');
}

/** Normalise a ?ref= value to a real member username, or null. */
function rmt_referrer_username(?string $ref): ?string {
    $ref = trim((string) $ref);
    if ($ref === '' || !preg_match('/^[A-Za-z0-9_]{1,40}$/', $ref)) return null;
    $u = q_one('SELECT username FROM users WHERE username = ? AND status = ?', [$ref, 'active']);
    return $u['username'] ?? null;
}
