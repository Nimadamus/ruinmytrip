<?php
/**
 * Tourist tax calculator (2026-11-04). Search Console showed the 2026 tax notes were the pages Google
 * ranks best (position 6 to 7), so the calculator sits on one URL and sends readers to and from those
 * notes instead of competing with them.
 *
 * Each rate is the figure in the note named by 'note', which cites the city or tax office. Change a
 * rate here only when the note changes. tests/tourist_tax_test.php holds the notes' worked examples.
 *
 * A tier adds up whichever parts it has, for each paid night:
 *   pp    flat amount per person
 *   pct   percent of the room price, per room
 *   room  flat amount per room
 *   bands [minimum price per person, amount per person], highest band that fits
 * 'cap' is the most nights a city charges for.
 */

function rmt_tt_cities(): array {
    static $c = null;
    if ($c !== null) return $c;
    $one = static fn(array $t): array => ['' => ['label' => ''] + $t];
    $c = [
        'amsterdam' => ['name' => 'Amsterdam', 'cur' => 'EUR', 'dest' => 'amsterdam-netherlands', 'note' => 'amsterdam-tourist-tax-2026',
            'rate' => '12.5% of the room price', 'tiers' => $one(['pct' => 12.5]),
            'tip' => 'Worked out on the room price without VAT. Accommodation VAT in the Netherlands is 21% from 1 January 2026 (campsites stay at 9%), on top of this.'],
        'barcelona' => ['name' => 'Barcelona', 'cur' => 'EUR', 'dest' => 'barcelona-spain', 'note' => 'barcelona-tourist-tax-2026',
            'rate' => '8.40 to 12 euros per person per night, first 7 nights', 'cap' => 7, 'tiers' => [
                '5' => ['label' => 'Five star hotel', 'pp' => 12.0],
                '4' => ['label' => 'Four star hotel', 'pp' => 8.4],
                'rental' => ['label' => 'Tourist apartment', 'pp' => 9.5]],
            'tip' => 'Count only guests aged 17 and over: 16 and under do not pay. At most 7 nights per person are taxed. Regional rate plus the 5 euro city surcharge from 1 April 2026, rising to 6 euros from 1 April 2027. Other categories are not listed here yet.'],
        'berlin' => ['name' => 'Berlin', 'cur' => 'EUR', 'dest' => 'berlin-germany', 'note' => 'berlin-city-tax-2026',
            'rate' => '7.5% of the room price', 'tiers' => $one(['pct' => 7.5]),
            'tip' => 'Worked out on the net room price. Business stays pay it too.'],
        'budapest' => ['name' => 'Budapest', 'cur' => 'HUF', 'dest' => 'budapest-hungary', 'note' => 'budapest-tourism-tax-2026',
            'rate' => '4% of the room price in the centre', 'tiers' => $one(['pct' => 4.0]),
            'tip' => 'Worked out on the gross room price. District XVI charges a flat 400 forints per person per night instead.'],
        'buenos-aires' => ['name' => 'Buenos Aires', 'cur' => 'USD', 'dest' => 'buenos-aires-argentina', 'note' => 'buenos-aires-tourist-tax-2026',
            'rate' => '1.50 dollars per person per night', 'tiers' => $one(['pp' => 1.5]),
            'tip' => 'For foreign visitors over 12.'],
        'dubrovnik' => ['name' => 'Dubrovnik', 'cur' => 'EUR', 'dest' => 'dubrovnik-croatia', 'note' => 'dubrovnik-tourist-tax-2026',
            'rate' => '1.85 to 2.65 euros per person per night', 'tiers' => [
                'high' => ['label' => '1 April to 30 September', 'pp' => 2.65],
                'low' => ['label' => '1 October to 31 March', 'pp' => 1.85]],
            'tip' => ''],
        'edinburgh' => ['name' => 'Edinburgh', 'cur' => 'GBP', 'dest' => 'edinburgh-scotland', 'note' => 'edinburgh-visitor-levy-2026',
            'rate' => '5% of the room price, first 5 nights', 'cap' => 5, 'tiers' => $one(['pct' => 5.0]),
            'tip' => 'Worked out on the accommodation cost before VAT, for bookings made on or after 1 October 2025. Meals and parking are not included.'],
        'hong-kong' => ['name' => 'Hong Kong', 'cur' => 'HKD', 'dest' => 'hong-kong', 'note' => 'hong-kong-hotel-accommodation-tax-2026',
            'rate' => '3% of the room price', 'tiers' => $one(['pct' => 3.0]), 'tip' => ''],
        'istanbul' => ['name' => 'Istanbul', 'cur' => 'TRY', 'dest' => 'istanbul-turkiye', 'note' => 'istanbul-accommodation-tax-2026',
            'rate' => '1% of the room price', 'tiers' => $one(['pct' => 1.0]),
            'tip' => 'This rate runs until 31 December 2026.'],
        'kyoto' => ['name' => 'Kyoto', 'cur' => 'JPY', 'dest' => 'kyoto-japan', 'note' => 'kyoto-lodging-tax-2026',
            'rate' => '200 to 10,000 yen per person per night', 'tiers' => $one(['bands' => [[0, 200], [6000, 400], [20000, 1000], [50000, 4000], [100000, 10000]]]),
            'tip' => ''],
        'las-vegas' => ['name' => 'Las Vegas', 'cur' => 'USD', 'dest' => 'las-vegas-usa', 'note' => 'las-vegas-room-tax-2026',
            'rate' => '13% to 13.38% of the room price', 'tiers' => [
                'corridor' => ['label' => 'Inside the Primary Gaming Corridor', 'pct' => 13.38],
                'other' => ['label' => 'Outside the corridor', 'pct' => 13.0]],
            'tip' => 'City of Las Vegas rates. Resort fees are a separate charge.'],
        'lisbon' => ['name' => 'Lisbon', 'cur' => 'EUR', 'dest' => 'lisbon-portugal', 'note' => 'lisbon-tourist-tax-2026',
            'rate' => '4 euros per person per night, first 7 nights', 'cap' => 7, 'tiers' => $one(['pp' => 4.0]),
            'tip' => 'Guests aged 13 and over.'],
        'maldives' => ['name' => 'Maldives', 'cur' => 'USD', 'dest' => 'maldives', 'note' => 'maldives-green-tax-2026',
            'rate' => '6 to 12 dollars per person per night', 'tiers' => [
                'resort' => ['label' => 'Resort', 'pp' => 12.0],
                'guesthouse' => ['label' => 'Guesthouse of 50 rooms or fewer, inhabited island', 'pp' => 6.0]],
            'tip' => 'Green tax only. The 17% TGST on the room is separate.'],
        'mexico-city' => ['name' => 'Mexico City', 'cur' => 'MXN', 'dest' => 'mexico-city-mexico', 'note' => 'mexico-city-lodging-tax-2026',
            'rate' => '3.5% to 5% of the room price', 'tiers' => [
                'hotel' => ['label' => 'Hotel', 'pct' => 3.5],
                'platform' => ['label' => 'Apartment or house paid through a platform', 'pct' => 5.0]],
            'tip' => ''],
        'milan' => ['name' => 'Milan', 'cur' => 'EUR', 'dest' => 'milan-italy', 'note' => 'milan-tourist-tax-2026',
            'rate' => '3 to 12 euros per person per night', 'tiers' => [
                '5' => ['label' => 'Five star hotel', 'pp' => 12.0],
                '4' => ['label' => 'Four star hotel', 'pp' => 10.0],
                '3' => ['label' => 'Three star hotel', 'pp' => 7.0],
                '2' => ['label' => 'Two star hotel', 'pp' => 4.0],
                '1' => ['label' => 'One star hotel', 'pp' => 3.0],
                'rental' => ['label' => 'B&B, holiday apartment or short term rental', 'pp' => 9.5],
                'home' => ['label' => 'Holiday home', 'pp' => 7.0],
                'hostel' => ['label' => 'Youth hostel or open air site', 'pp' => 3.0]],
            'tip' => 'Rates from 1 April 2026.'],
        'naples' => ['name' => 'Naples', 'cur' => 'EUR', 'dest' => 'naples-italy', 'note' => 'naples-tourist-tax-2026',
            'rate' => '4 to 6 euros per person per night, first 14 nights', 'cap' => 14, 'tiers' => [
                '5' => ['label' => 'Five star hotel', 'pp' => 6.0],
                '4' => ['label' => 'Four star hotel', 'pp' => 6.0],
                '3' => ['label' => 'Three star hotel', 'pp' => 5.5],
                '2' => ['label' => 'Two star hotel', 'pp' => 4.5],
                '1' => ['label' => 'One star hotel', 'pp' => 4.0],
                'other' => ['label' => 'Other accommodation', 'pp' => 5.5],
                'rental' => ['label' => 'Short term rental', 'pp' => 6.0]],
            'tip' => ''],
        'new-york' => ['name' => 'New York City', 'cur' => 'USD', 'dest' => 'new-york-city-usa', 'note' => 'new-york-city-hotel-tax-2026',
            'rate' => '5.875% plus 3.50 dollars per room per night', 'tiers' => $one(['pct' => 5.875, 'room' => 3.5]),
            'tip' => 'City occupancy tax, the 2 dollar flat charge and the 1.50 dollar state unit fee, for rooms of 40 dollars or more. State and city sales tax come on top.'],
        'nice' => ['name' => 'Nice', 'cur' => 'EUR', 'dest' => 'nice-france', 'note' => 'nice-tourist-tax-2026',
            'rate' => '2.28 to 6.43 euros per person per night', 'tiers' => [
                'palace' => ['label' => 'Palace', 'pp' => 6.43],
                '5' => ['label' => 'Five star hotel', 'pp' => 4.69],
                '4' => ['label' => 'Four star hotel', 'pp' => 3.48],
                '3' => ['label' => 'Three star hotel', 'pp' => 2.28]],
            'tip' => 'Count adults only: guests under 18 do not pay. Unrated stays pay 6.70% of the price per person per night, up to 6.43 euros, and are not worked out here.'],
        'osaka' => ['name' => 'Osaka', 'cur' => 'JPY', 'dest' => 'osaka-japan', 'note' => 'osaka-accommodation-tax-2026',
            'rate' => '0 to 500 yen per person per night', 'tiers' => $one(['bands' => [[0, 0], [5000, 200], [15000, 400], [20000, 500]]]),
            'tip' => 'Charged separately from the room and paid at the property, even on prepaid bookings.'],
        'paris' => ['name' => 'Paris', 'cur' => 'EUR', 'dest' => 'paris-france', 'note' => 'paris-tourist-tax-2026',
            'rate' => '5.53 to 15.93 euros per adult per night', 'tiers' => [
                'palace' => ['label' => 'Palace', 'pp' => 15.93],
                '5' => ['label' => 'Five star hotel', 'pp' => 11.7],
                '4' => ['label' => 'Four star hotel', 'pp' => 8.45],
                '3' => ['label' => 'Three star hotel', 'pp' => 5.53]],
            'tip' => 'Count adults only. Unrated stays pay a percentage of the price per person and are not worked out here.'],
        'porto' => ['name' => 'Porto', 'cur' => 'EUR', 'dest' => 'porto-portugal', 'note' => 'porto-tourist-tax-2026',
            'rate' => '3 euros per person per night, first 7 nights', 'cap' => 7, 'tiers' => $one(['pp' => 3.0]),
            'tip' => 'Guests aged 12 or under do not pay.'],
        'prague' => ['name' => 'Prague', 'cur' => 'CZK', 'dest' => 'prague-czechia', 'note' => 'prague-accommodation-fee-2026',
            'rate' => '50 crowns per person per night', 'tiers' => $one(['pp' => 50.0]), 'tip' => ''],
        'reykjavik' => ['name' => 'Reykjavik', 'cur' => 'ISK', 'dest' => 'reykjavik-iceland', 'note' => 'reykjavik-accommodation-tax-2026',
            'rate' => '800 kronur per room per night', 'tiers' => $one(['room' => 800.0]),
            'tip' => 'Iceland lodging tax for hotels and guesthouses. Charged per room, not per person.'],
        'rome' => ['name' => 'Rome', 'cur' => 'EUR', 'dest' => 'rome-italy', 'note' => 'rome-tourist-tax-2026',
            'rate' => '3 to 10 euros per person per night, first 10 nights', 'cap' => 10, 'tiers' => [
                '5' => ['label' => 'Five star hotel', 'pp' => 10.0],
                '4' => ['label' => 'Four star hotel', 'pp' => 7.5],
                '3' => ['label' => 'Three star hotel', 'pp' => 6.0],
                '2' => ['label' => 'Two star hotel', 'pp' => 5.0],
                '1' => ['label' => 'One star hotel', 'pp' => 4.0],
                'rental' => ['label' => 'B&B or short let', 'pp' => 6.0],
                'hostel' => ['label' => 'Hostel', 'pp' => 3.5]],
            'tip' => 'Count only guests aged 10 and over: children under 10 do not pay. Youth hostel stays are exempt. Campsites pay 3 euros for the first 5 nights and are not worked out here.'],
        'san-francisco' => ['name' => 'San Francisco', 'cur' => 'USD', 'dest' => 'san-francisco-usa', 'note' => 'san-francisco-hotel-tax-2026',
            'rate' => '14% of the room price', 'tiers' => $one(['pct' => 14.0]),
            'tip' => 'Hotels also pass on a Tourism Improvement District assessment of 2% to 2.25%, not counted here.'],
        'tokyo' => ['name' => 'Tokyo', 'cur' => 'JPY', 'dest' => 'tokyo-japan', 'note' => 'tokyo-accommodation-tax-2026',
            'rate' => '0 to 200 yen per person per night', 'tiers' => $one(['bands' => [[0, 0], [10000, 100], [15000, 200]]]),
            'tip' => 'The rules change from 1 April 2027.'],
        'vienna' => ['name' => 'Vienna', 'cur' => 'EUR', 'dest' => 'vienna-austria', 'note' => 'vienna-ortstaxe-2026',
            'rate' => '5% of the room price', 'tiers' => $one(['pct' => 5.0]),
            'tip' => 'Worked out on the net accommodation charge, from 1 July 2026. A rise to 8% is scheduled for 1 July 2027.'],
    ];
    return $c;
}

