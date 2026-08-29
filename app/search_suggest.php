<?php
declare(strict_types=1);

/**
 * Autocomplete: turning three letters into the thing somebody meant.
 *
 * /search already does full-text well and keeps doing it. It cannot do this. plainto_tsquery lexes
 * whole words, so "Bell" and "Rijks" match nothing at all — typeahead is a prefix problem wearing a
 * search problem's clothes, and it needs different machinery.
 *
 * The order of that machinery is the whole design:
 *
 *   1. what you typed IS the name                 (exact)
 *   2. an alias of the name                       (Wien, NYC, Praha)
 *   3. the name STARTS WITH what you typed        (prefix)
 *   4. a word in the name starts with it          (token prefix)
 *   5. it appears somewhere in the name           (substring)
 *   6. it is nearly the name                      (trigram, Postgres only)
 *
 * Popularity is a tiebreaker inside a tier and can never lift a result out of one. That is
 * deliberate and it is the difference between a search box and a slot machine: typing
 * "Rijksmuseum" has to return the Rijksmuseum even if some other Amsterdam entry has ten times
 * the reviews. The tier gaps are wider than the largest popularity bonus, by construction.
 *
 * Matching runs on a normalised copy of the name and never on the name itself. Nobody types the
 * accent in Café Savoy or the umlaut in München, and the canonical display name is never touched.
 */

/** How many suggestions a person can usefully read. Not a page of results. */
const RMT_SUGGEST_LIMIT = 8;

/** Below this, autocomplete does nothing: one letter is not a query, it is a keystroke. */
const RMT_SUGGEST_MIN_CHARS = 2;

/** Below this, a near-match is a coincidence rather than a typo. */
const RMT_SUGGEST_FUZZY_MIN_CHARS = 5;

/**
 * The form a name is matched in: lowercase, unaccented, punctuation collapsed.
 *
 * Transliteration is an explicit table rather than iconv(): iconv's //TRANSLIT depends on the
 * server locale and silently produces '?' for characters it cannot handle on some builds, which
 * would make matching quietly worse in production than in a test. The table covers the languages
 * our destinations are actually in; anything outside it survives as-is rather than being mangled.
 */
function rmt_search_norm(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    static $map = [
        'á'=>'a','à'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','ā'=>'a','ă'=>'a','ą'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ė'=>'e','ę'=>'e','ě'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ī'=>'i','į'=>'i','ı'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ø'=>'o','ō'=>'o','ő'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ū'=>'u','ů'=>'u','ű'=>'u','ų'=>'u',
        'ý'=>'y','ÿ'=>'y',
        'ñ'=>'n','ń'=>'n','ň'=>'n','ç'=>'c','ć'=>'c','č'=>'c','ĉ'=>'c',
        'ś'=>'s','š'=>'s','ş'=>'s','ż'=>'z','ź'=>'z','ž'=>'z',
        'ł'=>'l','ľ'=>'l','đ'=>'d','ď'=>'d','ť'=>'t','ř'=>'r','ğ'=>'g','ŋ'=>'n',
        'ß'=>'ss','æ'=>'ae','œ'=>'oe','þ'=>'th','ð'=>'d',
    ];
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, $map);
    // Everything that is not a letter or a digit becomes one space. "St. Paul's" and "St Pauls"
    // are the same query; so are "Cafe-Savoy" and "Cafe Savoy".
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? '';
    return trim(preg_replace('/\s+/', ' ', $s) ?? '');
}

/**
 * Does this database have pg_trgm?
 *
 * Asked once and remembered. The migration tries to create the extension and survives being
 * refused, so the answer genuinely varies by environment and assuming it would turn a missing
 * extension into a 500 on every keystroke.
 */
