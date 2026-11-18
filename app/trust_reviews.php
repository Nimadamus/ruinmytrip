<?php
/**
 * Vouches and flags, the trust score they add up to, moderator review, and the restricted state
 * (migrations 122 and 123).
 *
 * After two travelers have actually crossed paths here, each may leave the other one private
 * vouch or flag:
 *
 *   - a buddy trip both were on (the poster and everybody the poster accepted) that is over
 *   - two check-ins to the same city that overlapped for an hour or more, where both of them
 *     wrote in that city's room, once both check-ins have ended
 *
 * WHAT IS PRIVATE: who vouched, who flagged, and what they wrote. Only moderators read those.
 * A member's score and counts are shown to that member and to moderators, and to nobody else.
 * Nothing public is ordered by the score.
 *
 * WHAT A MEMBER CAN ALWAYS SEE (see app/trust.php): their own vouch count, flag count, score, the
 * arithmetic that produced it, their state, and the dated history of every moderator decision
 * about them, on /trust.
 *
 * NOTHING HERE IS AUTOMATIC. Flags never restrict anybody. When enough travelers have flagged one
 * member the account goes into a moderator's review queue, with warnings about the patterns a
 * coordinated pile-on leaves behind (rmt_trust_flag_signals). Only a moderator can restrict an
 * account, only with a written internal note and a reason the member is shown, and only while the
 * feature is in 'active' mode. A restricted member is told on every page, can appeal from /trust,
 * and every step is written to trust_events and moderation_log.
 *
 * STAGED ROLLOUT (RMT_TRUST_MODE in the environment, read by rmt_trust_mode()):
 *
 *     off      the default. Only staff accounts can see or use any of it. Internal testing.
 *     monitor  members vouch and flag and see their own numbers; moderators see the review queue;
 *              NO account can be restricted.
 *     active   as monitor, and a moderator can restrict after a documented review.
 *
 * The arithmetic, in one place (rmt_trust_recompute):
 *
 *     vouch_count = travelers who vouched for you      (a traveler counts once)
 *     flag_count  = travelers who flagged you          (a traveler counts once)
 *     trust_score = vouch_count - RMT_TRUST_FLAG_WEIGHT * flag_count
 *
 * Reviews from accounts that are no longer active, and reviews a moderator has set aside, are
 * not counted.
 */
declare(strict_types=1);

const RMT_TRUST_FLAG_WEIGHT = 2;
/** This many travelers flagging one member puts the account in the moderators' review queue. */
const RMT_TRUST_REVIEW_FLAGS = 3;
/** How long after a trip or a check-in ends the two travelers can still review each other. */
const RMT_TRUST_REVIEW_DAYS = 30;
/** Two check-ins have to overlap this long before either traveler can review the other. */
const RMT_TRUST_MIN_OVERLAP = 3600;
const RMT_TRUST_NOTE_MAX = 1000;
/** One member can leave at most this many flags in any 30 days. */
const RMT_TRUST_FLAGS_PER_30D = 5;
/** A moderator's note has to say something. */
const RMT_TRUST_MOD_NOTE_MIN = 10;
const RMT_TRUST_APPEAL_MIN = 20;
const RMT_TRUST_APPEAL_MAX = 2000;
/** After an appeal is turned down, the member can appeal again after this many days. */
const RMT_TRUST_APPEAL_AGAIN_DAYS = 14;

function rmt_trust_now(?string $now = null): string { return $now ?? date('Y-m-d H:i:s'); }

/** off | monitor | active. Anything unrecognised is off. */
function rmt_trust_mode(): string {
    $m = (string) ($GLOBALS['rmt_trust_mode'] ?? (getenv('RMT_TRUST_MODE') ?: 'off'));
    return in_array($m, ['off', 'monitor', 'active'], true) ? $m : 'off';
}

function rmt_trust_is_staff(?array $user): bool {
    return $user !== null && in_array((string) ($user['role'] ?? ''), ['admin', 'mod'], true);
}

/** May this account see and use vouching at all? Staff always (internal testing); members once it is on. */
function rmt_trust_open_for(?array $user): bool {
    if (!$user) return false;
    return rmt_trust_mode() !== 'off' || rmt_trust_is_staff($user);
}

