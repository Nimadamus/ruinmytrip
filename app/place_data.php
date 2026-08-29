<?php
declare(strict_types=1);

/**
 * Place attributes: address, coordinates, contact, price, category, hours, photos, slug history.
 *
 * Kept out of places.php on purpose. That file is about place IDENTITY — resolving a typed name to
 * one row, and never merging two things that are different. This file is about what we KNOW about
 * that row, which is a separate concern with a separate failure mode: identity fails by merging,
 * attributes fail by asserting something untrue.
 *
 * The rule every function here follows: an unknown value is NULL, an empty list, or false-y, and
 * the caller renders nothing. There is no placeholder address, no default price band, no "hours
 * not available" box, and no coordinate we guessed from a city centre. A page that omits a fact is
 * honest; a page that prints an invented one is worse than a page with a gap in it.
 */

/* ===========================================================================
 * Slug history and the 301 contract
 * ======================================================================== */

/**
 * Retire a slug so the URL it used to serve keeps working.
 *
 * The current slug is never stored in history — it lives on the place — so a lookup can never
 * return a redirect to the URL that was just requested. If the place is renamed back to a slug it
 * used before, that row is deleted from history rather than left to point at itself.
 *
 * Redirect chains are impossible by construction: history maps slug -> place_id, and the redirect
 * target is always read fresh from the place. Ten renames still cost exactly one hop.
 */
