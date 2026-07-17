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
    if (!$u || $u['status'] === 'suspended') return false;
    if (!password_verify($password, $u['password_hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    return true;
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
    $id = q_run('INSERT INTO users (username, email, password_hash, role, birthdate, status, created_at)
                 VALUES (?,?,?,?,?,?,?)',
                [$username, $email, $hash, 'user', $birthdate, 'active', date('Y-m-d H:i:s')]);
    q_run('INSERT INTO profiles (user_id, display_name, credibility_score) VALUES (?,?,0)', [$id, $username]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$id;
    return ['ok' => true, 'id' => (int)$id];
}

function logout(): void { $_SESSION = []; session_destroy(); }

function can_host_meetups(?array $u): bool {
    return $u && !empty($u['birthdate']) && age_from($u['birthdate']) >= 18;
}
