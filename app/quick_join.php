<?php
declare(strict_types=1);

/**
 * Quick join and Sign in with Google (Q3, 2026-10-01).
 *
 * Joining asked for four things up front: a username, an email, a password and a date of birth.
 * It now asks for an email and a password (or one tap with Google) and a tick that says the person
 * is 16 or older. The username is drawn from the email and can be changed in settings; the date of
 * birth is asked for only where it decides something (hosting a meetup is 18+). Everything a
 * profile needs beyond that is gathered after the account exists, starting with the trip.
 *
 * Google: the authorization code flow, server side, with a state token and a nonce held in the
 * session. The ID token comes straight from Google's token endpoint over TLS in exchange for our
 * client secret, which is the case in which Google's own documentation says the signature need
 * not be re-verified; its issuer, audience, expiry, nonce and email_verified are still checked.
 * An existing account with the same address is linked only when Google says the address is
 * verified, and if that account had never confirmed its address its password is replaced, so a
 * stranger who pre-registered somebody else's email cannot keep a way in after the owner arrives.
 */

const RMT_GOOGLE_AUTH  = 'https://accounts.google.com/o/oauth2/v2/auth';
const RMT_GOOGLE_TOKEN = 'https://oauth2.googleapis.com/token';
const RMT_GOOGLE_ISS   = ['accounts.google.com', 'https://accounts.google.com'];

function rmt_google_client_id(): string { return trim((string) getenv('GOOGLE_CLIENT_ID')); }
function rmt_google_client_secret(): string { return trim((string) getenv('GOOGLE_CLIENT_SECRET')); }
function rmt_google_enabled(): bool { return rmt_google_client_id() !== '' && rmt_google_client_secret() !== ''; }
function rmt_google_redirect_uri(): string { return abs_url('/auth/google/callback'); }

/** A free username drawn from an email or a name: letters, digits, underscores, 3 to 24. */
function rmt_username_from(string $seed): string {
    $base = strtolower((string) preg_replace('/[^A-Za-z0-9_]+/', '_', $seed));
    $base = trim((string) preg_replace('/_+/', '_', $base), '_');
    if (strlen($base) < 3) $base = 'traveler';
    $base = substr($base, 0, 18);
    $try = $base;
    for ($i = 0; $i < 50; $i++) {
        if (!q_one('SELECT id FROM users WHERE username = ?', [$try])) return $try;
        $try = substr($base, 0, 18) . random_int(10, 99999);
    }
    return 'traveler' . bin2hex(random_bytes(4));
}