function rmt_search_has_trigram(): bool {
    static $has = null;
    if ($has !== null) return $has;
    if (($GLOBALS['config']['db_driver'] ?? '') !== 'pgsql') return $has = false;
    try {
        $row = q_one("SELECT to_regproc('public.similarity') IS NOT NULL AS ok");
        $has = !empty($row['ok']) && $row['ok'] !== 'f';
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * LIKE with the pattern's own wildcards escaped, so a query of "100%" is not a wildcard.
 *
 * The escape character is '!' and not the usual backslash, deliberately. PDO parses a statement
 * itself to find placeholders and walks quoted strings so it does not find one inside a literal.
 * Given ESCAPE with a backslash it can read that backslash as escaping the closing quote, decide
 * the string is still open, and swallow every following ? into it. That surfaces as
 * "Invalid parameter number: parameter was not defined", it took /suggest down on Postgres, and it
 * passed locally on SQLite. A backslash has no business in a LIKE clause when any character will do.
 */
function rmt_search_like(string $norm): string {
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $norm);
}

/* ===========================================================================
 * Scoring
 * ======================================================================== */

/** How much worse a match on an alternative name is than the same match on the real one. */
const RMT_SUGGEST_ALIAS_PENALTY = 6.0;

const RMT_SUGGEST_TIERS = [
    'exact'        => 100,
    'alias'        => 92,
    'prefix'       => 80,
    'token_prefix' => 68,
    'substring'    => 55,
    'fuzzy'        => 40,
];

/**
 * Which tier a candidate name falls in for this query, or null when it does not match at all.
 *
 * @return array{tier:string,score:float}|null
 */
function rmt_suggest_tier(string $nameNorm, string $qNorm, ?string $aliasNorm = null): ?array {
    if ($qNorm === '') return null;

    $rank = static function (string $hay) use ($qNorm): ?array {
        if ($hay === '') return null;
        if ($hay === $qNorm)                    return ['tier' => 'exact', 'score' => (float) RMT_SUGGEST_TIERS['exact']];
        if (str_starts_with($hay, $qNorm))      return ['tier' => 'prefix', 'score' => (float) RMT_SUGGEST_TIERS['prefix']];
        if (str_contains($hay, ' ' . $qNorm))   return ['tier' => 'token_prefix', 'score' => (float) RMT_SUGGEST_TIERS['token_prefix']];
        if (str_contains($hay, $qNorm))         return ['tier' => 'substring', 'score' => (float) RMT_SUGGEST_TIERS['substring']];
        return null;
    };

    $own = $rank($nameNorm);
    if ($own) return $own;
    if ($aliasNorm === null || $aliasNorm === '') return null;

    // An alias match is scored on how it matched the ALIAS, then docked a few points, so a place
    // whose own name matches as well as another place's alias always wins. Giving every alias hit
    // one flat high tier put "Van Gogh Museum" above "Rijksmuseum" for the query "rijks", because
    // one of the Van Gogh Museum's recorded names happens to begin with it.
    $via = $rank($aliasNorm);
    if (!$via) return null;
    return ['tier' => 'alias:' . $via['tier'], 'score' => $via['score'] - RMT_SUGGEST_ALIAS_PENALTY];
}

/**
 * A small bonus for things people actually review, capped well below the gap between two tiers.
 *
 * Five points at most against a twelve-point tier gap: popularity orders results that are equally
 * good matches and can never promote a worse one. A search box that answers a different question
 * than the one asked is worse than one that answers nothing.
 */
function rmt_suggest_popularity(int $reviews): float {
    return min(5.0, sqrt(max(0, $reviews)) * 1.4);
}

/* ===========================================================================
 * The query
 * ======================================================================== */

/**
 * Suggestions for a typed fragment, grouped and ready to render.
 *
 * @return array{query:string, groups:list<array{label:string,items:list<array<string,mixed>>}>, count:int}
 */
function rmt_search_suggest(string $raw, int $limit = RMT_SUGGEST_LIMIT): array {
    $qNorm = rmt_search_norm($raw);
    $empty = ['query' => $qNorm, 'groups' => [], 'count' => 0];
    if (mb_strlen($qNorm) < RMT_SUGGEST_MIN_CHARS) return $empty;

    $dests  = rmt_suggest_destinations($qNorm, $limit);
    $places = rmt_suggest_places($qNorm, $limit);
    $users  = rmt_suggest_users($qNorm, 3);

    // Category suggestions hang off a destination we are confident about, and they point at pages
    // that already exist and already have content on them. Autocomplete does not get to invent a
    // destination for a page to live on.
    $explore = [];
    if ($dests && $dests[0]['score'] >= RMT_SUGGEST_TIERS['prefix']) {
        $explore = rmt_suggest_explore($dests[0], 3);
    }

    // Trim to a readable total, keeping the strongest things whatever type they are.
    $all = array_merge($dests, $places, $users);
    usort($all, static fn($a, $b) => $b['score'] <=> $a['score']);
    $keep = [];
    foreach (array_slice($all, 0, $limit) as $row) $keep[$row['type'] . ':' . $row['id']] = true;

    $groups = [];
    foreach ([['Destinations', $dests], ['Places', $places], ['Travelers', $users]] as [$label, $rows]) {
        $items = array_values(array_filter($rows, static fn($r) => isset($keep[$r['type'] . ':' . $r['id']])));
        if ($items) $groups[] = ['label' => $label, 'items' => $items];
    }
    if ($explore) $groups[] = ['label' => 'Explore', 'items' => $explore];

    $count = 0;
    foreach ($groups as $g) $count += count($g['items']);
    return ['query' => $qNorm, 'groups' => $groups, 'count' => $count];
}

/**
 * The public shape of a suggestion list: exactly what the browser needs to draw a row and follow
 * it, and nothing else.
 *
 * Defined here rather than in the controller so there is one definition of what leaves the server,
 * and so a test can hold it to that. Scores, tiers, slugs and internal ids do not travel: they are
 * how ranking works, not something a client should see or could use.
 */
function rmt_suggest_public(array $res): array {
    return [
        'query'  => $res['query'],
        'count'  => $res['count'],
        'groups' => array_map(static fn(array $g) => [
            'label' => $g['label'],
            'items' => array_map(static fn(array $i) => [
                'type'     => $i['type'],
                'id'       => (string) $i['id'],
                'name'     => $i['name'],
                'subtitle' => $i['subtitle'],
                'url'      => $i['url'],
            ], $g['items']),
        ], $res['groups']),
    ];
}

/** Destination candidates, prefix first and fuzzy only if prefix did not fill the list. */
function rmt_suggest_destinations(string $qNorm, int $limit): array {
    $like = rmt_search_like($qNorm);
    $rows = q_all(
        "SELECT d.id, d.slug, d.name, d.country, d.region, d.name_norm,
                (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = d.id AND r.status = 'published') review_count,
                NULL AS alias_hit
           FROM destinations d
          WHERE d.name_norm LIKE ? ESCAPE '!'
          ORDER BY d.name LIMIT " . ($limit * 3), [$like . '%']);

    $seen = array_column($rows, null, 'id');

    // Aliases: Wien, Praha, NYC. A hit here is a strong signal, not a fuzzy one.
    foreach (q_all("SELECT a.alias, d.id, d.slug, d.name, d.country, d.region, d.name_norm,
                           (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = d.id AND r.status = 'published') review_count,
                           a.alias_norm AS alias_hit
                      FROM search_aliases a JOIN destinations d ON d.id = a.entity_id
                     WHERE a.entity_type = 'destination' AND a.alias_norm LIKE ? ESCAPE '!'
                     LIMIT " . ($limit * 2), [$like . '%']) as $r) {
        if (!isset($seen[$r['id']])) { $seen[$r['id']] = $r; $rows[] = $r; }
    }

    if (count($rows) < $limit) {
        foreach (rmt_suggest_fuzzy('destinations', $qNorm, $limit) as $r) {
            if (!isset($seen[$r['id']])) { $seen[$r['id']] = $r; $rows[] = $r; }
        }
    }

    $out = [];
    foreach ($rows as $r) {
        $tier = rmt_suggest_tier((string) $r['name_norm'], $qNorm, $r['alias_hit'] ?? null);
        if (!$tier && !empty($r['fuzzy_score'])) {
            $tier = ['tier' => 'fuzzy', 'score' => RMT_SUGGEST_TIERS['fuzzy'] * (float) $r['fuzzy_score']];
        }
        if (!$tier) continue;
        $where = trim(implode(', ', array_filter([$r['region'] ?: null, $r['country'] ?: null])));
        $out[] = [
            'type'     => 'destination',
            'id'       => (int) $r['id'],
            'name'     => (string) $r['name'],
            'subtitle' => $where !== '' ? $where : 'Destination',
            'kind'     => 'Destination',
            'url'      => url('d/' . $r['slug']),
            'slug'     => (string) $r['slug'],
            'score'    => $tier['score'] + rmt_suggest_popularity((int) $r['review_count']),
            'tier'     => $tier['tier'],
        ];
    }
    usort($out, static fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($out, 0, $limit);
}

/** Place candidates. Always carries the city, so two venues with one name are told apart. */
function rmt_suggest_places(string $qNorm, int $limit): array {
    $like = rmt_search_like($qNorm);
    $sql = "SELECT p.id, p.slug, p.name, p.type, p.name_norm, p.category_id,
                   d.name dest_name, d.country dest_country,
                   (SELECT COUNT(*) FROM reviews r WHERE r.place_id = p.id AND r.status = 'published') review_count,
                   NULL AS alias_hit
              FROM places p JOIN destinations d ON d.id = p.destination_id
             WHERE p.status = 'active' AND p.name_norm LIKE ? ESCAPE '!'
             ORDER BY p.name LIMIT " . ($limit * 3);
    $rows = q_all($sql, [$like . '%']);
    $seen = array_column($rows, null, 'id');

    // A word inside the name starting with the query: "savoy" should find "Cafe Savoy". Same
    // statement, different pattern -- this one cannot use the prefix index, which is why it runs
    // second and only over a bounded LIMIT.
    foreach (q_all($sql, ['% ' . $like . '%']) as $r) {
        if (!isset($seen[$r['id']])) { $seen[$r['id']] = $r; $rows[] = $r; }
    }

    // Names the map knows this place by that we did not choose for it: "Milan Cathedral" for
    // Duomo di Milano, the local name for an English one. Stored by enrichment, matched here.
    foreach (q_all("SELECT p.id, p.slug, p.name, p.type, p.name_norm, p.category_id,
                           d.name dest_name, d.country dest_country,
                           (SELECT COUNT(*) FROM reviews r WHERE r.place_id = p.id AND r.status = 'published') review_count,
                           a.alias_norm AS alias_hit
                      FROM search_aliases a
                      JOIN places p ON p.id = a.entity_id AND p.status = 'active'
                      JOIN destinations d ON d.id = p.destination_id
                     WHERE a.entity_type = 'place' AND a.alias_norm LIKE ? ESCAPE '!'
                     LIMIT " . ($limit * 2), [$like . '%']) as $r) {
        if (!isset($seen[$r['id']])) { $seen[$r['id']] = $r; $rows[] = $r; }
    }

    if (count($rows) < $limit) {
        foreach (rmt_suggest_fuzzy('places', $qNorm, $limit) as $r) {
            if (!isset($seen[$r['id']])) { $seen[$r['id']] = $r; $rows[] = $r; }
        }
    }

    $out = [];
    foreach ($rows as $r) {
        $tier = rmt_suggest_tier((string) $r['name_norm'], $qNorm, $r['alias_hit'] ?? null);
        if (!$tier && !empty($r['fuzzy_score'])) {
            $tier = ['tier' => 'fuzzy', 'score' => RMT_SUGGEST_TIERS['fuzzy'] * (float) $r['fuzzy_score']];
        }
        if (!$tier) continue;
        $cat = rmt_place_category(isset($r['category_id']) ? (int) $r['category_id'] : null);
        $kind = $cat['name'] ?? rmt_place_type_label((string) $r['type']);
        $out[] = [
            'type'     => 'place',
            'id'       => (int) $r['id'],
            'name'     => (string) $r['name'],
            'subtitle' => $kind . ' · ' . trim($r['dest_name'] . ', ' . $r['dest_country'], ', '),
            'kind'     => $kind,
            'url'      => url('p/' . $r['slug']),
            'slug'     => (string) $r['slug'],
            'score'    => $tier['score'] + rmt_suggest_popularity((int) $r['review_count']),
            'tier'     => $tier['tier'],
        ];
    }
    usort($out, static fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($out, 0, $limit);
}

/** Reviewers, by username or display name. Deliberately few: this is not a people search. */
function rmt_suggest_users(string $qNorm, int $limit): array {
    $like = rmt_search_like($qNorm);
    $rows = q_all("SELECT u.id, u.username, p.display_name, p.avatar_url,
                          (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id AND r.status = 'published') review_count
                     FROM users u LEFT JOIN profiles p ON p.user_id = u.id
                    WHERE u.status = 'active'
                      AND (LOWER(u.username) LIKE ? ESCAPE '!' OR LOWER(COALESCE(p.display_name, '')) LIKE ? ESCAPE '!')
                    ORDER BY u.username LIMIT " . ($limit * 2), [$like . '%', $like . '%']);
    $out = [];
    foreach ($rows as $r) {
        $name = (string) ($r['display_name'] ?: $r['username']);
        $tier = rmt_suggest_tier(rmt_search_norm((string) $r['username']), $qNorm)
             ?? rmt_suggest_tier(rmt_search_norm($name), $qNorm);
        if (!$tier) continue;
        $out[] = [
            'type'     => 'user',
            'id'       => (int) $r['id'],
            'name'     => $name,
            'subtitle' => '@' . $r['username'],
            'kind'     => 'Traveler',
            'url'      => url('u/' . $r['username']),
            'slug'     => (string) $r['username'],
            // Users sit a tier lower than an entity of the same strength: somebody typing three
            // letters into a travel search box means a place far more often than a person.
            'score'    => $tier['score'] - 12 + rmt_suggest_popularity((int) $r['review_count']),
            'tier'     => $tier['tier'],
        ];
    }
    usort($out, static fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($out, 0, $limit);
}

/**
 * "Hotels in Paris" and friends, for a destination we are confident about.
 *
 * Every one points at /d/{slug}/places?type=..., which is an existing page with real content on
 * it. Autocomplete does not get to conjure a destination for a suggestion to land on, and nothing
 * here creates an indexable page.
 */
function rmt_suggest_explore(array $dest, int $limit): array {
    $counts = rmt_place_type_counts((int) $dest['id']);
    $labels = ['hotel' => 'Hotels in ', 'restaurant' => 'Restaurants in ', 'attraction' => 'Things to do in '];
    $out = [];
    foreach ($labels as $type => $prefix) {
        if ((int) ($counts[$type] ?? 0) < 1) continue;    // no page without places on it
        $out[] = [
            'type'     => 'explore',
            'id'       => $dest['id'] . ':' . $type,
            'name'     => $prefix . $dest['name'],
            'subtitle' => (int) $counts[$type] . ' ' . ((int) $counts[$type] === 1 ? 'place' : 'places'),
            'kind'     => 'Browse',
            'url'      => url('d/' . $dest['slug'] . '/places') . '?type=' . $type,
            'slug'     => (string) $dest['slug'],
            'score'    => 0.0,
            'tier'     => 'explore',
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}


/**
 * Typo tolerance without pg_trgm, for SQLite and for a Postgres that would not grant the extension.
 *
 * Bounded on purpose. The candidate set is only rows whose normalised name begins with the first
 * two characters of the query, which is an index range scan rather than a table scan, and it is
 * capped. Most typos are not in the first two characters -- "Rijksmusem", "Bellago", "Sagrada
 * Familai" all keep their opening -- so this catches the common case cheaply and does not pretend
 * to catch every case. Anything better than this belongs in the database, which is what pg_trgm is.
 *
 * The distance threshold scales with the length of the query: two edits in a five-letter word is a
 * different word, two edits in a fifteen-letter one is a slip.
 */
function rmt_suggest_fuzzy_portable(string $table, string $qNorm, int $limit): array {
    // Below five characters this is noise, not tolerance: "par" is one edit from "pal", so
    // Palazzo Ducale and the Palace of Holyroodhouse turn up for somebody typing Paris. A short
    // fragment is a prefix, and the prefix tiers already handle it.
    if (mb_strlen($qNorm) < RMT_SUGGEST_FUZZY_MIN_CHARS) return [];
    $head = mb_substr($qNorm, 0, 2);
    if ($head === '') return [];
    $like = rmt_search_like($head) . '%';

    $rows = $table === 'destinations'
        ? q_all("SELECT d.id, d.slug, d.name, d.country, d.region, d.name_norm,
                        (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = d.id AND r.status = 'published') review_count,
                        NULL AS alias_hit
                   FROM destinations d WHERE d.name_norm LIKE ? ESCAPE '!' LIMIT 300", [$like])
        : q_all("SELECT p.id, p.slug, p.name, p.type, p.name_norm, p.category_id,
                        d.name dest_name, d.country dest_country,
                        (SELECT COUNT(*) FROM reviews r WHERE r.place_id = p.id AND r.status = 'published') review_count,
                        NULL AS alias_hit
                   FROM places p JOIN destinations d ON d.id = p.destination_id
                  WHERE p.status = 'active' AND p.name_norm LIKE ? ESCAPE '!' LIMIT 300", [$like]);

    $qLen = mb_strlen($qNorm);
    $allowed = $qLen <= 5 ? 1 : ($qLen <= 10 ? 2 : 3);

    $out = [];
    foreach ($rows as $r) {
        $name = (string) $r['name_norm'];
        // Compare against the leading part of the name of the same length as the query, so a typed
        // fragment is judged as a fragment: "rijksmusem" against "rijksmuseu", not against the
        // whole "rijksmuseum amsterdam".
        $head2 = mb_substr($name, 0, max($qLen, 1));
        $d = levenshtein($qNorm, $head2);
        if ($d === 0 || $d > $allowed) continue;
        $r['fuzzy_score'] = max(0.0, 1.0 - ($d / max(1, $qLen)));
        $out[] = $r;
    }
    usort($out, static fn($a, $b) => $b['fuzzy_score'] <=> $a['fuzzy_score']);
    return array_slice($out, 0, $limit);
}

/**
 * Trigram near-matches, on Postgres, only when the cheap paths came up short.
 *
 * The threshold is deliberately high. A weak fuzzy hit is worse than no hit: it fills the list
 * with things nobody asked for and pushes the real answer out of view. "Rijksmusem" should find
 * the Rijksmuseum; "xqz" should find nothing.
 */
function rmt_suggest_fuzzy(string $table, string $qNorm, int $limit): array {
    if (mb_strlen($qNorm) < RMT_SUGGEST_FUZZY_MIN_CHARS) return [];
    if (!rmt_search_has_trigram()) return rmt_suggest_fuzzy_portable($table, $qNorm, $limit);
    if ($table === 'destinations') {
        return q_all("SELECT d.id, d.slug, d.name, d.country, d.region, d.name_norm,
                             similarity(d.name_norm, ?) AS fuzzy_score,
                             (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = d.id AND r.status = 'published') review_count,
                             NULL AS alias_hit
                        FROM destinations d
                       WHERE d.name_norm % ? AND similarity(d.name_norm, ?) > 0.45
                       ORDER BY fuzzy_score DESC LIMIT " . $limit, [$qNorm, $qNorm, $qNorm]);
    }
    return q_all("SELECT p.id, p.slug, p.name, p.type, p.name_norm, p.category_id,
                         d.name dest_name, d.country dest_country,
                         similarity(p.name_norm, ?) AS fuzzy_score,
                         (SELECT COUNT(*) FROM reviews r WHERE r.place_id = p.id AND r.status = 'published') review_count,
                         NULL AS alias_hit
                    FROM places p JOIN destinations d ON d.id = p.destination_id
                   WHERE p.status = 'active' AND p.name_norm % ? AND similarity(p.name_norm, ?) > 0.45
                   ORDER BY fuzzy_score DESC LIMIT " . $limit, [$qNorm, $qNorm, $qNorm]);
}

/* ===========================================================================
 * Logging. What people ask for and do not find is the clearest statement of
 * what to build next, and it needs no personal data to be useful.
 * ======================================================================== */

/** Record a query and how many suggestions it produced. No user, no session, no IP. */
function rmt_search_log(string $qNorm, int $resultCount): void {
    if ($qNorm === '' || mb_strlen($qNorm) > 120) return;
    try {
        q_run('INSERT INTO search_log (query_norm, result_count, created_at) VALUES (?,?,?)',
              [$qNorm, $resultCount, date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        // Analytics must never break search. A log row is not worth a 500.
    }
}

/** Record which suggestion was taken, and where in the list it was. */
function rmt_search_log_click(string $qNorm, string $type, string $id, int $position): void {
    if ($qNorm === '' || mb_strlen($qNorm) > 120) return;
    try {
        q_run('INSERT INTO search_log (query_norm, result_count, clicked_type, clicked_id, clicked_position, created_at)
               VALUES (?,?,?,?,?,?)',
              [$qNorm, -1, mb_substr($type, 0, 20), (int) $id, max(0, min(99, $position)), date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
    }
}

/**
 * Queries that found nothing, most asked first.
 *
 * This is a content queue, not a metric. Sixty people looking for something we do not have is a
 * decision waiting to be made, and it is the only place on the site where the gap says so out loud.
 */
function rmt_search_zero_results(int $days = 90, int $limit = 50): array {
    $since = date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'));
    return q_all("SELECT query_norm, COUNT(*) searches, MAX(created_at) last_searched
                    FROM search_log
                   WHERE result_count = 0 AND created_at >= ?
                   GROUP BY query_norm
                   ORDER BY searches DESC, last_searched DESC
                   LIMIT " . max(1, $limit), [$since]);
}

/** Queries that found something, but barely. Almost as informative as finding nothing. */
function rmt_search_low_results(int $days = 90, int $limit = 25, int $max = 2): array {
    $since = date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'));
    return q_all("SELECT query_norm, COUNT(*) searches, MAX(result_count) best, MAX(created_at) last_searched
                    FROM search_log
                   WHERE result_count > 0 AND result_count <= ? AND created_at >= ?
                   GROUP BY query_norm
                   ORDER BY searches DESC
                   LIMIT " . max(1, $limit), [$max, $since]);
}

/* ===========================================================================
 * Keeping name_norm in step
 * ======================================================================== */

/**
 * Fill in any missing normalised names.
 *
 * Idempotent and cheap: it only touches rows where name_norm is absent or no longer matches what
 * the current normaliser produces, so it is safe to run on every boot and does nothing on almost
 * all of them.
 *
 * @return array{destinations:int,places:int,aliases:int}
 */
function rmt_search_backfill_norm(): array {
    $n = ['destinations' => 0, 'places' => 0, 'aliases' => 0];

    foreach (q_all('SELECT id, name, name_norm FROM destinations') as $r) {
        $want = rmt_search_norm((string) $r['name']);
        if ((string) ($r['name_norm'] ?? '') === $want) continue;
        q_run('UPDATE destinations SET name_norm = ? WHERE id = ?', [$want, (int) $r['id']]);
        $n['destinations']++;
    }
    foreach (q_all('SELECT id, name, name_norm FROM places') as $r) {
        $want = rmt_search_norm((string) $r['name']);
        if ((string) ($r['name_norm'] ?? '') === $want) continue;
        q_run('UPDATE places SET name_norm = ? WHERE id = ?', [$want, (int) $r['id']]);
        $n['places']++;
    }
    foreach (q_all('SELECT id, alias, alias_norm FROM search_aliases') as $r) {
        $want = rmt_search_norm((string) $r['alias']);
        if ((string) ($r['alias_norm'] ?? '') === $want) continue;
        q_run('UPDATE search_aliases SET alias_norm = ? WHERE id = ?', [$want, (int) $r['id']]);
        $n['aliases']++;
    }
    return $n;
}

/** Add one alias for an entity. Duplicates are ignored, not errors. */
function rmt_search_add_alias(string $entityType, int $entityId, string $alias, string $source = 'editorial'): bool {
    $norm = rmt_search_norm($alias);
    if ($norm === '' || $entityId <= 0) return false;
    if (q_one('SELECT id FROM search_aliases WHERE entity_type = ? AND entity_id = ? AND alias_norm = ?',
              [$entityType, $entityId, $norm])) {
        return false;
    }
    q_run('INSERT INTO search_aliases (entity_type, entity_id, alias, alias_norm, source, created_at)
           VALUES (?,?,?,?,?,?)',
          [$entityType, $entityId, trim($alias), $norm, $source, date('Y-m-d H:i:s')]);
    return true;
}
