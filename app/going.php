<?php
declare(strict_types=1);

/**
 * Travel dates, as the rest of the app still calls them.
 *
 * The `going` table is retired. A member's dates are a trip now, because that is the object the
 * product is built around: an upcoming trip other travelers can find you on, that becomes the page
 * you post updates and photos to while you are there, and the story afterwards. See app/plans.php.
 *
 * These functions are kept because roughly twenty call sites use them and a rename would have been
 * twenty chances to get something wrong in the same commit as a data migration. They now read and
 * write trips, and return rows shaped the way their callers already expect, including a `going`-era
 * `id` field that is the trip id.
 */

/** One member's dates for one city, as a row. */
function rmt_going_for_user_dest(int $userId, int $destId): ?array {
    $t = rmt_plan_for_user_dest($userId, $destId);
    return $t ? rmt_going_row($t) : null;
}

/** A trip row in the shape the who-is-going views read. */
function rmt_going_row(array $t): array {
    $t['going_id'] = (int) $t['id'];
    $t['trip_id']  = (int) $t['id'];
    return $t;
}

/**
 * Who is going to this city. Visibility is decided in one place, app/plans.php, which is also
 * where the promise that this is destination and dates only is kept.
 */
function rmt_going_list_for_destination(int $destId, ?array $viewer): array {
    return array_map('rmt_going_row', rmt_plans_for_destination($destId, $viewer));
}

/** Plans visible on a profile. The owner sees their own history as well as what is coming. */
function rmt_going_list_for_profile(int $profileUid, ?array $viewer): array {
    $isOwner = $viewer && (int) $viewer['id'] === $profileUid;
    return array_map('rmt_going_row', rmt_plans_for_user($profileUid, $viewer, !$isOwner));
}

/** Kept for callers that build their own query around the visibility rule. */
function rmt_going_visibility_sql(string $alias, ?array $viewer): array {
    return rmt_plan_visibility_sql($alias, $viewer);
}

/** Validate submitted dates. */
function rmt_going_validate(array $in): array {
    return rmt_plan_validate($in);
}

/** Create or move a member's dates for a city. Returns the trip id. */
function rmt_going_upsert(int $userId, array $data): int {
    return rmt_plan_upsert($userId, $data);
}

/** Remove the dates. A trip that has been written into keeps its words and loses only its dates. */
function rmt_going_delete(int $userId, int $destId): void {
    rmt_plan_clear($userId, $destId);
}

/**
 * Tell people a public plan exists: the traveler's followers, and the people who saved that city.
 *
 * Both are deliberate acts by the person being notified. The notification target is the trip, so
 * it links to a page that will fill up with the trip itself rather than to a bare date range.
 */
function rmt_going_notify_followers(int $actorId, int $tripId, string $visibility): void {
    if ($visibility !== 'public' || $tripId < 1) return;
    $t = q_one('SELECT destination_id FROM trips WHERE id = ?', [$tripId]);
    $now = date('Y-m-d H:i:s');
    foreach (q_all('SELECT follower_id FROM follows WHERE followee_id = ?', [$actorId]) as $f) {
        $fid = (int) $f['follower_id'];
        if ($fid < 1 || $fid === $actorId) continue;
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
              [$fid, 'going', $actorId, 'trip', $tripId, $now]);
    }
    if ($t && (int) $t['destination_id'] > 0 && function_exists('rmt_city_notify')) {
        rmt_city_notify((int) $t['destination_id'], 'city_going', $actorId, 'trip', $tripId);
    }
}
