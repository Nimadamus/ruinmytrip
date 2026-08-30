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

    /* A place is more specific than a city, so when both are given the place wins and the city is
       filled in from it: "about the Anne Frank House" is never also "about somewhere else". */
    $placeId = (int) ($in['place_id'] ?? 0);
    if ($placeId > 0) {
        $pl = q_one("SELECT id, destination_id FROM places WHERE id=? AND status='active'", [$placeId]);
        if (!$pl) {
            $errors[] = 'That place does not exist.';
            $placeId = 0;
        } elseif ($pl['destination_id']) {
            $destId = (int) $pl['destination_id'];
        }
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
        'place_id' => $placeId ?: null,
    ]];
}

function rmt_post_create(int $userId, array $data): int {
    $now = date('Y-m-d H:i:s');
    q_run('INSERT INTO posts (user_id, destination_id, collection_id, place_id, body, status, created_at)
           VALUES (?,?,?,?,?,?,?)',
          [$userId, $data['destination_id'], $data['collection_id'], $data['place_id'] ?? null,
           $data['body'], 'published', $now]);
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
    return q_one('SELECT p.*, d.slug dest_slug, d.name dest_name, c.slug community_slug, c.title community_title,
                         pl.slug place_slug, pl.name place_name
                    FROM posts p
               LEFT JOIN destinations d ON d.id = p.destination_id
               LEFT JOIN collections c ON c.id = p.collection_id
               LEFT JOIN places pl ON pl.id = p.place_id AND pl.status = "active"
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
function rmt_posts_recent(int $limit = 40, ?int $destId = null, ?int $collectionId = null, ?int $placeId = null): array {
    $where = ["p.status = 'published'", "u.status = 'active'"];
    $args = [];
    if ($destId !== null)       { $where[] = 'p.destination_id = ?'; $args[] = $destId; }
    if ($collectionId !== null) { $where[] = 'p.collection_id = ?';  $args[] = $collectionId; }
    if ($placeId !== null)      { $where[] = 'p.place_id = ?';       $args[] = $placeId; }
    $sql = implode(' AND ', $where);
    $rows = q_all(
        "SELECT p.*, u.username, pr.avatar_url, pr.display_name,
                d.slug dest_slug, d.name dest_name,
                c.slug community_slug, c.title community_title,
                pl.slug place_slug, pl.name place_name,
                (SELECT COUNT(*) FROM comments cm
                  WHERE cm.target_type='post' AND cm.target_id=p.id AND cm.status='published') reply_count
           FROM posts p
           JOIN users u ON u.id = p.user_id
      LEFT JOIN profiles pr ON pr.user_id = p.user_id
      LEFT JOIN destinations d ON d.id = p.destination_id
      LEFT JOIN collections c ON c.id = p.collection_id
      LEFT JOIN places pl ON pl.id = p.place_id AND pl.status = 'active'
          WHERE $sql
       ORDER BY p.created_at DESC, p.id DESC
          LIMIT " . (int) $limit,
        $args
    );
    return rmt_posts_attach_originals($rows);
}

/** Somebody's own posts, for their profile. */
function rmt_posts_by_user(int $userId, int $limit = 20): array {
    return rmt_posts_attach_originals(q_all(
        "SELECT p.*, d.slug dest_slug, d.name dest_name, c.slug community_slug, c.title community_title,
                (SELECT COUNT(*) FROM comments cm
                  WHERE cm.target_type='post' AND cm.target_id=p.id AND cm.status='published') reply_count
           FROM posts p
      LEFT JOIN destinations d ON d.id = p.destination_id
      LEFT JOIN collections c ON c.id = p.collection_id
          WHERE p.user_id = ? AND p.status = 'published'
       ORDER BY p.created_at DESC, p.id DESC
          LIMIT " . (int) $limit, [$userId]));
}

function rmt_post_reply_count(int $postId): int {
    return (int) q_one("SELECT COUNT(*) n FROM comments
                         WHERE target_type='post' AND target_id=? AND status='published'", [$postId])['n'];
}

/** Posts whose thread already has substance, for the destination page's talk module. */
function rmt_posts_for_destination(int $destId, int $limit = 3): array {
    return rmt_posts_recent($limit, $destId);
}

/** What travelers are asking about one specific place, for its own page. */
function rmt_posts_for_place(int $placeId, int $limit = 3): array {
    return rmt_posts_recent($limit, null, null, $placeId);
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

/* --------------------------------------------------------------------- reposting */

/**
 * Pass somebody else's post on to your own followers.
 *
 * A repost is a post with a pointer, so comments, likes, moderation and the feed need no special
 * case. An empty body is a plain repost; a body makes it a quote, which is the version that
 * actually adds something and the one worth encouraging.
 *
 * @return array{ok:bool,error?:string,id?:int}
 */
function rmt_post_repost(int $userId, int $originalId, string $body = ''): array {
    $orig = rmt_post_get($originalId);
    if (!$orig || $orig['status'] !== 'published') return ['ok' => false, 'error' => 'That post is gone.'];
    // Reposting a repost points at the thing itself. Chains of "X reposted Y reposting Z" are
    // noise, and the original author is who the credit belongs to.
    if (!empty($orig['repost_of'])) {
        $orig = rmt_post_get((int) $orig['repost_of']);
        if (!$orig || $orig['status'] !== 'published') return ['ok' => false, 'error' => 'That post is gone.'];
        $originalId = (int) $orig['id'];
    }
    if ((int) $orig['user_id'] === $userId) return ['ok' => false, 'error' => 'That is already yours.'];

    $body = trim($body);
    if ($body !== '' && mb_strlen($body) > RMT_POST_MAX) {
        return ['ok' => false, 'error' => 'That is too long to add to a repost.'];
    }
    // Twice is an accident, not an opinion, unless there is something new to say with it.
    if ($body === '' && q_one("SELECT 1 x FROM posts WHERE user_id=? AND repost_of=? AND body='' AND status='published'",
                              [$userId, $originalId])) {
        return ['ok' => false, 'error' => 'You already reposted that.'];
    }

    $now = date('Y-m-d H:i:s');
    q_run('INSERT INTO posts (user_id, destination_id, collection_id, body, status, created_at, repost_of)
           VALUES (?,?,?,?,?,?,?)',
          [$userId, $orig['destination_id'], null, $body, 'published', $now, $originalId]);
    $id = (int) (q_one('SELECT id FROM posts WHERE user_id=? ORDER BY id DESC', [$userId])['id'] ?? 0);
    return ['ok' => true, 'id' => $id];
}

function rmt_post_repost_count(int $postId): int {
    return (int) q_one("SELECT COUNT(*) n FROM posts WHERE repost_of=? AND status='published'", [$postId])['n'];
}

/**
 * Fill in what each repost is passing on, in one query rather than one per row.
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>> each repost row gains `original`
 */
function rmt_posts_attach_originals(array $rows): array {
    $ids = array_values(array_unique(array_filter(array_map(
        static fn(array $r): int => (int) ($r['repost_of'] ?? 0), $rows))));
    if (!$ids) return $rows;
    $in = implode(',', array_fill(0, count($ids), '?'));
    $found = [];
    foreach (q_all("SELECT p.*, u.username, pr.avatar_url, d.slug dest_slug, d.name dest_name
                      FROM posts p JOIN users u ON u.id = p.user_id
                 LEFT JOIN profiles pr ON pr.user_id = p.user_id
                 LEFT JOIN destinations d ON d.id = p.destination_id
                     WHERE p.id IN ($in)", $ids) as $o) {
        $found[(int) $o['id']] = $o;
    }
    foreach ($rows as $i => $r) {
        $oid = (int) ($r['repost_of'] ?? 0);
        // A repost whose original was removed keeps its own words and loses the quote block: the
        // alternative is a card that says somebody passed on nothing.
        $rows[$i]['original'] = $oid && isset($found[$oid]) && $found[$oid]['status'] === 'published'
            ? $found[$oid] : null;
    }
    return $rows;
}

/**
 * Other talk worth reading after this one: same city first, then anything sharing a hashtag.
 *
 * A post that ends in nothing sends the reader back to where they came from, which for most of
 * them is a search result. Two or three real neighbours is the difference between a page and a
 * visit.
 *
 * @return list<array<string,mixed>>
 */
function rmt_posts_related(array $p, int $limit = 4): array {
    $id = (int) $p['id'];
    $out = [];
    $seen = [$id => true];

    $take = static function (array $rows) use (&$out, &$seen, $limit): void {
        foreach ($rows as $r) {
            if (count($out) >= $limit) return;
            if (isset($seen[(int) $r['id']])) continue;
            $seen[(int) $r['id']] = true;
            $out[] = $r;
        }
    };

    if (!empty($p['destination_id'])) {
        $take(q_all("SELECT p.id, p.body, p.created_at, u.username
                       FROM posts p JOIN users u ON u.id = p.user_id
                      WHERE p.status='published' AND u.status='active' AND p.repost_of IS NULL
                        AND p.destination_id = ? AND p.id <> ?
                   ORDER BY p.created_at DESC LIMIT " . (int) $limit, [(int) $p['destination_id'], $id]));
    }
    if (count($out) < $limit) {
        $tagIds = array_column(rmt_tags_for('post', $id), 'id');
        if ($tagIds) {
            $in = implode(',', array_fill(0, count($tagIds), '?'));
            $take(q_all("SELECT DISTINCT p.id, p.body, p.created_at, u.username
                           FROM posts p
                           JOIN taggings tg ON tg.target_type='post' AND tg.target_id = p.id
                           JOIN users u ON u.id = p.user_id
                          WHERE p.status='published' AND u.status='active' AND p.repost_of IS NULL
                            AND tg.tag_id IN ($in) AND p.id <> ?
                       ORDER BY p.created_at DESC LIMIT " . (int) $limit,
                       array_merge($tagIds, [$id])));
        }
    }
    return $out;
}

/**
 * The talk worth reading first: what other people actually engaged with, this week.
 *
 * Chronological is honest and, past a certain volume, useless: the newest thing is not the best
 * thing, and a stream sorted by clock rewards whoever posts most often. The score is deliberately
 * simple and countable -- a reply is worth more than a like because writing one costs more, and a
 * repost is worth most because somebody put their own name on it.
 *
 * Restricted to a window so the same three posts do not sit at the top forever. Outside the window
 * the answer is the chronological list, which is what /talk shows by default.
 *
 * @return list<array<string,mixed>>
 */
function rmt_posts_top(int $limit = 40, ?int $destId = null, ?int $collectionId = null, int $days = 7): array {
    $where = ["p.status = 'published'", "u.status = 'active'", 'p.created_at >= ?'];
    $args = [date('Y-m-d H:i:s', time() - $days * 86400)];
    if ($destId !== null)       { $where[] = 'p.destination_id = ?'; $args[] = $destId; }
    if ($collectionId !== null) { $where[] = 'p.collection_id = ?';  $args[] = $collectionId; }
    $sql = implode(' AND ', $where);

    $rows = q_all(
        "SELECT p.*, u.username, pr.avatar_url, pr.display_name,
                d.slug dest_slug, d.name dest_name,
                c.slug community_slug, c.title community_title,
                (SELECT COUNT(*) FROM comments cm
                  WHERE cm.target_type='post' AND cm.target_id=p.id AND cm.status='published') reply_count,
                (SELECT COUNT(*) FROM likes lk WHERE lk.target_type='post' AND lk.target_id=p.id) like_count,
                (SELECT COUNT(*) FROM posts rp WHERE rp.repost_of=p.id AND rp.status='published') repost_count
           FROM posts p
           JOIN users u ON u.id = p.user_id
      LEFT JOIN profiles pr ON pr.user_id = p.user_id
      LEFT JOIN destinations d ON d.id = p.destination_id
      LEFT JOIN collections c ON c.id = p.collection_id
          WHERE $sql
       ORDER BY (
                (SELECT COUNT(*) FROM comments cm
                  WHERE cm.target_type='post' AND cm.target_id=p.id AND cm.status='published') * 3
              + (SELECT COUNT(*) FROM likes lk WHERE lk.target_type='post' AND lk.target_id=p.id)
              + (SELECT COUNT(*) FROM posts rp WHERE rp.repost_of=p.id AND rp.status='published') * 5
                ) DESC, p.created_at DESC, p.id DESC
          LIMIT " . (int) $limit,
        $args
    );
    return rmt_posts_attach_originals($rows);
}

/**
 * Structured data for one post.
 *
 * Two shapes, because the same table holds two different things. A post ending in a question mark
 * with answers under it is a Q&A page, which is the shape Google shows question results from and
 * exactly what the place pages now collect. Everything else is a forum posting. Describing either
 * as an Article would be wrong and would compete in a category it cannot win.
 *
 * An unanswered question stays a DiscussionForumPosting: QAPage with an empty answer list is a
 * claim that there is an answer here, and there is not.
 *
 * @param array $p        the post, with author filled in
 * @param array $comments published replies, oldest first
 */
function rmt_post_jsonld(array $p, array $comments, int $likeCount = 0): array {
    $url = url('post/' . (int) $p['id']);
    $author = static fn(string $username): array =>
        ['@type' => 'Person', 'name' => '@' . $username, 'url' => url('u/' . $username)];
    $body = (string) $p['body'];
    $isQuestion = str_ends_with(rtrim($body), '?');

    if ($isQuestion && $comments) {
        $answers = array_map(static fn(array $c): array => [
            '@type' => 'Answer',
            'text' => (string) $c['body'],
            'dateCreated' => $c['created_at'],
            'url' => $url . '#comment-' . (int) $c['id'],
            'author' => $author((string) $c['username']),
        ], $comments);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'QAPage',
            'mainEntity' => [
                '@type' => 'Question',
                'name' => rmt_post_title($p),
                'text' => $body,
                'answerCount' => count($answers),
                'upvoteCount' => $likeCount,
                'dateCreated' => $p['created_at'],
                'author' => $author((string) $p['author']['username']),
                'url' => $url,
                // The oldest answer is not "accepted" -- nobody accepted it -- so every answer is
                // suggested. Marking one accepted with no signal behind it is a fabricated fact.
                'suggestedAnswer' => $answers,
            ],
        ];
    }

    return [
        '@context' => 'https://schema.org',
        '@type' => 'DiscussionForumPosting',
        'headline' => rmt_post_title($p),
        'text' => $body,
        'url' => $url,
        'datePublished' => $p['created_at'],
        'dateModified' => $p['updated_at'] ?: $p['created_at'],
        'author' => $author((string) $p['author']['username']),
        'image' => !empty($p['image_url']) ? abs_url((string) $p['image_url']) : null,
        'interactionStatistic' => [
            ['@type' => 'InteractionCounter',
             'interactionType' => 'https://schema.org/CommentAction',
             'userInteractionCount' => count($comments)],
            ['@type' => 'InteractionCounter',
             'interactionType' => 'https://schema.org/LikeAction',
             'userInteractionCount' => $likeCount],
        ],
        'comment' => array_map(static fn(array $c): array => [
            '@type' => 'Comment',
            'text' => (string) $c['body'],
            'datePublished' => $c['created_at'],
            'author' => $author((string) $c['username']),
        ], $comments),
    ];
}
