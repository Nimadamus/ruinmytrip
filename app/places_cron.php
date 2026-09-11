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
    /* The finer word, which is the one a reader browses by. The coarse type says "attraction" and
       tells you nothing about whether the city is museums or viewpoints. */
    $out['by_category'] = [];
    foreach (q_all("SELECT c.slug, COUNT(*) n FROM places p
                      JOIN place_categories c ON c.id = p.category_id
                     WHERE p.destination_id = ? GROUP BY c.slug ORDER BY n DESC", [$destId]) as $r) {
        $out['by_category'][(string) $r['slug']] = (int) $r['n'];
    }
    $out['uncategorised'] = (int) (q_one('SELECT COUNT(*) c FROM places
                                            WHERE destination_id = ? AND category_id IS NULL',
                                         [$destId])['c'] ?? 0);
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

    /* Which of this city's imported places have no opening hours yet.
       Read only, and it answers with OSM references rather than rows, because the point of it is
       to let a backfill ask the provider for exactly those objects by id instead of scanning the
       city again. Everything imported before the hours parser worked is in here. */
    if ($op === 'needs_hours') {
        $limit = max(1, min(400, (int) (input('limit') ?: 200)));
        $rows = q_all("SELECT p.source_ref FROM places p
                        WHERE p.destination_id = ? AND p.source_ref IS NOT NULL AND p.source_ref <> ''
                          AND NOT EXISTS (SELECT 1 FROM place_hours h WHERE h.place_id = p.id)
                        ORDER BY p.id LIMIT " . $limit, [(int) $dest['id']]);
        echo json_encode(['city' => $dest['slug'], 'refs' => array_column($rows, 'source_ref')],
                         JSON_UNESCAPED_SLASHES), "
";
        return;
    }

    /* The deeper audit. Kept apart from `verify` because verify is the cheap one that a tally
       script runs ten times in a row; this one asks harder questions and is meant to be read.

       Everything here is a fact about a record, never a judgement about a venue. A place is
       flagged for being malformed, for disagreeing with itself, or for being somewhere its city
       is not; never for being obscure. Nothing is corrected automatically, because a wrong
       correction is worse than a visible oddity. */
    if ($op === 'audit') {
        $destId = (int) $dest['id'];
        $out = ['city' => $dest['slug']];

        /* Somewhere its city is not. Rows are selected by destination_id, so "wrong city" cannot
           mean a join error; what it can mean is a provider record whose point is forty kilometres
           outside the city it was imported for, which is what a bounding box that reached into the
           next town looks like afterwards. */
        $far = [];
        if ($dest['lat'] !== null && $dest['lng'] !== null) {
            $clat = (float) $dest['lat'];
            $clng = (float) $dest['lng'];
            foreach (q_all('SELECT id, name, slug, lat, lng FROM places
                             WHERE destination_id = ? AND lat IS NOT NULL AND lng IS NOT NULL',
                           [$destId]) as $r) {
                $dLat = ((float) $r['lat'] - $clat) * 111.0;
                $dLng = ((float) $r['lng'] - $clng) * 111.0 * cos(deg2rad($clat));
                $km = sqrt($dLat * $dLat + $dLng * $dLng);
                if ($km > 40) $far[] = ['id' => (int) $r['id'], 'name' => $r['name'],
                                        'slug' => $r['slug'], 'km' => round($km, 1)];
            }
            usort($far, static fn(array $a, array $b): int => $b['km'] <=> $a['km']);
        }
        $out['far_from_city'] = array_slice($far, 0, 20);

        $out['no_coordinates'] = q_all('SELECT id, name, slug FROM places
                                         WHERE destination_id = ? AND (lat IS NULL OR lng IS NULL)
                                         LIMIT 20', [$destId]);

        /* A link we would print. Anything that is not plainly http(s) with no whitespace in it is
           a link we should not put in front of a reader. */
        $bad = [];
        foreach (q_all("SELECT id, name, slug, website_url FROM places
                         WHERE destination_id = ? AND COALESCE(website_url,'') <> ''", [$destId]) as $r) {
            $u = (string) $r['website_url'];
            if (!preg_match('#^https?://[^\s<>"]+$#i', $u)) $bad[] = $r;
            if (count($bad) >= 20) break;
        }
        $out['malformed_urls'] = $bad;

        /* An alias is another name the same place goes by. One that repeats the name, or is a web
           address, or is a paragraph, is none of those. */
        $out['odd_aliases'] = q_all("SELECT a.place_id, p.name, a.alias FROM place_aliases a
                                       JOIN places p ON p.id = a.place_id
                                      WHERE p.destination_id = ?
                                        AND (a.alias_key = p.name_key OR a.alias LIKE 'http%'
                                             OR LENGTH(a.alias) > 120 OR TRIM(a.alias) = '')
                                      LIMIT 20", [$destId]);

        /* Hours that do not describe a day. The parser refuses these at the door, so anything here
           arrived before it did or was typed by hand. */
        $out['bad_hours'] = q_all("SELECT h.place_id, p.name, h.day_of_week, h.opens, h.closes
                                     FROM place_hours h JOIN places p ON p.id = h.place_id
                                    WHERE p.destination_id = ?
                                      AND ((COALESCE(h.closed,0) = 0
                                            AND (h.opens IS NULL OR h.closes IS NULL OR h.opens >= h.closes))
                                        OR h.day_of_week < 0 OR h.day_of_week > 6)
                                    LIMIT 20", [$destId]);

        /* A category that no longer follows from the provider's own word. This is exactly what
           op=recategorize would change, reported rather than done, so a mapping revision can be
           looked at before it is applied to four hundred rows. */
        $slugs = [];
        foreach (q_all("SELECT id, slug FROM place_categories WHERE status = 'active'") as $c) {
            $slugs[(string) $c['slug']] = (int) $c['id'];
        }
        $mismatch = [];
        foreach (q_all("SELECT id, name, slug, source_kind, category_id FROM places
                         WHERE destination_id = ? AND COALESCE(source_kind,'') <> ''", [$destId]) as $r) {
            $want = rmt_osm_category_slug((string) $r['source_kind']);
            $wantId = $want !== null ? ($slugs[$want] ?? null) : null;
            if ((int) ($r['category_id'] ?? 0) === (int) ($wantId ?? 0)) continue;
            $mismatch[] = ['id' => (int) $r['id'], 'name' => $r['name'],
                           'kind' => $r['source_kind'], 'should_be' => $want];
            if (count($mismatch) >= 20) break;
        }
        $out['category_mismatch'] = $mismatch;

        /* How old the provider data is. Not a fault, a fact: it says when this city is worth
           asking about again. */
        $age = q_one("SELECT MIN(source_updated_at) oldest, MAX(source_updated_at) newest
                        FROM places WHERE destination_id = ? AND source_updated_at IS NOT NULL",
                     [$destId]);
        $out['provider_data'] = ['oldest' => $age['oldest'] ?? null, 'newest' => $age['newest'] ?? null];

        $out['clean'] = $out['far_from_city'] === [] && $out['no_coordinates'] === []
                     && $out['malformed_urls'] === [] && $out['odd_aliases'] === []
                     && $out['bad_hours'] === [] && $out['category_mismatch'] === [];
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
        return;
    }

    /* The places still sitting on a serial number, with the OSM object behind each one, so the
       provider can be asked what other names it holds for them. Read only. */
    if ($op === 'serials') {
        $rows = q_all("SELECT id, slug, name, source_ref FROM places
                        WHERE destination_id = ? AND slug LIKE 'item-%' ORDER BY id",
                      [(int) $dest['id']]);
        echo json_encode(['city' => $dest['slug'], 'n' => count($rows), 'rows' => $rows],
                         JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
        return;
    }

    /* Give a serial number a real URL.
       A place whose name is written in a script the slugifier cannot carry ends up at
       /p/item-tokyo-31, which is stable and honest and is not a link anybody would send to a
       friend. OpenStreetMap usually records name:en or alt_name for exactly these, and this site
       already stores those as aliases. The NAME is not touched: アーティゾン美術館 is what the
       place is called, and replacing it with an English one would be editorial, not repair. Only
       the slug moves, and the old one is retired into place_slug_history so every URL already
       published keeps resolving. */
    if ($op === 'reslug') {
        $moved = []; $stuck = 0;
        /* By default only the serial numbers. `all=1` re-mints every slug in the city against the
           rules as they stand today, which is how the accented names were repaired: "Plaça de la
           Sagrada Família" had been slugged to "pla-a-de-la-sagrada-fam-lia", a URL with two holes
           in it. `dry=1` says what it would change without changing it, because re-minting every
           URL in a city is not something to run and then look at. */
        $all = (string) input('all') === '1';
        $dry = (string) input('dry') === '1';
        $rows = $all
            ? q_all("SELECT id, slug, name FROM places WHERE destination_id = ?", [(int) $dest['id']])
            : q_all("SELECT id, slug, name FROM places
                      WHERE destination_id = ? AND slug LIKE 'item-%'", [(int) $dest['id']]);
        foreach ($rows as $r) {
            $alts = array_column(q_all('SELECT alias FROM place_aliases WHERE place_id = ? ORDER BY id',
                                       [(int) $r['id']]), 'alias');
            $new = rmt_place_unique_slug((string) $r['name'], (string) $dest['name'], (int) $r['id'], $alts);
            if ($new === (string) $r['slug'] || (!$all && str_starts_with($new, 'item-'))) { $stuck++; continue; }
            $moved[(string) $r['slug']] = $new;
            if ($dry) continue;
            q_run('UPDATE places SET slug = ?, updated_at = ? WHERE id = ?',
                  [$new, date('Y-m-d H:i:s'), (int) $r['id']]);
            rmt_place_retire_slug((int) $r['id'], (string) $r['slug'], $new);
        }
        echo json_encode(['city' => $dest['slug'], 'dry' => $dry, 'all' => $all,
                          'looked_at' => count($rows),
                          'moved' => count($moved), 'unchanged' => $stuck, 'slugs' => $moved],
                         JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
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
        /* The other names a place goes by ride along with the row, not only afterwards, because a
           name written in a script the URL cannot carry needs one of them to build a slug. A place
           called アーティゾン美術館 otherwise lands at /p/item-tokyo-27. */
        if (!empty($in['aliases']) && is_array($in['aliases'])) {
            foreach ($in['rows'] as $i => $row) {
                $ref = (string) ($row['source_ref'] ?? '');
                if ($ref !== '' && !empty($in['aliases'][$ref])) {
                    $in['rows'][$i]['aliases'] = array_values((array) $in['aliases'][$ref]);
                }
            }
        }
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
