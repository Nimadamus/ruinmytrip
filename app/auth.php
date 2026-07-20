<?php
declare(strict_types=1);

function current_user(): ?array {
    static $cached = false, $user = null;
    if ($cached) return $user;
    $cached = true;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return $user = null;
    $user = q_one('SELECT u.*, p.display_name, p.avatar_url, p.bio, p.home_city, p.credibility_score
                   FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = ?', [$id]);
    return $user;
}

function is_logged_in(): bool { return current_user() !== null; }

function require_login(): void {
    if (!is_logged_in()) { flash('Please sign in to continue.'); redirect('/login'); }
}

function require_role(string ...$roles): void {
    $u = current_user();
    if (!$u || !in_array($u['role'], $roles, true)) { http_response_code(403); exit('403 — not authorized.'); }
}

function attempt_login(string $email, string $password): bool {
    $u = q_one('SELECT * FROM users WHERE email = ?', [strtolower($email)]);
    if (!$u || $u['status'] === 'suspended') {
        // Spend roughly the same time as a real verify so response timing doesn't reveal
        // whether the address exists.
        password_verify($password, '$2y$10$usesomesillystringforsalttoburnthesamecputime.aaaaaa');
        return false;
    }
    if (!password_verify($password, $u['password_hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    return true;
}

/**
 * Is this account's email confirmed?
 *
 * Accounts created BEFORE email verification existed have email_verified_at = NULL but must not
 * be locked out — they are grandfathered by created_at. Only accounts created from the cutover
 * onward are held to verification.
 */
const RMT_VERIFY_ENFORCED_FROM = '2026-07-17 12:00:00';

function email_is_verified(?array $u): bool {
    if (!$u) return false;
    if (!empty($u['email_verified_at'])) return true;
    return strtotime((string)($u['created_at'] ?? '')) < strtotime(RMT_VERIFY_ENFORCED_FROM);
}

/**
 * Gate for actions that publish content. Reading is never gated — an unverified account can
 * browse, it just cannot post until it confirms an address we can actually reach.
 */
function require_verified_email(): void {
    require_login();
    if (!email_is_verified(current_user())) {
        flash('Confirm your email address before posting. Check your inbox for the link.');
        redirect('/verify-email');
    }
}

/** Issue a verification token and email it. Returns [ok, detail]; never throws. */
function send_verification_email(array $u): array {
    try {
        $raw = rmt_token_issue((int)$u['id'], 'verify');
        $link = rtrim(cfg('app_url'), '/') . '/verify-email?token=' . $raw;
        return rmt_mail_verification((string)$u['email'], (string)$u['username'], $link);
    } catch (Throwable $e) {
        return [false, 'token/mail error: ' . $e->getMessage()];
    }
}

/** Issue a reset token and email it. Returns [ok, detail]; never throws. */
function send_password_reset_email(array $u): array {
    try {
        $raw = rmt_token_issue((int)$u['id'], 'reset');
        $link = rtrim(cfg('app_url'), '/') . '/reset-password?token=' . $raw;
        return rmt_mail_password_reset((string)$u['email'], (string)$u['username'], $link);
    } catch (Throwable $e) {
        return [false, 'token/mail error: ' . $e->getMessage()];
    }
}

function register_user(string $username, string $email, string $password, string $birthdate, ?string $ref = null): array {
    $errors = [];
    $username = trim($username); $email = strtolower(trim($email));
    if (!preg_match('/^[a-zA-Z0-9_]{3,24}$/', $username)) $errors[] = 'Username must be 3–24 letters, numbers, or underscores.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if (age_from($birthdate) < 16) $errors[] = 'You must be at least 16 to join RuinMyTrip.';
    if (!$errors) {
        if (q_one('SELECT id FROM users WHERE email = ?', [$email])) $errors[] = 'That email is already registered.';
        if (q_one('SELECT id FROM users WHERE username = ?', [$username])) $errors[] = 'That username is taken.';
    }
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $hash = password_hash($password, PASSWORD_BCRYPT);
    // Referral is recorded only when ?ref= resolved to a real active member. It grants nothing,
    // so a forged or absent ref is harmless — it exists to show members their invites landed.
    $referrer = rmt_referrer_username($ref);
    if ($referrer === $username) $referrer = null;
    $id = q_run('INSERT INTO users (username, email, password_hash, role, birthdate, status, created_at, referred_by)
                 VALUES (?,?,?,?,?,?,?,?)',
                [$username, $email, $hash, 'user', $birthdate, 'active', date('Y-m-d H:i:s'), $referrer]);
    q_run('INSERT INTO profiles (user_id, display_name, credibility_score) VALUES (?,?,0)', [$id, $username]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$id;

    // Send the confirmation link. A mail failure must NOT fail the signup — the account exists
    // and the user can request a fresh link from /verify-email.
    $mail = send_verification_email(['id' => (int)$id, 'email' => $email, 'username' => $username]);
    return ['ok' => true, 'id' => (int)$id, 'mail_ok' => $mail[0], 'mail_detail' => $mail[1]];
}

function logout(): void { $_SESSION = []; session_destroy(); }

function can_host_meetups(?array $u): bool {
    return $u && !empty($u['birthdate']) && age_from($u['birthdate']) >= 18;
}
