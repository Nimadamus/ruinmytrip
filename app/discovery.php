<?php
declare(strict_types=1);

/**
 * Finding people.
 *
 * This is the thing the product is for. Everything else on the site, the reviews, the photographs,
 * the city pages, is in service of one question a traveler actually has: who else will be there.
 * The site could answer exactly one version of it, "whose dates land on top of mine", and only for
 * somebody who had already posted dates, which is the smallest possible audience for the feature
 * that is supposed to explain why this network exists.
 *
 * Six ways in, then:
 *
 *   1. overlapping   somebody in the same city on the same days as you
 *   2. same city     somebody going where you are going, whenever
 *   3. here now      somebody in a city today, mid-trip
 *   4. kindred       somebody who travels the way you do and wants the cities you want
 *   5. meetup peers  somebody who said yes to the same meetup
 *   6. locals        somebody who lives there and said they are open to meeting travelers
 *
 * PRIVACY, which is not negotiable in any of them:
 *   - a trip is only ever read through rmt_plan_visibility_sql(), so followers-only and private
 *     trips behave here exactly as they do everywhere else
 *   - blocks are applied in both directions
 *   - "here now" is a city and a date range, never anything finer, and never a live location: it
 *     says a trip covers today, which is a thing the traveler published themselves
 *   - a local appears only after opting in (profiles.open_to_meeting), never by default, and never
 *     because we inferred it from a home city
 */

/** The blocks clause plus its two bind values, or a no-op pair when nobody is signed in. */
function rmt_discover_blocks(string $col, ?array $viewer): array {
    if (!$viewer) return ['1=1', []];
    [$sql] = rmt_match_block_sql($col);
    return [$sql, [(int) $viewer['id'], (int) $viewer['id']]];
}

/**
 * People going to a city you are also going to, at any time. Wider than a date overlap on purpose:
 * most people plan a trip before they know the exact week, and "we are both going to Lisbon" is
 * already a reason to talk.
 *
 * @return list<array<string,mixed>>
 */
function rmt_discover_same_city(int $uid, int $limit = 12): array {
    if ($uid < 1) return [];
    $viewer = ['id' => $uid];
    [$visSql, $visArgs] = rmt_plan_visibility_sql('o', $viewer);
    [$blockSql, $blockArgs] = rmt_discover_blocks('o.user_id', $viewer);
    return q_all(
        "SELECT o.user_id, o.date_from, o.date_to, d.name dest_name, d.slug dest_slug,
                u.username, p.avatar_url, p.display_name, p.home_city
           FROM trips mine
           JOIN trips o ON o.destination_id = mine.destination_id AND o.user_id <> mine.user_id
           JOIN destinations d ON d.id = o.destination_id
           JOIN users u ON u.id = o.user_id AND u.status = 'active'
      LEFT JOIN profiles p ON p.user_id = o.user_id
          WHERE mine.user_id = ? AND mine.status = 'published'
            AND mine.date_to IS NOT NULL AND mine.date_to >= ?
            AND o.status = 'published' AND o.date_to IS NOT NULL AND o.date_to >= ?
            AND NOT EXISTS (SELECT 1 FROM follows f WHERE f.follower_id = ? AND f.followee_id = o.user_id)
            AND $visSql AND $blockSql
       GROUP BY o.user_id, o.date_from, o.date_to, d.name, d.slug, u.username, p.avatar_url,
                p.display_name, p.home_city
       ORDER BY o.date_from LIMIT " . (int) $limit,
        array_merge([$uid, date('Y-m-d'), date('Y-m-d'), $uid], $visArgs, $blockArgs)
    );
}

/**
 * Who is in a city right now, according to their own published dates.
 *
 * Not a location feature. A trip whose range covers today is a thing its owner wrote down and
 * chose to show, and the answer is still a city and a date range.
 *
 * @return list<array<string,mixed>>
 */
function rmt_discover_here_now(int $destId, ?array $viewer, int $limit = 12): array {
    if ($destId < 1) return [];
    $today = date('Y-m-d');
    [$visSql, $visArgs] = rmt_plan_visibility_sql('t', $viewer);
    [$blockSql, $blockArgs] = rmt_discover_blocks('t.user_id', $viewer);
    return q_all(
        "SELECT t.user_id, t.date_from, t.date_to, u.username, p.avatar_url, p.display_name, p.home_city
           FROM trips t
           JOIN users u ON u.id = t.user_id AND u.status = 'active'
      LEFT JOIN profiles p ON p.user_id = t.user_id
          WHERE t.destination_id = ? AND t.status = 'published'
            AND t.date_from IS NOT NULL AND t.date_to IS NOT NULL
            AND t.date_from <= ? AND t.date_to >= ?
            AND $visSql AND $blockSql
       ORDER BY t.date_to LIMIT " . (int) $limit,
        array_merge([$destId, $today, $today], $visArgs, $blockArgs)
    );
}

