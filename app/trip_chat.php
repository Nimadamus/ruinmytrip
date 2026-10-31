<?php
declare(strict_types=1);

/**
 * Trip chat: one shared thread per trip, for the people on it.
 *
 * A trip three people are planning had one page they could all edit and nowhere to talk, so the
 * talk happened in pairs of private messages and somebody was always missing from it. The rule for
 * who is in the room is the trip's own rule, rmt_trip_role(): the owner and every active member.
 * Leaving the trip, or being taken off it, leaves the room with it. Nobody else ever sees a line,
 * whatever the trip's visibility, because a public itinerary is not a public conversation.
 *
 * Blocks hold here as everywhere: lines from somebody you blocked, or who blocked you, are not
 * shown to you. They stay on the trip for everybody else, since the trip is not yours alone.
 */

const RMT_TRIP_CHAT_BODY_MAX = 2000;
const RMT_TRIP_CHAT_PAGE = 200;

/** May this person read and write in this trip's chat? */
function rmt_trip_chat_allowed(?array $trip, ?array $viewer): bool {
    if (!$trip || !$viewer) return false;
    if ((string) ($trip['status'] ?? '') !== 'published') return false;
    return rmt_trip_role($trip, $viewer) !== null;
}

/** A trip's chat is offered once somebody other than the owner is on it. */
function rmt_trip_chat_open(int $tripId): bool {
    return (bool) q_one("SELECT 1 FROM trip_members WHERE trip_id = ? AND state = 'active'", [$tripId]);
}

/**
 * The lines a viewer may see, oldest first, after a given id.
 *
 * @return list<array<string,mixed>>
 */
function rmt_trip_chat_lines(int $tripId, int $viewerId, int $afterId = 0): array {
    $rows = q_all("SELECT * FROM (
                     SELECT m.id, m.user_id, m.body, m.created_at, u.username, u.status user_status,
                            p.display_name, p.avatar_url
                       FROM trip_messages m
                       JOIN users u ON u.id = m.user_id
                  LEFT JOIN profiles p ON p.user_id = m.user_id
                      WHERE m.trip_id = ? AND m.id > ?
                   ORDER BY m.id DESC LIMIT " . RMT_TRIP_CHAT_PAGE . ") x ORDER BY id",
                  [$tripId, $afterId]);
    return rmt_without_blocked($rows, $viewerId);
}

/** Remember the newest line this member has seen. Never moves backwards. */
function rmt_trip_chat_mark_read(int $tripId, int $userId, int $lastId): void {
    if ($lastId < 1) return;
    $have = q_one('SELECT last_read_id FROM trip_chat_reads WHERE trip_id = ? AND user_id = ?', [$tripId, $userId]);
    if ($have === null) {
        q_run('INSERT INTO trip_chat_reads (trip_id, user_id, last_read_id) VALUES (?,?,?)', [$tripId, $userId, $lastId]);
    } elseif ((int) $have['last_read_id'] < $lastId) {
        q_run('UPDATE trip_chat_reads SET last_read_id = ? WHERE trip_id = ? AND user_id = ?', [$lastId, $tripId, $userId]);
    }
}

/** Lines from other people this member has not seen yet, on one trip. */
function rmt_trip_chat_unread(int $tripId, int $userId): int {
    return (int) (q_one("SELECT COUNT(*) n FROM trip_messages m
                          WHERE m.trip_id = ? AND m.user_id <> ?
                            AND m.id > COALESCE((SELECT r.last_read_id FROM trip_chat_reads r
                                                  WHERE r.trip_id = m.trip_id AND r.user_id = ?), 0)",
                         [$tripId, $userId, $userId])['n'] ?? 0);
}

/**
 * Every trip chat this member is in that has at least one line, newest first, for the inbox.
 *
 * @return list<array<string,mixed>>
 */
