<?php
declare(strict_types=1);

/**
 * Admin place editor — the human way to fill in what migration 047 made room for.
 *
 * Built on the existing admin system: require_role('admin','mod'), csrf_check(), the same flash and
 * redirect helpers as every other write on the site. There is no second admin architecture, no
 * separate login, and no new permission concept.
 *
 * Every value written here goes through the same validators the rest of the app uses
 * (rmt_place_update_attributes, rmt_place_set_hours), so an address typed by a person is held to
 * exactly the same standard as one written by an import. The editor cannot store a coordinate of
 * (0,0), a javascript: website or a price level of 9 any more than a script can.
 */

/** Places an admin can page through, newest edits first, with an optional name/slug filter. */
function rmt_admin_places(string $q = '', int $limit = 200): array {
    $q = trim($q);
    $args = [];
    $where = "p.status = 'active'";
    if ($q !== '') {
        $where .= ' AND (LOWER(p.name) LIKE ? OR LOWER(p.slug) LIKE ? OR LOWER(d.name) LIKE ?)';
        $like = '%' . mb_strtolower($q) . '%';
        $args = [$like, $like, $like];
    }
    return q_all(
        'SELECT p.id, p.slug, p.name, p.type, p.street_address, p.lat, p.phone, p.website_url,
                p.price_level, p.data_source, p.data_checked_at, p.category_id,
                d.name dest_name, d.country dest_country,
                (SELECT COUNT(*) FROM place_hours h WHERE h.place_id = p.id) hours_rows,
                (SELECT COUNT(*) FROM place_photos pp WHERE pp.place_id = p.id AND pp.status = \'published\') photo_rows
           FROM places p JOIN destinations d ON d.id = p.destination_id
          WHERE ' . $where . '
          ORDER BY p.name LIMIT ' . max(1, $limit), $args);
}

/**
 * How much of a place we actually know, 0-100.
 *
 * Not a score to optimise. It exists so an editor can see at a glance which rows are still empty,
 * and so the enrichment pilot can be measured rather than asserted.
 */
function rmt_place_completeness(array $p): int {
    $fields = [
        !empty($p['street_address']),
        isset($p['lat']) && $p['lat'] !== null,
        !empty($p['phone']),
        !empty($p['website_url']),
        !empty($p['price_level']),
        !empty($p['category_id']),
        (int) ($p['hours_rows'] ?? 0) > 0,
        (int) ($p['photo_rows'] ?? 0) > 0,
    ];
    $have = count(array_filter($fields));
    return (int) round($have * 100 / count($fields));
}

/**
 * Turn the hours grid the form posts into the interval list rmt_place_set_hours() expects.
 *
 * The form renders a fixed number of slots per day rather than adding rows with JavaScript: a
 * venue with a lunch and a dinner service fills two, a bar fills one, and the rest are left blank
 * and ignored. Blank slots are not "closed" — only the explicit Closed box means closed.
 *
 * @return array{intervals:list<array<string,mixed>>,errors:list<string>}
 */
function rmt_admin_parse_hours_grid(array $in): array {
    $intervals = [];
    $errors = [];
    $closedDays = array_map('intval', array_keys((array) ($in['closed'] ?? [])));

    foreach (((array) ($in['opens'] ?? [])) as $day => $slots) {
        $day = (int) $day;
        if ($day < 0 || $day > 6) { $errors[] = 'Bad day index.'; continue; }
        if (in_array($day, $closedDays, true)) continue;   // Closed wins; its slots are ignored
        foreach ((array) $slots as $i => $opens) {
            $opens  = trim((string) $opens);
            $closes = trim((string) (($in['closes'][$day][$i]) ?? ''));
            if ($opens === '' && $closes === '') continue;
            if ($opens === '' || $closes === '') {
                $errors[] = RMT_DAY_NAMES[$day] . ' has an interval with only one time filled in.';
                continue;
            }
            $intervals[] = ['day_of_week' => $day, 'opens' => $opens, 'closes' => $closes];
        }
    }
    foreach ($closedDays as $day) {
        if ($day >= 0 && $day <= 6) $intervals[] = ['day_of_week' => $day, 'closed' => true];
    }
    usort($intervals, static fn($a, $b) => $a['day_of_week'] <=> $b['day_of_week']);
    return ['intervals' => $intervals, 'errors' => $errors];
}

/**
 * The hours grid as the form wants to render it: day => list of ['opens'=>, 'closes'=>].
 * @return array{closed:array<int,bool>,slots:array<int,list<array{opens:string,closes:string}>>}
 */
function rmt_admin_hours_grid(int $placeId, int $slotsPerDay = 3): array {
    $closed = [];
    $slots  = [];
    foreach (range(0, 6) as $d) { $closed[$d] = false; $slots[$d] = []; }
    foreach (rmt_place_hours($placeId) as $h) {
        $d = (int) $h['day_of_week'];
        if ($h['closed']) { $closed[$d] = true; continue; }
        $slots[$d][] = ['opens' => (string) $h['opens'], 'closes' => (string) $h['closes']];
    }
    foreach ($slots as $d => $rows) {
        while (count($slots[$d]) < $slotsPerDay) $slots[$d][] = ['opens' => '', 'closes' => ''];
    }
    return ['closed' => $closed, 'slots' => $slots];
}

/**
 * Field-by-field coverage across every active place.
 *
 * One query, counted in SQL rather than by loading a hundred rows and looping. The point is not a
 * scoreboard: it is knowing which fields are actually thin before deciding what to work on, and
 * being able to say afterwards whether the work landed.
 *
 * @return array{total:int, fields:array<string,int>, buckets:array<string,int>}
 */
function rmt_place_coverage(): array {
    $row = q_one("SELECT COUNT(*) total,
        SUM(CASE WHEN p.category_id     IS NOT NULL THEN 1 ELSE 0 END) category,
        SUM(CASE WHEN p.street_address  IS NOT NULL AND p.street_address <> '' THEN 1 ELSE 0 END) street_address,
        SUM(CASE WHEN p.neighborhood    IS NOT NULL AND p.neighborhood   <> '' THEN 1 ELSE 0 END) neighborhood,
        SUM(CASE WHEN p.region          IS NOT NULL AND p.region         <> '' THEN 1 ELSE 0 END) region,
        SUM(CASE WHEN p.postal_code     IS NOT NULL AND p.postal_code    <> '' THEN 1 ELSE 0 END) postal_code,
        SUM(CASE WHEN p.lat IS NOT NULL AND p.lng IS NOT NULL THEN 1 ELSE 0 END) coordinates,
        SUM(CASE WHEN p.phone           IS NOT NULL AND p.phone       <> '' THEN 1 ELSE 0 END) phone,
        SUM(CASE WHEN p.website_url     IS NOT NULL AND p.website_url <> '' THEN 1 ELSE 0 END) website,
        SUM(CASE WHEN p.price_level     IS NOT NULL THEN 1 ELSE 0 END) price_level,
        SUM(CASE WHEN p.timezone        IS NOT NULL AND p.timezone    <> '' THEN 1 ELSE 0 END) timezone,
        SUM(CASE WHEN p.data_source     IS NOT NULL AND p.data_source <> '' THEN 1 ELSE 0 END) provenance,
        SUM(CASE WHEN p.data_checked_at IS NOT NULL AND p.data_checked_at <> '' THEN 1 ELSE 0 END) checked_at,
        SUM(CASE WHEN EXISTS (SELECT 1 FROM place_hours h WHERE h.place_id = p.id) THEN 1 ELSE 0 END) hours,
        SUM(CASE WHEN EXISTS (SELECT 1 FROM place_photos pp WHERE pp.place_id = p.id AND pp.status = 'published') THEN 1 ELSE 0 END) photo
        FROM places p WHERE p.status = 'active'");

    $total = (int) ($row['total'] ?? 0);
    $fields = [];
    foreach (['category','street_address','neighborhood','region','postal_code','coordinates',
              'phone','website','price_level','timezone','hours','photo','provenance','checked_at'] as $k) {
        $fields[$k] = (int) ($row[$k] ?? 0);
    }

    // Buckets over the eight fields rmt_place_completeness() counts, so the two agree.
    $buckets = ['full' => 0, 'partial' => 0, 'thin' => 0];
    foreach (rmt_admin_places('', 100000) as $p) {
        $pct = rmt_place_completeness($p);
        if ($pct >= 88)      $buckets['full']++;
        elseif ($pct >= 38)  $buckets['partial']++;
        else                 $buckets['thin']++;
    }
    return ['total' => $total, 'fields' => $fields, 'buckets' => $buckets];
}

/**
 * Places the automatic enrichment refused, with the reason it gave.
 *
 * Read from the committed proposal file rather than a table: the refusal is a property of the last
 * enrichment run, not of the place, and a run is a file. Everything here needs a human, which is
 * why the reason matters -- "no external match" and "the map says this is a bus stop" are different
 * jobs, and a queue of things labelled "failed" is not a queue.
 *
 * @return list<array{slug:string,name:string,reason:string,detail:string,confidence:float}>
 */
function rmt_enrichment_refusals(?string $file = null): array {
    $file = $file ?: BASE_PATH . '/database/enrichment/proposal.json';
    if (!is_file($file)) return [];
    $doc = json_decode((string) file_get_contents($file), true);
    $out = [];
    foreach ((array) ($doc['places'] ?? []) as $p) {
        if (empty($p['refusal'])) continue;
        $out[] = [
            'slug'       => (string) ($p['slug'] ?? ''),
            'name'       => (string) ($p['name'] ?? ''),
            'reason'     => (string) ($p['refusal']['reason'] ?? 'unknown'),
            'detail'     => (string) ($p['refusal']['detail'] ?? ''),
            'confidence' => (float) ($p['confidence'] ?? 0),
        ];
    }
    usort($out, static fn($a, $b) => [$a['reason'], $a['slug']] <=> [$b['reason'], $b['slug']]);
    return $out;
}

/**
 * Places whose facts have not been re-checked in a while.
 *
 * Groundwork for refresh, not a crawler. A place is stale when we hold sourced data and the last
 * check is older than $days. A place we never checked is not stale, it is unenriched, and those
 * are different queues. Nothing here deletes anything: a source disappearing is a reason to look,
 * never a reason to remove a place travelers have reviewed.
 */
function rmt_stale_places(int $days = 180, int $limit = 200): array {
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'));
    return q_all("SELECT p.id, p.slug, p.name, p.data_source, p.data_checked_at, d.name dest_name
                    FROM places p JOIN destinations d ON d.id = p.destination_id
                   WHERE p.status = 'active' AND p.data_checked_at IS NOT NULL
                     AND p.data_checked_at <> '' AND p.data_checked_at < ?
                   ORDER BY p.data_checked_at LIMIT " . max(1, $limit), [$cutoff]);
}
