<?php
declare(strict_types=1);

/**
 * "I would like to meet on this trip", as a request somebody can say no to.
 *
 * The gap this fills. Two travelers whose dates overlap could, until now, do exactly two things
 * about it: nothing, or send a private message to a stranger. Nothing is why a match list goes
 * cold after one look, and an unsolicited message from somebody who found you by your travel dates
 * is how a travel site becomes a place people leave. What was missing is the small deliberate
 * signal in between, and this is it.
 *
 * Four rules it is built on, each one a decision rather than a default:
 *
 *   1. IT CARRIES NO WORDS. There is no body, no subject, no note. It cannot be harassment with a
 *      message attached, and it needs no moderation queue, because the only content is the fact
 *      that somebody pressed a button.
 *   2. IT SHARES NOTHING NEW. Both people already published the city and the date range; that is
 *      all either of them learns. No address, no telephone number, no email, no accommodation,
 *      nothing about where anybody is.
 *   3. IT IS A REQUEST, NOT AN ENROLMENT. Nobody is added to anybody's trip. The person asked
 *      decides, and until they decide, nothing about them has changed anywhere on the site.
 *   4. IT IS IDEMPOTENT AT THE DATABASE. One row per (trip, sender), enforced by a unique index
 *      rather than by a check in code, so a double tap, a refresh, a retried POST and a back
 *      button all land on the row that already exists.
 *
 * Messaging is deliberately downstream of this: a thread is offered once both sides have said yes,
 * and never before.
 */

const RMT_CONNECT_STATES = ['interested', 'accepted', 'declined', 'withdrawn'];

/** One notification type for both halves: somebody asked, and somebody said yes. */
const RMT_CONNECT_NOTIFY_TYPE = 'trip_connect';

/** The connect row between one member and one trip, whichever side is asking. */
function rmt_connect_get(int $tripId, int $fromUserId): ?array {
    return q_one('SELECT * FROM trip_connects WHERE trip_id = ? AND from_user_id = ?', [$tripId, $fromUserId]);
}

