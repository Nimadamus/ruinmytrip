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
