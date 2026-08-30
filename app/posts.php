<?php
/**
 * Posts: saying something without having to write an article about it.
 *
 * The site could publish a trip story, a review of one named place, or a guide. Every one of those
 * assumes the trip is over and you have something finished to say. None of them holds "is Lisbon
 * unbearable in August", "just landed, this hostel lied about the shower", or "anybody in Oaxaca
 * next week". That is most of what travelers actually say to each other, and there was nowhere to
 * put it, so nobody said anything and the site read like a directory with a comment box.
 *
 * A post is a body of text, optionally about a city, optionally inside a community. Comments,
 * likes, saves, reports, @mentions and moderation all come from the machinery that already exists
 * for every other content type: 'post' is added to the interaction allow-list and the rest works.
 *
 * The one thing kept deliberately narrow: posting into a community requires being in it. An open
 * door is not the same as an open microphone.
 */
declare(strict_types=1);

const RMT_POST_MIN = 8;
const RMT_POST_MAX = 4000;

/** Long enough that a search engine gets a real answer rather than a shrug. See rmt_indexable(). */
const RMT_POST_INDEX_MIN = 220;

/**
 * @return array{ok:bool, errors:string[], data:array<string,mixed>}
 */
function rmt_post_validate(array $in, ?array $user): array {
    $errors = [];
    $body = trim((string) ($in['body'] ?? ''));
    if (mb_strlen($body) < RMT_POST_MIN) {
        $errors[] = 'Say a little more than that.';
    } elseif (mb_strlen($body) > RMT_POST_MAX) {
        $errors[] = 'That is too long for a post (' . RMT_POST_MAX . ' characters max). A trip story or guide fits better.';
    }

    $destId = (int) ($in['destination_id'] ?? 0);
    if ($destId > 0 && !q_one('SELECT id FROM destinations WHERE id=?', [$destId])) {
        $errors[] = 'That destination does not exist.';
        $destId = 0;
    }

    $colId = (int) ($in['collection_id'] ?? 0);
    if ($colId > 0) {
        $c = q_one("SELECT * FROM collections WHERE id=? AND status='published'", [$colId]);
        if (!$c) {
            $errors[] = 'That community does not exist.';
            $colId = 0;
        } elseif (!$user || (rmt_community_role($colId, (int) $user['id']) === null)) {
            // Reading a community is open. Talking in one is for the people who joined it.
            $errors[] = 'Join the community before posting in it.';
            $colId = 0;
        }
    }

    return ['ok' => $errors === [], 'errors' => $errors, 'data' => [
        'body' => $body, 'destination_id' => $destId ?: null, 'collection_id' => $colId ?: null,
    ]];
}

function rmt_post_create(int $userId, array $data): int {
    $now = date('Y-m-d H:i:s');
    q_run('INSERT INTO posts (user_id, destination_id, collection_id, body, status, created_at)
           VALUES (?,?,?,?,?,?)',
          [$userId, $data['destination_id'], $data['collection_id'], $data['body'], 'published', $now]);
    return (int) (q_one('SELECT id FROM posts WHERE user_id=? ORDER BY id DESC', [$userId])['id'] ?? 0);
}

/** Edit is body only. Moving a post between cities or communities after people have replied to it
 *  changes what they replied to, so it is not offered. */
function rmt_post_update(int $postId, string $body): void {
    q_run('UPDATE posts SET body=?, updated_at=? WHERE id=?', [$body, date('Y-m-d H:i:s'), $postId]);
}

/** Soft delete, the same as reviews, trips and comments: the thread keeps its shape. */
function rmt_post_delete(int $postId): void {
    q_run("UPDATE posts SET status='removed' WHERE id=?", [$postId]);
}

