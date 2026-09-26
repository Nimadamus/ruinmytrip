<?php
declare(strict_types=1);

/**
 * What a brand new member did before their email came back.
 *
 * Publishing needs a confirmed address, which is right, and the welcome screen enforced it by
 * throwing the whole submission away: somebody who signed up thirty seconds ago, picked three
 * cities, typed their dates and wrote a first line was bounced to /verify-email with the dates
 * gone, the rooms unjoined and the sentence deleted. That is the single worst moment to lose
 * somebody's work, because it is the only moment where they have not yet decided the site is
 * worth the trouble.
 *
 * So the gated parts are kept in the session and applied the instant the address is confirmed.
 * Nothing here bypasses the gate: it is the same validation, run at the same time, with the write
 * deferred rather than the intent discarded.
 */

/** Session key. One slot: a second welcome submission replaces the first, which is what the member meant. */
const RMT_PENDING_KEY = 'pending_onboarding';

/**
 * Hold validated onboarding work until the email is confirmed.
 *
 * $data may carry 'going' (already through rmt_going_validate) and 'hello' (already through
 * rmt_post_validate). Anything else is ignored rather than trusted.
 */
function rmt_pending_stash(array $data): void {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $keep = [];
    if (!empty($data['going']) && is_array($data['going'])) $keep['going'] = $data['going'];
    if (!empty($data['hello']) && is_array($data['hello'])) $keep['hello'] = $data['hello'];
    /* A trip posted before the address came back. The same reasoning as the two above, and the
       one most likely to happen now that the welcome screen sends people straight at it: the form
       asks for a city, two dates and a paragraph, and throwing that away because an email is in
       flight loses the only thing the member has made. Photographs cannot ride along in a
       session and are not pretended to: the trip arrives without them and can be added to. */
    if (!empty($data['trip']) && is_array($data['trip'])) $keep['trip'] = $data['trip'];
    /* A travel buddy post, for the same reason: the landing pages send brand new members straight
       to that form, and it is the longest thing on the site anyone fills in before confirming. */
    if (!empty($data['buddy']) && is_array($data['buddy'])) $keep['buddy'] = $data['buddy'];
    if (!empty($data['post']) && is_array($data['post'])) $keep['post'] = $data['post'];
    // Interests picked on the trip first form (app/plan_first.php), for a profile that has none.
    if (!empty($data['interests']) && is_array($data['interests'])) $keep['interests'] = array_values(array_map('strval', $data['interests']));
    if (!$keep) return;
    $_SESSION[RMT_PENDING_KEY] = $keep;
    /* And against the account, because the confirmation link is usually opened from a mail app:
       a different browser, a different session, and the slot above is not there. */
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid > 0) rmt_pending_persist($uid, $keep);
}

/** Hold the same slot in the database for one member. A second one replaces the first. */
function rmt_pending_persist(int $uid, array $keep): void {
    if ($uid < 1 || !$keep) return;
    try {
        q_run('DELETE FROM held_work WHERE user_id = ?', [$uid]);
        q_run('INSERT INTO held_work (user_id, payload, created_at) VALUES (?,?,?)',
              [$uid, (string) json_encode($keep), date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        // The session copy still stands; losing the durable one costs a cross browser confirm only.
    }
}

/** What the database holds for a member, or null. */
function rmt_pending_load(int $uid): ?array {
    if ($uid < 1) return null;
    try {
        $row = q_one('SELECT payload FROM held_work WHERE user_id = ?', [$uid]);
    } catch (Throwable $e) {
        return null;
    }
    $held = $row ? json_decode((string) $row['payload'], true) : null;
    return is_array($held) && $held ? $held : null;
}

function rmt_pending_forget(int $uid): void {
    if ($uid < 1) return;
    try { q_run('DELETE FROM held_work WHERE user_id = ?', [$uid]); } catch (Throwable $e) { /* nothing held */ }
}

/** Is anything waiting? Used to word the "check your email" page as a reason rather than a chore. */
function rmt_pending_has(): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) return false;
    if (!empty($_SESSION[RMT_PENDING_KEY])) return true;
    return rmt_pending_load((int) ($_SESSION['uid'] ?? 0)) !== null;
}

