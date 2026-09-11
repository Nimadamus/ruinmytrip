<?php
declare(strict_types=1);

/**
 * A trip, as a thing with dates.
 *
 * The site used to hold two objects that both meant "I am going to this city". `going` was a date
 * range with a visibility setting and no page; `trips` was a story written afterwards. A member had
 * to learn both, neither knew about the other, and the object the product is actually built around
 * -- an upcoming trip other travelers can find you on -- was neither of them.
 *
 * This file is the one API for the dated half: who is going where and when. It reads and writes
 * `trips`. The `going` table still exists, deliberately untouched, because deleting rows is the one
 * migration that cannot be undone; nothing here reads it.
 *
 * Visibility is unchanged from what `going` promised, and so is the location model: a destination
 * and a date range, never anything finer.
 */

/** What a plan's visibility may be. Same three values `going` used. */
const RMT_PLAN_VISIBILITIES = ['public', 'followers', 'private'];

/** A trip is a plan when it has both dates. A story with no dates is still a trip, just not a plan. */
const RMT_PLAN_SQL = "t.date_from IS NOT NULL AND t.date_to IS NOT NULL";

/**
 * Where a trip sits in time. Derived, never stored: a stored status is wrong every midnight.
 *
 * @return 'upcoming'|'current'|'past'|'undated'
 */
function rmt_trip_phase(array $t, ?string $today = null): string {
    $from = trim((string) ($t['date_from'] ?? ''));
    $to   = trim((string) ($t['date_to'] ?? ''));
    if ($from === '' || $to === '') return 'undated';
    $today ??= date('Y-m-d');
    if ($to < $today)   return 'past';
    if ($from > $today) return 'upcoming';
    return 'current';
}

/**
 * The visibility clause for somebody looking at other people's plans.
 *
 * Owners see their own whatever it says. A follower sees 'followers'. Everybody else sees 'public'
 * and nothing else. Private is exactly that: it exists so a member can keep a trip for themselves
 * and still use the site to plan it.
 *
 * @return array{0:string,1:array<int,mixed>} SQL fragment and its bind values
 */
function rmt_plan_visibility_sql(string $alias, ?array $viewer): array {
    if (!$viewer) return ["$alias.visibility = 'public'", []];
    $uid = (int) $viewer['id'];
    /* The member clause is here rather than in each caller on purpose. A trip can now be planned
       by more than one person, and somebody invited onto a private trip has to be able to see it,
       or the invitation means nothing. Putting it in the one clause every read already uses means
       the trip page, the feed, the city page, search, the sitemap and the photo walls all learned
       it at once, and none of them can forget it separately. */
    return [
        "($alias.visibility = 'public'
          OR $alias.user_id = ?
          OR EXISTS (SELECT 1 FROM trip_members tm
                      WHERE tm.trip_id = $alias.id AND tm.user_id = ? AND tm.state = 'active')
          OR ($alias.visibility = 'followers'
              AND EXISTS (SELECT 1 FROM follows f WHERE f.followee_id = $alias.user_id AND f.follower_id = ?)))",
        [$uid, $uid, $uid],
    ];
}

/**
 * Validate submitted plan dates.
 *
 * The rules are the ones `going` had, kept deliberately: a real destination, a real range, the end
 * not before the start, and a length a trip can actually be. A year-long range is not a trip, it is
 * a life change, and it would sit in every city's "who is going" list for a year.
 *
 * @return array{ok:bool, errors:string[], data:array}
 */
function rmt_plan_validate(array $in): array {
    $errors = [];
    $dest = (int) ($in['destination_id'] ?? 0);
    $from = trim((string) ($in['date_from'] ?? ''));
    $to   = trim((string) ($in['date_to'] ?? ''));
    $vis  = (string) ($in['visibility'] ?? 'public');

    if ($dest <= 0 || !dest_by_id($dest)) $errors[] = 'Pick the city you are going to.';

    $fromTs = $from !== '' ? strtotime($from) : false;
    $toTs   = $to !== ''   ? strtotime($to)   : false;
    if (!$fromTs) $errors[] = 'Pick the day you arrive.';
    if (!$toTs)   $errors[] = 'Pick the day you leave.';
    if ($fromTs && $toTs) {
        if ($toTs < $fromTs) $errors[] = 'The day you leave cannot be before the day you arrive.';
        elseif (($toTs - $fromTs) > 400 * 86400) $errors[] = 'That range is longer than a year.';
    }
    if (!in_array($vis, RMT_PLAN_VISIBILITIES, true)) $vis = 'public';

    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'destination_id' => $dest,
        'date_from' => $fromTs ? date('Y-m-d', $fromTs) : '',
        'date_to'   => $toTs ? date('Y-m-d', $toTs) : '',
        'visibility' => $vis,
    ]];
}

