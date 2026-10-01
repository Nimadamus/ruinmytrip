<?php
declare(strict_types=1);

/**
 * First in a city (2026-10-01, migration 105). Two recognitions, on purpose, and nothing that
 * counts logins or clicks. Founding Traveler already exists (app/profiles.php, /founding: the
 * first 100 accounts to publish a review) and is a published promise, so it is left exactly as it
 * is rather than given a second, different rule here.
 *
 *   First traveler to X    the first real member to post public dates for that city.
 *   First review of X      the first real member's published review in that city.
 *
 * Anti abuse, in one place (rmt_recognitions_sweep):
 *   - Real members only: active, not a house or editorial account, email confirmed.
 *   - The thing has to have stood for 48 hours and still be live when the sweep runs, so posting
 *     and deleting a trip to grab a city does nothing.
 *   - One First per city per kind, ever (a unique index).
 *   - Permanent once earned: deleting a later trip does not take it away. Moderation does: a
 *     recognition whose qualifying thing was removed by moderation, or whose member is suspended,
 *     is revoked (revoked_at), never silently reassigned.
 *
 * Shown as one quiet line under the name on a profile, at most three, beside the existing badges.
 */

const RMT_RECOG_HOLD_HOURS = 48;

function rmt_recog_real_sql(string $u = 'u'): string {
    $base = function_exists('rmt_sc_real_user_sql') ? rmt_sc_real_user_sql($u) : "$u.status = 'active' AND SUBSTR($u.username, 1, 5) <> 'team_'";
    return "$base AND $u.email_verified_at IS NOT NULL";
}

/**
 * Award whatever has become due. Idempotent: run it as often as you like.
 * Returns how many recognitions were added.
 */
function rmt_recognitions_sweep(?string $now = null): int {
    $now = $now ?? date('Y-m-d H:i:s');
    $cut = date('Y-m-d H:i:s', (int) strtotime($now) - RMT_RECOG_HOLD_HOURS * 3600);
    $real = rmt_recog_real_sql('u');
    $added = 0;

    // First traveler to a city: the earliest public trip with dates that has stood for 48 hours.
    try {
        $rows = q_all("SELECT t.destination_id, t.user_id, t.id, t.created_at FROM trips t JOIN users u ON u.id = t.user_id
                        WHERE t.status = 'published' AND t.visibility = 'public' AND t.date_from IS NOT NULL
                          AND t.destination_id IS NOT NULL AND t.created_at <= ? AND $real
                          AND NOT EXISTS (SELECT 1 FROM recognitions r WHERE r.kind = 'first_traveler' AND r.destination_id = t.destination_id)
                        ORDER BY t.created_at, t.id", [$cut]);
        $seen = [];
        foreach ($rows as $r) {
            if (isset($seen[$r['destination_id']])) continue;
            $seen[$r['destination_id']] = 1;
            $added += rmt_recog_add((int) $r['user_id'], 'first_traveler', (int) $r['destination_id'], null, 'trip', (int) $r['id'], $now);
        }
    } catch (Throwable $e) { /* no trips table */ }

    // First review of a city.
    try {
        $rows = q_all("SELECT rv.destination_id, rv.user_id, rv.id, rv.created_at FROM reviews rv JOIN users u ON u.id = rv.user_id
                        WHERE rv.status = 'published' AND rv.destination_id IS NOT NULL AND rv.created_at <= ? AND $real
                          AND NOT EXISTS (SELECT 1 FROM recognitions r WHERE r.kind = 'first_review' AND r.destination_id = rv.destination_id)
                        ORDER BY rv.created_at, rv.id", [$cut]);
        $seen = [];
        foreach ($rows as $r) {
            if (isset($seen[$r['destination_id']])) continue;
            $seen[$r['destination_id']] = 1;
            $added += rmt_recog_add((int) $r['user_id'], 'first_review', (int) $r['destination_id'], null, 'review', (int) $r['id'], $now);
        }
    } catch (Throwable $e) { /* no reviews table */ }

    rmt_recognitions_revoke($now);
    return $added;
}

function rmt_recog_add(int $uid, string $kind, ?int $destId, ?int $ordinal, ?string $refType, ?int $refId, string $now): int {
    try {
        q_run('INSERT INTO recognitions (user_id, kind, destination_id, ordinal, ref_type, ref_id, earned_at) VALUES (?,?,?,?,?,?,?)',
              [$uid, $kind, $destId, $ordinal, $refType, $refId, $now]);
    } catch (Throwable $e) { return 0; }   // the unique index said somebody else got there first
    if (function_exists('rmt_track')) rmt_track('recognition_earned', ['destination_id' => $destId]);
    return 1;
}

/** Moderation takes a First back: the trip or review it rested on was removed, not merely deleted by its author later. */
function rmt_recognitions_revoke(string $now): void {
    try {
        q_run("UPDATE recognitions SET revoked_at = ? WHERE revoked_at IS NULL AND ref_type = 'review'
                 AND ref_id IN (SELECT id FROM reviews WHERE status = 'removed')", [$now]);
        q_run("UPDATE recognitions SET revoked_at = ? WHERE revoked_at IS NULL AND ref_type = 'trip'
                 AND ref_id IN (SELECT id FROM trips WHERE status = 'removed')", [$now]);
    } catch (Throwable $e) { /* status values differ in a narrow test */ }
}

/** The run at most every half hour, from the page that shows the result. */
function rmt_recognitions_maybe_sweep(): void {
    $flag = sys_get_temp_dir() . '/rmt_recog_sweep';
    if (is_file($flag) && time() - (int) filemtime($flag) < 1800) return;
    @touch($flag);
    try { rmt_recognitions_sweep(); } catch (Throwable $e) { /* never break a profile */ }
}

/**
 * A member's recognitions as short labels, travelers before reviews, at most $max, live ones only.
 *
 * @return list<string>
 */
function rmt_recognitions_for(int $uid, int $max = 3): array {
    try {
        $rows = q_all("SELECT r.kind, r.ordinal, d.name FROM recognitions r LEFT JOIN destinations d ON d.id = r.destination_id
                        WHERE r.user_id = ? AND r.revoked_at IS NULL
                        ORDER BY CASE r.kind WHEN 'first_traveler' THEN 1 ELSE 2 END, r.earned_at", [$uid]);
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        $out[] = match ((string) $r['kind']) {
            'first_traveler' => 'First traveler to ' . $r['name'],
            'first_review'   => 'First review of ' . $r['name'],
            default          => '',
        };
    }
    $out = array_values(array_filter($out));
    if (count($out) > $max) $out = array_merge(array_slice($out, 0, $max), ['and ' . (count($out) - $max) . ' more']);
    return $out;
}
