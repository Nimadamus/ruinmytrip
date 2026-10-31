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
function rmt_notify_email_direct(int $userId, string $subject, string $line, string $path,
                                 string $why = 'somebody wrote to you directly'): bool {
    if ($userId < 1 || !rmt_mail_enabled()) return false;
    if (rmt_email_muted($userId, rmt_email_category($path))) return false;

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
        . 'You are getting this because ' . e($why) . '. '
        . '<a href="' . e($unsub) . '" style="color:#8895a3">Turn these off</a>.</p>'
    );
    $text = $line . "\n\n" . $url . "\n\nTurn these off: " . $unsub;

    [$ok] = rmt_mail_send((string) $u['email'], $subject, $html, $text, rmt_mail_unsub_headers($unsub));
    return (bool) $ok;
}

/**
 * One welcome email, sent the first time a member confirms their address.
 *
 * It describes the account rather than answering anything the member did, so it is treated as
 * promotional: it goes out only when rmt_mail_marketing_ready() is true, which needs the sender's
 * postal address in the footer, and it carries the unsubscribe link and header. Until then it is
 * simply not sent.
 */
function rmt_welcome_email(int $userId): bool {
    if ($userId < 1 || !rmt_mail_marketing_ready()) return false;
    $u = q_one("SELECT u.id, u.username, u.email, u.status, u.email_verified_at, COALESCE(p.digest_opt_out, 0) opt_out
                  FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = ?", [$userId]);
    if (!$u || $u['status'] !== 'active' || empty($u['email_verified_at']) || (int) $u['opt_out'] === 1) return false;
    if (!rmt_rate_ok('welcome_mail', (string) $userId, 1, 86400 * 3650)) return false;
    $unsub = rmt_unsubscribe_url($userId);
    $li = static fn(string $b, string $t, string $path): string =>
        '<li style="margin:0 0 10px"><b>' . e($b) . '</b> ' . e($t) . ' <a href="' . e(url($path)) . '">Open</a></li>';
    $html = rmt_mail_layout('Welcome to RuinMyTrip',
        '<p>Your account is ready. One profile does all of this:</p><ul style="padding-left:18px">'
        . $li('Honest reviews.', 'Rate a place, bad parts included.', 'review/new')
        . $li('Travel buddies.', 'Post a city and dates and see who overlaps.', 'buddies')
        . $li('Hosts & Locals.', 'Meet people who live where you are going, or be a local in your own city.', 'hosts-locals')
        . $li('Your travel map.', 'Color in the countries you have been to.', 'map')
        . '</ul><p style="color:#8895a3;font-size:12px;margin:24px 0 0">You are getting this once because you confirmed your email. '
        . '<a href="' . e($unsub) . '" style="color:#8895a3">Turn off emails like this</a>.</p>',
        'Finish your profile', url('welcome'));
    $text = "Welcome to RuinMyTrip.

Reviews: " . url('review/new') . "
Travel buddies: " . url('buddies')
          . "
Hosts & Locals: " . url('hosts-locals') . "
Travel map: " . url('map') . "

Turn these off: " . $unsub;
    [$ok] = rmt_mail_send((string) $u['email'], 'Welcome to RuinMyTrip', $html, $text, rmt_mail_unsub_headers($unsub));
    if ($ok && function_exists('rmt_track')) rmt_track('welcome_email_sent');
    return (bool) $ok;
}

/**
 * Email categories a member can switch off one by one at /settings/email. The category is read
 * from where the email links to, so every existing caller is covered without a new argument.
 */
const RMT_EMAIL_CATEGORIES = [
    'messages' => ['Messages', 'Private messages and trip chats'],
    'requests' => ['Requests and answers', 'Stay requests, travelers asking to meet or join you, and their answers'],
    'social'   => ['Replies and mentions', 'Replies to what you posted, comments and @mentions'],
    'trips'    => ['Trips and matches', 'Somebody overlapping your dates, trip invitations and plans'],
    'other'    => ['Everything else', 'Reminders and anything not listed above'],
];

function rmt_email_category(string $path): string {
    $p = (string) (parse_url($path, PHP_URL_PATH) ?? $path);
    if (str_starts_with($p, '/messages') || preg_match('#^/trip/\d+/chat#', $p)) return 'messages';
    if (preg_match('#^/(stay|stays|buddies|buddy|hosts-locals|activity|connect)(/|$)#', $p)) return 'requests';
    if (preg_match('#^/(post|review|blog|guide|talk|u)(/|$)#', $p)) return 'social';
    if (preg_match('#^/(trip|matches|plan|going|e)(/|$)#', $p)) return 'trips';
    return 'other';
}

/** @return list<string> */
function rmt_email_muted_list(int $userId): array {
    /* A database without migration 119 (an old test schema, or the minute before a deploy migrates)
       has no column to read, which means nothing is muted rather than no email at all. */
    try {
        $raw = (string) (q_one('SELECT email_muted FROM profiles WHERE user_id = ?', [$userId])['email_muted'] ?? '');
    } catch (\PDOException $e) {
        return [];
    }
    return array_values(array_intersect(array_keys(RMT_EMAIL_CATEGORIES), explode(',', $raw)));
}

function rmt_email_muted(int $userId, string $category): bool {
    return in_array($category, rmt_email_muted_list($userId), true);
}

/** GET /settings/email */
function settings_email_form(array $a): void {
    require_login();
    $me = current_user();
    rmt_profile_ensure((int) $me['id']);
    $p = q_one('SELECT digest_opt_out FROM profiles WHERE user_id = ?', [(int) $me['id']]) ?: [];
    view('settings_email', ['me' => $me, 'muted' => rmt_email_muted_list((int) $me['id']), 'all_off' => (int) ($p['digest_opt_out'] ?? 0) === 1],
         ['title' => 'Email settings | RuinMyTrip', 'robots' => 'noindex, nofollow', 'app_shell' => true]);
}

/** POST /settings/email */
function settings_email_save(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $uid = (int) $me['id'];
    rmt_profile_ensure($uid);
    $on = array_map('strval', (array) ($_POST['on'] ?? []));
    $muted = array_values(array_diff(array_keys(RMT_EMAIL_CATEGORIES), $on));
    $allOff = !empty($_POST['all_off']) ? 1 : 0;
    q_run('UPDATE profiles SET email_muted = ?, digest_opt_out = ? WHERE user_id = ?', [implode(',', $muted), $allOff, $uid]);
    flash($allOff ? 'Saved. RuinMyTrip will not email you, except account and security emails.' : 'Saved.');
    redirect('/settings/email');
}

