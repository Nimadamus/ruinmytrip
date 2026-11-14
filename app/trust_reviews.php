<?php
/**
 * Vouches and flags, the trust score they add up to, and the restricted state (migration 122).
 *
 * After two travelers have actually crossed paths here, each may leave the other one private
 * vouch or flag:
 *
 *   - a buddy trip both were on (the poster and everybody the poster accepted) that is over
 *   - two check-ins to the same city that overlapped for an hour or more, where both of them
 *     wrote in that city's room, once both check-ins have ended
 *
 * WHAT IS PRIVATE: who vouched, who flagged, and what they wrote. Only moderators read those.
 *
 * WHAT IS NOT, and must never become so (see app/trust.php): the result. A member can always see
 * their own vouch count, flag count, score, the arithmetic that produced the score, their state,
 * and the dated history of every change to it, on /trust. Nothing here hides a member, lowers them
 * in a list, or changes what they can do without telling them. The one consequence is the
 * RESTRICTED state: private messages and room posts are paused, a notice says so on every page,
 * they are notified and emailed, and moderators are alerted to look.
 *
 * The arithmetic, in one place (rmt_trust_recompute):
 *
 *     vouch_count = travelers who vouched for you      (a traveler counts once)
 *     flag_count  = travelers who flagged you          (a traveler counts once)
 *     trust_score = vouch_count - RMT_TRUST_FLAG_WEIGHT * flag_count
 *     restricted  = flag_count >= RMT_TRUST_RESTRICT_FLAGS and trust_score < 0
 *
 * Reviews from accounts that are no longer active, and reviews a moderator has discounted, are
 * not counted.
 */
declare(strict_types=1);

const RMT_TRUST_FLAG_WEIGHT = 2;
const RMT_TRUST_RESTRICT_FLAGS = 3;
/** How long after a trip or a check-in ends the two travelers can still review each other. */
const RMT_TRUST_REVIEW_DAYS = 30;
/** Two check-ins have to overlap this long before either traveler can review the other. */
const RMT_TRUST_MIN_OVERLAP = 3600;
const RMT_TRUST_NOTE_MAX = 1000;

function rmt_trust_now(?string $now = null): string { return $now ?? date('Y-m-d H:i:s'); }

/**
 * Is this member's messaging paused? Asked on every send, so it is one indexed read, kept for the
 * request. It is deliberately NOT folded into current_user(): a sign-in must never depend on
 * this feature's columns. Reads as "no" where the columns do not exist yet, so a page never fails on this check.
 */
function rmt_trust_restricted(int $userId, bool $fresh = false): bool {
    static $seen = [];
    if ($userId < 1) return false;
    if (!$fresh && isset($seen[$userId])) return $seen[$userId];
    try {
        $row = q_one('SELECT trust_state FROM profiles WHERE user_id = ?', [$userId]);
    } catch (\PDOException $e) {
        return $seen[$userId] = false;
    }
    return $seen[$userId] = ($row && (string) $row['trust_state'] === 'restricted');
}

/**
 * Everybody this member may review right now and has not yet, newest meeting first.
 *
 * @return list<array{subject_id:int, username:string, display_name:?string, avatar_url:?string,
 *                    context_type:string, context_key:string, label:string, ended_at:string}>
 */
