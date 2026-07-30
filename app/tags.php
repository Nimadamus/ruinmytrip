<?php
declare(strict_types=1);

/**
 * Hashtags/topics.
 *
 * Tags are extracted from the text a traveler already writes (#solo, #budget-travel), never a
 * separate form field — the same content model as every social platform, and it means old posts
 * can be backfilled from their bodies alone (scripts/backfill_tags.php).
 *
 * One regex is shared by extraction and linkification so the set of tags stored for a post and
 * the set of tags highlighted in its rendered body can never disagree. The pattern requires at
 * least one letter (a bare "#039" or "#1" is an HTML entity or a ranking, not a topic) and
 * refuses a '&' or '/' immediately before the '#' so HTML entities in escaped text and URL
 * fragments ("...page#section") are never mistaken for tags.
 */
const RMT_TAG_RX = '/(?<![\w&\/])#(?=[a-z0-9_-]*[a-z])([a-z0-9][a-z0-9_-]{1,29})/i';
const RMT_TAGS_MAX_PER_ITEM = 15;

/** Lowercased, de-duplicated tag names found in the given texts, capped at RMT_TAGS_MAX_PER_ITEM. */
function rmt_extract_tags(?string ...$texts): array {
    $found = [];
    foreach ($texts as $t) {
        if ($t === null || $t === '') continue;
        if (preg_match_all(RMT_TAG_RX, $t, $m)) {
            foreach ($m[1] as $name) $found[strtolower($name)] = true;
        }
    }
    return array_slice(array_keys($found), 0, RMT_TAGS_MAX_PER_ITEM);
}

/**
 * Make the stored taggings for one item match its current text. Called on every create AND every
 * edit, so removing a hashtag from a post really removes it from the tag page. Draft reviews are
 * synced too — harmless, because every tag-page query joins on status='published'.
 */
function rmt_sync_tags(string $type, int $id, ?string ...$texts): void {
    $names = rmt_extract_tags(...$texts);
    db()->prepare('DELETE FROM taggings WHERE target_type=? AND target_id=?')->execute([$type, $id]);
    if (!$names) return;
    $now = date('Y-m-d H:i:s');
    foreach ($names as $name) {
        $tag = q_one('SELECT id FROM tags WHERE name=?', [$name]);
        $tagId = $tag ? (int)$tag['id'] : (int) q_run('INSERT INTO tags (name, created_at) VALUES (?,?)', [$name, $now]);
        // Re-entrant safe (backfill + a concurrent edit): the UNIQUE constraint is the guard.
        try {
            q_run('INSERT INTO taggings (tag_id, target_type, target_id, created_at) VALUES (?,?,?,?)',
                  [$tagId, $type, $id, $now]);
        } catch (PDOException $e) { /* duplicate tagging — already correct */ }
    }
}

