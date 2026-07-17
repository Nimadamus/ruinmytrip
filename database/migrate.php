<?php
/**
 * Production migrate job. Runs as Render preDeployCommand: `php database/migrate.php`.
 * Idempotent: creates tables if absent (IF NOT EXISTS), seeds demo content only when
 * the users table is empty and SEED_DEMO != '0'. Safe to run on every deploy.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/loadconfig.php';
$GLOBALS['config'] = rmt_load_config();
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/database/seed.php';

$driver = $GLOBALS['config']['db_driver'];
fwrite(STDOUT, "migrate: driver={$driver}\n");

try {
    $pdo = db();
    rmt_apply_schema($pdo, $driver);
    fwrite(STDOUT, "migrate: schema applied\n");

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
