<?php
/**
 * Communities: a collection other people can join.
 *
 * The site already had every social primitive except a container. You could follow a person, save
 * a place and write a review, but there was nowhere for a group of travelers to gather that
 * belonged to one of them rather than to us. A collection was the closest thing already in the
 * database: it has an owner, a title, a slug, a public URL and items. What it lacked was a second
 * person. That is all a community is here, and building it this way means the ten thousand lines
 * that already render, moderate, tag, search and index a collection keep working untouched.
 *
 * Three join policies, and the default is the old behaviour, so nothing that exists changes:
 *
 *   closed  a personal list. Only the owner is in it.
 *   invite  joinable by someone holding the current invite link.
 *   open    joinable by any signed-in traveler.
 *
 * Whether a member may ADD to the community is a separate flag on purpose. Letting somebody in is
 * not the same decision as handing them the pen, and a founder needs to be able to make the first
 * decision without making the second.
 */
declare(strict_types=1);

const RMT_JOIN_POLICIES = ['closed', 'invite', 'open'];

/** The thresholds below which a community is not shown to strangers. See rmt_community_is_discoverable(). */
const RMT_COMMUNITY_MIN_ITEMS   = 3;
const RMT_COMMUNITY_MIN_MEMBERS = 2;

function rmt_is_community(array $c): bool {
    return in_array($c['join_policy'] ?? 'closed', ['invite', 'open'], true);
}

/** The membership row, whatever its status, or null. Removed members return a row: that is the point. */
function rmt_community_membership(int $collectionId, ?int $userId): ?array {
    if (!$userId) return null;
    return q_one('SELECT * FROM collection_members WHERE collection_id=? AND user_id=?', [$collectionId, $userId]);
}

/** 'owner', 'member', or null for everybody else. A removed member is nobody. */
function rmt_community_role(int $collectionId, ?int $userId): ?string {
    $m = rmt_community_membership($collectionId, $userId);
    if (!$m || $m['status'] !== 'active') return null;
    return (string) $m['role'];
}

function rmt_community_is_member(int $collectionId, ?int $userId): bool {
    return rmt_community_role($collectionId, $userId) !== null;
}

