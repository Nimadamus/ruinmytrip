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
        "SELECT o.id their_trip_id, o.user_id, o.date_from their_from, o.date_to their_to, o.visibility,
                COALESCE(o.travel_style, p.travel_style) travel_style,
                g.id my_trip_id, g.date_from my_from, g.date_to my_to,
                d.slug dest_slug, d.name dest_name, d.id dest_id,
                u.username, p.display_name, p.avatar_url, p.home_city
           FROM trips g
           JOIN trips o ON o.destination_id = g.destination_id AND o.user_id <> g.user_id
           JOIN destinations d ON d.id = g.destination_id
           JOIN users u ON u.id = o.user_id
      LEFT JOIN profiles p ON p.user_id = o.user_id
          WHERE g.user_id = ?
            AND u.status = 'active'
            AND g.status = 'published' AND o.status = 'published'
            AND g.date_from IS NOT NULL AND o.date_from IS NOT NULL
            AND o.date_from <= g.date_to AND o.date_to >= g.date_from
            AND g.date_to >= ?
            AND COALESCE(o.open_to_meeting, 1) = 1
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

    /* One row per person, not one per pair of overlapping trips.
       Somebody with two trips to Lisbon that both land on mine produced two identical looking
       cards with different dates, in a list headed "On your dates" that is meant to be short
       enough to read. The soonest overlap leads, and the others are counted rather than dropped,
       because "also 4 days in October" is a reason to say hello and losing it would be losing a
       fact. Grouped after the window arithmetic so the count is of real overlaps. */
    $byPerson = [];
    foreach ($rows as $r) {
        $uid = (int) $r['user_id'];
        if (!isset($byPerson[$uid])) {
            $r['other_overlaps'] = 0;
            $byPerson[$uid] = $r;
            continue;
        }
        $byPerson[$uid]['other_overlaps']++;
        // The soonest one leads: it is the one a person can still act on.
        if ((string) $r['overlap_from'] < (string) $byPerson[$uid]['overlap_from']) {
            $keep = $byPerson[$uid]['other_overlaps'];
            $r['other_overlaps'] = $keep;
            $byPerson[$uid] = $r;
        }
    }
    return array_values($byPerson);
}

/**
 * Take back the notifications a trip no longer justifies.
 *
 * Two cases, and they are the same case. Somebody moves their dates out of everybody else's, or
 * deletes the trip entirely: the taps on the shoulder it caused are now about something that is
 * not true. Unread ones are removed, because nobody has acted on them and a notification for a
 * trip that no longer overlaps is worse than no notification at all.
 *
 * Read ones are LEFT ALONE, on purpose. Somebody has already seen it, possibly acted on it, and
 * deleting what a person has read is rewriting their history rather than correcting ours.
 *
 * @param list<int> $keep recipients who still overlap and should keep theirs
 * @return int notifications removed
 */
function rmt_match_notify_clear(int $tripId, array $keep = []): int {
    if ($tripId < 1) return 0;
    $sql = "DELETE FROM notifications
             WHERE type = ? AND target_type = 'going' AND target_id = ? AND read_at IS NULL";
    $args = [RMT_MATCH_NOTIFY_TYPE, $tripId];
    $keep = array_values(array_unique(array_map('intval', $keep)));
    if ($keep) {
        $sql .= ' AND user_id NOT IN (' . implode(',', array_fill(0, count($keep), '?')) . ')';
        $args = array_merge($args, $keep);
    }
    /* Prepared here rather than through q_run(), which returns a last insert id and would report
       every delete as zero rows removed. The count is the return value this function is for. */
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->rowCount();
}

/**
 * Travelers in the same city who just miss: their trip ends before mine starts, or starts after
 * mine ends, inside a window of a fortnight either way.
 *
 * Why it is worth a section of its own. On a network this small the honest answer to "who else is
 * going to Bangkok while I am there" is very often nobody, and a page that stops at that has
 * thrown away the person who was there the week before and could tell them everything. A near miss
 * is not a match and is never drawn as one: it is labelled by how it misses, before or after, and
 * by how many days.
 *
 * Every rule the overlap list obeys applies here too, in the same words: the visibility clause
 * decides what this viewer was allowed to see, blocks are honoured, and somebody who said they do
 * not want to be met is not on it.
 *
 * @return list<array<string,mixed>>
 */
