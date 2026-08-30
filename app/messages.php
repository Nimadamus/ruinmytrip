<?php
declare(strict_types=1);

/**
 * Direct messages, plus the blocking it depends on.
 *
 * A conversation is identified by the unordered pair of participants, stored canonically as
 * (user_lo_id, user_hi_id) with user_lo_id < user_hi_id (enforced by a DB CHECK) so there is
 * exactly one conversation row per pair no matter who messaged first.
 *
 * Blocking existed only as an empty `blocks` table before this — safety.php and meetup_show.php
 * both already promised "report or block a user" as an escape hatch, but nothing read or wrote
 * the table. Messaging is the first place that promise needed to be real, so this file also
 * covers block/unblock. A block is symmetric for the purpose of messaging and following: it does
 * not matter who blocked whom, neither side can message or follow the other while it stands.
 */

const RMT_MESSAGE_BODY_MAX = 4000;

/** True if either user has blocked the other. Direction never matters for enforcement. */
function rmt_is_blocked(int $a, int $b): bool {
    if ($a === $b) return false;
    return (bool) q_one(
        'SELECT 1 FROM blocks WHERE (blocker_id=? AND blocked_id=?) OR (blocker_id=? AND blocked_id=?)',
        [$a, $b, $b, $a]
    );
}

/** Canonical [lo, hi] ordering for a conversation between two user ids. */
function rmt_conversation_pair(int $a, int $b): array {
    return $a < $b ? [$a, $b] : [$b, $a];
}

/** Existing conversation id between two users, or null if they have never messaged. */
function rmt_find_conversation(int $a, int $b): ?int {
    [$lo, $hi] = rmt_conversation_pair($a, $b);
    $row = q_one('SELECT id FROM conversations WHERE user_lo_id=? AND user_hi_id=?', [$lo, $hi]);
    return $row ? (int) $row['id'] : null;
}

/** Find-or-create, racing safely on the (user_lo_id, user_hi_id) unique constraint. */
function rmt_get_or_create_conversation(int $a, int $b): int {
    $existing = rmt_find_conversation($a, $b);
    if ($existing) return $existing;
    [$lo, $hi] = rmt_conversation_pair($a, $b);
    try {
        q_run('INSERT INTO conversations (user_lo_id, user_hi_id, created_at) VALUES (?,?,?)',
            [$lo, $hi, date('Y-m-d H:i:s')]);
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
    }
    return (int) rmt_find_conversation($a, $b);
}

/** Unread message count across all of a user's conversations, for the nav badge. */
function rmt_unread_message_count(int $uid): int {
    $row = q_one(
        "SELECT COUNT(*) c FROM messages m JOIN conversations c ON c.id=m.conversation_id
         WHERE (c.user_lo_id=? OR c.user_hi_id=?) AND m.sender_id<>? AND m.read_at IS NULL",
        [$uid, $uid, $uid]
    );
    return (int) ($row['c'] ?? 0);
}

/** GET /messages — inbox: one row per conversation, newest activity first. */
function messages_index(array $a): void {
    require_login(); $me = current_user();
    $uid = (int) $me['id'];
    $rows = q_all(
        "SELECT c.id conversation_id, c.last_message_at,
                u.username, u.id other_id, p.display_name, p.avatar_url,
                (SELECT body FROM messages WHERE conversation_id=c.id ORDER BY id DESC LIMIT 1) last_body,
                (SELECT COUNT(*) FROM messages WHERE conversation_id=c.id AND sender_id<>? AND read_at IS NULL) unread
         FROM conversations c
         JOIN users u ON u.id = (CASE WHEN c.user_lo_id=? THEN c.user_hi_id ELSE c.user_lo_id END)
         LEFT JOIN profiles p ON p.user_id = u.id
         WHERE (c.user_lo_id=? OR c.user_hi_id=?) AND u.status='active'
         ORDER BY c.last_message_at DESC NULLS LAST, c.id DESC",
        [$uid, $uid, $uid, $uid]
    );
    view('messages_index', compact('rows'), [
        'title' => 'Messages — RuinMyTrip',
        'description' => 'Your RuinMyTrip conversations.',
    ]);
}

