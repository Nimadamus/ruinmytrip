<?php
declare(strict_types=1);

/**
 * Provision and health-check the LOCAL DEVELOPMENT SQLite database.
 *
 * Why this exists
 * ---------------
 * The dev database used to live in the session scratchpad under %LOCALAPPDATA%\Temp. That
 * directory is transient: it was cleared mid-session on 2026-08-12, taking every working file with
 * it and leaving a zero-byte stub where the database had been. The first symptom was
 * "SQLSTATE[HY000]: General error: 1 no such table: users", which reads like schema corruption and
 * is nothing of the sort.
 *
 * Two things went wrong and both are fixed here:
 *
 *   1. WRONG LOCATION. A database you rely on across a work session does not belong in a temp
 *      directory that something else is entitled to delete. The default is now inside the repo at
 *      database/dev.sqlite, gitignored, durable, and never near production.
 *   2. NO INTEGRITY CHECK. A zero-byte or truncated file was only discovered by a confusing query
 *      error several commands later. This checks the file up front and says plainly what is wrong.
 *
 * PRODUCTION SAFETY
 * -----------------
 * This script refuses to run when DATABASE_URL is set or APP_ENV is production. It only ever
 * touches a local SQLite file, and it never reads, writes or connects to the production Postgres.
 *
 * Usage:
 *   php -c php.local.ini scripts/dev_db.php              provision if needed, then health-check
 *   php -c php.local.ini scripts/dev_db.php --recreate   rebuild from the seed copy
 *   php -c php.local.ini scripts/dev_db.php --check      health-check only, never write
 *   php -c php.local.ini scripts/dev_db.php --path X     use a different file
 *
 * Prints the resolved path on success so a shell can do:
 *   export RMT_SQLITE="$(php -c php.local.ini scripts/dev_db.php --quiet)"
 */

define('RMT_NO_AUTOSEED', true);   // never let bootstrap conjure a demo-seeded DB underneath us

$args     = array_slice($argv, 1);
$recreate = in_array('--recreate', $args, true);
$checkOnly= in_array('--check', $args, true);
$quiet    = in_array('--quiet', $args, true);

$pathArg = null;
foreach ($args as $i => $a) {
    if ($a === '--path' && isset($args[$i + 1])) $pathArg = $args[$i + 1];
}

$base = dirname(__DIR__);

function say(string $m): void { global $quiet; if (!$quiet) fwrite(STDERR, $m . PHP_EOL); }
function bail(string $m): never { fwrite(STDERR, 'ERROR: ' . $m . PHP_EOL); exit(1); }

/* ---------------- production guard, before anything else ---------------- */

if (getenv('DATABASE_URL')) {
    bail('DATABASE_URL is set. This script is for the local SQLite dev database only and will not '
       . 'run against a configured remote database. Unset DATABASE_URL and try again.');
}
if (in_array(strtolower((string) getenv('APP_ENV')), ['production', 'prod'], true)) {
    bail('APP_ENV is production. Refusing to run.');
}

$dbPath = $pathArg ?: ($base . '/database/dev.sqlite');
$seed   = $base . '/database/ruinmytrip.sqlite';

/* ---------------- transient-location guard ----------------
 * The original failure was a database living under %LOCALAPPDATA%\Temp, which another process
 * cleared mid-session. That is not a hazard we can fix from inside this repo, because the sweep is
 * done by something else entirely and is entitled to do it. What we can do is refuse to put
 * anything we depend on there in the first place, and say so loudly if someone points --path at
 * one. A warning here is much cheaper than the confusing "no such table: users" it prevents. */
$transientRoots = array_filter([
    getenv('TEMP'), getenv('TMP'), getenv('TMPDIR'),
    getenv('LOCALAPPDATA') ? getenv('LOCALAPPDATA') . '\\Temp' : null,
    '/tmp',
]);
$normalised = strtolower(str_replace('\\', '/', (string) $dbPath));
foreach ($transientRoots as $root) {
    $root = strtolower(str_replace('\\', '/', rtrim((string) $root, '\\/')));
    if ($root !== '' && str_starts_with($normalised, $root . '/')) {
        bail("refusing to use a database inside a transient directory:\n"
           . "         {$dbPath}\n"
           . "  That tree is cleared by other processes without warning, which is exactly how the\n"
           . "  2026-08-12 data loss happened. Use the default (database/dev.sqlite) or another\n"
           . "  durable path outside temp.");
    }
}

/* ---------------- health check ---------------- */

