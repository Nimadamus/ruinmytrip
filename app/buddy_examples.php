<?php
declare(strict_types=1);

/**
 * Example listings for Travel Buddies.
 *
 * A young network is empty, and an empty search teaches a visitor that nothing happens here before
 * they have seen what would. These show what the section does, and they are built so that they
 * cannot be mistaken for people or leak into anything real:
 *
 *   - they live in this file, not in the database: no account, no row, nothing in a count, a feed,
 *     a sitemap, a match notification or an email
 *   - every one is labeled as an example on the card, named "Example traveler", with no photo of a
 *     person and no profile to open
 *   - they carry no request button; the only action is to post a trip like it
 *   - they only appear while real results are thin, in their own labeled section under the real
 *     ones, and they go through the same filters so a search still behaves like a search
 *
 * Dates are relative to today, so an example is never in the past.
 */

/** Real results at or above this and the examples step aside. */
const RMT_BUDDY_EXAMPLES_BELOW = 6;

/** @return list<array> cards in the shape rmt_buddy_card() makes, flagged 'example' */
function rmt_buddy_examples(): array {
    $day = static fn(int $n): string => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));
    // A cruise sails on a Saturday, like most do; two examples share it to show a same sailing.
    $sail = date('Y-m-d', strtotime('saturday +8 weeks'));
    $sailEnd = date('Y-m-d', strtotime($sail . ' +7 days'));
    $med = date('Y-m-d', strtotime('saturday +14 weeks'));
    $defs = [
        ['key' => 'tokyo', 'kind' => 'post', 'type' => 'trip', 'dest' => 'tokyo-japan', 'party' => 'solo',
         'title' => 'Two weeks in Tokyo and Kyoto', 'from' => $day(45), 'to' => $day(59), 'flexible' => true,
         'summary' => 'First time in Japan. Food markets by day, izakayas at night, a day trip to Nikko. Happy to split a few meals and explore together.',
         'interests' => ['food', 'culture', 'photography'], 'languages' => ['English'], 'spots' => 2],
        ['key' => 'bangkok', 'kind' => 'post', 'type' => 'backpacking', 'dest' => 'bangkok-thailand', 'party' => 'solo',
         'title' => 'A month around Thailand, starting in Bangkok', 'from' => $day(20), 'to' => $day(50), 'flexible' => true,
         'where' => 'Bangkok, Chiang Mai and the islands',
         'summary' => 'Backpacking on a budget. Bangkok for a week, then north to Chiang Mai and down to the islands. Looking for people to share hostels and ferries.',
         'interests' => ['beaches', 'food', 'nightlife'], 'languages' => ['English', 'Spanish'], 'spots' => 3],
        ['key' => 'paris', 'kind' => 'post', 'type' => 'trip', 'dest' => 'paris-france', 'party' => 'couple',
         'title' => 'Long weekend in Paris, museums and wine bars', 'from' => $day(70), 'to' => $day(74), 'flexible' => false,
         'summary' => 'We have the Louvre and the Orsay booked and would enjoy meeting another couple or two for dinner in the Marais.',
         'interests' => ['culture', 'history', 'food'], 'languages' => ['English', 'French'], 'spots' => 2],
        ['key' => 'cruise-a', 'kind' => 'post', 'type' => 'cruise', 'dest' => '', 'party' => 'solo',
         'title' => 'Solo on a 7 night Western Caribbean cruise', 'from' => $sail, 'to' => $sailEnd, 'flexible' => false,
         'where' => 'Western Caribbean', 'cruise_line' => 'Royal Caribbean', 'ship' => 'Icon of the Seas', 'departure_port' => 'Miami',
         'itinerary' => 'Perfect Day at CocoCay, Cozumel, Roatan',
         'summary' => 'Sailing on my own and would love company for excursions and trivia night. Thinking about snorkeling in Cozumel.',
         'interests' => ['beaches', 'food'], 'languages' => ['English'], 'spots' => 2],
        ['key' => 'cruise-b', 'kind' => 'post', 'type' => 'cruise', 'dest' => '', 'party' => 'friends',
         'title' => 'Three friends on Icon of the Seas, looking for more', 'from' => $sail, 'to' => $sailEnd, 'flexible' => false,
         'where' => 'Western Caribbean', 'cruise_line' => 'Royal Caribbean', 'ship' => 'Icon of the Seas', 'departure_port' => 'Miami',
         'itinerary' => 'Perfect Day at CocoCay, Cozumel, Roatan',
         'summary' => 'Same sailing as above. We want a bigger group for the waterpark day and a table at dinner.',
         'interests' => ['nightlife', 'beaches'], 'languages' => ['English'], 'spots' => 4],
        ['key' => 'cruise-med', 'kind' => 'post', 'type' => 'cruise', 'dest' => '', 'party' => 'couple',
         'title' => 'Mediterranean cruise from Barcelona', 'from' => $med, 'to' => date('Y-m-d', strtotime($med . ' +10 days')), 'flexible' => false,
         'where' => 'Western Mediterranean', 'cruise_line' => 'MSC Cruises', 'ship' => 'MSC World Europa', 'departure_port' => 'Barcelona',
         'itinerary' => 'Marseille, Genoa, Naples, Messina, Valletta',
         'summary' => 'Retired and travelling slowly. Would enjoy sharing a taxi in port and a glass of wine on sea days.',
         'interests' => ['history', 'food'], 'languages' => ['English', 'Italian'], 'spots' => 2],
        ['key' => 'mexico', 'kind' => 'post', 'type' => 'resort', 'dest' => 'tulum-mexico', 'party' => 'friends',
         'title' => 'Resort week in Tulum and Playa del Carmen', 'from' => $day(35), 'to' => $day(42), 'flexible' => true,
         'where' => 'Tulum and Playa del Carmen',
         'summary' => 'Beach days, a cenote swim and one big night out. Two of us, open to joining another small group for day trips.',
         'interests' => ['beaches', 'outdoors', 'nightlife'], 'languages' => ['English', 'Spanish'], 'spots' => 3],
        ['key' => 'italy', 'kind' => 'post', 'type' => 'trip', 'dest' => 'rome-italy', 'party' => 'solo',
         'title' => 'Rome, Florence and the Amalfi Coast by train', 'from' => $day(90), 'to' => $day(104), 'flexible' => true,
         'where' => 'Rome, Florence and the Amalfi Coast',
         'summary' => 'Two weeks by train with a camera. Looking for a walking buddy for Rome and someone to share a boat on the Amalfi Coast.',
         'interests' => ['history', 'photography', 'food'], 'languages' => ['English'], 'spots' => 1],
        ['key' => 'lisbon-now', 'kind' => 'trip', 'type' => 'trip', 'dest' => 'lisbon-portugal', 'party' => 'solo',
         'title' => 'In Lisbon this week', 'from' => $day(-2), 'to' => $day(4), 'flexible' => false,
         'summary' => 'Here now and up for a sunset at a miradouro or a pastel de nata crawl.',
         'interests' => ['food', 'photography'], 'languages' => ['English', 'Portuguese'], 'spots' => 0],
        ['key' => 'barcelona-local', 'kind' => 'local', 'type' => '', 'dest' => 'barcelona-spain', 'party' => '',
         'title' => 'Lives in Barcelona', 'from' => '', 'to' => '', 'flexible' => false,
         'summary' => 'Local who likes showing visitors the neighborhood bars in Gracia and answering questions about getting around.',
         'interests' => ['food', 'nightlife', 'sport'], 'languages' => ['Spanish', 'Catalan', 'English'], 'spots' => 0],
    ];

    $slugs = array_values(array_filter(array_unique(array_column($defs, 'dest'))));
    $dests = [];
    if ($slugs) {
        foreach (q_all('SELECT slug, name, country FROM destinations WHERE slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')', $slugs) as $d) {
            $dests[$d['slug']] = $d;
        }
    }
    $today = date('Y-m-d');
    $out = [];
    foreach ($defs as $x) {
        $d = $dests[$x['dest']] ?? null;
        if ($x['dest'] !== '' && !$d) continue;   // a city this site does not carry is not an example
        $newQuery = array_filter(['type' => $x['type'], 'dest' => $x['dest'], 'from' => $x['from'], 'to' => $x['to'],
                                  'ship' => $x['ship'] ?? '', 'line' => $x['cruise_line'] ?? '', 'port' => $x['departure_port'] ?? '']);
        $out[] = [
            'kind' => $x['kind'], 'example' => true, 'id' => 0, 'user_id' => 0, 'username' => 'example',
            'name' => 'Example traveler', 'avatar_url' => null, 'verified' => false, 'languages' => $x['languages'],
            'dest_name' => (string) ($d['name'] ?? ''), 'dest_slug' => (string) ($d['slug'] ?? ''), 'country' => (string) ($d['country'] ?? ''),
            'here_now' => $x['from'] !== '' && $x['from'] <= $today && $x['to'] >= $today,
            'overlap_days' => 0, 'viewer_state' => null, 'saved' => false, 'rank' => 0, 'on_sailing' => 0,
            'url' => url('buddies/new') . ($newQuery ? '?' . http_build_query($newQuery) : ''),
            'type' => $x['type'], 'party' => $x['party'], 'title' => $x['title'], 'summary' => $x['summary'],
            'where' => (string) ($x['where'] ?? ($d['name'] ?? '')), 'from' => $x['from'], 'to' => $x['to'], 'flexible' => $x['flexible'],
            'post_interests' => $x['interests'], 'interests' => $x['interests'], 'spots' => $x['spots'], 'interest_count' => 0,
            'cruise_line' => (string) ($x['cruise_line'] ?? ''), 'ship' => (string) ($x['ship'] ?? ''),
            'ship_key' => (string) rmt_buddy_ship_key($x['ship'] ?? ''), 'departure_port' => (string) ($x['departure_port'] ?? ''),
            'itinerary' => (string) ($x['itinerary'] ?? ''),
            'nights' => $x['from'] !== '' ? rmt_buddy_nights($x['from'], $x['to']) : 0, 'age_min' => 0, 'age_max' => 0,
        ];
    }
    return $out;
}

