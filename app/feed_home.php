<?php
/**
 * The rails beside the feed.
 *
 * A feed on its own is a river: you read it and you leave. What keeps somebody in a social product
 * is the column next to it, because that is where the next action lives, and every item in it here
 * is a person or a date rather than an article. Four things, in the order they matter to a member
 * who just arrived:
 *
 *   1. who is in the same city on the same days as them, which is the whole promise of the site
 *   2. their own trips, so the next one is one click from every page they land on
 *   3. people worth following, because a feed with nobody in it never fills
 *   4. meetups they could actually walk into
 *
 * Every query is bounded and every one of them is skipped when its rail would be empty, so a new
 * member is never shown four headings over four blank spaces.
 */
declare(strict_types=1);

/**
 * @return array{matches:list<array<string,mixed>>,trips:list<array<string,mixed>>,
 *               suggested:list<array<string,mixed>>,meetups:list<array<string,mixed>>,
 *               match_count:int}
 */
function rmt_feed_rails(int $uid): array {
    if ($uid < 1) {
        return ['matches' => [], 'trips' => [], 'suggested' => [], 'meetups' => [], 'match_count' => 0];
    }

    /* Overlaps, nearest first. rmt_trip_matches() already applies visibility and blocks, so this
       only has to cut it down: three is a rail, ten is a second feed. */
    $matches = array_slice(rmt_trip_matches($uid, 12), 0, 3);

    /* Their own upcoming trips. A member with dates posted is the member the whole product works
       for, and a member without any is the one to ask. */
    $trips = q_all(
        "SELECT t.id, t.title, t.slug, t.date_from, t.date_to, t.visibility,
                d.name dest_name, d.slug dest_slug
           FROM trips t
      LEFT JOIN destinations d ON d.id = t.destination_id
          WHERE t.user_id = ? AND t.status = 'published'
            AND t.date_to IS NOT NULL AND t.date_to >= ?
       ORDER BY t.date_from LIMIT 3",
        [$uid, date('Y-m-d')]
    );

    $suggested = array_slice(rmt_follow_suggestions($uid, 6), 0, 4);

    /* Meetups in the cities this member is going to or has saved, and if there are none, the next
       public ones anywhere. A meetup two months away in a city they will never see is still a
       better answer than an empty box, as long as the heading does not claim it is theirs. */
    $meetups = q_all(
        "SELECT m.id, m.title, m.date_start, d.name dest_name
           FROM meetups m
      LEFT JOIN destinations d ON d.id = m.destination_id
          WHERE m.status = 'published' AND m.date_start >= ?
            AND (m.destination_id IN (SELECT destination_id FROM trips
                                       WHERE user_id = ? AND status = 'published'
                                         AND destination_id IS NOT NULL)
              OR m.destination_id IN (SELECT target_id FROM saves
                                       WHERE user_id = ? AND target_type = 'destination'))
       ORDER BY m.date_start LIMIT 3",
        [date('Y-m-d H:i:s'), $uid, $uid]
    );
    if (!$meetups) {
        $meetups = q_all(
            "SELECT m.id, m.title, m.date_start, d.name dest_name
               FROM meetups m
          LEFT JOIN destinations d ON d.id = m.destination_id
              WHERE m.status = 'published' AND m.date_start >= ?
           ORDER BY m.date_start LIMIT 2",
            [date('Y-m-d H:i:s')]
        );
        foreach ($meetups as $i => $_) $meetups[$i]['elsewhere'] = true;
    }

    return [
        'matches'     => $matches,
        'trips'       => $trips,
        'suggested'   => $suggested,
        'meetups'     => $meetups,
        'match_count' => rmt_match_count($uid),
    ];
}

/**
 * Like, comment and save counts for a page of feed rows, in three queries rather than three per row.
 *
 * A feed without numbers on it is a list of links: nobody can tell which of forty entries anybody
 * else cared about, and there is nothing to press. The naive version of this is a COUNT per row per
 * kind, which on a forty row feed is a hundred and twenty round trips on a free instance. Grouping
 * by target type and asking once per table keeps it at three no matter how long the feed is.
 *
 * 'going' rows are trips shown as plans, so they count as the trip they are.
 *
 * @param list<array<string,mixed>> $items
 * @return array{likes:array<string,int>,comments:array<string,int>,mine:array<string,bool>}
 */