/** One member's plan for one city, if they have one. The most recent, when there are several. */
function rmt_plan_for_user_dest(int $userId, int $destId): ?array {
    return q_one("SELECT t.* FROM trips t
                   WHERE t.user_id = ? AND t.destination_id = ? AND t.status = 'published'
                     AND " . RMT_PLAN_SQL . "
                ORDER BY t.date_from DESC LIMIT 1", [$userId, $destId]);
}

/**
 * Create or update a member's dates for a city.
 *
 * An existing plan for the same city is moved rather than duplicated, which is what somebody means
 * when they change their dates. A trip that has been written into -- a title of their own, a body,
 * photos -- is never retitled by this, because it is their story now and not a placeholder.
 *
 * @return int the trip id
 */
function rmt_plan_upsert(int $userId, array $data): int {
    $destId = (int) $data['destination_id'];
    $from   = (string) $data['date_from'];
    $to     = (string) $data['date_to'];
    $vis    = (string) $data['visibility'];
    $now    = date('Y-m-d H:i:s');
    $existing = rmt_plan_for_user_dest($userId, $destId);

    if ($existing) {
        $isPlaceholder = trim((string) ($existing['body'] ?? '')) === '';
        $title = $isPlaceholder ? rmt_plan_title($destId, $from, $to) : (string) $existing['title'];
        $slug  = $isPlaceholder ? rmt_plan_slug($destId, $from, (int) $existing['id']) : (string) $existing['slug'];
        db()->prepare('UPDATE trips SET date_from=?, date_to=?, visibility=?, title=?, slug=?, updated_at=? WHERE id=?')
            ->execute([$from, $to, $vis, $title, $slug, $now, (int) $existing['id']]);
        return (int) $existing['id'];
    }

    q_run('INSERT INTO trips (user_id, destination_id, title, slug, body, status, visibility,
                              date_from, date_to, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?)',
          [$userId, $destId, rmt_plan_title($destId, $from, $to), 'plan', '', 'published', $vis, $from, $to, $now]);
    $id = (int) (q_one('SELECT MAX(id) m FROM trips WHERE user_id = ?', [$userId])['m'] ?? 0);
    // The slug carries the id so it is unique without a lookup, and readable in the URL.
    db()->prepare('UPDATE trips SET slug=? WHERE id=?')->execute([rmt_plan_slug($destId, $from, $id), $id]);
    return $id;
}

/** "Lisbon, 2 to 9 April 2027", the way somebody would say it. */
function rmt_plan_title(int $destId, string $from, string $to): string {
    $d = dest_by_id($destId);
    $name = $d['name'] ?? 'Somewhere';
    $f = strtotime($from); $t = strtotime($to);
    if (!$f || !$t) return (string) $name;
    if (date('Y-m', $f) === date('Y-m', $t)) {
        return $name . ', ' . date('j', $f) . ' to ' . date('j F Y', $t);
    }
    return $name . ', ' . date('j M', $f) . ' to ' . date('j M Y', $t);
}

/** A readable, unique slug: city, month, id. */
function rmt_plan_slug(int $destId, string $from, int $tripId): string {
    $d = dest_by_id($destId);
    $base = slugify((string) ($d['slug'] ?? $d['name'] ?? 'trip'));
    $when = strtotime($from) ? date('M-Y', strtotime($from)) : '';
    return strtolower(trim($base . '-' . $when, '-')) . '-' . $tripId;
}

