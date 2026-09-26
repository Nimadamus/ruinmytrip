<?php
declare(strict_types=1);

/**
 * The growth scorecard: one block that answers "is the loop working", in the order the loop runs.
 *
 *   VISITOR -> MEMBER -> FIRST CONTRIBUTION -> RETURNING
 *
 * Everything below either reuses a number another module already computes (so two pages can never
 * disagree about the same count) or is a COUNT over product rows or event rows. Our own house
 * accounts (role editorial, usernames team_*) are left out of every member and content number, and
 * so is any event tagged utm_content=selfcheck.
 *
 * Two visitor numbers are published side by side on purpose. "Likely human" is the cookie based
 * classifier in app/traffic_shape.php. "Engaged" is new on 2026-09-25: a visit in which somebody
 * tapped, typed or scrolled (one bit, sent by the browser). They were split because on 2026-09-25
 * the first one reported 368 human arrivals from Google in a day on which Search Console recorded
 * zero clicks, so a returned cookie is not evidence of a person.
 */

/** Real members only: not our editorial account, not the labelled team accounts. */
function rmt_sc_real_user_sql(string $alias = 'u'): string {
    return "$alias.status = 'active' AND $alias.role <> '" . (defined('RMT_EDITORIAL_ROLE') ? RMT_EDITORIAL_ROLE : 'editorial')
         . "' AND SUBSTR($alias.username, 1, 5) <> 'team_'";
}

