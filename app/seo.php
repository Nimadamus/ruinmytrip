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
    /* The old version was honest and too long: "Book of Kells Experience at Trinity College,
       Dublin 2026: tickets, prices and is it worth visiting | RuinMyTrip" is 110 characters, and
       a search result shows about 60. Everything that made it a good title -- the word tickets,
       the word prices, the year -- was past the cut.

       So it is assembled to a budget instead, dropping the least load-bearing part first: the
       brand, then the year, then the city. The name and what the page answers always survive,
       because those are the two things somebody is searching for. */
    $answer = match ((string) ($p['type'] ?? '')) {
        'hotel'      => 'prices & fees',
        'restaurant' => 'prices & hours',
        default      => 'tickets & prices',
    };
    $name = trim((string) ($p['name'] ?? 'Place'));
    $city = trim((string) ($p['dest_name'] ?? ''));
    $year = date('Y');

    foreach ([
        $name . ', ' . $city . ' ' . $year . ': ' . $answer . ' | RuinMyTrip',
        $name . ', ' . $city . ' ' . $year . ': ' . $answer,
        $name . ' ' . $year . ': ' . $answer,
        $name . ': ' . $answer,
    ] as $candidate) {
        if ($city === '' && str_contains($candidate, ', ')) continue;
        if (mb_strlen($candidate) <= 60) return $candidate;
    }
    /* A name that will not fit beside the answer gets shortened, not the answer. "Book of Kells
       Experience at Trinity…: tickets & prices" is a usable result; the same name alone is a page
       about nothing in particular. */
    $room = 60 - mb_strlen(': ' . $answer) - 1;
    $short = mb_substr($name, 0, max(12, $room));
    $sp = mb_strrpos($short, ' ');
    if ($sp !== false && $sp > $room * 0.5) $short = mb_substr($short, 0, $sp);
    return rtrim($short, " ,;:-–—") . '…: ' . $answer;
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
    $add('/founding');
    $add('/start');
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

    foreach (q_all('SELECT DISTINCT country FROM destinations WHERE country IS NOT NULL AND country <> \'\'') as $c) {
        $add('in/'.rmt_country_slug((string) $c['country']));
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

/* ------------------------------------------------------------------ announcing new pages */

/**
 * Remember that a URL is new or changed, so the next flush can tell search engines about it.
 *
 * Deliberately not a submit. Publishing a post must not wait on somebody else's API, and a search
 * engine being briefly out of date is a smaller problem than a member watching a spinner because
 * api.indexnow.org is slow. Writing the row is one insert; the talking happens on a schedule.
 *
 * Silently does nothing if the queue table is not there yet, so a deploy that runs the app before
 * its migration cannot take publishing down with it.
 */
function rmt_seo_announce(string $path): void {
    $url = str_starts_with($path, 'http') ? $path : url(ltrim($path, '/'));
    try {
        q_run('INSERT INTO seo_ping_queue (url, created_at) VALUES (?,?)', [$url, date('Y-m-d H:i:s')]);
    } catch (\PDOException $e) {
        // A duplicate means it is already queued, which is the correct state. Anything else is
        // not worth failing a publish over.
    }
}

/**
 * Flush, but only when there is something that has been waiting a while.
 *
 * This is called from the sitemap request, which is the one moment where announcing is obviously
 * relevant and where the cost lands on a crawler rather than on a member. The age check batches a
 * burst of publishing into one submission and stops a busy hour turning into an API call per page.
 */
function rmt_seo_flush_if_due(int $minAgeMinutes = 5): int {
    try {
        $oldest = q_one('SELECT created_at FROM seo_ping_queue WHERE sent_at IS NULL ORDER BY id LIMIT 1');
    } catch (\PDOException $e) {
        return 0;   // table not migrated yet; a sitemap request is not the place to fail
    }
    if (!$oldest) return 0;
    if (strtotime((string) $oldest['created_at']) > time() - $minAgeMinutes * 60) return 0;
    return rmt_seo_flush(500);
}

/** @return list<string> URLs waiting to go out, oldest first. */
function rmt_seo_pending(int $limit = 500): array {
    $rows = q_all('SELECT url FROM seo_ping_queue WHERE sent_at IS NULL ORDER BY id LIMIT ' . (int) $limit);
    return array_map(static fn(array $r): string => (string) $r['url'], $rows);
}

/**
 * Submit everything waiting and mark it done. Returns how many URLs went out.
 *
 * A failed submit leaves the rows pending on purpose: IndexNow being down for an hour should cost
 * an hour of delay, not the announcement itself.
 */
function rmt_seo_flush(int $limit = 500): int {
    $urls = rmt_seo_pending($limit);
    if (!$urls) return 0;
    if (!rmt_indexnow_submit($urls)) return 0;
    $now = date('Y-m-d H:i:s');
    $in = implode(',', array_fill(0, count($urls), '?'));
    q_run("UPDATE seo_ping_queue SET sent_at = ? WHERE url IN ($in)", array_merge([$now], $urls));
    return count($urls);
}

/* --------------------------------------------------------------- what a result looks like */

/**
 * A title that survives being shown in a search result.
 *
 * Google gives a title roughly 60 characters before it truncates, and every character spent on
 * something the reader already knows is one the headline does not get. Review pages were spending
 * theirs on " — review by @ruinmytrip | RuinMyTrip": a suffix on every result, on a site whose
 * name is already in the URL and the breadcrumb, pushing the actual subject off the end.
 *
 * The brand is appended only when it fits. Being recognisable is worth something; being
 * recognisable instead of legible is not.
 */
function rmt_meta_title(string $head, string $brand = 'RuinMyTrip', int $max = 60): string {
    $head = trim(preg_replace('/\s+/', ' ', strip_tags($head)) ?? '');
    if ($head === '') return $brand;
    if (mb_strlen($head) > $max) {
        $cut = mb_substr($head, 0, $max);
        $sp = mb_strrpos($cut, ' ');
        // Never break a word: a title ending "expensi…" reads as a broken page, not a long one.
        // A headline this long has already used the whole budget, so the brand does not go on.
        return rtrim($sp !== false && $sp > $max * 0.6 ? mb_substr($cut, 0, $sp) : $cut, " ,;:-–—") . '…';
    }
    $withBrand = $head . ' | ' . $brand;
    return mb_strlen($withBrand) <= $max ? $withBrand : $head;
}

/**
 * A description that ends where a sentence does.
 *
 * The old one took 155 characters of the body and hung an ellipsis off whatever word it landed in
 * the middle of, so the one line a searcher reads before deciding stopped mid-thought. Preferring
 * a sentence boundary costs a few characters and reads like something somebody wrote.
 */
function rmt_meta_description(string $text, int $max = 155): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    if ($text === '') return '';
    if (mb_strlen($text) <= $max) return $text;

    $cut = mb_substr($text, 0, $max);
    // The last sentence that finishes inside the budget, if it is not so early that we throw the
    // description away to get it.
    if (preg_match_all('/[.!?](?=\s|$)/u', $cut, $m, PREG_OFFSET_CAPTURE)) {
        $last = (int) end($m[0])[1];
        $upTo = mb_strlen(substr($cut, 0, $last + 1));
        // A third of the budget is enough: a complete short sentence reads better than a long
        // one that stops mid-clause, but a two-word opener is not a description.
        if ($upTo >= $max * 0.35) return mb_substr($cut, 0, $upTo);
    }
    $sp = mb_strrpos($cut, ' ');
    return rtrim($sp !== false ? mb_substr($cut, 0, $sp) : $cut, " ,;:-–—") . '…';
}