function rmt_trip_near_misses(int $userId, int $windowDays = 14, int $limit = 12): array {
    if ($userId < 1) return [];
    $today = gmdate('Y-m-d');
    [$visSql, $visArgs] = rmt_going_visibility_sql('o', ['id' => $userId]);
    [$blockSql] = rmt_match_block_sql('o.user_id');
    $rows = q_all(
        "SELECT o.id their_trip_id, o.user_id, o.date_from their_from, o.date_to their_to,
                COALESCE(o.travel_style, p.travel_style) travel_style,
                g.id my_trip_id, g.date_from my_from, g.date_to my_to,
                d.slug dest_slug, d.name dest_name, d.id dest_id,
                u.username, p.display_name, p.avatar_url, p.home_city
           FROM trips g
           JOIN trips o ON o.destination_id = g.destination_id AND o.user_id <> g.user_id
           JOIN destinations d ON d.id = g.destination_id
           JOIN users u ON u.id = o.user_id
      LEFT JOIN profiles p ON p.user_id = o.user_id
          WHERE g.user_id = ?
            AND u.status = 'active'
            AND g.status = 'published' AND o.status = 'published'
            AND g.date_from IS NOT NULL AND o.date_from IS NOT NULL
            AND g.date_to >= ?
            AND NOT (o.date_from <= g.date_to AND o.date_to >= g.date_from)
            AND COALESCE(o.open_to_meeting, 1) = 1
            AND $visSql
            AND $blockSql
       ORDER BY o.date_from
          LIMIT " . (int) max(1, $limit * 3),
        array_merge([$userId, $today], $visArgs, [$userId, $userId])
    );

    $out = [];
    foreach ($rows as $r) {
        /* Which side, and by how far. Counted in whole days from the edge that is nearest, so
           "four days after you leave" means what it says. */
        $myFrom = (int) strtotime((string) $r['my_from']);
        $myTo   = (int) strtotime((string) $r['my_to']);
        $thFrom = (int) strtotime((string) $r['their_from']);
        $thTo   = (int) strtotime((string) $r['their_to']);
        if ($thTo < $myFrom) { $side = 'before'; $gap = (int) round(($myFrom - $thTo) / 86400); }
        else                 { $side = 'after';  $gap = (int) round(($thFrom - $myTo) / 86400); }
        if ($gap < 1 || $gap > $windowDays) continue;
        $uid = (int) $r['user_id'];
        // One row per person, the nearest miss, for the same reason the overlap list groups.
        if (isset($out[$uid]) && (int) $out[$uid]['gap_days'] <= $gap) continue;
        $r['side'] = $side;
        $r['gap_days'] = $gap;
        $out[$uid] = $r;
    }
    usort($out, static fn(array $a, array $b) => $a['gap_days'] <=> $b['gap_days']);
    return array_slice(array_values($out), 0, $limit);
}

/**
 * Interests for a set of members in one query, keyed by user id.
 *
 * A card reads better with two or three words about what somebody is actually into, and a list of
 * twelve cards must not be twelve queries to say so.
 *
 * @return array<int,list<string>>
 */
