<?php
declare(strict_types=1);

/**
 * Double-submit protection for content-creation forms (trips, guides, reviews, comments).
 *
 * None of trip_create/guide_create/review_create/comment_action had any duplicate-submission
 * guard: a double-click, a refresh-and-resubmit, or a replayed POST created a second identical
 * row every time, since none of those tables have a uniqueness constraint that could catch it.
 *
 * Each form render mints a fresh single-use token into a per-form SET (not a single slot) so
 * that two tabs open to the same "new" page at once don't invalidate each other's token -- only
 * consuming the exact token a request carries burns it, and a second request bearing that same
 * (already-consumed) token is treated as a duplicate rather than creating another row.
 */

function rmt_submit_token(string $form): string {
    $t = bin2hex(random_bytes(16));
    $_SESSION['_submit'][$form][$t] = true;
    // Bound growth if someone repeatedly opens the form without ever submitting.
    if (count($_SESSION['_submit'][$form]) > 20) {
        $_SESSION['_submit'][$form] = array_slice($_SESSION['_submit'][$form], -20, null, true);
    }
    return $t;
}

/** Consumes the token if valid. Returns false for missing/unknown/already-used tokens. */
function rmt_submit_ok(string $form, ?string $token): bool {
    if ($token === null || $token === '' || empty($_SESSION['_submit'][$form][$token])) return false;
    unset($_SESSION['_submit'][$form][$token]);
    return true;
}
