<?php
/**
 * Trip matching: who else is going to be where you are, when you are.
 *
 * The site already knew everything it needed to answer that question and never asked it. "Who's
 * going" listed every public plan in date order, so a traveler with dates in Lisbon had to read a
 * global list and do the overlap arithmetic in their head. Nobody does that, so nobody met anybody.
 *
 * Two tiers, and they are different promises:
 *
 *   overlap    same destination, dates that actually intersect. This is the strong one: there is a
 *              real week where two people are in the same city, so there is something to arrange.
 *   wishlist   same saved destinations, no dates yet. Weaker, but it is the only signal most new
 *              members give us on the day they join, and it is what keeps the page from being
 *              empty for somebody who has not booked anything.
 *
 * Everything here keeps the promises the rest of the site already makes: destination and date
 * range only, never a precise location; a plan is matched only when its visibility already allowed
 * the viewer to see it; a block hides both people from each other, in both directions.
 */
declare(strict_types=1);

/** Below this many shared saved destinations, "you both like cities" is not a signal. */
const RMT_MATCH_MIN_SHARED = 2;

/** How many people one new plan may notify. A popular city must not become a mailing list. */
const RMT_MATCH_NOTIFY_MAX = 25;

const RMT_MATCH_NOTIFY_TYPE = 'trip_match';

/**
 * Days two inclusive date ranges share. 0 when they do not touch.
 *
 * Inclusive because a traveler who lands on the 8th and one who leaves on the 8th do have a day,
 * and telling them they have none is the small wrongness that makes a feature feel broken.
 */
function rmt_match_overlap_days(string $aFrom, string $aTo, string $bFrom, string $bTo): int {
    if ($aFrom === '' || $aTo === '' || $bFrom === '' || $bTo === '') return 0;
    $start = max($aFrom, $bFrom);
    $end   = min($aTo, $bTo);
    if ($start > $end) return 0;
    $s = strtotime($start . ' 00:00:00 UTC');
    $e = strtotime($end . ' 00:00:00 UTC');
    if ($s === false || $e === false) return 0;
    return (int) round(($e - $s) / 86400) + 1;
}

/** The window two ranges share, as ['from','to','days'], or null when they miss each other. */
function rmt_match_overlap_window(string $aFrom, string $aTo, string $bFrom, string $bTo): ?array {
    $days = rmt_match_overlap_days($aFrom, $aTo, $bFrom, $bTo);
    if ($days < 1) return null;
    return ['from' => max($aFrom, $bFrom), 'to' => min($aTo, $bTo), 'days' => $days];
}

/**
 * SQL excluding anybody either side of a block. Both directions, deliberately: a person I blocked
 * should not appear to me, and I should not appear to a person who blocked me.
 *
 * @return array{0:string,1:int} fragment, and how many times the viewer id must be bound
 */
function rmt_match_block_sql(string $col): array {
    return ["NOT EXISTS (SELECT 1 FROM blocks b WHERE (b.blocker_id = ? AND b.blocked_id = $col)
                                                   OR (b.blocker_id = $col AND b.blocked_id = ?))", 2];
}

/**
 * Travelers whose dates overlap one of mine, soonest first.
 *
 * Visibility is not re-invented here: the rule the destination page already uses decides whether
 * the other person's plan was one I was allowed to see, so a followers-only plan matches only
 * somebody who follows them, and a private plan matches nobody.
 *
 * @return list<array<string,mixed>>
 */
