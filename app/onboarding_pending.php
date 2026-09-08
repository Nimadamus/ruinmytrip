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
    if ($keep) $_SESSION[RMT_PENDING_KEY] = $keep;
}

/** Is anything waiting? Used to word the "check your email" page as a reason rather than a chore. */
function rmt_pending_has(): bool {
    return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION[RMT_PENDING_KEY]);
}

/**
 * Apply what was held, now that the address is confirmed, and clear the slot either way.
 *
 * Returns what actually happened so the caller can say so: ['going' => bool, 'hello' => bool].
 * Each half is independent -- a rejected post must not take the travel dates down with it.
 */
function rmt_pending_apply(array $user): array {
    $done = ['going' => false, 'hello' => false];
    if (session_status() !== PHP_SESSION_ACTIVE) return $done;
    $held = $_SESSION[RMT_PENDING_KEY] ?? null;
    unset($_SESSION[RMT_PENDING_KEY]);
    if (!is_array($held) || !$held) return $done;
    $uid = (int) $user['id'];

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
    if (!empty($held['hello'])) {
        $pv = rmt_post_validate(['body' => (string) ($held['hello']['body'] ?? '')], $user);
        if ($pv['ok']) {
            rmt_post_create($uid, $pv['data']);
            $done['hello'] = true;
        }
    }
    return $done;
}
