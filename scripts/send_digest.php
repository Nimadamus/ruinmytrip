<?php
declare(strict_types=1);

/**
 * Weekly activity digest email. Intended to run on a schedule (see
 * .github/workflows/weekly-digest.yml), not on every deploy.
 *
 * For each active, email-verified, non-opted-out user: sums real activity since their last digest.
 * What counts as activity lives in app/digest.php (rmt_digest_activity), so the question this whole
 * retention loop rests on is testable and is asked in exactly one place. A user with zero activity
 * in the window gets nothing -- an empty digest is spam, not a service.
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

    $activity = rmt_digest_activity($uid, $since);
    if (!$activity['any']) { $skippedEmpty++; continue; }

    out(sprintf('%s @%s: %s', $dryRun ? 'WOULD SEND' : 'sending', $u['username'], rmt_digest_summary($activity)));

    if (!$dryRun) {
        [$ok, $detail] = rmt_mail_digest($u['email'], $u['username'], $activity, rmt_unsubscribe_url($uid));
        if (!$ok) { out("  ! send failed: {$detail}"); continue; }
        db()->prepare('UPDATE profiles SET last_digest_at = ? WHERE user_id = ?')->execute([$now, $uid]);
    }
    $sent++;
}

out(sprintf('%s: %d %s, %d skipped (no activity), %d skipped (unverified email)',
           $dryRun ? 'DRY RUN complete' : 'done', $sent, $dryRun ? 'would send' : 'sent', $skippedEmpty, $skippedUnverified));