function rmt_trip_matches(int $userId, int $limit = 40): array {
    if ($userId < 1) return [];
    $today = gmdate('Y-m-d');
    [$visSql, $visArgs] = rmt_going_visibility_sql('o', ['id' => $userId]);
    [$blockSql] = rmt_match_block_sql('o.user_id');
    $rows = q_all(
        "SELECT o.user_id, o.date_from their_from, o.date_to their_to, o.visibility,
                g.date_from my_from, g.date_to my_to,
                d.slug dest_slug, d.name dest_name, d.id dest_id,
                u.username, p.display_name, p.avatar_url, p.home_city
           FROM going g
           JOIN going o ON o.destination_id = g.destination_id AND o.user_id <> g.user_id
           JOIN destinations d ON d.id = g.destination_id
           JOIN users u ON u.id = o.user_id
      LEFT JOIN profiles p ON p.user_id = o.user_id
          WHERE g.user_id = ?
            AND u.status = 'active'
            AND o.date_from <= g.date_to AND o.date_to >= g.date_from
            AND g.date_to >= ?
            AND $visSql
            AND $blockSql
       ORDER BY g.date_from, o.date_from
          LIMIT " . (int) $limit,
        array_merge([$userId, $today], $visArgs, [$userId, $userId])
    );
    foreach ($rows as $i => $r) {
        $w = rmt_match_overlap_window((string) $r['my_from'], (string) $r['my_to'],
                                      (string) $r['their_from'], (string) $r['their_to']);
        $rows[$i]['overlap_from'] = $w['from'] ?? null;
        $rows[$i]['overlap_to']   = $w['to'] ?? null;
        $rows[$i]['overlap_days'] = $w['days'] ?? 0;
    }
    return $rows;
}

/**
 * The same question from one plan's point of view: who does this plan land on top of. Used when a
 * plan is saved, so the people it affects hear about it instead of waiting to go looking.
 *
 * @return list<int> user ids
 */
function rmt_trip_match_user_ids(int $actorId, int $destId, string $from, string $to, int $limit = RMT_MATCH_NOTIFY_MAX): array {
    if ($actorId < 1 || $destId < 1 || $from === '' || $to === '') return [];
    [$blockSql] = rmt_match_block_sql('o.user_id');
    $rows = q_all(
        "SELECT o.user_id
           FROM going o JOIN users u ON u.id = o.user_id
          WHERE o.destination_id = ? AND o.user_id <> ?
            AND u.status = 'active'
            AND o.date_from <= ? AND o.date_to >= ?
            AND $blockSql
       ORDER BY o.date_from
          LIMIT " . (int) $limit,
        [$destId, $actorId, $to, $from, $actorId, $actorId]
    );
    return array_map(static fn(array $r): int => (int) $r['user_id'], $rows);
}

/**
 * Tell overlapping travelers about a newly shared plan.
 *
 * Only public plans notify: a followers-only plan is something you told your followers, not an
 * announcement, and a private one is a note to yourself. One notification per recipient per plan,
 * ever, so editing the dates on the same trip does not tap the same people again.
 *
 * @return int notifications written
 */
function rmt_match_notify(int $actorId, int $goingId, int $destId, string $from, string $to, string $visibility): int {
    if ($visibility !== 'public' || $goingId < 1) return 0;
    $now = date('Y-m-d H:i:s');
    $sent = 0;
    foreach (rmt_trip_match_user_ids($actorId, $destId, $from, $to) as $uid) {
        if ($uid < 1 || $uid === $actorId) continue;
        $seen = q_one('SELECT 1 x FROM notifications WHERE user_id=? AND type=? AND actor_id=? AND target_id=?',
                      [$uid, RMT_MATCH_NOTIFY_TYPE, $actorId, $goingId]);
        if ($seen) continue;
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
              [$uid, RMT_MATCH_NOTIFY_TYPE, $actorId, 'going', $goingId, $now]);
        $sent++;
    }
    return $sent;
}

/**
 * Travelers who want to go where I want to go, by saved destinations.
 *
 * This is the cold-start half of the page. Somebody who joined an hour ago has saved four cities
 * and booked nothing, and this is the only honest thing there is to show them.
 *
 * @return list<array<string,mixed>>
 */
function rmt_wishlist_matches(int $userId, int $limit = 12): array {
    if ($userId < 1) return [];
    [$blockSql] = rmt_match_block_sql('s.user_id');
    return q_all(
        "SELECT s.user_id, u.username, p.display_name, p.avatar_url, p.home_city,
                COUNT(*) shared
           FROM saves s
           JOIN saves mine ON mine.user_id = ? AND mine.target_type = 'destination'
                          AND mine.target_id = s.target_id
           JOIN users u ON u.id = s.user_id
      LEFT JOIN profiles p ON p.user_id = s.user_id
          WHERE s.target_type = 'destination' AND s.user_id <> ?
            AND u.status = 'active'
            AND $blockSql
       GROUP BY s.user_id, u.username, p.display_name, p.avatar_url, p.home_city
         HAVING COUNT(*) >= " . RMT_MATCH_MIN_SHARED . "
       ORDER BY shared DESC, s.user_id
          LIMIT " . (int) $limit,
        [$userId, $userId, $userId, $userId]
    );
}

