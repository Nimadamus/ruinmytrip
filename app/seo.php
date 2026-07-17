<?php
declare(strict_types=1);

function rmt_current_url(): string {
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return rtrim((string)cfg('app_url'), '/') . $path;
}

/** Emit a JSON-LD script block. */
function jsonld(array $data): string {
    return '<script type="application/ld+json">' .
        json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

/** BreadcrumbList JSON-LD from [['name'=>,'url'=>], ...]. */
function breadcrumb_jsonld(array $crumbs): string {
    $items = [];
    foreach ($crumbs as $i => $c) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => $c['url']];
    }
    return jsonld(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items]);
}
