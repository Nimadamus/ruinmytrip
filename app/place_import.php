<?php
/**
 * Importing real places from a named provider.
 *
 * The rule this file exists to enforce: every place on this site came from somewhere a person can
 * check. A provider hands over a canonical record, this decides whether it is a place we already
 * hold, and either creates one or fills gaps in the one that is there. Nothing is invented and
 * nothing is guessed.
 *
 * What a provider may NEVER supply, and what this will refuse to store even if it is in the payload:
 * a rating, a review count, a popularity score, a "top ten" rank. Those are claims about quality,
 * this site's ratings are written by its own members, and mixing the two would make every number on
 * a place page a lie about who said it. The refusal is a whitelist, not a blacklist, and there is a
 * test that the whitelist contains none of them.
 *
 * Deduplication happens in three passes, cheapest first:
 *   1. the provider's own record id, which makes a second import an update
 *   2. the normalised name inside the same city, which is what the places table is already unique on
 *   3. an alias, then proximity: the same name within sixty metres is the same building
 *
 * Merging is deliberately timid. A field a human filled is never overwritten by a provider. A field
 * the same provider filled last time is refreshed, because that is what an update is for. A field
 * nobody has filled is filled. Anything else is left alone and reported, so a disagreement between
 * two sources becomes something to look at rather than a silent overwrite.
 */
declare(strict_types=1);

/**
 * The only fields a provider may write. Anything else in a payload is dropped.
 *
 * Note what is absent: rating, reviews, popularity, rank, price beyond the four-band price_level
 * that describes the venue rather than judging it.
 */
const RMT_PLACE_IMPORT_FIELDS = [
    'name', 'type', 'category_id', 'lat', 'lng', 'street_address', 'neighborhood', 'region',
    'postal_code', 'phone', 'website_url', 'price_level', 'timezone',
    'data_source', 'data_source_url', 'source_ref', 'source_kind', 'category_slug',
    /* Not a column on places: pulled out below and stored in place_hours, and only when the value
       is one of the forms the parser is willing to trust. */
    'opening_hours',
];

/** How far apart two records can be and still be the same building, in metres. */
const RMT_PLACE_DEDUPE_METRES = 60;

/** Registered providers: slug => human name and the licence line a page must carry. */
function rmt_place_providers(): array {
    return [
        'openstreetmap' => [
            'name'        => 'OpenStreetMap',
            'attribution' => 'Place data from OpenStreetMap contributors, available under the ODbL.',
            'url'         => 'https://www.openstreetmap.org/copyright',
            'needs_key'   => false,
        ],
    ];
}

