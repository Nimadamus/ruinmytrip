<?php
declare(strict_types=1);

/**
 * Owner membership report: who has joined, and what members have actually done.
 *
 * GET /cron/members (X-Cron-Key, 404 otherwise). Read only. Aggregates plus one anonymous row per
 * account (id, join time, class, flags, action count): no names, usernames or emails leave here.
 *
 * Classes:
 *   genuine  passes rmt_sc_real_user_sql and is not staff (admin or mod)
 *   staff    admin or mod accounts (the owner and moderators)
 *   house    editorial role, team_ usernames, +rmtqa test addresses
 *   inactive any status other than active (deleted, banned, pending)
 */

/** @return array<string,mixed> */
function rmt_members_report(?string $now = null): array {
    $nowTs = strtotime(($now ?? gmdate('Y-m-d H:i:s')) . ' UTC');
    $at = static fn(int $sec): string => gmdate('Y-m-d H:i:s', $nowTs - $sec);
    $errors = [];
    $one = static function (string $name, string $sql, array $args = []) use (&$errors): ?int {
        try { return (int) (q_one($sql, $args)['c'] ?? 0); }
        catch (Throwable $e) { $errors[] = $name; return null; }
    };
    $all = static function (string $name, string $sql, array $args = []) use (&$errors): array {
        try { return q_all($sql, $args); }
        catch (Throwable $e) { $errors[] = $name; return []; }
    };

    $real = rmt_sc_real_user_sql('u');
    $genuine = "($real AND u.role NOT IN ('admin', 'mod'))";

    /* Every account, classified, with what it has done. Counts only, never content. */
    $acts = [
        'reviews'  => "SELECT user_id k, COUNT(*) c FROM reviews WHERE status = 'published' GROUP BY user_id",
        'trips'    => "SELECT user_id k, COUNT(*) c FROM trips GROUP BY user_id",
        'buddy'    => "SELECT user_id k, COUNT(*) c FROM buddy_posts GROUP BY user_id",
        'interest' => "SELECT user_id k, COUNT(*) c FROM buddy_interest GROUP BY user_id",
        'posts'    => "SELECT user_id k, COUNT(*) c FROM posts GROUP BY user_id",
        'comments' => "SELECT user_id k, COUNT(*) c FROM comments GROUP BY user_id",
        'messages' => "SELECT sender_id k, COUNT(*) c FROM messages GROUP BY sender_id",
        'connects' => "SELECT from_user_id k, COUNT(*) c FROM local_connects GROUP BY from_user_id",
        'follows'  => "SELECT follower_id k, COUNT(*) c FROM follows GROUP BY follower_id",
    ];
    $byUser = [];
    foreach ($acts as $name => $sql) {
        foreach ($all("acts_$name", $sql) as $r) $byUser[(int) $r['k']][$name] = (int) $r['c'];
    }
    $google = [];
    foreach ($all('oauth', "SELECT DISTINCT user_id FROM oauth_identities") as $r) $google[(int) $r['user_id']] = true;

    $rows = $all('accounts', "SELECT u.id, u.created_at, u.role, u.status, u.email_verified_at,
                                     CASE WHEN $genuine THEN 'genuine'
                                          WHEN u.status <> 'active' THEN 'inactive'
                                          WHEN u.role IN ('admin', 'mod') THEN 'staff'
                                          ELSE 'house' END AS cls,
                                     CASE WHEN TRIM(COALESCE(p.display_name, '')) <> ''
                                           AND LENGTH(TRIM(COALESCE(p.bio, ''))) >= 20
                                           AND TRIM(COALESCE(p.home_city, '')) <> '' THEN 1 ELSE 0 END AS profile_complete,
                                     CASE WHEN TRIM(COALESCE(p.local_offers, '')) <> '' THEN 1 ELSE 0 END AS local
                                FROM users u LEFT JOIN profiles p ON p.user_id = u.id
                               ORDER BY u.created_at DESC");
    $accounts = []; $byClass = ['genuine' => 0, 'staff' => 0, 'house' => 0, 'inactive' => 0];
    $lastGenuine = null;
    foreach ($rows as $r) {
        $id = (int) $r['id']; $a = $byUser[$id] ?? [];
        $byClass[$r['cls']] = ($byClass[$r['cls']] ?? 0) + 1;
        if ($r['cls'] === 'genuine' && $lastGenuine === null) $lastGenuine = (string) $r['created_at'];
        $accounts[] = [
            'id' => $id, 'joined_utc' => (string) $r['created_at'], 'class' => $r['cls'],
            'confirmed' => $r['email_verified_at'] !== null, 'google' => isset($google[$id]),
            'profile_complete' => (bool) $r['profile_complete'], 'local' => (bool) $r['local'],
            'actions' => array_sum($a), 'did' => $a,
        ];
    }

    $win = static fn(int $sec) => $one("new_$sec", "SELECT COUNT(*) c FROM users u WHERE $genuine AND u.created_at >= ?", [$at($sec)]);
    $j = "JOIN users u ON u.id = ";
    $since30 = $at(30 * 86400);

    return [
        'generated_utc' => gmdate('Y-m-d H:i:s', $nowTs),
        'accounts_total' => count($rows),
        'by_class' => $byClass,
        'genuine' => [
            'total' => $byClass['genuine'],
            'new_24h' => $win(86400), 'new_7d' => $win(7 * 86400), 'new_30d' => $win(30 * 86400),
            'last_joined_utc' => $lastGenuine,
            'confirmed' => $one('confirmed', "SELECT COUNT(*) c FROM users u WHERE $genuine AND u.email_verified_at IS NOT NULL"),
            'google' => $one('google', "SELECT COUNT(DISTINCT o.user_id) c FROM oauth_identities o $j o.user_id WHERE $genuine"),
            'profiles_complete' => count(array_filter($accounts, fn($x) => $x['class'] === 'genuine' && $x['profile_complete'])),
            'with_any_action' => count(array_filter($accounts, fn($x) => $x['class'] === 'genuine' && $x['actions'] > 0)),
        ],
        'content_by_genuine' => [
            'reviews_published' => $one('reviews', "SELECT COUNT(*) c FROM reviews r $j r.user_id WHERE $genuine AND r.status = 'published'"),
            'trips' => $one('trips', "SELECT COUNT(*) c FROM trips t $j t.user_id WHERE $genuine"),
            'trips_published' => $one('trips_pub', "SELECT COUNT(*) c FROM trips t $j t.user_id WHERE $genuine AND t.status = 'published'"),
            'buddy_posts' => $one('buddy', "SELECT COUNT(*) c FROM buddy_posts b $j b.user_id WHERE $genuine"),
            'buddy_requests' => $one('interest', "SELECT COUNT(*) c FROM buddy_interest i $j i.user_id WHERE $genuine"),
            'buddy_matches' => $one('matches', "SELECT COUNT(*) c FROM buddy_interest i $j i.user_id WHERE $genuine AND i.state = 'accepted'"),
            'locals' => count(array_filter($accounts, fn($x) => $x['class'] === 'genuine' && $x['local'])),
            'host_applications' => $one('hosts', "SELECT COUNT(*) c FROM host_profiles h $j h.user_id WHERE $genuine"),
            'hosts_approved' => $one('hosts_ok', "SELECT COUNT(*) c FROM host_profiles h $j h.user_id WHERE $genuine AND h.status = 'approved'"),
            'local_connect_requests' => $one('lc', "SELECT COUNT(*) c FROM local_connects l $j l.from_user_id WHERE $genuine"),
            'stay_requests' => $one('stays', "SELECT COUNT(*) c FROM stay_requests s $j s.guest_id WHERE $genuine"),
            'messages' => $one('messages', "SELECT COUNT(*) c FROM messages m $j m.sender_id WHERE $genuine"),
            'trip_chat_messages' => $one('trip_msgs', "SELECT COUNT(*) c FROM trip_messages m $j m.user_id WHERE $genuine"),
            'discussion_posts' => $one('posts', "SELECT COUNT(*) c FROM posts p $j p.user_id WHERE $genuine AND p.status = 'published'"),
            'discussion_posts_30d' => $one('posts30', "SELECT COUNT(*) c FROM posts p $j p.user_id WHERE $genuine AND p.status = 'published' AND p.created_at >= ?", [$since30]),
            'comments' => $one('comments', "SELECT COUNT(*) c FROM comments c2 $j c2.user_id WHERE $genuine"),
        ],
        'accounts' => $accounts,
        'errors' => $errors,
        'note' => 'genuine = active, not editorial, not team_, not +rmtqa, not admin or mod. Rows carry no names or emails. A failed query is listed in errors and is null, never zero.',
    ];
}

/** GET /cron/members  same key and same 404 as /cron/kpi. */
function cron_members(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = rmt_cron_key_given();
    if ($key === '' || $given === '' || !hash_equals($key, $given)) not_found();
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    header('Cache-Control: no-store');
    echo json_encode(rmt_members_report());
}
