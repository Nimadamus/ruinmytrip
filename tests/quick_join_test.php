<?php
/**
 * Quick join and Sign in with Google (app/quick_join.php, Q3 2026-10-01).
 *
 * Joining is an email, a password and a 16+ tick; the username is drawn from the email and must be
 * unique; nobody gets in without the tick; a date of birth is no longer required, but when one is
 * given it is still checked. Google ID token claims are refused on any wrong issuer, audience,
 * expiry, nonce or unverified address.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
const RMT_EDITORIAL_ROLE = 'editorial';
putenv('RESEND_API_KEY');
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/csrf.php';
require BASE_PATH . '/app/mail.php';
require BASE_PATH . '/app/tokens.php';
require BASE_PATH . '/app/auth.php';
require BASE_PATH . '/app/quick_join.php';
if (session_status() !== PHP_SESSION_ACTIVE) { $_SESSION = []; }

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-56s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'user', birthdate TEXT, status TEXT NOT NULL DEFAULT 'active',
            created_at TEXT NOT NULL, email_verified_at TEXT, google_sub TEXT, age_confirmed_at TEXT)");
$pdo->exec("CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, credibility_score INTEGER NOT NULL DEFAULT 0)");
$pdo->exec("CREATE TABLE email_tokens (id INTEGER PRIMARY KEY, user_id INT, purpose TEXT, token_hash TEXT, expires_at TEXT, used_at TEXT, created_at TEXT)");

echo "\nUsernames come from the email and never collide:\n";
ok('a plain local part is used as it is', rmt_username_from('maria.lopez'), 'maria_lopez');
ok('a short one becomes traveler', rmt_username_from('a'), 'traveler');
$pdo->exec("INSERT INTO users (username,email,password_hash,created_at) VALUES ('maria_lopez','x@y.z','h','2026-01-01')");
$u = rmt_username_from('maria.lopez');
ok('a taken one gets a number', (bool) preg_match('/^maria_lopez\d+$/', $u), true);

echo "\nThe short form:\n";
$r = @rmt_register_quick('new.person@example.com', 'longenough1', false);
ok('no 16+ tick, no account', $r['ok'], false);
ok('and nothing was written', (int) q_one("SELECT COUNT(*) c FROM users WHERE email='new.person@example.com'")['c'], 0);
$r = @rmt_register_quick('new.person@example.com', 'longenough1', true);
ok('with the tick, an account', $r['ok'], true);
$row = q_one("SELECT * FROM users WHERE email='new.person@example.com'");
ok('its username came from the email', $row['username'], 'new_person');
ok('no date of birth was needed', $row['birthdate'], null);
ok('the 16+ confirmation is recorded', !empty($row['age_confirmed_at']), true);
ok('the password works', password_verify('longenough1', (string) $row['password_hash']), true);
$r = @register_user('kid_one', 'kid@example.com', 'longenough1', date('Y-m-d', strtotime('-12 years')), true);
ok('a date of birth under 16 is still refused, tick or not', $r['ok'], false);
$r = @rmt_register_quick('short@example.com', 'short', true);
ok('a short password is refused', $r['ok'], false);

echo "\nGoogle ID token claims:\n";
$good = ['iss' => 'https://accounts.google.com', 'aud' => 'cid', 'exp' => time() + 300, 'nonce' => 'n1',
         'email_verified' => true, 'sub' => '1234567890', 'email' => 'g@example.com'];
ok('good claims pass', rmt_google_claims_problem($good, 'n1', 'cid'), null);
ok('wrong issuer', rmt_google_claims_problem(['iss' => 'https://evil.example'] + $good, 'n1', 'cid'), 'iss');
ok('wrong audience', rmt_google_claims_problem($good, 'n1', 'other'), 'aud');
ok('no client id configured', rmt_google_claims_problem($good, 'n1', ''), 'aud');
ok('expired', rmt_google_claims_problem(['exp' => time() - 1] + $good, 'n1', 'cid'), 'exp');
ok('nonce mismatch', rmt_google_claims_problem($good, 'n2', 'cid'), 'nonce');
ok('empty session nonce', rmt_google_claims_problem(['nonce' => ''] + $good, '', 'cid'), 'nonce');
ok('unverified email', rmt_google_claims_problem(['email_verified' => false] + $good, 'n1', 'cid'), 'unverified');
ok('missing sub', rmt_google_claims_problem(['sub' => ''] + $good, 'n1', 'cid'), 'fields');
ok('disabled without credentials', rmt_google_enabled(), getenv('GOOGLE_CLIENT_ID') && getenv('GOOGLE_CLIENT_SECRET') ? true : false);

printf("\n%s: %d passed, %d failed\n", basename(__FILE__, '.php'), $pass, $fail);
exit($fail ? 1 : 0);