function rmt_place_retire_slug(int $placeId, string $oldSlug, string $newSlug): void {
    $oldSlug = trim($oldSlug);
    if ($placeId <= 0 || $oldSlug === '' || $oldSlug === $newSlug) return;
    // The slug we are moving TO must not also be a historic slug, or /p/new would redirect to
    // itself. This is the "renamed back to an earlier name" case.
    q_run('DELETE FROM place_slug_history WHERE slug = ?', [$newSlug]);
    try {
        q_run('INSERT INTO place_slug_history (place_id, slug, created_at) VALUES (?,?,?)',
              [$placeId, $oldSlug, date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        // The slug is already recorded (unique index). Point it at this place: a slug can only
        // ever belong to one entity, and the most recent claim is the correct one.
        q_run('UPDATE place_slug_history SET place_id = ? WHERE slug = ?', [$placeId, $oldSlug]);
    }
}

/** The place a retired slug used to name, or null. */
function rmt_place_for_retired_slug(string $slug): ?array {
    if ($slug === '') return null;
    $row = q_one('SELECT p.slug, p.status FROM place_slug_history h JOIN places p ON p.id = h.place_id
                   WHERE h.slug = ?', [$slug]);
    return $row ?: null;
}

/**
 * Rename a place, keeping its identity and its old URL.
 *
 * The database id does not change, so every review, save, photo, visit and category link survives
 * a rebrand untouched. Only the presentation layer moves, and the old presentation keeps resolving.
 *
 * @return array{slug:string,renamed:bool}
 */
function rmt_place_rename(int $placeId, string $newName): array {
    $p = q_one('SELECT p.id, p.slug, p.name, p.destination_id, d.name dest_name
                  FROM places p JOIN destinations d ON d.id = p.destination_id WHERE p.id = ?', [$placeId]);
    if (!$p) throw new RuntimeException('no such place: ' . $placeId);

    $newName = trim($newName);
    if ($newName === '' || mb_strlen($newName) > RMT_PLACE_NAME_MAX) {
        throw new InvalidArgumentException('invalid place name');
    }
    if ($newName === $p['name']) return ['slug' => (string) $p['slug'], 'renamed' => false];

    $newSlug = rmt_place_unique_slug($newName, (string) $p['dest_name'], $placeId);
    q_run('UPDATE places SET name = ?, name_key = ?, slug = ?, updated_at = ? WHERE id = ?',
          [$newName, rmt_place_name_key($newName), $newSlug, date('Y-m-d H:i:s'), $placeId]);
    rmt_place_retire_slug($placeId, (string) $p['slug'], $newSlug);
    return ['slug' => $newSlug, 'renamed' => true];
}

/* ===========================================================================
 * Attribute validation. Every writer goes through these.
 * ======================================================================== */

/**
 * A website we are willing to link to, or null.
 *
 * Rejecting rather than repairing is the point: a value we cannot parse is a value we should not
 * publish as a business's official site. Only http(s) survives — javascript:, data: and mailto:
 * are how a link field becomes an XSS vector or a phishing hop.
 */
function rmt_place_normalize_website(?string $raw): ?string {
    $raw = trim((string) $raw);
    if ($raw === '' || mb_strlen($raw) > 500) return null;
    // A scheme we do not accept is rejected, never repaired. Prepending https:// to "mailto:a@b.com"
    // would produce a parseable URL pointing at b.com, which is a different address than the one
    // that was submitted. The scheme pattern excludes dots so that "example.com:8080/x" is read as
    // a host and a port, which is what it is, rather than as a scheme named "example.com".
    if (preg_match('#^([a-z][a-z0-9+\-]*):#i', $raw, $m)) {
        $given = strtolower($m[1]);
        if ($given !== 'http' && $given !== 'https') return null;
    } else {
        $raw = 'https://' . $raw;
    }

    $parts = parse_url($raw);
    if (!$parts || empty($parts['host'])) return null;
    $scheme = strtolower($parts['scheme'] ?? '');
    if ($scheme !== 'http' && $scheme !== 'https') return null;

    $host = strtolower($parts['host']);
    // A host with no dot is either a typo or an intranet name; neither belongs on a public page.
    if (!str_contains($host, '.') || preg_match('/\s/', $host)) return null;

    $out = $scheme . '://' . $host;
    if (!empty($parts['port']))  $out .= ':' . (int) $parts['port'];
    if (!empty($parts['path']))  $out .= $parts['path'];
    if (!empty($parts['query'])) $out .= '?' . $parts['query'];
    return $out;
}

/**
 * A phone number as the business writes it, minus anything that is not part of a phone number.
 *
 * Deliberately NOT reformatted into one canonical shape: international numbering is not uniform,
 * and rewriting "+44 20 7946 0958" into a guess at E.164 for every country is how a working number
 * becomes an unreachable one. Punctuation the ITU actually uses is kept; everything else goes.
 */
function rmt_place_normalize_phone(?string $raw): ?string {
    $raw = trim((string) $raw);
    if ($raw === '') return null;
    $clean = preg_replace('/[^0-9+()\-. ]/', '', $raw) ?? '';
    $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');
    $digits = preg_replace('/\D/', '', $clean) ?? '';
    // Shorter than 6 digits is not a dialable number anywhere; longer than 20 is not one either.
    if (strlen($digits) < 6 || strlen($digits) > 20) return null;
    return mb_substr($clean, 0, 40);
}

/**
 * 1..4, or null. This is the constraint SQLite could not take in ALTER TABLE, enforced in the one
 * place every write passes through so local and production behave identically.
 */
function rmt_place_normalize_price_level($raw): ?int {
    if ($raw === null || $raw === '' || $raw === false) return null;
    if (!is_numeric($raw)) return null;
    $n = (int) $raw;
    return ($n >= 1 && $n <= 4) ? $n : null;
}

/**
 * A coordinate pair, or null.
 *
 * Both halves or neither: a latitude with no longitude cannot be put on a map and is not worth
 * storing. (0,0) is rejected outright — it is in the Atlantic 600km off Ghana, and in practice it
 * is what an import writes when it failed to geocode. A silent wrong pin is worse than no pin.
 *
 * @return array{0:float,1:float}|null
 */
function rmt_place_normalize_coords($lat, $lng): ?array {
    if ($lat === null || $lng === null || $lat === '' || $lng === '') return null;
    if (!is_numeric($lat) || !is_numeric($lng)) return null;
    $la = (float) $lat; $ln = (float) $lng;
    if ($la < -90 || $la > 90 || $ln < -180 || $ln > 180) return null;
    if (abs($la) < 0.00001 && abs($ln) < 0.00001) return null;
    return [round($la, 6), round($ln, 6)];   // ~11cm; more precision than any address needs
}

/** Trim to a length, or null when there is nothing left. */
function rmt_place_clean_text(?string $raw, int $max): ?string {
    $v = trim((string) $raw);
    if ($v === '') return null;
    return mb_substr($v, 0, $max);
}

/**
 * Write validated attributes. Unknown keys are ignored; a key that is present but invalid is
 * reported and NOT written, so a bad phone number can never quietly overwrite a good one.
 *
 * @param  array<string,mixed> $in
 * @return array<string,string> field => error message; empty on success
 */
function rmt_place_update_attributes(int $placeId, array $in): array {
    $p = q_one('SELECT id, type FROM places WHERE id = ?', [$placeId]);
    if (!$p) return ['place' => 'No such place.'];

    $set = [];
    $errors = [];

    $textFields = ['street_address' => 200, 'neighborhood' => 120, 'region' => 120,
                   'postal_code' => 32, 'timezone' => 64, 'data_source' => 60,
                   'data_source_url' => 500];
    foreach ($textFields as $f => $max) {
        if (array_key_exists($f, $in)) $set[$f] = rmt_place_clean_text((string) $in[$f], $max);
    }
    if (isset($set['timezone']) && !in_array($set['timezone'], timezone_identifiers_list(), true)) {
        $errors['timezone'] = 'Not an IANA timezone name.';
        unset($set['timezone']);
    }

    if (array_key_exists('website_url', $in)) {
        $raw = trim((string) $in['website_url']);
        $url = rmt_place_normalize_website($raw);
        if ($raw !== '' && $url === null) $errors['website_url'] = 'That does not look like a web address.';
        else $set['website_url'] = $url;
    }
    if (array_key_exists('phone', $in)) {
        $raw = trim((string) $in['phone']);
        $ph = rmt_place_normalize_phone($raw);
        if ($raw !== '' && $ph === null) $errors['phone'] = 'That does not look like a phone number.';
        else $set['phone'] = $ph;
    }
    if (array_key_exists('price_level', $in)) {
        $raw = $in['price_level'];
        $pl = rmt_place_normalize_price_level($raw);
        if ($raw !== null && $raw !== '' && $pl === null) $errors['price_level'] = 'Price level must be 1 to 4.';
        else $set['price_level'] = $pl;
    }
    if (array_key_exists('lat', $in) || array_key_exists('lng', $in)) {
        $raw = [$in['lat'] ?? null, $in['lng'] ?? null];
        $co = rmt_place_normalize_coords($raw[0], $raw[1]);
        $given = ($raw[0] !== null && $raw[0] !== '') || ($raw[1] !== null && $raw[1] !== '');
        if ($given && $co === null) $errors['lat'] = 'Latitude and longitude must both be present and in range.';
        else { $set['lat'] = $co[0] ?? null; $set['lng'] = $co[1] ?? null; }
    }
    if (array_key_exists('category_id', $in)) {
        $cid = (int) $in['category_id'];
        if ($cid === 0) {
            $set['category_id'] = null;
        } else {
            $cat = q_one('SELECT id, bucket FROM place_categories WHERE id = ? AND status = ?', [$cid, 'active']);
            // A subcategory belongs to exactly one place type. Attaching "Steakhouse" to a museum
            // is not a typo we should store and clean up later.
            if (!$cat) $errors['category_id'] = 'No such category.';
            elseif ($cat['bucket'] !== $p['type']) $errors['category_id'] = 'That category does not belong to a ' . $p['type'] . '.';
            else $set['category_id'] = (int) $cat['id'];
        }
    }

    if ($errors) return $errors;
    if (!$set) return [];

    $set['data_checked_at'] = date('Y-m-d H:i:s');
    $set['updated_at'] = date('Y-m-d H:i:s');
    $cols = implode(', ', array_map(static fn($k) => $k . ' = ?', array_keys($set)));
    $args = array_values($set);
    $args[] = $placeId;
    q_run('UPDATE places SET ' . $cols . ' WHERE id = ?', $args);
    return [];
}

/* ===========================================================================
 * Reading attributes back
 * ======================================================================== */

/**
 * The address of a place, assembled from the place and the destination it belongs to.
 *
 * City and country are NOT columns on `places`. They are the destination's, the place already
 * references the destination, and copying them would create a second copy to keep in sync. Region
 * falls back to the destination's region for the same reason.
 *
 * @return array{street:?string,neighborhood:?string,locality:?string,region:?string,postal:?string,country:?string,lines:list<string>}
 */
function rmt_place_address(array $p): array {
    $a = [
        'street'       => $p['street_address'] ?? null,
        'neighborhood' => $p['neighborhood'] ?? null,
        'locality'     => $p['dest_name'] ?? null,
        'region'       => ($p['region'] ?? null) ?: ($p['dest_region'] ?? null),
        'postal'       => $p['postal_code'] ?? null,
        'country'      => $p['dest_country'] ?? null,
    ];
    // City-states and single-city regions name the region after the city (Prague, Prague; Berlin,
    // Berlin), and printing both gives "Prague Prague". Repeating a word is not extra information.
    $region = $a['region'];
    if ($region !== null && $a['locality'] !== null
        && mb_strtolower(trim((string) $region)) === mb_strtolower(trim((string) $a['locality']))) {
        $region = null;
    }
    $line2 = trim(implode(' ', array_filter([$a['locality'], $region, $a['postal']])));
    $a['lines'] = array_values(array_filter([$a['street'], $line2 ?: null, $a['country']]));
    return $a;
}

/** True when we hold enough to print an address block worth printing. */
function rmt_place_has_address(array $p): bool {
    return ($p['street_address'] ?? null) !== null && trim((string) $p['street_address']) !== '';
}

/** '$' .. '$$$$', or null. */
function rmt_place_price_label(?int $level): ?string {
    return $level === null ? null : str_repeat('$', max(1, min(4, $level)));
}

/** What a price level means in words, for a title attribute and for screen readers. */
function rmt_place_price_title(?int $level): ?string {
    return [1 => 'Inexpensive', 2 => 'Moderate', 3 => 'Expensive', 4 => 'Very expensive'][$level] ?? null;
}

/**
 * The dialable form of a phone number for a tel: link.
 *
 * Only digits and a leading plus survive: spaces, dashes and brackets are how humans read a number
 * and are not part of dialing it.
 */
function rmt_place_tel_href(string $phone): string {
    $plus = str_starts_with(trim($phone), '+') ? '+' : '';
    return $plus . (preg_replace('/\D/', '', $phone) ?? '');
}

/**
 * A map link for a coordinate pair.
 *
 * OpenStreetMap, because it needs no API key, no billing account and no third-party script on the
 * page. Opening a map is a deliberate click, so the cost belongs on that click and not on every
 * page load.
 */
function rmt_place_map_url(float $lat, float $lng): string {
    return sprintf('https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=17/%1$s/%2$s', $lat, $lng);
}

/** The subcategory row for a place, or null. */
function rmt_place_category(?int $categoryId): ?array {
    if (!$categoryId) return null;
    return q_one('SELECT * FROM place_categories WHERE id = ?', [$categoryId]) ?: null;
}

/**
 * Subcategories offered for one place type, in display order.
 * @return list<array{id:int,slug:string,name:string,plural:string}>
 */
function rmt_place_categories_for(string $bucket): array {
    if (!in_array($bucket, RMT_PLACE_TYPES, true)) return [];
    return q_all('SELECT id, slug, name, plural FROM place_categories
                   WHERE bucket = ? AND status = ? ORDER BY sort, name', [$bucket, 'active']);
}

/* ===========================================================================
 * Opening hours
 * ======================================================================== */

/** Monday-first, matching day_of_week 0..6 and schema.org's day list. */
const RMT_DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

/**
 * The regular week for a place: every stored interval, ordered.
 *
 * Only rows with no validity window are returned. Dated exceptions (a holiday closure) are stored
 * in the same table but are not the normal week and must not be presented as it; nothing writes
 * them yet.
 *
 * @return list<array{day_of_week:int,opens:?string,closes:?string,closed:bool}>
 */
function rmt_place_hours(int $placeId): array {
    $rows = q_all('SELECT day_of_week, opens, closes, closed FROM place_hours
                    WHERE place_id = ? AND valid_from IS NULL AND valid_through IS NULL
                    ORDER BY day_of_week, sort, opens', [$placeId]);
    foreach ($rows as &$r) {
        $r['day_of_week'] = (int) $r['day_of_week'];
        $r['closed'] = rmt_place_flag($r['closed']);
    }
    return $rows;
}

/**
 * Read a stored 0/1 flag as a bool.
 *
 * Not a plain (bool) cast. A driver that hands a flag back as the string 'f' would make
 * (bool) $v true, and the value that gets misread here is "this place is closed on Tuesday" —
 * printed to a reader as "Open now". Migration 048 made both drivers store an integer; this is the
 * belt to that migration's braces.
 */
function rmt_place_flag($v): bool {
    if (is_bool($v)) return $v;
    if (is_int($v))  return $v !== 0;
    $s = strtolower(trim((string) $v));
    return $s === '1' || $s === 't' || $s === 'true';
}

/**
 * Hours grouped for display: one entry per day we actually know about.
 *
 * A day with no row is absent, not "Closed". "We have no information about Sunday" and "it is shut
 * on Sunday" are different claims and only one of them is ours to make.
 *
 * @return list<array{day:string,dow:int,intervals:list<string>,closed:bool}>
 */
function rmt_place_hours_by_day(array $hours): array {
    $byDay = [];
    foreach ($hours as $h) {
        $d = $h['day_of_week'];
        if (!isset($byDay[$d])) $byDay[$d] = ['day' => RMT_DAY_NAMES[$d] ?? '', 'dow' => $d, 'intervals' => [], 'closed' => false];
        if ($h['closed']) { $byDay[$d]['closed'] = true; continue; }
        $byDay[$d]['intervals'][] = rmt_place_hhmm($h['opens']) . '-' . rmt_place_hhmm($h['closes']);
    }
    ksort($byDay);
    return array_values($byDay);
}

/** 'HH:MM', or the raw value when it is not one. */
function rmt_place_hhmm(?string $t): string {
    $t = trim((string) $t);
    return preg_match('/^\d{2}:\d{2}$/', $t) ? $t : $t;
}

/**
 * schema.org OpeningHoursSpecification, or null when we know nothing.
 *
 * An overnight interval (closes < opens) is emitted exactly as stored: schema.org defines closes
 * earlier than opens as running into the next day, which is what a 21:00-02:00 bar does.
 *
 * @return list<array<string,mixed>>|null
 */
function rmt_place_hours_schema(array $hours): ?array {
    if (!$hours) return null;
    $out = [];
    foreach ($hours as $h) {
        $day = 'https://schema.org/' . (RMT_DAY_NAMES[$h['day_of_week']] ?? '');
        if ($h['closed']) {
            // schema.org expresses "closed all day" as an interval of zero length.
            $out[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $day,
                      'opens' => '00:00', 'closes' => '00:00'];
            continue;
        }
        $out[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $day,
                  'opens' => $h['opens'], 'closes' => $h['closes']];
    }
    return $out ?: null;
}

/**
 * Is the place open right now?
 *
 * Returns null — not false — when we cannot know: no hours, or no timezone. "Closed" is a claim
 * that sends someone home; we only make it when the data supports it. Requires the place's own
 * IANA timezone, because the server's clock and the traveler's clock are both the wrong clock.
 */
function rmt_place_open_now(array $hours, ?string $tz, ?DateTimeImmutable $now = null): ?bool {
    if (!$hours || !$tz) return null;
    try { $zone = new DateTimeZone($tz); } catch (Throwable $e) { return null; }
    $now = ($now ?? new DateTimeImmutable('now'))->setTimezone($zone);

    $dow = (int) $now->format('N') - 1;          // 0 = Monday
    $yesterday = ($dow + 6) % 7;
    $mins = (int) $now->format('G') * 60 + (int) $now->format('i');

    // "Closed" is only sayable about a day somebody actually told us about. A place whose hours we
    // hold for Monday and Friday tells us nothing about Sunday, and answering false there would put
    // "Closed now" on the page as if it were a fact. Yesterday counts too, because an interval that
    // runs past midnight is still today's answer.
    $known = false;
    foreach ($hours as $h) {
        if ($h['day_of_week'] === $dow) { $known = true; break; }
        if ($h['day_of_week'] === $yesterday && !$h['closed']) {
            $o = rmt_place_minutes($h['opens']); $c = rmt_place_minutes($h['closes']);
            if ($o !== null && $c !== null && $c <= $o) { $known = true; break; }
        }
    }
    if (!$known) return null;

    foreach ($hours as $h) {
        if ($h['closed']) continue;
        $o = rmt_place_minutes($h['opens']);
        $c = rmt_place_minutes($h['closes']);
        if ($o === null || $c === null) continue;

        if ($c > $o) {
            if ($h['day_of_week'] === $dow && $mins >= $o && $mins < $c) return true;
        } else {
            // Overnight: open on its own day from `opens`, and on the NEXT day until `closes`.
            if ($h['day_of_week'] === $dow && $mins >= $o) return true;
            if ($h['day_of_week'] === $yesterday && $mins < $c) return true;
        }
    }
    return false;
}

/** 'HH:MM' as minutes past midnight, or null. */
function rmt_place_minutes(?string $t): ?int {
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim((string) $t), $m)) return null;
    $h = (int) $m[1]; $i = (int) $m[2];
    if ($h > 24 || $i > 59) return null;
    return $h * 60 + $i;
}

/**
 * Replace a place's regular week in one transaction.
 *
 * @param list<array{day_of_week:int,opens?:?string,closes?:?string,closed?:bool}> $intervals
 * @return array<string,string> errors; empty on success
 */
function rmt_place_set_hours(int $placeId, array $intervals): array {
    $clean = [];
    foreach ($intervals as $i => $row) {
        $d = (int) ($row['day_of_week'] ?? -1);
        if ($d < 0 || $d > 6) return ['hours' => 'Day ' . $i . ' is out of range.'];
        if (!empty($row['closed'])) { $clean[] = [$d, null, null, 1]; continue; }
        $o = rmt_place_minutes($row['opens'] ?? null);
        $c = rmt_place_minutes($row['closes'] ?? null);
        if ($o === null || $c === null) return ['hours' => 'Row ' . $i . ' needs both an opening and a closing time as HH:MM.'];
        if ($o === $c) return ['hours' => 'Row ' . $i . ' opens and closes at the same minute.'];
        $clean[] = [$d, sprintf('%02d:%02d', intdiv($o, 60), $o % 60), sprintf('%02d:%02d', intdiv($c, 60), $c % 60), 0];
    }

    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        q_run('DELETE FROM place_hours WHERE place_id = ? AND valid_from IS NULL AND valid_through IS NULL', [$placeId]);
        foreach ($clean as $n => [$d, $o, $c, $closed]) {
            q_run('INSERT INTO place_hours (place_id, day_of_week, opens, closes, closed, sort) VALUES (?,?,?,?,?,?)',
                  [$placeId, $d, $o, $c, $closed, $n]);
        }
        if ($own) $pdo->commit();
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return [];
}

/* ===========================================================================
 * Photos
 * ======================================================================== */

/** The public URL for a photo row, whichever way its bytes are stored. */
function rmt_place_photo_url(array $row): string {
    $key = (string) ($row['storage_key'] ?? '');
    if ($key !== '') return rmt_media_url($key);
    return (string) ($row['url'] ?? '');
}

/**
 * The gallery for a place: photos of the place itself, then photos travelers attached to reviews
 * of it, cover first.
 *
 * Both sources are references, never copies. A review photo surfaced here is the same media row
 * the review renders, so there is exactly one blob and deleting the review takes the reference
 * with it.
 *
 * The returned shape is unchanged from the previous review-only version — url, caption,
 * created_at, parent_id, parent_slug, user_id, kind — so existing callers keep working. Rows from
 * place_photos carry kind='place' and a null parent_id, which is how the view knows a photo has no
 * review to link to.
 */
function rmt_place_gallery(int $placeId, int $limit = 12): array {
    $limit = max(1, $limit);

    $own = q_all("SELECT pp.id, pp.storage_key, pp.url, pp.caption, pp.alt_text, pp.credit,
                         pp.created_at, pp.uploaded_by user_id, pp.is_cover,
                         NULL AS parent_id, NULL AS parent_slug, 'place' AS kind
                    FROM place_photos pp
                   WHERE pp.place_id = ? AND pp.status = 'published' AND pp.review_photo_id IS NULL
                   ORDER BY pp.is_cover DESC, pp.sort, pp.id
                   LIMIT " . $limit, [$placeId]);

    $fromReviews = q_all("SELECT rp.id, rp.storage_key, rp.url, rp.caption, NULL AS alt_text, NULL AS credit,
                                 rp.created_at, r.user_id, 0 AS is_cover,
                                 r.id AS parent_id, r.slug AS parent_slug, 'review' AS kind
                            FROM review_photos rp JOIN reviews r ON r.id = rp.review_id
                           WHERE r.place_id = ? AND r.status = 'published'
                           ORDER BY rp.created_at DESC, rp.id DESC
                           LIMIT " . $limit, [$placeId]);

    $rows = array_merge($own, $fromReviews);
    foreach ($rows as &$r) $r['url'] = rmt_place_photo_url($r);
    unset($r);
    $rows = array_slice($rows, 0, $limit);
    authors_fill($rows);
    return $rows;
}

/**
 * The single image that represents the place: its cover if one is set, otherwise the first
 * gallery photo, otherwise null. Never the destination's hero — that is a picture of the city, and
 * using it as a place's og:image tells a share preview something false.
 */
function rmt_place_cover_url(int $placeId): ?string {
    $row = q_one("SELECT storage_key, url FROM place_photos
                   WHERE place_id = ? AND status = 'published' AND is_cover = 1
                   LIMIT 1", [$placeId]);
    if (!$row) {
        $row = q_one("SELECT storage_key, url FROM place_photos
                       WHERE place_id = ? AND status = 'published' ORDER BY sort, id LIMIT 1", [$placeId]);
    }
    if (!$row) {
        $row = q_one("SELECT rp.storage_key, rp.url FROM review_photos rp JOIN reviews r ON r.id = rp.review_id
                       WHERE r.place_id = ? AND r.status = 'published'
                       ORDER BY rp.created_at DESC, rp.id DESC LIMIT 1", [$placeId]);
    }
    if (!$row) return null;
    $u = rmt_place_photo_url($row);
    return $u !== '' ? $u : null;
}

/** How many photos a place has, across both sources. */
function rmt_place_photo_count(int $placeId): int {
    $a = (int) (q_one("SELECT COUNT(*) c FROM place_photos WHERE place_id = ? AND status = 'published' AND review_photo_id IS NULL", [$placeId])['c'] ?? 0);
    $b = (int) (q_one("SELECT COUNT(*) c FROM review_photos rp JOIN reviews r ON r.id = rp.review_id
                        WHERE r.place_id = ? AND r.status = 'published'", [$placeId])['c'] ?? 0);
    return $a + $b;
}

/**
 * Add a photo to a place from bytes we already hold in media storage.
 *
 * @return int the place_photos id
 */
function rmt_place_photo_add(int $placeId, array $in): int {
    $key = rmt_place_clean_text($in['storage_key'] ?? null, 191);
    $url = rmt_place_clean_text($in['url'] ?? null, 500);
    if ($key === null && $url === null) throw new InvalidArgumentException('a photo needs a storage key or a url');

    $isCover = !empty($in['is_cover']);
    if ($isCover) q_run('UPDATE place_photos SET is_cover = 0 WHERE place_id = ?', [$placeId]);

    return (int) q_run(
        'INSERT INTO place_photos (place_id, review_photo_id, storage_key, url, caption, alt_text,
                                   credit, license, source_url, uploaded_by, width, height, bytes,
                                   is_cover, sort, status, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$placeId,
         isset($in['review_photo_id']) ? (int) $in['review_photo_id'] : null,
         $key, $url,
         rmt_place_clean_text($in['caption'] ?? null, 300),
         rmt_place_clean_text($in['alt_text'] ?? null, 300),
         rmt_place_clean_text($in['credit'] ?? null, 200),
         rmt_place_clean_text($in['license'] ?? null, 120),
         rmt_place_normalize_website($in['source_url'] ?? null),
         isset($in['uploaded_by']) ? (int) $in['uploaded_by'] : null,
         isset($in['width']) ? (int) $in['width'] : null,
         isset($in['height']) ? (int) $in['height'] : null,
         isset($in['bytes']) ? (int) $in['bytes'] : null,
         $isCover ? 1 : 0,
         (int) ($in['sort'] ?? 0),
         (string) ($in['status'] ?? 'published'),
         date('Y-m-d H:i:s')]);
}

/* ===========================================================================
 * Structured data
 * ======================================================================== */

/**
 * The attribute half of a place's JSON-LD: address, geo, contact, price, hours.
 *
 * Returned as a fragment the caller merges into its own node, because whether that node may carry
 * review markup is a separate question answered by rmt_place_review_type(). Everything here is
 * safe on ANY schema.org Place — a museum can legitimately have an address, coordinates and
 * opening hours; what it cannot legitimately have is a Google review snippet.
 */
function rmt_place_schema_attributes(array $p, array $hours = []): array {
    $out = [];
    $addr = rmt_place_address($p);
    $postal = array_filter([
        '@type'           => 'PostalAddress',
        'streetAddress'   => $addr['street'],
        'addressLocality' => $addr['locality'],
        'addressRegion'   => $addr['region'],
        'postalCode'      => $addr['postal'],
        'addressCountry'  => $addr['country'],
    ], static fn($v) => $v !== null && $v !== '');
    if (count($postal) > 1) $out['address'] = $postal;

    $co = rmt_place_normalize_coords($p['lat'] ?? null, $p['lng'] ?? null);
    if ($co) $out['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $co[0], 'longitude' => $co[1]];

    if (!empty($p['phone']))       $out['telephone'] = $p['phone'];
    if (!empty($p['website_url'])) $out['sameAs'] = [$p['website_url']];

    $price = rmt_place_price_label(isset($p['price_level']) && $p['price_level'] !== null ? (int) $p['price_level'] : null);
    if ($price !== null) $out['priceRange'] = $price;

    $spec = rmt_place_hours_schema($hours);
    if ($spec) $out['openingHoursSpecification'] = $spec;

    return $out;
}
