<?php
/**
 * ONE-SHOT schema importer for production MySQL (alternative to phpMyAdmin import).
 * Deploy temporarily, hit it ONCE in the browser, confirm success, then DELETE this file.
 * It refuses to run unless app/config.php is present and points at MySQL, and it will
 * NOT overwrite existing tables (aborts if `users` already exists).
 *
 * Place at:  public/import_schema.php   (temporarily)  ->  https://ruinmytrip.com/import_schema.php
 */
declare(strict_types=1);
header('Content-Type: text/plain');

$root = dirname(__DIR__);
$config = require $root . '/app/config.php';
if (($config['db_driver'] ?? '') !== 'mysql') { exit("Refusing: config is not MySQL.\n"); }

$m = $config['mysql'];
try {
    $pdo = new PDO("mysql:host={$m['host']};port={$m['port']};dbname={$m['name']};charset=utf8mb4", $m['user'], $m['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) { exit('DB connect FAILED: ' . $e->getMessage() . "\n"); }

$exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
if ($exists) { exit("Refusing: 'users' table already exists (schema already imported).\n"); }

$sql = file_get_contents($root . '/database/schema.mysql.sql');
try {
    $pdo->exec($sql);
    $n = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    echo "Schema imported OK. Tables now: {$n}\n";
    echo "DELETE this file (public/import_schema.php) NOW.\n";
} catch (Throwable $e) {
    echo 'Import FAILED: ' . $e->getMessage() . "\n";
}
