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
/**
 * Run a write. Returns the last insert id for an INSERT, and an empty string otherwise.
 *
 * The INSERT check is not a tidiness pass, it is a correctness one. pdo_pgsql implements
 * lastInsertId() as `SELECT lastval()`, and lastval() RAISES when the session has not used a
 * sequence yet. Outside a transaction that error is isolated and the catch below is enough.
 * INSIDE a transaction it aborts the entire transaction, and catching the PHP exception does not
 * undo that: every following statement fails with 25P02 "current transaction is aborted", and the
 * commit silently discards work that looked like it succeeded.
 *
 * That is exactly what happened on 2026-08-29: the place enrichment run reported "91 fields
 * written" and wrote nothing at all, because the first UPDATE inside each per-place transaction
 * was followed by a lastval() that poisoned it. Asking only after an INSERT removes the call from
 * every UPDATE and DELETE, which is every case where the answer was meaningless anyway.
 *
 * Residual case: an INSERT into a table with no sequence (a composite-key table) inside a
 * transaction would still raise. Nothing does that today; if something needs to, give it its own
 * savepoint or a RETURNING clause rather than relaxing this.
 */
function q_run(string $sql, array $args = []): string {
    $st = db()->prepare($sql); $st->execute($args);
    if (!preg_match('/^\s*INSERT\s/i', $sql)) return '';
    try { return (string) db()->lastInsertId(); }
    catch (\PDOException $e) { return ''; } // pgsql lastval() undefined on no-serial inserts
}
