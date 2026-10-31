<?php
/**
 * Safety signals for moderators: members whose recent behavior looks like mass outreach or trouble.
 * Read only. It flags, a moderator decides; nothing here suspends, hides or messages anyone.
 */

const RMT_SIGNAL_DAYS = 7;
const RMT_SIGNAL_RULES = [
    'requests'  => [10, 'Sent 10 or more requests (stays, meet a local, trips, buddies, activities) in 7 days'],
    'new_rush'  => [5,  'Account under 7 days old that sent 5 or more requests'],
    'declined'  => [5,  'Five or more requests declined in 7 days'],
    'new_chats' => [15, 'Started 15 or more new conversations in 7 days'],
    'reports'   => [2,  'Two or more open reports about them'],
    'blocked'   => [3,  'Blocked by 3 or more members'],
];

/** Per member counts for the window, only for members with any activity in it. @return array<int,array> */
function rmt_signal_counts(int $days = RMT_SIGNAL_DAYS): array {
    $since = date('Y-m-d H:i:s', time() - $days * 86400);
    $sent = [
        "SELECT guest_id u, COUNT(*) n, SUM(CASE WHEN state = 'declined' THEN 1 ELSE 0 END) d FROM stay_requests WHERE created_at >= ? GROUP BY guest_id",
        "SELECT from_user_id u, COUNT(*) n, SUM(CASE WHEN state = 'declined' THEN 1 ELSE 0 END) d FROM local_connects WHERE created_at >= ? GROUP BY from_user_id",
        "SELECT from_user_id u, COUNT(*) n, SUM(CASE WHEN state = 'declined' THEN 1 ELSE 0 END) d FROM trip_connects WHERE created_at >= ? GROUP BY from_user_id",
        "SELECT user_id u, COUNT(*) n, SUM(CASE WHEN state = 'declined' THEN 1 ELSE 0 END) d FROM buddy_interest WHERE created_at >= ? GROUP BY user_id",
        "SELECT user_id u, COUNT(*) n, SUM(CASE WHEN state = 'declined' THEN 1 ELSE 0 END) d FROM activity_joins WHERE created_at >= ? GROUP BY user_id",
    ];
    $c = [];
    $row = static function (int $u) use (&$c): void { $c[$u] ??= ['requests' => 0, 'declined' => 0, 'new_chats' => 0, 'reports' => 0, 'blocked' => 0]; };
    foreach ($sent as $sql) foreach (q_all($sql, [$since]) as $r) {
        $row((int) $r['u']); $c[(int) $r['u']]['requests'] += (int) $r['n']; $c[(int) $r['u']]['declined'] += (int) $r['d'];
    }
    // A new conversation counts for whoever wrote its first message.
    foreach (q_all("SELECT m.sender_id u, COUNT(*) n FROM conversations c
                      JOIN messages m ON m.id = (SELECT MIN(id) FROM messages WHERE conversation_id = c.id)
                     WHERE c.created_at >= ? GROUP BY m.sender_id", [$since]) as $r) {
        $row((int) $r['u']); $c[(int) $r['u']]['new_chats'] = (int) $r['n'];
    }
    foreach (q_all("SELECT target_id u, COUNT(*) n FROM reports WHERE target_type = 'user' AND status = 'open' GROUP BY target_id") as $r) {
        $row((int) $r['u']); $c[(int) $r['u']]['reports'] = (int) $r['n'];
    }
    foreach (q_all('SELECT blocked_id u, COUNT(*) n FROM blocks GROUP BY blocked_id') as $r) {
        $row((int) $r['u']); $c[(int) $r['u']]['blocked'] = (int) $r['n'];
    }
    return $c;
}

/** Members tripping at least one rule, most flags first. @return list<array> */
function rmt_signal_flagged(int $days = RMT_SIGNAL_DAYS): array {
    $counts = rmt_signal_counts($days);
    if (!$counts) return [];
    $users = [];
    foreach (array_chunk(array_keys($counts), 500) as $ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (q_all("SELECT id, username, status, created_at, email_verified_at FROM users WHERE id IN ($in)", $ids) as $u) $users[(int) $u['id']] = $u;
    }
    $newCut = date('Y-m-d H:i:s', time() - RMT_SIGNAL_DAYS * 86400);
    $out = [];
    foreach ($counts as $uid => $n) {
        $u = $users[$uid] ?? null;
        if (!$u || $u['status'] === 'deleted') continue;
        $n['new_rush'] = (string) $u['created_at'] >= $newCut ? $n['requests'] : 0;
        $flags = [];
        foreach (RMT_SIGNAL_RULES as $k => [$min, $label]) if ($n[$k] >= $min) $flags[$k] = $label;
        if ($flags) $out[] = $u + ['counts' => $n, 'flags' => $flags];
    }
    usort($out, fn($a, $b) => [count($b['flags']), $b['counts']['requests']] <=> [count($a['flags']), $a['counts']['requests']]);
    return $out;
}

/** GET /admin/safety */
function admin_safety(array $a): void {
    require_role('admin', 'mod');
    view('admin_safety', ['rows' => rmt_signal_flagged()], ['title' => 'Safety signals | RuinMyTrip admin', 'robots' => 'noindex, nofollow']);
}
