<?php
/**
 * A 60 second HTML cache for public city, travelers, event guide (/e) and place (/p) pages, signed
 * out readers only (Q5, 2026-10-01; /e and /p 2026-10-02).
 *
 * A city page runs about thirty queries against a shared Postgres; a signed out reader gets the
 * same page as every other signed out reader for the next minute, so it is built once and kept in
 * the container's temp dir. What makes that safe is what is refused:
 *   - any signed in reader (their page is personal: saved, going, follow, held work);
 *   - any query string (filters, utm campaign banners, ?ask= prompts);
 *   - any session carrying something meant for this reader only (a flash, old form input, a
 *     pending submit, an invite, a match alert or I'm going card of theirs) or an invite cookie;
 *   - anything but GET.
 * The one per session value in the HTML, the CSRF token, is stored as a placeholder and swapped
 * for the reader's own on the way out. Tracking runs in the controller before the cache is asked,
 * and landing_view is replayed here, so the funnel counts a cached page exactly like a built one.
 */

const RMT_PAGE_CACHE_TTL = 60;
const RMT_PAGE_CACHE_CSRF = '__RMT_CSRF_TOKEN__';

function rmt_page_cache_dir(): string {
    return rtrim(sys_get_temp_dir(), '/' . chr(92)) . '/rmt_page_cache';
}

/** The cache key for this request, or null when the request must be built fresh. */
function rmt_page_cache_key(string $scope): ?string {
    if (getenv('RMT_PAGE_CACHE') === '0') return null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return null;
    if ((string) ($_SERVER['QUERY_STRING'] ?? '') !== '') return null;
    if (function_exists('is_logged_in') && is_logged_in()) return null;
    if (session_status() === PHP_SESSION_ACTIVE) {
        foreach (['_flash', '_old', '_submit', 'ref', 'uid', 'alert_email', 'alert_last', 'my_cards'] as $k) {
            if (!empty($_SESSION[$k])) return null;
        }
    }
    if (defined('RMT_INVITE_COOKIE') && !empty($_COOKIE[RMT_INVITE_COOKIE])) return null;
    return sha1($scope . '|' . (string) ($_SERVER['REQUEST_URI'] ?? ''));
}

/** Serve a fresh cached copy if there is one. True when the response has been sent. */
function rmt_page_cache_serve(?string $key): bool {
    if ($key === null) return false;
    $file = rmt_page_cache_dir() . '/' . $key . '.html';
    $mtime = @filemtime($file);
    if ($mtime === false || time() - $mtime > RMT_PAGE_CACHE_TTL) return false;
    $raw = @file_get_contents($file);
    if ($raw === false || !str_starts_with($raw, "robots:")) return false;
    [$head, $html] = explode("\n", $raw, 2) + [1 => ''];
    $robots = substr($head, 7);
    if (function_exists('rmt_track_landing')) rmt_track_landing($robots);
    header('X-RMT-Cache: hit');
    echo str_replace(RMT_PAGE_CACHE_CSRF, e(csrf_token()), $html);
    return true;
}

/** Start capturing the page being built. */
function rmt_page_cache_begin(?string $key): void {
    if ($key !== null) ob_start();
}

/** Store the captured page and send it. Only a 200 is kept. */
function rmt_page_cache_end(?string $key, string $robots): void {
    if ($key === null) return;
    $html = (string) ob_get_clean();
    if (http_response_code() === 200 && $html !== '') {
        $dir = rmt_page_cache_dir();
        if (is_dir($dir) || @mkdir($dir, 0700, true)) {
            $tok = session_status() === PHP_SESSION_ACTIVE ? (string) ($_SESSION['_csrf'] ?? '') : '';
            $store = $tok !== '' ? str_replace(e($tok), RMT_PAGE_CACHE_CSRF, $html) : $html;
            $tmp = $dir . '/' . $key . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, "robots:" . $robots . "\n" . $store) !== false) {
                @rename($tmp, $dir . '/' . $key . '.html');
            }
        }
    }
    header('X-RMT-Cache: miss');
    echo $html;
}