/**
 * People who travel the way you say you do and want the cities you want.
 *
 * Both halves are self-declared, so this is a preference match and is described as one. It is
 * ranked by how many saved cities two people have in common, because that is the part that is
 * actually about where somebody wants to go.
 *
 * @return list<array<string,mixed>>
 */
function rmt_discover_kindred(int $uid, int $limit = 8): array {
    if ($uid < 1) return [];
    $me = q_one('SELECT travel_style FROM profiles WHERE user_id = ?', [$uid]);
    $style = trim((string) ($me['travel_style'] ?? ''));
    [$blockSql, $blockArgs] = rmt_discover_blocks('u.id', ['id' => $uid]);

    $rows = q_all(
        "SELECT u.id user_id, u.username, p.avatar_url, p.display_name, p.home_city, p.travel_style,
                (SELECT COUNT(*) FROM saves s1
                   JOIN saves s2 ON s2.target_id = s1.target_id AND s2.target_type = 'destination'
                  WHERE s1.user_id = u.id AND s1.target_type = 'destination' AND s2.user_id = ?) shared_cities
           FROM users u
      LEFT JOIN profiles p ON p.user_id = u.id
          WHERE u.id <> ? AND u.status = 'active' AND u.role <> ?
            AND NOT EXISTS (SELECT 1 FROM follows f WHERE f.follower_id = ? AND f.followee_id = u.id)
            AND $blockSql
       ORDER BY shared_cities DESC, u.id DESC LIMIT 60",
        array_merge([$uid, $uid, RMT_EDITORIAL_ROLE, $uid], $blockArgs)
    );

    /* Rank in PHP rather than in a CASE expression: the weighting is a product decision and it
       belongs somewhere a person can read it. A shared city is worth more than a shared style,
       because one is about a place and the other is about a word. */
    foreach ($rows as $i => $r) {
        $score = 2 * (int) $r['shared_cities'];
        if ($style !== '' && (string) ($r['travel_style'] ?? '') === $style) $score += 1;
        $rows[$i]['match_score'] = $score;
        $rows[$i]['same_style'] = $style !== '' && (string) ($r['travel_style'] ?? '') === $style;
    }
    $rows = array_values(array_filter($rows, static fn(array $r) => (int) $r['match_score'] > 0));
    usort($rows, static fn(array $x, array $y) => $y['match_score'] <=> $x['match_score']);
    return array_slice($rows, 0, $limit);
}

/**
 * People going to the same meetups as you. A yes to the same evening is the strongest signal on
 * the site short of a date overlap, and it was not used for anything.
 *
 * @return list<array<string,mixed>>
 */
function rmt_discover_meetup_peers(int $uid, int $limit = 8): array {
    if ($uid < 1) return [];
    [$blockSql, $blockArgs] = rmt_discover_blocks('u.id', ['id' => $uid]);
    return q_all(
        "SELECT u.id user_id, u.username, p.avatar_url, p.display_name,
                m.id meetup_id, m.title meetup_title, m.date_start, d.name dest_name
           FROM meetup_rsvps mine
           JOIN meetup_rsvps theirs ON theirs.meetup_id = mine.meetup_id AND theirs.user_id <> mine.user_id
           JOIN meetups m ON m.id = mine.meetup_id
      LEFT JOIN destinations d ON d.id = m.destination_id
           JOIN users u ON u.id = theirs.user_id AND u.status = 'active'
      LEFT JOIN profiles p ON p.user_id = u.id
          WHERE mine.user_id = ? AND mine.status = 'going' AND theirs.status = 'going'
            AND m.status = 'published' AND m.date_start >= ?
            AND $blockSql
       ORDER BY m.date_start LIMIT " . (int) $limit,
        array_merge([$uid, date('Y-m-d H:i:s')], $blockArgs)
    );
}

/**
 * People who live in a city and have said they are open to meeting travelers.
 *
 * Opt in, always. profiles.open_to_meeting defaults to off and is a checkbox somebody has to find
 * and tick; living in a city is never taken as consent to be listed as available. The same page
 * says what it means, which is "happy to answer a question or meet in public", not more.
 *
 * @return list<array<string,mixed>>
 */
