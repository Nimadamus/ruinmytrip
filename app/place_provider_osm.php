<?php
/**
 * OpenStreetMap as a place provider.
 *
 * OSM is the one source that covers restaurants, hotels and attractions at scale AND may legally be
 * redistributed: ODbL, attribution required, no key, no account, no payment. Every other provider
 * worth having either forbids storing its data or charges for it, and a directory built on data we
 * are not allowed to keep is a directory that has to be deleted later.
 *
 * What it gives us, per venue: a name, a category, coordinates, an address, a phone number and a
 * website. What it does not give us, and what this site therefore does not pretend to have from it:
 * a rating, a review, a photograph, or any notion of how popular somewhere is. Those come from
 * members or they do not exist.
 *
 * Attribution is not optional and not decorative. rmt_place_providers() carries the line, and any
 * page showing a place sourced here has to print it.
 */
declare(strict_types=1);

/* Overpass mirrors, tried in order. A public endpoint under load returns 429 or simply hangs, and
   the main instance applies per-address slot limits: from a shared cloud address, which is what a
   platform like Render gives you, a request can sit in a queue behind every other tenant until it
   times out. The mirror is tried first for that reason, each attempt gets a short timeout so both
   fit inside one web request, and rmt_place_ingest() exists so the fetching need not happen on the
   server at all. */
const RMT_OSM_ENDPOINTS = [
    'https://overpass.kumi.systems/api/interpreter',
    'https://overpass-api.de/api/interpreter',
];

/**
 * OSM tags to this site's four types.
 *
 * Deliberately narrow. A "place" here is somewhere a traveler goes on purpose, so this takes
 * museums and restaurants and leaves out bus stops, post boxes and the eleven thousand benches in
 * central Lisbon. Anything not on this list is not imported rather than being filed under "other".
 */
function rmt_osm_type_map(): array {
    return [
        'restaurant' => [
            'amenity' => ['restaurant', 'cafe', 'bar', 'pub', 'fast_food', 'ice_cream'],
        ],
        'hotel' => [
            'tourism' => ['hotel', 'hostel', 'guest_house', 'apartment'],
        ],
        'attraction' => [
            'tourism'    => ['museum', 'attraction', 'gallery', 'viewpoint', 'aquarium', 'zoo', 'theme_park'],
            'historic'   => ['castle', 'monument', 'memorial', 'ruins', 'archaeological_site'],
            'leisure'    => ['park', 'garden'],
            /* A covered market or a landmark department store is somewhere travelers go on
               purpose, which is the only test that matters here. Ordinary shops are not: a city
               has ten thousand of them and none of them is a page. */
            'amenity'    => ['marketplace'],
            'shop'       => ['department_store', 'mall'],
        ],
        'experience' => [
            'amenity' => ['theatre', 'cinema', 'nightclub', 'casino'],
            'leisure' => ['stadium'],
        ],
    ];
}

/** Build the Overpass query for one type inside a bounding box. */
function rmt_osm_query(string $type, array $bbox, int $limit): string {
    $map = rmt_osm_type_map()[$type] ?? [];
    if (!$map) return '';
    [$south, $west, $north, $east] = $bbox;
    $box = sprintf('(%.5f,%.5f,%.5f,%.5f)', $south, $west, $north, $east);

    $parts = [];
    foreach ($map as $key => $values) {
        $v = implode('|', array_map('preg_quote', $values));
        /* Only named venues. An unnamed cafe is a dot on a map, not a page.

           `nwr` is one statement for nodes, ways and relations together. Written as separate node
           and way statements the attraction query was twelve statements over a 24km box and a
           public Overpass endpoint answered it with a 504. */
        $parts[] = 'nwr["' . $key . '"~"^(' . $v . ')$"]["name"]' . $box . ';';
    }
    return "[out:json][timeout:45];\n(\n" . implode("\n", $parts) . "\n);\nout center tags " . (int) $limit . ";";
}

/**
 * A bounding box around a destination, in degrees.
 *
 * A city page holds one point, so the box is that point plus a radius. Twelve kilometres covers a
 * European city centre and its inner suburbs without dragging in the next town.
 */
function rmt_osm_bbox(float $lat, float $lng, float $km = 12.0): array {
    $dLat = $km / 111.0;
    $dLng = $km / max(0.1, 111.0 * cos(deg2rad($lat)));
    return [$lat - $dLat, $lng - $dLng, $lat + $dLat, $lng + $dLng];
}

/**
 * Ask Overpass. Returns the decoded elements, or an error.
 *
 * @return array{ok:bool,elements:list<array>,error:?string}
 */
function rmt_osm_fetch(string $query, int $timeout = 25): array {
    if ($query === '') return ['ok' => false, 'elements' => [], 'error' => 'Empty query.'];
    $lastError = 'No endpoint answered.';
    foreach (RMT_OSM_ENDPOINTS as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['data' => $query]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            // Overpass asks for a real user agent so it can tell a runaway script from a person.
            CURLOPT_USERAGENT => 'RuinMyTrip place importer (+https://ruinmytrip.com)',
        ]);
        /* Certificate verification stays on. This only tells curl WHERE the trusted roots are, for
           the machines that ship PHP without a bundle: a developer laptop. Unset on the server,
           where the system store is where curl already looks. */
        $ca = (string) ($GLOBALS['config']['ca_bundle'] ?? (getenv('CURL_CA_BUNDLE') ?: ''));
        if ($ca !== '' && is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code !== 200) {
            $lastError = $err !== '' ? $err : ('HTTP ' . $code);
            continue;
        }
        $json = json_decode((string) $body, true);
        if (!is_array($json) || !isset($json['elements'])) {
            $lastError = 'Unreadable response.';
            continue;
        }
        return ['ok' => true, 'elements' => $json['elements'], 'error' => null];
    }
    return ['ok' => false, 'elements' => [], 'error' => $lastError];
}

