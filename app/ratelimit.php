<?php
declare(strict_types=1);

/**
 * Fixed-window rate limiting, backed by the DB so it holds across container restarts and
 * multiple instances (an in-process counter would reset on every deploy and is useless on
 * a horizontally-scaled tier).
 *
 * Buckets are "<action>:<identifier>" where identifier is an IP or an email — never a raw
 * password or token. Windows are aligned to the clock, so a caller gets at most N hits per
 * window rather than a sliding average. Good enough to stop credential stuffing and mail
 * bombing; not a substitute for a WAF.
 */

/** Client IP, honouring Render's proxy header but never trusting it blindly for auth. */
function rmt_client_ip(): string {
    $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($fwd !== '') {
        $first = trim(explode(',', $fwd)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    $ra = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ra, FILTER_VALIDATE_IP) ? $ra : '0.0.0.0';
}

/**
 * Record a hit and report whether the caller is now over the limit.
 * Returns true when the request should be ALLOWED, false when it should be blocked.
 */
function rmt_rate_ok(string $action, string $identifier, int $limit, int $windowSeconds): bool {
    $bucket = $action . ':' . strtolower(trim($identifier));
    $windowStart = intdiv(time(), $windowSeconds) * $windowSeconds;
    $driver = $GLOBALS['config']['db_driver'];

    if ($driver === 'pgsql') {
        $sql = 'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?,?,1)
                ON CONFLICT (bucket, window_start) DO UPDATE SET hits = rate_limits.hits + 1';
    } elseif ($driver === 'mysql') {
        $sql = 'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?,?,1)
                ON DUPLICATE KEY UPDATE hits = hits + 1';
    } else {
        $sql = 'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?,?,1)
                ON CONFLICT(bucket, window_start) DO UPDATE SET hits = hits + 1';
    }
    db()->prepare($sql)->execute([$bucket, $windowStart]);

    $row = q_one('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?', [$bucket, $windowStart]);
    $hits = (int) ($row['hits'] ?? 0);

    // Opportunistic cleanup: drop windows older than a day. Cheap, and keeps the table from
    // growing without bound on a site with no cron.
    if (random_int(1, 50) === 1) {
        db()->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([time() - 86400]);
    }
    return $hits <= $limit;
}

/** How long until the current window rolls over (for user-facing messages). */
function rmt_rate_retry_after(int $windowSeconds): int {
    $windowStart = intdiv(time(), $windowSeconds) * $windowSeconds;
    return max(1, ($windowStart + $windowSeconds) - time());
}
