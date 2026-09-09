<?php
declare(strict_types=1);

/**
 * Telling the people who saved a city that somebody turned up in it.
 *
 * Saving a city was a bookmark, then it became a feed subscription, and it is still silent: the
 * first member in Lisbon has no way of hearing that the second one arrived, which on a young
 * network is the only event that matters. A feed only works for somebody already looking at the
 * site, and the whole problem is that there is not yet a reason to look.
 *
 * So a public plan or a new meetup reaches the people who said that city interests them. It is a
 * deliberate act on both sides: they saved the city, and the traveler chose to be public. Nothing
 * here reaches somebody who did neither.
 */

/** Notification types this file writes. Both render on /notifications and in a push. */
const RMT_CITY_NOTIFY_TYPES = ['city_going', 'city_meetup', 'city_review'];

/** At most this many people are told about one event, newest savers first. */
const RMT_CITY_NOTIFY_MAX = 50;

/**
 * Who watches this city, minus one person (the traveler doing the thing).
 *
 * @return int[]
 */
function rmt_city_watchers(int $destId, int $exceptUserId = 0): array {
    if ($destId < 1) return [];
    $rows = q_all("SELECT s.user_id
                     FROM saves s JOIN users u ON u.id = s.user_id
                    WHERE s.target_type = 'destination' AND s.target_id = ?
                      AND u.status = 'active' AND s.user_id <> ?
                 ORDER BY s.user_id
                    LIMIT " . RMT_CITY_NOTIFY_MAX, [$destId, $exceptUserId]);
    return array_map(static fn(array $r) => (int) $r['user_id'], $rows);
}

/**
 * Tell the watchers of a city about one thing that happened in it.
 *
 * Deduped on (user, type, target): a traveler editing their dates twice in an evening is one piece
 * of news, and the second notification would only teach people to ignore the first. A blocked
 * relationship is honoured in both directions, since this puts two strangers in the same city.
 *
 * @return int how many people were told
 */
function rmt_city_notify(int $destId, string $type, int $actorId, string $targetType, int $targetId): int {
    if (!in_array($type, RMT_CITY_NOTIFY_TYPES, true)) return 0;
    $now = date('Y-m-d H:i:s');
    $sent = 0;
    foreach (rmt_city_watchers($destId, $actorId) as $uid) {
        if (function_exists('rmt_is_blocked') && (rmt_is_blocked($uid, $actorId) || rmt_is_blocked($actorId, $uid))) {
            continue;
        }
        $seen = q_one('SELECT 1 x FROM notifications WHERE user_id=? AND type=? AND target_type=? AND target_id=?',
                      [$uid, $type, $targetType, $targetId]);
        if ($seen) continue;
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at)
               VALUES (?,?,?,?,?,?)', [$uid, $type, $actorId, $targetType, $targetId, $now]);
        $sent++;
    }
    return $sent;
}
