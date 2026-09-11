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

/* Overpass mirrors.
 *
 * One public endpoint is a single point of failure, and it is somebody else's free service. These
 * are the public instances that publish an open usage policy; the list is configurable so it can be
 * changed without a deploy, and ordered at runtime by which of them has been answering.
 *
 * The main instance applies per-address slot limits: from a shared cloud address, which is what a
 * platform gives you, a request can sit in a queue behind every other tenant until it times out.
 * That is why there is a list at all, why each attempt gets a short timeout, and why
 * rmt_place_ingest() exists so the fetching need not happen on the server.
 */
const RMT_OSM_DEFAULT_ENDPOINTS = [
    'https://overpass.kumi.systems/api/interpreter',
    'https://overpass-api.de/api/interpreter',
    'https://overpass.private.coffee/api/interpreter',
    'https://overpass.osm.jp/api/interpreter',
];

/** The mirrors to use, from config when set. */
function rmt_osm_endpoints(): array {
    $c = $GLOBALS['config']['osm_mirrors'] ?? null;
    if (is_string($c) && trim($c) !== '') {
        $c = array_values(array_filter(array_map('trim', explode(',', $c))));
    }
    return is_array($c) && $c ? $c : RMT_OSM_DEFAULT_ENDPOINTS;
}

/**
 * Where the note of which mirrors are answering lives.
 *
 * A small JSON file rather than a table: it is operational state about somebody else's servers, it
 * is worthless after a day, and it has to work from a CLI script and a web request alike without a
 * migration. Missing, unreadable or corrupt all mean "no history", which is the safe answer.
 */
function rmt_osm_health_path(): string {
    $p = (string) ($GLOBALS['config']['osm_health_file'] ?? '');
    return $p !== '' ? $p : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/rmt_osm_health.json';
}

function rmt_osm_health_read(): array {
    $f = rmt_osm_health_path();
    if (!is_file($f)) return [];
    $j = json_decode((string) @file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

/**
 * Record how a mirror behaved, and how long to leave it alone.
 *
 * Failures compound: thirty seconds, then a minute, then two, capped at a quarter of an hour. A
 * mirror that is struggling is not helped by being asked again immediately, and the whole point of
 * having four is that one being tired costs nothing.
 */
function rmt_osm_health_note(string $host, bool $ok): void {
    $h = rmt_osm_health_read();
    $row = $h[$host] ?? ['ok' => 0, 'fails' => 0, 'until' => 0];
    if ($ok) {
        $row['ok'] = (int) $row['ok'] + 1;
        $row['fails'] = 0;
        $row['until'] = 0;
    } else {
        $row['fails'] = (int) $row['fails'] + 1;
        $row['until'] = time() + (int) min(900, 30 * (2 ** min(5, (int) $row['fails'] - 1)));
    }
    $h[$host] = $row;
    @file_put_contents(rmt_osm_health_path(), json_encode($h), LOCK_EX);
}

/** Mirrors in the order worth trying: the ones not cooling off first, freshest failure last. */
function rmt_osm_endpoints_ranked(): array {
    $h = rmt_osm_health_read();
    $now = time();
    $urls = rmt_osm_endpoints();
    usort($urls, static function (string $a, string $b) use ($h, $now): int {
        $ha = $h[(string) parse_url($a, PHP_URL_HOST)] ?? [];
        $hb = $h[(string) parse_url($b, PHP_URL_HOST)] ?? [];
        $ca = (int) ($ha['until'] ?? 0) > $now ? 1 : 0;
        $cb = (int) ($hb['until'] ?? 0) > $now ? 1 : 0;
        if ($ca !== $cb) return $ca <=> $cb;                 // not cooling off first
        return (int) ($ha['fails'] ?? 0) <=> (int) ($hb['fails'] ?? 0);
    });
    return $urls;
}

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
            /* A stadium sits here rather than under attraction because "experience" is this site's
               bucket for a thing you go and do at a time, which is what a match is. */
            'leisure' => ['stadium'],
        ],
    ];
}

