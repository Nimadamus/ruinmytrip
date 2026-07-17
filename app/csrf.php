<?php
declare(strict_types=1);

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Is the request's CSRF token valid?
 *
 * BOTH the session token and the submitted token must be present, be strings, and be non-empty,
 * and they must match. The non-empty guards matter: hash_equals('', '') returns TRUE, so without
 * them a request that carries no token against a session that never issued one (empty vs empty)
 * would pass. Every form renders csrf_field() first, which seeds a session token, so a legitimate
 * submission always has a non-empty session token to match against.
 */
function csrf_valid(): bool {
    $sent = $_POST['_csrf'] ?? '';
    $session = $_SESSION['_csrf'] ?? '';
    return is_string($sent) && $sent !== ''
        && is_string($session) && $session !== ''
        && hash_equals($session, $sent);
}

function csrf_check(): void {
    if (!csrf_valid()) {
        // 403 (standard) — not 419: CDN/edge proxies reject non-standard codes and rewrite them to 500.
        http_response_code(403);
        exit('Invalid or expired form token. Go back and try again.');
    }
}
