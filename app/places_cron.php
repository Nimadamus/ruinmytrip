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
 *   import  one city and one kind at a time, fetching from the provider here.
 *   ingest  the same import, with the rows posted in, for when fetching here is unreliable.
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
    $out['with_hours'] = (int) (q_one('SELECT COUNT(DISTINCT h.place_id) c FROM place_hours h
                                         JOIN places p ON p.id = h.place_id
                                        WHERE p.destination_id = ?', [$destId])['c'] ?? 0);

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

    /* Names a person should look at. Empty, a URL somebody mapped into a name field, or a name
       carrying a character that reads as a typo in the source: "Mercado Munici+al" is a real venue
       whose name is misspelt in OpenStreetMap, and we copy what the source says rather than
       correcting it, so the right response is to show it to somebody rather than to guess. */
    $rows = q_all("SELECT id, name, slug FROM places
                    WHERE destination_id = ? AND (TRIM(COALESCE(name,'')) = ''
                       OR name LIKE 'http%' OR LENGTH(name) < 2
                       OR name LIKE '%+%' OR name LIKE '%  %'
                       OR name LIKE '%?%' OR name LIKE '%|%') LIMIT 40", [$destId]);
    /* "Craft + Carry" and "Bar + Bistro" are real names of real places. "Mercado Munici+al" is a
       typo in the source. A plus between words is ordinary; a plus INSIDE a word is the one worth
       a person's attention, and an audit that cries wolf on two thirds of its findings stops being
       read. The narrowing happens here rather than in SQL because neither driver has the same
       regular expressions. */
    $out['odd_names'] = [];
    foreach ($rows as $r) {
        $name = (string) $r['name'];
        if (str_contains($name, '+') && !preg_match('/\p{L}\+\p{L}/u', $name)) continue;
        $out['odd_names'][] = $r;
        if (count($out['odd_names']) >= 20) break;
    }
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
    /* Fill in any normalised names that are missing, which is what makes a place
       findable by typing without the accents. Idempotent: it only touches rows whose
       folded name is absent or no longer matches what the normaliser produces. */
    if ($op === 'backfill') {
        $n = function_exists('rmt_search_backfill_norm') ? rmt_search_backfill_norm() : [];
        echo json_encode($n), "
";
        return;
    }

    /* Re-derive every category from the provider's own word, using the mapping as it stands today.
       This is what source_kind is for: a mapping is a decision, decisions get revised, and the
       alternative to storing the raw kind was asking the provider for four hundred rows again.
       A kind the mapping no longer recognises has its category cleared rather than left stale. */
    if ($op === 'recategorize') {
        $slugs = [];
        foreach (q_all("SELECT id, slug FROM place_categories WHERE status = 'active'") as $c) {
            $slugs[(string) $c['slug']] = (int) $c['id'];
        }
        $changed = 0;
        $cleared = 0;
        foreach (q_all("SELECT id, source_kind, category_id FROM places
                         WHERE destination_id = ? AND COALESCE(source_kind,'') <> ''",
                       [(int) $dest['id']]) as $row) {
            $want = rmt_osm_category_slug((string) $row['source_kind']);
            $wantId = $want !== null ? ($slugs[$want] ?? null) : null;
            if ((int) ($row['category_id'] ?? 0) === (int) ($wantId ?? 0)) continue;
            q_run('UPDATE places SET category_id = ?, updated_at = ? WHERE id = ?',
                  [$wantId, date('Y-m-d H:i:s'), (int) $row['id']]);
            if ($wantId === null) $cleared++; else $changed++;
        }
        echo json_encode(['recategorized' => $changed, 'cleared' => $cleared]), "
";
        return;
    }

    /* Remove one imported place, addressed by the provider's own record id.
     *
     * Narrow on purpose. It takes a city and an exact source_ref, it only ever touches a row that
     * came from a registered provider, and it names what it removed. A place somebody added by hand
     * has no source_ref and cannot be reached by this at all, which is the point: this is for
     * undoing an import, not for deleting content.
     */
    if ($op === 'forget') {
        $ref = trim((string) input('ref'));
        if ($ref === '') { echo "a source_ref is required
"; return; }
        $row = q_one("SELECT id, name, slug FROM places
                       WHERE destination_id = ? AND source_ref = ? AND COALESCE(data_source,'') <> ''",
                     [(int) $dest['id'], $ref]);
        if (!$row) { echo "no imported place with that record id here
"; return; }
        q_run('DELETE FROM place_hours WHERE place_id = ?', [(int) $row['id']]);
        q_run('DELETE FROM place_aliases WHERE place_id = ?', [(int) $row['id']]);
        q_run('DELETE FROM places WHERE id = ?', [(int) $row['id']]);
        echo 'forgot ', (string) $row['name'], ' (', (string) $row['slug'], ")
";
        return;
    }

    if ($op === 'verify') {
        echo json_encode(rmt_places_verify((int) $dest['id']),
                         JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
        return;
    }
    /* Ingest: canonical rows posted in, rather than fetched here.
     *
     * The provider is one way to get rows; it is not the only way, and on a shared cloud address
     * it is an unreliable one, because the public Overpass instance queues per address and a
     * request can sit behind every other tenant of the platform until it times out. Fetching can
     * happen anywhere with an internet connection; writing has to happen where the database
     * credentials are. This is the seam between the two.
     *
     * It is not a wider door. Rows go through exactly the same rmt_place_import_one(): the field
     * whitelist still drops anything that is not a fact, the provider must still be a registered
     * one, the type must still be real, and deduplication still runs. Somebody holding this key
     * can add places from a source we already trust. They cannot invent a rating.
     */
    if ($op === 'ingest') {
        $raw = file_get_contents('php://input') ?: '';
        $in = json_decode($raw, true);
        if (!is_array($in) || !isset($in['rows']) || !is_array($in['rows'])) {
            echo "expected a JSON body with a rows array
";
            return;
        }
        if (count($in['rows']) > 200) { echo "at most 200 rows in one request
"; return; }
        $dry = (string) input('dry') === '1';
        $res = rmt_place_import_batch((int) $dest['id'], $in['rows'], $dry);

        $aliases = 0;
        if (!$dry && !empty($in['aliases']) && is_array($in['aliases'])) {
            foreach ($res['details'] as $i => $d) {
                $ref = (string) ($in['rows'][$i]['source_ref'] ?? '');
                if (!$d['place_id'] || $ref === '') continue;
                foreach ((array) ($in['aliases'][$ref] ?? []) as $alias) {
                    if (rmt_place_alias_add((int) $d['place_id'], (string) $alias, 'openstreetmap')) $aliases++;
                }
            }
        }
        $matched = [];
        $refused = [];
        foreach ($res['details'] as $d) {
            if ($d['action'] === 'update' && $d['how']) $matched[$d['how']] = ($matched[$d['how']] ?? 0) + 1;
            foreach ($d['refused'] as $f) $refused[$f] = ($refused[$f] ?? 0) + 1;
        }
        printf("offered=%d created=%d updated=%d skipped=%d aliases=%d%s%s
",
               count($in['rows']), $res['created'], $res['updated'], $res['skipped'], $aliases,
               $matched ? ' matched_by=' . json_encode($matched) : '',
               $refused ? ' refused_fields=' . json_encode($refused) : '');
        foreach (array_slice(array_unique($res['errors']), 0, 5) as $e) printf("  ! %s
", $e);
        echo $dry ? "dry run, nothing written
" : "done
";
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
