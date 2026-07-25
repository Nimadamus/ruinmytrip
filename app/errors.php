<?php
declare(strict_types=1);

/**
 * Production-only error hardening.
 *
 * Nothing here sets a php.ini anywhere in the Docker build (confirmed: no ini file is copied,
 * only an uploads-limits drop-in), so production was running on PHP's compiled-in default of
 * display_errors=On. An uncaught exception or fatal error would print a raw stack trace --
 * file paths, query fragments, whatever the error touched -- straight to the visitor.
 *
 * Local/dev is untouched: php.local.ini already sets display_errors=On there deliberately,
 * and that's the right behavior for debugging, so this file no-ops outside production.
 */

if (($GLOBALS['config']['app_env'] ?? '') !== 'production') return;

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

function rmt_render_fatal_page(): void {
    if (PHP_SAPI === 'cli') return; // maintenance scripts (migrate.php, publish_editorial.php): log only, no HTML
    if (!headers_sent()) http_response_code(500);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Something went wrong — RuinMyTrip</title></head>'
       . '<body style="font-family:system-ui,sans-serif;max-width:520px;margin:96px auto;text-align:center;color:#1c2b40">'
       . '<h1 style="margin-bottom:.3em">Something went wrong</h1>'
       . '<p style="color:#5b6b7f">We hit an unexpected error on our end. Try again, or head back to the '
       . '<a href="/" style="color:#0f766e">homepage</a>.</p>'
       . '</body></html>';
}

set_exception_handler(function (Throwable $e): void {
    error_log('[uncaught] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    rmt_render_fatal_page();
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[fatal] ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        rmt_render_fatal_page();
    }
});