/**
 * Is this member's messaging paused? Asked on every send, so it is one indexed read, kept for the
 * request. It is deliberately NOT folded into current_user(): a sign-in must never depend on
 * this feature's columns. Reads as "no" where the columns do not exist yet, so a page never fails
 * on this check.
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
    if (rmt_trust_mode() === 'off'
        && !rmt_trust_is_staff(q_one('SELECT role FROM users WHERE id = ?', [$userId]))) return [];
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
 * @return array{ok:bool, error?:string}
 */
function rmt_trust_review_submit(int $reviewerId, int $subjectId, string $contextKey, bool $isVouch,
                                 string $note = '', ?string $now = null): array {
    $now = rmt_trust_now($now);
    $offer = null;
    foreach (rmt_trust_reviewable($reviewerId, $now) as $r) {
        if ($r['subject_id'] === $subjectId && $r['context_key'] === $contextKey) { $offer = $r; break; }
    }
    if (!$offer) return ['ok' => false, 'error' => 'That is not open for a vouch or a flag. It may already be done, or the 30 days may have passed.'];
    if (!$isVouch) {
        /* One account cannot flag its way through a room. */
        $recent = (int) (q_one('SELECT COUNT(*) n FROM user_reviews WHERE reviewer_id = ? AND is_vouch = 0 AND created_at >= ?',
                               [$reviewerId, date('Y-m-d H:i:s', strtotime($now) - 30 * 86400)])['n'] ?? 0);
        if ($recent >= RMT_TRUST_FLAGS_PER_30D) {
            return ['ok' => false, 'error' => 'You have reached the limit on flags for now. If somebody is a danger to others, use Report on their profile so a moderator sees it today.'];
        }
    }
    $note = mb_substr(trim($note), 0, RMT_TRUST_NOTE_MAX);
    try {
        q_run('INSERT INTO user_reviews (reviewer_id, subject_id, context_type, context_key, is_vouch, note, status, created_at)
               VALUES (?,?,?,?,?,?,?,?)',
              [$reviewerId, $subjectId, $offer['context_type'], $contextKey, $isVouch ? 1 : 0, $note === '' ? null : $note, 'counted', $now]);
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        return ['ok' => false, 'error' => 'You have already answered for that one.'];
    }
    $before = (int) (q_one('SELECT flag_count FROM profiles WHERE user_id = ?', [$subjectId])['flag_count'] ?? 0);
    $t = rmt_trust_recompute($subjectId);
    /* A new traveler flagging, at or past the line: moderators are told to look. The member is
       not restricted and nothing else changes. */
    if (!$isVouch && $t['flag_count'] > $before && $t['flag_count'] >= RMT_TRUST_REVIEW_FLAGS) {
        try { if (function_exists('rmt_report_alert_staff')) rmt_report_alert_staff('user', 'traveler flags need review'); }
        catch (\Throwable $e) { /* the queue has it either way */ }
    }
    return ['ok' => true];
}

/**
 * Count again from the reviews and store the numbers. This never changes anybody's state.
 *
 * @return array{trust_score:int, vouch_count:int, flag_count:int}
 */
