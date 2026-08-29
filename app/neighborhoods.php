<?php
/**
 * Neighborhoods as entities, with aliases, scoped to a destination.
 *
 * The problem this solves is identity, not display. A place carries whatever text its source used
 * for the area it sits in, and sources disagree: "1st Arrondissement", "Paris 1er Arrondissement"
 * and "1er" are one area written three ways, and once they are three strings in a column no
 * template can put them back together, because nothing ever recorded that they were the same
 * thing. So identity is recorded once, here, and every variant points at it.
 *
 * Two rules govern everything below.
 *
 * NOTHING IS GUESSED. A place is attached to a neighborhood only when its raw text matches a known
 * alias exactly, after normalisation. There is no fuzzy matching, no nearest-string, no "probably
 * Le Marais because it is close". An unresolved place keeps its raw text, still shows it, and
 * waits. A browse module that is smaller and correct beats one that is fuller and invented.
 *
 * KIND IS HONEST. OSM answers "which area" with whatever administrative unit it happens to hold,
 * so the real data contains Municipio Roma I, Municipio 1, Manhattan and Venezia-Murano-Burano
 * next to Kreuzberg and Maxvorstadt. A borough is not a neighborhood and a comune is not a
 * neighborhood. Those are kept, labelled for what they are, and left out of neighborhood browsing
 * rather than deleted or quietly renamed into something more marketable.
 */

declare(strict_types=1);

/** What an area can be. Only the browsable ones are offered as a way to explore a destination. */
const RMT_NB_KINDS = ['neighborhood', 'district', 'borough', 'administrative'];

/** Kinds a traveler would actually browse by. A borough is a postal fact, not a night out. */
const RMT_NB_BROWSABLE = ['neighborhood', 'district'];

/**
 * How many places an area needs before it is worth offering as a way to browse.
 *
 * One is an address, not an area. A "neighborhood" naming exactly one venue tells a traveler
 * nothing they did not already know from the venue's own page, and a list of twelve such rows is
 * a directory of addresses pretending to be a guide.
 */
const RMT_NB_MIN_PLACES = 2;

/**
 * The form two spellings of one area have in common.
 *
 * Deliberately aggressive: case, accents, punctuation and the words that decorate an area name
 * without identifying it all disappear, because they are exactly what differs between sources.
 * "Paris 1er Arrondissement", "1er arrondissement" and "1ER ARR." are one key. Matching happens on
 * this; display never does.
 */
function rmt_nb_key(string $s, string $destName = ''): string {
    $orig = trim($s);
    $s = rmt_search_norm($s);                       // lowercase, accents folded, one source of truth
    $s = str_replace(['-', '_', '/', '.', ','], ' ', $s);
    $s = preg_replace('/[^a-z0-9 ]+/', '', $s) ?? '';
    // Ordinals and their spelled forms are the same number. 1st = 1er = 1e = 1.
    $s = preg_replace('/\b(\d+)\s*(st|nd|rd|th|er|e|eme|ème|o|a)\b/u', '$1', $s) ?? $s;
    // Words that describe the KIND of area rather than which one it is.
    $s = preg_replace('/\b(arrondissement|arrondissements|district|districts|quarter|neighbourhood|neighborhood|borough|barrio|bairro|quartier|stadtteil|bezirk|ward|municipio|distrito|sestiere|synoikia)\b/u', ' ', $s) ?? $s;
    $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');

    // Everything above assumes the Latin alphabet, and half the world does not use it. Petralona is
    // written Πετράλωνα by the source we import from, and stripping to [a-z0-9] leaves nothing at
    // all -- so the area had no key, could not be stored as an alias, and could never be matched.
    // When that happens, key the string as itself: lowercased, punctuation dropped, spacing
    // collapsed. This does NOT merge Πετράλωνα with Petralona and is not meant to; that pairing is
    // a fact about Greek, and facts about languages belong in the alias table where a human puts
    // them. What it does is make the local-language spelling addressable at all.
    if ($s === '') {
        $raw = function_exists('mb_strtolower') ? mb_strtolower($orig, 'UTF-8') : strtolower($orig);
        $raw = preg_replace('/[\p{P}\p{S}]+/u', ' ', $raw) ?? $raw;
        $s = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
    }

    // Sources vary on whether the city is part of the area's name: OSM may say "Paris 1er
    // Arrondissement" where an editor writes "1st Arrondissement". Dropping a LEADING city name is
    // what makes those one key without needing a curated alias for every city in the world. Only
    // when something is left afterwards -- an area genuinely called after its city, like Vatican
    // City, must keep its name rather than become the empty string.
    // Guarded, not merely optional: normalising the city name means calling this function, and
    // without the check the empty-string case calls itself forever.
    $city = $destName === '' ? '' : rmt_nb_key($destName);
    if ($city !== '' && str_starts_with($s . ' ', $city . ' ')) {
        $rest = trim(substr($s, strlen($city)));
        if ($rest !== '') $s = $rest;
    }
    return $s;
}

