<?php
declare(strict_types=1);

/**
 * How far members actually get, counted from rows that already exist.
 *
 * This is deliberately NOT a tracker. Every number below is derived from the thing itself: a signup
 * is a user row, a confirmation is a timestamp on it, a first trip is a trip that member wrote.
 * Nothing new is recorded about anybody to produce any of it, which has two consequences worth
 * stating: it cannot drift out of agreement with the product the way an event log can, and there is
 * no behavioural history here to leak, because none was ever kept.
 *
 * The top of the funnel is the one thing rows cannot answer, since a visit leaves no row, so that
 * single number comes from the aggregate landing_view event in contribution_events. Everything from
 * "signed up" onward is counted from the product's own data.
 *
 * It answers one question, which is the only one worth asking before inviting anybody:
 *
 *   arrived, signed up, confirmed, made a trip, did something useful, came back
 *
 * Counts and rates only. No names, no per-person journeys, no timelines. That eleven of nineteen
 * members confirmed their address is a fact about the product. Which eleven is a fact about eleven
 * people, and this report has no reason to hold it.
 */

/**
 * Members who reached each step, and the rate at which they got there.
 *
 * @param int $days how far back the cohort goes. 0 means every member ever.
 * @return array<string,mixed>
 */
function rmt_growth_funnel(int $days = 0): array {
    $since = $days > 0 ? date('Y-m-d H:i:s', time() - $days * 86400) : '0000-01-01 00:00:00';
    $ed    = defined('RMT_EDITORIAL_ROLE') ? RMT_EDITORIAL_ROLE : 'editorial';

    /* The cohort, fixed once: real members who joined inside the window. Editorial accounts are
       excluded everywhere, because counting ourselves as a converted visitor is how a funnel starts
       lying to the person reading it. */
    $cohort = "u.role <> ? AND u.status = 'active' AND u.created_at >= ?";

    /** One member count over that cohort. Extra args come after the two the cohort needs. */
    $n = static function (string $extra, array $args = []) use ($cohort, $ed, $since): int {
        $sql = "SELECT COUNT(*) c FROM users u WHERE $cohort" . ($extra !== '' ? " AND ($extra)" : '');
        return (int) (q_one($sql, array_merge([$ed, $since], $args))['c'] ?? 0);
    };

    /* "A later day than they joined" rather than "a later timestamp". Saving a place ninety seconds
       after signing up is the same visit; doing it the next morning is somebody who decided this was
       worth coming back to, which is the only version of retention worth reporting at this size.
       SUBSTR over a cast is the comparison SQLite and Postgres both agree on. */
    $laterDay = static fn(string $t): string =>
        "SUBSTR(CAST($t.created_at AS TEXT),1,10) > SUBSTR(CAST(u.created_at AS TEXT),1,10)";

    $members    = $n('');
    $confirmed  = $n('u.email_verified_at IS NOT NULL');

    $trip       = $n("EXISTS (SELECT 1 FROM trips t WHERE t.user_id = u.id AND t.status = 'published')");
    $savedPlace = $n("EXISTS (SELECT 1 FROM saves s WHERE s.user_id = u.id AND s.target_type = 'place')");
    $plan       = $n("EXISTS (SELECT 1 FROM trip_activities a WHERE a.user_id = u.id AND a.status = 'published')");
    $followed   = $n("EXISTS (SELECT 1 FROM follows f WHERE f.follower_id = u.id)");
    $messaged   = $n("EXISTS (SELECT 1 FROM messages m WHERE m.sender_id = u.id)");
    $joined     = $n("EXISTS (SELECT 1 FROM activity_joins j WHERE j.user_id = u.id)");
    $photo      = $n("EXISTS (SELECT 1 FROM trip_photos p WHERE p.user_id = u.id)
                   OR EXISTS (SELECT 1 FROM activity_photos p WHERE p.user_id = u.id)
                   OR EXISTS (SELECT 1 FROM review_photos p WHERE p.user_id = u.id)");

    /* Anything that counts as having got value out of the site, deliberately a list rather than one
       action, because planning does not have a single shape. Somebody who saved four places got
       something out of this even if they never wrote a plan, and so did somebody who scheduled a
       dinner without saving anything. */
    $useful = $n("EXISTS (SELECT 1 FROM trip_activities a WHERE a.user_id = u.id AND a.status = 'published')
               OR EXISTS (SELECT 1 FROM saves s WHERE s.user_id = u.id)");

    $social = $n("EXISTS (SELECT 1 FROM follows f WHERE f.follower_id = u.id)
               OR EXISTS (SELECT 1 FROM messages m WHERE m.sender_id = u.id)
               OR EXISTS (SELECT 1 FROM activity_joins j WHERE j.user_id = u.id)");

    $returned = $n("EXISTS (SELECT 1 FROM trips t WHERE t.user_id = u.id AND " . $laterDay('t') . ")
                 OR EXISTS (SELECT 1 FROM trip_activities a WHERE a.user_id = u.id AND " . $laterDay('a') . ")
                 OR EXISTS (SELECT 1 FROM saves s WHERE s.user_id = u.id AND " . $laterDay('s') . ")
                 OR EXISTS (SELECT 1 FROM messages m WHERE m.sender_id = u.id AND " . $laterDay('m') . ")");

    /* Visits: the one number with no row behind it, so the one number that comes from the event
       table, counted per journey rather than per request because a reload is not a visitor. It also
       counts crawlers, which is why it is labelled as arrivals and not quietly trusted as people. */
    $visits = 0;
    $countingSince = null;
    try {
        $row = q_one("SELECT COUNT(DISTINCT COALESCE(journey, CAST(id AS TEXT))) c, MIN(created_at) first
                        FROM contribution_events
                       WHERE event = 'landing_view' AND created_at >= ?", [$since]);
        $visits = (int) ($row['c'] ?? 0);
        $countingSince = $row['first'] ?? null;
    } catch (Throwable $e) {
        $visits = 0;      // before the migration, or on a database that has never seen a visit
    }

    /* Whether the arrival counter actually covers the window being reported.
       It usually will not on the first day it exists, and a rate computed across two different
       spans of time is worse than no rate: nine arrivals against four members who joined over a
       month reads as 44% conversion and means nothing at all. So the rate is withheld until the
       counter has been running for the whole window, and the page says when counting began. */
    $covers = $countingSince !== null
           && ($days === 0 ? false : (string) $countingSince <= $since);

    $pct = static fn(int $a, int $b): ?float => $b > 0 ? round($a * 100 / $b, 1) : null;

    return [
        'days'    => $days,
        'visits'  => $visits,
        'members' => $members,
        /* When the arrival counter started, and whether it covers the window. A rate the
           report cannot honestly compute is left out rather than estimated. */
        'arrivals_since'   => $countingSince,
        'arrivals_cover'   => $covers,
        /* The spine. Each step as a share of the step above it, because a funnel read as a share of
           the top hides which single step is the broken one. */
        'spine'   => [
            ['label' => 'Arrived on the front page',  'n' => $visits,    'of' => null],
            ['label' => 'Signed up',                  'n' => $members,   'of' => $covers ? $pct($members, $visits) : null],
            ['label' => 'Confirmed their email',      'n' => $confirmed, 'of' => $pct($confirmed, $members)],
            ['label' => 'Wrote a trip',               'n' => $trip,      'of' => $pct($trip, $members)],
            ['label' => 'Planned or saved something', 'n' => $useful,    'of' => $pct($useful, $members)],
            ['label' => 'Came back another day',      'n' => $returned,  'of' => $pct($returned, $members)],
        ],
        /* The first of each specific thing, as a share of members. Not a sequence: nobody does these
           in this order, and drawing them as a ladder would invent a drop-off that is not there. */
        'firsts'  => [
            ['label' => 'Wrote a trip',            'n' => $trip,       'of' => $pct($trip, $members)],
            ['label' => 'Saved a place',           'n' => $savedPlace, 'of' => $pct($savedPlace, $members)],
            ['label' => 'Added something to a plan', 'n' => $plan,     'of' => $pct($plan, $members)],
            ['label' => 'Followed a traveler',     'n' => $followed,   'of' => $pct($followed, $members)],
            ['label' => 'Sent a message',          'n' => $messaged,   'of' => $pct($messaged, $members)],
            ['label' => 'Asked to join something', 'n' => $joined,     'of' => $pct($joined, $members)],
            ['label' => 'Uploaded a photo',        'n' => $photo,      'of' => $pct($photo, $members)],
            ['label' => 'Did anything social',     'n' => $social,     'of' => $pct($social, $members)],
        ],
    ];
}

/**
 * Whether the network has started working, counted from trips rather than from tracking.
 *
 * Two aggregate events, and the order matters because the second is only meaningful after the
 * first:
 *
 *   NETWORK ACTIVATION  a member has a trip whose dates overlap another member's trip in the same
 *                       city. That member could see a real traveler. It is the moment this site
 *                       stops being a notebook.
 *   SOCIAL ACTIVATION   a member who reached the first one then followed, messaged or asked to
 *                       join something. It is the moment the site stops being a directory.
 *
 * Both are COUNTS of members, never pairs. Who overlaps with whom is exactly the fact a traveler
 * would not want sitting in an analytics table, and a count cannot hold it. There is no event
 * recorded for either: overlap is a property of two trip rows and is recomputed every time this is
 * read, so it cannot drift and there is nothing to leak.
 *
 * Only upcoming trips count. Two people who were in Lisbon in the same week last March did not
 * meet and cannot now.
 *
 * @return array<string,int>
 */
function rmt_growth_overlap(): array {
    $today = date('Y-m-d');
    $ed = defined('RMT_EDITORIAL_ROLE') ? RMT_EDITORIAL_ROLE : 'editorial';

    /* The overlap condition itself, written once: same city, different traveler, and date ranges
       that touch. Standard interval intersection, which is A starting before B ends and B starting
       before A ends. A trip with no dates cannot overlap anything and is excluded rather than
       treated as matching everybody. */
    $overlaps = "EXISTS (
        SELECT 1 FROM trips o
          JOIN users ou ON ou.id = o.user_id
         WHERE o.destination_id = t.destination_id
           AND o.user_id <> t.user_id
           AND o.status = 'published' AND ou.status = 'active' AND ou.role <> ?
           AND o.date_from IS NOT NULL AND o.date_to IS NOT NULL
           AND o.date_from <= t.date_to AND t.date_from <= o.date_to
           AND o.date_to >= ?)";

    $liveTrip = "t.status = 'published' AND t.date_from IS NOT NULL AND t.date_to IS NOT NULL
                 AND t.date_to >= ?";

    $one = static fn(string $sql, array $a): int => (int) (q_one($sql, $a)['c'] ?? 0);

    return [
        /* Upcoming trips that have somebody else in the same city on the same days. */
        'trips_with_overlap' => $one(
            "SELECT COUNT(*) c FROM trips t
               JOIN users u ON u.id = t.user_id AND u.status = 'active' AND u.role <> ?
              WHERE $liveTrip AND $overlaps", [$ed, $today, $ed, $today]),

        /* Members who could see a real traveler. The number that decides whether any of the social
           half of this product does anything at all. */
        'network_activated' => $one(
            "SELECT COUNT(DISTINCT t.user_id) c FROM trips t
               JOIN users u ON u.id = t.user_id AND u.status = 'active' AND u.role <> ?
              WHERE $liveTrip AND $overlaps", [$ed, $today, $ed, $today]),

        /* ...and then did something about it. */
        'social_activated' => $one(
            "SELECT COUNT(DISTINCT t.user_id) c FROM trips t
               JOIN users u ON u.id = t.user_id AND u.status = 'active' AND u.role <> ?
              WHERE $liveTrip AND $overlaps
                AND (EXISTS (SELECT 1 FROM follows f WHERE f.follower_id = t.user_id)
                  OR EXISTS (SELECT 1 FROM messages m WHERE m.sender_id = t.user_id)
                  OR EXISTS (SELECT 1 FROM activity_joins j WHERE j.user_id = t.user_id))",
            [$ed, $today, $ed, $today]),

        /* Cities where meeting somebody is currently possible at all. Going from 0 to 1 is the
           first result that matters more than any signup count. */
        'cities_with_overlap' => $one(
            "SELECT COUNT(DISTINCT t.destination_id) c FROM trips t
               JOIN users u ON u.id = t.user_id AND u.status = 'active' AND u.role <> ?
              WHERE $liveTrip AND $overlaps", [$ed, $today, $ed, $today]),

        /* Upcoming trips with dates at all, as the denominator for the three above: overlap that
           is low because nobody entered dates is a different problem from overlap that is low
           because nobody else is going. */
        'dated_upcoming_trips' => $one(
            "SELECT COUNT(*) c FROM trips t
               JOIN users u ON u.id = t.user_id AND u.status = 'active' AND u.role <> ?
              WHERE $liveTrip", [$ed, $today]),
    ];
}

/**
 * What there is to arrive for.
 *
 * Separate from the funnel on purpose. The funnel says how people move through the product; this
 * says whether the product currently holds anything worth moving through, which at this size is the
 * more honest of the two. cities_with_overlap is the number that decides whether the social half of
 * this site does anything at all: a city with one traveler in it is a directory.
 *
 * @return array<string,int>
 */
function rmt_growth_inventory(): array {
    $today = date('Y-m-d');
    $one = static fn(string $sql, array $a = []): int => (int) (q_one($sql, $a)['c'] ?? 0);
    return [
        'cities'         => $one('SELECT COUNT(*) c FROM destinations'),
        'places'         => $one("SELECT COUNT(*) c FROM places WHERE status = 'active'"),
        'public_trips'   => $one("SELECT COUNT(*) c FROM trips WHERE status = 'published'
                                    AND COALESCE(visibility,'public') = 'public'"),
        'upcoming_trips' => $one("SELECT COUNT(*) c FROM trips WHERE status = 'published'
                                    AND date_to >= ?", [$today]),
        'open_plans'     => $one("SELECT COUNT(*) c FROM trip_activities
                                   WHERE status = 'published' AND cancelled_at IS NULL"),
        'cities_with_overlap' => $one("SELECT COUNT(*) c FROM (
                                         SELECT t.destination_id FROM trips t
                                          WHERE t.status = 'published' AND t.date_to >= ?
                                          GROUP BY t.destination_id
                                         HAVING COUNT(DISTINCT t.user_id) > 1) x", [$today]),
    ];
}

/**
 * GET /cron/funnel?key=...&days=30
 *
 * The funnel as JSON, for reading production without an admin session.
 *
 * It exists because the numbers had to be checked against a real signup on the live site, and the
 * only alternative was signing in as an administrator from a script. Aggregates only, which is the
 * same thing /admin/funnel shows: this endpoint cannot name a member because the functions behind
 * it never learn a name.
 */
function cron_funnel(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = (string) input('key');
    if ($key === '' || $given === '' || !hash_equals($key, $given)) not_found();

    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');

    $days = (int) (input('days') !== '' ? input('days') : 30);
    echo json_encode([
        'growth'    => rmt_growth_funnel($days),
        'signup'    => rmt_signup_funnel($days),
        'inventory' => rmt_growth_inventory(),
        'overlap'   => rmt_growth_overlap(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
}
