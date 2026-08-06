<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $c = $GLOBALS['config'];
    if ($c['db_driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['sqlite_path']);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } elseif ($c['db_driver'] === 'pgsql') {
        $p = $c['pgsql'];
        $dsn = "pgsql:host={$p['host']};port={$p['port']};dbname={$p['name']}";
        if (!empty($p['sslmode'])) $dsn .= ";sslmode={$p['sslmode']}";
        $pdo = new PDO($dsn, $p['user'], $p['pass']);
    } else {
        $m = $c['mysql'];
        $dsn = "mysql:host={$m['host']};port={$m['port']};dbname={$m['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $m['user'], $m['pass']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

/** Convenience: fetch all rows. */
function q_all(string $sql, array $args = []): array {
    $st = db()->prepare($sql); $st->execute($args); return $st->fetchAll();
}
/** Convenience: fetch one row or null. */
function q_one(string $sql, array $args = []): ?array {
    $st = db()->prepare($sql); $st->execute($args); $r = $st->fetch(); return $r === false ? null : $r;
}
/** Convenience: run a write, return last insert id (empty string when N/A, e.g. composite-key tables on pgsql). */
function q_run(string $sql, array $args = []): string {
    $st = db()->prepare($sql); $st->execute($args);
    try { return (string) db()->lastInsertId(); }
    catch (\PDOException $e) { return ''; } // pgsql lastval() undefined on no-serial inserts
}
/**
 * Run a write that has no insert id — UPDATE, DELETE, or an INSERT whose id nobody wants.
 *
 * This exists because q_run() unconditionally calls lastInsertId(), and on Postgres that is
 * lastval(), which raises a real server-side error when nothing in the session has touched a
 * sequence yet. q_run() swallows the PHP exception, but Postgres has already marked the
 * transaction aborted, so every later statement fails with 25P02. Inside a transaction, or for
 * any statement whose id is not needed, use this instead.
 *
 * @return int rows affected
 */
function q_exec(string $sql, array $args = []): int {
    $st = db()->prepare($sql); $st->execute($args); return $st->rowCount();
}
