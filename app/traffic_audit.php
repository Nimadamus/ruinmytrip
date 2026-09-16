<?php
declare(strict_types=1);

/**
 * TEMPORARY read only diagnostic: how much of the recorded traffic is a person?
 *
 * Why this file exists at all. 4,076 signed out sessions were recorded in four days against ZERO
 * search clicks in twenty eight, and a join form viewed 1,286 times produced two attempts. Those
 * numbers cannot all describe humans, and every decision about what to build next rests on which
 * of them does. The event table cannot be queried from outside (the database is firewalled, and
 * opening it was refused, correctly), so the only read only path into it is from inside the
 * application, key protected exactly like /cron/funnel.
 *
 * It is deliberately temporary. It is deleted, with its route, as soon as the report is taken.
 *
 * WHAT IT CANNOT DO, said plainly rather than implied. The event table holds no IP address, no
 * user agent and no referrer, by design and by a test that fails if anybody adds one. So this
 * cannot identify a named crawler, cannot attribute traffic to Google or Bing or a referrer, and
 * cannot geolocate anything. It classifies by SHAPE alone: how many events a session produced,
 * how fast, in what order, and whether anything a person does was among them.
 *
 * It is also conservative on purpose. A session of one page view with nothing after it is what a
 * bored human and a crawler both look like, and there is no honest way to tell them apart from
 * this data, so those are UNCERTAIN rather than quietly counted as bots to make a number look
 * better. Only shapes a person cannot physically produce are called automated.
 *
 * Every statement it makes is a COUNT or an aggregate. It reads; it writes nothing.
 */

/** Events that mean a person did something rather than something was fetched. */
const RMT_AUDIT_HUMAN_EVENTS = [
    'destination_follow_click', 'destination_follow_success', 'ask_question_click', 'question_posted',
    'post_created', 'comment_created', 'reaction_created', 'join_submit', 'join_created',
    'login_completed', 'trip_create_started', 'trip_created', 'profile_edit_started', 'profile_completed',
    'review_cta_click', 'review_form_start', 'review_submit_attempt', 'review_publish_success',
    'contribute_search', 'contribute_place_selected', 'place_suggested', 'message_sent',
    'trip_connect_requested', 'trip_connect_accepted', 'overlapping_traveler_viewed',
];

/**
 * GET /cron/traffic-audit?key=CRON_KEY[&days=N]
 *
 * Same authentication as the funnel endpoint next to it, same noindex header, same JSON out.
 */
