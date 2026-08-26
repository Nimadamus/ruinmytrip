<?php
declare(strict_types=1);

/**
 * "Who's going": destination + date range only. Never coordinates, never a live location.
 *
 * One plan per traveler per destination. Visibility is public | followers | private.
 * Public plans are the matching surface; followers plans are for people you already follow;
 * private plans exist only on your own profile.
 */
const RMT_GOING_VIS = ['public', 'followers', 'private'];

function rmt_going_for_user_dest(int $userId, int $destId): ?array {
    if ($userId < 1 || $destId < 1) return null;
    return q_one('SELECT * FROM going WHERE user_id = ? AND destination_id = ?', [$userId, $destId]);
}

/**
 * Plans a viewer is allowed to see for one destination.
 *
 * @return list<array<string,mixed>>
 */
function rmt_going_list_for_destination(int $destId, ?array $viewer): array {
    [$visSql, $visArgs] = rmt_going_visibility_sql('g', $viewer);
    return q_all(
        "SELECT g.*, u.username, p.avatar_url, p.display_name
         FROM going g JOIN users u ON u.id = g.user_id
         LEFT JOIN profiles p ON p.user_id = u.id
         WHERE g.destination_id = ? AND u.status = 'active' AND $visSql
         ORDER BY g.date_from",
        array_merge([$destId], $visArgs)
    );
}

/**
 * Plans visible on a profile. Owner sees all of their own; everyone else sees public, plus
 * followers-visibility if they follow.
 *
 * @return list<array<string,mixed>>
 */
function rmt_going_list_for_profile(int $profileUid, ?array $viewer): array {
    $isOwner = $viewer && (int) $viewer['id'] === $profileUid;
    if ($isOwner) {
        return q_all(
            "SELECT g.*, d.name dest_name, d.slug dest_slug
             FROM going g JOIN destinations d ON d.id = g.destination_id
             WHERE g.user_id = ? ORDER BY g.date_from",
            [$profileUid]
        );
    }
    [$visSql, $visArgs] = rmt_going_visibility_sql('g', $viewer);
    return q_all(
        "SELECT g.*, d.name dest_name, d.slug dest_slug
         FROM going g JOIN destinations d ON d.id = g.destination_id
         WHERE g.user_id = ? AND $visSql
         ORDER BY g.date_from",
        array_merge([$profileUid], $visArgs)
    );
}

/** @return array{0:string,1:list<mixed>} SQL fragment + bound args */
function rmt_going_visibility_sql(string $alias, ?array $viewer): array {
    if (!$viewer) return ["{$alias}.visibility = 'public'", []];
    $uid = (int) $viewer['id'];
    return [
        "({$alias}.user_id = ?
          OR {$alias}.visibility = 'public'
          OR ({$alias}.visibility = 'followers'
              AND EXISTS (SELECT 1 FROM follows f WHERE f.follower_id = ? AND f.followee_id = {$alias}.user_id)))",
        [$uid, $uid],
    ];
}

/**
 * @return array{ok:bool, errors:string[], data:array<string,mixed>}
 */
function rmt_going_validate(array $in): array {
    $errors = [];
    $destId = (int) ($in['destination_id'] ?? 0);
    if ($destId < 1 || !q_one('SELECT id FROM destinations WHERE id = ?', [$destId])) {
        $errors[] = 'Pick a destination.';
    }
    $from = trim((string) ($in['date_from'] ?? ''));
    $to = trim((string) ($in['date_to'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !strtotime($from)) {
        $errors[] = 'Start date must be a real calendar day.';
        $from = '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || !strtotime($to)) {
        $errors[] = 'End date must be a real calendar day.';
        $to = '';
    }
    if ($from !== '' && $to !== '' && $from > $to) {
        $errors[] = 'End date cannot be before the start date.';
    }
    if ($to !== '' && $to < gmdate('Y-m-d')) {
        $errors[] = 'This is for upcoming trips. Share a past trip as a trip story instead.';
    }
    $vis = (string) ($in['visibility'] ?? 'public');
    if (!in_array($vis, RMT_GOING_VIS, true)) $vis = 'public';
    return ['ok' => $errors === [], 'errors' => $errors, 'data' => [
        'destination_id' => $destId, 'date_from' => $from, 'date_to' => $to, 'visibility' => $vis,
    ]];
}

/**
 * Insert or replace this traveler's plan for one destination. Returns the going id, or 0 on
 * validation failure (errors are left for the caller).
 */
function rmt_going_upsert(int $userId, array $data): int {
    $now = date('Y-m-d H:i:s');
    $have = rmt_going_for_user_dest($userId, (int) $data['destination_id']);
    if ($have) {
        db()->prepare('UPDATE going SET date_from=?, date_to=?, visibility=? WHERE id=?')
           ->execute([$data['date_from'], $data['date_to'], $data['visibility'], (int) $have['id']]);
        return (int) $have['id'];
    }
    q_run(
        'INSERT INTO going (user_id, destination_id, date_from, date_to, visibility, created_at) VALUES (?,?,?,?,?,?)',
        [$userId, (int) $data['destination_id'], $data['date_from'], $data['date_to'], $data['visibility'], $now]
    );
    $row = rmt_going_for_user_dest($userId, (int) $data['destination_id']);
    return (int) ($row['id'] ?? 0);
}

function rmt_going_delete(int $userId, int $destId): void {
    db()->prepare('DELETE FROM going WHERE user_id = ? AND destination_id = ?')->execute([$userId, $destId]);
}

/** Tell followers a public plan was posted. Followers-only and private plans do not notify. */
function rmt_going_notify_followers(int $actorId, int $goingId, string $visibility): void {
    if ($visibility !== 'public' || $goingId < 1) return;
    $fol = q_all('SELECT follower_id FROM follows WHERE followee_id = ?', [$actorId]);
    $now = date('Y-m-d H:i:s');
    foreach ($fol as $f) {
        $fid = (int) $f['follower_id'];
        if ($fid < 1 || $fid === $actorId) continue;
        q_run(
            'INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
            [$fid, 'going', $actorId, 'going', $goingId, $now]
        );
    }
}
