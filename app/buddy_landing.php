<?php
/**
 * Travel buddy landing pages: /travel-buddies/{country} and /cruise-buddies/{line}.
 *
 * The copy lives in app/buddy_landing_pages.php. What makes a page more than its copy is the live
 * part: the real members and trips that match it, the cities it covers with how many people are
 * going to each, and the meetups coming up there. All of it comes from the same buddy search the
 * /buddies page runs, so a page can never show somebody the search itself would hide (blocks,
 * closed posts, private trips).
 */

require_once __DIR__ . '/buddy_landing_pages.php';

/** The page definition for a slug, or null. */
function rmt_buddy_landing(string $slug): ?array {
    $p = RMT_BUDDY_LANDING[$slug] ?? null;
    return $p ? $p + ['slug' => $slug] : null;
}

/** Site path of a landing page, without the leading slash. */
function rmt_buddy_landing_path(string $slug): string {
    $p = RMT_BUDDY_LANDING[$slug] ?? null;
    return ($p && $p['kind'] === 'cruise' ? 'cruise-buddies/' : 'travel-buddies/') . $slug;
}

/** Every landing page of one kind, as slug => definition, in file order. */
function rmt_buddy_landing_all(?string $kind = null): array {
    $out = [];
    foreach (RMT_BUDDY_LANDING as $slug => $p) {
        if ($kind === null || $p['kind'] === $kind) $out[$slug] = $p + ['slug' => $slug];
    }
    return $out;
}

/**
 * The country landing page that covers a destination, for links from city and country pages.
 * A page with pinned cities (Bali) covers only those; any other covers its whole country.
 */
function rmt_buddy_landing_for_dest(string $destSlug, string $country): ?array {
    foreach (rmt_buddy_landing_all('country') as $p) {
        if (!empty($p['dests'])) { if (in_array($destSlug, $p['dests'], true)) return $p; continue; }
        if ($p['country'] === $country) return $p;
    }
    return null;
}

/** The country landing page for a whole country, when there is exactly one that covers all of it. */
function rmt_buddy_landing_for_country(string $country): ?array {
    foreach (rmt_buddy_landing_all('country') as $p) {
        if (empty($p['dests']) && $p['country'] === $country) return $p;
    }
    return null;
}

/** The country page for a destination known only by its slug (trip pages carry no country). */
function rmt_buddy_landing_for_dest_slug(string $destSlug): ?array {
    if ($destSlug === '') return null;
    $d = q_one('SELECT country FROM destinations WHERE slug = ?', [$destSlug]);
    return $d ? rmt_buddy_landing_for_dest($destSlug, (string) $d['country']) : null;
}

/**
 * The landing page a single buddy post belongs to: its cruise line, else its city's country, else
 * a country named in its own "where" words. Null when none fits, which is most of the world.
 */
function rmt_buddy_landing_for_post(array $b): ?array {
    $line = mb_strtolower(trim((string) ($b['cruise_line'] ?? '')));
    if ($line !== '') {
        foreach (rmt_buddy_landing_all('cruise') as $p) {
            if (str_contains($line, mb_strtolower($p['line']))) return $p;
        }
    }
    if (!empty($b['dest_slug'])) {
        $p = rmt_buddy_landing_for_dest((string) $b['dest_slug'], (string) ($b['dest_country'] ?? ''));
        if ($p) return $p;
    }
    $where = mb_strtolower((string) ($b['where_text'] ?? ''));
    if ($where !== '') {
        foreach (rmt_buddy_landing_all('country') as $p) {
            if (preg_match('/\b' . preg_quote(mb_strtolower($p['name']), '/') . '\b/u', $where)) return $p;
        }
    }
    return null;
}

/**
 * The landing page a search query is asking about: a country or cruise line by name ("greece",
 * "royal caribbean", "ncl"), or a city we have in one of those countries ("santorini").
 */
function rmt_buddy_landing_for_query(string $q): ?array {
    $q = mb_strtolower(trim(preg_replace('/\s+/', ' ', $q)));
    $q = trim(preg_replace('/\b(travel|buddy|buddies|cruise|cruises|cruising|line|trip|trips|partner|partners|in|to|for)\b/u', ' ', $q));
    $q = trim(preg_replace('/\s+/', ' ', $q));
    if ($q === '' || mb_strlen($q) < 3) return null;
    foreach (rmt_buddy_landing_all() as $p) {
        $names = [mb_strtolower($p['name']), str_replace('-', ' ', $p['slug'])];
        if ($p['kind'] === 'cruise') $names[] = mb_strtolower($p['line']);
        if ($p['slug'] === 'norwegian') $names[] = 'ncl';
        if (in_array($q, $names, true)) return $p;
    }
    $d = q_one('SELECT slug, country FROM destinations WHERE LOWER(name) = ? OR slug = ? LIMIT 1', [$q, $q]);
    return $d ? rmt_buddy_landing_for_dest((string) $d['slug'], (string) $d['country']) : null;
}