function rmt_interests_for_many(array $userIds): array {
    $ids = array_values(array_unique(array_map('intval', $userIds)));
    if (!$ids || !defined('RMT_INTERESTS')) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $out = [];
    foreach (q_all("SELECT user_id, interest FROM profile_interests WHERE user_id IN ($in)", $ids) as $r) {
        $k = (string) $r['interest'];
        if (!isset(RMT_INTERESTS[$k])) continue;   // a key we no longer publish is not a label
        $out[(int) $r['user_id']][] = $k;
    }
    return $out;
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
           FROM trips o JOIN users u ON u.id = o.user_id
          WHERE o.destination_id = ? AND o.user_id <> ?
            AND u.status = 'active' AND o.status = 'published'
            AND o.date_from IS NOT NULL AND o.date_to IS NOT NULL
            AND o.date_from <= ? AND o.date_to >= ?
            AND COALESCE(o.open_to_meeting, 1) = 1
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
    /* Somebody who said they are not looking to meet is not announced to strangers either. The
       notification is the same offer as the match list, delivered rather than browsed, and a
       control that stops one and not the other would not be a control. */
    $trip = q_one('SELECT open_to_meeting FROM trips WHERE id = ?', [$goingId]);
    if ($trip && $trip['open_to_meeting'] !== null && (int) $trip['open_to_meeting'] === 0) return 0;
    $now = date('Y-m-d H:i:s');
    /* Read once rather than per recipient: the city is the only thing the email says. */
    $destName = (string) (q_one('SELECT name FROM destinations WHERE id = ?', [$destId])['name'] ?? '');
    $sent = 0;
    foreach (rmt_trip_match_user_ids($actorId, $destId, $from, $to) as $uid) {
        if ($uid < 1 || $uid === $actorId) continue;
        $seen = q_one('SELECT 1 x FROM notifications WHERE user_id=? AND type=? AND actor_id=? AND target_id=?',
                      [$uid, RMT_MATCH_NOTIFY_TYPE, $actorId, $goingId]);
        if ($seen) continue;
        /* And not a second time for the same pair and the same city while the first one is still
           unread. Somebody posting three trips to Bangkok in one evening is one piece of news to
           the people already going there, not three taps on the shoulder. A row they have read
           does not suppress: next month, on another trip, it is news again. */
        $pending = q_one("SELECT 1 x FROM notifications n
                            JOIN trips t ON t.id = n.target_id
                           WHERE n.user_id = ? AND n.type = ? AND n.actor_id = ?
                             AND n.target_type = 'going' AND t.destination_id = ? AND n.read_at IS NULL",
                         [$uid, RMT_MATCH_NOTIFY_TYPE, $actorId, $destId]);
        if ($pending) continue;
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
              [$uid, RMT_MATCH_NOTIFY_TYPE, $actorId, 'going', $goingId, $now]);
        /* The in app notification stays canonical; this is the courtesy that makes it reachable.
           Somebody who joined, posted dates and left has no reason to come back on their own, and
           this is the one event that is genuinely worth a return: the product just did the thing
           they joined for.
           What it says: the city, and that dates overlap. Not who, not which dates, not anything
           they would have had to open the site to see anyway. The helper refuses unverified
           addresses, opt outs, and more than one email an hour or six a day per person, and the
           duplicate guards above mean one new trip cannot tap the same shoulder twice. */
        if (function_exists('rmt_notify_email_direct')) {
            $city = $destName !== '' ? $destName : 'a city you are going to';
            rmt_notify_email_direct(
                $uid,
                'Somebody overlaps your dates in ' . $city,
                'A traveler posted dates in ' . $city . ' that overlap yours.',
                '/matches',
                "your dates overlap somebody else's"
            );
        }
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
           FROM trips o JOIN users u ON u.id = o.user_id
          WHERE o.destination_id = ? AND o.user_id <> ?
            AND u.status = 'active' AND o.status = 'published'
            AND o.date_from IS NOT NULL AND o.date_to IS NOT NULL
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

/* ------------------------------------------------------------------ who to follow */

/**
 * People worth following, with the reason attached.
 *
 * A flat directory of every member sorted by review count is a phone book: it answers "who is
 * here" and never "who is here for me". Three signals, strongest first, and each carries the
 * sentence that explains it, because "suggested for you" with no reason is the thing people learn
 * to ignore.
 *
 *   in a community with you   you already share a room
 *   followed by people you follow   the oldest working signal on any social graph
 *   wants to go where you want   shared saved destinations
 *
 * @return list<array<string,mixed>> user rows plus `reason`
 */
/** The editorial role name, or a value no account has when the constant is not loaded. */
function rmt_editorial_role_name(): string {
    return defined('RMT_EDITORIAL_ROLE') ? (string) RMT_EDITORIAL_ROLE : '__no_editorial_role__';
}

function rmt_follow_suggestions(int $userId, int $limit = 8): array {
    if ($userId < 1) return [];
    [$blockSql] = rmt_match_block_sql('u.id');
    $exclude = "u.id <> ? AND u.status='active'
                AND NOT EXISTS (SELECT 1 FROM follows f WHERE f.follower_id = ? AND f.followee_id = u.id)
                AND $blockSql";

    $rows = [];
    $add = static function (array $found, string $reason) use (&$rows): void {
        foreach ($found as $r) {
            $id = (int) $r['id'];
            if (isset($rows[$id])) continue;          // the first reason found is the strongest one
            $rows[$id] = $r + ['reason' => $reason];
        }
    };

    // Four placeholders in $exclude: the self check, the follow check, and two for the block pair.
    $exclArgs = [$userId, $userId, $userId, $userId];

    /* Travel first, because that is what this site is for. Both trips upcoming and public on their
       side; the reason names the city, never the dates or anything more precise. */
    $today = date('Y-m-d');
    $going = q_all("SELECT u.id, u.username, p.display_name, p.avatar_url, p.home_city, d.name dest_name,
                           CASE WHEN theirs.date_from <= mine.date_to AND theirs.date_to >= mine.date_from
                                THEN 1 ELSE 0 END same_days
                      FROM trips mine
                      JOIN trips theirs ON theirs.destination_id = mine.destination_id
                                       AND theirs.user_id <> mine.user_id AND theirs.status = 'published'
                                       AND COALESCE(theirs.visibility, 'public') = 'public'
                                       AND theirs.date_to IS NOT NULL AND theirs.date_to >= ?
                      JOIN users u ON u.id = theirs.user_id
                      JOIN destinations d ON d.id = mine.destination_id
                 LEFT JOIN profiles p ON p.user_id = u.id
                     WHERE mine.user_id = ? AND mine.status = 'published' AND mine.destination_id IS NOT NULL
                       AND mine.date_to IS NOT NULL AND mine.date_to >= ?
                       AND COALESCE(u.role, '') <> ? AND $exclude
                  ORDER BY same_days DESC, theirs.date_from
                     LIMIT 40", array_merge([$today, $userId, $today, rmt_editorial_role_name()], $exclArgs));
    $add(array_filter($going, static fn(array $r) => (int) $r['same_days'] === 1), '');
    foreach ($rows as $id => $r) {
        if ($r['reason'] === '') $rows[$id]['reason'] = 'Same dates in ' . $r['dest_name'];
    }
    $add($going, '');
    foreach ($rows as $id => $r) {
        if ($r['reason'] === '') $rows[$id]['reason'] = 'Also going to ' . $r['dest_name'];
    }
    $add(q_all("SELECT DISTINCT u.id, u.username, p.display_name, p.avatar_url, p.home_city
                  FROM collection_members mine
                  JOIN collection_members theirs ON theirs.collection_id = mine.collection_id
                                                AND theirs.status='active' AND theirs.user_id <> mine.user_id
                  JOIN users u ON u.id = theirs.user_id
             LEFT JOIN profiles p ON p.user_id = u.id
                 WHERE mine.user_id = ? AND mine.status='active' AND $exclude
                 LIMIT 20", array_merge([$userId], $exclArgs)), 'in a community with you');

    $add(q_all("SELECT DISTINCT u.id, u.username, p.display_name, p.avatar_url, p.home_city
                  FROM follows mine
                  JOIN follows theirs ON theirs.follower_id = mine.followee_id
                  JOIN users u ON u.id = theirs.followee_id
             LEFT JOIN profiles p ON p.user_id = u.id
                 WHERE mine.follower_id = ? AND $exclude
                 LIMIT 20", array_merge([$userId], $exclArgs)), 'followed by people you follow');

    /* Shared interests, only when there are at least two: one shared box ticked is everybody. */
    try {
        $shared = q_all("SELECT u.id, u.username, p.display_name, p.avatar_url, p.home_city, COUNT(*) n
                           FROM profile_interests mine
                           JOIN profile_interests theirs ON theirs.interest = mine.interest AND theirs.user_id <> mine.user_id
                           JOIN users u ON u.id = theirs.user_id
                      LEFT JOIN profiles p ON p.user_id = u.id
                          WHERE mine.user_id = ? AND COALESCE(u.role, '') <> ? AND $exclude
                       GROUP BY u.id, u.username, p.display_name, p.avatar_url, p.home_city
                         HAVING COUNT(*) >= 2
                       ORDER BY n DESC LIMIT 20", array_merge([$userId, rmt_editorial_role_name()], $exclArgs));
    } catch (Throwable) {
        $shared = [];
    }
    foreach ($shared as $r) {
        $id = (int) $r['id'];
        if (!isset($rows[$id])) $rows[$id] = $r + ['reason' => (int) $r['n'] . ' shared travel interests'];
    }

    $followed = [];
    foreach (q_all('SELECT followee_id FROM follows WHERE follower_id = ?', [$userId]) as $f) {
        $followed[(int) $f['followee_id']] = true;
    }
    foreach (rmt_wishlist_matches($userId, 20) as $w) {
        $id = (int) $w['user_id'];
        if (isset($rows[$id])) continue;
        // rmt_wishlist_matches already excludes blocks; the follow check is the one thing it does
        // not do, because there it is a list of travelers rather than a list of suggestions.
        if (isset($followed[$id])) continue;
        $rows[$id] = ['id' => $id, 'username' => $w['username'], 'display_name' => $w['display_name'] ?? null,
                      'avatar_url' => $w['avatar_url'] ?? null, 'home_city' => $w['home_city'] ?? null,
                      'reason' => 'wants to go where you want'];
    }

    return array_slice(array_values($rows), 0, $limit);
}