function rmt_tt_key_for_dest(string $dest): ?string {
    foreach (rmt_tt_cities() as $k => $c) {
        if ($c['dest'] === $dest) return (string) $k;
    }
    return null;
}

/** The next $n cities round the list, so every note gets the same number of links from its neighbours. */
function rmt_tt_neighbours(string $key, int $n): array {
    $all = array_values(rmt_tt_cities());
    $at = (int) array_search($key, array_keys(rmt_tt_cities()), true);
    $out = [];
    for ($i = 1; $i <= $n; $i++) $out[] = $all[($at + $i) % count($all)];
    return $out;
}

/** Which of the form's inputs a city's tiers need. */
function rmt_tt_needs(array $city): array {
    $n = ['people' => false, 'rooms' => false, 'price' => false, 'per_person_price' => false];
    foreach ($city['tiers'] as $t) {
        if (isset($t['pp']) || isset($t['bands'])) $n['people'] = true;
        if (isset($t['pct']) || isset($t['room'])) $n['rooms'] = true;
        if (isset($t['pct']) || isset($t['bands'])) $n['price'] = true;
        if (isset($t['bands'])) $n['per_person_price'] = true;
    }
    return $n;
}

/**
 * The tax on one stay. $price is per room per night for a percentage, per person per night for bands.
 * Returns total, the nights charged, the nights the stay asked for, and the amount per paid night.
 */
