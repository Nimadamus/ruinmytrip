<?php
/**
 * Regression test: current_user() must exclude suspended/removed accounts.
 *
 * Every other status-sensitive check in the app (follow_action, rmt_qualifies_founding_traveler,
 * forgot_submit, the sitemap) already gates on users.status='active' -- but current_user(), the
 * single function every authenticated action goes through, did not check status at all. A user
 * suspended or removed while already logged in kept full access under their existing session
 * until they happened to log out themselves; suspending them had no immediate effect.
 *
 * current_user() memoizes its result in a function-local `static` variable, so calling it twice
 * in one process (e.g. after flipping $_SESSION['uid'] or the DB row) returns the first call's
 * cached answer, not a fresh query. Testing that honestly means one call per process -- so this
 * spawns a fresh `php` subprocess per case rather than working around the cache in-process.
 *
 * Each case is written out as a real temp PHP file and run with the real php binary -- passing
 * source through `php -r` plus shell escaping mangled Windows backslash paths unpredictably; a
 * file sidesteps shell quoting entirely.
 *
 *   php tests/current_user_status_test.php   -> PASS/FAIL per case, exits non-zero on failure.
 */
declare(strict_types=1);

$phpBin = PHP_BINARY;
$iniPath = dirname(__DIR__) . '/php.local.ini';
$iniFlag = is_file($iniPath) ? '-c ' . escapeshellarg($iniPath) : '';

function run_case(string $phpBin, string $iniFlag, string $status, ?int $sessionUid): string {
    $uidLiteral = $sessionUid === null ? 'null' : (string) $sessionUid;
    $tpl = <<<'PHP'
        <?php
        define('BASE_PATH', __DIR__);
        $GLOBALS['config'] = ['app_env'=>'test','app_url'=>'https://example.test','app_name'=>'RuinMyTrip',
                                'db_driver'=>'sqlite','sqlite_path'=>':memory:'];
        require BASE_PATH . '/app/db.php';
        require BASE_PATH . '/app/helpers.php';
        require BASE_PATH . '/app/auth.php';
        $pdo = db();
        $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, password_hash TEXT, status TEXT)");
        $pdo->exec("CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, avatar_url TEXT, bio TEXT, home_city TEXT, credibility_score INTEGER)");
        $pdo->prepare("INSERT INTO users (id,username,email,password_hash,status) VALUES (1,'alice','alice@example.com','x',?)")->execute([__STATUS__]);
        $_SESSION['uid'] = __SESSION_UID__;
        $u = current_user();
        echo $u === null ? 'null' : 'user:' . $u['username'];
        PHP;
    // This file lives directly in tests/, so __DIR__ inside it IS the repo root's tests dir --
    // BASE_PATH needs the parent of that, matching every other test harness in this suite.
    $tpl = str_replace("define('BASE_PATH', __DIR__);", "define('BASE_PATH', dirname(__DIR__));", $tpl);
    $code = str_replace(['__STATUS__', '__SESSION_UID__'], [var_export($status, true), $uidLiteral], $tpl);

    $tmpFile = __DIR__ . '/_tmp_current_user_case.php';
    file_put_contents($tmpFile, $code);
    $cmd = $phpBin . ' ' . $iniFlag . ' ' . escapeshellarg($tmpFile) . ' 2>&1';
    $out = trim((string) shell_exec($cmd));
    unlink($tmpFile);
    return $out;
}

$fail = 0;
$check = function (string $name, $got, $expect) use (&$fail) {
    $ok = $got === $expect;
    printf("  [%s] %-55s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
        var_export($expect, true), var_export($got, true));
    if (!$ok) $fail++;
};

$check('active user with a valid session -> returned', run_case($phpBin, $iniFlag, 'active', 1), 'user:alice');
$check('suspended user with a valid session -> null (logged out in effect)', run_case($phpBin, $iniFlag, 'suspended', 1), 'null');
$check('removed user with a valid session -> null (logged out in effect)', run_case($phpBin, $iniFlag, 'removed', 1), 'null');
$check('no session uid at all -> null', run_case($phpBin, $iniFlag, 'active', null), 'null');
$check('session uid pointing at a nonexistent user -> null', run_case($phpBin, $iniFlag, 'active', 999), 'null');

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL CURRENT_USER STATUS TESTS PASS\n";