/** Record the 16+ confirmation. A missing column (an old schema) costs the timestamp, never the signup. */
function rmt_mark_age_confirmed(int $uid): void {
    try { q_run('UPDATE users SET age_confirmed_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $uid]); }
    catch (Throwable $e) { /* migration 104 not applied */ }
}

/**
 * The short form: email, password, the 16+ tick, and an optional username.
 * @return array{ok:bool, errors?:list<string>, id?:int, mail_ok?:bool}
 */
function rmt_register_quick(string $email, string $password, bool $ageOk, string $username = ''): array {
    if (!$ageOk) return ['ok' => false, 'errors' => ['Please confirm you are 16 or older. RuinMyTrip is for travelers 16+.']];
    $email = strtolower(trim($email));
    $username = trim($username);
    if ($username === '') $username = rmt_username_from((string) strstr($email, '@', true) ?: $email);
    $r = register_user($username, $email, $password, '', true);
    if ($r['ok']) rmt_mark_age_confirmed((int) $r['id']);
    return $r;
}

/** Sign a member in and run the same bookkeeping as a password sign in. */
function rmt_login_user_id(int $uid): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
}

/* ---------- controllers ---------- */

/** GET /auth/google  start: remember where they were going, send them to Google. */
function google_start(array $a): void {
    if (!rmt_google_enabled()) { flash('Google sign in is not available right now. Use your email instead.'); redirect('/register'); }
    $_SESSION['g_state'] = bin2hex(random_bytes(16));
    $_SESSION['g_nonce'] = bin2hex(random_bytes(16));
    $_SESSION['g_return'] = rmt_safe_return_path((string) input('return'));
    rmt_track('cta_click', ['detail' => 'google_signin', 'source' => rmt_join_source((string) $_SESSION['g_return'])]);
    $q = http_build_query([
        'client_id' => rmt_google_client_id(), 'redirect_uri' => rmt_google_redirect_uri(),
        'response_type' => 'code', 'scope' => 'openid email profile',
        'state' => $_SESSION['g_state'], 'nonce' => $_SESSION['g_nonce'], 'prompt' => 'select_account',
    ]);
    redirect(RMT_GOOGLE_AUTH . '?' . $q);
}

/** Exchange the code for Google's ID token claims, or null with a reason in $why. */
function rmt_google_claims(string $code, ?string &$why = null): ?array {
    $body = http_build_query([
        'code' => $code, 'client_id' => rmt_google_client_id(), 'client_secret' => rmt_google_client_secret(),
        'redirect_uri' => rmt_google_redirect_uri(), 'grant_type' => 'authorization_code',
    ]);
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15, 'ignore_errors' => true,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body]]);
    $raw = @file_get_contents(RMT_GOOGLE_TOKEN, false, $ctx);
    $tok = is_string($raw) ? json_decode($raw, true) : null;
    $jwt = is_array($tok) ? (string) ($tok['id_token'] ?? '') : '';
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) { $why = 'token'; return null; }
    $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);
    if (!is_array($claims)) { $why = 'claims'; return null; }
    $why = rmt_google_claims_problem($claims, (string) ($_SESSION['g_nonce'] ?? ''), rmt_google_client_id());
    return $why === null ? $claims : null;
}

/** Why these ID token claims are not acceptable, or null when they are. */
function rmt_google_claims_problem(array $claims, string $nonce, string $clientId, ?int $now = null): ?string {
    if (!in_array((string) ($claims['iss'] ?? ''), RMT_GOOGLE_ISS, true)) return 'iss';
    if ($clientId === '' || (string) ($claims['aud'] ?? '') !== $clientId) return 'aud';
    if ((int) ($claims['exp'] ?? 0) < ($now ?? time())) return 'exp';
    if ($nonce === '' || !hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) return 'nonce';
    if (($claims['email_verified'] ?? false) !== true && ($claims['email_verified'] ?? '') !== 'true') return 'unverified';
    if ((string) ($claims['sub'] ?? '') === '' || !filter_var((string) ($claims['email'] ?? ''), FILTER_VALIDATE_EMAIL)) return 'fields';
    return null;
}

/** Where a member goes after Google: their held trip or question first, then where they were headed. */
function rmt_after_google(array $me, string $return, bool $isNew): void {
    $draft = $_SESSION[RMT_PLAN_DRAFT_KEY] ?? null;
    if (is_array($draft)) { unset($_SESSION[RMT_PLAN_DRAFT_KEY]); rmt_plan_first_hand_over($me, $draft); }
    if ($return !== '' && $return !== '/feed' && $return !== '/') redirect($return);
    if ($isNew) redirect('/plan?welcome=1');
    flash('Welcome back.');
    redirect('/feed');
}