/** Which of our types an OSM element is, or null when it is something we do not carry. */
function rmt_osm_element_type(array $tags): ?string {
    foreach (rmt_osm_type_map() as $type => $keys) {
        foreach ($keys as $key => $values) {
            if (isset($tags[$key]) && in_array((string) $tags[$key], $values, true)) return $type;
        }
    }
    return null;
}

/**
 * One OSM element to one canonical place record.
 *
 * Every field is copied, never derived. A missing tag produces a missing field, not a blank or a
 * guess: a wrong address on a page that looks authoritative is worse than a page with a gap.
 *
 * @return array{row:?array<string,mixed>,aliases:list<string>}
 */
function rmt_osm_to_place(array $el): array {
    $tags = (array) ($el['tags'] ?? []);
    $name = trim((string) ($tags['name'] ?? ''));
    if ($name === '') return ['row' => null, 'aliases' => []];

    $type = rmt_osm_element_type($tags);
    if ($type === null) return ['row' => null, 'aliases' => []];

    $lat = $el['lat'] ?? ($el['center']['lat'] ?? null);
    $lng = $el['lon'] ?? ($el['center']['lon'] ?? null);
    $osmType = (string) ($el['type'] ?? 'node');
    $osmId = (int) ($el['id'] ?? 0);
    if ($osmId <= 0) return ['row' => null, 'aliases' => []];

    $street = trim(((string) ($tags['addr:street'] ?? '')) !== ''
        ? trim((string) ($tags['addr:housenumber'] ?? '') . ' ' . (string) $tags['addr:street'])
        : '');

    $row = [
        'name'            => $name,
        'type'            => $type,
        'lat'             => $lat !== null ? (float) $lat : null,
        'lng'             => $lng !== null ? (float) $lng : null,
        'street_address'  => $street !== '' ? $street : null,
        'neighborhood'    => trim((string) ($tags['addr:suburb'] ?? '')) ?: null,
        'postal_code'     => trim((string) ($tags['addr:postcode'] ?? '')) ?: null,
        'phone'           => trim((string) ($tags['phone'] ?? $tags['contact:phone'] ?? '')) ?: null,
        'website_url'     => trim((string) ($tags['website'] ?? $tags['contact:website'] ?? '')) ?: null,
        'data_source'     => 'openstreetmap',
        'data_source_url' => 'https://www.openstreetmap.org/' . $osmType . '/' . $osmId,
        'source_ref'      => $osmType . '/' . $osmId,
    ];
    if (!empty($row['website_url']) && !preg_match('#^https?://#i', (string) $row['website_url'])) {
        $row['website_url'] = 'https://' . ltrim((string) $row['website_url'], '/');
    }

    /* The other names it goes by. These are what make the next import match rather than duplicate,
       and what makes searching for "Geological Museum" find "Museu Geologico". */
    $aliases = [];
    foreach (['alt_name', 'old_name', 'int_name', 'name:en', 'short_name', 'official_name'] as $k) {
        $v = trim((string) ($tags[$k] ?? ''));
        if ($v !== '' && $v !== $name) $aliases[] = $v;
    }

    return ['row' => $row, 'aliases' => array_values(array_unique($aliases))];
}

/**
 * Pull a batch of places for one destination.
 *
 * @return array{ok:bool,rows:list<array>,aliases:array<string,list<string>>,error:?string,seen:int}
 */
function rmt_osm_default_km(string $type): float {
    /* How far out to look, by how dense the thing is. A city centre holds thousands of cafes and a
       handful of museums, and Overpass scans the whole box whatever the output limit says: asking
       for restaurants across 24km of Lisbon takes over a minute and times out, while the same
       question over 12km answers in under three seconds. Sparse kinds keep the wider net because
       otherwise the good ones outside the centre are simply never found. */
    return in_array($type, ['restaurant', 'hotel'], true) ? 6.0 : 12.0;
}

function rmt_osm_places_for_destination(array $dest, string $type, int $limit = 60, ?float $km = null): array {
    $km = $km !== null && $km > 0 ? $km : rmt_osm_default_km($type);
    $lat = $dest['lat'] ?? null;
    $lng = $dest['lng'] ?? null;
    if ($lat === null || $lng === null) {
        return ['ok' => false, 'rows' => [], 'aliases' => [], 'seen' => 0,
                'error' => 'That city has no coordinates, so there is nowhere to look.'];
    }
    $q = rmt_osm_query($type, rmt_osm_bbox((float) $lat, (float) $lng, $km), max(1, $limit * 3));
    $res = rmt_osm_fetch($q);
    if (!$res['ok']) return ['ok' => false, 'rows' => [], 'aliases' => [], 'seen' => 0, 'error' => $res['error']];

    $rows = [];
    $aliases = [];
    foreach ($res['elements'] as $el) {
        $c = rmt_osm_to_place($el);
        if ($c['row'] === null) continue;
        $ref = (string) $c['row']['source_ref'];
        if (isset($aliases[$ref])) continue;          // the same venue mapped twice
        $rows[] = $c['row'];
        $aliases[$ref] = $c['aliases'];
        if (count($rows) >= $limit) break;
    }
    return ['ok' => true, 'rows' => $rows, 'aliases' => $aliases, 'seen' => count($res['elements']), 'error' => null];
}
