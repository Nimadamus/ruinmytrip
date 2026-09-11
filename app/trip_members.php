<?php
/**
 * Collaborative trips: a couple, or four friends, using one trip as their shared home.
 *
 * One owner, held in `trips.user_id`, and any number of editors in `trip_members`. The owner row is
 * deliberately not duplicated into this table: one source of ownership cannot disagree with itself.
 *
 * What an editor may do is the whole design. They can add to the trip: plans, photographs, saved
 * places, the story. They cannot do anything that destroys or exposes it: no deleting the trip, no
 * changing who can see it, no removing anybody else, no handing it to somebody new. A person you
 * invited to help plan a holiday should not be one misclick from publishing it to the world.
 *
 * Privacy: a member sees the trip whatever its visibility, which is the point of inviting them, and
 * that is expressed once, inside rmt_plan_visibility_sql(), so every query on the site picks it up
 * rather than each one remembering.
 */
declare(strict_types=1);

/** What a member may be. Owner is implied by trips.user_id and never stored here. */
const RMT_TRIP_MEMBER_ROLES = ['editor' => 'Can add plans, places and photos'];

/** invited: asked, not answered. active: in. declined / left: kept, so neither can be re-sent silently. */
const RMT_TRIP_MEMBER_STATES = ['invited', 'active', 'declined', 'left'];

/** How many people one trip may have on it. A trip is a holiday, not a mailing list. */
const RMT_TRIP_MEMBER_MAX = 12;

/** How many invitations one member may send in a day, across all their trips. */
const RMT_TRIP_INVITES_PER_DAY = 20;

/**
 * The viewer's standing on a trip: 'owner', 'editor', or null.
 *
 * Null means no standing, which is not the same as no access: a public trip is readable by anybody.
 */
function rmt_trip_role(?array $trip, ?array $viewer): ?string {
    if (!$trip || !$viewer) return null;
    if ((int) $trip['user_id'] === (int) $viewer['id']) return 'owner';
    $r = q_one("SELECT role FROM trip_members WHERE trip_id = ? AND user_id = ? AND state = 'active'",
               [(int) $trip['id'], (int) $viewer['id']]);
    return $r ? (string) $r['role'] : null;
}

/** May this person add plans, photographs and places to this trip? */
function rmt_trip_can_edit(?array $trip, ?array $viewer): bool {
    return in_array(rmt_trip_role($trip, $viewer), ['owner', 'editor'], true);
}

/**
 * May this person change who the trip is for, delete it, or change who is on it?
 *
 * Only the owner, always. An editor who could flip a private trip to public could publish somebody
 * else's holiday, and an editor who could remove members could remove the owner.
 */
function rmt_trip_can_admin(?array $trip, ?array $viewer): bool {
    return rmt_trip_role($trip, $viewer) === 'owner';
}

/**
 * Everybody on a trip, owner first, with the profile bits a list needs.
 *
 * @param string $state 'active' for the people on it, 'invited' for the ones who have not answered
 * @return list<array<string,mixed>>
 */