/** Every connect on a trip, newest first, with the asker's name and face. */
function rmt_connects_for_trip(int $tripId): array {
    return q_all("SELECT c.*, u.username, p.display_name, p.avatar_url
                    FROM trip_connects c
                    JOIN users u ON u.id = c.from_user_id
               LEFT JOIN profiles p ON p.user_id = c.from_user_id
                   WHERE c.trip_id = ? AND u.status = 'active'
                ORDER BY c.id DESC", [$tripId]);
}

/**
 * What somebody has asked, and what has been asked of them, in two lookups rather than one per
 * card. Keyed by trip id so a list of trips can ask about itself without a query each.
 *
 * @return array{sent:array<int,array<string,mixed>>, received:array<int,list<array<string,mixed>>>}
 */
function rmt_connect_state_for(int $userId, array $tripIds): array {
    $ids = array_values(array_unique(array_map('intval', $tripIds)));
    $sent = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (q_all("SELECT * FROM trip_connects WHERE from_user_id = ? AND trip_id IN ($in)",
                       array_merge([$userId], $ids)) as $r) {
            $sent[(int) $r['trip_id']] = $r;
        }
    }
    $received = [];
    foreach (q_all("SELECT c.*, u.username, p.display_name, p.avatar_url
                      FROM trip_connects c
                      JOIN users u ON u.id = c.from_user_id
                 LEFT JOIN profiles p ON p.user_id = c.from_user_id
                     WHERE c.to_user_id = ? AND u.status = 'active' AND c.state IN ('interested','accepted')
                  ORDER BY c.id DESC LIMIT 100", [$userId]) as $r) {
        $received[(int) $r['trip_id']][] = $r;
    }
    return ['sent' => $sent, 'received' => $received];
}

/**
 * May this member ask to meet on this trip?
 *
 * Everything that decides who appears in a match list decides this too, and for the same reasons:
 * the trip has to be one they were allowed to see, neither side can have blocked the other, and
 * the trip owner must not have said they are not looking to meet. A control honoured on the page
 * and not on the button is not a control.
 *
 * @return array{ok:bool, reason?:string, trip?:array}
 */
function rmt_connect_can(int $userId, int $tripId): array {
    $t = q_one("SELECT * FROM trips WHERE id = ? AND status = 'published'", [$tripId]);
    if (!$t) return ['ok' => false, 'reason' => 'That trip is not there any more.'];
    $ownerId = (int) $t['user_id'];
    if ($ownerId === $userId) return ['ok' => false, 'reason' => 'That is your own trip.'];
    if (empty($t['date_from']) || empty($t['date_to'])) {
        return ['ok' => false, 'reason' => 'That trip has no dates to meet on.'];
    }
    if ((string) $t['date_to'] < gmdate('Y-m-d')) {
        return ['ok' => false, 'reason' => 'That trip is over.'];
    }
    if ($t['open_to_meeting'] !== null && (int) $t['open_to_meeting'] === 0) {
        return ['ok' => false, 'reason' => 'That traveler is not looking to meet up.'];
    }
    if (!rmt_trip_visible_to($t, ['id' => $userId])) {
        return ['ok' => false, 'reason' => 'That trip is not there any more.'];
    }
    if (function_exists('rmt_is_blocked') && rmt_is_blocked($userId, $ownerId)) {
        return ['ok' => false, 'reason' => 'That traveler is not available.'];
    }
    return ['ok' => true, 'trip' => $t];
}

/**
 * Record the interest. Returns the state the pair is in afterwards, which is the state they were
 * already in when the request is a repeat.
 *
 * A declined request is not offered again: somebody who said no said it once, and a site that lets
 * the asker retry tomorrow has built a way to pester people out of a button.
 *
 * @return array{ok:bool, state:string, created:bool, reason?:string}
 */
function rmt_connect_request(int $userId, int $tripId): array {
    $can = rmt_connect_can($userId, $tripId);
    if (!$can['ok']) return ['ok' => false, 'state' => 'none', 'created' => false, 'reason' => $can['reason']];
    $trip = $can['trip'];

    $existing = rmt_connect_get($tripId, $userId);
    if ($existing) {
        $state = (string) $existing['state'];
        if ($state === 'withdrawn') {
            // Changing your mind back is allowed. Changing somebody else's answer is not.
            q_run("UPDATE trip_connects SET state = 'interested', created_at = ?, decided_at = NULL WHERE id = ?",
                  [date('Y-m-d H:i:s'), (int) $existing['id']]);
            return ['ok' => true, 'state' => 'interested', 'created' => true];
        }
        return ['ok' => true, 'state' => $state, 'created' => false];
    }

    try {
        q_run('INSERT INTO trip_connects (trip_id, from_user_id, to_user_id, state, created_at)
               VALUES (?,?,?,?,?)',
              [$tripId, $userId, (int) $trip['user_id'], 'interested', date('Y-m-d H:i:s')]);
    } catch (\PDOException $e) {
        // The unique index won a race with another tab. That is the right answer, not an error.
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        return ['ok' => true, 'state' => (string) (rmt_connect_get($tripId, $userId)['state'] ?? 'interested'), 'created' => false];
    }
    return ['ok' => true, 'state' => 'interested', 'created' => true];
}

/**
 * The trip owner answers. Only the owner, only their own trip, and only from a state that is still
 * open: an answer already given is not overwritten by a stale form.
 *
 * @return array{ok:bool, state:string, changed:bool}
 */
function rmt_connect_decide(int $ownerId, int $connectId, string $answer): array {
    $c = q_one('SELECT * FROM trip_connects WHERE id = ?', [$connectId]);
    if (!$c || (int) $c['to_user_id'] !== $ownerId) return ['ok' => false, 'state' => 'none', 'changed' => false];
    $want = $answer === 'accept' ? 'accepted' : 'declined';
    if ((string) $c['state'] !== 'interested') {
        return ['ok' => true, 'state' => (string) $c['state'], 'changed' => false];
    }
    q_run('UPDATE trip_connects SET state = ?, decided_at = ? WHERE id = ?',
          [$want, date('Y-m-d H:i:s'), $connectId]);
    return ['ok' => true, 'state' => $want, 'changed' => true];
}

/** The asker taking it back. Their own row only, and only while it is still a question. */
function rmt_connect_withdraw(int $userId, int $connectId): bool {
    $c = q_one('SELECT * FROM trip_connects WHERE id = ? AND from_user_id = ?', [$connectId, $userId]);
    if (!$c || (string) $c['state'] !== 'interested') return false;
    q_run("UPDATE trip_connects SET state = 'withdrawn', decided_at = ? WHERE id = ?",
          [date('Y-m-d H:i:s'), (int) $c['id']]);
    return true;
}

/**
 * Have these two agreed to meet on any trip? This is the one gate that opens messaging between
 * strangers, so it asks the narrow question rather than a convenient one: an accepted connect,
 * either direction.
 */
function rmt_connect_mutual(int $a, int $b): bool {
    return (bool) q_one("SELECT 1 FROM trip_connects
                          WHERE state = 'accepted'
                            AND ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?))
                          LIMIT 1", [$a, $b, $b, $a]);
}

/** How many people are waiting on an answer from this member, for the one number in the nav. */
function rmt_connect_pending_count(int $userId): int {
    return (int) (q_one("SELECT COUNT(*) n FROM trip_connects c JOIN users u ON u.id = c.from_user_id
                          WHERE c.to_user_id = ? AND c.state = 'interested' AND u.status = 'active'",
                        [$userId])['n'] ?? 0);
}
