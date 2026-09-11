<?php
declare(strict_types=1);

/**
 * The loops that bring somebody back.
 *
 * Everything the site notifies about today is a reaction to somebody else: a follow, a like, a
 * reply, a match. Those only fire when the place is already busy, which is the wrong way round for
 * a young network. The two moments that belong to the member themselves are the ones nobody was
 * using:
 *
 *   a trip that starts soon    "Lisbon in three days. Four travelers will be there while you are."
 *   a trip that just ended     "How was Lisbon? Photos and a review are what the next person reads."
 *
 * Both are about a thing the member already told us, at the moment it matters, which is the only
 * kind of notification worth sending.
 *
 * SCHEDULING. There is no cron for this and there cannot be from a session: pushing a GitHub
 * workflow file needs a token scope this machine's credentials do not have. So it runs
 * opportunistically, throttled, on a page a signed-in member loads anyway, exactly as
 * rmt_seo_flush_if_due() rides on a sitemap request. When the workflow token exists it should move
 * to a real schedule; nothing else about it changes.
 *
 * IDEMPOTENCE. A notification row is its own record: before sending, this asks whether one of that
 * type already exists for that trip. Two members loading the feed in the same second cannot
 * produce two copies, and a member who reads it and comes back tomorrow does not get it again.
 */

/** How close a trip has to be to be worth mentioning. */
const RMT_TRIP_SOON_DAYS = 3;

/** How long after a trip ends to ask how it went. */
const RMT_TRIP_OVER_GRACE_DAYS = 1;

/**
 * Send what is due, at most once every $throttleMinutes across the whole site.
 *
 * @return int notifications created
 */
function rmt_lifecycle_if_due(int $throttleMinutes = 30): int {
    try {
        /* The last run is remembered as a rate-limit bucket, because one already exists, is
           already cleaned up, and a whole table for one timestamp is a table to maintain. */
        if (!rmt_rate_ok('lifecycle_sweep', 'global', 1, $throttleMinutes * 60)) return 0;
    } catch (\PDOException $e) {
        return 0;
    }
    try {
        return rmt_lifecycle_run();
    } catch (\PDOException $e) {
        error_log('[rmt_lifecycle] ' . $e->getMessage());
        return 0;
    }
}

/**
 * The sweep itself. Safe to call directly (a script, a test).
 *
 * @return int notifications created
 */
function rmt_lifecycle_run(int $limit = 200): int {
    $made = 0;
    $today = date('Y-m-d');
    $soonEdge = date('Y-m-d', strtotime('+' . RMT_TRIP_SOON_DAYS . ' days'));
    $overEdge = date('Y-m-d', strtotime('-' . RMT_TRIP_OVER_GRACE_DAYS . ' days'));
    $now = date('Y-m-d H:i:s');

    /* Starting soon. Any visibility: this goes to the person whose trip it is, and a private trip
       is still their trip. */
    $soon = q_all(
        "SELECT t.id, t.user_id, t.destination_id, t.date_from, t.date_to
           FROM trips t
          WHERE t.status = 'published' AND t.date_from IS NOT NULL AND t.date_to IS NOT NULL
            AND t.date_from >= ? AND t.date_from <= ?
            AND NOT EXISTS (SELECT 1 FROM notifications n
                             WHERE n.user_id = t.user_id AND n.type = 'trip_soon'
                               AND n.target_type = 'trip' AND n.target_id = t.id)
          LIMIT " . (int) $limit, [$today, $soonEdge]);

    foreach ($soon as $t) {
        q_run('INSERT INTO notifications (user_id, type, actor_id, target_type, target_id, created_at)
               VALUES (?,?,?,?,?,?)',
              [(int) $t['user_id'], 'trip_soon', null, 'trip', (int) $t['id'], $now]);
        $made++;
    }

    /* Just finished. Asked once, the day after they get back, when it is still fresh and the
       photographs are still on the phone. */
    $over = q_all(
        "SELECT t.id, t.user_id, t.destination_id
           FROM trips t
          WHERE t.status = 'published' AND t.date_to IS NOT NULL
            AND t.date_to < ? AND t.date_to >= ?
            AND NOT EXISTS (SELECT 1 FROM notifications n
                             WHERE n.user_id = t.user_id AND n.type = 'trip_over'
                               AND n.target_type = 'trip' AND n.target_id = t.id)
          LIMIT " . (int) $limit,
        [$today, date('Y-m-d', strtotime('-14 days'))]);

    foreach ($over as $t) {
        q_run('INSERT INTO notifications (user_id, type, actor_id, target_type, target_id, created_at)
               VALUES (?,?,?,?,?,?)',
              [(int) $t['user_id'], 'trip_over', null, 'trip', (int) $t['id'], $now]);
        $made++;
    }

    return $made;
}

/**
 * How many other travelers will be in that city while this trip is on, for the line the
 * notification prints. Public trips only, because that is what the member could go and look at.
 */
function rmt_lifecycle_company(int $tripId): int {
    $t = q_one('SELECT id, user_id, destination_id, date_from, date_to FROM trips WHERE id = ?', [$tripId]);
    if (!$t || empty($t['destination_id']) || empty($t['date_from'])) return 0;
    return (int) (q_one(
        "SELECT COUNT(DISTINCT user_id) n FROM trips
          WHERE destination_id = ? AND id <> ? AND user_id <> ?
            AND status = 'published' AND visibility = 'public'
            AND date_from IS NOT NULL AND date_to IS NOT NULL
            AND date_from <= ? AND date_to >= ?",
        [(int) $t['destination_id'], (int) $t['id'], (int) $t['user_id'],
         (string) $t['date_to'], (string) $t['date_from']])['n'] ?? 0);
}
