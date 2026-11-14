<?php
/**
 * Destination hubs: the live travel partner block on a city page, the warnings directory, and the content threshold that keeps a newly seeded city out of the index until
 * there is something on it (migration 125).
 *
 * THE THRESHOLD (Nima, 2026-11-14). A SEEDED city, one added in bulk rather than built by hand
 * (destinations.seeded_at is set), stays noindex and out of public listings until it has any one of:
 *
 *     a buddy trip posted for it in the last RMT_HUB_LOOKBACK_DAYS days
 *     a check-in to it in the last RMT_HUB_LOOKBACK_DAYS days
 *     RMT_HUB_MIN_WARNINGS or more sourced warnings
 *
 * The lookback is what stops a page flipping in and out of the index: a check-in lasts hours, and
 * a rule read from "is anybody checked in this minute" would change its answer every day.
 *
 * The cities that existed before seeding are GRANDFATHERED. seeded_at is null on every one of
 * them, this file never looks at them, and their index rule is the one they always had
 * (rmt_indexable('destination') in app/indexability.php).
 *
 * Every number shown comes from rows that exist, and is not shown when it is zero. Nothing here
 * invents a crowd.
 */
declare(strict_types=1);

const RMT_HUB_LOOKBACK_DAYS = 90;
const RMT_HUB_MIN_WARNINGS = 3;
/** A warnings page for one city and one kind of scam exists only with this many sourced items. */
const RMT_WARNING_TYPE_MIN_ITEMS = 3;

/** URL words for each kind of warning (the keys are RMT_SCAM_TYPES in app/city_scams.php). */
const RMT_WARNING_TYPE_SLUGS = [
    'taxi' => 'taxi-scams', 'spiking' => 'drink-spiking', 'pickpocket' => 'pickpockets', 'card' => 'card-and-atm-fraud',
    'bill' => 'restaurant-scams', 'money' => 'money-changers', 'police' => 'fake-police', 'touts' => 'fake-guides-and-touts',
    'kidnap' => 'express-kidnapping', 'shopping' => 'shopping-scams', 'rental' => 'rental-scams',
    'virtual' => 'virtual-kidnapping', 'dating' => 'dating-app-setups',
];

function rmt_hub_now(?string $now = null): string { return $now ?? date('Y-m-d H:i:s'); }

/* ---------- the threshold ---------- */

/** Ids of seeded cities. Empty, not an error, where the column does not exist yet. @return array<int,true> */
function rmt_hub_seeded_ids(bool $fresh = false): array {
    static $ids = null;
    if ($ids !== null && !$fresh) return $ids;
    $ids = [];
    try {
        foreach (q_all('SELECT id FROM destinations WHERE seeded_at IS NOT NULL') as $r) $ids[(int) $r['id']] = true;
    } catch (\PDOException $e) {
        $ids = [];
    }
    return $ids;
}

function rmt_hub_is_seeded(array $d): bool {
    if (array_key_exists('seeded_at', $d)) return !empty($d['seeded_at']);
    return isset(rmt_hub_seeded_ids()[(int) ($d['id'] ?? 0)]);
}

/**
 * What each city has had in the lookback window, for every city at once (two grouped queries,
 * kept for the request).
 *
 * @return array{trips:array<int,int>, checkins:array<int,int>}
 */
