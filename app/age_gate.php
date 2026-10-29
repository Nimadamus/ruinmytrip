<?php
declare(strict_types=1);

/**
 * The 18+ step for travel buddies and meetups.
 *
 * Joining asks only for a "16 or older" tick (app/quick_join.php), so no member who joined since
 * 2026-10-01 has a birthdate, and can_host_meetups() is false for every one of them. Before this,
 * a new member who pressed "find a travel buddy" was sent back to /buddies with "Travel buddies is
 * 18+" and nothing they could do about it, and a buddy post ticked on the trip first form was
 * dropped without a word when the trip went live. Now the gate asks once for a date of birth,
 * stores it privately, and sends them back to what they were doing, with a held buddy post
 * published on the spot.
 *
 * A date of birth already on the account is never changed here, so somebody under 18 cannot
 * answer again with a different year.
 */

/** A signed in member whose age the site does not know yet. */
function rmt_age_unknown(?array $u): bool {
    return $u !== null && !empty($u['id']) && empty($u['birthdate']);
}

/**
 * Called where can_host_meetups() said no. A member with no birthdate is asked for it and comes
 * back to $return; a member known to be under 18 gets $deny as before.
 */
function rmt_adult_gate(?array $u, string $return, string $deny, string $denyTo): void {
    if (rmt_age_unknown($u)) {
        redirect('/account/age?return=' . rawurlencode(rmt_age_safe_return($return)));
    }
    flash($deny);
    redirect($denyTo);
}

/** Only a path on this site, never somewhere else. */
function rmt_age_safe_return(string $r): string {
    return (preg_match('#^/(?!/)[^\s\\\\]*$#', $r) === 1) ? $r : '/buddies';
}

/**
 * A buddy post that could not go up because the age was not known yet: kept against the account
 * (held_work) instead of being dropped, and published by age_submit().
 */
function rmt_age_hold_buddy(int $uid, array $buddy): void {
    if ($uid < 1 || !$buddy || !function_exists('rmt_pending_persist')) return;
    rmt_pending_persist($uid, ['buddy' => $buddy]);
}

/** Checks a typed date of birth. Returns [birthdate or null, error or null]. */
function rmt_age_validate(string $dob, ?string $today = null): array {
    $today = $today ?? date('Y-m-d');
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return [null, 'Enter your date of birth.'];
    }
    if ($dob > $today) return [null, 'That date is in the future.'];
    $age = age_from($dob);
    if ($age > 110) return [null, 'Check the year.'];
    if ($age < 16) return [null, 'RuinMyTrip is for travelers 16 and over.'];
    return [$dob, null];
}

/** GET /account/age */
function age_form(array $a): void {
    require_login();
    $me = current_user();
    $return = rmt_age_safe_return((string) input('return', '/buddies'));
    if (!rmt_age_unknown($me)) redirect($return);
    view('account_age', ['return' => $return, 'errors' => [], 'dob' => ''], [
        'title' => 'Confirm your age | RuinMyTrip',
        'robots' => 'noindex,follow',
    ]);
}

/** POST /account/age */
function age_submit(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $return = rmt_age_safe_return((string) input('return', '/buddies'));
    if (!rmt_age_unknown($me)) redirect($return);
    [$dob, $err] = rmt_age_validate(trim((string) input('dob')));
    if ($err !== null) {
        view('account_age', ['return' => $return, 'errors' => [$err], 'dob' => (string) input('dob')], [
            'title' => 'Confirm your age | RuinMyTrip',
            'robots' => 'noindex,follow',
        ]);
        return;
    }
    $uid = (int) $me['id'];
    q_run('UPDATE users SET birthdate = ? WHERE id = ? AND (birthdate IS NULL OR birthdate = \'\')', [$dob, $uid]);
    if (function_exists('rmt_track')) rmt_track('age_confirmed', ['adult' => age_from($dob) >= 18 ? 1 : 0]);
    $me = (array) q_one('SELECT * FROM users WHERE id = ?', [$uid]);
    if (!can_host_meetups($me)) {
        if (function_exists('rmt_pending_forget')) rmt_pending_forget($uid);
        flash('Thanks. Travel buddies and meetups are for travelers 18 and over. Reviews, questions, your map and your trips are all yours.');
        redirect('/feed');
    }
    // A buddy post held for this step goes up now.
    $held = function_exists('rmt_pending_load') ? rmt_pending_load($uid) : null;
    if (!empty($held['buddy']) && is_array($held['buddy'])) {
        rmt_pending_forget($uid);
        $v = rmt_buddy_validate($held['buddy']);
        if ($v['ok']) {
            $bid = rmt_buddy_insert($uid, $v['data']);
            if ($bid > 0) {
                rmt_buddy_notify_matches('buddy', $bid);
                if (function_exists('rmt_track')) rmt_track('buddy_post_created', ['source' => 'age_step', 'destination_id' => $v['data']['destination_id']]);
                flash('Thanks. Your travel buddy post is live.');
                redirect('/buddy/' . $bid);
            }
        }
    }
    flash('Thanks, you are all set.');
    redirect($return);
}
