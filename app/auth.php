<?php
declare(strict_types=1);

function current_user(): ?array {
    static $cached = false, $user = null;
    if ($cached) return $user;
    $cached = true;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return $user = null;
    // Every other status-sensitive check in the app (follow, founding-traveler badge, password
    // reset, sitemap) already gates on status='active' -- but this one, the single function every
    // authenticated action goes through, did not. Suspending or removing a user had zero effect on
    // a session they already held: they kept full access until they happened to log out.
    $user = q_one("SELECT u.*, p.display_name, p.avatar_url, p.bio, p.home_city, p.credibility_score
                   FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = ? AND u.status = 'active'", [$id]);
    return $user;
}

function is_logged_in(): bool { return current_user() !== null; }

function require_login(): void {
    if (!is_logged_in()) {
        // A logged-out visit to any protected route (a notification link, a deep link, a bookmark)
        // always dropped the user on /feed after signing in, discarding wherever they were actually
        // headed -- most noticeable on mobile, where re-finding a specific page by hand is worse.
        //
        // POST-only action endpoints (comment, react, follow, report, meetup RSVP) have no GET
        // route at all -- capturing their own REQUEST_URI as the return target sent a freshly
        // logged-in user to a dead 404 instead of back to the page they were actually on
        // (confirmed live: a session expiring mid comment-submit redirected to /login?return=
        // %2Fcomment, and GET /comment is a 404). Those forms already carry their own `return`
        // field pointing at that real page for their own post-success redirect; prefer it here.
        $return = (string) (input('return') ?: ($_SERVER['REQUEST_URI'] ?? '/'));
        flash('Please sign in to continue.');
        redirect('/login?return=' . urlencode($return));
    }
}

/**
 * Only ever redirect back to a same-site relative path. Without this, `return` becomes an open
 * redirect: /login?return=https://evil.example would send a freshly-authenticated user straight
 * to an attacker's page right after they typed their password.
 *
 * Action forms (comment, react, follow, report, meetup RSVP) build their `return` field with
 * url(), which always returns a same-origin ABSOLUTE URL, not a bare path -- confirmed live: one
 * of those return values reached this function as "https://ruinmytrip.com/trip/22/...", which
 * the checks below correctly reject on their own (it contains "://"), so it fell back to /feed
 * instead of the trip page. Strip a same-origin prefix down to a plain path first so those
 * values validate normally; anything that ISN'T our own origin is untouched by the strip and
 * still gets rejected by the checks that follow.
 */
function rmt_safe_return_path(string $path): string {
    $appUrl = rtrim((string) cfg('app_url'), '/');
    if ($appUrl !== '') {
        if ($path === $appUrl) $path = '/';
        elseif (str_starts_with($path, $appUrl . '/')) $path = substr($path, strlen($appUrl));
    }
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '://')) {
        return '/feed';
    }
    return $path;
}

function require_role(string ...$roles): void {
    $u = current_user();
    if (!$u || !in_array($u['role'], $roles, true)) { forbidden('You are not authorized to view this page.'); }
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

function register_user(string $username, string $email, string $password, string $birthdate): array {
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
    // The two uniqueness checks above are check-then-act: two near-simultaneous submissions (a
    // double-click on a slow connection, or two tabs) for the same email/username can both pass
    // them before either INSERT lands. The UNIQUE constraints on users.username/email stop the
    // duplicate row, but without this catch the loser got an uncaught PDOException (500 page)
    // instead of the same friendly "already taken" message the pre-check gives everyone else.
    try {
        $id = q_run('INSERT INTO users (username, email, password_hash, role, birthdate, status, created_at)
                     VALUES (?,?,?,?,?,?,?)',
                    [$username, $email, $hash, 'user', $birthdate, 'active', date('Y-m-d H:i:s')]);
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        if (q_one('SELECT id FROM users WHERE email = ?', [$email])) $errors[] = 'That email is already registered.';
        if (q_one('SELECT id FROM users WHERE username = ?', [$username])) $errors[] = 'That username is taken.';
        return ['ok' => false, 'errors' => $errors ?: ['That email or username is already taken.']];
    }
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
