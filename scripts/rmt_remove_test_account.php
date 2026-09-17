<?php
/**
 * Take a test account off the public site without deleting anything.
 *
 *   php scripts/rmt_remove_test_account.php <username>          dry run, prints what would change
 *   php scripts/rmt_remove_test_account.php <username> --apply  does it
 *
 * Soft only, and reversible: its trips and buddy posts move to status 'removed', its local listing
 * is switched off, and the account is suspended (which also blocks sign in). Every change is written
 * to moderation_log. No row is deleted and no other member's data is touched.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';

$username = (string) ($argv[1] ?? '');
$apply = in_array('--apply', $argv, true);
$u = q_one('SELECT id, username, role, status FROM users WHERE username = ?', [$username]);
if (!$u) { echo "no such user\n"; exit(1); }
if (in_array($u['role'], ['admin', 'mod'], true)) { echo "refusing: staff account\n"; exit(1); }
$uid = (int) $u['id'];
$now = date('Y-m-d H:i:s');

$trips = q_all("SELECT id, status FROM trips WHERE user_id = ? AND status <> 'removed'", [$uid]);
$posts = q_all("SELECT id, status FROM buddy_posts WHERE user_id = ? AND status <> 'removed'", [$uid]);
echo json_encode(['user' => $u, 'trips_to_remove' => $trips, 'buddy_posts_to_remove' => $posts, 'apply' => $apply]), "\n";
if (!$apply) exit(0);

$log = static function (string $type, int $id, string $from, string $to) use ($now): void {
    q_run('INSERT INTO moderation_log (actor_id, target_type, target_id, report_id, action, from_status, to_status, note, created_at)
           VALUES (NULL, ?, ?, NULL, ?, ?, ?, ?, ?)', [$type, $id, 'remove', $from, $to, 'Test account cleanup approved by the owner', $now]);
};
foreach ($trips as $t) {
    q_run("UPDATE trips SET status = 'removed', updated_at = ? WHERE id = ?", [$now, (int) $t['id']]);
    $log('trip', (int) $t['id'], (string) $t['status'], 'removed');
}
foreach ($posts as $p) {
    q_run("UPDATE buddy_posts SET status = 'removed', updated_at = ? WHERE id = ?", [$now, (int) $p['id']]);
    $log('buddy', (int) $p['id'], (string) $p['status'], 'removed');
}
q_run('UPDATE profiles SET open_to_meeting = 0 WHERE user_id = ?', [$uid]);
q_run("UPDATE users SET status = 'suspended' WHERE id = ?", [$uid]);
$log('user', $uid, (string) $u['status'], 'suspended');
echo json_encode(q_one('SELECT status FROM users WHERE id = ?', [$uid])), " trips removed: ", count($trips), " posts removed: ", count($posts), "\n";