/** GET /auth/google/callback */
function google_callback(array $a): void {
    $state = (string) input('state');
    $return = (string) ($_SESSION['g_return'] ?? '');
    $okState = $state !== '' && hash_equals((string) ($_SESSION['g_state'] ?? ''), $state);
    unset($_SESSION['g_state']);
    if (!rmt_google_enabled() || !$okState || input('code') === '') {
        rmt_track('join_failure', ['source' => 'google', 'reason' => $okState ? 'cancelled' : 'state']);
        flash('Google sign in did not finish. Try again, or join with your email.');
        redirect('/register');
    }
    $why = null;
    $c = rmt_google_claims((string) input('code'), $why);
    unset($_SESSION['g_nonce']);
    if (!$c) {
        rmt_track('join_failure', ['source' => 'google', 'reason' => 'google_' . $why]);
        flash('Google could not confirm that account. Try again, or join with your email.');
        redirect('/register');
    }
    $sub = (string) $c['sub'];
    $email = strtolower((string) $c['email']);
    $now = date('Y-m-d H:i:s');

    $u = q_one('SELECT * FROM users WHERE google_sub = ?', [$sub]);
    if (!$u) {
        $u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($u) {
            if (!empty($u['google_sub']) && $u['google_sub'] !== $sub) {
                flash('That email belongs to an account linked to a different Google account. Sign in with your password.');
                redirect('/login');
            }
            // Linking. An account that never confirmed this address loses its password: whoever
            // set it had not proved they own the inbox, and Google just proved somebody else does.
            if (email_is_verified($u)) {
                q_run('UPDATE users SET google_sub = ? WHERE id = ?', [$sub, (int) $u['id']]);
            } else {
                q_run('UPDATE users SET google_sub = ?, email_verified_at = ?, password_hash = ? WHERE id = ?',
                      [$sub, $now, password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT), (int) $u['id']]);
            }
        }
    }
    if ($u) {
        if (($u['status'] ?? '') === 'suspended') { flash('This account is suspended.'); redirect('/login'); }
        rmt_login_user_id((int) $u['id']);
        rmt_track('login_completed', ['source' => 'google']);
        rmt_after_google((array) current_user(), $return, false);
    }
    // A new member: one short step for the username and the 16+ tick, then the account exists.
    $_SESSION['g_pending'] = ['sub' => $sub, 'email' => $email, 'name' => (string) ($c['given_name'] ?? $c['name'] ?? ''),
                              'return' => $return, 'at' => time()];
    redirect('/join/google');
}

/** GET /join/google  the one step between Google and an account. */
function google_finish_form(array $a, array $errors = []): void {
    $g = $_SESSION['g_pending'] ?? null;
    if (!is_array($g) || time() - (int) $g['at'] > 1800) redirect('/register');
    $suggest = (string) input('username') ?: rmt_username_from($g['name'] !== '' ? $g['name'] : (string) strstr($g['email'], '@', true));
    rmt_track('join_view', ['source' => 'google']);
    view('auth/google_finish', ['g' => $g, 'suggest' => $suggest, 'errors' => $errors],
         ['title' => 'Almost there | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}

/** POST /join/google */
function google_finish_submit(array $a): void {
    csrf_check();
    $g = $_SESSION['g_pending'] ?? null;
    if (!is_array($g) || time() - (int) $g['at'] > 1800) redirect('/register');
    if (!rmt_rate_ok('register_ip', rmt_client_ip(), 5, 3600)) {
        google_finish_form($a, ['Too many accounts created from this connection. Try again later.']); return;
    }
    rmt_track('join_submit', ['source' => 'google']);
    if (input('age_ok') !== '1') { google_finish_form($a, ['Please confirm you are 16 or older. RuinMyTrip is for travelers 16+.']); return; }
    $r = register_user((string) input('username'), (string) $g['email'], bin2hex(random_bytes(24)), '', true, false);
    if (!$r['ok']) {
        rmt_track('join_failure', ['source' => 'google', 'reason' => 'validation']);
        google_finish_form($a, $r['errors']); return;
    }
    $uid = (int) $r['id'];
    q_run('UPDATE users SET google_sub = ?, email_verified_at = ? WHERE id = ?', [(string) $g['sub'], date('Y-m-d H:i:s'), $uid]);
    rmt_mark_age_confirmed($uid);
    if ($g['name'] !== '') q_run('UPDATE profiles SET display_name = ? WHERE user_id = ?', [mb_substr($g['name'], 0, 60), $uid]);
    unset($_SESSION['g_pending']);
    rmt_track('join_created', ['source' => 'google']);
    rmt_track('join_confirmed', ['source' => 'google']);
    rmt_after_google((array) current_user(), (string) $g['return'], true);
}
