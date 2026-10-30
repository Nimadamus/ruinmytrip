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

/**
 * Was this POST thrown away by PHP for being over post_max_size? PHP then hands over empty $_POST
 * and $_FILES, the token included, so a member who chose several large phone photos at once was
 * told their form session had expired: wrong, and no hint that the photos were the cause.
 */
function rmt_post_too_large(): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $_POST || $_FILES) return false;
    $max = ini_parse_quantity((string) ini_get('post_max_size'));
    return $max > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $max;
}

function csrf_check(): void {
    if (rmt_post_too_large()) {
        http_response_code(413);
        view('403', ['msg' => 'Those photos were too large to send together. Go back and add them '
                            . 'a few at a time (up to 8MB each).', 'heading' => 'That was too much to upload at once'],
             ['title' => 'Too large | RuinMyTrip', 'canonical' => '', 'robots' => 'noindex,follow']);
        exit;
    }
    if (!csrf_valid()) {
        // 403 (standard) — not 419: CDN/edge proxies reject non-standard codes and rewrite them to 500.
        forbidden('Your form session expired. Go back and try again.');
    }
}
