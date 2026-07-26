<?php
declare(strict_types=1);

/**
 * Database-backed session storage so logins survive container restarts/deploys and work
 * across multiple instances. Used for all drivers (sqlite/mysql/pgsql).
 *
 * Locking: PHP's default file-based session handler serializes concurrent requests for the same
 * session via flock(), so one request's session writes can never clobber another's. This handler
 * had no equivalent -- read() and write() were plain, unlocked SELECT/UPSERT. Two requests for the
 * same session that overlap even slightly (confirmed live: a page load's main document and the
 * browser's own automatic GET /favicon.ico request, both racing against a session with no row yet)
 * both read the same starting snapshot and each write back their own full copy; whichever write
 * lands last silently erases the other's changes -- CSRF tokens, submit-dedup tokens, anything
 * stored in $_SESSION. Reproduced: a submit token that WAS rendered into a form's HTML was
 * completely absent from the session row in Postgres moments later.
 *
 * pg_advisory_lock is used rather than a held transaction because db() returns one shared PDO
 * connection for the whole request -- wrapping read()..close() in a transaction would put every
 * unrelated query the request makes (rate limits, content lookups) inside it too. An advisory
 * lock has no such effect: it's independent of transactions, and Postgres releases it
 * automatically if the connection ever drops without an explicit unlock, so a crashed request
 * can't deadlock a later one.
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    private ?string $lockedId = null;

    public function open(string $path, string $name): bool { return true; }

    public function close(): bool { $this->releaseLock(); return true; }

    private function acquireLock(string $id): void {
        $driver = $GLOBALS['config']['db_driver'];
        if ($driver === 'pgsql') {
            db()->prepare('SELECT pg_advisory_lock(hashtext(?))')->execute([$id]);
        } elseif ($driver === 'mysql') {
            db()->prepare('SELECT GET_LOCK(?, 10)')->execute(['rmt_session_' . $id]);
        }
        // sqlite is local-dev-only (single low-concurrency process); no lock needed there.
        $this->lockedId = $id;
    }

    private function releaseLock(): void {
        if ($this->lockedId === null) return;
        $driver = $GLOBALS['config']['db_driver'];
        if ($driver === 'pgsql') {
            db()->prepare('SELECT pg_advisory_unlock(hashtext(?))')->execute([$this->lockedId]);
        } elseif ($driver === 'mysql') {
            db()->prepare('SELECT RELEASE_LOCK(?)')->execute(['rmt_session_' . $this->lockedId]);
        }
        $this->lockedId = null;
    }

    #[\ReturnTypeWillChange]
    public function read(string $id): string
    {
        // Held until close(), so every other query this request makes on the shared connection
        // still runs outside any transaction -- only this session row is serialized against
        // concurrent requests for the SAME session id.
        $this->acquireLock($id);
        $r = q_one('SELECT data FROM sessions WHERE id = ?', [$id]);
        return $r['data'] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        $driver = $GLOBALS['config']['db_driver'];
        if ($driver === 'pgsql') {
            $sql = 'INSERT INTO sessions (id, data, updated_at) VALUES (?,?,?)
                    ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, updated_at = EXCLUDED.updated_at';
        } elseif ($driver === 'mysql') {
            $sql = 'INSERT INTO sessions (id, data, updated_at) VALUES (?,?,?)
                    ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = VALUES(updated_at)';
        } else { // sqlite (3.24+)
            $sql = 'INSERT INTO sessions (id, data, updated_at) VALUES (?,?,?)
                    ON CONFLICT(id) DO UPDATE SET data = excluded.data, updated_at = excluded.updated_at';
        }
        db()->prepare($sql)->execute([$id, $data, time()]);
        return true;
    }

    public function destroy(string $id): bool
    {
        db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc(int $max_lifetime): int|false
    {
        db()->prepare('DELETE FROM sessions WHERE updated_at < ?')->execute([time() - $max_lifetime]);
        return 0;
    }
}
