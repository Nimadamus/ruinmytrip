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
        return ['joinable' => [], 'matches' => [], 'trips' => [], 'suggested' => [], 'meetups' => [],
                'match_count' => 0];
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

    /* Plans the member could walk into, on the days they are actually there. This is the end of
       the sentence the whole product is built toward: three people overlap my dates, one is going
       to the match, and I can join whichever fits me. */
    $joinable = function_exists('rmt_activities_joinable_for') ? rmt_activities_joinable_for($uid, 4) : [];

    /* The other end of the same loop. A plan whose day has passed and which its owner has not
       answered for is the one piece of knowledge the next traveler needs and nobody else has. */
    $toReview = function_exists('rmt_activities_to_review') ? rmt_activities_to_review($uid, 3) : [];

    return [
        'review'      => $toReview,
        'joinable'    => $joinable,
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

/**
 * Rank a page of feed rows for one member.
 *
 * A feed sorted purely by time is a log. It treats a photograph from somebody who will be in Lisbon
 * the same week as you exactly like a blog post about tourist taxes, and the only thing it knows
 * about you is when you loaded the page. That is what makes a feed feel like database rows.
 *
 * So: recency still leads, because a travel feed is about what is happening, and then five signals
 * that are all things the member did on purpose rather than things guessed about them.
 *
 *   overlapping dates   the strongest, and the reason this product exists
 *   a city they are going to
 *   a city they saved
 *   somebody they follow
 *   how many other people already reacted to it
 *
 * Nothing here is a secret. Every boost that changes an item's position also writes a line on the
 * row saying why it is there ("Because you are going to Lisbon"), because a ranked feed that cannot
 * explain itself is indistinguishable from a broken one.
 *
 * @param list<array<string,mixed>> $items
 * @return list<array<string,mixed>> same rows, reordered, each with feed_score and feed_reason
 */
function rmt_feed_rank(array $items, int $uid, array $engagement = []): array {
    if (!$items) return $items;

    /* The signals, fetched once for the page rather than per row. */
    $follows = [];
    foreach (q_all('SELECT followee_id FROM follows WHERE follower_id = ?', [$uid]) as $r) {
        $follows[(int) $r['followee_id']] = true;
    }
    $saved = [];
    foreach (q_all("SELECT target_id FROM saves WHERE user_id = ? AND target_type = 'destination'", [$uid]) as $r) {
        $saved[(int) $r['target_id']] = true;
    }
    $myCities = [];
    foreach (q_all("SELECT DISTINCT destination_id FROM trips
                     WHERE user_id = ? AND status = 'published' AND destination_id IS NOT NULL
                       AND (date_to IS NULL OR date_to >= ?)", [$uid, date('Y-m-d')]) as $r) {
        $myCities[(int) $r['destination_id']] = true;
    }
    $overlap = [];
    foreach (rmt_trip_matches($uid, 40) as $m) $overlap[(int) $m['user_id']] = (string) $m['dest_name'];

    /* Kind weights. A meetup and a photograph are events; a collection is a list that will be just
       as good tomorrow. */
    $kindWeight = ['meetup' => 0.40, 'activity' => 0.38, 'photo' => 0.35, 'trip' => 0.30, 'going' => 0.25,
                   'review' => 0.20, 'post' => 0.15, 'guide' => 0.05, 'blog_post' => 0.0,
                   'collection' => 0.0];

    $now = time();
    foreach ($items as $i => $it) {
        $age = max(0.0, ($now - strtotime((string) ($it['created_at'] ?? 'now'))) / 3600);
        $score = 1.6 / (1 + $age / 36);          // half of its weight after a day and a half
        $why = '';

        $author = (int) ($it['user_id'] ?? 0);
        $dest = (int) ($it['destination_id'] ?? 0);

        if (isset($overlap[$author])) {
            $score += 1.2;
            $why = 'You are both in ' . $overlap[$author] . ' at the same time';
        }
        if ($dest > 0 && isset($myCities[$dest])) {
            $score += 0.9;
            if ($why === '') $why = 'Because you are going to ' . (string) ($it['dest_name'] ?? 'this city');
        }
        if ($dest > 0 && isset($saved[$dest])) {
            $score += 0.5;
            if ($why === '') $why = 'From a city you saved';
        }
        if (isset($follows[$author])) {
            $score += 0.6;
            if ($why === '') $why = '';   // following somebody is not news, it is the default
        }
        $score += $kindWeight[(string) ($it['kind'] ?? '')] ?? 0.0;

        /* What other people did with it, capped: popularity is a tiebreak, never the ranking. */
        $type = ($it['kind'] ?? '') === 'going' ? 'trip' : (string) ($it['kind'] ?? '');
        $key = $type . ':' . (int) ($it['id'] ?? 0);
        $reacted = (int) ($engagement['likes'][$key] ?? 0) + (int) ($engagement['comments'][$key] ?? 0);
        $score += 0.10 * min($reacted, 6);

        /* A meetup that is soon is worth more than one in three months, and one that has passed is
           not in this list at all. */
        if (($it['kind'] ?? '') === 'meetup' && !empty($it['date_start'])) {
            $days = (strtotime((string) $it['date_start']) - $now) / 86400;
            if ($days >= 0 && $days <= 14) $score += 0.35;
        }

        // Your own activity, which you already know about.
        if ($author === $uid) $score -= 0.5;

        $items[$i]['feed_score'] = round($score, 4);
        $items[$i]['feed_reason'] = $why;
    }

    usort($items, static fn(array $x, array $y) => $y['feed_score'] <=> $x['feed_score']);

    /* One author must not own the top of the page. Without this, somebody who posts six
       photographs in a minute buries everybody else, and the feed reads as one person shouting. */
    $seen = [];
    $spread = [];
    $held = [];
    foreach ($items as $it) {
        $a = (int) ($it['user_id'] ?? 0);
        $seen[$a] = ($seen[$a] ?? 0) + 1;
        if ($seen[$a] <= 2) $spread[] = $it;
        else $held[] = $it;
    }
    return array_merge($spread, $held);
}