/** Distinct journeys that recorded an event since a time, excluding our own checks. */
function rmt_sc_journeys(string $event, string $since, string $extra = '', array $args = []): int {
    try {
        return (int) (q_one("SELECT COUNT(DISTINCT journey) c FROM contribution_events
                              WHERE event = ? AND created_at >= ? AND COALESCE(acq_content, '') <> 'selfcheck'"
                            . ($extra !== '' ? " AND ($extra)" : ''), array_merge([$event, $since], $args))['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function rmt_sc_pct(int $n, int $of): ?float {
    return $of > 0 ? round($n * 100 / $of, 1) : null;
}

/** @return array<string,mixed> */
function rmt_growth_scorecard(int $days = 30): array {
    $since = rmt_funnel_since($days);
    $real = rmt_sc_real_user_sql('u');
    $count = static function (string $sql, array $args = []): int {
        try { return (int) (q_one($sql, $args)['c'] ?? 0); } catch (Throwable $e) { return 0; }
    };
    $g = rmt_growth_funnel($days);

    /* Visitors. */
    $engaged = $count("SELECT COUNT(DISTINCT visitor) c FROM contribution_events
                        WHERE event = 'human_interaction' AND created_at >= ? AND visitor IS NOT NULL AND visitor <> ''
                          AND COALESCE(acq_content, '') <> 'selfcheck'", [$since]);
    $engagedFrom = null;
    try { $engagedFrom = q_one("SELECT MIN(created_at) m FROM contribution_events WHERE event = 'human_interaction'")['m'] ?? null; }
    catch (Throwable $e) { /* none yet */ }
    $searchLanded = rmt_sc_journeys('landing_view', $since, "acq_source = 'search'");
    $searchEngaged = rmt_sc_journeys('human_interaction', $since, "acq_source = 'search'");

    /* Members and their first steps, counted from the product's own rows. */
    $cohort = "FROM users u WHERE $real AND u.created_at >= ?";
    $members = $count("SELECT COUNT(*) c $cohort", [$since]);
    $confirmed = $count("SELECT COUNT(*) c $cohort AND u.email_verified_at IS NOT NULL", [$since]);
    $contributed = $count("SELECT COUNT(*) c $cohort AND (
            EXISTS (SELECT 1 FROM trips t WHERE t.user_id = u.id AND t.status = 'published')
         OR EXISTS (SELECT 1 FROM posts p WHERE p.user_id = u.id AND p.status = 'published')
         OR EXISTS (SELECT 1 FROM comments c2 WHERE c2.user_id = u.id)
         OR EXISTS (SELECT 1 FROM reviews r WHERE r.user_id = u.id AND r.status = 'published')
         OR EXISTS (SELECT 1 FROM buddy_posts b WHERE b.user_id = u.id))", [$since]);
    $returned = 0;
    foreach ($g['spine'] as $st) if ($st['label'] === 'Came back another day') $returned = (int) $st['n'];

    /* What members made in the window. */
    $content = [
        'trips'          => $count("SELECT COUNT(*) c FROM trips t JOIN users u ON u.id = t.user_id WHERE $real AND t.status = 'published' AND t.created_at >= ?", [$since]),
        'public_upcoming'=> $count("SELECT COUNT(*) c FROM trips t JOIN users u ON u.id = t.user_id WHERE $real AND t.status = 'published'
                                     AND COALESCE(t.visibility, 'public') = 'public' AND t.date_from IS NOT NULL AND t.date_to >= ?", [date('Y-m-d')]),
        'buddy_posts'    => $count("SELECT COUNT(*) c FROM buddy_posts b JOIN users u ON u.id = b.user_id WHERE $real AND b.created_at >= ?", [$since]),
        'buddy_open'     => $count("SELECT COUNT(*) c FROM buddy_posts b JOIN users u ON u.id = b.user_id WHERE $real AND b.status = 'open' AND b.date_to >= ?", [date('Y-m-d')]),
        'posts'          => $count("SELECT COUNT(*) c FROM posts p JOIN users u ON u.id = p.user_id WHERE $real AND p.status = 'published' AND p.created_at >= ?", [$since]),
        'comments'       => $count("SELECT COUNT(*) c FROM comments c2 JOIN users u ON u.id = c2.user_id WHERE $real AND c2.created_at >= ?", [$since]),
        'reviews'        => $count("SELECT COUNT(*) c FROM reviews r JOIN users u ON u.id = r.user_id WHERE $real AND r.status = 'published' AND r.created_at >= ?", [$since]),
    ];
    /* Active: a real member who made something in the window, whenever they joined. */
    $active = $count("SELECT COUNT(*) c FROM users u WHERE $real AND (
            EXISTS (SELECT 1 FROM trips t WHERE t.user_id = u.id AND t.created_at >= ?)
         OR EXISTS (SELECT 1 FROM posts p WHERE p.user_id = u.id AND p.created_at >= ?)
         OR EXISTS (SELECT 1 FROM comments c2 WHERE c2.user_id = u.id AND c2.created_at >= ?)
         OR EXISTS (SELECT 1 FROM reviews r WHERE r.user_id = u.id AND r.created_at >= ?)
         OR EXISTS (SELECT 1 FROM buddy_posts b WHERE b.user_id = u.id AND b.created_at >= ?))",
        [$since, $since, $since, $since, $since]);

    /* The two funnels into an account. */
    $signup = [
        'join_view'   => rmt_sc_journeys('join_view', $since),
        'join_submit' => rmt_sc_journeys('join_submit', $since),
        'join_created'=> rmt_sc_journeys('join_created', $since),
        'confirmed'   => $confirmed,
    ];
    $plan = [
        'cta_clicks'       => rmt_sc_journeys('cta_click', $since),
        'plan_view'        => rmt_sc_journeys('plan_view', $since),
        'plan_started'     => rmt_sc_journeys('plan_started', $since),
        'plan_submitted'   => rmt_sc_journeys('plan_submitted', $since, "source = 'plan'"),
        // A question typed on a city page by somebody with no account, held for the account step.
        'questions_held'   => rmt_sc_journeys('plan_submitted', $since, "source = 'destination'"),
        'plan_signup_view' => rmt_sc_journeys('plan_signup_view', $since),
        'join_submit'      => rmt_sc_journeys('join_submit', $since, "source = 'plan'"),
        'join_created'     => rmt_sc_journeys('join_created', $since, "source = 'plan'"),
        'trips_published'  => rmt_sc_journeys('trip_created', $since),
        'buddy_posts'      => rmt_sc_journeys('buddy_post_created', $since),
    ];

    /* Which call to action was pressed, by name. */
    $cta = [];
    try {
        $cta = q_all("SELECT detail, COUNT(DISTINCT journey) n FROM contribution_events
                       WHERE event = 'cta_click' AND created_at >= ? AND detail IS NOT NULL
                         AND COALESCE(acq_content, '') <> 'selfcheck'
                    GROUP BY detail ORDER BY n DESC", [$since]);
    } catch (Throwable $e) { /* before migration 102 */ }

    /* Which landing pages produce members: the first landing path the joining browser ever had. */
    $landingMembers = rmt_sc_landing_paths('join_created', $since);
    $landingTrips = rmt_sc_landing_paths('plan_submitted', $since);
    $landingEngaged = [];
    try {
        $landingEngaged = q_all("SELECT l.path, COUNT(DISTINCT l.journey) n FROM contribution_events l
                                  WHERE l.event = 'landing_view' AND l.path IS NOT NULL AND l.created_at >= ?
                                    AND COALESCE(l.acq_content, '') <> 'selfcheck'
                                    AND EXISTS (SELECT 1 FROM contribution_events h
                                                 WHERE h.journey = l.journey AND h.event = 'human_interaction')
                               GROUP BY l.path ORDER BY n DESC, l.path LIMIT 12", [$since]);
    } catch (Throwable $e) { /* before migration 102 */ }

    $visitorBase = $engaged > 0 ? $engaged : (int) $g['visits'];
    return [
        'window_days' => $days,
        'visitors' => ['likely_human' => (int) $g['visits'], 'engaged' => $engaged,
                       'engaged_measured_since' => $engagedFrom,
                       'search_landed' => $searchLanded, 'search_engaged' => $searchEngaged],
        'members'  => ['registered' => $members, 'confirmed' => $confirmed, 'first_contribution' => $contributed,
                       'returned_another_day' => $returned, 'active' => $active],
        'content'  => $content,
        'signup'   => $signup,
        'plan'     => $plan,
        'rates'    => [
            'visitor_to_member_pct'      => rmt_sc_pct($members, $visitorBase),
            'visitor_base'               => $engaged > 0 ? 'engaged' : 'likely_human',
            'member_to_contribution_pct' => rmt_sc_pct($contributed, $members),
            'member_to_returning_pct'    => rmt_sc_pct($returned, $members),
            'plan_view_to_submit_pct'    => rmt_sc_pct($plan['plan_submitted'], $plan['plan_view']),
            'plan_submit_to_account_pct' => rmt_sc_pct($plan['join_created'], $plan['plan_submitted']),
            'join_view_to_account_pct'   => rmt_sc_pct($signup['join_created'], $signup['join_view']),
        ],
        'cta'              => array_map(static fn($r) => ['cta' => (string) $r['detail'], 'n' => (int) $r['n']], $cta),
        'landing_members'  => $landingMembers,
        'landing_trips'    => $landingTrips,
        'landing_engaged'  => array_map(static fn($r) => ['path' => (string) $r['path'], 'n' => (int) $r['n']], $landingEngaged),
        'destinations_members' => rmt_signup_attribution($days),
        'by_source'        => rmt_source_funnel($days),
    ];
}

/**
 * For every browser that recorded $event in the window, the first landing path it ever had.
 *
 * @return list<array{path:string, n:int}>
 */
function rmt_sc_landing_paths(string $event, string $since): array {
    try {
        $rows = q_all("SELECT l.path, COUNT(DISTINCT j.visitor) n
                         FROM contribution_events j
                         JOIN contribution_events l ON l.id = (SELECT MIN(l2.id) FROM contribution_events l2
                                                                WHERE l2.visitor = j.visitor AND l2.event = 'landing_view'
                                                                  AND l2.path IS NOT NULL)
                        WHERE j.event = ? AND j.created_at >= ? AND j.visitor IS NOT NULL AND j.visitor <> ''
                          AND COALESCE(j.acq_content, '') <> 'selfcheck'
                     GROUP BY l.path ORDER BY n DESC, l.path LIMIT 12", [$event, $since]);
    } catch (Throwable $e) {
        return [];
    }
    return array_map(static fn($r) => ['path' => (string) $r['path'], 'n' => (int) $r['n']], $rows);
}

/**
 * The same loop, one row per channel: which source produces engaged visitors, trip forms, signups,
 * members, first contributions and return visits. Counted in browsers (the visitor token), so a
 * person who arrived from TikTok on Monday and joined on Wednesday is one TikTok row. The channel
 * is first touch and held for ninety days (app/acquisition.php); no channel means direct.
 * "search" is every search engine (Google, Bing, DuckDuckGo and the rest): only the word is stored.
 *
 * @return list<array<string,mixed>>
 */
function rmt_source_funnel(int $days = 30): array {
    $since = rmt_funnel_since($days);
    $contrib = "'trip_created','post_created','comment_created','buddy_post_created','review_publish_success'";
    try {
        $rows = q_all("SELECT COALESCE(acq_source, 'direct') src,
                COUNT(DISTINCT CASE WHEN event = 'landing_view' THEN visitor END) landed,
                COUNT(DISTINCT CASE WHEN event = 'human_interaction' THEN visitor END) engaged,
                COUNT(DISTINCT CASE WHEN event = 'cta_click' THEN visitor END) cta,
                COUNT(DISTINCT CASE WHEN event IN ('plan_view','trip_create_started') THEN visitor END) trip_form,
                COUNT(DISTINCT CASE WHEN event IN ('plan_started','plan_submitted','ask_question_click','cta_click') THEN visitor END) acted,
                COUNT(DISTINCT CASE WHEN event IN ('join_view','plan_signup_view') THEN visitor END) signup_started,
                COUNT(DISTINCT CASE WHEN event = 'join_created' THEN visitor END) signup_completed,
                COUNT(DISTINCT CASE WHEN event IN ($contrib) THEN visitor END) contributed
              FROM contribution_events
             WHERE created_at >= ? AND visitor IS NOT NULL AND visitor <> ''
               AND COALESCE(acq_content, '') <> 'selfcheck'
          GROUP BY COALESCE(acq_source, 'direct')", [$since]);
        $ret = q_all("SELECT src, COUNT(*) n FROM (
                         SELECT COALESCE(acq_source, 'direct') src, visitor
                           FROM contribution_events
                          WHERE created_at >= ? AND visitor IS NOT NULL AND visitor <> ''
                            AND COALESCE(acq_content, '') <> 'selfcheck'
                       GROUP BY COALESCE(acq_source, 'direct'), visitor
                         HAVING COUNT(DISTINCT SUBSTR(CAST(created_at AS TEXT), 1, 10)) > 1) x
                      GROUP BY src", [$since]);
    } catch (Throwable $e) {
        return [];
    }
    $back = [];
    foreach ($ret as $r) $back[(string) $r['src']] = (int) $r['n'];
    $out = [];
    foreach ($rows as $r) {
        $row = ['source' => (string) $r['src']];
        foreach (['landed', 'engaged', 'cta', 'trip_form', 'acted', 'signup_started', 'signup_completed', 'contributed'] as $k) $row[$k] = (int) $r[$k];
        $row['returned'] = $back[$row['source']] ?? 0;
        $row['engaged_to_member_pct'] = rmt_sc_pct($row['signup_completed'], $row['engaged']);
        $out[] = $row;
    }
    // The channels we are working on are always listed, even at zero, so a dead channel is visible.
    foreach (['search', 'facebook', 'instagram', 'tiktok', 'reddit', 'direct', 'referral'] as $s) {
        if (!in_array($s, array_column($out, 'source'), true)) {
            $out[] = ['source' => $s, 'landed' => 0, 'engaged' => 0, 'cta' => 0, 'trip_form' => 0, 'acted' => 0, 'signup_started' => 0,
                      'signup_completed' => 0, 'contributed' => 0, 'returned' => 0, 'engaged_to_member_pct' => null];
        }
    }
    usort($out, static fn($a, $b) => [$b['engaged'], $b['landed']] <=> [$a['engaged'], $a['landed']]);
    return $out;
}