/** Remove a member's dates for a city. A trip they have written into is kept; only its dates go. */
function rmt_plan_clear(int $userId, int $destId): void {
    $t = rmt_plan_for_user_dest($userId, $destId);
    if (!$t) return;
    if (trim((string) ($t['body'] ?? '')) === '') {
        db()->prepare('DELETE FROM trips WHERE id = ? AND user_id = ?')->execute([(int) $t['id'], $userId]);
        return;
    }
    db()->prepare('UPDATE trips SET date_from = NULL, date_to = NULL WHERE id = ? AND user_id = ?')
        ->execute([(int) $t['id'], $userId]);
}

/**
 * Who is going to this city and has not left yet, soonest first.
 *
 * @return list<array<string,mixed>>
 */
function rmt_plans_for_destination(int $destId, ?array $viewer, int $limit = 50): array {
    [$vis, $args] = rmt_plan_visibility_sql('t', $viewer);
    return q_all(
        // travel_style comes along because the city page counts how many of the people going said
        // they travel alone, and a second query per traveler to ask that would be absurd.
        "SELECT t.*, u.username, p.avatar_url, p.display_name, p.travel_style
           FROM trips t JOIN users u ON u.id = t.user_id
      LEFT JOIN profiles p ON p.user_id = u.id
          WHERE t.destination_id = ? AND t.status = 'published' AND u.status = 'active'
            AND " . RMT_PLAN_SQL . " AND t.date_to >= ? AND $vis
       ORDER BY t.date_from LIMIT " . max(1, $limit),
        array_merge([$destId, date('Y-m-d')], $args)
    );
}

/**
 * A member's plans, for their profile.
 *
 * $upcomingOnly is what a stranger sees on somebody's profile; the owner's own page passes false to
 * see their history as well.
 */
function rmt_plans_for_user(int $userId, ?array $viewer, bool $upcomingOnly = true, int $limit = 50): array {
    [$vis, $args] = rmt_plan_visibility_sql('t', $viewer);
    $when = $upcomingOnly ? ' AND t.date_to >= ?' : '';
    $bind = array_merge([$userId], $upcomingOnly ? [date('Y-m-d')] : [], $args);
    return q_all(
        "SELECT t.*, d.name dest_name, d.slug dest_slug
           FROM trips t LEFT JOIN destinations d ON d.id = t.destination_id
          WHERE t.user_id = ? AND t.status = 'published' AND " . RMT_PLAN_SQL . "$when AND $vis
       ORDER BY t.date_from DESC LIMIT " . max(1, $limit),
        $bind
    );
}

/** How many travelers have upcoming public plans for a city. Used on browse pages. */
function rmt_plan_count_for_destination(int $destId): int {
    return (int) (q_one("SELECT COUNT(*) c FROM trips t JOIN users u ON u.id = t.user_id
                          WHERE t.destination_id = ? AND t.status = 'published' AND u.status = 'active'
                            AND t.visibility = 'public' AND " . RMT_PLAN_SQL . " AND t.date_to >= ?",
                        [$destId, date('Y-m-d')])['c'] ?? 0);
}

/**
 * The cities a member has actually been to, for the travel history on their profile.
 *
 * A city counts when they have a past trip, a published review about it, or marked it as visited.
 * Three sources because the site has always let people record a visit three ways and a history that
 * ignored two of them would look wrong to the person who wrote them.
 */
function rmt_traveler_history(int $userId): array {
    $row = q_one(
        "SELECT COUNT(DISTINCT x.destination_id) cities, COUNT(DISTINCT d.country) countries
           FROM (
                SELECT t.destination_id FROM trips t
                 WHERE t.user_id = ? AND t.status = 'published' AND t.destination_id IS NOT NULL
                   AND (t.date_to < ? OR t.visited_on IS NOT NULL)
                UNION
                SELECT r.destination_id FROM reviews r
                 WHERE r.user_id = ? AND r.status = 'published' AND r.destination_id IS NOT NULL
                UNION
                SELECT v.destination_id FROM visits v WHERE v.user_id = ?
           ) x JOIN destinations d ON d.id = x.destination_id",
        [$userId, date('Y-m-d'), $userId, $userId]
    );
    return ['cities' => (int) ($row['cities'] ?? 0), 'countries' => (int) ($row['countries'] ?? 0)];
}