/**
 * Is the file a usable SQLite database?
 * @return array{ok:bool, reason:string, tables:int}
 */
function rmt_db_health(string $path): array {
    if (!file_exists($path))            return ['ok' => false, 'reason' => 'missing', 'tables' => 0];
    $size = (int) filesize($path);
    if ($size === 0)                    return ['ok' => false, 'reason' => 'zero bytes', 'tables' => 0];
    if ($size < 4096)                   return ['ok' => false, 'reason' => "truncated ({$size} bytes)", 'tables' => 0];

    // A file of NUL bytes is the other failure mode seen on this machine, and it is NOT the same as
    // a truncation: the size looks right and only the header gives it away.
    $head = (string) file_get_contents($path, false, null, 0, 16);
    if (strncmp($head, "SQLite format 3\0", 16) !== 0) {
        $nulls = strspn($head, "\0") === strlen($head);
        return ['ok' => false, 'reason' => $nulls ? 'all-NUL bytes (C: corruption)' : 'bad SQLite header', 'tables' => 0];
    }

    try {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $res = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
        if (strtolower($res) !== 'ok') return ['ok' => false, 'reason' => "integrity_check: {$res}", 'tables' => 0];
        $n = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
        if ($n === 0)                  return ['ok' => false, 'reason' => 'no tables', 'tables' => 0];
        // The tables the app cannot start without. A file can pass integrity_check and still be an
        // empty database that will fail with "no such table: users" on the first real query.
        foreach (['users', 'destinations', 'reviews'] as $t) {
            $has = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($t))->fetchColumn();
            if (!$has) return ['ok' => false, 'reason' => "missing core table '{$t}'", 'tables' => $n];
        }
        return ['ok' => true, 'reason' => 'ok', 'tables' => $n];
    } catch (Throwable $e) {
        return ['ok' => false, 'reason' => 'cannot open: ' . $e->getMessage(), 'tables' => 0];
    }
}

$health = rmt_db_health($dbPath);

if ($checkOnly) {
    say(sprintf('%s: %s%s', $dbPath, $health['reason'], $health['ok'] ? " ({$health['tables']} tables)" : ''));
    if ($quiet && $health['ok']) echo $dbPath . PHP_EOL;
    exit($health['ok'] ? 0 : 1);
}

/* ---------------- provision ---------------- */

if ($recreate || !$health['ok']) {
    if (!$health['ok'] && file_exists($dbPath)) {
        say("dev db unusable ({$health['reason']}), rebuilding");
        // Keep the broken file rather than deleting it, so a real corruption event can be examined
        // afterwards instead of being erased by the thing that noticed it.
        $quarantine = $dbPath . '.broken-' . date('Ymd-His');
        @rename($dbPath, $quarantine);
        say("  previous file kept at " . basename($quarantine));
    } elseif ($recreate) {
        say('rebuilding on request');
        if (file_exists($dbPath)) @unlink($dbPath);
    } else {
        say('dev db missing, creating');
    }

    if (file_exists($seed) && rmt_db_health($seed)['ok']) {
        if (!@copy($seed, $dbPath)) bail("could not copy {$seed} to {$dbPath}");
        say('  seeded from database/ruinmytrip.sqlite');
    } else {
        say('  no usable seed database; starting empty and letting migrations build it');
        if (!file_exists($dbPath) && @file_put_contents($dbPath, '') === false) {
            bail("could not create {$dbPath}");
        }
    }
}

/* ---------------- migrate ---------------- */

putenv('RMT_SQLITE=' . $dbPath);
$_SERVER['RMT_SQLITE'] = $dbPath;
require $base . '/app/bootstrap.php';
require $base . '/app/migrator.php';

if ($GLOBALS['config']['db_driver'] !== 'sqlite') {
    bail('resolved driver is ' . $GLOBALS['config']['db_driver'] . ', expected sqlite. Refusing to continue.');
}
if (realpath($GLOBALS['config']['sqlite_path']) !== realpath($dbPath)) {
    bail('config resolved a different sqlite path (' . $GLOBALS['config']['sqlite_path'] . '). Refusing to continue.');
}

$res = rmt_migrate(db(), 'sqlite', static fn(string $m) => say($m));
say(sprintf('  migrations: %d applied, %d already current', count($res['applied']), count($res['skipped'])));

$final = rmt_db_health($dbPath);
if (!$final['ok']) bail('database is still unusable after provisioning: ' . $final['reason']);
say(sprintf('READY  %s  (%d tables)', $dbPath, $final['tables']));

echo $dbPath . PHP_EOL;