/** The cities a page covers, with how many people are going to each and upcoming meetups. */
function rmt_buddy_landing_cities(array $p): array {
    if ($p['kind'] !== 'country') return [];
    if (!empty($p['dests'])) {
        $in = implode(',', array_fill(0, count($p['dests']), '?'));
        $rows = q_all("SELECT id, slug, name, country, hero_url FROM destinations WHERE slug IN ($in) ORDER BY name", $p['dests']);
    } else {
        $rows = q_all('SELECT id, slug, name, country, hero_url FROM destinations WHERE country = ? ORDER BY name', [$p['country']]);
    }
    $today = date('Y-m-d'); $now = date('Y-m-d H:i:s');
    foreach ($rows as &$d) {
        $d['going'] = (int) (q_one("SELECT COUNT(*) c FROM trips WHERE destination_id = ? AND visibility = 'public'
                                     AND status = 'published' AND date_from IS NOT NULL AND date_to >= ?", [(int) $d['id'], $today])['c'] ?? 0);
        $d['meetups'] = (int) (q_one("SELECT COUNT(*) c FROM meetups WHERE destination_id = ? AND status = 'published'
                                       AND date_start >= ?", [(int) $d['id'], $now])['c'] ?? 0);
    }
    unset($d);
    return $rows;
}

function buddy_landing_show(array $a): void {
    $slug = (string) ($a['slug'] ?? '');
    $kind = (string) ($a['kind'] ?? '');
    $p = rmt_buddy_landing($slug);
    if (!$p || $p['kind'] !== $kind) not_found();
    $me = current_user();

    /* Real people only. The examples the /buddies page shows while it is thin are not shown here:
       a page about one country that filled itself with invented travelers would be exactly the
       doorway page this is trying not to be. */
    $cards = []; $seen = [];
    foreach ($p['search'] as $g) {
        $res = rmt_buddy_search(rmt_buddy_filters($g), $me, 24);
        foreach ($res['cards'] as $c) {
            $k = $c['kind'] . ':' . $c['id'];
            if (isset($seen[$k])) continue;
            $seen[$k] = true; $cards[] = $c;
        }
    }
    $cards = rmt_buddy_card_interests(array_slice($cards, 0, 12));

    $cities = rmt_buddy_landing_cities($p);
    $meetups = [];
    if ($cities) {
        $ids = array_map(static fn($d) => (int) $d['id'], $cities);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $meetups = q_all("SELECT m.id, m.title, m.date_start, d.name dest_name FROM meetups m
                           JOIN destinations d ON d.id = m.destination_id
                          WHERE m.destination_id IN ($in) AND m.status = 'published' AND m.date_start >= ?
                          ORDER BY m.date_start LIMIT 6", array_merge($ids, [date('Y-m-d H:i:s')]));
    }

    $postQuery = $p['kind'] === 'cruise' ? ['type' => 'cruise', 'line' => $p['line']] : ['where' => $p['name']];
    $postPath = '/buddies/new?' . http_build_query($postQuery);
    $postHref = $me ? url(ltrim($postPath, '/')) : url('register?return=' . rawurlencode($postPath));

    $path = rmt_buddy_landing_path($slug);
    $hub = $p['kind'] === 'cruise' ? ['name' => 'Cruise buddies', 'url' => url('buddies/cruise')]
                                    : ['name' => 'Travel buddies', 'url' => url('buddies')];
    $crumbs = [['name' => 'Home', 'url' => url()], $hub, ['name' => $p['name'], 'url' => url($path)]];
    $related = array_filter(rmt_buddy_landing_all($p['kind']), static fn($r) => $r['slug'] !== $slug);

    $ld = jsonld(['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $p['title'],
                  'description' => $p['desc'], 'url' => url($path),
                  'isPartOf' => ['@type' => 'WebSite', 'name' => 'RuinMyTrip', 'url' => url()]])
        . jsonld(['@context' => 'https://schema.org', '@type' => 'FAQPage',
                  'mainEntity' => array_map(static fn($f) => ['@type' => 'Question', 'name' => $f[0],
                      'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $p['faq'])]);

    $meta = ['title' => $p['title'] . ' | RuinMyTrip', 'description' => $p['desc'], 'canonical' => url($path),
             'breadcrumbs' => $crumbs, 'jsonld' => $ld];
    if ($cities && !empty($cities[0]['hero_url'])) $meta['og_image'] = abs_url($cities[0]['hero_url']);
    view('buddy_landing', compact('p', 'me', 'cards', 'cities', 'meetups', 'postHref', 'related', 'path'), $meta);
}

function buddy_landing_country(array $a): void { buddy_landing_show($a + ['kind' => 'country']); }
function buddy_landing_cruise(array $a): void { buddy_landing_show($a + ['kind' => 'cruise']); }