/**
 * May this person read this trip?
 *
 * The owner always can. Everybody else sees public trips, and followers see the ones marked for
 * followers. Kept here rather than in the controller because the sitemap has to ask the same
 * question, and two places deciding who may read something is how one of them ends up wrong.
 */
function rmt_trip_visible_to(array $t, ?array $viewer): bool {
    $vis = (string) ($t['visibility'] ?? 'public');
    if ($vis === 'public') return true;
    if (!$viewer) return false;
    $uid = (int) $viewer['id'];
    if ((int) $t['user_id'] === $uid) return true;
    /* Somebody planning the trip with you sees it, whatever it is set to. The SQL twin of this is
       in rmt_plan_visibility_sql(); the two have to agree, and the tests check that they do. */
    if (!empty($t['id']) && q_one("SELECT 1 FROM trip_members
                                    WHERE trip_id = ? AND user_id = ? AND state = 'active'",
                                  [(int) $t['id'], $uid])) return true;
    if ($vis === 'followers') {
        return (bool) q_one('SELECT 1 FROM follows WHERE followee_id = ? AND follower_id = ?',
                            [(int) $t['user_id'], $uid]);
    }
    return false;
}

/**
 * Is there anything on this trip yet?
 *
 * A trip with dates and nothing else is a real page and stays reachable, but it is not something to
 * put in front of a search engine: the site would be submitting one near-empty page per plan. It
 * earns a place in the sitemap when somebody has written on it, added a photo, or posted an update.
 * No noindex is involved; this only decides what we ASK to have crawled.
 */
function rmt_trip_has_substance(array $t): bool {
    if (trim((string) ($t['body'] ?? '')) !== '') return true;
    $id = (int) $t['id'];
    if ((int) (q_one('SELECT COUNT(*) c FROM trip_photos WHERE trip_id = ?', [$id])['c'] ?? 0) > 0) return true;
    return (int) (q_one("SELECT COUNT(*) c FROM posts WHERE trip_id = ? AND status = 'published'",
                        [$id])['c'] ?? 0) > 0;
}

/**
 * "I am going too."
 *
 * The site could tell you that somebody's trip overlaps yours, and the only thing you could do
 * about it was write your own dates somewhere else and hope they matched. This copies the dates you
 * are looking at into a plan of your own, which is what the button means, and tells the person
 * whose trip it was.
 *
 * It is a plan, not a shared trip: their page stays theirs, and yours is yours. Trips are the only
 * thing here that lead to two strangers in the same place, so nothing about this puts anybody on
 * somebody else's page without their say.
 *
 * @return array{ok:bool, trip_id:int, error:string}
 */
function rmt_plan_join(array $theirTrip, array $me): array {
    $destId = (int) ($theirTrip['destination_id'] ?? 0);
    if ($destId < 1 || empty($theirTrip['date_from']) || empty($theirTrip['date_to'])) {
        return ['ok' => false, 'trip_id' => 0, 'error' => 'That trip has no city and dates to copy.'];
    }
    if ((int) $theirTrip['user_id'] === (int) $me['id']) {
        return ['ok' => false, 'trip_id' => 0, 'error' => 'That is your own trip.'];
    }
    $v = rmt_plan_validate([
        'destination_id' => $destId,
        'date_from' => (string) $theirTrip['date_from'],
        'date_to'   => (string) $theirTrip['date_to'],
        // Public by default, because the point of pressing it is to be findable by the other
        // people going. They can narrow it afterwards like any other plan.
        'visibility' => 'public',
    ]);
    if (!$v['ok']) return ['ok' => false, 'trip_id' => 0, 'error' => $v['errors'][0]];

    $id = rmt_plan_upsert((int) $me['id'], $v['data']);
    // The person whose trip it was hears about it once, on the trip they posted.
    $seen = q_one('SELECT 1 x FROM notifications WHERE user_id=? AND type=? AND target_type=? AND target_id=?',
                  [(int) $theirTrip['user_id'], 'going_too', 'trip', (int) $theirTrip['id']]);
    if (!$seen && (int) $theirTrip['user_id'] !== (int) $me['id']) {
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at)
               VALUES (?,?,?,?,?,?)',
              [(int) $theirTrip['user_id'], 'going_too', (int) $me['id'], 'trip',
               (int) $theirTrip['id'], date('Y-m-d H:i:s')]);
    }
    return ['ok' => true, 'trip_id' => $id, 'error' => ''];
}

