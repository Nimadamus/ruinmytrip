<?php
declare(strict_types=1);

/**
 * "Happening on RuinMyTrip": one mixed stream of what people are actually doing, for the front
 * page and the feed.
 *
 * Real rows first, always: upcoming public trips, open travel buddy requests, questions and posts,
 * traveler reviews, upcoming meetups. Only when those are thin is the stream topped up, and only
 * with things that are honestly ours and say so on the card: "what went wrong" lines from the
 * RuinMyTrip research account, and the RuinMyTrip team's questions for a city, each of which opens
 * that city's composer. Nothing here is written as, or looks like, a member who does not exist.
 *
 * The labelled team accounts (team_*) post under names that already say "(RuinMyTrip team)", and
 * their posts are shown under those names.
 */

/** @return list<array<string,mixed>> */
function rmt_live_activity(?int $viewerId, int $limit = 8, ?int $destId = null): array {
    $today = date('Y-m-d');
    $ed = defined('RMT_EDITORIAL_ROLE') ? RMT_EDITORIAL_ROLE : 'editorial';
    $blocked = function_exists('rmt_blocked_ids') ? rmt_blocked_ids($viewerId) : [];
    $dw = static fn(string $col): string => $destId ? " AND $col = " . (int) $destId : '';
    $who = static fn(array $r): string => trim((string) ($r['display_name'] ?? '')) !== ''
        ? (string) $r['display_name'] : '@' . (string) $r['username'];
    $safe = static function (callable $f): array { try { return $f(); } catch (Throwable $e) { return []; } };
    $keep = static fn(array $rows): array => array_values(array_filter($rows,
        static fn($r) => !isset($blocked[(int) ($r['user_id'] ?? 0)]) && (int) ($r['user_id'] ?? 0) !== (int) $viewerId));

    $lanes = [];
    $lanes['trip'] = array_map(static fn($r) => [
        'kind' => 'trip', 'label' => 'Going soon', 'title' => (string) $r['title'],
        // Auto titles already carry the dates ("Milan, 15 to 21 October"); say them once.
        'meta' => preg_match('/\d/', (string) $r['title']) ? '' : rmt_live_range((string) $r['date_from'], (string) $r['date_to']),
        'href' => url('trip/' . (int) $r['id'] . '/' . (string) $r['slug']), 'who' => $who($r), 'editorial' => false,
    ], $keep($safe(static fn() => q_all("SELECT t.id, t.slug, t.title, t.date_from, t.date_to, t.user_id, u.username, p.display_name
              FROM trips t JOIN users u ON u.id = t.user_id AND u.status = 'active' AND u.role <> ?
         LEFT JOIN profiles p ON p.user_id = u.id
             WHERE t.status = 'published' AND COALESCE(t.visibility, 'public') = 'public'
               AND t.date_from IS NOT NULL AND t.date_to >= ?" . $dw('t.destination_id') . "
          ORDER BY t.date_from, t.id LIMIT 6", [$ed, $today]))));

    $lanes['buddy'] = array_map(static fn($r) => [
        'kind' => 'buddy', 'label' => 'Looking for a travel buddy', 'title' => (string) $r['title'],
        'meta' => preg_match('/\d/', (string) $r['title']) ? '' : rmt_live_range((string) $r['date_from'], (string) $r['date_to']),
        'href' => url('buddy/' . (int) $r['id']), 'who' => $who($r), 'editorial' => false,
    ], $keep($safe(static fn() => q_all("SELECT b.id, b.title, b.date_from, b.date_to, b.user_id, u.username, p.display_name
              FROM buddy_posts b JOIN users u ON u.id = b.user_id AND u.status = 'active'
         LEFT JOIN profiles p ON p.user_id = u.id
             WHERE b.status = 'open' AND b.date_to >= ?" . $dw('b.destination_id') . "
          ORDER BY b.created_at DESC, b.id DESC LIMIT 6", [$today]))));

    $lanes['question'] = array_map(static fn($r) => [
        'kind' => 'question', 'label' => !empty($r['dest_name']) ? 'Asked about ' . $r['dest_name'] : 'Travel talk',
        'title' => excerpt((string) $r['body'], 140),
        'meta' => (int) $r['replies'] > 0 ? ((int) $r['replies'] . ((int) $r['replies'] === 1 ? ' reply' : ' replies')) : 'No replies yet',
        'href' => url('post/' . (int) $r['id']), 'who' => $who($r), 'editorial' => false,
    ], $keep($safe(static fn() => q_all("SELECT po.id, po.body, po.user_id, u.username, pr.display_name, d.name dest_name,
                   (SELECT COUNT(*) FROM comments c WHERE c.target_type = 'post' AND c.target_id = po.id AND c.status = 'published') replies
              FROM posts po JOIN users u ON u.id = po.user_id AND u.status = 'active'
         LEFT JOIN profiles pr ON pr.user_id = u.id
         LEFT JOIN destinations d ON d.id = po.destination_id
             WHERE po.status = 'published' AND po.collection_id IS NULL" . $dw('po.destination_id') . "
          ORDER BY po.created_at DESC, po.id DESC LIMIT 6", []))));

    $lanes['review'] = array_map(static fn($r) => [
        'kind' => 'review', 'label' => 'Traveler review', 'title' => (string) ($r['title'] ?: ($r['place_name'] ?? 'A review')),
        'meta' => (string) ($r['place_name'] ?: ($r['dest_name'] ?? '')),
        'href' => url('review/' . (int) $r['id']), 'who' => $who($r), 'editorial' => false,
    ], $keep($safe(static fn() => q_all("SELECT r.id, r.title, r.user_id, u.username, p.display_name, pl.name place_name, d.name dest_name
              FROM reviews r JOIN users u ON u.id = r.user_id AND u.status = 'active' AND u.role <> ?
         LEFT JOIN profiles p ON p.user_id = u.id
         LEFT JOIN places pl ON pl.id = r.place_id
         LEFT JOIN destinations d ON d.id = r.destination_id
             WHERE r.status = 'published'" . $dw('r.destination_id') . "
          ORDER BY r.created_at DESC, r.id DESC LIMIT 4", [$ed]))));

    $lanes['meetup'] = array_map(static fn($r) => [
        'kind' => 'meetup', 'label' => 'Meetup' . (!empty($r['dest_name']) ? ' in ' . $r['dest_name'] : ''),
        'title' => (string) $r['title'], 'meta' => date('D j M', (int) strtotime((string) $r['date_start'])),
        'href' => url('meetup/' . (int) $r['id']), 'who' => $who($r), 'editorial' => false,
    ], $keep($safe(static fn() => q_all("SELECT m.id, m.title, m.date_start, m.host_id user_id, u.username, p.display_name, d.name dest_name
              FROM meetups m JOIN users u ON u.id = m.host_id AND u.status = 'active'
         LEFT JOIN profiles p ON p.user_id = u.id
         LEFT JOIN destinations d ON d.id = m.destination_id
             WHERE m.status = 'published' AND m.date_start >= ?" . $dw('m.destination_id') . "
          ORDER BY m.date_start LIMIT 3", [date('Y-m-d H:i:s')]))));

    $items = rmt_live_interleave($lanes, $limit);
    $real = count($items);

    /* Topped up, labelled, when members have not filled it yet. */
    if ($real < $limit) {
        $lines = $safe(static fn() => q_all("SELECT r.id, r.what_ruined, pl.name place_name, d.name dest_name
                   FROM reviews r JOIN users u ON u.id = r.user_id AND u.status = 'active' AND u.role = ?
              LEFT JOIN places pl ON pl.id = r.place_id
              LEFT JOIN destinations d ON d.id = r.destination_id
                  WHERE r.status = 'published' AND r.what_ruined IS NOT NULL AND TRIM(r.what_ruined) <> ''"
                  . $dw('r.destination_id') . "
               ORDER BY r.id DESC LIMIT 12", [$ed]));
        // A different few each day, so a returning visitor is not shown the same three lines.
        if ($lines) {
            $off = (int) date('z') % count($lines);
            $lines = array_merge(array_slice($lines, $off), array_slice($lines, 0, $off));
        }
        $fill = [];
        foreach (array_slice($lines, 0, 3) as $r) {
            $fill[] = ['kind' => 'warning', 'label' => 'What went wrong' . (!empty($r['dest_name']) ? ' in ' . $r['dest_name'] : ''),
                       'title' => excerpt((string) $r['what_ruined'], 150), 'meta' => (string) ($r['place_name'] ?? ''),
                       'href' => url('review/' . (int) $r['id']), 'who' => 'RuinMyTrip research', 'editorial' => true];
        }
        $cities = $destId ? $safe(static fn() => q_all('SELECT slug, name FROM destinations WHERE id = ?', [$destId]))
            : $safe(static fn() => q_all('SELECT slug, name FROM destinations ORDER BY id LIMIT 40'));
        if ($cities && !$destId) {
            $off = (int) date('z') % count($cities);
            $cities = array_slice(array_merge(array_slice($cities, $off), array_slice($cities, 0, $off)), 0, 3);
        }
        foreach ($cities as $i => $c) {
            $pr = rmt_city_prompts((string) $c['name'], 3);
            $k = array_keys($pr)[$i % 3];
            $fill[] = ['kind' => 'prompt', 'label' => 'The RuinMyTrip team asks', 'title' => $pr[$k],
                       'meta' => 'Answer it in the ' . $c['name'] . ' community',
                       'href' => url('d/' . $c['slug'] . '?ask=' . $k) . '#city-ask', 'who' => 'RuinMyTrip team', 'editorial' => true];
        }
        // Alternate research and questions so neither kind fills the block on its own.
        $w = array_values(array_filter($fill, static fn($x) => $x['kind'] === 'warning'));
        $p = array_values(array_filter($fill, static fn($x) => $x['kind'] === 'prompt'));
        $mixed = [];
        for ($i = 0; $i < max(count($w), count($p)); $i++) {
            if (isset($p[$i])) $mixed[] = $p[$i];
            if (isset($w[$i])) $mixed[] = $w[$i];
        }
        $items = array_merge($items, array_slice($mixed, 0, $limit - $real));
    }
    return $items;
}

/** One from each lane in turn, so a busy lane cannot bury the others. */
function rmt_live_interleave(array $lanes, int $limit): array {
    $out = [];
    $order = ['trip', 'question', 'buddy', 'review', 'meetup'];
    for ($round = 0; count($out) < $limit; $round++) {
        $added = false;
        foreach ($order as $k) {
            if (isset($lanes[$k][$round])) { $out[] = $lanes[$k][$round]; $added = true; if (count($out) >= $limit) break; }
        }
        if (!$added) break;
    }
    return $out;
}

function rmt_live_range(string $from, string $to): string {
    $f = strtotime($from); $t = strtotime($to);
    if (!$f || !$t) return '';
    return date('M', $f) === date('M', $t) ? date('j', $f) . ' to ' . date('j M', $t) : date('j M', $f) . ' to ' . date('j M', $t);
}

/**
 * The two things a city page did not show near the top: who is looking for a travel buddy there,
 * and what went wrong for people who went. Members' lines first; the research account's lines
 * only when members have none, and labelled as research.
 *
 * @return array{buddies:list<array<string,mixed>>, warnings:list<array<string,mixed>>}
 */
function rmt_city_extras(int $destId): array {
    $out = ['buddies' => [], 'warnings' => []];
    if ($destId < 1) return $out;
    $ed = defined('RMT_EDITORIAL_ROLE') ? RMT_EDITORIAL_ROLE : 'editorial';
    try {
        $out['buddies'] = q_all("SELECT b.id, b.title, b.date_from, b.date_to, u.username
                                   FROM buddy_posts b JOIN users u ON u.id = b.user_id AND u.status = 'active'
                                  WHERE b.destination_id = ? AND b.status = 'open' AND b.date_to >= ?
                               ORDER BY b.date_from LIMIT 3", [$destId, date('Y-m-d')]);
        $out['warnings'] = q_all("SELECT r.id, r.what_ruined, u.role, u.username, pl.name place_name
                                    FROM reviews r JOIN users u ON u.id = r.user_id AND u.status = 'active'
                               LEFT JOIN places pl ON pl.id = r.place_id
                                   WHERE r.destination_id = ? AND r.status = 'published'
                                     AND r.what_ruined IS NOT NULL AND TRIM(r.what_ruined) <> ''
                                ORDER BY CASE WHEN u.role = ? THEN 1 ELSE 0 END, r.created_at DESC, r.id DESC
                                   LIMIT 2", [$destId, $ed]);
    } catch (Throwable $e) {
        // A community strip is never worth a broken city page.
    }
    return $out;
}

/** The nine city pages in the SEO title experiment are left exactly as they were until it is read. */
function rmt_in_title_test(string $slug): bool {
    return defined('RMT_DEST_SOCIAL_TITLE_TEST') && isset(RMT_DEST_SOCIAL_TITLE_TEST[$slug])
        && date('Y-m-d') <= '2026-09-29';
}
