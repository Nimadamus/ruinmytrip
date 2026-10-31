<?php
declare(strict_types=1);

/**
 * Content Security Policy (2026-10-30). The inventory behind every directive is in
 * docs/CSP_20261030.md; change the two together.
 *
 * Two layers:
 *   - ENFORCED: only directives verified not to break a page: no plugins, no <base> hijack,
 *     forms post only to this site (plus Google sign in), and no framing by other sites.
 *   - REPORT ONLY: the full script/style/img/connect policy. A violation shows in the browser
 *     console and blocks nothing, so it can be watched before it is enforced.
 *
 * Inline <script> blocks carry rmt_csp_nonce(). Inline style attributes are everywhere (2,000+),
 * so style-src keeps 'unsafe-inline'; styles cannot run script.
 */

/** One nonce per response. base64url, which the CSP nonce grammar accepts. */
function rmt_csp_nonce(): string {
    static $n = null;
    if ($n === null) $n = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    return $n;
}

/** Where forms may send (and redirect after sending). Google is the sign in hop. */
const RMT_CSP_FORM_ACTION = "'self' https://accounts.google.com";

function rmt_csp_enforced(): string {
    return "object-src 'none'; base-uri 'self'; form-action " . RMT_CSP_FORM_ACTION . "; frame-ancestors 'self'";
}

function rmt_csp_report_only(string $nonce): string {
    return implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'nonce-{$nonce}' https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com",
        // Place photos are imported from many hosts (Wikimedia Commons and others) and stored
        // media may sit on an R2 public host, so any https image is allowed. Map tiles are images.
        "img-src 'self' data: blob: https:",
        "font-src 'self'",
        "connect-src 'self'",
        "media-src 'self' blob:",
        "worker-src 'self'",
        "manifest-src 'self'",
        "frame-src 'none'",
        "object-src 'none'",
        "base-uri 'self'",
        'form-action ' . RMT_CSP_FORM_ACTION,
    ]);
}

/** Send both headers. Called once per web request from bootstrap; media responses replace the enforced one with their sandbox. */
function rmt_csp_send(): void {
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    header('Content-Security-Policy: ' . rmt_csp_enforced());
    header('Content-Security-Policy-Report-Only: ' . rmt_csp_report_only(rmt_csp_nonce()));
}
