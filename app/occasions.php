<?php
declare(strict_types=1);

/**
 * Occasions: a reason a lot of travelers are in one place at one time (2026-10-01).
 *
 * The site's matching is city plus overlapping dates. An occasion is a named window on top of that:
 * a festival, a convention, a sporting event, a cruise sailing, a season, a backpacking circuit. It
 * owns no members and no rows of its own. Who is going is read live from the same trips and buddy
 * posts everything else reads (public, real members, overlapping the window), so a traveler who
 * posts Chiang Mai 22 to 27 November is on the Yi Peng page without doing anything else, and the
 * alerts they get are the city alerts they already get.
 *
 * The registry is code, like the buddy landing pages: each entry is written and sourced by hand and
 * reviewed in a diff. A page stays out of the index and the sitemap until a real member is on it
 * (the same rule as the city travelers page): an occasion page with nobody on it is a template.
 *
 * Kinds: festival, event, convention, sports, cruise, seasonal, backpacking, solo_city. A cruise
 * occasion names a ship and a sail date; its people are the buddy posts on that sailing.
 */

const RMT_OCCASION_KINDS = [
    'festival' => 'Festival', 'event' => 'Event', 'convention' => 'Convention', 'sports' => 'Sporting event',
    'cruise' => 'Cruise sailing', 'seasonal' => 'Season', 'backpacking' => 'Backpacking circuit', 'solo_city' => 'Solo travel city',
];

const RMT_OCCASIONS = [
    'yi-peng-2026' => [
        'kind' => 'festival', 'name' => 'Yi Peng and Loy Krathong 2026', 'short' => 'Yi Peng',
        'dest' => 'chiang-mai-thailand', 'from' => '2026-11-23', 'to' => '2026-11-26',
        'lede' => 'Chiang Mai\'s lantern festival. Loy Krathong falls on the full moon of the twelfth Thai lunar month, Tuesday 24 November 2026, and the city\'s Yi Peng celebrations run around it.',
        'facts' => [
            'The full moon night is Tuesday 24 November 2026. Published 2026 schedules for Chiang Mai range from 23 to 26 November, and the final official program comes from TAT Chiang Mai close to the date, so check it before you book.',
            'The big mass lantern releases are ticketed events held outside the old city. Getting there and back at night is the part people most often want company for.',
            'Rooms in the old city fill early for the full moon nights, so people who are flexible on dates often stay a night either side.',
        ],
        'sources' => [
            ['Loy Krathong (lunar calendar rule)', 'https://en.wikipedia.org/wiki/Loy_Krathong'],
            ['Yi Peng and Loy Krathong 2026 dates', 'https://www.beautiful-chiangmai.com/festivals/yi-peng-loy-krathong/'],
        ],
        'checked' => '2026-10-01',
    ],
    'day-of-the-dead-2026' => [
        'kind' => 'festival', 'name' => 'Day of the Dead in Oaxaca 2026', 'short' => 'Day of the Dead',
        'dest' => 'oaxaca-mexico', 'from' => '2026-10-31', 'to' => '2026-11-02',
        'lede' => 'Día de Muertos is fixed by the calendar on 1 and 2 November; in Oaxaca the vigils and street processions begin on the 31st.',
        'facts' => [
            'The cemetery vigils happen after dark, often outside the city. Going with other people is the usual advice.',
            'Oaxaca is one of the busiest places in Mexico that week; book rooms well ahead.',
        ],
        'sources' => [['Day of the Dead', 'https://en.wikipedia.org/wiki/Day_of_the_Dead']],
        'checked' => '2026-10-01',
    ],
    'web-summit-2026' => [
        'kind' => 'convention', 'name' => 'Web Summit 2026, Lisbon', 'short' => 'Web Summit',
        'dest' => 'lisbon-portugal', 'from' => '2026-11-09', 'to' => '2026-11-12',
        'lede' => 'Web Summit runs 9 to 12 November 2026 in Lisbon.',
        'facts' => [
            'The conference app matches attendees during the event. Nothing matches you for the days either side, which is where RuinMyTrip comes in.',
        ],
        'sources' => [['Web Summit', 'https://websummit.com/']],
        'checked' => '2026-10-01',
    ],
    'new-year-bangkok-2027' => [
        'kind' => 'seasonal', 'name' => 'New Year in Bangkok 2026 to 2027', 'short' => 'New Year',
        'dest' => 'bangkok-thailand', 'from' => '2026-12-27', 'to' => '2027-01-02',
        'lede' => 'The week from 27 December to 2 January is one of the busiest of the year in Bangkok and the peak of the Southeast Asia travel season.',
        'facts' => [
            'Countdown events along the river draw large crowds; plan how you get home before midnight.',
        ],
        'sources' => [],
        'checked' => '2026-10-01',
    ],
    'rio-carnival-2027' => [
        'kind' => 'festival', 'name' => 'Rio Carnival 2027', 'short' => 'Carnival',
        'dest' => 'rio-de-janeiro-brazil', 'from' => '2027-02-05', 'to' => '2027-02-13',
        'lede' => 'Ash Wednesday falls on 10 February 2027, so Carnival weekend is 6 to 9 February, with the street blocos starting before it and the champions parade after.',
        'facts' => [
            'Blocos are free street parties with no ticket and no door. Which one, on which morning, and with whom, is the whole question.',
            'The Sambadrome parades are ticketed; the blocos are not.',
        ],
        'sources' => [['Rio Carnival', 'https://en.wikipedia.org/wiki/Rio_Carnival']],
        'checked' => '2026-10-01',
    ],
];

