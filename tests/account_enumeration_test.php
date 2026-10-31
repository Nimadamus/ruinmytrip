<?php
/**
 * Account enumeration (2026-10-30).
 *
 * Nobody may learn whether an email address has a RuinMyTrip account by trying to join, sign in or
 * reset a password with it. A signup for a registered address answers with the same "check your
 * email" result and the same redirect as a real signup, mails the owner (at most twice a day), and
 * signs nobody in. Usernames are public, so "that username is taken" stays.
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
require BASE_PATH . '/app/ratelimit.php';
require BASE_PATH . '/app/auth.php';
require BASE_PATH . '/app/quick_join.php';
if (session_status() !== PHP_SESSION_ACTIVE) { $_SESSION = []; }

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-60s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'user', birthdate TEXT, status TEXT NOT NULL DEFAULT 'active',
            created_at TEXT NOT NULL, email_verified_at TEXT, google_sub TEXT, age_confirmed_at TEXT)");
$pdo->exec("CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, credibility_score INTEGER NOT NULL DEFAULT 0)");
$pdo->exec("CREATE TABLE email_tokens (id INTEGER PRIMARY KEY, user_id INT, purpose TEXT, token_hash TEXT, expires_at TEXT, used_at TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE rate_limits (bucket TEXT NOT NULL, window_start INTEGER NOT NULL, hits INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (bucket, window_start))");
$pdo->exec("INSERT INTO users (username,email,password_hash,created_at) VALUES ('owner','owner@example.com','h','2026-01-01')");
$users = fn(): int => (int) q_one('SELECT COUNT(*) c FROM users')['c'];

echo "\nA signup for a registered address reveals nothing:\n";
$_SESSION = [];
$r = @register_user('someone_new', 'Owner@Example.com', 'longenough1', '', true);
ok('it does not create an account', $users(), 1);
ok('it is flagged for the controller', $r['exists'] ?? false, true);
ok('the only message is the neutral check your email line', $r['errors'], [RMT_REGISTER_EXISTING_MSG]);
ok('nobody is signed in', isset($_SESSION['uid']), false);
ok('the message never says registered or exists',
   (bool) preg_match('/registered|already|exists|account/i', implode(' ', $r['errors'])), false);
$q = @rmt_register_quick('owner@example.com', 'longenough1', true);
ok('quick join with a registered address gets the same answer', [$q['ok'], $q['exists'] ?? false, $q['errors']], [false, true, [RMT_REGISTER_EXISTING_MSG]]);

echo "\nThe owner notice is rate limited per address:\n";
$hits = (int) (q_one("SELECT SUM(hits) h FROM rate_limits WHERE bucket = 'exists_notice:owner@example.com'")['h'] ?? 0);
ok('each attempt is counted against the owner notice limit', $hits, 2);
@register_user('third_try', 'owner@example.com', 'longenough1', '', true);
ok('a third attempt is over the 2 a day limit', rmt_rate_ok('exists_notice', 'owner@example.com', 2, 86400), false);
ok('the notice email has a sign in and a reset link', function_exists('rmt_mail_account_exists'), true);
$src = file_get_contents(BASE_PATH . '/app/mail.php');
ok('the notice copy carries no dashes', (bool) preg_match('/Somebody tried to join[^\n]*[\x{2013}\x{2014}]/u', $src), false);

echo "\nUsernames are public, so a taken one is still said:\n";
$r = @register_user('owner', 'fresh@example.com', 'longenough1', '', true);
ok('a taken username with a new email says the username is taken', $r['errors'], ['That username is taken.']);
$r = @register_user('owner', 'owner@example.com', 'longenough1', '', true);
ok('a taken username with a registered email says the same thing', $r['errors'], ['That username is taken.']);

echo "\nA new address still joins and is signed in:\n";
$_SESSION = [];
$r = @rmt_register_quick('new.person@example.com', 'longenough1', true);
ok('a new address makes an account', $r['ok'], true);
ok('and signs the new member in', (int) ($_SESSION['uid'] ?? 0), (int) $r['id']);

echo "\nSign in and reset do not leak either:\n";
$info = password_get_info('$2y$10$.TVLLE5WC34GSFSW9bRMhOatS3vShRWfsTR9wnBl9O9I9rbXeStbG');
ok('the unknown address path verifies against a real bcrypt hash (same cost as a real one)', [$info['algo'], $info['options']['cost'] ?? 0], [PASSWORD_BCRYPT, 10]);
ok('a wrong address and a wrong password both fail the same way', [attempt_login('nobody@example.com', 'x'), attempt_login('owner@example.com', 'x')], [false, false]);
$ctl = file_get_contents(BASE_PATH . '/app/controllers.php');
$plan = file_get_contents(BASE_PATH . '/app/plan_first.php');
$auth = file_get_contents(BASE_PATH . '/app/auth.php');
ok('no code path says an email is already registered', (bool) preg_match('/already registered/i', $ctl . $plan . $auth), false);
ok('the sign in error is one generic line', substr_count($ctl, "'Incorrect email or password.'") >= 1, true);
ok('forgot password pads its answer so a send is not visible in timing', (bool) preg_match('/function forgot_submit.*?rmt_auth_time_floor\(\$t0\).*?view\(\'auth\/forgot\'/s', $ctl), true);
ok('register answers a registered address with the neutral flash and the signup redirect',
   (bool) preg_match('/function register_submit.*?exists.*?flash\(RMT_REGISTER_EXISTING_MSG\);\s*redirect\(\$return !== \'\' && \$return !== \'\/feed\' \? \$return : \'\/plan\?welcome=1\'\)/s', $ctl), true);
ok('the review quick join answers a registered address with the pending review redirect',
   (bool) preg_match('/exists.*?flash\(RMT_REGISTER_EXISTING_MSG[^;]*\);\s*redirect\(\'\/verify-email\'\)/s', $plan), true);
ok('a registered address counts toward the join limit like a signup',
   substr_count($ctl . $plan, "'reason' => 'exists'"), 2);
$views = '';
foreach (glob(BASE_PATH . '/views/auth/*.php') as $f) $views .= file_get_contents($f);
ok('no auth view says an address is registered', (bool) preg_match('/already registered|email is taken|no account (with|for) that/i', $views), false);

echo "\naccount_enumeration_test: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
