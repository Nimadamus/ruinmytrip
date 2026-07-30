<?php
declare(strict_types=1);
/**
 * One-time backfill: extract #hashtags from existing trips/reviews/guides/blog posts into the
 * tags/taggings tables (migration 020). Safe to re-run — rmt_sync_tags() replaces each item's
 * taggings wholesale.
 *
 * Editorial-account content is EXCLUDED everywhere: its bodies are trusted raw HTML, and hex
 * colors ("#fff") or CSS in that markup would be extracted as garbage tags. The live write paths
 * never sync editorial content either (publish_editorial.php doesn't call rmt_sync_tags), so
 * excluding it here keeps backfill and live behavior identical.
 *
 * Usage: php scripts/backfill_tags.php   (DATABASE_URL decides which DB, same as everything else)
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$notEditorial = "user_id NOT IN (SELECT id FROM users WHERE role = " . db()->quote(RMT_EDITORIAL_ROLE) . ")";
$done = 0;
foreach (q_all("SELECT id, title, body FROM trips WHERE status <> 'removed' AND $notEditorial") as $t) {
    rmt_sync_tags('trip', (int)$t['id'], $t['title'], $t['body']); $done++;
}
foreach (q_all("SELECT id, title, body, what_great, what_ruined FROM reviews WHERE status <> 'removed' AND $notEditorial") as $r) {
    rmt_sync_tags('review', (int)$r['id'], $r['title'], $r['body'], $r['what_great'], $r['what_ruined']); $done++;
}
foreach (q_all("SELECT id, title, summary, body FROM guides WHERE status <> 'removed' AND $notEditorial") as $g) {
    rmt_sync_tags('guide', (int)$g['id'], $g['title'], $g['summary'], $g['body']); $done++;
}
foreach (q_all("SELECT id, title, summary, body FROM blog_posts WHERE status <> 'removed' AND $notEditorial") as $p) {
    rmt_sync_tags('blog_post', (int)$p['id'], $p['title'], $p['summary'], $p['body']); $done++;
}

$tags = (int) q_one('SELECT COUNT(*) n FROM tags')['n'];
$taggings = (int) q_one('SELECT COUNT(*) n FROM taggings')['n'];
echo "Scanned $done items -> $tags tags, $taggings taggings\n";