/** One occasion with its city attached, or null. */
function rmt_occasion(string $slug): ?array {
    $o = RMT_OCCASIONS[$slug] ?? null;
    if (!$o) return null;
    $d = q_one('SELECT id, slug, name, country, hero_url FROM destinations WHERE slug = ?', [$o['dest']]);
    if (!$d) return null;
    return $o + ['slug' => $slug, 'd' => $d];
}

/** Every occasion that has not finished, soonest first, with its city. */
function rmt_occasions_upcoming(): array {
    $out = [];
    foreach (array_keys(RMT_OCCASIONS) as $slug) {
        $o = RMT_OCCASIONS[$slug];
        if ($o['to'] < date('Y-m-d')) continue;
        if ($x = rmt_occasion($slug)) $out[] = $x;
    }
    usort($out, static fn(array $a, array $b): int => strcmp($a['from'], $b['from']));
    return $out;
}

/** The occasion page for a city, if one is open, so the city page can point at it. */
function rmt_occasion_for_city(string $destSlug): ?array {
    // The registry is filtered first so a city page with no occasion costs no query at all.
    $best = null;
    foreach (RMT_OCCASIONS as $slug => $o) {
        if ($o['dest'] !== $destSlug || $o['to'] < date('Y-m-d')) continue;
        if ($best === null || $o['from'] < RMT_OCCASIONS[$best]['from']) $best = $slug;
    }
    return $best !== null ? rmt_occasion($best) : null;
}

/**
 * Real members whose public trips or open buddy posts overlap the window, newest dates first.
 * The same visibility as everywhere: public, published, active, not a house or editorial account.
 */
function rmt_occasion_people(array $o, ?array $viewer = null, int $limit = 24): array {
    $real = function_exists('rmt_sc_real_user_sql') ? rmt_sc_real_user_sql('u')
          : "u.status = 'active' AND SUBSTR(u.username, 1, 5) <> 'team_'";
    $rows = [];
    try {
        $rows = q_all("SELECT 'trip' kind, t.id, t.date_from, t.date_to, u.id user_id, u.username, p.avatar_url
                         FROM trips t JOIN users u ON u.id = t.user_id LEFT JOIN profiles p ON p.user_id = u.id
                        WHERE t.destination_id = ? AND t.status = 'published' AND t.visibility = 'public'
                          AND t.date_from IS NOT NULL AND t.date_from <= ? AND t.date_to >= ? AND $real
                        ORDER BY t.date_from LIMIT " . (int) $limit,
                       [(int) $o['d']['id'], $o['to'], $o['from']]);
    } catch (Throwable $e) { $rows = []; }
    try {
        $rows = array_merge($rows, q_all("SELECT 'buddy' kind, b.id, b.date_from, b.date_to, u.id user_id, u.username, p.avatar_url
                         FROM buddy_posts b JOIN users u ON u.id = b.user_id LEFT JOIN profiles p ON p.user_id = u.id
                        WHERE b.destination_id = ? AND b.status = 'open' AND b.date_from <= ? AND b.date_to >= ? AND $real
                        ORDER BY b.date_from LIMIT " . (int) $limit,
                       [(int) $o['d']['id'], $o['to'], $o['from']]));
    } catch (Throwable $e) { /* no buddy table */ }
    if ($viewer && function_exists('rmt_without_blocked')) $rows = rmt_without_blocked($rows, (int) $viewer['id']);
    return $rows;
}