/** Build the Overpass query for one type inside a bounding box. */
function rmt_osm_query(string $type, array $bbox, int $limit, array $only = []): string {
    $map = rmt_osm_type_map()[$type] ?? [];
    if (!$map) return '';
    /* Narrowed to particular kinds when asked. Without this an "attraction" run in Lisbon comes
       back fourteen memorials and six museums, because it takes whatever the bounding box offers
       first. A city deserves a deliberate mix rather than whatever is densest. */
    if ($only) {
        $filtered = [];
        foreach ($map as $key => $values) {
            $keep = array_values(array_intersect($values, $only));
            if ($keep) $filtered[$key] = $keep;
        }
        $map = $filtered;
        if (!$map) return '';
    }
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
    if ($query === '') return ['ok' => false, 'elements' => [], 'error' => 'Empty query.', 'tries' => []];
    $lastError = 'No endpoint answered.';
    $tries = [];
    foreach (rmt_osm_endpoints_ranked() as $url) {
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
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: $url);
        if ($body === false || $code !== 200) {
            $lastError = $err !== '' ? $err : ('HTTP ' . $code);
            $tries[] = $host . ': ' . $lastError;
            rmt_osm_health_note($host, false);
            /* 429 is the provider saying "not now" in as many words. Backing off is the difference
               between being a heavy user of a free service and being the reason it gets locked
               down. Anything else, move on to the next mirror immediately. */
            if ($code === 429) sleep(3);
            continue;
        }
        $json = json_decode((string) $body, true);
        if (!is_array($json) || !isset($json['elements'])) {
            $lastError = 'Unreadable response.';
            $tries[] = $host . ': ' . $lastError;
            rmt_osm_health_note($host, false);
            continue;
        }
        $tries[] = $host . ': ok';
        rmt_osm_health_note($host, true);
        return ['ok' => true, 'elements' => $json['elements'], 'error' => null, 'tries' => $tries];
    }
    return ['ok' => false, 'elements' => [], 'error' => $lastError, 'tries' => $tries];
}

/** Which of our types an OSM element is, or null when it is something we do not carry. */
function rmt_osm_element_type(array $tags): ?string {
    $m = rmt_osm_element_match($tags);
    return $m['type'] ?? null;
}

/**
 * The same question, plus WHICH tag decided it.
 *
 * The deciding tag is the only honest label for a run report. A memorial is frequently also tagged
 * tourism=artwork, and a histogram that reads the first tag it finds rather than the one that
 * matched will report four artworks this site does not carry and hide ten memorials it does.
 *
 * @return array{type:?string,tag:?string}
 */
