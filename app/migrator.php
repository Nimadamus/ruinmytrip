<?php
declare(strict_types=1);

/**
 * Tracked, forward-only migration runner.
 *
 * Every migration is recorded in `schema_migrations` and applied at most once. Files live in
 * database/migrations/ and are named:
 *
 *     NNN_description.sql              portable across drivers
 *     NNN_description.<driver>.sql     driver-specific (pgsql | sqlite | mysql)
 *
 * The driver-specific file wins when present; otherwise the portable one is used. A migration
 * with no file for the active driver is skipped and reported (not an error) — that lets a
 * pgsql-only change exist without inventing a fake sqlite equivalent.
 *
 * Rules:
 *  - Forward-only. Never edit an applied migration; add a new one.
 *  - Additive only (ADD COLUMN nullable / CREATE TABLE). No drops, no renames, no type changes,
 *    so an older deploy keeps working against a newer schema and rollback is just a redeploy.
 *  - Each migration runs in its own transaction where the driver supports DDL transactions
 *    (pgsql, sqlite). A failure rolls that migration back and aborts the run.
 */

function rmt_migrations_dir(): string { return BASE_PATH . '/database/migrations'; }

function rmt_ensure_migrations_table(PDO $pdo, string $driver): void {
    $sql = match ($driver) {
        'pgsql'  => 'CREATE TABLE IF NOT EXISTS schema_migrations (
                       version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)',
        'mysql'  => 'CREATE TABLE IF NOT EXISTS schema_migrations (
                       version VARCHAR(191) PRIMARY KEY, applied_at TEXT NOT NULL)',
        default  => 'CREATE TABLE IF NOT EXISTS schema_migrations (
                       version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)',
    };
    $pdo->exec($sql);
}

/** Versions already applied. */
function rmt_applied_versions(PDO $pdo): array {
    $st = $pdo->query('SELECT version FROM schema_migrations');
    return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Discover migrations on disk for this driver.
 * @return array<string,string> version => absolute file path
 */
function rmt_discover_migrations(string $driver): array {
    $dir = rmt_migrations_dir();
    if (!is_dir($dir)) return [];
    $found = [];
    foreach (glob($dir . '/*.sql') ?: [] as $path) {
        $base = basename($path, '.sql');
        // NNN_name  or  NNN_name.<driver>
        if (!preg_match('/^(\d+)_([a-z0-9_]+?)(?:\.(pgsql|sqlite|mysql))?$/', $base, $m)) continue;
        [$all, $num, $name] = [$m[0], $m[1], $m[2]];
        $fileDriver = $m[3] ?? null;
        $version = $num . '_' . $name;
        if ($fileDriver === null) {
            $found[$version] ??= $path;                 // portable: only if no driver file seen yet
        } elseif ($fileDriver === $driver) {
            $found[$version] = $path;                   // driver-specific always wins
        }
    }
    ksort($found, SORT_NATURAL);
    return $found;
}

/**
 * Apply all pending migrations.
 * @return array{applied:string[], skipped:string[]}
 */
function rmt_migrate(PDO $pdo, string $driver, ?callable $log = null): array {
    $log ??= static fn(string $m) => null;
    rmt_ensure_migrations_table($pdo, $driver);

    $applied = rmt_applied_versions($pdo);
    $all = rmt_discover_migrations($driver);
    $ran = [];
    $skipped = [];

    foreach ($all as $version => $path) {
        if (in_array($version, $applied, true)) { $skipped[] = $version; continue; }

        $sql = trim((string) file_get_contents($path));
        if ($sql === '') { $skipped[] = $version; continue; }

        $useTx = in_array($driver, ['pgsql', 'sqlite'], true); // mysql auto-commits DDL
        if ($useTx) $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $st = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)');
            $st->execute([$version, date('Y-m-d H:i:s')]);
            if ($useTx) $pdo->commit();
            $ran[] = $version;
            $log("  applied  {$version}");
        } catch (Throwable $e) {
            if ($useTx && $pdo->inTransaction()) $pdo->rollBack();
            throw new RuntimeException("migration {$version} failed: " . $e->getMessage(), 0, $e);
        }
    }
    return ['applied' => $ran, 'skipped' => $skipped];
}