function cron_traffic_audit(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = (string) input('key');
    if ($key === '' || $given === '' || !hash_equals($key, $given)) not_found();

    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');

    $days = (int) (input('days') !== '' ? input('days') : 0);
    $since = $days > 0 ? date('Y-m-d H:i:s', strtotime('-' . $days . ' days')) : '0000-01-01 00:00:00';

    /* One row per session. The arithmetic is done in PHP rather than in SQL because created_at is
       TEXT on both drivers and date maths on it is the exact shape that has taken this site down
       before. Strings sort correctly in ISO order, which is all the aggregate needs. */
    $rows = q_all(
        "SELECT journey,
                COUNT(*) events,
                COUNT(DISTINCT event) kinds,
                MIN(created_at) first_at,
                MAX(created_at) last_at,
                COUNT(DISTINCT destination_id) dests,
                MAX(is_authed) authed
           FROM contribution_events
          WHERE created_at >= ? AND journey IS NOT NULL AND journey <> ''
       GROUP BY journey", [$since]);

    /* Which events each session produced, so a shape can be read rather than guessed at. */
    $eventsBy = [];
    foreach (q_all("SELECT journey, event, COUNT(*) n FROM contribution_events
                     WHERE created_at >= ? AND journey IS NOT NULL AND journey <> ''
                  GROUP BY journey, event", [$since]) as $r) {
        $eventsBy[(string) $r['journey']][(string) $r['event']] = (int) $r['n'];
    }

    $human = 0; $auto = 0; $unsure = 0;
    $humanSessions = [];
    $autoSessions = [];
    $reasons = ['human' => [], 'auto' => []];
    $spanBuckets = ['instant (0s)' => 0, 'under 5s' => 0, '5s to 60s' => 0, '1 to 10 min' => 0, 'over 10 min' => 0];
    $eventCountBuckets = ['1 event' => 0, '2 events' => 0, '3 to 5' => 0, '6 to 20' => 0, 'over 20' => 0];

    foreach ($rows as $r) {
        $j = (string) $r['journey'];
        $ev = $eventsBy[$j] ?? [];
        $n = (int) $r['events'];
        $span = max(0, strtotime((string) $r['last_at']) - strtotime((string) $r['first_at']));

        $spanBuckets[$span === 0 ? 'instant (0s)' : ($span < 5 ? 'under 5s' : ($span <= 60 ? '5s to 60s'
            : ($span <= 600 ? '1 to 10 min' : 'over 10 min')))]++;
        $eventCountBuckets[$n === 1 ? '1 event' : ($n === 2 ? '2 events' : ($n <= 5 ? '3 to 5'
            : ($n <= 20 ? '6 to 20' : 'over 20')))]++;

        $didSomething = false;
        foreach (RMT_AUDIT_HUMAN_EVENTS as $he) { if (!empty($ev[$he])) { $didSomething = true; break; } }

        /* LIKELY HUMAN. Either they did something only a person does, or they read more than one
           thing and took human time over it. Twenty seconds is not a high bar and is meant not to
           be: it only has to exclude a fetch. */
        if ($didSomething) {
            $human++; $reasons['human']['did something a person does'] = ($reasons['human']['did something a person does'] ?? 0) + 1;
            $humanSessions[$j] = true;
            continue;
        }
        if ((int) $r['kinds'] >= 2 && $span >= 20) {
            $human++; $reasons['human']['read several pages over human time'] = ($reasons['human']['read several pages over human time'] ?? 0) + 1;
            $humanSessions[$j] = true;
            continue;
        }

        /* LIKELY AUTOMATED. Only shapes a person cannot produce: many pages in less time than it
           takes to read one, or a page opened and the join form opened in the same second. */
        if ($n >= 8 && $span <= 30) {
            $auto++; $reasons['auto']['many pages in seconds'] = ($reasons['auto']['many pages in seconds'] ?? 0) + 1;
            $autoSessions[$j] = true;
            continue;
        }
        if ($n >= 5 && $span === 0) {
            $auto++; $reasons['auto']['several pages in the same second'] = ($reasons['auto']['several pages in the same second'] ?? 0) + 1;
            $autoSessions[$j] = true;
            continue;
        }
        if (!empty($ev['join_view']) && $n <= 2 && $span <= 1) {
            $auto++; $reasons['auto']['opened the join form and nothing else, instantly'] = ($reasons['auto']['opened the join form and nothing else, instantly'] ?? 0) + 1;
            $autoSessions[$j] = true;
            continue;
        }

        /* Everything else, which is mostly one page and nothing after it. A bored person and a
           polite crawler are the same row here and there is no honest way to split them. */
        $unsure++;
    }

    /* Where sessions landed, split the same way. Destination pages are the only landing we can
       name, because that is the only page type that records which one it was. */
    $landingHuman = [];
    $landingAuto = [];
    $landingUnsure = [];
    foreach (q_all("SELECT e.journey, d.slug, COUNT(*) n
                      FROM contribution_events e JOIN destinations d ON d.id = e.destination_id
                     WHERE e.created_at >= ? AND e.event = 'destination_page_view'
                       AND e.journey IS NOT NULL AND e.journey <> ''
                  GROUP BY e.journey, d.slug", [$since]) as $r) {
        $slug = (string) $r['slug'];
        $j = (string) $r['journey'];
        if (isset($humanSessions[$j]))     $landingHuman[$slug] = ($landingHuman[$slug] ?? 0) + 1;
        elseif (isset($autoSessions[$j]))  $landingAuto[$slug]  = ($landingAuto[$slug] ?? 0) + 1;
        else                               $landingUnsure[$slug] = ($landingUnsure[$slug] ?? 0) + 1;
    }
    arsort($landingHuman); arsort($landingAuto); arsort($landingUnsure);

    /* The hour of the day each session started, in UTC. A day with no night in it is a machine:
       human traffic has a shape, automated traffic is flat. */
    $byHour = array_fill(0, 24, 0);
    foreach ($rows as $r) {
        $h = (int) date('G', (int) strtotime((string) $r['first_at']));
        $byHour[$h]++;
    }

    /* How many sessions each event appears in, which is the shape of the whole window in one list. */
    $eventTotals = [];
    foreach (q_all("SELECT event, COUNT(DISTINCT journey) sessions, COUNT(*) rows_n
                      FROM contribution_events WHERE created_at >= ? GROUP BY event", [$since]) as $r) {
        $eventTotals[(string) $r['event']] = ['sessions' => (int) $r['sessions'], 'rows' => (int) $r['rows_n']];
    }
    arsort($eventTotals);

    /* Sessions against browsers. The visitor token only exists since migration 090, so this says
       nothing about the older part of the window and the response says so rather than implying a
       number it cannot support. */
    $visitor = q_one("SELECT COUNT(DISTINCT visitor) v, COUNT(DISTINCT journey) j, MIN(created_at) since
                        FROM contribution_events
                       WHERE created_at >= ? AND visitor IS NOT NULL AND visitor <> ''", [$since]);

    $window = q_one("SELECT MIN(created_at) first_at, MAX(created_at) last_at, COUNT(*) rows_n
                       FROM contribution_events WHERE created_at >= ?", [$since]);
    $days_span = 0.0;
    if ($window && $window['first_at'] && $window['last_at']) {
        $days_span = max(0.01, (strtotime((string) $window['last_at']) - strtotime((string) $window['first_at'])) / 86400);
    }

    echo json_encode([
        'note' => 'Read only. The event table holds no IP, no user agent and no referrer, so this '
                . 'classifies by session shape alone and cannot name a crawler or a traffic source.',
        'window' => ['first_event' => $window['first_at'] ?? null, 'last_event' => $window['last_at'] ?? null,
                     'days' => round($days_span, 2), 'event_rows' => (int) ($window['rows_n'] ?? 0)],
        'sessions' => [
            'total' => count($rows),
            'likely_human' => $human,
            'likely_automated' => $auto,
            'uncertain' => $unsure,
            'human_per_day' => $days_span > 0 ? round($human / $days_span, 1) : null,
        ],
        'why' => $reasons,
        'shape' => ['session_span' => $spanBuckets, 'events_per_session' => $eventCountBuckets],
        'landing_destinations' => [
            'human' => array_slice($landingHuman, 0, 10, true),
            'automated' => array_slice($landingAuto, 0, 10, true),
            'uncertain' => array_slice($landingUnsure, 0, 10, true),
        ],
        'sessions_starting_by_hour_utc' => $byHour,
        'events' => $eventTotals,
        'browsers' => ['distinct_visitor_tokens' => (int) ($visitor['v'] ?? 0),
                       'sessions_with_a_token' => (int) ($visitor['j'] ?? 0),
                       'token_data_since' => $visitor['since'] ?? null],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
}