function rmt_tt_estimate(array $city, array $tier, int $nights, int $people, int $rooms, float $price): array {
    $paid = isset($city['cap']) ? min($nights, (int) $city['cap']) : $nights;
    $night = ($tier['pp'] ?? 0.0) * $people
           + ($tier['pct'] ?? 0.0) / 100 * $price * $rooms
           + ($tier['room'] ?? 0.0) * $rooms;
    if (isset($tier['bands'])) {
        $amount = 0;
        foreach ($tier['bands'] as [$min, $a]) {
            if ($price >= $min) $amount = $a;
        }
        $night += $amount * $people;
    }
    return ['total' => round($night * $paid, 2), 'paid_nights' => $paid, 'nights' => $nights, 'per_night' => round($night, 2)];
}

function rmt_tt_money(float $v, string $cur): string {
    $whole = in_array($cur, ['JPY', 'ISK', 'HUF', 'CZK'], true);
    $n = number_format($v, $whole ? 0 : 2);
    $sym = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'JPY' => '¥'][$cur] ?? null;
    return $sym !== null ? $sym . $n : $n . ' ' . $cur;
}

function rmt_tt_faq(): array {
    return [
        ['What is a tourist tax?', 'A charge a city or country adds to an overnight stay, on top of the room. It can be a flat amount per person, a percentage of the room price, or a band set by hotel category or room price.'],
        ['Is the tourist tax included in my hotel price?', 'Often not. Many hotels collect it at the property when you check out, even on prepaid bookings, so read the tax line on your booking before you travel.'],
        ['Which city has the highest tourist tax in Europe?', 'Amsterdam charges 12.5% of the room price in 2026, and VAT comes on top. On a flat rate, a Paris palace hotel charges 15.93 euros per adult per night.'],
        ['Do children pay tourist tax?', 'It depends on the city. Lisbon charges guests aged 13 and over, Porto exempts guests aged 12 or under, Rome exempts children under 10, Barcelona exempts guests aged 16 or under, and Paris and Nice charge adults. Each city note lists what the source says.'],
    ];
}