function rmt_discover_locals(int $destId, ?array $viewer, int $limit = 8): array {
    if ($destId < 1) return [];
    [$blockSql, $blockArgs] = rmt_discover_blocks('u.id', $viewer);
    $exclude = $viewer ? ' AND u.id <> ?' : '';
    $args = [$destId];
    if ($viewer) $args[] = (int) $viewer['id'];
    return q_all(
        "SELECT u.id user_id, u.username, p.avatar_url, p.display_name, p.home_city, p.bio,
                (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id AND r.status = 'published') reviews
           FROM profiles p
           JOIN users u ON u.id = p.user_id AND u.status = 'active' AND u.role <> ?
          WHERE p.home_destination_id = ? AND COALESCE(p.open_to_meeting, 0) = 1
            $exclude AND $blockSql
       ORDER BY reviews DESC, u.id DESC LIMIT " . (int) $limit,
        array_merge([RMT_EDITORIAL_ROLE], $args, $blockArgs)
    );
}

/**
 * Everything at once, for the discovery page.
 *
 * @return array<string,list<array<string,mixed>>>
 */
function rmt_discover_all(int $uid): array {
    if ($uid < 1) return ['overlapping' => [], 'same_city' => [], 'kindred' => [],
                          'meetup_peers' => [], 'suggested' => []];
    return [
        'overlapping'  => array_slice(rmt_trip_matches($uid, 24), 0, 8),
        'same_city'    => rmt_discover_same_city($uid, 8),
        'kindred'      => rmt_discover_kindred($uid, 8),
        'meetup_peers' => rmt_discover_meetup_peers($uid, 6),
        'suggested'    => rmt_follow_suggestions($uid, 8),
    ];
}

/**
 * What to put on a page that has nothing on it yet.
 *
 * A young network is mostly empty by definition, and an empty page that only apologises teaches
 * the reader that the site is dead. The rule here is the same one the rest of the site follows:
 * everything offered is real. Cities appear because somebody is actually going to them, people
 * appear because they actually exist and have actually done something, and when neither is true
 * the caller gets nothing back and the page says so plainly rather than inventing company.
 *
 * @return array{cities:list<array<string,mixed>>,people:list<array<string,mixed>>}
 */
function rmt_empty_state_suggestions(?array $viewer, int $cityLimit = 6, int $peopleLimit = 4): array {
    $today = date('Y-m-d');

    /* Cities with somebody going, soonest first. A city with nobody in it is not a suggestion, it
       is a link, and this page already has plenty of those. */
    $cities = q_all(
        "SELECT d.id, d.slug, d.name, d.country,
                (SELECT COUNT(*) FROM trips t
                  WHERE t.destination_id = d.id AND t.status = 'published'
                    AND t.visibility = 'public' AND t.date_to IS NOT NULL AND t.date_to >= ?) going,
                (SELECT COUNT(*) FROM meetups m
                  WHERE m.destination_id = d.id AND m.status = 'published' AND m.date_start >= ?) meets
           FROM destinations d
       ORDER BY going DESC, meets DESC, d.name
          LIMIT 40", [$today, date('Y-m-d H:i:s')]);
    $cities = array_values(array_filter($cities,
        static fn(array $c) => (int) $c['going'] > 0 || (int) $c['meets'] > 0));
    $cities = array_slice($cities, 0, $cityLimit);

    /* People, with a reason. For a member this is the suggestion engine; for a stranger it is the
       travelers who have actually written something, which is the only ordering that is not a
       popularity claim we cannot back up. */
    $people = [];
    if ($viewer) {
        $people = array_slice(rmt_follow_suggestions((int) $viewer['id'], $peopleLimit + 2), 0, $peopleLimit);
    }
    /* A member on their first day has no signals, so the suggestion engine has nothing to say and
       the page would offer nobody at all. The fallback is the same list a stranger sees: members
       who have actually written something, which is a fact rather than a recommendation. */
    if (!$people) {
        $exclude = $viewer ? ' AND u.id <> ?' : '';
        $args = [RMT_EDITORIAL_ROLE];
        if ($viewer) $args[] = (int) $viewer['id'];
        $people = q_all(
            "SELECT u.id, u.username, p.display_name, p.avatar_url, p.home_city,
                    (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id AND r.status = 'published') reviews
               FROM users u LEFT JOIN profiles p ON p.user_id = u.id
              WHERE u.status = 'active' AND u.role <> ?$exclude
           ORDER BY reviews DESC, u.id DESC LIMIT " . (int) $peopleLimit, $args);
        $people = array_values(array_filter($people, static fn(array $r) => (int) $r['reviews'] > 0));
        foreach ($people as $i => $r) {
            $people[$i]['reason'] = (int) $r['reviews'] . ' ' . ((int) $r['reviews'] === 1 ? 'review' : 'reviews') . ' written';
        }
    }

    return ['cities' => $cities, 'people' => $people];
}
