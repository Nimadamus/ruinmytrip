<?php
declare(strict_types=1);

/**
 * Profile stats and badges.
 *
 * Every number here is a live COUNT against the database. Nothing is cached into a column and
 * nothing is seeded, because a denormalised counter drifts from reality and a drifted counter is
 * indistinguishable from a fake one.
 */

/** Badge award rules. Slug => [label, rule]. A badge is EARNED or it is not shown. */
const RMT_FOUNDING_TRAVELER_CUTOFF = 100;   // first N accounts, by id

/**
 * Profile stats for a user id.
 * @return array{reviews:int, trips:int, places:int, followers:int, following:int}
 */
function rmt_profile_stats(int $uid): array {
    $one = static fn(string $sql, array $a) => (int) (q_one($sql, $a)['c'] ?? 0);
    return [
        'reviews'   => $one("SELECT COUNT(*) c FROM reviews WHERE user_id=? AND status='published'", [$uid]),
        'trips'     => $one("SELECT COUNT(*) c FROM trips   WHERE user_id=? AND status='published'", [$uid]),
        // "Places visited" = distinct destinations the user has actually written about, from
        // either a review or a trip. Not a self-declared number.
        'places'    => $one("SELECT COUNT(*) c FROM (
                               SELECT destination_id FROM reviews
                                WHERE user_id=? AND status='published' AND destination_id IS NOT NULL
                               UNION
                               SELECT destination_id FROM trips
                                WHERE user_id=? AND status='published' AND destination_id IS NOT NULL
                             ) x", [$uid, $uid]),
        'followers' => $one('SELECT COUNT(*) c FROM follows WHERE followee_id=?', [$uid]),
        'following' => $one('SELECT COUNT(*) c FROM follows WHERE follower_id=?', [$uid]),
    ];
}

/** Badges a user currently holds. */
function rmt_user_badges(int $uid): array {
    return q_all('SELECT b.* FROM user_badges ub JOIN badges b ON b.id = ub.badge_id
                  WHERE ub.user_id = ? ORDER BY ub.awarded_at', [$uid]);
}

/**
 * Does this user qualify as a Founding Traveler?
 *
 * Rule: one of the first RMT_FOUNDING_TRAVELER_CUTOFF accounts AND has published at least one
 * review. Signing up early is not an achievement on its own — contributing is. Deliberately NOT
 * gated on email verification: verification cannot currently reach real users (no verified
 * sending domain), so gating on it would make the badge unearnable rather than meaningful.
 */
function rmt_qualifies_founding_traveler(int $uid): bool {
    $u = q_one('SELECT id, status FROM users WHERE id = ?', [$uid]);
    if (!$u || $u['status'] !== 'active') return false;
    if ((int) $u['id'] > RMT_FOUNDING_TRAVELER_CUTOFF) return false;
    $n = (int) (q_one("SELECT COUNT(*) c FROM reviews WHERE user_id=? AND status='published'", [$uid])['c'] ?? 0);
    return $n >= 1;
}

/**
 * Evaluate and grant any badges this user has newly earned. Idempotent — safe to call on every
 * publish. Returns the slugs newly awarded.
 */
function rmt_award_badges(int $uid): array {
    $awarded = [];
    if (rmt_qualifies_founding_traveler($uid)) {
        $b = q_one("SELECT id FROM badges WHERE slug = 'founding-traveler'");
        if ($b) {
            $has = q_one('SELECT 1 FROM user_badges WHERE user_id=? AND badge_id=?', [$uid, (int) $b['id']]);
            if (!$has) {
                q_run('INSERT INTO user_badges (user_id, badge_id, awarded_at) VALUES (?,?,?)',
                      [$uid, (int) $b['id'], date('Y-m-d H:i:s')]);
                $awarded[] = 'founding-traveler';
            }
        }
    }
    return $awarded;
}

/** Followers of a user, newest first. */
function rmt_followers(int $uid, int $limit = 200): array {
    return q_all("SELECT u.id, u.username, p.display_name, p.avatar_url, p.bio, p.home_city, f.created_at
                  FROM follows f JOIN users u ON u.id = f.follower_id
                  LEFT JOIN profiles p ON p.user_id = u.id
                  WHERE f.followee_id = ? AND u.status = 'active'
                  ORDER BY f.created_at DESC, u.id DESC LIMIT $limit", [$uid]);
}

/** Users a user follows, newest first. */
function rmt_following(int $uid, int $limit = 200): array {
    return q_all("SELECT u.id, u.username, p.display_name, p.avatar_url, p.bio, p.home_city, f.created_at
                  FROM follows f JOIN users u ON u.id = f.followee_id
                  LEFT JOIN profiles p ON p.user_id = u.id
                  WHERE f.follower_id = ? AND u.status = 'active'
                  ORDER BY f.created_at DESC, u.id DESC LIMIT $limit", [$uid]);
}

/**
 * Validate a profile edit.
 * @return array{ok:bool, errors:string[], data:array<string,string|null>}
 */
function rmt_profile_validate(array $in): array {
    $errors = [];
    $display = trim((string) ($in['display_name'] ?? ''));
    $bio     = trim((string) ($in['bio'] ?? ''));
    $home    = trim((string) ($in['home_city'] ?? ''));
    $avatar  = trim((string) ($in['avatar_url'] ?? ''));

    if (mb_strlen($display) > 60)  $errors[] = 'Display name is too long (60 characters max).';
    if (mb_strlen($bio) > 600)     $errors[] = 'Bio is too long (600 characters max).';
    if (mb_strlen($home) > 80)     $errors[] = 'Home location is too long (80 characters max).';

    // Avatar is a URL until object storage exists. Restrict the scheme so a profile can never
    // become a javascript:/data: payload delivered through an <img src>.
    if ($avatar !== '') {
        $ok = filter_var($avatar, FILTER_VALIDATE_URL) !== false
              && preg_match('#^https://#i', $avatar) === 1;
        if (!$ok) $errors[] = 'Photo URL must be a full https:// web address.';
        if (mb_strlen($avatar) > 500) $errors[] = 'That photo URL is too long.';
    }

    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'display_name' => $display ?: null,
        'bio'          => $bio ?: null,
        'home_city'    => $home ?: null,
        'avatar_url'   => $avatar ?: null,
    ]];
}