function rmt_feed_engagement(array $items, ?int $uid): array {
    $out = ['likes' => [], 'comments' => [], 'mine' => []];
    $ids = [];
    foreach ($items as $it) {
        $type = (string) ($it['kind'] ?? '');
        if ($type === 'going') $type = 'trip';
        if (!isset(RMT_INTERACT_TARGETS[$type])) continue;
        $id = (int) ($it['id'] ?? 0);
        if ($id > 0) $ids[$type][$id] = true;
    }
    if (!$ids) return $out;

    /* One WHERE per type, OR'd together, so the whole page is one statement per table. */
    $where = [];
    $args  = [];
    foreach ($ids as $type => $set) {
        $marks = implode(',', array_fill(0, count($set), '?'));
        $where[] = "(target_type = ? AND target_id IN ($marks))";
        $args[]  = $type;
        foreach (array_keys($set) as $id) $args[] = $id;
    }
    $clause = implode(' OR ', $where);

    foreach (q_all("SELECT target_type t, target_id i, COUNT(*) c FROM likes
                     WHERE $clause GROUP BY target_type, target_id", $args) as $r) {
        $out['likes'][$r['t'] . ':' . (int) $r['i']] = (int) $r['c'];
    }
    foreach (q_all("SELECT target_type t, target_id i, COUNT(*) c FROM comments
                     WHERE status = 'published' AND ($clause) GROUP BY target_type, target_id", $args) as $r) {
        $out['comments'][$r['t'] . ':' . (int) $r['i']] = (int) $r['c'];
    }
    if ($uid > 0) {
        foreach (q_all("SELECT target_type t, target_id i FROM likes
                         WHERE user_id = ? AND ($clause)", array_merge([$uid], $args)) as $r) {
            $out['mine'][$r['t'] . ':' . (int) $r['i']] = true;
        }
    }
    return $out;
}

/**
 * The last two comments on each row of a feed, in one query for the whole page.
 *
 * A like is a dead end: it says somebody was here and nothing about what they thought. Comments
 * are the part that turns a feed into a conversation, and a feed that hides them behind a click
 * has no conversation on it, because nobody clicks into a thread they cannot see the start of.
 *
 * One statement, ordered newest first, capped well above what the page can show, then grouped and
 * trimmed in PHP. A per-target LIMIT in SQL means a window function or one query per row, and the
 * cap does the same job at a fraction of the cost.
 *
 * @param list<array<string,mixed>> $items
 * @return array<string,list<array<string,mixed>>> keyed "type:id", oldest first within each
 */
function rmt_feed_comments(array $items, int $perTarget = 2): array {
    $ids = [];
    foreach ($items as $it) {
        $type = (string) ($it['kind'] ?? '');
        if ($type === 'going') $type = 'trip';
        if (!isset(RMT_INTERACT_TARGETS[$type])) continue;
        $id = (int) ($it['id'] ?? 0);
        if ($id > 0) $ids[$type][$id] = true;
    }
    if (!$ids) return [];

    $where = [];
    $args  = [];
    foreach ($ids as $type => $set) {
        $marks = implode(',', array_fill(0, count($set), '?'));
        $where[] = "(c.target_type = ? AND c.target_id IN ($marks))";
        $args[]  = $type;
        foreach (array_keys($set) as $id) $args[] = $id;
    }
    $clause = implode(' OR ', $where);

    $rows = q_all("SELECT c.id, c.target_type, c.target_id, c.body, c.created_at,
                          u.username, p.avatar_url
                     FROM comments c
                     JOIN users u ON u.id = c.user_id AND u.status = 'active'
                LEFT JOIN profiles p ON p.user_id = c.user_id
                    WHERE c.status = 'published' AND ($clause)
                 ORDER BY c.id DESC LIMIT 300", $args);

    $out = [];
    foreach ($rows as $r) {
        $key = $r['target_type'] . ':' . (int) $r['target_id'];
        if (count($out[$key] ?? []) >= $perTarget) continue;
        $out[$key][] = $r;
    }
    foreach ($out as $k => $v) $out[$k] = array_reverse($v);   // a thread reads oldest first
    return $out;
}
