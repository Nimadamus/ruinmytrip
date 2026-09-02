<?php
/**
 * Personal invite links (migration 068).
 *
 * Growth here is members bringing members: the first hundred travelers are recruited by hand,
 * and the people they bring are recruited by them. This gives every member a link that says who
 * sent it, remembers that through the signup, and tells the sender when somebody they invited
 * arrived. Nothing is paid for, nothing is ranked by it, and a member's count is a fact about
 * what they did, never a target.
 *
 *   https://ruinmytrip.com/?ref=ana   ->  session + 30-day cookie  ->  users.invited_by on signup
 */
declare(strict_types=1);

const RMT_INVITE_COOKIE = 'rmt_ref';
const RMT_INVITE_TTL    = 30 * 86400;

/** Record ?ref=username from any landing URL. Called once per request after the session starts. */
function rmt_invite_capture(): void {
    $ref = trim((string) ($_GET['ref'] ?? ''));
    if ($ref === '' || !preg_match('/^[A-Za-z0-9_]{3,24}$/', $ref)) return;
    $u = q_one("SELECT id, username FROM users WHERE LOWER(username)=LOWER(?) AND status='active'", [$ref]);
    if (!$u) return;
    $_SESSION['ref'] = (string) $u['username'];
    if (!headers_sent()) {
        setcookie(RMT_INVITE_COOKIE, (string) $u['username'], [
            'expires' => time() + RMT_INVITE_TTL, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => cfg('app_env') === 'production',
        ]);
    }
}

/** The username that sent the current visitor here, if any. */
function rmt_invite_referrer_name(): ?string {
    $n = (string) ($_SESSION['ref'] ?? $_COOKIE[RMT_INVITE_COOKIE] ?? '');
    return $n !== '' && preg_match('/^[A-Za-z0-9_]{3,24}$/', $n) ? $n : null;
}

/** The active member behind that name, or null. */
function rmt_invite_referrer(): ?array {
    $n = rmt_invite_referrer_name();
    if ($n === null) return null;
    $u = q_one("SELECT u.id, u.username, p.display_name, p.avatar_url FROM users u LEFT JOIN profiles p ON p.user_id=u.id
                 WHERE LOWER(u.username)=LOWER(?) AND u.status='active'", [$n]);
    return $u ?: null;
}

/**
 * Attach a freshly created account to whoever invited it, and tell them. Idempotent: an account
 * that already has an inviter keeps it. A member cannot invite themselves.
 */
function rmt_invite_attach(int $newUserId, ?int $referrerId = null): bool {
    if ($referrerId === null) {
        $r = rmt_invite_referrer();
        $referrerId = $r ? (int) $r['id'] : null;
    }
    rmt_invite_forget();
    if (!$referrerId || $referrerId === $newUserId) return false;
    $row = q_one('SELECT invited_by FROM users WHERE id=?', [$newUserId]);
    if (!$row || !empty($row['invited_by'])) return false;
    if (!q_one("SELECT 1 x FROM users WHERE id=? AND status='active'", [$referrerId])) return false;
    q_run('UPDATE users SET invited_by=? WHERE id=?', [$referrerId, $newUserId]);
    q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
          [$referrerId, 'invite_joined', $newUserId, 'user', $newUserId, date('Y-m-d H:i:s')]);
    return true;
}

function rmt_invite_forget(): void {
    unset($_SESSION['ref']);
    if (isset($_COOKIE[RMT_INVITE_COOKIE]) && !headers_sent()) {
        setcookie(RMT_INVITE_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
}

function rmt_invite_link(array $user): string {
    return url('?ref=' . rawurlencode((string) $user['username']));
}

/** Active members this person brought. Live count. */
function rmt_invite_count(int $uid): int {
    return (int) (q_one("SELECT COUNT(*) c FROM users WHERE invited_by=? AND status='active'", [$uid])['c'] ?? 0);
}

/** @return list<array{id:int,username:string,display_name:?string,avatar_url:?string,created_at:string}> */
function rmt_invite_recent(int $uid, int $limit = 20): array {
    return q_all("SELECT u.id, u.username, u.created_at, p.display_name, p.avatar_url
                    FROM users u LEFT JOIN profiles p ON p.user_id=u.id
                   WHERE u.invited_by=? AND u.status='active' ORDER BY u.id DESC LIMIT " . (int) $limit, [$uid]);
}

/** The message a member sends with the link. Short, human, and honest about what the site is. */
function rmt_invite_message(array $user): string {
    return "I'm on RuinMyTrip, a travel site where people say what actually went wrong. Join me: " . rmt_invite_link($user);
}
