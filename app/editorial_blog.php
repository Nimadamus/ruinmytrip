<?php
declare(strict_types=1);

/**
 * Editorial blog posts live in database/editorial/blog.json and are upserted by slug.
 * Called from migrate.php on production boot (so a deploy publishes them) and from
 * publish_editorial.php. Idempotent: unchanged rows are left alone so lastmod does not bounce.
 */
function rmt_editorial_blog_path(): string {
    return BASE_PATH . '/database/editorial/blog.json';
}

/** @return list<array<string,mixed>> */
function rmt_editorial_blog_items(): array {
    $path = rmt_editorial_blog_path();
    if (!is_file($path)) return [];
    $json = json_decode((string) file_get_contents($path), true);
    return is_array($json) ? array_values($json['posts'] ?? []) : [];
}

/**
 * Insert or update editorial blog posts. Returns how many rows were written (0 if unchanged).
 */
function rmt_upsert_editorial_blog(): int {
    $ed = q_one("SELECT id FROM users WHERE role = ? ORDER BY id LIMIT 1", [RMT_EDITORIAL_ROLE]);
    if (!$ed) return 0;
    $uid = (int) $ed['id'];
    $allowed = ['stories', 'tips', 'safety', 'budget', 'gear', 'news'];
    $written = 0;
    foreach (rmt_editorial_blog_items() as $p) {
        $slug = trim((string) ($p['slug'] ?? ''));
        $title = trim((string) ($p['title'] ?? ''));
        $summary = trim((string) ($p['summary'] ?? ''));
        $body = (string) ($p['body'] ?? '');
        $cat = (string) ($p['category'] ?? 'news');
        if ($slug === '' || $title === '' || $summary === '' || mb_strlen(strip_tags($body)) < 400) continue;
        if (!in_array($cat, $allowed, true)) $cat = 'news';
        foreach ([$title, $summary, $body] as $chunk) {
            if (str_contains($chunk, "\u{2014}")) continue 2;
        }
        $cover = null;
        if (!empty($p['destination_slug'])) {
            $d = q_one('SELECT name, hero_url FROM destinations WHERE slug = ?', [$p['destination_slug']]);
            $cover = $d['hero_url'] ?? null;
            // A post about a city links the city page, and the city page lists the post because of
            // that link (rmt_blog_posts_for_destination matches on /d/<slug>). Without it a post is
            // reachable only from the sitemap, which is a page nobody is sent to.
            if ($d && !str_contains($body, '/d/' . $p['destination_slug'])) {
                $body = rtrim($body) . '<p>More on <a href="' . e(url('d/' . $p['destination_slug'])) . '">' . e((string) $d['name'])
                      . '</a>: places, reviews and what travelers say went wrong.</p>';
            }
        }
        $created = (string) ($p['created_at'] ?? gmdate('Y-m-d\TH:i:s\Z'));
        $have = q_one('SELECT id, title, summary, body, cover_url, category FROM blog_posts WHERE slug = ?', [$slug]);
        if ($have
            && (string) $have['title'] === $title
            && (string) $have['summary'] === $summary
            && (string) $have['body'] === $body
            && (string) ($have['cover_url'] ?? '') === (string) ($cover ?? '')
            && (string) $have['category'] === $cat) {
            continue;
        }
        $now = gmdate('Y-m-d\TH:i:s\Z');
        if ($have) {
            db()->prepare('UPDATE blog_posts SET user_id=?, title=?, summary=?, body=?, cover_url=?, category=?, status=?, updated_at=? WHERE id=?')
               ->execute([$uid, $title, $summary, $body, $cover, $cat, 'published', $now, (int) $have['id']]);
        } else {
            db()->prepare('INSERT INTO blog_posts (user_id, slug, title, summary, body, cover_url, category, status, created_at, updated_at)
                           VALUES (?,?,?,?,?,?,?,?,?,?)')
               ->execute([$uid, $slug, $title, $summary, $body, $cover, $cat, 'published', $created, $now]);
        }
        /* Tell the search engines this page exists.
           Member written pages have announced themselves since the queue was built: a guide, a
           trip, a review, a post and a community all call rmt_seo_announce as they publish.
           Editorial never did, because it is written by a deploy script rather than by a request,
           so every editorial page ever published reached IndexNow only if a crawler happened to
           re-read the sitemap. Announced here rather than for the whole file, because the upsert
           above has already skipped everything unchanged: only a genuinely new or edited page
           gets announced, which is the only thing IndexNow is for. */
        if (function_exists('rmt_seo_announce')) rmt_seo_announce('/blog/' . $slug);
        $written++;
    }
    return $written;
}

/**
 * Published blog posts that actually mention this destination. Used on /d/{slug} so the
 * 2026 tax/ticket notes get internal links from the city pages they describe, not a
 * hand-maintained mapping that can drift.
 */
function rmt_blog_posts_for_destination(string $slug): array {
    $slug = trim($slug);
    if ($slug === '' || !preg_match('/^[a-z0-9\-]+$/', $slug)) return [];
    return q_all(
        "SELECT slug, title, summary FROM blog_posts
         WHERE status = 'published' AND body LIKE ?
         ORDER BY id DESC LIMIT 6",
        ['%/d/'.$slug.'%']
    );
}

/**
 * What kind of 2026 note a post is, read from its slug: 'tax', 'ticket' or '' for anything else.
 * The slug is written by the publisher from the subject, so it is the one field that says this
 * without a column. Tax is tested first: "santorini-cruise-fee" is a charge, not a ticket.
 */
function rmt_blog_topic(string $slug): string {
    if (preg_match('/(^|-)(tax|taxes|levy|fee|fees|ortstaxe|iva|gst)(-|$)/', $slug)) return 'tax';
    if (preg_match('/(^|-)(ticket|tickets|pass|price|prices)(-|$)/', $slug)) return 'ticket';
    return '';
}

/**
 * Other notes of the same kind, for the foot of a post: tax notes under a tax note, ticket prices
 * under a ticket price. A reader comparing the Berlin city tax is usually comparing cities.
 *
 * Measured 2026-11-10: 45 of 108 articles had two or fewer pages linking to them, because the only
 * links were the paginated blog index and the post's own city. The choice is the post's neighbours
 * in publishing order within its kind, wrapping round, so every post of a kind is linked from the
 * same number of others and no handful of favourites collects them all. It is stable between
 * requests (no randomness, so the cached page and the built page agree) and it never repeats a
 * post already listed in the city block above it.
 *
 * @param list<string> $skip slugs already linked on the page
 * @return list<array{slug:string,title:string}>
 */
function rmt_blog_related(string $slug, array $skip = [], int $n = 4): array {
    $topic = rmt_blog_topic($slug);
    if ($topic === '' || $n < 1) return [];
    $ring = [];
    foreach (q_all("SELECT slug, title FROM blog_posts WHERE status = 'published' ORDER BY id") as $r) {
        if (rmt_blog_topic((string) $r['slug']) === $topic) $ring[] = ['slug' => (string) $r['slug'], 'title' => (string) $r['title']];
    }
    $at = array_search($slug, array_column($ring, 'slug'), true);
    $count = count($ring);
    if ($at === false || $count < 2) return [];
    $skip = array_flip($skip);
    $out = [];
    // Nearest first, alternating after and before: +1, -1, +2, -2 ...
    for ($step = 1; $step < $count && count($out) < $n; $step++) {
        foreach ([$step, -$step] as $off) {
            $cand = $ring[(($at + $off) % $count + $count) % $count];
            if ($cand['slug'] === $slug || isset($skip[$cand['slug']]) || isset($out[$cand['slug']])) continue;
            $out[$cand['slug']] = $cand;
            if (count($out) >= $n) break;
        }
    }
    return array_values($out);
}