function rmt_trip_chats_for(int $userId): array {
    $rows = q_all("SELECT t.id, t.slug, t.title, d.name dest_name,
                          (SELECT MAX(m.id) FROM trip_messages m WHERE m.trip_id = t.id) last_id,
                          (SELECT MAX(m.created_at) FROM trip_messages m WHERE m.trip_id = t.id) last_at
                     FROM trips t
                LEFT JOIN destinations d ON d.id = t.destination_id
                    WHERE t.status = 'published'
                      AND (t.user_id = ? OR EXISTS (SELECT 1 FROM trip_members tm
                                                     WHERE tm.trip_id = t.id AND tm.user_id = ? AND tm.state = 'active'))
                      AND EXISTS (SELECT 1 FROM trip_messages m WHERE m.trip_id = t.id)
                 ORDER BY last_id DESC LIMIT 30", [$userId, $userId]);
    foreach ($rows as &$r) {
        $last = q_one('SELECT m.body, u.username, u.status FROM trip_messages m JOIN users u ON u.id = m.user_id
                        WHERE m.id = ?', [(int) $r['last_id']]);
        $r['last_body'] = $last ? (string) $last['body'] : '';
        $r['last_username'] = $last && $last['status'] === 'active' ? (string) $last['username'] : '';
        $r['unread'] = rmt_trip_chat_unread((int) $r['id'], $userId);
    }
    unset($r);
    return $rows;
}

/** Everybody in the room except one person, as user ids. */
function rmt_trip_chat_audience(array $trip, int $exceptId): array {
    $ids = [(int) $trip['user_id']];
    foreach (q_all("SELECT user_id FROM trip_members WHERE trip_id = ? AND state = 'active'", [(int) $trip['id']]) as $r) {
        $ids[] = (int) $r['user_id'];
    }
    return array_values(array_filter(array_unique($ids), static fn(int $id) => $id !== $exceptId));
}

/**
 * Write one line. Decides everything itself, so the controller and any later caller share one set
 * of rules.
 *
 * @return array{ok:bool, error?:string, id?:int}
 */
function rmt_trip_chat_post(array $trip, array $me, string $body): array {
    if (!rmt_trip_chat_allowed($trip, $me)) return ['ok' => false, 'error' => 'Only the travelers on this trip can talk here.'];
    $body = trim($body);
    if ($body === '') return ['ok' => false, 'error' => 'Write something first.'];
    if (mb_strlen($body) > RMT_TRIP_CHAT_BODY_MAX) {
        return ['ok' => false, 'error' => 'That is too long (' . RMT_TRIP_CHAT_BODY_MAX . ' characters max).'];
    }
    $meId = (int) $me['id'];
    if (!rmt_rate_ok('trip_chat', (string) $meId, 60, 3600)) {
        return ['ok' => false, 'error' => 'You are sending messages very fast. Try again shortly.'];
    }
    $tripId = (int) $trip['id'];
    $now = date('Y-m-d H:i:s');
    q_run('INSERT INTO trip_messages (trip_id, user_id, body, created_at) VALUES (?,?,?,?)', [$tripId, $meId, $body, $now]);
    $id = (int) (q_one('SELECT MAX(id) id FROM trip_messages WHERE trip_id = ? AND user_id = ?', [$tripId, $meId])['id'] ?? 0);
    rmt_trip_chat_mark_read($tripId, $meId, $id);
    if (function_exists('rmt_track')) rmt_track('trip_chat_sent');

    /* One notification per member per unread stretch, the same rule as private messages: a
       morning of planning is one line in somebody's notifications, not forty. */
    $where = (string) ($trip['title'] ?? 'your trip');
    foreach (rmt_trip_chat_audience($trip, $meId) as $uid) {
        if (rmt_is_blocked($meId, $uid)) continue;
        $pending = q_one("SELECT 1 FROM notifications WHERE user_id = ? AND type = 'trip_chat'
                            AND target_type = 'trip' AND target_id = ? AND read_at IS NULL", [$uid, $tripId]);
        if ($pending) continue;
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
              [$uid, 'trip_chat', $meId, 'trip', $tripId, $now]);
        rmt_notify_email_direct($uid, 'New in your trip chat',
            '@' . $me['username'] . ' wrote in the chat for ' . $where . '.',
            '/trip/' . $tripId . '/chat', 'you are planning this trip with other travelers');
    }
    return ['ok' => true, 'id' => $id];
}

function rmt_trip_chat_load(array $a): array {
    $t = q_one("SELECT t.*, d.name dest_name FROM trips t LEFT JOIN destinations d ON d.id = t.destination_id
                 WHERE t.id = ? AND t.status = 'published'", [(int) $a['id']]);
    if (!$t) not_found();
    return $t;
}

/** GET /trip/{id}/chat, and the same with ?after=N&format=json for the page's own refresh. */
function trip_chat(array $a): void {
    require_login();
    $me = current_user();
    $t = rmt_trip_chat_load($a);
    if (!rmt_trip_chat_allowed($t, $me)) forbidden('Only the travelers on this trip can see its chat.');
    $tripId = (int) $t['id'];
    $meId = (int) $me['id'];

    if ((string) input('format') === 'json') {
        $lines = rmt_trip_chat_lines($tripId, $meId, max(0, (int) input('after')));
        if ($lines) rmt_trip_chat_mark_read($tripId, $meId, (int) end($lines)['id']);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['lines' => array_map(static fn(array $m) => [
            'id'   => (int) $m['id'],
            'mine' => (int) $m['user_id'] === $meId,
            'who'  => (string) $m['user_status'] === 'active' ? (string) ($m['display_name'] ?: $m['username']) : 'Deleted member',
            'html' => rmt_linkify_mentions(e((string) $m['body'])),
            'when' => ago((string) $m['created_at']),
        ], $lines)], JSON_UNESCAPED_SLASHES);
        return;
    }

    $lines = rmt_trip_chat_lines($tripId, $meId);
    if ($lines) rmt_trip_chat_mark_read($tripId, $meId, (int) end($lines)['id']);
    q_run("UPDATE notifications SET read_at = ? WHERE user_id = ? AND type = 'trip_chat'
              AND target_type = 'trip' AND target_id = ? AND read_at IS NULL", [date('Y-m-d H:i:s'), $meId, $tripId]);
    $members = rmt_trip_members($tripId);
    view('trip_chat', ['t' => $t, 'lines' => $lines, 'members' => $members], [
        'title' => 'Trip chat: ' . (string) $t['title'] . ' | RuinMyTrip',
        'description' => 'The private chat for the travelers on this trip.',
        'app_shell' => true,
        'noindex' => true,
    ]);
}

/** POST /trip/{id}/chat */
function trip_chat_send(array $a): void {
    require_verified_email(); csrf_check();
    $me = current_user();
    $t = rmt_trip_chat_load($a);
    if (!rmt_trip_chat_allowed($t, $me)) forbidden('Only the travelers on this trip can talk here.');
    $back = '/trip/' . (int) $t['id'] . '/chat';
    if (!rmt_submit_ok('trip_chat_' . (int) $t['id'], input('_submit'))) redirect($back);
    $res = rmt_trip_chat_post($t, $me, (string) input('body'));
    if (!$res['ok']) flash((string) $res['error']);
    redirect($back . (!empty($res['id']) ? '#m' . (int) $res['id'] : ''));
}
