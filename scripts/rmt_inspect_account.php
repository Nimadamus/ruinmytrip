<?php
/**
 * Read-only: everything one account has put on the site, for deciding what to do about it.
 *
 *   php scripts/rmt_inspect_account.php <username>
 *
 * Prints counts and ids only, never message bodies or email addresses.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';

$username = (string) ($argv[1] ?? '');
$u = q_one('SELECT id, username, role, status, created_at FROM users WHERE username = ?', [$username]);
if (!$u) { echo "no such user\n"; exit(0); }
$uid = (int) $u['id'];
echo json_encode(['user' => $u]), "\n";
$checks = [
    'trips'            => ['SELECT id, status, visibility, title, date_from FROM trips WHERE user_id = ?'],
    'buddy_posts'      => ['SELECT id, status, title FROM buddy_posts WHERE user_id = ?'],
    'buddy_interest'   => ['SELECT post_id, state FROM buddy_interest WHERE user_id = ?'],
    'trip_connects'    => ['SELECT id, state FROM trip_connects WHERE from_user_id = ? OR to_user_id = ?', 2],
    'local_connects'   => ['SELECT from_user_id, to_user_id, state FROM local_connects WHERE from_user_id = ? OR to_user_id = ?', 2],
    'reviews'          => ['SELECT id, status FROM reviews WHERE user_id = ?'],
    'posts'            => ['SELECT id, status FROM posts WHERE user_id = ?'],
    'comments'         => ['SELECT id, status FROM comments WHERE user_id = ?'],
    'meetups'          => ['SELECT id, status FROM meetups WHERE host_id = ?'],
    'follows_out'      => ['SELECT COUNT(*) n FROM follows WHERE follower_id = ?'],
    'follows_in'       => ['SELECT COUNT(*) n FROM follows WHERE followee_id = ?'],
    'conversations'    => ['SELECT COUNT(*) n FROM conversations WHERE user_lo_id = ? OR user_hi_id = ?', 2],
    'profile'          => ['SELECT open_to_meeting, home_destination_id FROM profiles WHERE user_id = ?'],
];
foreach ($checks as $name => $c) {
    try {
        $rows = q_all($c[0], array_fill(0, $c[1] ?? 1, $uid));
        echo $name, ': ', json_encode($rows), "\n";
    } catch (Throwable $e) {
        echo $name, ': error ', $e->getMessage(), "\n";
    }
}
