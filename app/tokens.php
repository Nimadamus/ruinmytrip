<?php
declare(strict_types=1);

/**
 * Single-use, expiring tokens for email verification and password reset.
 *
 * The raw token is generated once, emailed, and never stored. Only sha256(token) goes in the DB,
 * so a database leak yields no usable verification or reset links. Lookup is by hash, which is
 * also why the column is UNIQUE-indexed rather than scanned.
 */

const RMT_TOKEN_VERIFY_TTL = 86400;  // 24h
const RMT_TOKEN_RESET_TTL  = 3600;   // 1h — short: a reset link is a live key to the account

function rmt_token_hash(string $raw): string { return hash('sha256', $raw); }

/**
 * Issue a token of $kind for $userId. Returns the RAW token (email it, never log it).
 * Any earlier unused tokens of the same kind are burned so only the newest link works.
 */
function rmt_token_issue(int $userId, string $kind): string {
    db()->prepare('UPDATE auth_tokens SET used_at = ? WHERE user_id = ? AND kind = ? AND used_at IS NULL')
        ->execute([date('Y-m-d H:i:s'), $userId, $kind]);

    $raw = bin2hex(random_bytes(32));
    $ttl = $kind === 'reset' ? RMT_TOKEN_RESET_TTL : RMT_TOKEN_VERIFY_TTL;
    db()->prepare('INSERT INTO auth_tokens (user_id, kind, token_hash, expires_at, created_at)
                   VALUES (?,?,?,?,?)')
        ->execute([
            $userId, $kind, rmt_token_hash($raw),
            date('Y-m-d H:i:s', time() + $ttl),
            date('Y-m-d H:i:s'),
        ]);
    return $raw;
}

/**
 * Look up a live (unused, unexpired) token. Returns the row or null.
 * Does NOT consume it — call rmt_token_consume() once the action succeeds.
 */
function rmt_token_lookup(string $raw, string $kind): ?array {
    if ($raw === '' || !ctype_xdigit($raw)) return null;
    $row = q_one('SELECT t.*, u.email FROM auth_tokens t JOIN users u ON u.id = t.user_id
                  WHERE t.token_hash = ? AND t.kind = ?', [rmt_token_hash($raw), $kind]);
    if (!$row) return null;
    if ($row['used_at'] !== null) return null;
    if (strtotime((string) $row['expires_at']) < time()) return null;
    return $row;
}

/** Mark a token used. Single-use is enforced here, not by deletion (keeps an audit trail). */
function rmt_token_consume(int $tokenId): void {
    db()->prepare('UPDATE auth_tokens SET used_at = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $tokenId]);
}

/** Burn every outstanding token of a kind for a user (e.g. after a successful reset). */
function rmt_token_burn_all(int $userId, string $kind): void {
    db()->prepare('UPDATE auth_tokens SET used_at = ? WHERE user_id = ? AND kind = ? AND used_at IS NULL')
        ->execute([date('Y-m-d H:i:s'), $userId, $kind]);
}
