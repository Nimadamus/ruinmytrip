<?php
declare(strict_types=1);

/**
 * Scams and traps on a city page, from official sources only (2026-11-07).
 *
 * Data: database/scams/city_scams.json, keyed by destination slug. Every item names the scam,
 * says what happens and how to avoid it, and carries at least one source with the publisher, the
 * URL and a short quote from the page that supports it. Sources are government travel advice
 * (UK FCDO, US State Department, Smartraveller, Canada) or official city, police and transport pages.
 * No blogs, no forums, no numbers that the source does not print.
 *
 * Deliberately NOT a page per city. The September 2026 spam update hit this site, and templated
 * "[city] scams" pages are exactly what the search results are already full of. This is a section
 * on a city page that already exists, written only for cities where official sources say something
 * specific, and it stays empty everywhere else rather than padding.
 *
 * An item that fails validation (no source, a source that is not https, no quote) is dropped, never
 * shown without its evidence. tests/city_scams_test.php holds the file to the same rules.
 */

const RMT_CITY_SCAMS_FILE = BASE_PATH . '/database/scams/city_scams.json';

/** Every city's entry, validated, cached for the request. @return array<string, array> */
function rmt_city_scams_all(): array {
    static $all = null;
    if ($all !== null) return $all;
    $all = [];
    if (!is_file(RMT_CITY_SCAMS_FILE)) return $all;
    $raw = json_decode((string) file_get_contents(RMT_CITY_SCAMS_FILE), true);
    if (!is_array($raw)) return $all;
    foreach ($raw as $slug => $city) {
        if (!is_string($slug) || !is_array($city)) continue;
        $items = array_values(array_filter(array_map('rmt_city_scam_item', (array) ($city['items'] ?? []))));
        if (!$items) continue;
        $links = [];
        foreach ((array) ($city['official_links'] ?? []) as $l) {
            $u = is_array($l) ? (string) ($l['url'] ?? '') : (string) $l;
            if (rmt_city_scam_url_ok($u)) $links[] = ['url' => $u, 'label' => is_array($l) ? (string) ($l['label'] ?? $l['publisher'] ?? '') : ''];
        }
        $all[$slug] = ['items' => $items, 'official_links' => $links, 'checked' => (string) ($city['checked'] ?? '')];
    }
    return $all;
}

/** One city's entry, or null when there is nothing sourced to show. */
function rmt_city_scams(string $slug): ?array {
    return rmt_city_scams_all()[$slug] ?? null;
}

function rmt_city_scam_url_ok(string $u): bool {
    return (bool) preg_match('#^https://[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $u);
}

/** A display-ready item, or null when it is missing the evidence it needs. */
function rmt_city_scam_item($it): ?array {
    if (!is_array($it)) return null;
    $name = trim((string) ($it['name'] ?? ''));
    $what = trim((string) ($it['what_happens'] ?? ''));
    $avoid = trim((string) ($it['avoid'] ?? ''));
    if ($name === '' || $what === '') return null;
    $sources = [];
    foreach ((array) ($it['sources'] ?? []) as $s) {
        if (!is_array($s)) continue;
        $url = trim((string) ($s['url'] ?? ''));
        $pub = trim((string) ($s['publisher'] ?? ''));
        $quote = trim((string) ($s['quote'] ?? ''));
        if (!rmt_city_scam_url_ok($url) || $pub === '' || $quote === '') continue;
        $sources[] = ['url' => $url, 'publisher' => $pub, 'quote' => $quote];
    }
    if (!$sources) return null;
    return ['name' => $name, 'what' => $what, 'avoid' => $avoid, 'sources' => $sources,
            'checked' => trim((string) ($it['checked'] ?? ''))];
}