function rmt_post_get(int $postId): ?array {
    return q_one('SELECT p.*, d.slug dest_slug, d.name dest_name, c.slug community_slug, c.title community_title
                    FROM posts p
               LEFT JOIN destinations d ON d.id = p.destination_id
               LEFT JOIN collections c ON c.id = p.collection_id
                   WHERE p.id = ?', [$postId]);
}

/** The author, an admin or a mod. A community founder moderates their room through the community. */
function rmt_post_can_edit(?array $p, ?array $user): bool {
    if (!$p || !$user) return false;
    if ((int) $p['user_id'] === (int) $user['id']) return true;
    return in_array($user['role'] ?? '', ['admin', 'mod'], true);
}

/**
 * A founder can take a post out of their own community without being able to touch it anywhere
 * else. Moderating your room is not the same power as moderating the site.
 */
function rmt_post_can_remove(?array $p, ?array $user): bool {
    if (rmt_post_can_edit($p, $user)) return true;
    if (!$p || !$user || !$p['collection_id']) return false;
    return rmt_community_role((int) $p['collection_id'], (int) $user['id']) === 'owner';
}

/** A one-line name for a body of text: the first sentence, or the first few words. */
function rmt_post_title(array $p, int $max = 70): string {
    $body = trim(preg_replace('/\s+/', ' ', strip_tags((string) $p['body'])) ?? '');
    if ($body === '') return 'Post';
    $cut = preg_split('/(?<=[.!?])\s/', $body, 2)[0] ?? $body;
    if (mb_strlen($cut) > $max) $cut = mb_strimwidth($cut, 0, $max, '…');
    return $cut;
}

/** @return list<array<string,mixed>> */
function rmt_posts_recent(int $limit = 40, ?int $destId = null, ?int $collectionId = null): array {
    $where = ["p.status = 'published'", "u.status = 'active'"];
    $args = [];
    if ($destId !== null)       { $where[] = 'p.destination_id = ?'; $args[] = $destId; }
    if ($collectionId !== null) { $where[] = 'p.collection_id = ?';  $args[] = $collectionId; }
    $sql = implode(' AND ', $where);
    return q_all(
        "SELECT p.*, u.username, pr.avatar_url, pr.display_name,
                d.slug dest_slug, d.name dest_name,
                c.slug community_slug, c.title community_title,
                (SELECT COUNT(*) FROM comments cm
                  WHERE cm.target_type='post' AND cm.target_id=p.id AND cm.status='published') reply_count
           FROM posts p
           JOIN users u ON u.id = p.user_id
      LEFT JOIN profiles pr ON pr.user_id = p.user_id
      LEFT JOIN destinations d ON d.id = p.destination_id
      LEFT JOIN collections c ON c.id = p.collection_id
          WHERE $sql
       ORDER BY p.created_at DESC, p.id DESC
          LIMIT " . (int) $limit,
        $args
    );
}

/** Somebody's own posts, for their profile. */
function rmt_posts_by_user(int $userId, int $limit = 20): array {
    return q_all(
        "SELECT p.*, d.slug dest_slug, d.name dest_name, c.slug community_slug, c.title community_title,
                (SELECT COUNT(*) FROM comments cm
                  WHERE cm.target_type='post' AND cm.target_id=p.id AND cm.status='published') reply_count
           FROM posts p
      LEFT JOIN destinations d ON d.id = p.destination_id
      LEFT JOIN collections c ON c.id = p.collection_id
          WHERE p.user_id = ? AND p.status = 'published'
       ORDER BY p.created_at DESC, p.id DESC
          LIMIT " . (int) $limit, [$userId]);
}

function rmt_post_reply_count(int $postId): int {
    return (int) q_one("SELECT COUNT(*) n FROM comments
                         WHERE target_type='post' AND target_id=? AND status='published'", [$postId])['n'];
}

/** Posts whose thread already has substance, for the destination page's talk module. */
function rmt_posts_for_destination(int $destId, int $limit = 3): array {
    return rmt_posts_recent($limit, $destId);
}

/**
 * Attach the one image a post is allowed.
 *
 * Uploading is deliberately separate from creating: a post whose text was fine must not be lost
 * because the photo was a 30MB HEIC. The post is written first, the image is attached if it
 * works, and a failed upload is a message rather than a discarded post.
 *
 * @return array{ok:bool,error?:string}
 */
function rmt_post_attach_image(int $postId, array $file, int $ownerId): array {
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['ok' => true];
    if (!rmt_rate_ok('upload', (string) $ownerId, 40, 3600)) {
        return ['ok' => false, 'error' => 'Too many uploads. Try again later.'];
    }
    $res = rmt_upload_image($file, $ownerId);
    if (!$res['ok']) return ['ok' => false, 'error' => $res['error']];
    q_run('UPDATE posts SET image_url=?, image_key=?, image_w=?, image_h=? WHERE id=?',
          [$res['url'], $res['key'], $res['w'], $res['h'], $postId]);
    return ['ok' => true];
}

/** Take the image off a post and out of storage. Used when the post itself is being removed. */
function rmt_post_drop_image(array $p): void {
    if (empty($p['image_key'])) return;
    rmt_storage_delete((string) $p['image_key']);
    q_run('UPDATE posts SET image_url=NULL, image_key=NULL, image_w=NULL, image_h=NULL WHERE id=?', [(int) $p['id']]);
}
