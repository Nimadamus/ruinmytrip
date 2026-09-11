<?php
declare(strict_types=1);

/**
 * Photos, as things with a page of their own.
 *
 * Until now a photo was a column on something else: a row in `trip_photos`, a row in
 * `review_photos`, or `posts.image_url`. They were shown in grids that linked to the parent, so a
 * photograph could be seen and never opened, never captioned in place, never sent to anybody, and
 * never carried its own preview into a chat window. On a travel network that is the wrong way
 * round: the photograph is the thing people come for.
 *
 * This file is the one place that answers four questions about any photo, whatever table it lives
 * in: where is it, who took it, who may see it, and what is next to it.
 *
 * PRIVACY. A photo inherits the visibility of the thing it belongs to, always. A trip photo on a
 * "followers" trip is visible to followers; on a private trip it is visible to its owner alone. A
 * review or post photo is public when its parent is published, because a review is a public act.
 * rmt_destination_photos() used to ignore this entirely, so a private trip's photographs appeared
 * on the city's public photo wall: the clause is in one place now, and every reader of these rows
 * goes through it.
 */

/** The photo kinds that have a page. Keyed by kind; each names its table and its parent. */
const RMT_PHOTO_KINDS = ['trip', 'review', 'post'];

/**
 * One photo, with everything a page needs to draw it.
 *
 * @return array{kind:string,id:int,url:string,caption:string,created_at:string,user_id:int,
 *               parent_id:int,parent_kind:string,parent_title:string,parent_url:string,
 *               dest_id:int,dest_name:string,dest_slug:string,visibility:string,
 *               width:int,height:int,storage_key:string}|null
 */