/**
 * Apply what was held, now that the address is confirmed, and clear the slot either way.
 *
 * Returns what actually happened so the caller can say so: ['going' => bool, 'hello' => bool].
 * Each half is independent -- a rejected post must not take the travel dates down with it.
 */
function rmt_pending_apply(array $user): array {
    $done = ['going' => false, 'hello' => false, 'trip' => false, 'buddy' => false, 'post' => false];
    if (session_status() !== PHP_SESSION_ACTIVE) return $done;
    $uid = (int) ($user['id'] ?? 0);
    $held = $_SESSION[RMT_PENDING_KEY] ?? null;
    unset($_SESSION[RMT_PENDING_KEY]);
    // Confirmed from another browser: the session is empty, the account row is not.
    if ((!is_array($held) || !$held) && $uid > 0) $held = rmt_pending_load($uid);
    // Cleared before anything is written, so a reload of the confirm page cannot publish twice.
    rmt_pending_forget($uid);
    if (!is_array($held) || !$held) return $done;

    if (!empty($held['going'])) {
        // Re-validated rather than trusted: the session is the member's own, but the row it writes
        // is a public claim about where somebody will be, and it is cheap to check twice.
        $v = rmt_going_validate($held['going']);
        if ($v['ok']) {
            $gid = rmt_going_upsert($uid, $v['data']);
            rmt_going_notify_followers($uid, $gid, $v['data']['visibility']);
            $done['going'] = true;
        }
    }
    if (!empty($held['trip'])) {
        // Re-validated, not trusted, for the same reason the other two are.
        $tv = rmt_trip_validate($held['trip']);
        if ($tv['ok'] && function_exists('rmt_trip_create_row')) {
            $tid = rmt_trip_create_row($uid, $tv['data']);
            // The id and the slug travel back so the caller can land them ON the trip rather than
            // telling them it exists somewhere and leaving them to find it.
            if ($tid > 0) {
                $done['trip'] = true;
                $done['trip_id'] = $tid;
                $done['trip_slug'] = function_exists('slugify') ? slugify((string) $tv['data']['title']) : '';
            }
        }
    }
    if (!empty($held['interests']) && is_array($held['interests']) && function_exists('rmt_plan_first_interests')) {
        rmt_plan_first_interests($uid, $held['interests']);
    }
    if (!empty($held['buddy']) && function_exists('rmt_buddy_validate') && can_host_meetups($user)) {
        // Re-validated, not trusted, like the rest.
        $bv = rmt_buddy_validate($held['buddy']);
        if ($bv['ok']) {
            $bid = rmt_buddy_insert($uid, $bv['data']);
            if ($bid > 0) {
                rmt_buddy_notify_matches('buddy', $bid);
                if (function_exists('rmt_track')) rmt_track('buddy_post_created', ['destination_id' => $bv['data']['destination_id']]);
                $done['buddy'] = true;
                $done['buddy_id'] = $bid;
            }
        }
    }
    /* A question typed into a city page before there was an account. Same validator and writer
       as the composer, so it is exactly the post they would have made signed in. */
    if (!empty($held['post']) && function_exists('rmt_post_create')) {
        $pv = rmt_post_validate(['body' => (string) ($held['post']['body'] ?? ''),
                                 'destination_id' => (int) ($held['post']['destination_id'] ?? 0)], $user);
        if ($pv['ok']) {
            $pid = rmt_post_create($uid, $pv['data']);
            if ($pid > 0) {
                if (function_exists('rmt_track')) {
                    rmt_track('post_created', ['destination_id' => $pv['data']['destination_id'] ?? null]);
                    if (!empty($pv['data']['destination_id'])) {
                        rmt_track('question_posted', ['source' => 'destination', 'destination_id' => (int) $pv['data']['destination_id']]);
                    }
                }
                $done['post'] = true;
                $done['post_id'] = $pid;
            }
        }
    }
    if (!empty($held['hello'])) {
        $pv = rmt_post_validate(['body' => (string) ($held['hello']['body'] ?? '')], $user);
        if ($pv['ok']) {
            rmt_post_create($uid, $pv['data']);
            $done['hello'] = true;
        }
    }
    return $done;
}
