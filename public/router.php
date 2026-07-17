<?php
// Dev router for: php -S localhost:8080 -t public public/router.php
// Serves real static files directly; routes everything else through index.php.
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file) && $uri !== '/index.php') {
    return false; // let the built-in server serve the asset
}
require __DIR__ . '/index.php';
