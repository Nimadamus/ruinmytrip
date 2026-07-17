<?php
/**
 * Migrate job. Runs on container boot (docker/entrypoint.sh) and as Render preDeployCommand.
 *
 * Two steps, both idempotent and safe to run on every deploy:
 *   1. Baseline schema (schema.<driver>.sql, all CREATE TABLE IF NOT EXISTS) — brings a brand
 *      new database up to the original table set.
 *   2. Tracked migrations (database/migrations/) — additive changes applied at most once each,
 *      recorded in schema_migrations. See app/migrator.php.
 *
 * Demo content is never seeded into production.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/loadconfig.php';
$GLOBALS['config'] = rmt_load_config();
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/migrator.php';
require BASE_PATH . '/database/seed.php';

$driver = $GLOBALS['config']['db_driver'];
fwrite(STDOUT, "migrate: driver={$driver}\n");

try {
    $pdo = db();
    rmt_apply_schema($pdo, $driver);
    fwrite(STDOUT, "migrate: baseline schema applied\n");

    $res = rmt_migrate($pdo, $driver, fn(string $m) => fwrite(STDOUT, $m . "\n"));
    $na = count($res['applied']);
    fwrite(STDOUT, "migrate: {$na} migration(s) applied, " . count($res['skipped']) . " already current\n");

    // Demo content is NEVER seeded into production — it fabricates members/reviews and creates
    // an admin account whose password is public in this repo. rmt_seed_data() also refuses, but
    // skip explicitly here so a deploy can never crash-loop on the exception.
    $isProd = (($GLOBALS['config']['app_env'] ?? '') === 'production') || getenv('DATABASE_URL');
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $seed = getenv('SEED_DEMO');
    $seed = ($seed === false || $seed === '') ? '1' : $seed;
    if ($isProd) {
        fwrite(STDOUT, "migrate: seed skipped (production — demo content is never seeded live)\n");
    } elseif ($count === 0 && $seed !== '0') {
        rmt_seed_data($pdo);
        $n = (int) $pdo->query('SELECT COUNT(*) FROM destinations')->fetchColumn();
        fwrite(STDOUT, "migrate: seeded demo content ({$n} destinations)\n");
    } else {
        fwrite(STDOUT, "migrate: seed skipped (users={$count})\n");
    }
    fwrite(STDOUT, "migrate: done\n");
} catch (Throwable $e) {
    fwrite(STDERR, "migrate FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
