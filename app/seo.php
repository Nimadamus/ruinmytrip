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

/**
 * Destination hub title. Generic "travel guide, reviews & meetups" cannot rank a new domain
 * against TripAdvisor. The unique asset is 2026 costs, taxes, tickets, and friction.
 */
function rmt_destination_page_title(array $d): string {
    return $d['name'].' 2026: costs, tickets, taxes and what nearly ruins it | RuinMyTrip';
}

/** Place page title: price/ticket intent, not a fake "reviewed by travelers" claim. */
function rmt_place_page_title(array $p): string {
    $q = match ((string) ($p['type'] ?? '')) {
        'hotel'      => 'prices, fees and is it worth staying',
        'restaurant' => 'prices, hours and is it worth eating at',
        default      => 'tickets, prices and is it worth visiting',
    };
    $city = (string) ($p['dest_name'] ?? '');
    $name = (string) ($p['name'] ?? 'Place');
    return $city === '' ? $name.' 2026: '.$q.' | RuinMyTrip'
                        : $name.', '.$city.' 2026: '.$q.' | RuinMyTrip';
}

/** YYYY-MM-DD for sitemap lastmod, or null when we do not actually know. */
function rmt_sitemap_day(?string $ts): ?string {
    if ($ts === null || $ts === '') return null;
    $t = strtotime($ts);
    return $t ? gmdate('Y-m-d', $t) : null;
}

/**
 * Public URLs worth sending to crawlers. Empty community indexes (meetups, going, leaderboard,
 * blog with no posts) stay out: they are thin pages, not a ranking strategy.
 *
 * @return list<array{loc:string, lastmod:?string}>
 */
function rmt_sitemap_entries(): array {
    $out = [];
    $add = static function (string $path, ?string $mod = null) use (&$out): void {
        $out[] = ['loc' => url(ltrim($path, '/')), 'lastmod' => rmt_sitemap_day($mod)];
    };

    $add('/');
    $add('/explore');
    $add('/travelers');
    $add('/guides');
    $add('/reviews');
    $add('/editorial-policy');
    $add('/terms');
    $add('/privacy');
    $add('/guidelines');
    $add('/affiliate');
    $add('/safety');

    $nBlog = (int) (q_one("SELECT COUNT(*) c FROM blog_posts WHERE status='published'")['c'] ?? 0);
    if ($nBlog > 0) $add('/blog');

    $nCol = (int) (q_one("SELECT COUNT(*) c FROM collections WHERE status='published'")['c'] ?? 0);
    if ($nCol > 0) $add('/collections');

    $nMeet = (int) (q_one("SELECT COUNT(*) c FROM meetups WHERE status='published'")['c'] ?? 0);
    if ($nMeet > 0) $add('/meetups');

    $nGoing = (int) (q_one("SELECT COUNT(*) c FROM going WHERE visibility='public'")['c'] ?? 0);
    if ($nGoing > 0) $add('/going');

    $nActivity = (int) (q_one("SELECT (
        (SELECT COUNT(*) FROM reviews WHERE status='published') +
        (SELECT COUNT(*) FROM guides WHERE status='published') +
        (SELECT COUNT(*) FROM blog_posts WHERE status='published') +
        (SELECT COUNT(*) FROM trips WHERE status='published')
    ) c")['c'] ?? 0);
    if ($nActivity > 0) $add('/discover');

    $nCommunity = (int) (q_one(
        "SELECT COUNT(*) c FROM reviews r JOIN users u ON u.id=r.user_id
         WHERE r.status='published' AND u.role <> ?",
        [RMT_EDITORIAL_ROLE]
    )['c'] ?? 0);
    if ($nCommunity > 0) $add('/leaderboard');

    if (function_exists('rmt_top_tags')) {
        $tags = rmt_top_tags(100);
        if ($tags) {
            $add('/tags');
            foreach ($tags as $t) $add('/tag/'.$t['name']);
        }
    }

    foreach (q_all('SELECT slug FROM destinations') as $d) $add('/d/'.$d['slug']);

    foreach (q_all("SELECT DISTINCT d.slug FROM destinations d
                    WHERE EXISTS (SELECT 1 FROM trip_photos tp JOIN trips t ON t.id=tp.trip_id WHERE t.destination_id=d.id AND t.status='published')
                       OR EXISTS (SELECT 1 FROM review_photos rp JOIN reviews r ON r.id=rp.review_id WHERE r.destination_id=d.id AND r.status='published')") as $d) {
        $add('/d/'.$d['slug'].'/photos');
    }
    foreach (q_all("SELECT DISTINCT d.slug FROM destinations d JOIN places p ON p.destination_id=d.id
                    WHERE p.status='active'") as $d) {
        $add('/d/'.$d['slug'].'/places');
    }
    foreach (q_all("SELECT DISTINCT p.slug FROM places p JOIN reviews r ON r.place_id=p.id
                    WHERE p.status='active' AND r.status='published'") as $p) {
        $add('/p/'.$p['slug']);
    }
    foreach (q_all("SELECT id,slug,created_at FROM trips WHERE status='published'") as $t) {
        $add('trip/'.$t['id'].'/'.$t['slug'], $t['created_at'] ?? null);
    }
    foreach (q_all("SELECT slug, created_at FROM guides WHERE status='published'") as $g) {
        $add('g/'.$g['slug'], $g['created_at'] ?? null);
    }
    foreach (q_all("SELECT slug, created_at FROM collections WHERE status='published'") as $c) {
        $add('c/'.$c['slug'], $c['created_at'] ?? null);
    }
    foreach (q_all("SELECT slug, created_at FROM blog_posts WHERE status='published'") as $bp) {
        $add('blog/'.$bp['slug'], $bp['created_at'] ?? null);
    }
    foreach (q_all("SELECT id, slug, title, subject_name, created_at FROM reviews WHERE status='published'") as $rv) {
        $add('review/'.$rv['id'].'/'.($rv['slug'] ?: rmt_review_slug($rv)), $rv['created_at'] ?? null);
    }
    foreach (q_all("SELECT username FROM users WHERE status='active'") as $u) {
        $add('u/'.$u['username']);
    }
    return $out;
}

const RMT_INDEXNOW_KEY = 'b7c4e91a2d8f4e0c9a1b6d5f3e8c2a70';

/**
 * Submit URLs to IndexNow (Bing, Yandex, others). Fire-and-forget; never call on a page view.
 * Google does not consume IndexNow; it still needs the sitemap.
 */
function rmt_indexnow_submit(array $urls): bool {
    $urls = array_values(array_unique(array_filter(array_map('strval', $urls))));
    if (!$urls) return false;
    $host = parse_url((string) cfg('app_url'), PHP_URL_HOST) ?: 'ruinmytrip.com';
    $payload = json_encode([
        'host' => $host,
        'key' => RMT_INDEXNOW_KEY,
        'keyLocation' => 'https://'.$host.'/'.RMT_INDEXNOW_KEY.'.txt',
        'urlList' => array_slice($urls, 0, 10000),
    ], JSON_UNESCAPED_SLASHES);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json; charset=utf-8\r\nUser-Agent: RuinMyTrip/1.0 IndexNow\r\n",
        'content' => $payload,
        'timeout' => 15,
        'ignore_errors' => true,
    ]]);
    $ok = @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
    return $ok !== false;
}