function rmt_photo_get(string $kind, int $id): ?array {
    if ($id < 1 || !in_array($kind, RMT_PHOTO_KINDS, true)) return null;

    if ($kind === 'trip') {
        $r = q_one("SELECT tp.id, tp.url, tp.caption, tp.created_at, tp.width, tp.height, tp.storage_key,
                           t.id parent_id, t.slug parent_slug, t.title parent_title, t.user_id,
                           COALESCE(t.visibility,'public') visibility, t.status parent_status,
                           d.id dest_id, d.name dest_name, d.slug dest_slug
                      FROM trip_photos tp
                      JOIN trips t ON t.id = tp.trip_id
                 LEFT JOIN destinations d ON d.id = t.destination_id
                     WHERE tp.id = ?", [$id]);
        if (!$r || $r['parent_status'] !== 'published') return null;
        $r['parent_kind'] = 'trip';
        $r['parent_url'] = url('trip/' . (int) $r['parent_id'] . '/' . (string) $r['parent_slug']);
    } elseif ($kind === 'review') {
        $r = q_one("SELECT rp.id, rp.url, rp.caption, rp.created_at, rp.width, rp.height, rp.storage_key,
                           r.id parent_id, r.slug parent_slug, r.title parent_title, r.subject_name,
                           r.user_id, 'public' visibility, r.status parent_status,
                           d.id dest_id, d.name dest_name, d.slug dest_slug
                      FROM review_photos rp
                      JOIN reviews r ON r.id = rp.review_id
                 LEFT JOIN destinations d ON d.id = r.destination_id
                     WHERE rp.id = ?", [$id]);
        if (!$r || $r['parent_status'] !== 'published') return null;
        $r['parent_kind'] = 'review';
        $r['parent_title'] = (string) ($r['parent_title'] ?: $r['subject_name']);
        $r['parent_url'] = url(ltrim(rmt_review_path($r), '/'));
    } else {
        $r = q_one("SELECT p.id, p.image_url url, p.body caption, p.created_at, p.user_id,
                           p.id parent_id, p.body parent_title, 'public' visibility, p.status parent_status,
                           d.id dest_id, d.name dest_name, d.slug dest_slug
                      FROM posts p
                 LEFT JOIN destinations d ON d.id = p.destination_id
                     WHERE p.id = ? AND p.image_url IS NOT NULL AND p.image_url <> ''", [$id]);
        if (!$r || $r['parent_status'] !== 'published') return null;
        $r['parent_kind'] = 'post';
        $r['parent_title'] = rmt_photo_trim((string) $r['parent_title'], 70);
        $r['parent_url'] = url('post/' . (int) $r['parent_id']);
        $r['width'] = $r['height'] = 0;
        $r['storage_key'] = '';
    }

    $r['kind'] = $kind;
    $r['id'] = (int) $r['id'];
    $r['user_id'] = (int) $r['user_id'];
    $r['parent_id'] = (int) $r['parent_id'];
    $r['dest_id'] = (int) ($r['dest_id'] ?? 0);
    $r['caption'] = trim((string) ($r['caption'] ?? ''));
    $r['author'] = author($r['user_id']);
    return $r;
}

/** Who may see this photo. A photo is exactly as private as the thing it belongs to. */
function rmt_photo_visible_to(array $photo, ?array $viewer): bool {
    $vis = (string) ($photo['visibility'] ?? 'public');
    if ($vis === 'public') return true;
    if (!$viewer) return false;
    $uid = (int) $viewer['id'];
    if ($uid === (int) $photo['user_id']) return true;
    if (in_array($viewer['role'] ?? '', ['admin', 'mod'], true)) return true;
    if ($vis === 'followers') {
        return (bool) q_one('SELECT 1 FROM follows WHERE followee_id = ? AND follower_id = ?',
                            [(int) $photo['user_id'], $uid]);
    }
    return false;
}

/** The path of a photo's own page. */
function rmt_photo_path(string $kind, int $id): string {
    return '/photo/' . $kind . '/' . $id;
}

/**
 * The photos either side of this one, inside the same parent, so a page can be walked like an
 * album rather than dead-ending. Returns ids, or null at either end.
 *
 * @return array{prev:?int,next:?int,index:int,total:int}
 */
function rmt_photo_siblings(array $photo): array {
    $out = ['prev' => null, 'next' => null, 'index' => 1, 'total' => 1];
    if ($photo['kind'] === 'trip') {
        $rows = q_all('SELECT id FROM trip_photos WHERE trip_id = ? ORDER BY sort, id',
                      [(int) $photo['parent_id']]);
    } elseif ($photo['kind'] === 'review') {
        $rows = q_all('SELECT id FROM review_photos WHERE review_id = ? ORDER BY sort, id',
                      [(int) $photo['parent_id']]);
    } else {
        return $out;
    }
    $ids = array_map(static fn(array $r) => (int) $r['id'], $rows);
    $at = array_search((int) $photo['id'], $ids, true);
    if ($at === false) return $out;
    $out['total'] = count($ids);
    $out['index'] = $at + 1;
    if ($at > 0) $out['prev'] = $ids[$at - 1];
    if ($at < count($ids) - 1) $out['next'] = $ids[$at + 1];
    return $out;
}

/**
 * Every photo taken in one city that the viewer is allowed to see, newest first.
 *
 * Replaces the version in editorial.php, which selected trip photos with no visibility clause at
 * all: a trip somebody had marked "only you" had its photographs on the city's public photo wall.
 *
 * @return list<array<string,mixed>>
 */
function rmt_city_photos(int $destId, ?array $viewer, int $limit = 120): array {
    [$visSql, $visArgs] = rmt_plan_visibility_sql('t', $viewer);
    $trip = q_all("SELECT tp.id, tp.url, tp.caption, tp.created_at, tp.width, tp.height,
                          t.user_id, 'trip' kind, t.id parent_id, t.title parent_title
                     FROM trip_photos tp
                     JOIN trips t ON t.id = tp.trip_id
                    WHERE t.destination_id = ? AND t.status = 'published' AND $visSql
                 ORDER BY tp.created_at DESC, tp.id DESC LIMIT 200",
                  array_merge([$destId], $visArgs));
    $review = q_all("SELECT rp.id, rp.url, rp.caption, rp.created_at, rp.width, rp.height,
                            r.user_id, 'review' kind, r.id parent_id,
                            COALESCE(NULLIF(r.title,''), r.subject_name) parent_title
                       FROM review_photos rp
                       JOIN reviews r ON r.id = rp.review_id
                      WHERE r.destination_id = ? AND r.status = 'published'
                   ORDER BY rp.created_at DESC, rp.id DESC LIMIT 200", [$destId]);
    $post = q_all("SELECT p.id, p.image_url url, p.body caption, p.created_at,
                          0 width, 0 height, p.user_id, 'post' kind, p.id parent_id, p.body parent_title
                     FROM posts p
                    WHERE p.destination_id = ? AND p.status = 'published'
                      AND p.image_url IS NOT NULL AND p.image_url <> ''
                 ORDER BY p.created_at DESC, p.id DESC LIMIT 100", [$destId]);

    $all = array_merge($trip, $review, $post);
    usort($all, static fn(array $x, array $y) => strcmp((string) $y['created_at'], (string) $x['created_at']));
    $all = array_slice($all, 0, max(1, $limit));
    foreach ($all as $i => $r) {
        $all[$i]['href'] = url(ltrim(rmt_photo_path((string) $r['kind'], (int) $r['id']), '/'));
        $all[$i]['caption'] = rmt_photo_trim((string) ($r['caption'] ?? ''), 120);
    }
    authors_fill($all);
    return $all;
}

/**
 * Every photo one member has taken that the viewer may see, newest first, for the profile wall.
 *
 * @return list<array<string,mixed>>
 */
function rmt_member_photos(int $uid, ?array $viewer, int $limit = 18): array {
    [$visSql, $visArgs] = rmt_plan_visibility_sql('t', $viewer);
    $trip = q_all("SELECT tp.id, tp.url, tp.caption, tp.created_at, 'trip' kind, t.title parent_title
                     FROM trip_photos tp JOIN trips t ON t.id = tp.trip_id
                    WHERE t.user_id = ? AND t.status = 'published' AND $visSql
                 ORDER BY tp.id DESC LIMIT 60", array_merge([$uid], $visArgs));
    $review = q_all("SELECT rp.id, rp.url, rp.caption, rp.created_at, 'review' kind,
                            COALESCE(NULLIF(r.title,''), r.subject_name) parent_title
                       FROM review_photos rp JOIN reviews r ON r.id = rp.review_id
                      WHERE r.user_id = ? AND r.status = 'published'
                   ORDER BY rp.id DESC LIMIT 60", [$uid]);
    $post = q_all("SELECT p.id, p.image_url url, p.body caption, p.created_at, 'post' kind, p.body parent_title
                     FROM posts p
                    WHERE p.user_id = ? AND p.status = 'published'
                      AND p.image_url IS NOT NULL AND p.image_url <> ''
                 ORDER BY p.id DESC LIMIT 60", [$uid]);

    $all = array_merge($trip, $review, $post);
    usort($all, static fn(array $x, array $y) => strcmp((string) $y['created_at'], (string) $x['created_at']));
    $all = array_slice($all, 0, max(1, $limit));
    foreach ($all as $i => $r) {
        $all[$i]['href'] = url(ltrim(rmt_photo_path((string) $r['kind'], (int) $r['id']), '/'));
        $all[$i]['caption'] = rmt_photo_trim((string) ($r['caption'] ?? ''), 120);
    }
    return $all;
}

/** A caption, shortened without cutting a word in half. */
function rmt_photo_trim(string $s, int $max): string {
    $s = trim(preg_replace('/\s+/', ' ', strip_tags($s)) ?? '');
    if ($s === '' || mb_strlen($s) <= $max) return $s;
    $cut = mb_substr($s, 0, $max);
    $sp = mb_strrpos($cut, ' ');
    return rtrim($sp !== false && $sp > $max * 0.5 ? mb_substr($cut, 0, $sp) : $cut, " ,;:") . '...';
}