function rmt_trust_recompute(int $userId): array {
    $n = static fn(int $isVouch): int => (int) (q_one(
        "SELECT COUNT(DISTINCT r.reviewer_id) n FROM user_reviews r
           JOIN users u ON u.id = r.reviewer_id AND u.status = 'active'
          WHERE r.subject_id = ? AND r.status = 'counted' AND r.is_vouch = ?", [$userId, $isVouch])['n'] ?? 0);
    $vouches = $n(1); $flags = $n(0);
    $score = $vouches - RMT_TRUST_FLAG_WEIGHT * $flags;
    q_run('UPDATE profiles SET trust_score = ?, vouch_count = ?, flag_count = ? WHERE user_id = ?', [$score, $vouches, $flags, $userId]);
    return ['trust_score' => $score, 'vouch_count' => $vouches, 'flag_count' => $flags];
}

/** One line in the audit trail. $note is what the member is shown; internal notes go to moderation_log. */
function rmt_trust_event(int $userId, string $from, string $to, string $reason, ?int $actorId, ?string $note, string $now): void {
    $p = q_one('SELECT trust_score, vouch_count, flag_count FROM profiles WHERE user_id = ?', [$userId]) ?: [];
    q_run('INSERT INTO trust_events (user_id, from_state, to_state, reason, trust_score, vouch_count, flag_count, actor_id, note, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?)',
          [$userId, $from, $to, $reason, (int) ($p['trust_score'] ?? 0), (int) ($p['vouch_count'] ?? 0), (int) ($p['flag_count'] ?? 0),
           $actorId ?: null, $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null, $now]);
}

/** A decision about a member is never silent: a notification and an email. Mail is best effort. */
function rmt_trust_tell(int $userId, string $type, string $now): void {
    q_run('INSERT INTO notifications (user_id, type, actor_id, target_type, target_id, created_at) VALUES (?,?,?,?,?,?)',
          [$userId, $type, null, 'trust', $userId, $now]);
    $mail = [
        'trust_restricted'    => ['Your messaging on RuinMyTrip is paused', 'A moderator reviewed flags from travelers you met and restricted your account, so sending messages is paused. Your trust page shows the reason and how to appeal.', 'your account was restricted'],
        'trust_restored'      => ['Your messaging on RuinMyTrip is back on', 'The restriction on your account has been lifted. You can send messages again.', 'your account restriction was lifted'],
        'trust_appeal_upheld' => ['An update on your appeal', 'A moderator looked at your appeal and the restriction stays for now. Your trust page has the details.', 'your appeal was answered'],
    ][$type] ?? null;
    try {
        if ($mail && function_exists('rmt_notify_email_direct')) rmt_notify_email_direct($userId, $mail[0], $mail[1], '/trust', $mail[2]);
    } catch (\Throwable $e) {
        // The state, the event and the notification are already written.
    }
}

/**
 * What a member is shown about themselves on /trust. Their own numbers and history, never who.
 *
 * @return array<string,mixed>
 */
function rmt_trust_summary(int $userId, ?string $now = null): array {
    $now = rmt_trust_now($now);
    $p = q_one('SELECT trust_score, vouch_count, flag_count, trust_state, trust_state_at FROM profiles WHERE user_id = ?', [$userId]) ?: [];
    $state = (string) ($p['trust_state'] ?? 'ok');
    $appeal = q_one('SELECT id, status, body, created_at, decided_at, decision_note FROM trust_appeals WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
    $canAppeal = $state === 'restricted' && (!$appeal || ($appeal['status'] === 'upheld'
        && strtotime((string) $appeal['decided_at']) <= strtotime($now) - RMT_TRUST_APPEAL_AGAIN_DAYS * 86400));
    return [
        'trust_score' => (int) ($p['trust_score'] ?? 0), 'vouch_count' => (int) ($p['vouch_count'] ?? 0),
        'flag_count' => (int) ($p['flag_count'] ?? 0), 'trust_state' => $state,
        'trust_state_at' => $p['trust_state_at'] ?? null,
        'reason' => $state === 'restricted'
            ? (q_one("SELECT note FROM trust_events WHERE user_id = ? AND to_state = 'restricted' AND reason = 'restricted' ORDER BY id DESC LIMIT 1", [$userId])['note'] ?? null)
            : null,
        'appeal' => $appeal, 'can_appeal' => $canAppeal,
        'events' => q_all('SELECT from_state, to_state, reason, note, trust_score, vouch_count, flag_count, created_at
                             FROM trust_events WHERE user_id = ? ORDER BY id DESC LIMIT 20', [$userId]),
    ];
}

/** What happened, in the member's words. */
function rmt_trust_reason_label(string $reason): string {
    return match ($reason) {
        'reviewed_no_action' => 'A moderator reviewed flags on your account and took no action',
        'flags_set_aside'    => 'A moderator reviewed flags on your account and set them aside',
        'restricted'         => 'A moderator reviewed flags on your account and restricted it. Messaging was paused',
        'restriction_lifted' => 'A moderator lifted the restriction. Messaging was turned back on',
        'appeal_granted'     => 'Your appeal was granted. Messaging was turned back on',
        'appeal_upheld'      => 'A moderator answered your appeal and the restriction stayed',
        default              => 'A moderator updated your account',
    };
}

/* ---------- appeals ---------- */

/** @return array{ok:bool, error?:string} */
function rmt_trust_appeal_submit(int $userId, string $body, ?string $now = null): array {
    $now = rmt_trust_now($now);
    $s = rmt_trust_summary($userId, $now);
    if ($s['trust_state'] !== 'restricted') return ['ok' => false, 'error' => 'Your account is not restricted, so there is nothing to appeal.'];
    if (!$s['can_appeal']) {
        return ['ok' => false, 'error' => ($s['appeal']['status'] ?? '') === 'open'
            ? 'Your appeal is already with a moderator.'
            : 'You can appeal again ' . RMT_TRUST_APPEAL_AGAIN_DAYS . ' days after the last answer.'];
    }
    $body = trim($body);
    if (mb_strlen($body) < RMT_TRUST_APPEAL_MIN) return ['ok' => false, 'error' => 'Say a little more, so a moderator has something to go on.'];
    q_run('INSERT INTO trust_appeals (user_id, body, status, created_at) VALUES (?,?,?,?)',
          [$userId, mb_substr($body, 0, RMT_TRUST_APPEAL_MAX), 'open', $now]);
    try { if (function_exists('rmt_report_alert_staff')) rmt_report_alert_staff('user', 'restriction appeal'); } catch (\Throwable $e) {}
    return ['ok' => true];
}

/* ---------- moderators ---------- */

/**
 * The patterns a coordinated pile-on leaves behind, for the flags that count against one member.
 * Plain sentences for the moderator. Empty means none of them showed up, not that the flags are true.
 *
 * @return list<string>
 */
function rmt_trust_flag_signals(int $userId): array {
    $flags = q_all("SELECT r.reviewer_id, r.context_key, r.created_at, u.created_at joined
                      FROM user_reviews r JOIN users u ON u.id = r.reviewer_id AND u.status = 'active'
                     WHERE r.subject_id = ? AND r.is_vouch = 0 AND r.status = 'counted' ORDER BY r.created_at", [$userId]);
    $n = count($flags);
    if ($n === 0) return [];
    $out = [];

    $byMeeting = array_count_values(array_column($flags, 'context_key'));
    arsort($byMeeting);
    $top = (int) reset($byMeeting);
    if ($n >= 2 && $top >= 2 && $top >= (int) ceil($n * 0.6)) {
        $out[] = "$top of the $n flags come from one trip or one room, so this may be one group, not $n separate meetings.";
    }
    $times = array_map(static fn(array $f): int => (int) strtotime((string) $f['created_at']), $flags);
    if ($n >= 2 && max($times) - min($times) <= 86400) $out[] = "All $n flags arrived within 24 hours of each other.";

    $new = 0;
    foreach ($flags as $f) {
        if (strtotime((string) $f['created_at']) - (int) strtotime((string) $f['joined']) < 14 * 86400) $new++;
    }
    if ($new > 0) $out[] = "$new of the $n came from accounts less than two weeks old when they flagged.";

    $ids = array_values(array_unique(array_map('intval', array_column($flags, 'reviewer_id'))));
    $in = implode(',', $ids);
    $retaliation = (int) (q_one("SELECT COUNT(DISTINCT subject_id) n FROM user_reviews
                                  WHERE reviewer_id = ? AND is_vouch = 0 AND subject_id IN ($in)", [$userId])['n'] ?? 0);
    if ($retaliation > 0) $out[] = "This member had flagged $retaliation of the travelers who flagged them, so some of this may be an argument between them.";

    $serial = (int) (q_one("SELECT COUNT(*) n FROM (SELECT reviewer_id FROM user_reviews
                             WHERE is_vouch = 0 AND reviewer_id IN ($in) GROUP BY reviewer_id HAVING COUNT(DISTINCT subject_id) >= 3) s")['n'] ?? 0);
    if ($serial > 0) $out[] = "$serial of the flaggers have flagged three or more different members.";

    $linked = (int) (q_one("SELECT COUNT(*) n FROM follows WHERE follower_id IN ($in) AND followee_id IN ($in)")['n'] ?? 0);
    if ($linked > 0) $out[] = 'Some of the flaggers follow each other.';
    return $out;
}

/** The flags that count against a member, with who left each. Moderators only. */
function rmt_trust_flags_for(int $userId): array {
    return q_all("SELECT r.id, r.note, r.context_type, r.context_key, r.created_at, u.username reviewer
                    FROM user_reviews r LEFT JOIN users u ON u.id = r.reviewer_id
                   WHERE r.subject_id = ? AND r.is_vouch = 0 AND r.status = 'counted' ORDER BY r.id DESC", [$userId]);
}

/**
 * Everything waiting for a moderator:
 *   review      members with enough flags, not restricted, with a flag newer than the last review
 *   restricted  members currently restricted
 *   appeals     open appeals
 *
 * @return array{review:list<array<string,mixed>>, restricted:list<array<string,mixed>>, appeals:list<array<string,mixed>>}
 */
function rmt_trust_mod_queue(int $limit = 50): array {
    $limit = max(1, min(200, $limit));
    $cols = 'u.id, u.username, p.display_name, p.trust_score, p.vouch_count, p.flag_count, p.trust_state, p.trust_state_at';
    $decorate = static function (array $r): array {
        $r['flags'] = rmt_trust_flags_for((int) $r['id']);
        $r['signals'] = rmt_trust_flag_signals((int) $r['id']);
        return $r;
    };
    $review = [];
    foreach (q_all("SELECT $cols FROM profiles p JOIN users u ON u.id = p.user_id AND u.status = 'active'
                     WHERE p.trust_state = 'ok' AND p.flag_count >= ? ORDER BY p.flag_count DESC LIMIT $limit", [RMT_TRUST_REVIEW_FLAGS]) as $r) {
        $lastFlag = (string) (q_one("SELECT MAX(created_at) t FROM user_reviews WHERE subject_id = ? AND is_vouch = 0 AND status = 'counted'", [(int) $r['id']])['t'] ?? '');
        $lastLook = (string) (q_one('SELECT MAX(created_at) t FROM trust_events WHERE user_id = ?', [(int) $r['id']])['t'] ?? '');
        if ($lastLook !== '' && $lastLook >= $lastFlag) continue;      // already reviewed, nothing new since
        $review[] = $decorate($r);
    }
    $restricted = array_map($decorate, q_all("SELECT $cols FROM profiles p JOIN users u ON u.id = p.user_id
                                               WHERE p.trust_state = 'restricted' ORDER BY p.trust_state_at DESC LIMIT $limit"));
    $appeals = q_all("SELECT a.id, a.user_id, a.body, a.created_at, u.username FROM trust_appeals a JOIN users u ON u.id = a.user_id
                       WHERE a.status = 'open' ORDER BY a.id LIMIT $limit");
    return ['review' => $review, 'restricted' => $restricted, 'appeals' => $appeals];
}

function rmt_trust_mod_log(int $actorId, int $userId, string $action, string $from, string $to, string $note, string $now): void {
    q_run('INSERT INTO moderation_log (actor_id, target_type, target_id, report_id, action, from_status, to_status, note, created_at)
           VALUES (?,?,?,?,?,?,?,?,?)',
          [$actorId, 'user', $userId, null, $action, $from, $to, mb_substr(trim($note), 0, 500), $now]);
}

/**
 * A moderator's decision after reading the flags. Every action needs a written internal note.
 *
 *   no_action   reviewed, nothing changes; the account leaves the queue until a new flag arrives
 *   set_aside   the flags do not hold up: they stop counting (kept as a record). Lifts a restriction.
 *   restrict    pause the member's messaging. 'active' mode only, needs $reason, which the member is shown.
 *   lift        end a restriction and leave the flags counted
 *
 * @return array{ok:bool, error?:string, state?:string}
 */
function rmt_trust_mod_action(int $actorId, int $userId, string $action, string $note, string $reason = '', ?string $now = null): array {
    $now = rmt_trust_now($now);
    $actor = q_one("SELECT id, role FROM users WHERE id = ? AND status = 'active'", [$actorId]);
    if (!rmt_trust_is_staff($actor)) return ['ok' => false, 'error' => 'Only a moderator can do that.'];
    if (!in_array($action, ['no_action', 'set_aside', 'restrict', 'lift'], true)) return ['ok' => false, 'error' => 'Unknown action.'];
    $u = q_one('SELECT u.id, u.role, p.trust_state FROM users u JOIN profiles p ON p.user_id = u.id WHERE u.id = ?', [$userId]);
    if (!$u) return ['ok' => false, 'error' => 'That account no longer exists.'];
    if ($userId === $actorId) return ['ok' => false, 'error' => 'You cannot review your own account.'];
    $note = trim($note); $reason = trim($reason);
    if (mb_strlen($note) < RMT_TRUST_MOD_NOTE_MIN) return ['ok' => false, 'error' => 'Write what you checked and why. The note is the record of this review.'];
    $from = (string) $u['trust_state'];

    if ($action === 'restrict') {
        if (rmt_trust_mode() !== 'active') return ['ok' => false, 'error' => 'Restrictions are switched off. Vouching is in ' . rmt_trust_mode() . ' mode.'];
        if (rmt_trust_is_staff($u)) return ['ok' => false, 'error' => 'Staff accounts are not restricted from here.'];
        if ($from === 'restricted') return ['ok' => false, 'error' => 'That account is already restricted.'];
        if (!rmt_trust_flags_for($userId)) return ['ok' => false, 'error' => 'There are no counted flags against that account.'];
        if (mb_strlen($reason) < RMT_TRUST_MOD_NOTE_MIN) return ['ok' => false, 'error' => 'Write the reason the member will be shown.'];
        q_run("UPDATE profiles SET trust_state = 'restricted', trust_state_at = ? WHERE user_id = ?", [$now, $userId]);
        rmt_trust_event($userId, $from, 'restricted', 'restricted', $actorId, $reason, $now);
        rmt_trust_mod_log($actorId, $userId, 'trust_restrict', $from, 'restricted', $note, $now);
        rmt_trust_tell($userId, 'trust_restricted', $now);
        rmt_trust_restricted($userId, true);
        return ['ok' => true, 'state' => 'restricted'];
    }
    if ($action === 'lift') {
        if ($from !== 'restricted') return ['ok' => false, 'error' => 'That account is not restricted.'];
        q_run("UPDATE profiles SET trust_state = 'ok', trust_state_at = ? WHERE user_id = ?", [$now, $userId]);
        rmt_trust_event($userId, 'restricted', 'ok', 'restriction_lifted', $actorId, null, $now);
        rmt_trust_mod_log($actorId, $userId, 'trust_lift', 'restricted', 'ok', $note, $now);
        rmt_trust_tell($userId, 'trust_restored', $now);
        rmt_trust_restricted($userId, true);
        return ['ok' => true, 'state' => 'ok'];
    }
    if ($action === 'set_aside') {
        q_run("UPDATE user_reviews SET status = 'discounted' WHERE subject_id = ? AND is_vouch = 0 AND status = 'counted'", [$userId]);
        rmt_trust_recompute($userId);
        if ($from === 'restricted') q_run("UPDATE profiles SET trust_state = 'ok', trust_state_at = ? WHERE user_id = ?", [$now, $userId]);
        rmt_trust_event($userId, $from, 'ok', 'flags_set_aside', $actorId, null, $now);
        rmt_trust_mod_log($actorId, $userId, 'trust_set_aside', $from, 'ok', $note, $now);
        if ($from === 'restricted') rmt_trust_tell($userId, 'trust_restored', $now);
        rmt_trust_restricted($userId, true);
        return ['ok' => true, 'state' => 'ok'];
    }
    rmt_trust_event($userId, $from, $from, 'reviewed_no_action', $actorId, null, $now);
    rmt_trust_mod_log($actorId, $userId, 'trust_no_action', $from, $from, $note, $now);
    return ['ok' => true, 'state' => $from];
}

/**
 * Answer an appeal: grant (the restriction ends) or uphold (it stays). Both need a note, and the
 * member is shown $answer.
 *
 * @return array{ok:bool, error?:string}
 */
function rmt_trust_appeal_decide(int $actorId, int $appealId, string $decision, string $note, string $answer = '', ?string $now = null): array {
    $now = rmt_trust_now($now);
    if (!rmt_trust_is_staff(q_one("SELECT id, role FROM users WHERE id = ? AND status = 'active'", [$actorId]))) return ['ok' => false, 'error' => 'Only a moderator can do that.'];
    if (!in_array($decision, ['grant', 'uphold'], true)) return ['ok' => false, 'error' => 'Unknown decision.'];
    $a = q_one("SELECT * FROM trust_appeals WHERE id = ? AND status = 'open'", [$appealId]);
    if (!$a) return ['ok' => false, 'error' => 'That appeal is no longer open.'];
    $userId = (int) $a['user_id'];
    if ($userId === $actorId) return ['ok' => false, 'error' => 'You cannot answer your own appeal.'];
    $note = trim($note); $answer = trim($answer);
    if (mb_strlen($note) < RMT_TRUST_MOD_NOTE_MIN) return ['ok' => false, 'error' => 'Write what you checked and why. The note is the record of this decision.'];
    if ($decision === 'uphold' && mb_strlen($answer) < RMT_TRUST_MOD_NOTE_MIN) return ['ok' => false, 'error' => 'Write the answer the member will be shown.'];
    $status = $decision === 'grant' ? 'granted' : 'upheld';
    q_run('UPDATE trust_appeals SET status = ?, decided_by = ?, decided_at = ?, decision_note = ? WHERE id = ?',
          [$status, $actorId, $now, $answer === '' ? null : mb_substr($answer, 0, 500), $appealId]);
    $restricted = rmt_trust_restricted($userId, true);
    if ($decision === 'grant') {
        if ($restricted) q_run("UPDATE profiles SET trust_state = 'ok', trust_state_at = ? WHERE user_id = ?", [$now, $userId]);
        rmt_trust_event($userId, $restricted ? 'restricted' : 'ok', 'ok', 'appeal_granted', $actorId, $answer, $now);
        rmt_trust_mod_log($actorId, $userId, 'trust_appeal_granted', $restricted ? 'restricted' : 'ok', 'ok', $note, $now);
        rmt_trust_tell($userId, 'trust_restored', $now);
    } else {
        rmt_trust_event($userId, 'restricted', 'restricted', 'appeal_upheld', $actorId, $answer, $now);
        rmt_trust_mod_log($actorId, $userId, 'trust_appeal_upheld', 'restricted', 'restricted', $note, $now);
        rmt_trust_tell($userId, 'trust_appeal_upheld', $now);
    }
    rmt_trust_restricted($userId, true);
    return ['ok' => true];
}

/* ---------- controllers ---------- */

/** GET /trust: a member's own numbers, how they are counted, their state, and who they can review. */
function trust_page(array $a): void {
    require_login(); $me = current_user();
    $t = rmt_trust_summary((int) $me['id']);
    /* A restricted member always reaches this page, whatever the mode: it is where the reason and
       the appeal live. */
    if (!rmt_trust_open_for($me) && $t['trust_state'] !== 'restricted') not_found();
    view('trust_page', ['me' => $me, 't' => $t, 'open' => rmt_trust_reviewable((int) $me['id'])],
         ['title' => 'Your trust | RuinMyTrip', 'canonical' => '', 'robots' => 'noindex,nofollow', 'private' => true]);
}

/** POST /trust/review: subject_id, context_key, answer (vouch | flag), note. */
function trust_review(array $a): void {
    require_verified_email(); csrf_check(); $me = current_user();
    if (!rmt_trust_open_for($me)) not_found();
    $answer = (string) input('answer');
    if (!in_array($answer, ['vouch', 'flag'], true)) { flash('Choose vouch or flag.'); redirect('/trust'); }
    if (!rmt_rate_ok('trust_review', (string) $me['id'], 20, 3600)) { flash('Slow down a little and try again soon.'); redirect('/trust'); }
    $r = rmt_trust_review_submit((int) $me['id'], (int) input('subject_id'), (string) input('context_key'), $answer === 'vouch', (string) input('note'));
    flash($r['ok']
        ? ($answer === 'vouch' ? 'Thank you. Your vouch is counted, and it stays private.' : 'Thank you. Your flag is counted, and it stays private. If you are in danger, contact local emergency services.')
        : $r['error']);
    redirect('/trust');
}

/** POST /trust/appeal: body. */
function trust_appeal(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    if (!rmt_rate_ok('trust_appeal', (string) $me['id'], 5, 3600)) { flash('Slow down a little and try again soon.'); redirect('/trust'); }
    $r = rmt_trust_appeal_submit((int) $me['id'], (string) input('body'));
    flash($r['ok'] ? 'Your appeal is with a moderator. You will be told the answer here and by email.' : $r['error']);
    redirect('/trust');
}

/** POST /admin/trust/act: user_id, action, note, reason. */
function admin_trust_act(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $r = rmt_trust_mod_action((int) current_user()['id'], (int) input('user_id'), (string) input('action'), (string) input('note'), (string) input('reason'));
    flash($r['ok'] ? 'Done, and recorded.' : ($r['error'] ?? 'That could not be done.'));
    redirect('/admin/moderation#trust');
}

/** POST /admin/trust/appeal: appeal_id, decision, note, answer. */
function admin_trust_appeal(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $r = rmt_trust_appeal_decide((int) current_user()['id'], (int) input('appeal_id'), (string) input('decision'), (string) input('note'), (string) input('answer'));
    flash($r['ok'] ? 'Answered, and recorded.' : ($r['error'] ?? 'That could not be done.'));
    redirect('/admin/moderation#trust');
}
