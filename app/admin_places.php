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
