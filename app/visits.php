<?php
declare(strict_types=1);

/**
 * "I've been" is a self-asserted stamp: destination-level, no GPS, not a review, not a rating.
 * It never enters a community average. Editorial accounts cannot stamp visits.
 */
function rmt_visit_get(int $userId, int $destId): ?array {
    if ($userId < 1 || $destId < 1) return null;
    return q_one('SELECT * FROM visits WHERE user_id = ? AND destination_id = ?', [$userId, $destId]);
}

function rmt_visit_toggle(int $userId, int $destId): bool {
    $have = rmt_visit_get($userId, $destId);
    if ($have) {
        db()->prepare('DELETE FROM visits WHERE user_id = ? AND destination_id = ?')->execute([$userId, $destId]);
        return false;
    }
    q_run('INSERT INTO visits (user_id, destination_id, created_at) VALUES (?,?,?)',
          [$userId, $destId, date('Y-m-d H:i:s')]);
    return true;
}

/** @return list<array<string,mixed>> */
function rmt_visits_for_destination(int $destId, int $limit = 12): array {
    return q_all(
        "SELECT u.username, p.display_name, p.avatar_url, v.created_at
         FROM visits v JOIN users u ON u.id = v.user_id
         LEFT JOIN profiles p ON p.user_id = u.id
         WHERE v.destination_id = ? AND u.status = 'active' AND u.role <> ?
         ORDER BY v.created_at DESC LIMIT $limit",
        [$destId, RMT_EDITORIAL_ROLE]
    );
}

function rmt_visit_count(int $destId): int {
    return (int) (q_one(
        "SELECT COUNT(*) c FROM visits v JOIN users u ON u.id = v.user_id
         WHERE v.destination_id = ? AND u.status = 'active' AND u.role <> ?",
        [$destId, RMT_EDITORIAL_ROLE]
    )['c'] ?? 0);
}

/** @return list<array<string,mixed>> */
function rmt_visits_for_user(int $userId): array {
    return q_all(
        "SELECT d.slug, d.name, d.country, v.created_at
         FROM visits v JOIN destinations d ON d.id = v.destination_id
         WHERE v.user_id = ? ORDER BY v.created_at DESC",
        [$userId]
    );
}

/** @return list<array<string,mixed>> */
function rmt_wanters_for_destination(int $destId, int $limit = 12): array {
    return q_all(
        "SELECT u.username, p.display_name, p.avatar_url
         FROM saves s JOIN users u ON u.id = s.user_id
         LEFT JOIN profiles p ON p.user_id = u.id
         WHERE s.target_type = 'destination' AND s.target_id = ?
           AND u.status = 'active' AND u.role <> ?
         ORDER BY s.created_at DESC LIMIT $limit",
        [$destId, RMT_EDITORIAL_ROLE]
    );
}