/** Metres between two points. Good enough for "is this the same building". */
function rmt_place_distance_m(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $r = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Strip a payload to the fields a provider is allowed to set, and refuse the ones it is not.
 *
 * @return array{data:array<string,mixed>,refused:list<string>}
 */
function rmt_place_import_clean(array $row): array {
    $data = [];
    $refused = [];
    foreach ($row as $k => $v) {
        if (!in_array($k, RMT_PLACE_IMPORT_FIELDS, true)) { $refused[] = (string) $k; continue; }
        if ($v === '' || $v === null) continue;
        $data[$k] = $v;
    }
    return ['data' => $data, 'refused' => $refused];
}

/**
 * Find the place this record already is, if any.
 *
 * @return array{id:int,how:string}|null
 */
function rmt_place_import_match(int $destId, array $data): ?array {
    $source = (string) ($data['data_source'] ?? '');
    $ref    = (string) ($data['source_ref'] ?? '');
    if ($source !== '' && $ref !== '') {
        $hit = q_one('SELECT id FROM places WHERE data_source = ? AND source_ref = ?', [$source, $ref]);
        if ($hit) return ['id' => (int) $hit['id'], 'how' => 'source_ref'];
    }

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') return null;
    $key = rmt_place_name_key($name);

    $hit = q_one('SELECT id FROM places WHERE destination_id = ? AND name_key = ?', [$destId, $key]);
    if ($hit) return ['id' => (int) $hit['id'], 'how' => 'name'];

    $hit = q_one('SELECT p.id FROM place_aliases a JOIN places p ON p.id = a.place_id
                   WHERE p.destination_id = ? AND a.alias_key = ?', [$destId, $key]);
    if ($hit) return ['id' => (int) $hit['id'], 'how' => 'alias'];

    /* Last pass: the same place under a slightly different spelling, in the same spot. Proximity
       alone is not enough -- two restaurants share a doorway -- so the names have to look like each
       other as well. */
    $lat = isset($data['lat']) ? (float) $data['lat'] : null;
    $lng = isset($data['lng']) ? (float) $data['lng'] : null;
    if ($lat !== null && $lng !== null) {
        foreach (q_all('SELECT id, name, name_key, lat, lng FROM places
                         WHERE destination_id = ? AND lat IS NOT NULL AND lng IS NOT NULL',
                       [$destId]) as $p) {
            $d = rmt_place_distance_m($lat, $lng, (float) $p['lat'], (float) $p['lng']);
            if ($d > RMT_PLACE_DEDUPE_METRES) continue;
            $a = (string) $p['name_key'];
            $b = $key;
            if ($a === '' || $b === '') continue;
            similar_text($a, $b, $pct);
            if ($pct >= 80.0 || str_contains($a, $b) || str_contains($b, $a)) {
                return ['id' => (int) $p['id'], 'how' => 'nearby'];
            }
        }
    }
    return null;
}

/**
 * Import one canonical record into one city.
 *
 * @param bool $dryRun when true nothing is written and the verdict is returned anyway
 * @return array{action:string,place_id:?int,how:?string,filled:list<string>,kept:list<string>,
 *               refused:list<string>,error:?string}
 */
function rmt_place_import_one(int $destId, array $row, bool $dryRun = false): array {
    $out = ['action' => 'skipped', 'place_id' => null, 'how' => null, 'filled' => [],
            'kept' => [], 'refused' => [], 'error' => null];

    $clean = rmt_place_import_clean($row);
    $data = $clean['data'];
    $out['refused'] = $clean['refused'];

    $name = trim((string) ($data['name'] ?? ''));
    $type = (string) ($data['type'] ?? '');
    if ($destId < 1)  { $out['error'] = 'No city.'; return $out; }
    if ($name === '') { $out['error'] = 'A place with no name is not a place.'; return $out; }
    if (!in_array($type, RMT_PLACE_TYPES, true)) { $out['error'] = 'Unknown type: ' . $type; return $out; }
    if (mb_strlen($name) > RMT_PLACE_NAME_MAX) $name = mb_substr($name, 0, RMT_PLACE_NAME_MAX);
    $data['name'] = $name;

    $source = (string) ($data['data_source'] ?? '');
    if ($source !== '' && !isset(rmt_place_providers()[$source])) {
        $out['error'] = 'Unknown provider: ' . $source;
        return $out;
    }

    /* Fit to be a page? Checked before anything is written: a bad row is far easier to refuse than
       to find later among four hundred good ones. */
    $fault = rmt_place_record_fault($data, $destId);
    if ($fault !== null) { $out['error'] = $name . ': ' . $fault; return $out; }

    /* The provider's own word for the thing is stored as it came; the category is resolved to the
       row this site already has, and dropped if the taxonomy does not know it. A category nobody
       defined is worse than none, because it sends somebody looking for a museum to a car park. */
    $catSlug = (string) ($data['category_slug'] ?? '');
    unset($data['category_slug']);
    $openingHours = (string) ($data['opening_hours'] ?? '');
    unset($data['opening_hours']);
    if ($catSlug !== '') {
        $cat = q_one("SELECT id FROM place_categories WHERE slug = ? AND status = 'active'", [$catSlug]);
        if ($cat) $data['category_id'] = (int) $cat['id'];
    }

    $match = rmt_place_import_match($destId, $data);
    $now = date('Y-m-d H:i:s');

    if (!$match) {
        $out['action'] = 'create';
        $out['filled'] = array_keys($data);
        if ($dryRun) return $out;

        // Asked directly rather than through dest_by_id(), which lives in controllers.php: this
        // file has to be loadable by a script and by a test without the whole application.
        $destName = (string) (q_one('SELECT name FROM destinations WHERE id = ?', [$destId])['name'] ?? '');
        /* The other names this place goes by, used only when its own name leaves the slugifier
           with nothing to work with. They are not written here: aliases are added by the caller
           after the row exists. */
        $alts = [];
        foreach ((array) ($row['aliases'] ?? []) as $alt) {
            if (is_string($alt) && trim($alt) !== '') $alts[] = trim($alt);
        }
        $slug = rmt_place_unique_slug($name, $destName, 0, $alts);
        /* name_norm is what the suggest box matches on, accents folded, so "geolog" finds
           "Museu Geologico". A row created without it is a place nobody can find by typing. */
        $cols = ['destination_id', 'slug', 'name', 'name_key', 'name_norm', 'status', 'created_at',
                 'updated_at', 'data_checked_at', 'source_updated_at'];
        $vals = [$destId, $slug, $name, rmt_place_name_key($name),
                 function_exists('rmt_search_norm') ? rmt_search_norm($name) : mb_strtolower($name),
                 'active', $now, $now, $now, $now];
        foreach ($data as $k => $v) {
            if ($k === 'name') continue;
            $cols[] = $k;
            $vals[] = $v;
        }
        q_run('INSERT INTO places (' . implode(',', $cols) . ') VALUES ('
              . implode(',', array_fill(0, count($cols), '?')) . ')', $vals);
        $out['place_id'] = (int) (q_one('SELECT id FROM places WHERE destination_id = ? AND name_key = ?',
                                        [$destId, rmt_place_name_key($name)])['id'] ?? 0);
        if ($openingHours !== '' && $out['place_id'] > 0 && function_exists('rmt_osm_hours_store')) {
            if (rmt_osm_hours_store($out['place_id'], $openingHours) > 0) $out['filled'][] = 'hours';
        }
        return $out;
    }

    /* An update. Timid on purpose: a value a person typed is never replaced by a provider, and a
       value a DIFFERENT provider supplied is left alone and reported rather than fought over. */
    $out['action'] = 'update';
    $out['place_id'] = $match['id'];
    $out['how'] = $match['how'];
    $cur = q_one('SELECT * FROM places WHERE id = ?', [$match['id']]);
    if (!$cur) { $out['error'] = 'Vanished mid-import.'; return $out; }

    $sameSource = $source !== '' && (string) ($cur['data_source'] ?? '') === $source;
    $set = [];
    $args = [];
    foreach ($data as $k => $v) {
        if (in_array($k, ['name', 'data_source', 'source_ref'], true)) continue;
        $have = $cur[$k] ?? null;
        $empty = $have === null || $have === '' ;
        if ($empty) {
            $set[] = "$k = ?"; $args[] = $v; $out['filled'][] = $k;
        } elseif ($sameSource && (string) $have !== (string) $v) {
            $set[] = "$k = ?"; $args[] = $v; $out['filled'][] = $k;
        } elseif ((string) $have !== (string) $v) {
            $out['kept'][] = $k;
        }
    }
    /* A row that predates the folded name column has a NULL in it, and the picker then compares a
       folded query against an unfolded name and finds nothing: "Museu Geologico" typed in full
       failed to find Museu Geológico. Filled on update as well as on create, so an old row heals
       the next time the importer touches it. */
    if (empty($cur['name_norm']) && function_exists('rmt_search_norm')) {
        $set[] = 'name_norm = ?';
        $args[] = rmt_search_norm((string) ($cur['name'] ?? $name));
        $out['filled'][] = 'name_norm';
    }

    // Claim the provider ref if the row has none, so the next run matches on it directly.
    if ($source !== '' && empty($cur['source_ref']) && !empty($data['source_ref'])) {
        $set[] = 'data_source = ?'; $args[] = $source;
        $set[] = 'source_ref = ?';  $args[] = (string) $data['source_ref'];
        $out['filled'][] = 'source_ref';
    }

    if ($dryRun) return $out;

    $set[] = 'data_checked_at = ?'; $args[] = $now;
    if ($sameSource || empty($cur['data_source'])) { $set[] = 'source_updated_at = ?'; $args[] = $now; }
    $set[] = 'updated_at = ?'; $args[] = $now;
    $args[] = $match['id'];
    q_run('UPDATE places SET ' . implode(', ', $set) . ' WHERE id = ?', $args);

    if ($openingHours !== '' && function_exists('rmt_osm_hours_store')) {
        if (rmt_osm_hours_store($match['id'], $openingHours) > 0) $out['filled'][] = 'hours';
    }

    // A name we matched but did not adopt becomes an alias, so the next spelling finds it faster.
    if (rmt_place_name_key($name) !== (string) $cur['name_key']) {
        rmt_place_alias_add($match['id'], $name, $source ?: null);
    }
    return $out;
}

/** Record another name a place goes by. Ignored when it is already known. */
function rmt_place_alias_add(int $placeId, string $alias, ?string $source = null): bool {
    $alias = trim($alias);
    if ($placeId < 1 || $alias === '') return false;
    $key = rmt_place_name_key($alias);
    if ($key === '') return false;
    if (q_one('SELECT 1 x FROM place_aliases WHERE place_id = ? AND alias_key = ?', [$placeId, $key])) {
        return false;
    }
    q_run('INSERT INTO place_aliases (place_id, alias, alias_key, source, created_at) VALUES (?,?,?,?,?)',
          [$placeId, mb_substr($alias, 0, RMT_PLACE_NAME_MAX), $key, $source, date('Y-m-d H:i:s')]);
    return true;
}

/**
 * Import a batch and report what happened to each record.
 *
 * @param list<array<string,mixed>> $rows
 * @return array{created:int,updated:int,skipped:int,errors:list<string>,details:list<array>}
 */
function rmt_place_import_batch(int $destId, array $rows, bool $dryRun = false): array {
    $sum = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'details' => []];
    foreach ($rows as $row) {
        $r = rmt_place_import_one($destId, $row, $dryRun);
        $sum['details'][] = $r;
        if ($r['error'] !== null) { $sum['skipped']++; $sum['errors'][] = $r['error']; continue; }
        if ($r['action'] === 'create') $sum['created']++;
        elseif ($r['action'] === 'update') $sum['updated']++;
        else $sum['skipped']++;
    }
    return $sum;
}

/**
 * Is this record fit to be a page?
 *
 * Checked before anything is written, because a bad row is far easier to refuse than to find later
 * among four hundred good ones. Everything here is a fact about the record itself, never a
 * judgement about the venue: a place is rejected for being malformed, never for being obscure.
 *
 * A rejected record is reported with a reason, so a run says what it would not take and somebody
 * can decide whether the rule or the data is wrong.
 *
 * @return ?string the reason to refuse, or null to accept
 */
function rmt_place_record_fault(array $data, int $destId): ?string {
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') return 'no name';
    if (mb_strlen($name) < 2) return 'name too short to be a name';
    if (preg_match('#^https?://#i', $name)) return 'a URL in the name field';
    if (preg_match('/^[\W_]+$/u', $name)) return 'a name with no letters or digits in it';
    /* Mojibake: a name that came through the wrong decoder reads as Ã© rather than é, and the page
       it makes looks broken in a way no reader will report. */
    if (str_contains($name, 'Ã') || str_contains($name, 'â€') || str_contains($name, "\u{FFFD}")) {
        return 'the name looks like it was decoded wrongly';
    }

    $lat = $data['lat'] ?? null;
    $lng = $data['lng'] ?? null;
    if ($lat !== null || $lng !== null) {
        if ($lat === null || $lng === null) return 'half a coordinate';
        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return 'a coordinate off the planet';
        // 0,0 is in the Atlantic and is what a missing coordinate looks like when somebody used zero.
        if (abs($lat) < 0.0001 && abs($lng) < 0.0001) return 'null island';

        /* Far from the city it claims to be in. A bounding box query should make this impossible,
           and it happens anyway when a provider record carries the wrong point. */
        $d = q_one('SELECT lat, lng FROM destinations WHERE id = ?', [$destId]);
        if ($d && $d['lat'] !== null && $d['lng'] !== null) {
            $km = rmt_place_distance_m((float) $d['lat'], (float) $d['lng'], $lat, $lng) / 1000;
            if ($km > 80) return 'more than 80km from the city it is filed under';
        }
    }

    $url = trim((string) ($data['website_url'] ?? ''));
    if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) return 'a website that is not a URL';
    if ($url !== '' && !preg_match('#^https?://#i', $url)) return 'a website with no usable scheme';

    return null;
}
