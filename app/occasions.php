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

/* Slugs carry no year (a dated URL reads as stale the day after); the edition is a field. Pages that
   went out under a dated slug answer with a 301 to the evergreen one. */
const RMT_OCCASION_OLD_SLUGS = [
    'yi-peng-2026' => 'yi-peng-chiang-mai', 'day-of-the-dead-2026' => 'day-of-the-dead-oaxaca',
    'web-summit-2026' => 'web-summit-lisbon', 'new-year-bangkok-2027' => 'new-year-bangkok',
    'rio-carnival-2027' => 'rio-carnival',
];

/*
 * The first three ecosystems (2026-10-01) carry a full guide: what happens, what is confirmed and
 * what is not yet, getting there, staying, what travelers wish they knew, and sources. Every fact was
 * checked against the source named beside it on the date in 'checked'; anything not published yet
 * for this edition is said to be not published yet rather than estimated. A page with a guide that
 * clears rmt_occasion_quality() may be indexed before anybody is on it, because it is useful on its
 * own. A page without one stays out of the index until a real member is.
 */
const RMT_OCCASIONS = [
    'yi-peng-chiang-mai' => [
        'kind' => 'festival', 'name' => 'Yi Peng and Loy Krathong in Chiang Mai 2026', 'short' => 'Yi Peng', 'edition' => '2026',
        'dest' => 'chiang-mai-thailand', 'from' => '2026-11-22', 'to' => '2026-11-26',
        'when' => 'Full moon night: Tuesday 24 November 2026',
        'card' => 'Full moon: Tuesday 24 November 2026',
        'meta' => 'Yi Peng in Chiang Mai, 2026: the full moon falls on Tuesday 24 November. What is confirmed so far, what travelers wish they knew, and who else is going on your dates.',
        'lede' => 'Yi Peng is Chiang Mai\'s lantern festival, held together with Loy Krathong on the full moon of the twelfth Thai lunar month. In 2026 that full moon falls on Tuesday 24 November. This page is for the people going: what is confirmed, what is not yet, and who else will be there.',
        'facts' => [
            'Loy Krathong 2026 is on Tuesday 24 November, the full moon of the twelfth Thai lunar month. Yi Peng is the Lanna festival of the same full moon, and in Chiang Mai the two are celebrated together.',
            'As of 1 October 2026 no official 2026 program from the Tourism Authority of Thailand or Chiang Mai municipality had been published. Parade and event times in other guides are estimates until it is.',
            'The best known ticketed mass release, CAD Khomloy, is being sold for the nights of 24 and 25 November 2026 by its ticket agent, at roughly 4,800 to 15,500 baht depending on the package.',
        ],
        'guide' => [
            ['What actually happens', [
                'Two things happen on the same nights. Krathong, small floats of banana leaf, flowers and a candle, are set on the water, which in Chiang Mai means the Ping River. Khom loi, the paper sky lanterns, are the Yi Peng part, and they are the picture everybody has seen.',
                'That sea of lanterns is not a free event in the middle of town. It comes from ticketed releases held outside the city. In town, the free celebration gathers around Tha Phae Gate, the Three Kings Monument, Nawarat Bridge and along the Ping River, where people float their krathong.',
            ]],
            ['Lantern rules change every year', [
                'Releasing a lantern is not a free for all. In 2025 the province allowed releases only from 7 pm to 1 am on the two festival nights and only in approved zones, and it named red zones where releases were banned outright, with Mueang Chiang Mai, the city district itself, among them. The penalty quoted was up to five years in prison or a fine of up to 200,000 baht.',
                'The 2026 rules had not been announced when this page was checked. Until they are, do not plan on releasing a lantern from a guesthouse roof in the old city. Floating a krathong on the river is the older tradition and the part you can join anywhere along the water.',
            ]],
            ['Flights on the festival nights', [
                'Lanterns and aircraft do not mix. In 2025 Chiang Mai airport stopped all flights by 7 pm on the two festival nights, and 161 flights were cancelled or moved. The 2026 schedule had not been announced when this page was checked. If you fly in or out around 24 November, pick a daytime flight and expect it to be changed.',
            ]],
            ['After the festival: high season', [
                'The festival opens Chiang Mai\'s cool season, roughly November to February, when the city is at its busiest with travelers and remote workers. That makes late November an easy time to find people for a trip up to Pai, a cooking class or a day in the mountains.',
                'The season to avoid is the burning season. From around February to April smoke from crop burning hangs over the north, and in early 2026 PM2.5 readings above 300 micrograms per cubic meter were recorded in some districts.',
            ]],
            ['Why go with company', [
                'The ticketed releases are out of town and end late. Shared transport back, somebody to keep your place by Tha Phae Gate, somebody who went last year and knows which package was worth it: that is what the people on this page are for. Add your dates and you are matched with anyone else in Chiang Mai between 22 and 26 November.',
            ]],
        ],
        'warnings' => [
            'A lantern ticket is expensive and out of town. Check what the package includes: transport, food, how many lanterns, and what time you get back.',
            'The famous photos come from ticketed events. The free celebrations in town are lovely, but they look different.',
            'Do not release a lantern wherever you like. In 2025 the city district was a no release zone with heavy fines.',
            'Evening flights on the festival nights were cancelled in 2025. Fly in during the day.',
        ],
        'faq' => [
            ['When is Yi Peng 2026?', 'The full moon night is Tuesday 24 November 2026. Celebrations in Chiang Mai run around it. The official 2026 program had not been published as of 1 October 2026.'],
            ['Can I release a lantern in the old city?', 'In 2025 the city district, Mueang Chiang Mai, was a red zone where releases were banned, and releases elsewhere were limited to approved zones from 7 pm to 1 am. Check the 2026 rules when they are announced.'],
            ['How do I find people to go with?', 'Add your Chiang Mai dates on this page, or leave your email for a match alert. When anybody posts dates that overlap yours, you hear about it.'],
        ],
        'sources' => [
            ['Loy Krathong: 2026 date and lunar rule (Wikipedia)', 'https://en.wikipedia.org/wiki/Loy_Krathong'],
            ['CAD Khomloy 2026 nights and prices (ticket agent)', 'https://www.chiangmaiyipengfestival.com/faq'],
            ['2025 airport schedule (Nation Thailand)', 'https://www.nationthailand.com/news/tourism/40057614'],
            ['2025 lantern red zones (The Thaiger)', 'https://thethaiger.com/news/chiang-mai/chiang-mai-bans-lanterns-in-red-zones-during-yi-peng-festival'],
            ['2026 Chiang Mai smog (Wikipedia)', 'https://en.wikipedia.org/wiki/2026_Chiang_Mai_Smog'],
        ],
        'checked' => '2026-10-01',
    ],
    'day-of-the-dead-oaxaca' => [
        'kind' => 'festival', 'name' => 'Day of the Dead in Oaxaca 2026', 'short' => 'Day of the Dead', 'edition' => '2026',
        'dest' => 'oaxaca-mexico', 'from' => '2026-10-31', 'to' => '2026-11-02',
        'when' => 'Cemetery vigils on the nights of 31 October and 1 November 2026',
        'card' => 'Vigils on 31 October and 1 November 2026',
        'meta' => 'Day of the Dead in Oaxaca, 2026: cemetery vigils on the nights of 31 October and 1 November. What to book now, what travelers wish they knew, and who else is going.',
        'lede' => 'Día de Muertos is when families welcome back their dead, and Oaxaca is where many travelers go to see it. The days are fixed: 1 November for children who have died, 2 November for adults, and around Oaxaca the cemetery vigils begin on the night of 31 October.',
        'facts' => [
            'Day of the Dead is on 1 and 2 November every year. All Saints\' Day, 1 November, is for children; All Souls\' Day, 2 November, is for adults. Some places begin on 31 October.',
            'In Santa Cruz Xoxocotlán, just south of Oaxaca city, vigils are held in the cemeteries on the nights of 31 October and 1 November, from about 5 or 6 in the evening until the next morning.',
            'As of 1 October 2026 the official 2026 program for Oaxaca city had not been published. Parade times and cemetery prices in other guides are last year\'s until it is.',
        ],
        'guide' => [
            ['What it is, and what it is not', [
                'This is not Halloween and it is not a show staged for visitors. It is families decorating graves and home altars, the ofrendas, with marigolds, candles, food and photographs, and keeping their dead company through the night. In many places visitors are welcome, as guests.',
                'Around that, the city fills with comparsas and muerteadas, costumed processions with a band, and with altars in squares, hotels and restaurants.',
            ]],
            ['The cemetery nights', [
                'The night most travelers come for is the vigil in the cemeteries of Santa Cruz Xoxocotlán, the town by the airport just south of the city. Vigils there are held on the nights of 31 October and 1 November and run from early evening into the next morning, and the crowds were already counted at more than 30,000 visitors in 2005.',
                'Because it is late and outside the center, decide before you go how you are getting back. This is the part of the week people most often want company for.',
            ]],
            ['How to behave in a cemetery', [
                'You are walking through somebody\'s family gathering. Ask before you photograph anyone, never use flash, keep your voice low and do not touch the offerings.',
            ]],
            ['Getting there', [
                'Oaxaca International Airport (OAX) is in Santa Cruz Xoxocotlán, just south of the city, with flights from both Mexico City airports, Benito Juárez and Felipe Ángeles. The flight takes about an hour to an hour and a half. By bus from Mexico City it is about six to seven hours.',
            ]],
            ['Where people stay', [
                'Most visitors stay in the historic Centro, within walking distance of the squares and the processions. Jalatlaco, a historic barrio about ten minutes northeast of the Centro, and Xochimilco, a quieter residential area to the north, are the usual alternatives. Whichever you choose, this is one of the busiest weeks of the year, so book early.',
            ]],
            ['Weather', [
                'Early November is the dry season. The 1991 to 2020 averages for November are a high of 29 °C and a low of about 12 °C, so expect warm afternoons and a cold night in the cemetery. Bring a layer.',
            ]],
            ['Why go with other people', [
                'Late nights, cemeteries outside town, and a lot of choice about which ones and when. Add your dates and you are matched with anyone else in Oaxaca between 31 October and 2 November, or leave your email and hear the moment somebody overlaps.',
            ]],
        ],
        'warnings' => [
            'The official 2026 program is not out yet. Do not trust parade times copied from last year.',
            'The cemetery vigils last all night and are outside the center. Plan your ride back before you go.',
            'Ask before photographing people and altars, and never use flash.',
            'It gets cold after dark in November. Bring a jacket for the cemetery.',
        ],
        'faq' => [
            ['When is Day of the Dead 2026 in Oaxaca?', 'The days themselves are Sunday 1 and Monday 2 November 2026. Around Oaxaca the cemetery vigils are held on the nights of 31 October and 1 November.'],
            ['Which night should I go to the cemetery?', 'In Santa Cruz Xoxocotlán vigils are held on both the night of 31 October and the night of 1 November, from early evening until the next morning.'],
            ['How do I find people to go with?', 'Add your Oaxaca dates on this page, or leave your email for a match alert. When anybody posts dates that overlap yours, you hear about it.'],
        ],
        'sources' => [
            ['Day of the Dead (Wikipedia)', 'https://en.wikipedia.org/wiki/Day_of_the_Dead'],
            ['Santa Cruz Xoxocotlán vigils (Wikipedia)', 'https://en.wikipedia.org/wiki/Santa_Cruz_Xoxocotl%C3%A1n'],
            ['Oaxaca International Airport (Wikipedia)', 'https://en.wikipedia.org/wiki/Oaxaca_International_Airport'],
            ['Mexico City to Oaxaca (Rough Guides)', 'https://www.roughguides.com/mexico/getting-around/mexico-city-to-oaxaca/'],
            ['Oaxaca climate (Wikipedia)', 'https://en.wikipedia.org/wiki/Oaxaca_City'],
            ['Cemetery etiquette (Oaxaca Culture)', 'https://oaxacaculture.com/2012/11/day-of-the-dead-night-in-xoxocotlan-cemetery-oaxaca/'],
        ],
        'checked' => '2026-10-01',
    ],
    'web-summit-lisbon' => [
        'kind' => 'convention', 'name' => 'Web Summit 2026 in Lisbon', 'short' => 'Web Summit', 'edition' => '2026',
        'dest' => 'lisbon-portugal', 'from' => '2026-11-09', 'to' => '2026-11-12',
        'when' => 'Monday 9 to Thursday 12 November 2026',
        'card' => '9 to 12 November 2026, MEO Arena',
        'meta' => 'Web Summit 2026 runs 9 to 12 November at the MEO Arena in Lisbon. Getting there from the airport by metro, the day off nobody plans, and who else is going alone.',
        'lede' => 'Web Summit runs from 9 to 12 November 2026 at the MEO Arena in Lisbon\'s Parque das Nações. Thousands of people fly in for four days knowing almost nobody in the city. This page is for the time around the talks: who else is in town, and what is worth knowing.',
        'facts' => [
            'Web Summit 2026 is 9 to 12 November at the MEO Arena, Parque das Nações. Night Summit, the evening program, is part of it; its 2026 venues and times come from Web Summit.',
            'The nearest metro is Oriente on the red line, a short walk from the arena, and the red line runs straight to the airport.',
            'Ticket prices rise by sales phase. On 1 October 2026 a General Attendee ticket was listed at €995 including tax.',
        ],
        'guide' => [
            ['Getting there and around', [
                'Lisbon airport is on the metro. The red line runs from Aeroporto to Oriente, the station beside the venue, so you can get from the plane to the arena without a taxi.',
                'For the rest of the city, the reusable navegante occasional card costs €0.50, and a 24 hour ticket covering Carris buses and trams, the metro and CP trains costs €11.40.',
            ]],
            ['Where to stay', [
                'Parque das Nações puts you next to the venue. The old center puts you next to everything else: Alfama is the oldest quarter, Bairro Alto is the heart of the city\'s nightlife, Cais do Sodré has the Time Out Market, and Príncipe Real is quieter and greener.',
            ]],
            ['The day off nobody plans', [
                'Most people arrive with a spare day and no plan for it. Belém, with the tower and the monastery, is a tram ride west along the river on tram 15. Sintra and its palaces are a day trip by train from Rossio station in the center. Both are better with somebody, and both are exactly the kind of thing people ask about on this page: anyone want to go tomorrow?',
            ]],
            ['The days either side', [
                'The conference app helps you meet attendees during the event. Nothing helps with the Sunday you land or the Friday after, which is when people want somebody to explore with or a table for dinner. Add your dates and you are matched with anyone in Lisbon from 9 to 12 November, attendee or not.',
            ]],
            ['Staying on: Lisbon for solo travelers and remote workers', [
                'Plenty of people turn a conference week into a month. Portugal has a remote work visa, the D8, for people working for employers or clients abroad. The income test is at least four times the Portuguese minimum wage; with the 2026 minimum wage at €920 a month, that is €3,680 a month. Check the current rules with a Portuguese consulate before you plan around it.',
                'November in Lisbon averages a high of about 18 °C and a low of about 12 °C, with rain on around ten days of the month.',
            ]],
        ],
        'warnings' => [
            'Pickpockets work in teams on crowded trams and at stops, and the scenic tram 28 is a known hotspot.',
            'In Baixa and around Rossio people offer drugs on the street. Walk on; what they sell is often not what they say.',
            'Web Summit tickets go up in price by phase. If you are going anyway, buy in an earlier phase.',
            'It rains on about a third of November days and the stone pavements get slippery. Wear shoes with grip.',
        ],
        'faq' => [
            ['When is Web Summit 2026?', 'Monday 9 to Thursday 12 November 2026, at the MEO Arena in Parque das Nações, Lisbon.'],
            ['Where should I stay for Web Summit?', 'Parque das Nações is next to the venue and quiet at night. The old center, Alfama, Baixa, Bairro Alto or Cais do Sodré, is a metro ride away and where the evenings happen.'],
            ['How do I get from the airport to Web Summit?', 'Take the metro red line from Aeroporto to Oriente, the station next to the MEO Arena.'],
            ['Can I stay and work from Lisbon afterwards?', 'Portugal\'s D8 remote work visa asks for income of at least four times the Portuguese minimum wage, which in 2026 is €3,680 a month. Check the current rules with a consulate.'],
        ],
        'sources' => [
            ['Web Summit 2026 dates and venue', 'https://websummit.com/web-summit-2026/'],
            ['Web Summit ticket guide', 'https://websummit.com/ticket-guide/'],
            ['Lisbon Metro red line (Wikipedia)', 'https://en.wikipedia.org/wiki/Lisbon_Metro'],
            ['24 hour ticket and navegante card (CP)', 'https://www.cp.pt/info/en/w/24-hours-ticket'],
            ['Portugal safety advice (GOV.UK)', 'https://www.gov.uk/foreign-travel-advice/portugal/safety-and-security'],
            ['D8 visa checklist (Portuguese embassy)', 'https://islamabad.embaixadaportugal.mne.gov.pt/images/temporary_national_checklists/national_visa_eng/d9_new_en.pdf'],
            ['2026 minimum wage (Euronews)', 'https://pt.euronews.com/2025/12/29/agora-e-oficial-salario-minimo-nacional-sobe-para-920-euros-em-2026'],
            ['Lisbon climate (Wikipedia)', 'https://en.wikipedia.org/wiki/Lisbon'],
            ['Staying safe in Lisbon (Lisbon Guru)', 'https://www.lisbonguru.com/staying-safe-lisbon/'],
        ],
        'checked' => '2026-10-01',
    ],
    'new-year-bangkok' => [
        'kind' => 'seasonal', 'name' => 'New Year in Bangkok 2026 to 2027', 'short' => 'New Year', 'edition' => '2027',
        'dest' => 'bangkok-thailand', 'from' => '2026-12-27', 'to' => '2027-01-02',
        'lede' => 'The week from 27 December to 2 January is one of the busiest of the year in Bangkok and the peak of the Southeast Asia travel season.',
        'facts' => [
            'Countdown events along the river draw large crowds; plan how you get home before midnight.',
        ],
        'sources' => [],
        'checked' => '2026-10-01',
    ],
    'rio-carnival' => [
        'kind' => 'festival', 'name' => 'Rio Carnival 2027', 'short' => 'Carnival', 'edition' => '2027',
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

/**
 * Does the page stand on its own with nobody on it yet? A written guide of real length, things
 * travelers wish they had known, and sources: the bar for indexing an occasion before its first
 * member. A registry entry with only a lede never clears it.
 *
 * @return array{ok:bool, words:int, why:list<string>}
 */
function rmt_occasion_quality(array $o): array {
    $text = (string) ($o['lede'] ?? '') . ' ' . implode(' ', (array) ($o['facts'] ?? []));
    foreach ((array) ($o['guide'] ?? []) as [$h, $paras]) $text .= ' ' . $h . ' ' . implode(' ', $paras);
    $text .= ' ' . implode(' ', (array) ($o['warnings'] ?? []));
    foreach ((array) ($o['faq'] ?? []) as [$q, $ans]) $text .= ' ' . $q . ' ' . $ans;
    $words = str_word_count(strip_tags($text));
    $why = [];
    if ($words < 600) $why[] = 'under 600 words';
    if (count((array) ($o['guide'] ?? [])) < 4) $why[] = 'fewer than 4 guide sections';
    if (count((array) ($o['warnings'] ?? [])) < 3) $why[] = 'fewer than 3 warnings';
    if (count((array) ($o['sources'] ?? [])) < 3) $why[] = 'fewer than 3 sources';
    return ['ok' => !$why, 'words' => $words, 'why' => $why];
}

/** Index an occasion page once it is useful by itself, or once somebody real is on it. */
function rmt_occasion_indexable(array $o): bool {
    if (($o['to'] ?? '') < date('Y-m-d')) return false;
    return rmt_occasion_quality($o)['ok'] || count(rmt_occasion_people($o, null, 1)) > 0;
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
    $slug = (string) ($a['slug'] ?? '');
    if (isset(RMT_OCCASION_OLD_SLUGS[$slug])) redirect_permanent('/e/' . RMT_OCCASION_OLD_SLUGS[$slug]);
    $o = rmt_occasion($slug);
    if (!$o) not_found();
    $me = current_user();
    rmt_track_once_for('destination_page_view', 'occasion:' . $o['slug'],
                       ['source' => 'occasion', 'destination_id' => (int) $o['d']['id']]);
    $people = rmt_occasion_people($o, $me);
    $talk = function_exists('rmt_posts_recent') ? rmt_posts_recent(6, (int) $o['d']['id']) : [];
    $saved = $me ? (bool) q_one("SELECT 1 FROM saves WHERE user_id=? AND target_type='destination' AND target_id=?",
                               [(int) $me['id'], (int) $o['d']['id']]) : false;
    $dates = (string) ($o['when'] ?? (date('j F', strtotime($o['from'])) . ' to ' . date('j F Y', strtotime($o['to']))));
    $robots = rmt_occasion_indexable($o) ? 'index, follow' : 'noindex,follow';
    // The member's own trip in the window, so the share card is one tap away for them.
    $myTrip = $me ? q_one("SELECT id FROM trips WHERE user_id = ? AND destination_id = ? AND status = 'published'
                             AND date_from IS NOT NULL AND date_from <= ? AND date_to >= ? ORDER BY date_from LIMIT 1",
                          [(int) $me['id'], (int) $o['d']['id'], $o['to'], $o['from']]) : null;
    $others = array_values(array_filter(rmt_occasions_upcoming(), static fn(array $x): bool => $x['slug'] !== $o['slug']));
    $ld = jsonld(['@context' => 'https://schema.org', '@type' => 'Event', 'name' => $o['name'],
        'startDate' => $o['from'], 'endDate' => $o['to'],
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'location' => ['@type' => 'Place', 'name' => $o['d']['name'],
                       'address' => ['@type' => 'PostalAddress', 'addressLocality' => $o['d']['name'], 'addressCountry' => $o['d']['country']]],
        'description' => $o['lede'], 'url' => url('e/' . $o['slug'])]);
    if (!empty($o['faq'])) {
        $ld .= jsonld(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(
            static fn(array $f): array => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]],
            $o['faq'])]);
    }
    view('occasion', ['o' => $o, 'people' => $people, 'talk' => $talk, 'me' => $me, 'saved' => $saved, 'myTrip' => $myTrip,
                      'others' => $others, 'dates' => $dates, 'links' => rmt_occasion_links($o)], [
        'title' => $o['name'] . ': dates, tips and who is going | RuinMyTrip',
        'description' => (string) ($o['meta'] ?? ($o['short'] . ' in ' . $o['d']['name'] . ', ' . $dates . '. What is confirmed, what travelers wish they knew, '
                       . 'and the other travelers going on the same days.')),
        'robots' => $robots,
        // The event's own card: its name, its dates and the question the page answers, which is
        // what a link dropped into a Facebook group or a group chat has to say in one picture.
        'og_image' => function_exists('rmt_card_url') ? rmt_card_url('event', $o['slug']) : rmt_default_og_image(),
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Events', 'url' => url('events')],
                          ['name' => $o['short'], 'url' => url('e/' . $o['slug'])]],
        'jsonld' => $ld,
    ]);
}