function rmt_trust_reviewable(int $userId, ?string $now = null): array {
    $now = rmt_trust_now($now);
    if ($userId < 1) return [];
    $cut = date('Y-m-d H:i:s', strtotime($now) - RMT_TRUST_REVIEW_DAYS * 86400);
    $found = [];

    /* Buddy trips that are over. */
    $posts = q_all("SELECT b.id, b.title, b.user_id, b.completed_at FROM buddy_posts b
                     WHERE b.completed_at IS NOT NULL AND b.completed_at >= ? AND b.completed_at <= ?
                       AND b.status IN ('open','closed','completed')
                       AND (b.user_id = ? OR EXISTS (SELECT 1 FROM buddy_interest i
                                                      WHERE i.post_id = b.id AND i.user_id = ? AND i.state = 'accepted'))
                     ORDER BY b.completed_at DESC, b.id DESC", [$cut, $now, $userId, $userId]);
    foreach ($posts as $b) {
        $people = array_map('intval', array_column(
            q_all("SELECT user_id FROM buddy_interest WHERE post_id = ? AND state = 'accepted'", [(int) $b['id']]), 'user_id'));
        $people[] = (int) $b['user_id'];
        foreach (array_unique($people) as $pid) {
            if ($pid === $userId) continue;
            $found[] = ['subject_id' => $pid, 'context_type' => 'bounty', 'context_key' => 'bounty:' . (int) $b['id'],
                        'label' => 'Your trip together: ' . (string) $b['title'], 'ended_at' => (string) $b['completed_at']];
        }
    }

    /* Check-ins to the same city that overlapped, both ended, both wrote in the room. */
    $pairs = q_all("SELECT a.id mine, o.id theirs, o.user_id, d.name dest_name,
                           a.created_at a_from, COALESCE(a.ended_at, a.expires_at) a_to,
                           o.created_at o_from, COALESCE(o.ended_at, o.expires_at) o_to
                      FROM active_checkins a
                      JOIN active_checkins o ON o.destination_id = a.destination_id AND o.user_id <> a.user_id
                      JOIN destinations d ON d.id = a.destination_id
                     WHERE a.user_id = ?
                       AND COALESCE(a.ended_at, a.expires_at) <= ? AND COALESCE(a.ended_at, a.expires_at) >= ?
                       AND COALESCE(o.ended_at, o.expires_at) <= ?
                       AND a.created_at < COALESCE(o.ended_at, o.expires_at)
                       AND o.created_at < COALESCE(a.ended_at, a.expires_at)
                       AND EXISTS (SELECT 1 FROM city_room_messages m WHERE m.checkin_id = a.id)
                       AND EXISTS (SELECT 1 FROM city_room_messages m WHERE m.checkin_id = o.id)
                     ORDER BY a.id DESC, o.id DESC", [$userId, $now, $cut, $now]);
    $seenPerson = [];
    foreach ($pairs as $p) {
        $overlap = min(strtotime((string) $p['a_to']), strtotime((string) $p['o_to']))
                 - max(strtotime((string) $p['a_from']), strtotime((string) $p['o_from']));
        $pid = (int) $p['user_id'];
        if ($overlap < RMT_TRUST_MIN_OVERLAP || isset($seenPerson[$pid])) continue;
        $seenPerson[$pid] = true;
        $lo = min((int) $p['mine'], (int) $p['theirs']); $hi = max((int) $p['mine'], (int) $p['theirs']);
        $found[] = ['subject_id' => $pid, 'context_type' => 'checkin', 'context_key' => "checkin:$lo:$hi",
                    'label' => 'You were both checked in to ' . (string) $p['dest_name'],
                    'ended_at' => (string) min((string) $p['a_to'], (string) $p['o_to'])];
    }
    if (!$found) return [];

    /* Drop the ones already reviewed, and anybody whose account is gone. */
    $done = [];
    foreach (q_all('SELECT subject_id, context_key FROM user_reviews WHERE reviewer_id = ?', [$userId]) as $r) {
        $done[(int) $r['subject_id'] . '|' . (string) $r['context_key']] = true;
    }
    $out = [];
    foreach ($found as $f) {
        if (isset($done[$f['subject_id'] . '|' . $f['context_key']])) continue;
        $u = q_one("SELECT u.username, p.display_name, p.avatar_url FROM users u LEFT JOIN profiles p ON p.user_id = u.id
                     WHERE u.id = ? AND u.status = 'active'", [$f['subject_id']]);
        if (!$u) continue;
        $out[] = $f + ['username' => (string) $u['username'], 'display_name' => $u['display_name'], 'avatar_url' => $u['avatar_url']];
    }
    usort($out, static fn(array $a, array $b): int => strcmp($b['ended_at'], $a['ended_at']));
    return $out;
}

/**
 * Leave a vouch or a flag. Only for a meeting rmt_trust_reviewable() offers, and only once.
 *
 * @return array{ok:bool, error?:string, state?:string}
 */
function rmt_trust_review_submit(int $reviewerId, int $subjectId, string $contextKey, bool $isVouch,
                                 string $note = '', ?string $now = null): array {
    $now = rmt_trust_now($now);
    $offer = null;
    foreach (rmt_trust_reviewable($reviewerId, $now) as $r) {
        if ($r['subject_id'] === $subjectId && $r['context_key'] === $contextKey) { $offer = $r; break; }
    }
    if (!$offer) return ['ok' => false, 'error' => 'That is not open for a vouch or a flag. It may already be done, or the 30 days may have passed.'];
    $note = mb_substr(trim($note), 0, RMT_TRUST_NOTE_MAX);
    try {
        q_run('INSERT INTO user_reviews (reviewer_id, subject_id, context_type, context_key, is_vouch, note, status, created_at)
               VALUES (?,?,?,?,?,?,?,?)',
              [$reviewerId, $subjectId, $offer['context_type'], $contextKey, $isVouch ? 1 : 0, $note === '' ? null : $note, 'counted', $now]);
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        return ['ok' => false, 'error' => 'You have already answered for that one.'];
    }
    $t = rmt_trust_recompute($subjectId, $now);
    return ['ok' => true, 'state' => $t['trust_state']];
}

/**
 * Count again from the reviews and store the result. If that moves the member across the line,
 * record it, tell them, and tell the moderators.
 *
 * @return array{trust_score:int, vouch_count:int, flag_count:int, trust_state:string, changed:bool}
 */
function rmt_trust_recompute(int $userId, ?string $now = null, ?int $actorId = null, string $note = ''): array {
    $now = rmt_trust_now($now);
    $n = static fn(int $isVouch): int => (int) (q_one(
        "SELECT COUNT(DISTINCT r.reviewer_id) n FROM user_reviews r
           JOIN users u ON u.id = r.reviewer_id AND u.status = 'active'
          WHERE r.subject_id = ? AND r.status = 'counted' AND r.is_vouch = ?", [$userId, $isVouch])['n'] ?? 0);
    $vouches = $n(1); $flags = $n(0);
    $score = $vouches - RMT_TRUST_FLAG_WEIGHT * $flags;
    $state = ($flags >= RMT_TRUST_RESTRICT_FLAGS && $score < 0) ? 'restricted' : 'ok';

    $was = (string) (q_one('SELECT trust_state FROM profiles WHERE user_id = ?', [$userId])['trust_state'] ?? 'ok');
    $changed = $was !== $state;
    if ($changed) {
        q_run('UPDATE profiles SET trust_score = ?, vouch_count = ?, flag_count = ?, trust_state = ?, trust_state_at = ? WHERE user_id = ?',
              [$score, $vouches, $flags, $state, $now, $userId]);
        $reason = $actorId ? 'moderator' : ($state === 'restricted' ? 'flag_threshold' : 'flags_cleared');
        q_run('INSERT INTO trust_events (user_id, from_state, to_state, reason, trust_score, vouch_count, flag_count, actor_id, note, created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?)',
              [$userId, $was, $state, $reason, $score, $vouches, $flags, $actorId ?: null, mb_substr(trim($note), 0, 500) ?: null, $now]);
        rmt_trust_tell($userId, $state, $now);
    } else {
        q_run('UPDATE profiles SET trust_score = ?, vouch_count = ?, flag_count = ? WHERE user_id = ?', [$score, $vouches, $flags, $userId]);
    }
    rmt_trust_restricted($userId, true);
    return ['trust_score' => $score, 'vouch_count' => $vouches, 'flag_count' => $flags, 'trust_state' => $state, 'changed' => $changed];
}

/** A change of state is never silent: a notification, an email, and for a restriction a word to staff. */
function rmt_trust_tell(int $userId, string $state, string $now): void {
    q_run('INSERT INTO notifications (user_id, type, actor_id, target_type, target_id, created_at) VALUES (?,?,?,?,?,?)',
          [$userId, $state === 'restricted' ? 'trust_restricted' : 'trust_restored', null, 'trust', $userId, $now]);
    try {
        if (function_exists('rmt_notify_email_direct')) {
            $state === 'restricted'
                ? rmt_notify_email_direct($userId, 'Your messaging on RuinMyTrip is paused',
                    'Several travelers you met flagged your account, so sending messages is paused until a moderator has looked. Your trust page shows your numbers and what happens next.',
                    '/trust', 'your account was restricted')
                : rmt_notify_email_direct($userId, 'Your messaging on RuinMyTrip is back on',
                    'The restriction on your account has been lifted. You can send messages again.',
                    '/trust', 'your account restriction was lifted');
        }
        if ($state === 'restricted' && function_exists('rmt_report_alert_staff')) rmt_report_alert_staff('user', 'restricted by traveler flags');
    } catch (\Throwable $e) {
        // Mail is best effort. The state, the event and the notification are already written.
    }
}

/**
 * What a member is shown about themselves on /trust. Their own numbers and history, never who.
 *
 * @return array{trust_score:int, vouch_count:int, flag_count:int, trust_state:string,
 *               trust_state_at:?string, events:list<array<string,mixed>>}
 */
function rmt_trust_summary(int $userId): array {
    $p = q_one('SELECT trust_score, vouch_count, flag_count, trust_state, trust_state_at FROM profiles WHERE user_id = ?', [$userId]) ?: [];
    return [
        'trust_score' => (int) ($p['trust_score'] ?? 0), 'vouch_count' => (int) ($p['vouch_count'] ?? 0),
        'flag_count' => (int) ($p['flag_count'] ?? 0), 'trust_state' => (string) ($p['trust_state'] ?? 'ok'),
        'trust_state_at' => $p['trust_state_at'] ?? null,
        'events' => q_all('SELECT from_state, to_state, reason, trust_score, vouch_count, flag_count, created_at
                             FROM trust_events WHERE user_id = ? ORDER BY id DESC LIMIT 20', [$userId]),
    ];
}

/** Why a state changed, in the member's words. */
function rmt_trust_reason_label(string $reason, string $toState): string {
    return match ($reason) {
        'flag_threshold' => 'Travelers you met flagged your account',
        'flags_cleared'  => 'Your flags no longer meet the threshold',
        'moderator'      => $toState === 'ok' ? 'A moderator reviewed your flags and lifted the restriction' : 'A moderator restricted your account',
        default          => 'Changed',
    };
}

/* ---------- moderators ---------- */

/**
 * Restricted members, each with the flags that count against them. This is the only place the
 * notes and the names of the travelers who wrote them are read.
 *
 * @return list<array<string,mixed>>
 */
function rmt_trust_restricted_queue(int $limit = 50): array {
    $rows = q_all("SELECT u.id, u.username, p.display_name, p.trust_score, p.vouch_count, p.flag_count, p.trust_state_at
                     FROM profiles p JOIN users u ON u.id = p.user_id
                    WHERE p.trust_state = 'restricted' ORDER BY p.trust_state_at DESC LIMIT " . max(1, min(200, $limit)));
    foreach ($rows as &$r) {
        $r['flags'] = q_all("SELECT r.id, r.note, r.context_type, r.context_key, r.created_at, u.username reviewer
                               FROM user_reviews r LEFT JOIN users u ON u.id = r.reviewer_id
                              WHERE r.subject_id = ? AND r.is_vouch = 0 AND r.status = 'counted'
                              ORDER BY r.id DESC", [(int) $r['id']]);
    }
    return $rows;
}

/**
 * A moderator sets aside the flags against a member and the score is counted again. With the
 * flags discounted the restriction lifts by the same arithmetic that imposed it.
 *
 * @return array{ok:bool, error?:string, state?:string}
 */
function rmt_trust_lift(int $actorId, int $userId, string $note = '', ?string $now = null): array {
    $now = rmt_trust_now($now);
    if (!q_one('SELECT 1 FROM users WHERE id = ?', [$userId])) return ['ok' => false, 'error' => 'That account no longer exists.'];
    if (!rmt_trust_restricted($userId, true)) return ['ok' => false, 'error' => 'That account is not restricted.'];
    q_run("UPDATE user_reviews SET status = 'discounted' WHERE subject_id = ? AND is_vouch = 0 AND status = 'counted'", [$userId]);
    $t = rmt_trust_recompute($userId, $now, $actorId, $note);
    q_run('INSERT INTO moderation_log (actor_id, target_type, target_id, report_id, action, from_status, to_status, note, created_at)
           VALUES (?,?,?,?,?,?,?,?,?)',
          [$actorId ?: null, 'user', $userId, null, 'lift_restriction', 'restricted', $t['trust_state'], mb_substr(trim($note), 0, 500) ?: null, $now]);
    return ['ok' => true, 'state' => $t['trust_state']];
}

/* ---------- controllers ---------- */

/** GET /trust: a member's own numbers, how they are counted, their state, and who they can review. */
function trust_page(array $a): void {
    require_login(); $me = current_user();
    view('trust_page', [
        'me' => $me, 't' => rmt_trust_summary((int) $me['id']), 'open' => rmt_trust_reviewable((int) $me['id']),
    ], ['title' => 'Your trust | RuinMyTrip', 'canonical' => '', 'robots' => 'noindex,nofollow']);
}

/** POST /trust/review: subject_id, context_key, answer (vouch | flag), note. */
function trust_review(array $a): void {
    require_verified_email(); csrf_check(); $me = current_user();
    $answer = (string) input('answer');
    if (!in_array($answer, ['vouch', 'flag'], true)) { flash('Choose vouch or flag.'); redirect('/trust'); }
    if (!rmt_rate_ok('trust_review', (string) $me['id'], 20, 3600)) { flash('Slow down a little and try again soon.'); redirect('/trust'); }
    $r = rmt_trust_review_submit((int) $me['id'], (int) input('subject_id'), (string) input('context_key'), $answer === 'vouch', (string) input('note'));
    flash($r['ok']
        ? ($answer === 'vouch' ? 'Thank you. Your vouch is counted, and it stays private.' : 'Thank you. Your flag is counted, and it stays private. If you are in danger, contact local emergency services.')
        : $r['error']);
    redirect('/trust');
}

/** POST /admin/trust/lift: user_id, note. */
function admin_trust_lift(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $r = rmt_trust_lift((int) current_user()['id'], (int) input('user_id'), (string) input('note'));
    flash($r['ok'] ? 'Flags set aside and the restriction lifted. Recorded.' : ($r['error'] ?? 'That could not be done.'));
    redirect('/admin/moderation');
}
