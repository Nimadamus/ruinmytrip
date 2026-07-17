<?php
declare(strict_types=1);

/**
 * Database-backed session storage so logins survive container restarts/deploys and work
 * across multiple instances. Used for all drivers (sqlite/mysql/pgsql).
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    #[\ReturnTypeWillChange]
    public function read(string $id): string
    {
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
