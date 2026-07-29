<?php
declare(strict_types=1);

/**
 * Weekly activity digest email. Intended to run on a schedule (see
 * .github/workflows/weekly-digest.yml), not on every deploy.
 *
 * For each active, email-verified, non-opted-out user: sums real activity since their last
 * digest (new followers, votes received, compliments received, new reviews from people they
 * follow). A user with zero activity in the window gets nothing -- an empty digest is spam, not
 * a service.
 *
 * Usage:
 *   php scripts/send_digest.php --dry-run    preview who would get what, send nothing
 *   php scripts/send_digest.php              send for real, advance last_digest_at
 */

define('RMT_NO_AUTOSEED', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$dryRun = in_array('--dry-run', array_slice($argv, 1), true);
$now = date('Y-m-d H:i:s');
$defaultSince = date('Y-m-d H:i:s', time() - 7 * 86400); // first-ever run: last 7 days, not "all time"

function out(string $s): void { echo $s . PHP_EOL; }

$recipients = q_all("SELECT u.id, u.username, u.email, u.created_at, u.email_verified_at, p.last_digest_at
                     FROM users u JOIN profiles p ON p.user_id = u.id
                     WHERE u.status = 'active' AND u.role <> 'editorial'
                       AND COALESCE(p.digest_opt_out, 0) = 0");

$sent = 0; $skippedEmpty = 0; $skippedUnverified = 0;

foreach ($recipients as $u) {
    if (!email_is_verified($u)) { $skippedUnverified++; continue; }
    $uid = (int) $u['id'];
    $since = $u['last_digest_at'] ?: $defaultSince;

    $followers = q_all("SELECT u2.username FROM follows f JOIN users u2 ON u2.id = f.follower_id
                        WHERE f.followee_id = ? AND f.created_at > ? ORDER BY f.created_at DESC LIMIT 5",
                       [$uid, $since]);
    $followerCount = (int) (q_one("SELECT COUNT(*) c FROM follows WHERE followee_id = ? AND created_at > ?",
                                  [$uid, $since])['c'] ?? 0);
    $votes = (int) (q_one("SELECT COUNT(*) c FROM review_votes rv JOIN reviews r ON r.id = rv.review_id
                          WHERE r.user_id = ? AND r.status='published' AND rv.created_at > ?",
                         [$uid, $since])['c'] ?? 0);
    $compliments = (int) (q_one("SELECT COUNT(*) c FROM compliments WHERE to_user_id = ? AND created_at > ?",
                                [$uid, $since])['c'] ?? 0);
    $followedReviews = q_all("SELECT r.id, r.title, r.subject_name, r.slug, u2.username
                             FROM reviews r JOIN follows f ON f.followee_id = r.user_id
                             LEFT JOIN users u2 ON u2.id = r.user_id
                             WHERE f.follower_id = ? AND r.status = 'published' AND r.created_at > ?
                             ORDER BY r.created_at DESC LIMIT 5", [$uid, $since]);

    if ($followerCount === 0 && $votes === 0 && $compliments === 0 && !$followedReviews) {
        $skippedEmpty++;
        continue;
    }

    $activity = [
        'followers' => $followerCount,
        'follower_names' => array_column($followers, 'username'),
        'votes' => $votes,
        'compliments' => $compliments,
        'reviews' => array_map(fn($r) => [
            'title' => $r['title'] ?: $r['subject_name'],
            'author' => $r['username'] ?: 'a traveler',
            'url' => url(ltrim(rmt_review_path($r), '/')),
        ], $followedReviews),
    ];

    out(sprintf('%s @%s: %d follower(s), %d vote(s), %d compliment(s), %d review(s)',
               $dryRun ? 'WOULD SEND' : 'sending', $u['username'], $followerCount, $votes, $compliments, count($followedReviews)));

    if (!$dryRun) {
        [$ok, $detail] = rmt_mail_digest($u['email'], $u['username'], $activity, rmt_unsubscribe_url($uid));
        if (!$ok) { out("  ! send failed: {$detail}"); continue; }
        db()->prepare('UPDATE profiles SET last_digest_at = ? WHERE user_id = ?')->execute([$now, $uid]);
    }
    $sent++;
}

out(sprintf('%s: %d %s, %d skipped (no activity), %d skipped (unverified email)',
           $dryRun ? 'DRY RUN complete' : 'done', $sent, $dryRun ? 'would send' : 'sent', $skippedEmpty, $skippedUnverified));
