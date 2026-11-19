<?php
declare(strict_types=1);

/**
 * Sailing chat (2026-11-19): one room per ship and departure day, for the members on that sailing.
 *
 * The sailing page shows who is on a ship; until now the only way to talk was a connect request to
 * each person, one pair at a time. Once two members have posted the same sailing, they share a room.
 * Who is in the room is the sailing page's own rule, rmt_sailing_members(): a member whose open or
 * filled cruise post is this ship, leaving this day or a day either side. Closing or deleting the
 * post leaves the room with it. Nobody else ever reads a line.
 *
 * Blocks hold as everywhere: lines between two people who blocked each other are not shown to them.
 */

const RMT_SAILING_CHAT_BODY_MAX = 2000;
const RMT_SAILING_CHAT_PAGE = 200;
const RMT_SAILING_CHAT_MIN = 2;   // members on the sailing before the room opens

/** User ids on this sailing (ship key + day). Unfiltered by blocks: membership is not a view. */
function rmt_sailing_chat_member_ids(string $shipKey, string $date): array {
    return array_values(array_unique(array_map('intval', array_column(rmt_sailing_members($shipKey, $date), 'user_id'))));
}

/** Is the room open (enough members), and may this person use it? */
function rmt_sailing_chat_open(string $shipKey, string $date): bool {
    return count(rmt_sailing_chat_member_ids($shipKey, $date)) >= RMT_SAILING_CHAT_MIN;
}

function rmt_sailing_chat_allowed(string $shipKey, string $date, ?array $viewer): bool {
    if (!$viewer || $shipKey === '' || !rmt_sailing_valid_date($date)) return false;
    $ids = rmt_sailing_chat_member_ids($shipKey, $date);
    return count($ids) >= RMT_SAILING_CHAT_MIN && in_array((int) $viewer['id'], $ids, true);
}

/** The room row, made the first time somebody writes. */
function rmt_sailing_room(string $shipKey, string $date, bool $create = false, string $ship = ''): ?array {
    $r = q_one('SELECT * FROM sailing_rooms WHERE ship_key = ? AND sail_date = ?', [$shipKey, $date]);
    if ($r || !$create) return $r;
    q_run('INSERT INTO sailing_rooms (ship_key, sail_date, ship, created_at) VALUES (?,?,?,?)',
          [$shipKey, $date, $ship !== '' ? $ship : $shipKey, date('Y-m-d H:i:s')]);
    return q_one('SELECT * FROM sailing_rooms WHERE ship_key = ? AND sail_date = ?', [$shipKey, $date]);
}

function rmt_sailing_chat_url(array $room): string {
    return rmt_sailing_path((string) $room['ship'], (string) $room['sail_date']) . '/chat';
}

/** @return list<array<string,mixed>> lines a viewer may see, oldest first, after a given id */
function rmt_sailing_chat_lines(int $roomId, int $viewerId, int $afterId = 0): array {
    $rows = q_all("SELECT * FROM (
                     SELECT m.id, m.user_id, m.body, m.created_at, u.username, u.status user_status,
                            p.display_name, p.avatar_url
                       FROM sailing_messages m
                       JOIN users u ON u.id = m.user_id
                  LEFT JOIN profiles p ON p.user_id = m.user_id
                      WHERE m.room_id = ? AND m.id > ?
                   ORDER BY m.id DESC LIMIT " . RMT_SAILING_CHAT_PAGE . ") x ORDER BY id",
                  [$roomId, $afterId]);
    return rmt_without_blocked($rows, $viewerId);
}

function rmt_sailing_chat_mark_read(int $roomId, int $userId, int $lastId): void {
    if ($lastId < 1) return;
    $have = q_one('SELECT last_read_id FROM sailing_chat_reads WHERE room_id = ? AND user_id = ?', [$roomId, $userId]);
    if ($have === null) {
        q_run('INSERT INTO sailing_chat_reads (room_id, user_id, last_read_id) VALUES (?,?,?)', [$roomId, $userId, $lastId]);
    } elseif ((int) $have['last_read_id'] < $lastId) {
        q_run('UPDATE sailing_chat_reads SET last_read_id = ? WHERE room_id = ? AND user_id = ?', [$lastId, $roomId, $userId]);
    }
}

/**
 * Write one line. Every rule lives here so the controller and any later caller share it.
 *
 * @return array{ok:bool, error?:string, id?:int}
 */