/**
 * Which destinations I share with each of those people, so the page can name them instead of
 * saying "3 in common". One query for everybody, because the alternative is one per row.
 *
 * @param list<int> $otherIds
 * @return array<int, list<array{slug:string,name:string}>>
 */
function rmt_match_shared_destinations(int $userId, array $otherIds): array {
    $ids = array_values(array_filter(array_map('intval', $otherIds), static fn(int $i): bool => $i > 0));
    if ($userId < 1 || !$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = q_all(
        "SELECT s.user_id, d.slug, d.name
           FROM saves s
           JOIN saves mine ON mine.user_id = ? AND mine.target_type = 'destination'
                          AND mine.target_id = s.target_id
           JOIN destinations d ON d.id = s.target_id
          WHERE s.target_type = 'destination' AND s.user_id IN ($in)
       ORDER BY d.name",
        array_merge([$userId], $ids)
    );
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['user_id']][] = ['slug' => (string) $r['slug'], 'name' => (string) $r['name']];
    }
    return $out;
}

/** One number, for the nav and for deciding what the page leads with. */
function rmt_match_count(int $userId): int {
    return count(rmt_trip_matches($userId, 99));
}

/* --------------------------------------------------- from a match to an actual plan */

/**
 * Meetups happening in a city inside a traveler's own window.
 *
 * A match tells two people they will be in the same place. It does not tell them what to do about
 * it, and "message a stranger" is a bigger first step than most people take. An event that already
 * exists, on a date they are already there, is the smaller one.
 *
 * @return list<array<string,mixed>>
 */
function rmt_meetups_in_window(int $destId, string $from, string $to, int $limit = 5): array {
    if ($destId < 1 || $from === '' || $to === '') return [];
    return q_all(
        "SELECT m.*, u.username host_username,
                (SELECT COUNT(*) FROM meetup_rsvps r WHERE r.meetup_id=m.id AND r.status='going') going_count
           FROM meetups m JOIN users u ON u.id = m.host_id
          WHERE m.destination_id = ? AND m.status = 'published' AND m.visibility = 'public'
            AND m.date_start >= ? AND m.date_start <= ?
       ORDER BY m.date_start
          LIMIT " . (int) $limit,
        [$destId, $from . ' 00:00:00', $to . ' 23:59:59']
    );
}

/**
 * Tell the travelers who will already be in town that somebody is hosting something.
 *
 * Meetups had exactly one way to be found: opening the meetups page and hoping. The people most
 * likely to come are the ones who have already said they will be in that city on that day, and
 * the site knew who they were and never told them.
 *
 * @return int notifications written
 */
function rmt_meetup_notify_travelers(int $meetupId, int $hostId, int $destId, string $dateStart): int {
    if ($meetupId < 1 || $destId < 1) return 0;
    $day = substr(trim($dateStart), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) return 0;
    [$blockSql] = rmt_match_block_sql('o.user_id');
    $rows = q_all(
        "SELECT o.user_id
           FROM going o JOIN users u ON u.id = o.user_id
          WHERE o.destination_id = ? AND o.user_id <> ?
            AND u.status = 'active'
            AND o.date_from <= ? AND o.date_to >= ?
            AND $blockSql
       ORDER BY o.date_from
          LIMIT " . RMT_MATCH_NOTIFY_MAX,
        [$destId, $hostId, $day, $day, $hostId, $hostId]
    );
    $now = date('Y-m-d H:i:s');
    $sent = 0;
    foreach ($rows as $r) {
        $uid = (int) $r['user_id'];
        if ($uid < 1 || $uid === $hostId) continue;
        $seen = q_one('SELECT 1 x FROM notifications WHERE user_id=? AND type=? AND target_id=?',
                      [$uid, 'meetup_nearby', $meetupId]);
        if ($seen) continue;
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
              [$uid, 'meetup_nearby', $hostId, 'meetup', $meetupId, $now]);
        $sent++;
    }
    return $sent;
}