/** GET /messages/{username} — a thread. Works even before any message has been sent. */
function messages_thread(array $a): void {
    require_login(); $me = current_user();
    $them = q_one("SELECT u.*, p.display_name, p.avatar_url FROM users u LEFT JOIN profiles p ON p.user_id=u.id
                   WHERE u.username=? AND u.status='active'", [$a['username']]);
    if (!$them) not_found();
    $themId = (int) $them['id'];
    $meId = (int) $me['id'];
    if ($themId === $meId) not_found();

    $blocked = rmt_is_blocked($meId, $themId);
    $convId = rmt_find_conversation($meId, $themId);
    $items = $convId ? q_all('SELECT * FROM messages WHERE conversation_id=? ORDER BY id', [$convId]) : [];

    if ($convId && !$blocked) {
        db()->prepare('UPDATE messages SET read_at=? WHERE conversation_id=? AND sender_id<>? AND read_at IS NULL')
            ->execute([date('Y-m-d H:i:s'), $convId, $meId]);
    }

    view('messages_thread', compact('them', 'items', 'blocked'), [
        'title' => 'Messages with @' . $them['username'] . ' — RuinMyTrip',
        'description' => 'Conversation with @' . $them['username'] . ' on RuinMyTrip.',
    ]);
}

/** POST /messages/{username}/send */
function messages_send(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $them = q_one("SELECT id, username FROM users WHERE username=? AND status='active'", [$a['username']]);
    if (!$them) not_found();
    $themId = (int) $them['id'];
    $meId = (int) $me['id'];
    $return = url('messages/' . $them['username']);
    if ($themId === $meId) redirect($return);

    if (rmt_is_blocked($meId, $themId)) {
        flash('You cannot message this traveler.');
        redirect($return);
    }

    $body = trim((string) input('body'));
    if ($body === '') redirect($return);
    if (mb_strlen($body) > RMT_MESSAGE_BODY_MAX) {
        flash('That message is too long (' . RMT_MESSAGE_BODY_MAX . ' characters max). Please shorten it and try again.');
        redirect($return);
    }
    if (!rmt_submit_ok('message_' . $themId, input('_submit'))) {
        flash('That message was already sent.');
        redirect($return);
    }
    // A DM inbox is a much higher-volume surface than a public comment thread, but still needs a
    // real ceiling — this is what stops it becoming a spam/harassment channel.
    if (!rmt_rate_ok('message', (string) $meId, 60, 3600)) {
        flash('You are sending messages very fast. Try again shortly.');
        redirect($return);
    }

    $convId = rmt_get_or_create_conversation($meId, $themId);
    $now = date('Y-m-d H:i:s');
    q_run('INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (?,?,?,?)',
        [$convId, $meId, $body, $now]);
    db()->prepare('UPDATE conversations SET last_message_at=? WHERE id=?')->execute([$now, $convId]);

    // One notification per unread thread, not one per message — a burst of messages should not
    // flood the recipient's notification list the way a burst of comments would elsewhere.
    $pending = q_one("SELECT 1 FROM notifications WHERE user_id=? AND type='message' AND target_type='conversation' AND target_id=? AND read_at IS NULL",
        [$themId, $convId]);
    if (!$pending) {
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
            [$themId, 'message', $meId, 'conversation', $convId, $now]);
        /* Same rule as the notification: the email goes out for a thread that is not already
           waiting unread, so a conversation is one email rather than one per line typed. */
        rmt_notify_email_direct($themId, 'A message on RuinMyTrip',
            '@' . $me['username'] . ' sent you a message.', '/messages/' . $me['username']);
    }

    redirect($return);
}

/** POST /block — target user_id. Blocking also removes any existing follow, both directions. */
function block_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $target = (int) input('user_id');
    $meId = (int) $me['id'];
    if (!$target || $target === $meId) redirect(rmt_return_to());
    if (!q_one("SELECT 1 FROM users WHERE id=? AND status='active'", [$target])) {
        flash('That traveler is no longer available.'); redirect(rmt_return_to());
    }
    try {
        q_run('INSERT INTO blocks (blocker_id, blocked_id) VALUES (?,?)', [$meId, $target]);
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
    }
    db()->prepare('DELETE FROM follows WHERE (follower_id=? AND followee_id=?) OR (follower_id=? AND followee_id=?)')
        ->execute([$meId, $target, $target, $meId]);
    flash('Blocked. They can no longer message or follow you, and you will not see each other in follow lists.');
    redirect(rmt_return_to());
}

/** POST /unblock — target user_id. */
function unblock_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $target = (int) input('user_id');
    if (!$target) redirect(rmt_return_to());
    db()->prepare('DELETE FROM blocks WHERE blocker_id=? AND blocked_id=?')->execute([(int) $me['id'], $target]);
    flash('Unblocked.');
    redirect(rmt_return_to());
}

/**
 * Unread notifications, for the bell in the nav.
 *
 * The bell had no count, so the only way to find out whether anything had happened was to open the
 * page and find out that nothing had. Every event the site now generates -- a reply, a trip match,
 * a meetup in your dates -- is worth exactly nothing if nobody knows it is waiting.
 */
function rmt_unread_notification_count(int $uid): int {
    if ($uid < 1) return 0;
    return (int) (q_one('SELECT COUNT(*) n FROM notifications WHERE user_id=? AND read_at IS NULL', [$uid])['n'] ?? 0);
}
