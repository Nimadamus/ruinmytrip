<?php
/**
 * Email for the handful of things that are addressed to one person.
 *
 * The weekly digest is a summary of a week. It is the wrong instrument for "somebody answered
 * you", which is worth knowing today or not at all: by Monday the conversation is over and the
 * reply is a fact rather than an invitation. But an email per event is how a site teaches people
 * to filter it, so this is deliberately narrow and hard-capped.
 *
 * What qualifies: a reply to something you wrote, being mentioned by name, a direct message.
 * Nothing about likes, nothing about strangers publishing things, nothing that is merely
 * interesting. Those are what the digest and the notifications page are for.
 *
 * The caps are the whole design:
 *   - the recipient must be active, verified, and not opted out of email
 *   - at most one of these emails per recipient per hour
 *   - at most six per recipient per day
 * Over a cap it is simply not sent. The notification is already on the site; the email is the
 * courtesy, and a courtesy that arrives eleven times is not one.
 */
declare(strict_types=1);

const RMT_DIRECT_MAIL_PER_HOUR = 1;
const RMT_DIRECT_MAIL_PER_DAY  = 6;

/**
 * @param int    $userId  who it is for
 * @param string $subject email subject
 * @param string $line    one sentence: what happened, in the words the site would use
 * @param string $path    where to go, absolute or site-relative
 * @return bool whether an email was actually sent
 */
function rmt_notify_email_direct(int $userId, string $subject, string $line, string $path): bool {
    if ($userId < 1 || !rmt_mail_enabled()) return false;

    $u = q_one("SELECT u.id, u.username, u.email, u.status, u.email_verified_at,
                       COALESCE(p.digest_opt_out, 0) opt_out
                  FROM users u LEFT JOIN profiles p ON p.user_id = u.id
                 WHERE u.id = ?", [$userId]);
    if (!$u || $u['status'] !== 'active') return false;
    if (empty($u['email_verified_at'])) return false;   // never mail an address nobody confirmed
    if ((int) $u['opt_out'] === 1) return false;

    // Two windows, both enforced before anything is composed: an email that is not going to be
    // sent should not cost a render, and neither cap is worth being clever about.
    if (!rmt_rate_ok('direct_mail_hour', (string) $userId, RMT_DIRECT_MAIL_PER_HOUR, 3600)) return false;
    if (!rmt_rate_ok('direct_mail_day', (string) $userId, RMT_DIRECT_MAIL_PER_DAY, 86400)) return false;

    $url = str_starts_with($path, 'http') ? $path : url(ltrim($path, '/'));
    $unsub = rmt_unsubscribe_url($userId);
    $html = rmt_mail_layout(
        $subject,
        '<p>' . e($line) . '</p>'
        . '<p style="margin:20px 0"><a href="' . e($url) . '">Open it on RuinMyTrip</a></p>'
        . '<p style="color:#8895a3;font-size:12px;margin:24px 0 0">'
        . 'You are getting this because somebody wrote to you directly. '
        . '<a href="' . e($unsub) . '" style="color:#8895a3">Turn these off</a>.</p>'
    );
    $text = $line . "\n\n" . $url . "\n\nTurn these off: " . $unsub;

    [$ok] = rmt_mail_send((string) $u['email'], $subject, $html, $text);
    return (bool) $ok;
}
