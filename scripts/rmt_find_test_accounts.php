<?php
/**
 * Read-only: active accounts whose name looks like a test, and whether anything of theirs is public.
 *
 *   php scripts/rmt_find_test_accounts.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';

$rows = q_all("SELECT u.id, u.username, u.role, u.status, u.created_at,
                      (SELECT COUNT(*) FROM trips t WHERE t.user_id = u.id AND t.status = 'published' AND t.visibility = 'public') public_trips,
                      (SELECT COUNT(*) FROM buddy_posts b WHERE b.user_id = u.id AND b.status IN ('open','closed','completed')) buddy_posts,
                      (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id AND r.status = 'published') reviews,
                      (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.status = 'published') posts,
                      (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id AND c.status = 'published') comments,
                      (SELECT COALESCE(MAX(open_to_meeting), 0) FROM profiles pr WHERE pr.user_id = u.id) local_listed
                 FROM users u
                WHERE u.status = 'active'
                  AND (LOWER(u.username) LIKE '%test%' OR LOWER(u.username) LIKE '%fixture%' OR LOWER(u.username) LIKE 'qa%'
                       OR LOWER(u.username) LIKE '%probe%' OR LOWER(u.username) LIKE '%demo%' OR LOWER(u.username) LIKE '%sample%'
                       OR LOWER(u.username) LIKE '%dummy%' OR LOWER(u.username) LIKE '%fake%' OR LOWER(u.username) LIKE 'selfcheck%')
             ORDER BY u.id");
echo 'test-looking active accounts: ', count($rows), "\n";
foreach ($rows as $r) echo json_encode($r), "\n";
echo 'all active accounts with public trips or buddy posts: ',
    json_encode(q_all("SELECT u.username, u.role, COUNT(t.id) public_upcoming_trips FROM users u JOIN trips t ON t.user_id = u.id
                        WHERE u.status = 'active' AND t.status = 'published' AND t.visibility = 'public' AND t.date_to >= ?
                        GROUP BY u.username, u.role ORDER BY u.username", [date('Y-m-d')])), "\n";
echo 'open buddy posts: ', (int) (q_one("SELECT COUNT(*) n FROM buddy_posts WHERE status = 'open'")['n'] ?? 0), "\n";
echo 'locals listed: ', json_encode(q_all("SELECT u.username FROM profiles p JOIN users u ON u.id = p.user_id WHERE p.open_to_meeting = 1 AND u.status = 'active'")), "\n";
echo 'migrations: ', json_encode(q_all("SELECT version FROM schema_migrations WHERE version >= '098' ORDER BY version")), "\n";