function rmt_sailing_chat_post(string $shipKey, string $date, string $ship, array $me, string $body): array {
    if (!rmt_sailing_chat_allowed($shipKey, $date, $me)) return ['ok' => false, 'error' => 'Only the travelers on this sailing can talk here.'];
    if (function_exists('rmt_trust_restricted') && rmt_trust_restricted((int) $me['id'])) {
        return ['ok' => false, 'error' => 'Your messaging is paused while your account is restricted. Your trust page has the reason and how to appeal.'];
    }
    $body = trim($body);
    if ($body === '') return ['ok' => false, 'error' => 'Write something first.'];
    if (mb_strlen($body) > RMT_SAILING_CHAT_BODY_MAX) {
        return ['ok' => false, 'error' => 'That is too long (' . RMT_SAILING_CHAT_BODY_MAX . ' characters max).'];
    }
    $meId = (int) $me['id'];
    if (!rmt_rate_ok('sailing_chat', (string) $meId, 60, 3600)) {
        return ['ok' => false, 'error' => 'You are sending messages very fast. Try again shortly.'];
    }
    $room = rmt_sailing_room($shipKey, $date, true, $ship);
    $roomId = (int) $room['id'];
    $now = date('Y-m-d H:i:s');
    q_run('INSERT INTO sailing_messages (room_id, user_id, body, created_at) VALUES (?,?,?,?)', [$roomId, $meId, $body, $now]);
    $id = (int) (q_one('SELECT MAX(id) id FROM sailing_messages WHERE room_id = ? AND user_id = ?', [$roomId, $meId])['id'] ?? 0);
    rmt_sailing_chat_mark_read($roomId, $meId, $id);
    if (function_exists('rmt_track')) rmt_track('sailing_chat_sent');

    /* One notification per member per unread stretch, the trip chat's rule. */
    $where = (string) $room['ship'] . ', ' . rmt_sailing_date_label($date);
    foreach (rmt_sailing_chat_member_ids($shipKey, $date) as $uid) {
        if ($uid === $meId || rmt_is_blocked($meId, $uid)) continue;
        $pending = q_one("SELECT 1 FROM notifications WHERE user_id = ? AND type = 'sailing_chat'
                            AND target_type = 'sailing_room' AND target_id = ? AND read_at IS NULL", [$uid, $roomId]);
        if ($pending) continue;
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
              [$uid, 'sailing_chat', $meId, 'sailing_room', $roomId, $now]);
        rmt_notify_email_direct($uid, 'New in your sailing chat',
            '@' . $me['username'] . ' wrote in the chat for ' . $where . '.',
            rmt_sailing_chat_url($room), 'you posted this sailing on RuinMyTrip');
    }
    return ['ok' => true, 'id' => $id];
}

/** Resolve {ship}/{date} the way the sailing page does. @return array{0:string,1:string,2:string} key, date, ship */
function rmt_sailing_chat_resolve(array $a): array {
    $slug = (string) ($a['ship'] ?? '');
    $date = (string) ($a['date'] ?? '');
    if (!rmt_sailing_valid_date($date) || $slug === '' || strlen($slug) > 60) not_found();
    $key = (string) rmt_buddy_ship_key(str_replace('-', '', $slug));
    if ($key === '') not_found();
    $members = rmt_sailing_members($key, $date);
    $ship = $members ? (string) ($members[0]['ship'] ?: rmt_sailing_ship_name($slug)) : rmt_sailing_ship_name($slug);
    return [$key, $date, $ship];
}

/** GET /sailing/{ship}/{date}/chat, and ?format=json&after=N for the page's own refresh. */
function sailing_chat(array $a): void {
    require_login();
    $me = current_user();
    [$key, $date, $ship] = rmt_sailing_chat_resolve($a);
    if (!rmt_sailing_chat_allowed($key, $date, $me)) forbidden('Only the travelers on this sailing can see its chat. It opens once two members have posted it.');
    $meId = (int) $me['id'];
    $room = rmt_sailing_room($key, $date);
    $roomId = $room ? (int) $room['id'] : 0;

    if ((string) input('format') === 'json') {
        $lines = $roomId ? rmt_sailing_chat_lines($roomId, $meId, max(0, (int) input('after'))) : [];
        if ($lines) rmt_sailing_chat_mark_read($roomId, $meId, (int) end($lines)['id']);
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

    $lines = $roomId ? rmt_sailing_chat_lines($roomId, $meId) : [];
    if ($lines) rmt_sailing_chat_mark_read($roomId, $meId, (int) end($lines)['id']);
    if ($roomId) q_run("UPDATE notifications SET read_at = ? WHERE user_id = ? AND type = 'sailing_chat'
                          AND target_type = 'sailing_room' AND target_id = ? AND read_at IS NULL", [date('Y-m-d H:i:s'), $meId, $roomId]);
    $members = rmt_sailing_members($key, $date, $meId);
    rmt_track('sailing_chat_view', ['source' => 'buddies']);
    view('sailing_chat', ['key' => $key, 'ship' => $ship, 'date' => $date, 'label' => rmt_sailing_date_label($date), 'lines' => $lines, 'members' => $members], [
        'title' => 'Sailing chat: ' . $ship . ', ' . rmt_sailing_date_label($date) . ' | RuinMyTrip',
        'description' => 'The private chat for the travelers on this sailing.',
        'app_shell' => true,
    ]);
}

/** POST /sailing/{ship}/{date}/chat */
function sailing_chat_send(array $a): void {
    require_verified_email(); csrf_check();
    $me = current_user();
    [$key, $date, $ship] = rmt_sailing_chat_resolve($a);
    if (!rmt_sailing_chat_allowed($key, $date, $me)) forbidden('Only the travelers on this sailing can talk here.');
    $back = rmt_sailing_path($ship, $date) . '/chat';
    if (!rmt_submit_ok('sailing_chat_' . $key . $date, input('_submit'))) redirect($back);
    $res = rmt_sailing_chat_post($key, $date, $ship, $me, (string) input('body'));
    if (!$res['ok']) flash((string) $res['error']);
    redirect($back . (!empty($res['id']) ? '#m' . (int) $res['id'] : ''));
}
