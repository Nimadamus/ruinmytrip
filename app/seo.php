<?php
declare(strict_types=1);

function rmt_current_url(): string {
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return rtrim((string)cfg('app_url'), '/') . $path;
}

/**
 * Emit a JSON-LD script block.
 *
 * SECURITY: the payload is embedded in an HTML <script> element, so any literal "</script>" in a
 * string value would terminate the block early and let the rest of the value be parsed as HTML —
 * i.e. stored XSS via any user-controlled field that reaches JSON-LD (review titles, trip titles,
 * usernames, bios). JSON_HEX_TAG encodes < and > as < / >, which JSON-LD consumers
 * decode back to the original characters, so escaping costs nothing and closes the hole.
 * JSON_HEX_AMP/APOS/QUOT are included for the same reason.
 */
function jsonld(array $data): string {
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
           | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    // Drop null properties. Callers build these arrays with conditional entries (an
    // aggregateRating only exists once real reviews do), and emitting "aggregateRating":null is
    // an invalid, meaningless assertion in structured data. Absent is the correct encoding of
    // "we do not have this".
    return '<script type="application/ld+json">' . json_encode(rmt_jsonld_prune($data), $flags) . '</script>';
}

/** Recursively remove null values from a JSON-LD payload. */
function rmt_jsonld_prune(array $data): array {
    $out = [];
    foreach ($data as $k => $v) {
        if ($v === null) continue;
        $out[$k] = is_array($v) ? rmt_jsonld_prune($v) : $v;
    }
    return $out;
}

/** BreadcrumbList JSON-LD from [['name'=>,'url'=>], ...]. */
function breadcrumb_jsonld(array $crumbs): string {
    $items = [];
    foreach ($crumbs as $i => $c) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => $c['url']];
    }
    return jsonld(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items]);
}
