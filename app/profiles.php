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

/** Elite Traveler thresholds — deliberately steep: this is a status, not a participation badge. */
const RMT_ELITE_MIN_REVIEWS = 10;
const RMT_ELITE_MIN_DESTINATIONS = 5;
const RMT_ELITE_MIN_VOTES = 15;

/** The three vote flavors a review can receive, and the compliment types a profile can receive. */
const RMT_REVIEW_VOTE_TYPES = ['useful', 'funny', 'cool'];
const RMT_COMPLIMENT_TYPES = [
    'great_reviews' => 'Great Reviews',
    'great_photos'  => 'Great Photos',
    'trustworthy'   => 'Trustworthy',
    'helpful'       => 'Helpful',
];

/**
 * Profile stats for a user id.
 * @return array{reviews:int, trips:int, places:int, followers:int, following:int, photos:int, votes:int, compliments:int}
 */
function rmt_profile_stats(int $uid): array {
    $one = static fn(string $sql, array $a) => (int) (q_one($sql, $a)['c'] ?? 0);
    return [
        'reviews'   => $one("SELECT COUNT(*) c FROM reviews WHERE user_id=? AND status='published'", [$uid]),
        'trips'     => $one("SELECT COUNT(*) c FROM trips   WHERE user_id=? AND status='published'", [$uid]),
        // Talk is the thing most members produce most of, so leaving it out of the row made the
        // busiest profiles look like the emptiest ones.
        'posts'     => $one("SELECT COUNT(*) c FROM posts   WHERE user_id=? AND status='published'", [$uid]),
        // Members this person brought here on their invite link. A fact about what they did.
        'invited'   => $one("SELECT COUNT(*) c FROM users WHERE invited_by=? AND status='active'", [$uid]),
        // "Places visited" = distinct destinations the user has actually written about, from
        // either a review or a trip. Not a self-declared number.
        'places'    => $one("SELECT COUNT(*) c FROM (
                               SELECT destination_id FROM reviews
                                WHERE user_id=? AND status='published' AND destination_id IS NOT NULL
                               UNION
                               SELECT destination_id FROM trips
                                WHERE user_id=? AND status='published' AND destination_id IS NOT NULL
                             ) x", [$uid, $uid]),
        // Times other travelers marked this person's reviews useful. Their own votes never count.
        'helpful'   => $one("SELECT COUNT(*) c FROM review_votes rv JOIN reviews r ON r.id=rv.review_id
                              WHERE r.user_id=? AND r.status='published'
                                AND rv.vote_type='useful' AND rv.user_id <> ?", [$uid, $uid]),
        // Counted the same way they are LISTED. rmt_followers() and rmt_following() both join
        // users and require status='active'; these counts did not, so a profile whose follower
        // deactivated said "12 followers" above a list of 11 and there was no way to tell which
        // number was lying.
        'followers' => $one("SELECT COUNT(*) c FROM follows f JOIN users u ON u.id = f.follower_id
                              WHERE f.followee_id = ? AND u.status = 'active'", [$uid]),
        'following' => $one("SELECT COUNT(*) c FROM follows f JOIN users u ON u.id = f.followee_id
                              WHERE f.follower_id = ? AND u.status = 'active'", [$uid]),
        // "Photos" = every photo the user has actually posted, on a trip or a review.
        'photos'    => $one("SELECT COUNT(*) c FROM (
                               SELECT rp.id FROM review_photos rp JOIN reviews r ON r.id=rp.review_id
                                WHERE r.user_id=? AND r.status='published'
                               UNION ALL
                               SELECT tp.id FROM trip_photos tp JOIN trips t ON t.id=tp.trip_id
                                WHERE t.user_id=? AND t.status='published'
                             ) x", [$uid, $uid]),
        'votes'       => rmt_votes_received($uid),
        'compliments' => $one('SELECT COUNT(*) c FROM compliments WHERE to_user_id=?', [$uid]),
    ];
}

/** Total useful+funny+cool votes cast on this user's published reviews by other travelers. */
function rmt_votes_received(int $uid): int {
    return (int) (q_one("SELECT COUNT(*) c FROM review_votes rv JOIN reviews r ON r.id=rv.review_id
                         WHERE r.user_id=? AND r.status='published'", [$uid])['c'] ?? 0);
}

/**
 * Vote counts for one review, split by flavor.
 * @return array{useful:int, funny:int, cool:int}
 */
function rmt_review_vote_counts(int $reviewId): array {
    $rows = q_all('SELECT vote_type, COUNT(*) c FROM review_votes WHERE review_id=? GROUP BY vote_type', [$reviewId]);
    $out = array_fill_keys(RMT_REVIEW_VOTE_TYPES, 0);
    foreach ($rows as $r) {
        if (isset($out[$r['vote_type']])) $out[$r['vote_type']] = (int) $r['c'];
    }
    return $out;
}

/** Which of the three vote flavors $uid has already cast on this review. */
function rmt_review_my_votes(int $reviewId, int $uid): array {
    $rows = q_all('SELECT vote_type FROM review_votes WHERE review_id=? AND user_id=?', [$reviewId, $uid]);
    return array_column($rows, 'vote_type');
}

/** Compliments received, grouped by type, most-recent type first. */
function rmt_compliments_received(int $uid): array {
    return q_all("SELECT type, COUNT(*) c, MAX(created_at) last_at FROM compliments
                  WHERE to_user_id=? GROUP BY type ORDER BY last_at DESC", [$uid]);
}

/** Compliment types $fromUid has already sent $toUid (so the UI can show "sent" not a live count). */
function rmt_compliments_sent_by(int $fromUid, int $toUid): array {
    $rows = q_all('SELECT type FROM compliments WHERE from_user_id=? AND to_user_id=?', [$fromUid, $toUid]);
    return array_column($rows, 'type');
}

/** Badges a user currently holds. */
function rmt_user_badges(int $uid): array {
    // Filtered on read, not deleted. The editorial account was awarded Founding Traveler before
    // badges were restricted to travelers, and the row is history rather than something to erase --
    // but a traveler-reputation badge on a staff account is a claim the account is not entitled to
    // make, so it is not displayed. Stopping future awards was not enough on its own: the ones
    // already granted kept showing.
    $u = q_one('SELECT role FROM users WHERE id = ?', [$uid]);
    if ($u && rmt_is_editorial(['role' => (string) $u['role']])) return [];

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
 * Does this user qualify as Elite Traveler? Modeled on Yelp Elite but rule-based rather than
 * staff-curated: enough published reviews, spread across enough distinct destinations (not one
 * place reviewed ten times), that other travelers have actually found useful/funny/cool.
 */
function rmt_qualifies_elite_traveler(int $uid): bool {
    $u = q_one('SELECT id, status FROM users WHERE id = ?', [$uid]);
    if (!$u || $u['status'] !== 'active') return false;
    $stats = rmt_profile_stats($uid);
    return $stats['reviews'] >= RMT_ELITE_MIN_REVIEWS
        && $stats['places'] >= RMT_ELITE_MIN_DESTINATIONS
        && $stats['votes'] >= RMT_ELITE_MIN_VOTES;
}

/**
 * Evaluate and grant any badges this user has newly earned. Idempotent — safe to call on every
 * publish. Returns the slugs newly awarded.
 */

/* ===========================================================================
 * Contribution milestones
 * ======================================================================== */

/**
 * How many published reviews somebody has. Counted, never stored.
 *
 * A milestone standing on a counter is a milestone that survives the review being removed, which
 * is how a reputation system starts saying things that are not true. Every rule below recounts.
 */
function rmt_user_review_count(int $uid): int {
    return (int) (q_one("SELECT COUNT(*) c FROM reviews WHERE user_id = ? AND status = 'published'",
                        [$uid])['c'] ?? 0);
}

/** Photographs the user has actually posted, on a review or a trip. */
function rmt_user_photo_count(int $uid): int {
    return (int) (q_one("SELECT COUNT(*) c FROM (
                            SELECT rp.id FROM review_photos rp JOIN reviews r ON r.id = rp.review_id
                             WHERE r.user_id = ? AND r.status = 'published'
                            UNION ALL
                            SELECT tp.id FROM trip_photos tp JOIN trips t ON t.id = tp.trip_id
                             WHERE t.user_id = ? AND t.status = 'published') x", [$uid, $uid])['c'] ?? 0);
}

/**
 * Times other travelers marked this person's reviews useful.
 *
 * Only votes on reviews that are still published count, and a person's votes on their own reviews
 * are excluded -- a reputation you can award yourself is not one.
 */
function rmt_user_helpful_count(int $uid): int {
    return (int) (q_one("SELECT COUNT(*) c FROM review_votes rv
                           JOIN reviews r ON r.id = rv.review_id
                          WHERE r.user_id = ? AND r.status = 'published'
                            AND rv.vote_type = 'useful' AND rv.user_id <> ?", [$uid, $uid])['c'] ?? 0);
}

/**
 * Every badge and the rule that earns it, in one place.
 *
 * Centralised on purpose: a threshold scattered through templates as `if ($count >= 5)` is a
 * threshold nobody can change, and one that will eventually disagree with itself in two places.
 * Adding a milestone is a row in this map and a row in the badges table.
 */
const RMT_BADGE_RULES = [
    'founding-traveler' => 'rmt_qualifies_founding_traveler',
    'elite-traveler'    => 'rmt_qualifies_elite_traveler',
    'first-review'      => 'rmt_qualifies_first_review',
    'reviewer-5'        => 'rmt_qualifies_reviewer_5',
    'reviewer-10'       => 'rmt_qualifies_reviewer_10',
    'reviewer-25'       => 'rmt_qualifies_reviewer_25',
    'photo-contributor' => 'rmt_qualifies_photo_contributor',
    'helpful-reviewer'  => 'rmt_qualifies_helpful_reviewer',
];

function rmt_qualifies_first_review(int $uid): bool      { return rmt_user_review_count($uid) >= 1; }
function rmt_qualifies_reviewer_5(int $uid): bool        { return rmt_user_review_count($uid) >= 5; }
function rmt_qualifies_reviewer_10(int $uid): bool       { return rmt_user_review_count($uid) >= 10; }
function rmt_qualifies_reviewer_25(int $uid): bool       { return rmt_user_review_count($uid) >= 25; }
function rmt_qualifies_photo_contributor(int $uid): bool { return rmt_user_photo_count($uid) >= 5; }
function rmt_qualifies_helpful_reviewer(int $uid): bool  { return rmt_user_helpful_count($uid) >= 10; }

function rmt_award_badges(int $uid): array {
    // Badges are TRAVELER reputation: they say somebody went places and wrote about them. The
    // editorial account publishes researched articles and has never claimed to have gone anywhere,
    // so it cannot earn them -- and it would have earned every one of them, because
    // rmt_user_review_count() counts published reviews and 185 of them are ours. "First Review",
    // "25 Reviews" and "Helpful Reviewer" on a staff account would each be a small lie told by a
    // counter.
    $u = q_one('SELECT role FROM users WHERE id = ?', [$uid]);
    if ($u && rmt_is_editorial(['role' => (string) $u['role']])) return [];

    $rules = RMT_BADGE_RULES;
    $awarded = [];
    foreach ($rules as $slug => $qualifies) {
        if (!$qualifies($uid)) continue;
        $b = q_one('SELECT id FROM badges WHERE slug = ?', [$slug]);
        if (!$b) continue;
        $has = q_one('SELECT 1 FROM user_badges WHERE user_id=? AND badge_id=?', [$uid, (int) $b['id']]);
        if ($has) continue;
        q_run('INSERT INTO user_badges (user_id, badge_id, awarded_at) VALUES (?,?,?)',
              [$uid, (int) $b['id'], date('Y-m-d H:i:s')]);
        $awarded[] = $slug;
    }
    return $awarded;
}

/**
 * Top reviewers, ranked by a weighted score: 3 points per published review, 1 point per
 * useful/funny/cool vote received, 2 points per compliment received. Optionally scoped to a
 * single destination (reviews AND votes both restricted to that destination) so each destination
 * page can surface its own top local voice, not just the site-wide leaders.
 */
function rmt_top_reviewers(?int $destinationId = null, int $limit = 20): array {
    $destCond = $destinationId !== null ? 'AND r.destination_id = ?' : '';
    $voteDestCond = $destinationId !== null ? 'AND r2.destination_id = ?' : '';
    $args = $destinationId !== null ? [$destinationId, $destinationId] : [];
    return q_all("SELECT u.id, u.username, p.display_name, p.avatar_url, p.home_city,
                         COUNT(DISTINCT r.id) AS review_count,
                         COALESCE(v.votes,0) AS votes,
                         COALESCE(c.compliments,0) AS compliments,
                         (COUNT(DISTINCT r.id)*3 + COALESCE(v.votes,0) + COALESCE(c.compliments,0)*2) AS score
                  FROM users u
                  JOIN reviews r ON r.user_id = u.id AND r.status='published' $destCond
                  LEFT JOIN profiles p ON p.user_id = u.id
                  LEFT JOIN (SELECT r2.user_id, COUNT(*) votes FROM review_votes rv
                             JOIN reviews r2 ON r2.id = rv.review_id AND r2.status='published' $voteDestCond
                             GROUP BY r2.user_id) v ON v.user_id = u.id
                  LEFT JOIN (SELECT to_user_id, COUNT(*) compliments FROM compliments
                             GROUP BY to_user_id) c ON c.to_user_id = u.id
                  WHERE u.status = 'active'
                  GROUP BY u.id, u.username, p.display_name, p.avatar_url, p.home_city, v.votes, c.compliments
                  ORDER BY score DESC, review_count DESC, u.id ASC
                  LIMIT $limit", $args);
}

/** Followers of a user, newest first. */
function rmt_followers(int $uid, int $limit = 200): array {
    return q_all("SELECT u.id, u.username, p.display_name, p.avatar_url, p.bio, p.home_city, f.created_at,
                         (SELECT COUNT(*) FROM reviews r
                           WHERE r.user_id = u.id AND r.status = 'published') review_count,
                         0 pad
                  FROM follows f JOIN users u ON u.id = f.follower_id
                  LEFT JOIN profiles p ON p.user_id = u.id
                  WHERE f.followee_id = ? AND u.status = 'active'
                  ORDER BY f.created_at DESC, u.id DESC LIMIT $limit", [$uid]);
}

/** Users a user follows, newest first. */
function rmt_following(int $uid, int $limit = 200): array {
    return q_all("SELECT u.id, u.username, p.display_name, p.avatar_url, p.bio, p.home_city, f.created_at,
                         (SELECT COUNT(*) FROM reviews r
                           WHERE r.user_id = u.id AND r.status = 'published') review_count,
                         0 pad
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