/**
 * Tell the other travelers on the same days that this trip has an update.
 *
 * Who hears: anybody whose own plan is in the same city and overlaps these dates. That is exactly
 * the set of people the matching page would have shown each other anyway, so it is news they asked
 * for by posting their own dates rather than a broadcast.
 *
 * Deduped per update, capped, and never sent to the author. Blocks are honoured through the same
 * clause the matching page uses, because this is the feature that ends with two people meeting.
 *
 * @return int how many were told
 */
function rmt_trip_update_notify(array $trip, int $postId, int $actorId, int $limit = 20): int {
    $destId = (int) ($trip['destination_id'] ?? 0);
    $from = (string) ($trip['date_from'] ?? '');
    $to   = (string) ($trip['date_to'] ?? '');
    if ($destId < 1 || $from === '' || $to === '' || $postId < 1) return 0;
    if (!function_exists('rmt_trip_match_user_ids')) return 0;

    $now = date('Y-m-d H:i:s');
    $sent = 0;
    foreach (rmt_trip_match_user_ids($actorId, $destId, $from, $to, $limit) as $uid) {
        if ($uid === $actorId) continue;
        $seen = q_one('SELECT 1 x FROM notifications WHERE user_id=? AND type=? AND target_type=? AND target_id=?',
                      [$uid, 'trip_update', 'post', $postId]);
        if ($seen) continue;
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at)
               VALUES (?,?,?,?,?,?)', [$uid, 'trip_update', $actorId, 'post', $postId, $now]);
        $sent++;
    }
    return $sent;
}

/**
 * The other travelers whose public trip lands on top of this one, same city, overlapping days.
 *
 * The trip page could already tell you when somebody was going and offer to copy their dates, and
 * it could not tell you who else would be there. That is the fact the page exists to carry: a trip
 * with four other people on it is a reason to go, and it is the difference between a travel diary
 * and a network.
 *
 * Visibility is the same clause the rest of the site uses, so a followers only trip appears only to
 * a follower and a private one to nobody. Blocks are applied where the viewer has any.
 *
 * @return list<array<string,mixed>>
 */
function rmt_trip_overlappers(array $trip, ?array $viewer, int $limit = 8): array {
    $destId = (int) ($trip['destination_id'] ?? 0);
    $from   = trim((string) ($trip['date_from'] ?? ''));
    $to     = trim((string) ($trip['date_to'] ?? ''));
    if ($destId < 1 || $from === '' || $to === '') return [];

    [$visSql, $visArgs] = rmt_plan_visibility_sql('t', $viewer);
    $blockSql = '1=1';
    $blockArgs = [];
    if ($viewer && function_exists('rmt_match_block_sql')) {
        [$blockSql] = rmt_match_block_sql('t.user_id');
        $blockArgs = [(int) $viewer['id'], (int) $viewer['id']];
    }

    return q_all(
        "SELECT t.id, t.user_id, t.date_from, t.date_to, t.visibility, t.slug,
                u.username, p.avatar_url
           FROM trips t
           JOIN users u ON u.id = t.user_id AND u.status = 'active'
      LEFT JOIN profiles p ON p.user_id = t.user_id
          WHERE t.destination_id = ? AND t.id <> ? AND t.user_id <> ?
            AND t.status = 'published'
            AND t.date_from IS NOT NULL AND t.date_to IS NOT NULL
            AND t.date_from <= ? AND t.date_to >= ?
            AND $visSql AND $blockSql
       ORDER BY t.date_from LIMIT " . (int) $limit,
        array_merge([$destId, (int) ($trip['id'] ?? 0), (int) ($trip['user_id'] ?? 0), $to, $from],
                    $visArgs, $blockArgs)
    );
}