function rmt_osm_element_match(array $tags): array {
    foreach (rmt_osm_type_map() as $type => $keys) {
        foreach ($keys as $key => $values) {
            if (isset($tags[$key]) && in_array((string) $tags[$key], $values, true)) {
                return ['type' => $type, 'tag' => (string) $tags[$key]];
            }
        }
    }
    return ['type' => null, 'tag' => null];
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

    $match = rmt_osm_element_match($tags);
    $type = $match['type'];
    if ($type === null) return ['row' => null, 'aliases' => []];
    $kind = (string) $match['tag'];

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
        /* Not a place column: hours live in their own table and are stored separately, only when
           the value is one of the forms the parser trusts. Carried on the row so the importer can
           hand it on without a second request. */
        'opening_hours'   => trim((string) ($tags['opening_hours'] ?? '')) ?: null,
        'source_kind'     => $kind,
        'category_slug'   => rmt_osm_category_slug($kind),
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

function rmt_osm_places_for_destination(array $dest, string $type, int $limit = 60, ?float $km = null,
                                        array $only = []): array {
    $km = $km !== null && $km > 0 ? $km : rmt_osm_default_km($type);
    $lat = $dest['lat'] ?? null;
    $lng = $dest['lng'] ?? null;
    if ($lat === null || $lng === null) {
        return ['ok' => false, 'rows' => [], 'aliases' => [], 'tags' => [], 'seen' => 0,
                'error' => 'That city has no coordinates, so there is nowhere to look.'];
    }
    $q = rmt_osm_query($type, rmt_osm_bbox((float) $lat, (float) $lng, $km), max(1, $limit * 3), $only);
    if ($q === '') {
        return ['ok' => false, 'rows' => [], 'aliases' => [], 'tags' => [], 'seen' => 0,
                'error' => 'Nothing in that kind belongs to that type.'];
    }
    $res = rmt_osm_fetch($q);
    if (!$res['ok']) {
        return ['ok' => false, 'rows' => [], 'aliases' => [], 'tags' => [], 'seen' => 0,
                'error' => $res['error'], 'tries' => $res['tries'] ?? []];
    }

    $rows = [];
    $aliases = [];
    $tags = [];
    foreach ($res['elements'] as $el) {
        $c = rmt_osm_to_place($el);
        if ($c['row'] === null) continue;
        $ref = (string) $c['row']['source_ref'];
        if (isset($aliases[$ref])) continue;          // the same venue mapped twice
        $rows[] = $c['row'];
        $aliases[$ref] = $c['aliases'];
        /* What KIND of thing each row actually is, in the provider's own words. Our four types are
           a coarse bucket and "25 attractions" does not tell anybody whether that is museums or
           parks; this does, without storing a second taxonomy we would then have to maintain. */
        $decided = rmt_osm_element_match((array) ($el['tags'] ?? []))['tag'];
        if ($decided !== null) $tags[$decided] = ($tags[$decided] ?? 0) + 1;
        if (count($rows) >= $limit) break;
    }
    arsort($tags);
    return ['ok' => true, 'rows' => $rows, 'aliases' => $aliases, 'tags' => $tags,
            'seen' => count($res['elements']), 'error' => null, 'tries' => $res['tries'] ?? []];
}

/**
 * The provider's word for a thing, mapped to the category a reader would use.
 *
 * Two separate questions, deliberately. The type map above decides which of this site's four coarse
 * buckets a venue belongs in, because that drives pages that already exist. This decides what to
 * CALL it, and "Cafe", "Bar", "Museum", "Park" are the words somebody browsing actually thinks in.
 * A cafe and a steakhouse are both restaurants to the database and are not the same thing to a
 * person deciding where to have breakfast.
 *
 * A tag with no good category returns null rather than a guess: an uncategorised place still has a
 * type, a name and a map pin, and a wrong category is worse than an absent one because it sends
 * somebody looking for a museum to a car park.
 *
 * Several obvious-looking mappings are deliberately absent for that reason. A plain restaurant is
 * not a bistro, a hotel is not a luxury hotel, a cinema is not a theatre, and a piece of public art
 * is not a landmark. Those pages say "Restaurant" and "Hotel", which is the coarse type and is
 * true, rather than a precise word that is wrong.
 */
function rmt_osm_category_slug(string $kind): ?string {
    static $map = [
        // eating and drinking
        'cafe'          => 'cafe',
        'fast_food'     => 'street-food',
        'bar'           => 'bar',
        'pub'           => 'pub',
        'nightclub'     => 'nightclub',
        // staying
        'hostel'        => 'hostel',
        'guest_house'   => 'guesthouse',
        'apartment'     => 'vacation-rental',
        // seeing
        'museum'        => 'museum',
        'gallery'       => 'art-gallery',
        'viewpoint'     => 'viewpoint',
        'aquarium'      => 'zoo-aquarium',
        'zoo'           => 'zoo-aquarium',
        'theme_park'    => 'theme-park',
        'castle'        => 'historic-site',
        'monument'      => 'landmark',
        'memorial'      => 'landmark',
        'ruins'         => 'historic-site',
        'archaeological_site' => 'historic-site',
        'park'          => 'park',
        'garden'        => 'garden',
        // doing
        'marketplace'   => 'market',
        'department_store' => 'shopping',
        'mall'          => 'shopping',
        'theatre'       => 'theater',
        'casino'        => 'casino',
        'stadium'       => 'stadium',
    ];
    return $map[$kind] ?? null;
}