/** A url-safe name for an area, unique per destination by construction of the caller. */
function rmt_nb_slug(string $name): string {
    $s = rmt_search_norm($name);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

/**
 * The canonical area a raw string names, within one destination -- or null, which is a normal
 * answer and not a failure.
 */
function rmt_nb_resolve(int $destId, string $raw, ?string $destName = null): ?array {
    if ($destName === null) {
        $d = q_one("SELECT name FROM destinations WHERE id = ?", [$destId]);
        $destName = $d ? (string) $d['name'] : '';
    }
    $key = rmt_nb_key($raw, $destName);
    if ($key === '') return null;
    $row = q_one(
        "SELECT n.* FROM neighborhood_aliases a
           JOIN neighborhoods n ON n.id = a.neighborhood_id
          WHERE a.destination_id = ? AND a.alias_key = ?",
        [$destId, $key]);
    return $row ?: null;
}

/** One area by destination and slug, for its page. */
function rmt_nb_find(int $destId, string $slug): ?array {
    return q_one("SELECT * FROM neighborhoods WHERE destination_id = ? AND slug = ?", [$destId, $slug]) ?: null;
}

/**
 * Create or update one canonical area, and record every way of writing it.
 *
 * Idempotent: running it again with the same input changes nothing, which is what lets the seed be
 * re-applied on every deploy without accumulating duplicates. The canonical name is always its own
 * alias, so a source that already uses our preferred wording resolves without a special case.
 *
 * @param list<string> $aliases
 * @return array{id:int,created:bool,aliases_added:int}
 */
function rmt_nb_upsert(int $destId, string $canonical, array $aliases = [], array $opts = []): array {
    $slug = $opts['slug'] ?? rmt_nb_slug($canonical);
    $kind = (string) ($opts['kind'] ?? 'neighborhood');
    if (!in_array($kind, RMT_NB_KINDS, true)) $kind = 'neighborhood';

    $d = q_one("SELECT name FROM destinations WHERE id = ?", [$destId]);
    $destName = $d ? (string) $d['name'] : '';

    $existing = q_one("SELECT * FROM neighborhoods WHERE destination_id = ? AND slug = ?", [$destId, $slug]);
    if ($existing) {
        $id = (int) $existing['id'];
        q_run("UPDATE neighborhoods SET canonical_name = ?, local_name = ?, kind = ?, blurb = ?,
                      lat = COALESCE(?, lat), lng = COALESCE(?, lng), updated_at = ?
                WHERE id = ?",
              [$canonical, $opts['local_name'] ?? $existing['local_name'], $kind,
               $opts['blurb'] ?? $existing['blurb'], $opts['lat'] ?? null, $opts['lng'] ?? null,
               date('Y-m-d H:i:s'), $id]);
        $created = false;
    } else {
        $id = (int) q_run("INSERT INTO neighborhoods (destination_id, slug, canonical_name, local_name, kind, lat, lng, blurb, created_at, updated_at)
                           VALUES (?,?,?,?,?,?,?,?,?,?)",
                          [$destId, $slug, $canonical, $opts['local_name'] ?? null, $kind,
                           $opts['lat'] ?? null, $opts['lng'] ?? null, $opts['blurb'] ?? null,
                           date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $created = true;
    }

    $added = 0;
    foreach (array_merge([$canonical], (string) ($opts['local_name'] ?? '') !== '' ? [(string) $opts['local_name']] : [], $aliases) as $alias) {
        $alias = trim((string) $alias);
        $key = rmt_nb_key($alias, $destName);
        if ($alias === '' || $key === '') continue;
        $owner = q_one("SELECT neighborhood_id FROM neighborhood_aliases WHERE destination_id = ? AND alias_key = ?",
                       [$destId, $key]);
        if ($owner) {
            // A spelling that already means a DIFFERENT area is a curation mistake, not something
            // to silently reassign: reassigning it would move places between areas on the next
            // resolve pass.
            if ((int) $owner['neighborhood_id'] !== $id) {
                throw new RuntimeException(sprintf(
                    'alias "%s" in destination %d already belongs to neighborhood %d, not %d',
                    $alias, $destId, (int) $owner['neighborhood_id'], $id));
            }
            continue;
        }
        q_run("INSERT INTO neighborhood_aliases (neighborhood_id, destination_id, alias, alias_key, source, created_at)
               VALUES (?,?,?,?,?,?)",
              [$id, $destId, $alias, $key, (string) ($opts['source'] ?? 'curated'), date('Y-m-d H:i:s')]);
        $added++;
    }
    return ['id' => $id, 'created' => $created, 'aliases_added' => $added];
}

/**
 * Attach places to canonical areas by exact alias match.
 *
 * Only ever fills a null. A place already pointing at an area is left alone, so an editor's
 * decision is never overwritten by a later import -- the same rule enrichment follows for every
 * other held field.
 *
 * @return array{matched:int,unmatched:int,unresolved:array<string,int>}
 */
function rmt_nb_attach_places(bool $apply = false): array {
    $rows = q_all("SELECT p.id, p.destination_id, p.neighborhood, d.name dest_name FROM places p
                     JOIN destinations d ON d.id = p.destination_id
                    WHERE p.status = 'active' AND p.neighborhood_id IS NULL
                      AND p.neighborhood IS NOT NULL AND p.neighborhood <> ''");
    $matched = 0; $unresolved = [];
    foreach ($rows as $r) {
        $nb = rmt_nb_resolve((int) $r['destination_id'], (string) $r['neighborhood'], (string) $r['dest_name']);
        if (!$nb) {
            $k = (string) $r['neighborhood'];
            $unresolved[$k] = ($unresolved[$k] ?? 0) + 1;
            continue;
        }
        if ($apply) q_run("UPDATE places SET neighborhood_id = ? WHERE id = ?", [(int) $nb['id'], (int) $r['id']]);
        $matched++;
    }
    arsort($unresolved);
    return ['matched' => $matched, 'unmatched' => array_sum($unresolved), 'unresolved' => $unresolved];
}

/**
 * The areas of a destination worth offering as a way to browse.
 *
 * Counts are real counts of active places actually attached. An area below the threshold, or of a
 * kind nobody browses by, simply is not returned -- there are no empty shells and no rows that
 * exist to make the module look populated.
 *
 * @return list<array{id:int,slug:string,name:string,local_name:?string,places:int}>
 */
function rmt_nb_for_destination(int $destId, int $limit = 12, int $min = RMT_NB_MIN_PLACES): array {
    // The threshold is inlined as an integer rather than bound. A bound parameter arrives as a
    // string, and "COUNT(*) >= '2'" is a type comparison rather than a numeric one -- it silently
    // matched nothing on SQLite. Both values are ints from the signature, so there is nothing to
    // inject.
    $kinds = "'" . implode("','", RMT_NB_BROWSABLE) . "'";
    return q_all(
        "SELECT n.id, n.slug, n.canonical_name name, n.local_name, COUNT(p.id) places
           FROM neighborhoods n
           JOIN places p ON p.neighborhood_id = n.id AND p.status = 'active'
          WHERE n.destination_id = ? AND n.kind IN ($kinds)
          GROUP BY n.id, n.slug, n.canonical_name, n.local_name
         HAVING COUNT(p.id) >= " . max(1, $min) . "
          ORDER BY COUNT(p.id) DESC, n.canonical_name
          LIMIT " . max(1, $limit), [$destId]);
}

/**
 * The wider areas of a destination: boroughs and administrative units with real places in them.
 *
 * Manhattan holds more of our places than any actual neighborhood does, and it is still not a
 * neighborhood. Calling it one would be a small lie told for the sake of a fuller module; leaving
 * it entirely unreachable makes a real page with six places in it that nothing links to. So it is
 * offered under its own heading, labelled as what it is.
 *
 * @return list<array{id:int,slug:string,name:string,kind:string,places:int}>
 */
function rmt_nb_wider_for_destination(int $destId, int $limit = 8): array {
    $kinds = "'" . implode("','", RMT_NB_BROWSABLE) . "'";
    return q_all(
        "SELECT n.id, n.slug, n.canonical_name name, n.kind, COUNT(p.id) places
           FROM neighborhoods n
           JOIN places p ON p.neighborhood_id = n.id AND p.status = 'active'
          WHERE n.destination_id = ? AND n.kind NOT IN ($kinds)
          GROUP BY n.id, n.slug, n.canonical_name, n.kind
         HAVING COUNT(p.id) >= " . RMT_NB_MIN_PLACES . "
          ORDER BY COUNT(p.id) DESC, n.canonical_name
          LIMIT " . max(1, $limit), [$destId]);
}

/**
 * Areas that exist but are not browsable -- too few places, or an administrative unit.
 *
 * For the admin coverage view. The point is that they are visible as data rather than invisible as
 * an omission: "Rome has one neighborhood" is a fact somebody should be able to check.
 */
function rmt_nb_dormant(int $destId): array {
    $kinds = "'" . implode("','", RMT_NB_BROWSABLE) . "'";
    return q_all(
        "SELECT n.slug, n.canonical_name name, n.kind, COUNT(p.id) places
           FROM neighborhoods n
           LEFT JOIN places p ON p.neighborhood_id = n.id AND p.status = 'active'
          WHERE n.destination_id = ?
          GROUP BY n.id, n.slug, n.canonical_name, n.kind
         HAVING COUNT(p.id) < " . RMT_NB_MIN_PLACES . " OR n.kind NOT IN ($kinds)
          ORDER BY COUNT(p.id) DESC, n.canonical_name", [$destId]);
}

/** Active places in one area, newest listing first, for the area's page. */
function rmt_nb_places(int $nbId, ?string $type = null, int $limit = 60): array {
    $args = [$nbId];
    $where = '';
    if ($type !== null && $type !== '') { $where = ' AND p.type = ?'; $args[] = $type; }
    return q_all(
        "SELECT p.id, p.slug, p.name, p.type, p.price_level, p.neighborhood, p.street_address,
                p.lat, p.lng, p.category_id
           FROM places p
          WHERE p.neighborhood_id = ? AND p.status = 'active'" . $where . "
          ORDER BY p.name
          LIMIT " . max(1, $limit), $args);
}

/** How many active places an area holds, by type, for its page's own summary. */
function rmt_nb_type_counts(int $nbId): array {
    $out = [];
    foreach (q_all("SELECT type, COUNT(*) n FROM places WHERE neighborhood_id = ? AND status = 'active' GROUP BY type", [$nbId]) as $r) {
        $out[(string) $r['type']] = (int) $r['n'];
    }
    return $out;
}

/**
 * Raw neighborhood text nobody has mapped yet, across the whole site.
 *
 * This is the working queue, and it being non-empty is the normal state rather than a defect: a
 * variant nobody has seen before should sit here until a human decides what it means.
 */
function rmt_nb_unmapped(int $limit = 60): array {
    return q_all(
        "SELECT d.name city, d.slug dest_slug, p.neighborhood, COUNT(*) places
           FROM places p JOIN destinations d ON d.id = p.destination_id
          WHERE p.status = 'active' AND p.neighborhood_id IS NULL
            AND p.neighborhood IS NOT NULL AND p.neighborhood <> ''
          GROUP BY d.name, d.slug, p.neighborhood
          ORDER BY COUNT(*) DESC, d.name, p.neighborhood
          LIMIT " . max(1, $limit));
}
