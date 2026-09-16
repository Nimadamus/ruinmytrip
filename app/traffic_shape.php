<?php
declare(strict_types=1);

/**
 * How much of the recorded traffic is a person, read from the shape of each session.
 *
 * Why this is needed at all, and why it is careful. The dashboard said 4,076 arrivals in four days
 * on a site with ZERO search clicks in twenty eight, and a join form viewed 1,286 times that
 * produced two attempts. A number that wrong in the flattering direction is worse than no number,
 * because every decision gets made against it.
 *
 * WHAT THIS CANNOT DO, said rather than implied. The event table holds no IP address, no user
 * agent and no referrer, on purpose, and tests/growth_funnel_test.php fails if anybody adds one.
 * So this cannot name a crawler, cannot attribute a visit to Google or a referrer, and cannot
 * geolocate anything. It reads: how many events a session produced, over how long, in what
 * combination, and whether any of them are things only a person does.
 *
 * IT IS CONSERVATIVE ON PURPOSE. One page view and nothing after it is what a bored human and a
 * polite crawler both look like, and there is no honest way to separate them from this data. Those
 * are UNCERTAIN. Only shapes a person cannot physically produce are called automated, and only
 * evidence of a person is called human. The three numbers are meant to be read together: a large
 * uncertain pile next to eleven humans is itself the finding.
 *
 * Everything here is a COUNT or an aggregate over counts. It reads; it writes nothing.
 */

/** Events that mean somebody did something, rather than something was fetched. */
const RMT_SHAPE_HUMAN_EVENTS = [
    'destination_follow_click', 'destination_follow_success', 'ask_question_click', 'question_posted',
    'post_created', 'comment_created', 'reaction_created', 'join_submit', 'join_created',
    'login_completed', 'trip_create_started', 'trip_created', 'profile_edit_started', 'profile_completed',
    'review_cta_click', 'review_form_start', 'review_submit_attempt', 'review_publish_success',
    'contribute_search', 'contribute_place_selected', 'place_suggested', 'message_sent',
    'trip_connect_requested', 'trip_connect_accepted', 'overlapping_traveler_viewed',
];

/**
 * Classify every session in a window, and report the evidence rather than only the verdict.
 *
 * @return array<string,mixed>
 */
