<?php
declare(strict_types=1);

/**
 * A review written before its author has an account, kept on the server (migration 107).
 *
 * The guest review path holds the review in the PHP session between "Post review" and the account
 * step. That was the only copy, so every way a session can end lost the review: the tab closed, the
 * session lapsed, the confirmation link opened in a mail app's browser, a sign in that rotated the
 * session id. The review is the single most valuable thing a stranger gives this site, and losing
 * it at the moment they are deciding whether to trust us is the worst possible time.
 *
 * So the same validated text is also written here, keyed by sha256 of a random token that lives in
 * an HttpOnly cookie. The database never holds the token itself, the cookie never holds any text,
 * the row expires after 30 days, and it is deleted the moment the review is saved to an account.
 * The session copy stays the fast path; this table is the one that survives.
 */

const RMT_REVIEW_DRAFT_COOKIE = 'rmt_rdraft';
const RMT_REVIEW_DRAFT_TTL    = 30 * 86400;

/** The token from this browser's cookie, or '' when it carries none that looks like ours. */
function rmt_review_draft_token(): string {
    $t = (string) ($_COOKIE[RMT_REVIEW_DRAFT_COOKIE] ?? '');
    return preg_match('/^[a-f0-9]{48}$/', $t) ? $t : '';
}

function rmt_review_draft_cookie(string $value, int $expires): void {
    if (!headers_sent()) {
        setcookie(RMT_REVIEW_DRAFT_COOKIE, $value, [
            'expires'  => $expires, 'path' => '/',
            'httponly' => true, 'samesite' => 'Lax',
            'secure'   => (string) (function_exists('cfg') ? cfg('app_env') : '') === 'production',
        ]);
    }
    if ($value === '') unset($_COOKIE[RMT_REVIEW_DRAFT_COOKIE]);
    else $_COOKIE[RMT_REVIEW_DRAFT_COOKIE] = $value;
}

/**
 * Keep a validated guest review. Only string fields the review form posts are kept, capped the
 * same way the session copy is. Returns false when the table is missing or the write failed; the
 * session copy still exists in that case, so the caller carries on either way.
 */
function rmt_review_draft_put(array $post): bool {
    $keep = rmt_review_draft_clean($post);
    if (!$keep) return false;
    $now = date('Y-m-d H:i:s');
    $exp = date('Y-m-d H:i:s', time() + RMT_REVIEW_DRAFT_TTL);
    try {
        // Expired rows go first, here, so nothing else has to remember to sweep them.
        q_run('DELETE FROM review_drafts WHERE expires_at < ?', [$now]);
        $token = rmt_review_draft_token();
        $json = (string) json_encode($keep);
        $placeId = (int) ($keep['place_id'] ?? 0) ?: null;
        if ($token !== '' && q_one('SELECT id FROM review_drafts WHERE token_hash = ?', [hash('sha256', $token)])) {
            q_run('UPDATE review_drafts SET payload = ?, place_id = ?, updated_at = ?, expires_at = ? WHERE token_hash = ?',
                  [$json, $placeId, $now, $exp, hash('sha256', $token)]);
        } else {
            $token = bin2hex(random_bytes(24));
            q_run('INSERT INTO review_drafts (token_hash, place_id, payload, created_at, updated_at, expires_at)
                   VALUES (?,?,?,?,?,?)', [hash('sha256', $token), $placeId, $json, $now, $now, $exp]);
        }
        rmt_review_draft_cookie($token, time() + RMT_REVIEW_DRAFT_TTL);
        return true;
    } catch (Throwable $e) {
        error_log('review_drafts put: ' . $e->getMessage());
        return false;
    }
}

/** The kept review for this browser, or null. Never throws: a missing table reads as no draft. */
function rmt_review_draft_get(): ?array {
    $token = rmt_review_draft_token();
    if ($token === '') return null;
    try {
        $row = q_one('SELECT payload, expires_at FROM review_drafts WHERE token_hash = ?', [hash('sha256', $token)]);
    } catch (Throwable $e) {
        return null;
    }
    if (!$row || (string) $row['expires_at'] < date('Y-m-d H:i:s')) return null;
    $data = json_decode((string) $row['payload'], true);
    return is_array($data) && $data ? $data : null;
}

/** Forget it: the review now belongs to an account, or the writer threw it away. */
function rmt_review_draft_clear(): void {
    $token = rmt_review_draft_token();
    if ($token !== '') {
        try { q_run('DELETE FROM review_drafts WHERE token_hash = ?', [hash('sha256', $token)]); }
        catch (Throwable $e) { /* table missing: nothing to delete */ }
    }
    if (isset($_COOKIE[RMT_REVIEW_DRAFT_COOKIE])) rmt_review_draft_cookie('', time() - 3600);
}

/** The text fields a review form posts, nothing else. Shared by the session copy and this table. */
function rmt_review_draft_clean(array $post): array {
    /* Only the fields the review form has (2026-10-11). Keeping every posted key let one request
       store thousands of junk fields of 6,000 characters each; a held review is now a few KB at
       most, whatever is posted. */
    $keep = [];
    foreach (RMT_REVIEW_DRAFT_FIELDS as $k => $max) {
        if (!isset($post[$k]) || !is_string($post[$k])) continue;
        $keep[$k] = mb_substr($post[$k], 0, $max);
    }
    if (isset($post['aspect']) && is_array($post['aspect'])) {
        $asp = [];
        foreach (array_slice($post['aspect'], 0, 20, true) as $name => $val) {
            if (is_string($name) && preg_match('/^[a-z_]{1,32}$/', $name) && is_string($val)) $asp[$name] = mb_substr($val, 0, 2);
        }
        if ($asp) $keep['aspect'] = $asp;
    }
    return $keep;
}

/** The review form's own fields and how long each may be (the validator's limits, rounded up). */
const RMT_REVIEW_DRAFT_FIELDS = [
    'place_id' => 12, 'destination_id' => 12, 'subject_type' => 40, 'subject_name' => 200, 'rating' => 2,
    'title' => 200, 'body' => 12000, 'what_great' => 2000, 'what_ruined' => 2000, 'visited_on' => 10,
    'traveler_type' => 40, 'src' => 40,
];

/**
 * The review this visitor has in hand: the session copy when there is one, otherwise the server
 * copy, which is put back into the session so the rest of the request reads one place.
 */
function rmt_review_draft_current(): ?array {
    $s = $_SESSION[RMT_PLAN_DRAFT_KEY] ?? null;
    if (is_array($s) && !empty($s['review'])) return (array) $s['review'];
    if (is_array($s) && (!empty($s['post']) || !empty($s['inputs']))) return null;   // a trip or question owns the slot
    $d = rmt_review_draft_get();
    if ($d && session_status() === PHP_SESSION_ACTIVE) $_SESSION[RMT_PLAN_DRAFT_KEY] = ['review' => $d];
    return $d;
}

/**
 * The localStorage key review-draft.js uses for a form, so the page after a successful save can
 * tell the browser to drop its copy. Must match views/review_new.php.
 */
function rmt_review_draft_js_key(array $post): string {
    return 'new-' . (int) ($post['place_id'] ?? 0) . '-' . (int) ($post['destination_id'] ?? 0);
}

/** Ask the next page render to clear the browser's copy of this form (views/layout/footer.php). */
function rmt_review_draft_js_clear_next(array $post): void {
    if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['rmt_clear_draft'] = rmt_review_draft_js_key($post);
}