function rmt_hub_recent_activity(?string $now = null, bool $fresh = false): array {
    static $memo = [];
    $now = rmt_hub_now($now);
    if (!$fresh && isset($memo[$now])) return $memo[$now];
    $cut = date('Y-m-d H:i:s', strtotime($now) - RMT_HUB_LOOKBACK_DAYS * 86400);
    $out = ['trips' => [], 'checkins' => []];
    foreach (q_all("SELECT b.destination_id, COUNT(*) n FROM buddy_posts b JOIN users u ON u.id = b.user_id AND u.status = 'active'
                     WHERE b.destination_id IS NOT NULL AND b.status IN ('open','closed','completed') AND b.created_at >= ?
                     GROUP BY b.destination_id", [$cut]) as $r) $out['trips'][(int) $r['destination_id']] = (int) $r['n'];
    foreach (q_all("SELECT c.destination_id, COUNT(*) n FROM active_checkins c JOIN users u ON u.id = c.user_id AND u.status = 'active'
                     WHERE c.created_at >= ? GROUP BY c.destination_id", [$cut]) as $r) $out['checkins'][(int) $r['destination_id']] = (int) $r['n'];
    return $memo[$now] = $out;
}

/**
 * Does a city meet the content threshold? The answer and the reason, so the admin report and a
 * test can both say why.
 *
 * @return array{ok:bool, reason:string, trips:int, checkins:int, warnings:int}
 */
function rmt_hub_threshold(int $destId, string $slug, ?string $now = null): array {
    $act = rmt_hub_recent_activity($now);
    $trips = $act['trips'][$destId] ?? 0;
    $checkins = $act['checkins'][$destId] ?? 0;
    $warnings = function_exists('rmt_city_scams') ? count(rmt_city_scams($slug)['items'] ?? []) : 0;
    $reason = $trips > 0 ? 'a trip in the last ' . RMT_HUB_LOOKBACK_DAYS . ' days'
            : ($checkins > 0 ? 'a check-in in the last ' . RMT_HUB_LOOKBACK_DAYS . ' days'
            : ($warnings >= RMT_HUB_MIN_WARNINGS ? $warnings . ' sourced warnings' : 'below the threshold'));
    return ['ok' => $trips > 0 || $checkins > 0 || $warnings >= RMT_HUB_MIN_WARNINGS, 'reason' => $reason,
            'trips' => $trips, 'checkins' => $checkins, 'warnings' => $warnings];
}

/**
 * Seeded cities that are still below the threshold: noindex, and left out of the public lists
 * (explore, nearby cities, the country pages at /in/{country}, the sitemap). They can still be picked in a form and
 * opened by their address, which is how one earns its way out.
 *
 * @return array<int,true>
 */
function rmt_hub_hidden_ids(?string $now = null): array {
    $seeded = rmt_hub_seeded_ids();
    if (!$seeded) return [];
    $slugs = [];
    foreach (q_all('SELECT id, slug FROM destinations WHERE seeded_at IS NOT NULL') as $r) $slugs[(int) $r['id']] = (string) $r['slug'];
    $hidden = [];
    foreach ($slugs as $id => $slug) if (!rmt_hub_threshold($id, $slug, $now)['ok']) $hidden[$id] = true;
    return $hidden;
}

/* ---------- the block on a city page ---------- */

/**
 * What is happening in a city that somebody could join: open trips looking for company, and how
 * many travelers are checked in right now. Two queries.
 *
 * @return array{open_trips:list<array<string,mixed>>, open_trip_count:int, checked_in:int}
 */
function rmt_hub_live(int $destId, ?string $now = null): array {
    $now = rmt_hub_now($now);
    $rows = q_all("SELECT b.id, b.title, b.date_from, b.date_to, b.spots, b.budget, b.travel_pace
                     FROM buddy_posts b JOIN users u ON u.id = b.user_id AND u.status = 'active' AND COALESCE(u.email,'') NOT LIKE '%+rmtqa%'
                    WHERE b.destination_id = ? AND b.status = 'open' AND b.date_to >= ?
                    ORDER BY b.date_from, b.id LIMIT 25", [$destId, substr($now, 0, 10)]);
    return ['open_trips' => array_slice($rows, 0, 3), 'open_trip_count' => count($rows),
            'checked_in' => function_exists('rmt_city_room_count') ? rmt_city_room_count($destId, $now) : 0];
}

/**
 * How many different travelers are looking for company in a city in the next 30 days: an open
 * buddy trip that overlaps the window, or a check-in that is live now. For the line under a
 * warnings page. Counted, never rounded up.
 */
function rmt_hub_partner_count(int $destId, ?string $now = null): int {
    $now = rmt_hub_now($now);
    $today = substr($now, 0, 10);
    $until = date('Y-m-d', strtotime($today) + 30 * 86400);
    $ids = [];
    foreach (q_all("SELECT DISTINCT b.user_id FROM buddy_posts b JOIN users u ON u.id = b.user_id AND u.status = 'active' AND COALESCE(u.email,'') NOT LIKE '%+rmtqa%'
                     WHERE b.destination_id = ? AND b.status = 'open' AND b.date_to >= ? AND b.date_from <= ?", [$destId, $today, $until]) as $r) $ids[(int) $r['user_id']] = true;
    foreach (q_all("SELECT DISTINCT c.user_id FROM active_checkins c JOIN users u ON u.id = c.user_id AND u.status = 'active'
                     WHERE c.destination_id = ? AND c.ended_at IS NULL AND c.expires_at > ?", [$destId, $now]) as $r) $ids[(int) $r['user_id']] = true;
    return count($ids);
}

/* ---------- warnings ---------- */

/** Warning type key for a URL word, or null. */
function rmt_warning_type_from_slug(string $slug): ?string {
    $k = array_search($slug, RMT_WARNING_TYPE_SLUGS, true);
    return $k === false ? null : (string) $k;
}

/**
 * A city's sourced warnings for the directory, with the kinds that have enough items for a page
 * of their own.
 *
 * @return ?array{d:array, items:list<array>, official_links:list<array>, checked:string, types:array<string,int>, type_pages:list<string>}
 */
function rmt_warnings_city(string $slug): ?array {
    $scams = function_exists('rmt_city_scams') ? rmt_city_scams($slug) : null;
    if (!$scams) return null;
    $d = q_one('SELECT * FROM destinations WHERE slug = ?', [$slug]);
    if (!$d) return null;
    $types = [];
    foreach ($scams['items'] as $it) foreach ($it['types'] as $t) $types[$t] = ($types[$t] ?? 0) + 1;
    arsort($types);
    $checked = (string) $scams['checked'];
    foreach ($scams['items'] as $it) if ((string) ($it['checked'] ?? '') > $checked) $checked = (string) $it['checked'];
    return ['d' => $d, 'items' => $scams['items'], 'official_links' => $scams['official_links'], 'checked' => $checked,
            'types' => $types,
            'type_pages' => array_keys(array_filter($types, static fn(int $n): bool => $n >= RMT_WARNING_TYPE_MIN_ITEMS))];
}

/** Is the warnings page for a city worth an index entry? The same count the threshold uses. */
function rmt_warnings_indexable(array $w, ?string $type = null): bool {
    if ($type !== null) return ($w['types'][$type] ?? 0) >= RMT_WARNING_TYPE_MIN_ITEMS;
    return count($w['items']) >= RMT_HUB_MIN_WARNINGS;
}

/**
 * Title and description for a warnings page. The title is the approved "Is {City} Safe?" form,
 * used on these new pages only; city pages keep the titles they have.
 *
 * It says "travel partners", not "verified travel partners": nobody's identity is verified here,
 * and a title is a published claim like any other.
 *
 * @return array{title:string, description:string, h1:string}
 */
function rmt_warnings_meta(array $w, ?string $type = null): array {
    $city = (string) $w['d']['name'];
    $n = count($w['items']);
    $checked = $w['checked'] !== '' ? ', checked ' . date('j M Y', strtotime($w['checked']) ?: time()) : '';
    if ($type !== null) {
        $label = (string) (RMT_SCAM_TYPES[$type] ?? $type);
        $short = trim((string) preg_replace('/:.*$/', '', $label));
        $tn = (int) ($w['types'][$type] ?? 0);
        return [
            'title' => "$short in $city: What Official Advice Warns About | RuinMyTrip",
            'h1' => "$short in $city",
            'description' => "$tn sourced warnings about " . lcfirst($short) . " in $city from official travel advice$checked: how each one works and how to avoid it.",
        ];
    }
    return [
        'title' => "Is $city Safe? Tourist Traps, Scams and Travel Partners in $city | RuinMyTrip",
        'h1' => "Is $city safe? Tourist traps and scams to know before you go",
        'description' => "$n sourced scam and safety warnings for $city from official travel advice$checked: how each one works, how to avoid it, and travelers going when you are.",
    ];
}

/**
 * FAQPage markup, only for question and answer pairs that are written on the page and each carry
 * a source. A city entry in the scams file may hold a `faq` list for this; none does today, so
 * this returns '' and no markup is emitted. It never builds questions out of other content.
 *
 * @param list<array{q:string, a:string, source:string}> $pairs
 */
function rmt_hub_faq_jsonld(array $pairs): string {
    $ok = array_values(array_filter($pairs, static fn($p): bool => is_array($p)
        && trim((string) ($p['q'] ?? '')) !== '' && trim((string) ($p['a'] ?? '')) !== ''
        && preg_match('#^https://#', (string) ($p['source'] ?? '')) === 1));
    if (!$ok || !function_exists('jsonld')) return '';
    return jsonld(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn(array $p): array => [
        '@type' => 'Question', 'name' => (string) $p['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $p['a']],
    ], $ok)]);
}

/* ---------- controllers ---------- */

/** GET /warnings/{city} and /warnings/{city}/{type}. */
function warnings_city(array $a): void {
    $w = rmt_warnings_city((string) $a['slug']);
    if (!$w) not_found();
    $type = null;
    if (isset($a['type'])) {
        $type = rmt_warning_type_from_slug((string) $a['type']);
        /* A kind of warning with too few sourced items in this city has no page: it would be two
           sentences under a headline. */
        if ($type === null || !rmt_warnings_indexable($w, $type)) not_found();
    }
    $d = $w['d'];
    $meta = rmt_warnings_meta($w, $type);
    $hidden = rmt_hub_hidden_ids();
    $path = 'warnings/' . $d['slug'] . ($type !== null ? '/' . RMT_WARNING_TYPE_SLUGS[$type] : '');
    view('warnings_city', [
        'w' => $w, 'd' => $d, 'type' => $type, 'wm' => $meta,
        'items' => $type === null ? $w['items'] : array_values(array_filter($w['items'], static fn(array $it): bool => in_array($type, $it['types'], true))),
        'partners' => rmt_hub_partner_count((int) $d['id']),
        'cityListed' => !isset($hidden[(int) $d['id']]),
    ], [
        'title' => $meta['title'], 'description' => $meta['description'], 'canonical' => url($path),
        'robots' => rmt_warnings_indexable($w, $type) ? 'index, follow' : 'noindex,follow',
        'breadcrumbs' => array_values(array_filter([
            ['name' => 'Home', 'url' => url()], ['name' => 'Travel warnings', 'url' => url('ruined')],
            ['name' => (string) $d['name'], 'url' => url('warnings/' . $d['slug'])],
            $type !== null ? ['name' => trim((string) preg_replace('/:.*$/', '', (string) RMT_SCAM_TYPES[$type])), 'url' => url($path)] : null,
        ])),
    ]);
}