function rmt_traffic_shape(int $days = 30): array {
    $since = rmt_funnel_since($days);

    $rows = q_all(
        "SELECT journey, COUNT(*) events, COUNT(DISTINCT event) kinds,
                MIN(created_at) first_at, MAX(created_at) last_at,
                MAX(COALESCE(cookied, 0)) gave_cookie_back,
                COUNT(cookied) rows_with_the_bit
           FROM contribution_events
          WHERE created_at >= ? AND journey IS NOT NULL AND journey <> ''
       GROUP BY journey", [$since]);

    $eventsBy = [];
    foreach (q_all("SELECT journey, event FROM contribution_events
                     WHERE created_at >= ? AND journey IS NOT NULL AND journey <> ''
                  GROUP BY journey, event", [$since]) as $r) {
        $eventsBy[(string) $r['journey']][(string) $r['event']] = true;
    }

    $human = 0; $auto = 0; $unsure = 0;
    $verdict = [];                       // journey => human|auto|unsure, for the landing split
    $why = ['human' => [], 'automated' => []];
    $spans = ['instant_0s' => 0, 'under_5s' => 0, 's5_to_60s' => 0, 'm1_to_10m' => 0, 'over_10m' => 0];
    $sizes = ['one_event' => 0, 'two_events' => 0, 'three_to_five' => 0, 'six_to_twenty' => 0, 'over_twenty' => 0];

    foreach ($rows as $r) {
        $j = (string) $r['journey'];
        $ev = $eventsBy[$j] ?? [];
        $n = (int) $r['events'];
        $span = max(0, strtotime((string) $r['last_at']) - strtotime((string) $r['first_at']));

        $spans[$span === 0 ? 'instant_0s' : ($span < 5 ? 'under_5s' : ($span <= 60 ? 's5_to_60s'
            : ($span <= 600 ? 'm1_to_10m' : 'over_10m')))]++;
        $sizes[$n === 1 ? 'one_event' : ($n === 2 ? 'two_events' : ($n <= 5 ? 'three_to_five'
            : ($n <= 20 ? 'six_to_twenty' : 'over_twenty')))]++;

        $acted = false;
        foreach (RMT_SHAPE_HUMAN_EVENTS as $he) { if (isset($ev[$he])) { $acted = true; break; } }
        /* Whether the client ever handed back a token we set. Only meaningful on a session that
           produced more than one event: a person's first page has no cookie either, so a single
           hit without one proves nothing and stays uncertain. */
        $gaveCookie = (int) ($r['gave_cookie_back'] ?? 0) === 1;
        $bitKnown   = (int) ($r['rows_with_the_bit'] ?? 0) > 0;   // rows from before migration 093 have none

        if ($acted) {
            $human++; $verdict[$j] = 'human';
            $why['human']['did_something_only_a_person_does'] = ($why['human']['did_something_only_a_person_does'] ?? 0) + 1;
        } elseif ($gaveCookie) {
            /* It stored what we sent and sent it back. Automation can do this and almost none
               does, so on its own it is enough to call a session a browser. */
            $human++; $verdict[$j] = 'human';
            $why['human']['returned_the_cookie_we_set'] = ($why['human']['returned_the_cookie_we_set'] ?? 0) + 1;
        } elseif ((int) $r['kinds'] >= 2 && $span >= 20) {
            $human++; $verdict[$j] = 'human';
            $why['human']['read_several_pages_over_human_time'] = ($why['human']['read_several_pages_over_human_time'] ?? 0) + 1;
        } elseif ($bitKnown && $n >= 2 && !$gaveCookie) {
            /* Several requests and not one of them carried state back. No browser does this; it is
               the shape of a fetcher following links, which is what was being counted as arrivals.
               Only applied where the bit was actually recorded, so history is not reinterpreted. */
            $auto++; $verdict[$j] = 'auto';
            $why['automated']['several_requests_never_returned_a_cookie'] = ($why['automated']['several_requests_never_returned_a_cookie'] ?? 0) + 1;
        } elseif ($n >= 8 && $span <= 30) {
            $auto++; $verdict[$j] = 'auto';
            $why['automated']['many_pages_in_seconds'] = ($why['automated']['many_pages_in_seconds'] ?? 0) + 1;
        } elseif ($n >= 5 && $span === 0) {
            $auto++; $verdict[$j] = 'auto';
            $why['automated']['several_pages_same_second'] = ($why['automated']['several_pages_same_second'] ?? 0) + 1;
        } elseif (isset($ev['join_view']) && $n <= 2 && $span <= 1) {
            $auto++; $verdict[$j] = 'auto';
            $why['automated']['join_form_instantly_and_nothing_else'] = ($why['automated']['join_form_instantly_and_nothing_else'] ?? 0) + 1;
        } else {
            $unsure++; $verdict[$j] = 'unsure';
        }
    }

    /* Where sessions landed. Only destination pages can be named: they are the one page type that
       records WHICH page, and only since that event shipped, which the response states. */
    $land = ['human' => [], 'automated' => [], 'uncertain' => []];
    foreach (q_all("SELECT e.journey, d.slug
                      FROM contribution_events e JOIN destinations d ON d.id = e.destination_id
                     WHERE e.created_at >= ? AND e.event = 'destination_page_view'
                       AND e.journey IS NOT NULL AND e.journey <> ''
                  GROUP BY e.journey, d.slug", [$since]) as $r) {
        $bucket = ['human' => 'human', 'auto' => 'automated'][$verdict[(string) $r['journey']] ?? 'unsure'] ?? 'uncertain';
        $slug = (string) $r['slug'];
        $land[$bucket][$slug] = ($land[$bucket][$slug] ?? 0) + 1;
    }
    foreach ($land as $k => $v) { arsort($land[$k]); $land[$k] = array_slice($land[$k], 0, 10, true); }

    $firstDest = q_one("SELECT MIN(created_at) c FROM contribution_events WHERE event = 'destination_page_view'");

    /* The hour each session began, in UTC. Human traffic has a night in it; automated traffic does
       not, and a single hour holding a quarter of a window is a crawl rather than an audience. */
    $byHour = array_fill(0, 24, 0);
    foreach ($rows as $r) $byHour[(int) date('G', (int) strtotime((string) $r['first_at']))]++;

    /* The overcounting evidence, in the three numbers that settle it: a session id is minted per
       PHP session, so anything ignoring cookies gets a new one per request. */
    $events = (int) (q_one("SELECT COUNT(*) c FROM contribution_events WHERE created_at >= ?", [$since])['c'] ?? 0);
    $sessions = count($rows);
    $tokens = q_one("SELECT COUNT(DISTINCT visitor) v, COUNT(DISTINCT journey) j, MIN(created_at) since
                       FROM contribution_events
                      WHERE created_at >= ? AND visitor IS NOT NULL AND visitor <> ''", [$since]);

    return [
        'method' => 'session shape only: no IP, no user agent and no referrer are collected, so a '
                  . 'crawler cannot be named and a traffic source cannot be attributed',
        'sessions' => [
            'total' => $sessions,
            'likely_human' => $human,
            'likely_automated' => $auto,
            'uncertain' => $unsure,
        ],
        'why' => $why,
        'shape' => ['session_duration' => $spans, 'events_per_session' => $sizes],
        'landing_destinations' => $land,
        'landing_note' => 'destination_page_view has only existed since ' . (string) ($firstDest['c'] ?? 'never')
                        . '; earlier sessions recorded that a public page was seen, not which one',
        'sessions_starting_by_hour_utc' => $byHour,
        'overcounting' => [
            'event_rows' => $events,
            'sessions' => $sessions,
            'sessions_of_zero_duration' => $spans['instant_0s'],
            'sessions_of_one_event' => $sizes['one_event'],
            'distinct_browser_tokens' => (int) ($tokens['v'] ?? 0),
            'sessions_carrying_a_token' => (int) ($tokens['j'] ?? 0),
            'browser_token_data_since' => $tokens['since'] ?? null,
            'reading' => 'a session id is minted per PHP session, so a client that ignores cookies '
                       . 'produces one session per request: sessions count requests, not visitors',
        ],
    ];
}

/**
 * Every stage of the loop as a count, and the conversion between consecutive stages.
 *
 * Counted by session, like everything else in this table, so a double click is one attempt. The
 * ratios are computed here rather than left to whoever reads the JSON, because a rate with an
 * unstated denominator is how a funnel gets quoted wrongly.
 *
 * @return array<string,mixed>
 */
function rmt_funnel_stages(int $days = 30): array {
    $c = rmt_social_counts($days);
    $v = rmt_visitor_counts($days);
    $stages = [
        ['key' => 'landed_on_a_destination', 'n' => (int) ($c['destination_page_view'] ?? 0)],
        ['key' => 'touched_it',              'n' => (int) ($c['destination_follow_click'] ?? 0) + (int) ($c['ask_question_click'] ?? 0)
                                                  + (int) ($c['reaction_created'] ?? 0) + (int) ($c['comment_created'] ?? 0)],
        ['key' => 'signup_started',          'n' => (int) ($c['join_submit'] ?? 0)],
        ['key' => 'signup_completed',        'n' => (int) ($c['join_created'] ?? 0)],
        ['key' => 'trip_created',            'n' => (int) ($c['trip_created'] ?? 0)],
        ['key' => 'overlap_viewed',          'n' => (int) ($c['overlapping_traveler_viewed'] ?? 0)],
        ['key' => 'traveler_profile_clicked','n' => (int) ($c['traveler_profile_clicked'] ?? 0)],
        ['key' => 'connection_requested',    'n' => (int) ($c['trip_connect_requested'] ?? 0)],
        ['key' => 'connection_accepted',     'n' => (int) ($c['trip_connect_accepted'] ?? 0)],
        ['key' => 'conversation_started',    'n' => (int) ($c['message_started'] ?? 0)],
        ['key' => 'messages_sent',           'n' => (int) ($c['message_sent'] ?? 0)],
        ['key' => 'returned_later',          'n' => (int) ($c['destination_return_visit'] ?? 0)],
    ];
    $out = [];
    $prev = null;
    foreach ($stages as $s) {
        $s['of_previous_pct'] = ($prev !== null && $prev > 0) ? round($s['n'] * 100 / $prev, 1) : null;
        $out[] = $s;
        $prev = $s['n'];
    }
    return [
        'stages' => $out,
        'counts' => $c,
        'browsers' => $v,
        'note' => 'counted by session; a stage with a zero above it reports no rate rather than a zero rate',
    ];
}