/**
 * The examples a search would return, with the same rules the real search applies, so filtering
 * the page never shows an example that contradicts what the reader asked for.
 */
function rmt_buddy_examples_for(array $f): array {
    $hasDates = $f['from'] !== '';
    $slack = $f['flexible'] ? RMT_BUDDY_FLEX_DAYS : 0;
    $has = static fn(string $hay, string $needle): bool => $needle === '' || mb_stripos($hay, $needle) !== false;
    $out = [];
    foreach (rmt_buddy_examples() as $c) {
        $isLocal = $c['kind'] === 'local';
        if ($f['show'] === 'locals' && !$isLocal) continue;
        if ($isLocal && ($f['type'] !== '' || $f['party'] !== '' || $f['ship'] !== '' || $f['line'] !== '' || $f['port'] !== '')) continue;
        if ($isLocal && $f['show'] === 'all' && !($f['dest_id'] || $f['country'] !== '')) continue;
        if ($f['show'] === 'here' && !$c['here_now']) continue;
        if ($f['show'] === 'going' && $isLocal) continue;
        if ($f['dest'] && $c['dest_slug'] !== $f['dest']['slug']) continue;
        if ($f['country'] !== '' && strcasecmp($c['country'], $f['country']) !== 0 && !$has($c['where'], $f['country'])) continue;
        if ($f['text'] !== '' && !$has(implode(' ', [$c['where'], $c['title'], $c['ship'], $c['cruise_line'], $c['departure_port'], $c['itinerary'], $c['dest_name'], $c['country']]), $f['text'])) continue;
        if ($f['type'] !== '' && $c['type'] !== $f['type']) continue;
        if ($f['party'] !== '' && $c['party'] !== $f['party']) continue;
        if ($f['interest'] !== '' && !in_array($f['interest'], $c['interests'], true)) continue;
        if (!$has($c['cruise_line'], $f['line']) || !$has($c['departure_port'], $f['port'])) continue;
        if ($f['ship'] !== '' && $c['ship_key'] !== rmt_buddy_ship_key($f['ship']) && !$has($c['ship'], $f['ship'])) continue;
        if ($hasDates && !$isLocal) {
            $cs = $c['flexible'] ? RMT_BUDDY_FLEX_DAYS : 0;
            if (rmt_buddy_overlap_days(rmt_buddy_date_shift($f['from'], -$slack - $cs), rmt_buddy_date_shift($f['to'], $slack + $cs), $c['from'], $c['to']) < 1) continue;
            $c['overlap_days'] = rmt_buddy_overlap_days($f['from'], $f['to'], $c['from'], $c['to']);
        }
        $c['rank'] = rmt_buddy_rank($c, $f);
        $out[] = $c;
    }
    // Two examples share a sailing on purpose, so the card can show what that looks like.
    $bySailing = [];
    foreach ($out as $c) if ($c['ship_key'] !== '') $bySailing[$c['ship_key'] . '|' . $c['from']] = ($bySailing[$c['ship_key'] . '|' . $c['from']] ?? 0) + 1;
    foreach ($out as &$c) if ($c['ship_key'] !== '') $c['on_sailing'] = $bySailing[$c['ship_key'] . '|' . $c['from']] - 1;
    unset($c);
    usort($out, static fn($x, $y) => [$x['kind'] === 'local', -$x['rank'], $x['from']] <=> [$y['kind'] === 'local', -$y['rank'], $y['from']]);
    return $out;
}