/** Tag rows (id, name) attached to one item, for the chip row on show pages. */
function rmt_tags_for(string $type, int $id): array {
    return q_all('SELECT t.id, t.name FROM taggings tg JOIN tags t ON t.id=tg.tag_id
                  WHERE tg.target_type=? AND tg.target_id=? ORDER BY t.name', [$type, $id]);
}

/**
 * Wrap the #tags in an ALREADY-ESCAPED body in links. Input must have been through e() (and
 * optionally nl2br) first — this function inserts trusted <a> markup and must never receive raw
 * user HTML. Never used on editorial bodies (those are trusted raw HTML with real URLs/anchors).
 */
function rmt_linkify_tags(string $escapedHtml): string {
    return (string) preg_replace_callback(RMT_TAG_RX, function ($m) {
        $name = strtolower($m[1]);
        return '<a class="tag-link" href="' . e(url('tag/' . $name)) . '">#' . e($m[1]) . '</a>';
    }, $escapedHtml);
}

/**
 * Tags ordered by how much published content carries them. Counts join through to each content
 * type's status so a tag used only on drafts/removed posts shows the number a visitor will
 * actually find on its page — no phantom counts.
 */
function rmt_top_tags(int $limit = 14): array {
    $limit = max(1, min(100, $limit));
    return q_all("SELECT t.id, t.name, COUNT(*) n FROM (
                    SELECT tg.tag_id FROM taggings tg JOIN trips c ON c.id=tg.target_id AND c.status='published' WHERE tg.target_type='trip'
          UNION ALL SELECT tg.tag_id FROM taggings tg JOIN reviews c ON c.id=tg.target_id AND c.status='published' WHERE tg.target_type='review'
          UNION ALL SELECT tg.tag_id FROM taggings tg JOIN guides c ON c.id=tg.target_id AND c.status='published' WHERE tg.target_type='guide'
          UNION ALL SELECT tg.tag_id FROM taggings tg JOIN blog_posts c ON c.id=tg.target_id AND c.status='published' WHERE tg.target_type='blog_post'
                  ) x JOIN tags t ON t.id=x.tag_id
                  GROUP BY t.id, t.name ORDER BY n DESC, t.name LIMIT $limit");
}

/**
 * Published items carrying a tag, in the same card shape as rmt_activity_items() so tag pages
 * reuse the Discover/feed card markup.
 */
function rmt_tag_items(int $tagId, int $limitEach = 40): array {
    $in = '(SELECT target_id FROM taggings WHERE tag_id=? AND target_type=?)';

    $trips = q_all("SELECT t.*, d.name dest_name FROM trips t LEFT JOIN destinations d ON d.id=t.destination_id
                    WHERE t.status='published' AND t.id IN $in
                    ORDER BY t.created_at DESC, t.id DESC LIMIT $limitEach", [$tagId, 'trip']);
    foreach ($trips as &$row) {
        $row['kind'] = 'trip';
        $row['feed_url'] = url('trip/'.$row['id'].'/'.$row['slug']);
        $row['feed_excerpt'] = mb_strimwidth(strip_tags((string)$row['body']), 0, 180, '…');
    }
    unset($row);

    $reviews = q_all("SELECT r.*, d.name dest_name FROM reviews r LEFT JOIN destinations d ON d.id=r.destination_id
                      WHERE r.status='published' AND r.id IN $in
                      ORDER BY r.created_at DESC, r.id DESC LIMIT $limitEach", [$tagId, 'review']);
    foreach ($reviews as &$row) {
        $row['kind'] = 'review';
        $row['title'] = $row['title'] ?: $row['subject_name'];
        $row['feed_url'] = url(ltrim(rmt_review_path($row), '/'));
        $row['feed_excerpt'] = mb_strimwidth(strip_tags((string)$row['body']), 0, 180, '…');
    }
    unset($row);

    $guides = q_all("SELECT g.*, d.name dest_name FROM guides g LEFT JOIN destinations d ON d.id=g.destination_id
                     WHERE g.status='published' AND g.id IN $in
                     ORDER BY g.created_at DESC, g.id DESC LIMIT $limitEach", [$tagId, 'guide']);
    foreach ($guides as &$row) {
        $row['kind'] = 'guide';
        $row['feed_url'] = url('g/'.$row['slug']);
        $row['feed_excerpt'] = mb_strimwidth(strip_tags((string)$row['summary']), 0, 180, '…');
    }
    unset($row);

    $posts = q_all("SELECT * FROM blog_posts WHERE status='published' AND id IN $in
                    ORDER BY created_at DESC, id DESC LIMIT $limitEach", [$tagId, 'blog_post']);
    foreach ($posts as &$row) {
        $row['kind'] = 'blog_post';
        $row['dest_name'] = null;
        $row['feed_url'] = url('blog/'.$row['slug']);
        $row['feed_excerpt'] = mb_strimwidth(strip_tags((string)$row['summary']), 0, 180, '…');
    }
    unset($row);

    $items = array_merge($trips, $reviews, $guides, $posts);
    usort($items, fn($x, $y) => strcmp((string)$y['created_at'], (string)$x['created_at']));
    $items = array_slice($items, 0, $limitEach);
    authors_fill($items);
    return $items;
}
