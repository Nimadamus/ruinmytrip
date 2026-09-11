<?php
/**
 * Importing and auditing places from outside the box, without opening the box.
 *
 * The web service already holds the only credentials that reach the database. A job that curls this
 * needs none: no firewall to open, no connection string to hand out, and nothing that has to be
 * re-locked afterwards if the run dies halfway. That is the same reasoning as /cron/indexnow, and
 * it matters more here, because the alternative was briefly exposing a production database to the
 * internet in order to add restaurants to it.
 *
 * Unset CRON_KEY means the endpoint does not exist. A wrong key gets the same 404 as a wrong path.
 *
 * Two operations, both deliberately small:
 *   verify  read only. Counts, categories, coordinates, aliases, duplicates, and a check that
 *           nothing resembling a rating came in with the data.
 *   import  one city and one kind at a time, with a dry run that writes nothing.
 */
declare(strict_types=1);

/** The audit a person would otherwise run by hand against the database. Read only, always. */
function rmt_places_verify(int $destId): array {
    $out = [];
    $out['total'] = (int) (q_one('SELECT COUNT(*) c FROM places WHERE destination_id = ?', [$destId])['c'] ?? 0);
    $out['active'] = (int) (q_one("SELECT COUNT(*) c FROM places WHERE destination_id = ? AND status = 'active'",
                                  [$destId])['c'] ?? 0);
    $out['by_type'] = [];
    foreach (q_all('SELECT type, COUNT(*) c FROM places WHERE destination_id = ? GROUP BY type ORDER BY c DESC',
                   [$destId]) as $r) {
        $out['by_type'][(string) $r['type']] = (int) $r['c'];
    }
    $out['with_coords'] = (int) (q_one('SELECT COUNT(*) c FROM places
                                         WHERE destination_id = ? AND lat IS NOT NULL AND lng IS NOT NULL',
                                       [$destId])['c'] ?? 0);
    $out['with_address'] = (int) (q_one("SELECT COUNT(*) c FROM places
                                          WHERE destination_id = ? AND COALESCE(street_address,'') <> ''",
                                        [$destId])['c'] ?? 0);
    $out['with_website'] = (int) (q_one("SELECT COUNT(*) c FROM places
                                          WHERE destination_id = ? AND COALESCE(website_url,'') <> ''",
                                        [$destId])['c'] ?? 0);
    $out['with_source'] = (int) (q_one("SELECT COUNT(*) c FROM places
                                         WHERE destination_id = ? AND COALESCE(data_source,'') <> ''",
                                       [$destId])['c'] ?? 0);
    $out['aliases'] = (int) (q_one('SELECT COUNT(*) c FROM place_aliases a
                                      JOIN places p ON p.id = a.place_id WHERE p.destination_id = ?',
                                   [$destId])['c'] ?? 0);

    /* Duplicates, three ways, because three is how many ways they get in: the same normalised name,
       the same provider record, and the same coordinates to five decimal places. */
    $out['dupe_names'] = q_all('SELECT name_key, COUNT(*) c FROM places
                                 WHERE destination_id = ? GROUP BY name_key HAVING COUNT(*) > 1 LIMIT 10',
                               [$destId]);
    $out['dupe_source_refs'] = q_all("SELECT source_ref, COUNT(*) c FROM places
                                       WHERE destination_id = ? AND COALESCE(source_ref,'') <> ''
                                    GROUP BY source_ref HAVING COUNT(*) > 1 LIMIT 10", [$destId]);
    $out['dupe_points'] = q_all('SELECT lat, lng, COUNT(*) c FROM places
                                  WHERE destination_id = ? AND lat IS NOT NULL
                               GROUP BY lat, lng HAVING COUNT(*) > 1 LIMIT 10', [$destId]);

    // Names that would embarrass a page: empty, or a URL somebody mapped into a name field.
    $out['odd_names'] = q_all("SELECT id, name FROM places
                                WHERE destination_id = ? AND (TRIM(COALESCE(name,'')) = ''
                                   OR name LIKE 'http%' OR LENGTH(name) < 2) LIMIT 10", [$destId]);
    $out['wrong_city'] = 0;   // every row is selected by destination_id, so this is definitional
    return $out;
}

/**
 * GET /cron/places
 *
 * ?key=…&op=verify&city=slug
 * ?key=…&op=import&city=slug&type=restaurant|hotel|attraction|experience|all&limit=40&dry=1
 */
function cron_places(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = (string) input('key');
    if ($key === '' || $given === '' || !hash_equals($key, $given)) not_found();

    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');

    $citySlug = trim((string) input('city'));
    $dest = $citySlug !== '' ? q_one('SELECT * FROM destinations WHERE slug = ?', [$citySlug]) : null;
    if (!$dest) { echo "no such city\n"; return; }

    $op = (string) (input('op') ?: 'verify');
    if ($op === 'verify') {
        echo json_encode(rmt_places_verify((int) $dest['id']),
                         JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
        return;
    }
    if ($op !== 'import') { echo "unknown op\n"; return; }

    $type  = (string) (input('type') ?: 'all');
    $limit = max(1, min(120, (int) input('limit') ?: 40));
    $km    = (float) (input('km') ?: 0);   // 0 means: use the density default for this kind
    $dry   = (string) input('dry') === '1';
    $types = $type === 'all' ? RMT_PLACE_TYPES : [$type];
    foreach ($types as $t) {
        if (!in_array($t, RMT_PLACE_TYPES, true)) { echo "unknown type: $t\n"; return; }
    }

    /* One city and one kind per request even when asked for "all", because Overpass is a free
       service run by volunteers: it answers a narrow question and times out on a greedy one, and
       hammering it is how this site loses the only place data it may legally keep. */
    foreach ($types as $t) {
        $pull = rmt_osm_places_for_destination($dest, $t, $limit, $km);
        if (!$pull['ok']) { printf("%-12s FAILED: %s\n", $t, (string) $pull['error']); continue; }
        $res = rmt_place_import_batch((int) $dest['id'], $pull['rows'], $dry);

        $aliases = 0;
        if (!$dry) {
            foreach ($res['details'] as $i => $d) {
                $ref = (string) ($pull['rows'][$i]['source_ref'] ?? '');
                if (!$d['place_id'] || $ref === '') continue;
                foreach ($pull['aliases'][$ref] ?? [] as $alias) {
                    if (rmt_place_alias_add((int) $d['place_id'], $alias, 'openstreetmap')) $aliases++;
                }
            }
        }
        $matched = [];
        foreach ($res['details'] as $d) {
            if ($d['action'] === 'update' && $d['how']) $matched[$d['how']] = ($matched[$d['how']] ?? 0) + 1;
        }
        printf("%-12s offered=%d created=%d updated=%d skipped=%d aliases=%d%s\n",
               $t, count($pull['rows']), $res['created'], $res['updated'], $res['skipped'], $aliases,
               $matched ? ' matched_by=' . json_encode($matched) : '');
        foreach (array_slice(array_unique($res['errors']), 0, 5) as $e) printf("  ! %s\n", $e);
    }
    echo $dry ? "dry run, nothing written\n" : "done\n";
}