/** @return array<int,array<string,mixed>> Active members, owner first, then longest standing. */
function rmt_community_members(int $collectionId): array {
    return q_all("SELECT m.*, u.username, p.avatar_url
                    FROM collection_members m
                    JOIN users u ON u.id = m.user_id
               LEFT JOIN profiles p ON p.user_id = m.user_id
                   WHERE m.collection_id = ? AND m.status = 'active'
                ORDER BY CASE m.role WHEN 'owner' THEN 0 ELSE 1 END, m.id", [$collectionId]);
}

function rmt_community_member_count(int $collectionId): int {
    return (int) q_one("SELECT COUNT(*) n FROM collection_members WHERE collection_id=? AND status='active'",
                       [$collectionId])['n'];
}

/** Communities this user belongs to, for their profile and for "where do I post this". */
function rmt_community_memberships(int $userId): array {
    return q_all("SELECT c.*, m.role
                    FROM collection_members m
                    JOIN collections c ON c.id = m.collection_id
                   WHERE m.user_id = ? AND m.status = 'active' AND c.status = 'published'
                ORDER BY c.title", [$userId]);
}

/* ------------------------------------------------------------------ permissions */

/** Only the owner edits the community itself: title, policy, who is in it. */
function rmt_community_can_manage(array $c, ?array $user): bool {
    return $user !== null && (int) $c['user_id'] === (int) $user['id'];
}

/**
 * Who may put something into the community. The owner always may. A member may when the owner has
 * said so. Nobody else ever may, which is what stops an open community from being a spam target.
 */
function rmt_community_can_add(array $c, ?array $user): bool {
    if (!$user) return false;
    if (rmt_community_can_manage($c, $user)) return true;
    if (!rmt_is_community($c) || (int) ($c['members_can_add'] ?? 0) !== 1) return false;
    return rmt_community_role((int) $c['id'], (int) $user['id']) === 'member';
}

/** Whether this user could join right now, and if not, the honest reason. */
function rmt_community_join_state(array $c, ?array $user, ?string $inviteToken = null): string {
    if (!rmt_is_community($c))                     return 'not_a_community';
    if ($c['status'] !== 'published')              return 'unavailable';
    if (!$user)                                    return 'sign_in_required';
    $m = rmt_community_membership((int) $c['id'], (int) $user['id']);
    if ($m && $m['status'] === 'active')           return 'already_member';
    // Removal is a decision the founder made. An open door does not undo it.
    if ($m && $m['status'] === 'removed')          return 'removed';
    if ($c['join_policy'] === 'open')              return 'can_join';
    return rmt_community_invite_is_valid((int) $c['id'], $inviteToken) ? 'can_join' : 'invite_required';
}

/* ------------------------------------------------------------------ membership */

/**
 * Join. Returns the new state, and is safe to call twice: a double submit finds an active row and
 * says so rather than writing a second one, which the unique index would refuse anyway.
 */
function rmt_community_join(array $c, array $user, ?string $inviteToken = null): string {
    $state = rmt_community_join_state($c, $user, $inviteToken);
    if ($state !== 'can_join') return $state;
    q_run('INSERT INTO collection_members (collection_id, user_id, role, status, joined_at) VALUES (?,?,?,?,?)',
          [(int) $c['id'], (int) $user['id'], 'member', 'active', date('Y-m-d H:i:s')]);
    return 'joined';
}

/**
 * Leave. The owner cannot: a community without its founder has nobody who can moderate it, and
 * silently promoting somebody else would be a surprising thing to do to both of them.
 */
function rmt_community_leave(array $c, array $user): string {
    $role = rmt_community_role((int) $c['id'], (int) $user['id']);
    if ($role === null)  return 'not_a_member';
    if ($role === 'owner') return 'owner_cannot_leave';
    q_run('DELETE FROM collection_members WHERE collection_id=? AND user_id=?', [(int) $c['id'], (int) $user['id']]);
    return 'left';
}

/**
 * Remove somebody, which is a moderation act and therefore sticky: the row stays with status
 * 'removed' so the same person cannot walk back in through an open door. What they contributed
 * stays unless the owner removes it separately -- deleting a person's writing because they left
 * is not a decision to make on their behalf.
 */
function rmt_community_remove_member(array $c, array $actor, int $targetUserId): string {
    if (!rmt_community_can_manage($c, $actor))      return 'forbidden';
    if ($targetUserId === (int) $actor['id'])       return 'owner_cannot_leave';
    $m = rmt_community_membership((int) $c['id'], $targetUserId);
    if (!$m || $m['status'] !== 'active')           return 'not_a_member';
    q_run("UPDATE collection_members SET status='removed', removed_at=? WHERE id=?", [date('Y-m-d H:i:s'), (int) $m['id']]);
    return 'removed';
}

/** Let somebody back in after a removal. The founder changed their mind; the door reopens. */
function rmt_community_reinstate_member(array $c, array $actor, int $targetUserId): string {
    if (!rmt_community_can_manage($c, $actor)) return 'forbidden';
    $m = rmt_community_membership((int) $c['id'], $targetUserId);
    if (!$m || $m['status'] !== 'removed')     return 'not_removed';
    q_run("UPDATE collection_members SET status='active', removed_at=NULL WHERE id=?", [(int) $m['id']]);
    return 'reinstated';
}

/* ---------------------------------------------------------------------- invites */

/** The live invite token, or null when the owner has never made one or has revoked it. */
function rmt_community_invite(int $collectionId): ?array {
    return q_one('SELECT * FROM collection_invites WHERE collection_id=? AND revoked_at IS NULL
                  ORDER BY id DESC', [$collectionId]);
}

function rmt_community_invite_is_valid(int $collectionId, ?string $token): bool {
    if (!$token) return false;
    $row = q_one('SELECT id FROM collection_invites WHERE collection_id=? AND token=? AND revoked_at IS NULL',
                 [$collectionId, $token]);
    return $row !== null;
}

/** Find the community an invite link points at, without needing to know the community first. */
function rmt_community_by_invite(string $token): ?array {
    return q_one("SELECT c.* FROM collection_invites i JOIN collections c ON c.id = i.collection_id
                   WHERE i.token = ? AND i.revoked_at IS NULL AND c.status = 'published'", [$token]);
}

/**
 * Issue a link, revoking whatever came before it. One live link at a time is the whole point: a
 * founder who thinks a link has spread too far needs "make the old one stop working" to be the
 * same action as "give me a new one", not a second thing they have to remember.
 */
function rmt_community_rotate_invite(array $c, array $actor): ?string {
    if (!rmt_community_can_manage($c, $actor)) return null;
    rmt_community_revoke_invite($c, $actor);
    $token = bin2hex(random_bytes(16));
    q_run('INSERT INTO collection_invites (collection_id, token, created_by, created_at) VALUES (?,?,?,?)',
          [(int) $c['id'], $token, (int) $actor['id'], date('Y-m-d H:i:s')]);
    return $token;
}

function rmt_community_revoke_invite(array $c, array $actor): bool {
    if (!rmt_community_can_manage($c, $actor)) return false;
    q_run('UPDATE collection_invites SET revoked_at=? WHERE collection_id=? AND revoked_at IS NULL',
          [date('Y-m-d H:i:s'), (int) $c['id']]);
    return true;
}

/* ------------------------------------------------------------------- the items */

/** Pin an item to the top. Founder tool: the thing a newcomer should read first. */
function rmt_community_set_pinned(array $c, array $actor, int $itemId, bool $pinned): bool {
    if (!rmt_community_can_manage($c, $actor)) return false;
    q_run('UPDATE collection_items SET pinned=? WHERE id=? AND collection_id=?',
          [$pinned ? 1 : 0, $itemId, (int) $c['id']]);
    return true;
}

/**
 * Who may take an item out: the founder, always, and the member who put it there. A member can
 * retract their own contribution; they cannot edit the room.
 */
function rmt_community_can_remove_item(array $c, ?array $user, array $item): bool {
    if (!$user) return false;
    if (rmt_community_can_manage($c, $user)) return true;
    return $item['added_by'] !== null && (int) $item['added_by'] === (int) $user['id'];
}

/* --------------------------------------------------------------- discoverability */

/**
 * Whether a community is ready to be shown to somebody who did not come looking for it.
 *
 * A group with one member and nothing in it kills itself: the first stranger who arrives sees an
 * empty room, leaves, and never comes back. So a community stays reachable by its URL from the
 * moment it exists, and stays out of the index, the browse page and the sitemap until it has
 * something to show and somebody other than its founder in it. This is the same judgement the SEO
 * pilot already makes about category pages, applied to people instead of places.
 */
function rmt_community_is_discoverable(array $c, ?int $itemCount = null, ?int $memberCount = null): bool {
    if (($c['status'] ?? '') !== 'published') return false;
    if (!rmt_is_community($c)) return false;
    $cid = (int) $c['id'];
    $items = $itemCount ?? (int) q_one('SELECT COUNT(*) n FROM collection_items WHERE collection_id=?', [$cid])['n'];
    $members = $memberCount ?? rmt_community_member_count($cid);
    return $items >= RMT_COMMUNITY_MIN_ITEMS && $members >= RMT_COMMUNITY_MIN_MEMBERS;
}

/**
 * Communities a stranger may browse.
 *
 * Ordered by conversation, then by size. A room somebody spoke in yesterday is a better answer to
 * 'which of these should I join' than a bigger room that has been silent for a month, and rooms
 * with no talk at all fall to the bottom without disappearing.
 */
function rmt_community_browse(int $limit = 30): array {
    return q_all("SELECT c.*, u.username owner_username,
                         (SELECT COUNT(*) FROM collection_members m
                           WHERE m.collection_id=c.id AND m.status='active') member_count,
                         (SELECT COUNT(*) FROM collection_items i WHERE i.collection_id=c.id) item_count,
                         (SELECT COUNT(*) FROM posts p
                           WHERE p.collection_id=c.id AND p.status='published') post_count
                    FROM collections c
                    JOIN users u ON u.id = c.user_id
                   WHERE c.status='published' AND c.join_policy IN ('open','invite')
                     AND (SELECT COUNT(*) FROM collection_members m
                           WHERE m.collection_id=c.id AND m.status='active') >= " . RMT_COMMUNITY_MIN_MEMBERS . "
                     AND (SELECT COUNT(*) FROM collection_items i WHERE i.collection_id=c.id) >= " . RMT_COMMUNITY_MIN_ITEMS . "
                ORDER BY (SELECT COUNT(*) FROM posts p
                           WHERE p.collection_id=c.id AND p.status='published') > 0 DESC,
                         (SELECT MAX(p.created_at) FROM posts p
                           WHERE p.collection_id=c.id AND p.status='published') DESC,
                         member_count DESC, c.id DESC
                   LIMIT " . (int) $limit);
}