/** Index and submit an occasion page only once somebody real is on it. */
function rmt_occasion_indexable(array $o): bool {
    return count(rmt_occasion_people($o, null, 1)) > 0;
}

/** Links that open the existing flows with the occasion's city and window already filled in. */
function rmt_occasion_links(array $o): array {
    $slug = (string) $o['d']['slug'];
    return [
        'trip'   => url('plan?' . http_build_query(['d' => $slug, 'from' => $o['from'], 'to' => $o['to'], 'cta' => 'occ_trip'])),
        'find'   => url('buddies?' . http_build_query(['where' => $o['d']['name'], 'from' => $o['from'], 'to' => $o['to']])),
        'ask'    => url('d/' . $slug) . '#city-ask',
        'review' => url('review/new?destination=' . (int) $o['d']['id']),
        'ruined' => url('ruined'),
        'city'   => url('d/' . $slug),
        'people' => url('d/' . $slug . '/travelers'),
    ];
}

/* ---------- controller ---------- */

/** GET /e/{slug} */
function occasion_show(array $a): void {
    $o = rmt_occasion((string) ($a['slug'] ?? ''));
    if (!$o) not_found();
    $me = current_user();
    rmt_track_once_for('destination_page_view', 'occasion:' . $o['slug'],
                       ['source' => 'occasion', 'destination_id' => (int) $o['d']['id']]);
    $people = rmt_occasion_people($o, $me);
    $talk = function_exists('rmt_posts_recent') ? rmt_posts_recent(6, (int) $o['d']['id']) : [];
    $saved = $me ? (bool) q_one("SELECT 1 FROM saves WHERE user_id=? AND target_type='destination' AND target_id=?",
                               [(int) $me['id'], (int) $o['d']['id']]) : false;
    $dates = date('j F', strtotime($o['from'])) . ' to ' . date('j F Y', strtotime($o['to']));
    $robots = $people ? 'index, follow' : 'noindex,follow';
    view('occasion', ['o' => $o, 'people' => $people, 'talk' => $talk, 'me' => $me, 'saved' => $saved,
                      'dates' => $dates, 'links' => rmt_occasion_links($o)], [
        'title' => $o['name'] . ': who is going, travel buddies and meetups | RuinMyTrip',
        'description' => $o['short'] . ' in ' . $o['d']['name'] . ', ' . $dates . '. See which travelers are going on the same days, '
                       . 'find a buddy for the night out, ask people who have been, and add your own dates.',
        'robots' => $robots,
        'og_image' => function_exists('rmt_card_url') ? rmt_card_url('city', (string) $o['d']['slug']) : rmt_default_og_image(),
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Events', 'url' => url('events')],
                          ['name' => $o['short'], 'url' => url('e/' . $o['slug'])]],
        'jsonld' => jsonld(['@context' => 'https://schema.org', '@type' => 'Event', 'name' => $o['name'],
            'startDate' => $o['from'], 'endDate' => $o['to'],
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'location' => ['@type' => 'Place', 'name' => $o['d']['name'],
                           'address' => ['@type' => 'PostalAddress', 'addressLocality' => $o['d']['name'], 'addressCountry' => $o['d']['country']]],
            'description' => $o['lede'], 'url' => url('e/' . $o['slug'])]),
    ]);
}