function rmt_trip_members(int $tripId, string $state = 'active'): array {
    if ($tripId < 1) return [];
    $trip = q_one('SELECT user_id FROM trips WHERE id = ?', [$tripId]);
    if (!$trip) return [];

    $rows = [];
    if ($state === 'active') {
        $owner = q_one("SELECT u.id user_id, u.username, p.display_name, p.avatar_url
                          FROM users u LEFT JOIN profiles p ON p.user_id = u.id
                         WHERE u.id = ? AND u.status = 'active'", [(int) $trip['user_id']]);
        if ($owner) $rows[] = $owner + ['role' => 'owner', 'state' => 'active'];
    }
    foreach (q_all("SELECT m.user_id, m.role, m.state, m.created_at, u.username,
                           p.display_name, p.avatar_url
                      FROM trip_members m
                      JOIN users u ON u.id = m.user_id AND u.status = 'active'
                 LEFT JOIN profiles p ON p.user_id = m.user_id
                     WHERE m.trip_id = ? AND m.state = ?
                  ORDER BY m.created_at, m.user_id", [$tripId, $state]) as $r) {
        $rows[] = $r;
    }
    return $rows;
}

/** How many people are on a trip, counting its owner. Used for the cap and for the page. */
function rmt_trip_member_count(int $tripId): int {
    $n = (int) (q_one("SELECT COUNT(*) c FROM trip_members WHERE trip_id = ? AND state IN ('active','invited')",
                      [$tripId])['c'] ?? 0);
    return $n + 1;
}

/** Trips somebody has been invited to and has not answered. */
function rmt_trip_invitations(int $uid): array {
    if ($uid < 1) return [];
    return q_all("SELECT m.trip_id, m.created_at, t.title, t.slug, t.date_from, t.date_to,
                         u.username owner_username, d.name dest_name
                    FROM trip_members m
                    JOIN trips t ON t.id = m.trip_id AND t.status = 'published'
                    JOIN users u ON u.id = t.user_id AND u.status = 'active'
               LEFT JOIN destinations d ON d.id = t.destination_id
                   WHERE m.user_id = ? AND m.state = 'invited'
                ORDER BY m.created_at DESC LIMIT 20", [$uid]);
}

/**
 * Invite somebody to help plan a trip.
 *
 * Refused when: the trip is not yours, the person is you, they are already on it or have been
 * asked, either of you has blocked the other, the trip is full, or you are inviting the whole site.
 * A block is checked in both directions, as everywhere else.
 *
 * @return array{ok:bool,error?:string}
 */
function rmt_trip_invite(array $trip, array $inviter, string $username): array {
    if (!rmt_trip_can_admin($trip, $inviter)) {
        return ['ok' => false, 'error' => 'Only the traveler whose trip it is can invite people.'];
    }
    $username = ltrim(trim($username), '@');
    if ($username === '') return ['ok' => false, 'error' => 'Who would you like to invite?'];

    $them = q_one("SELECT id, username FROM users WHERE LOWER(username) = ? AND status = 'active'",
                  [mb_strtolower($username)]);
    if (!$them) return ['ok' => false, 'error' => 'No traveler with that username.'];
    $themId = (int) $them['id'];
    if ($themId === (int) $inviter['id']) return ['ok' => false, 'error' => 'It is already your trip.'];

    /* Blocks, both directions, queried here rather than through a helper behind function_exists().
       A security check that silently does nothing when a module is not loaded is not a check, and
       this file is deliberately loadable on its own. Said as a plain no, never as "they blocked
       you", which would report the block back to the person it was against. */
    if (q_one('SELECT 1 x FROM blocks WHERE (blocker_id = ? AND blocked_id = ?)
                                          OR (blocker_id = ? AND blocked_id = ?)',
              [(int) $inviter['id'], $themId, $themId, (int) $inviter['id']])) {
        return ['ok' => false, 'error' => 'That traveler cannot be invited.'];
    }
    if (rmt_trip_member_count((int) $trip['id']) >= RMT_TRIP_MEMBER_MAX) {
        return ['ok' => false, 'error' => 'A trip can have ' . RMT_TRIP_MEMBER_MAX . ' people on it.'];
    }
    if (function_exists('rmt_rate_ok')
        && !rmt_rate_ok('trip_invite', (string) $inviter['id'], RMT_TRIP_INVITES_PER_DAY, 86400)) {
        return ['ok' => false, 'error' => 'That is a lot of invitations for one day. Try again tomorrow.'];
    }

    $existing = q_one('SELECT state FROM trip_members WHERE trip_id = ? AND user_id = ?',
                      [(int) $trip['id'], $themId]);
    if ($existing) {
        $state = (string) $existing['state'];
        if ($state === 'active')  return ['ok' => false, 'error' => 'They are already on this trip.'];
        if ($state === 'invited') return ['ok' => false, 'error' => 'They have already been asked.'];
        /* Declined or left. Asking once more is fair; asking every hour is not, so the row is
           reused and the rate limit above is what stops a loop. */
        q_run("UPDATE trip_members SET state = 'invited', invited_by = ?, created_at = ?, decided_at = NULL
                WHERE trip_id = ? AND user_id = ?",
              [(int) $inviter['id'], date('Y-m-d H:i:s'), (int) $trip['id'], $themId]);
    } else {
        q_run('INSERT INTO trip_members (trip_id, user_id, role, state, invited_by, created_at)
               VALUES (?,?,?,?,?,?)',
              [(int) $trip['id'], $themId, 'editor', 'invited', (int) $inviter['id'], date('Y-m-d H:i:s')]);
    }

    q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at)
           VALUES (?,?,?,?,?,?)',
          [$themId, 'trip_invite', (int) $inviter['id'], 'trip', (int) $trip['id'], date('Y-m-d H:i:s')]);

    if (function_exists('rmt_notify_email_direct')) {
        rmt_notify_email_direct($themId, 'You were invited to a trip',
            '@' . (string) $inviter['username'] . ' asked you to help plan ' . (string) $trip['title'] . '.',
            '/trip/' . (int) $trip['id'] . '/' . (string) $trip['slug']);
    }
    return ['ok' => true];
}

/** Answer an invitation. Anything other than yes is a no, and the row is kept either way. */
function rmt_trip_invite_answer(int $tripId, int $uid, bool $yes): bool {
    $row = q_one("SELECT state FROM trip_members WHERE trip_id = ? AND user_id = ? AND state = 'invited'",
                 [$tripId, $uid]);
    if (!$row) return false;
    q_run('UPDATE trip_members SET state = ?, decided_at = ? WHERE trip_id = ? AND user_id = ?',
          [$yes ? 'active' : 'declined', date('Y-m-d H:i:s'), $tripId, $uid]);

    $trip = q_one('SELECT user_id FROM trips WHERE id = ?', [$tripId]);
    if ($yes && $trip) {
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at)
               VALUES (?,?,?,?,?,?)',
              [(int) $trip['user_id'], 'trip_joined', $uid, 'trip', $tripId, date('Y-m-d H:i:s')]);
    }
    return true;
}

/**
 * Take somebody off a trip.
 *
 * Two ways in: the owner removing a member, or a member leaving. The owner can never be removed by
 * anybody, including themselves, because a trip with no owner has nobody who can delete it.
 */
function rmt_trip_member_remove(array $trip, array $actor, int $targetId): array {
    $isSelf = (int) $actor['id'] === $targetId;
    if (!$isSelf && !rmt_trip_can_admin($trip, $actor)) {
        return ['ok' => false, 'error' => 'Only the traveler whose trip it is can remove somebody.'];
    }
    if ($targetId === (int) $trip['user_id']) {
        return ['ok' => false, 'error' => 'A trip keeps the traveler whose trip it is.'];
    }
    q_run('UPDATE trip_members SET state = ?, decided_at = ? WHERE trip_id = ? AND user_id = ?',
          [$isSelf ? 'left' : 'declined', date('Y-m-d H:i:s'), (int) $trip['id'], $targetId]);
    if (!$isSelf) {
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at)
               VALUES (?,?,?,?,?,?)',
              [$targetId, 'trip_member_removed', (int) $actor['id'], 'trip', (int) $trip['id'],
               date('Y-m-d H:i:s')]);
    }
    return ['ok' => true];
}
