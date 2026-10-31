<?php
/**
 * Content Security Policy (2026-10-30, app/csp.php, docs/CSP_20261030.md).
 *
 * The enforced policy carries only the directives verified not to break a page. The full policy
 * is Report-Only. These checks keep the views compatible with the full policy: no inline event
 * handler attributes, no javascript: URLs, and every inline <script> carries the nonce.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://ruinmytrip.com'];
require BASE_PATH . '/app/helpers.php';

$pass = 0; $fail = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  [PASS] $name\n"; } else { $fail++; echo "  [FAIL] $name $detail\n"; }
}

echo "\nThe enforced policy:\n";
$enf = rmt_csp_enforced();
ok('object-src none', str_contains($enf, "object-src 'none'"));
ok('base-uri self', str_contains($enf, "base-uri 'self'"));
ok('form-action self plus Google sign in only', str_contains($enf, "form-action 'self' https://accounts.google.com;"));
ok('frame-ancestors self', str_contains($enf, "frame-ancestors 'self'"));
ok('enforces no script or style rule yet (those are report only)', !preg_match('/script-src|style-src|default-src/', $enf));

echo "\nThe report only policy:\n";
$n = rmt_csp_nonce();
ok('the nonce is stable within a response', $n === rmt_csp_nonce());
ok('the nonce is base64url of 16 bytes', (bool) preg_match('/^[A-Za-z0-9_-]{22}$/', $n));
$ro = rmt_csp_report_only($n);
ok('script-src allows self, the nonce and the Leaflet CDN only',
   str_contains($ro, "script-src 'self' 'nonce-$n' https://cdnjs.cloudflare.com;"));
ok('script-src never allows unsafe-inline or unsafe-eval', !preg_match("/script-src[^;]*unsafe/", $ro));
ok('connect-src is this site only', str_contains($ro, "connect-src 'self';"));
ok('frames and plugins are off', str_contains($ro, "frame-src 'none'") && str_contains($ro, "object-src 'none'"));

echo "\nViews stay compatible with the full policy:\n";
$handlers = []; $scripts = []; $jsurls = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/views', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!str_ends_with((string) $f, '.php')) continue;
    $src = file_get_contents((string) $f);
    $rel = substr((string) $f, strlen(BASE_PATH) + 1);
    if (preg_match_all('/<[a-z][^>]*\son[a-z]+\s*=\s*["\']/i', $src, $m)) $handlers[] = $rel;
    if (preg_match_all('/<script\b(?![^>]*\bsrc=)(?![^>]*application\/ld\+json)[^>]*>/i', $src, $m)) {
        foreach ($m[0] as $tag) if (!str_contains($tag, 'rmt_csp_nonce()')) $scripts[] = $rel;
    }
    if (preg_match('/(href|src|action)\s*=\s*["\']\s*javascript:/i', $src)) $jsurls[] = $rel;
}
ok('no inline event handler attributes (onclick=, onsubmit= ...)', $handlers === [], implode(', ', $handlers));
ok('every inline <script> carries the nonce', $scripts === [], implode(', ', $scripts));
ok('no javascript: URLs', $jsurls === [], implode(', ', $jsurls));
$js = '';
foreach (glob(BASE_PATH . '/public/assets/js/*.js') as $f) $js .= file_get_contents($f);
ok('no eval or new Function in site scripts', !preg_match('/\beval\(|new Function\(/', $js));
foreach (['data-confirm-submit', 'data-autosubmit', 'data-hide-broken', 'data-reveal', 'data-toggle-body', 'data-close-body',
          'data-select-on-click', 'data-photo-autosubmit'] as $attr) {
    ok("app.js handles $attr", str_contains($js, $attr));
}

echo "\nCached pages get a fresh nonce:\n";
$pc = file_get_contents(BASE_PATH . '/app/page_cache.php');
ok('the nonce is stored as a placeholder', str_contains($pc, 'RMT_PAGE_CACHE_NONCE . \'"\'') || str_contains($pc, "RMT_PAGE_CACHE_NONCE"));
ok('and replaced with this response nonce on serve', (bool) preg_match('/RMT_PAGE_CACHE_NONCE\][^;]*rmt_csp_nonce\(\)/', $pc));
$boot = file_get_contents(BASE_PATH . '/app/bootstrap.php');
ok('bootstrap sends the headers before the session starts', strpos($boot, 'rmt_csp_send();') !== false
   && strpos($boot, 'rmt_csp_send();') < strpos($boot, 'session_start();'));

echo "\ncsp_test: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
