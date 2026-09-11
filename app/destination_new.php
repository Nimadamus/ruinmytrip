<?php
/**
 * Adding a city, properly.
 *
 * Until now a destination could only arrive through the seed file, so adding one meant editing
 * code and redeploying. That is the real reason Miami has no record on this site: not a policy
 * decision, an architecture gap.
 *
 * The rule this file exists to enforce is that a city page must never be published empty. Every
 * field here is either a checkable fact somebody typed or is left blank; nothing is generated,
 * nothing is guessed from the name, and there is no "starter summary". A draft can sit unfinished
 * for as long as it needs to, because until it is published it lives in a table no other query in
 * this application reads.
 *
 * What publishing requires, and why each one:
 *   name, country   a page cannot be titled without them
 *   lat, lng        the map, the place importer and "who else is going" all start from a point
 *   category        how the city is filed on /explore, from the list already in use
 *   summary         the only actual writing, and the reason this is editorial work: 120 characters
 *                   is about a sentence and a half, which is the least that is worth reading
 * A hero photograph is optional, and if there is one it must carry its credit, its licence and the
 * page it came from, because a photograph without those is one we are not entitled to publish.
 */
declare(strict_types=1);

/** How /explore files a city. The list already in use, not a new vocabulary. */
const RMT_DEST_CATEGORIES = ['city', 'culture', 'beach', 'nature', 'food', 'adventure'];

/** The shortest summary worth publishing. A sentence and a half. */
const RMT_DEST_SUMMARY_MIN = 120;

/**
 * Check a submitted draft. Returns the cleaned values and everything wrong with them.
 *
 * @param bool $forPublish false while saving a draft, true when it is about to become a page
 * @return array{ok:bool,errors:list<string>,data:array<string,mixed>}
 */
function rmt_dest_draft_validate(array $in, bool $forPublish, int $draftId = 0): array {
    $e = [];
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 80) $e[] = 'A city needs a name, under 80 characters.';

    $country = trim((string) ($in['country'] ?? ''));
    if ($country === '' || mb_strlen($country) > 80) $e[] = 'Name the country.';

    $region = trim((string) ($in['region'] ?? ''));
    if (mb_strlen($region) > 80) $e[] = 'That region is too long.';

    /* A point, not an approximation. Blank is allowed while drafting and refused at publish,
       because the map, the importer and the overlap search all start from it. */
    $latRaw = trim((string) ($in['lat'] ?? ''));
    $lngRaw = trim((string) ($in['lng'] ?? ''));
    $lat = $latRaw === '' ? null : (float) $latRaw;
    $lng = $lngRaw === '' ? null : (float) $lngRaw;
    if ($latRaw !== '' && (!is_numeric($latRaw) || $lat < -90 || $lat > 90)) {
        $e[] = 'Latitude is between -90 and 90.';
    }
    if ($lngRaw !== '' && (!is_numeric($lngRaw) || $lng < -180 || $lng > 180)) {
        $e[] = 'Longitude is between -180 and 180.';
    }
    if ($forPublish && ($lat === null || $lng === null)) {
        $e[] = 'A city needs coordinates before it can be published: the map, the place importer '
             . 'and the overlap search all start from them.';
    }
    // 0,0 is in the Atlantic and is what an empty form submits when somebody types a zero.
    if ($lat !== null && $lng !== null && abs($lat) < 0.0001 && abs($lng) < 0.0001) {
        $e[] = 'Those coordinates are in the middle of the Atlantic.';
    }

    $category = trim((string) ($in['category'] ?? ''));
    if ($category !== '' && !in_array($category, RMT_DEST_CATEGORIES, true)) {
        $e[] = 'That is not one of the categories.';
    }
    if ($forPublish && $category === '') $e[] = 'Choose a category, so the city can be found on Explore.';

    $summary = trim((string) ($in['summary'] ?? ''));
    if (mb_strlen($summary) > 2000) $e[] = 'That summary is very long. Two thousand characters is the cap.';
    if ($forPublish && mb_strlen($summary) < RMT_DEST_SUMMARY_MIN) {
        $e[] = 'Write a summary of at least ' . RMT_DEST_SUMMARY_MIN
             . ' characters. A city page with nothing on it is worse than no city page.';
    }

    /* A photograph we are not entitled to publish is worse than no photograph. If there is one, it
       carries who took it, under what licence, and where it came from. */
    $hero = trim((string) ($in['hero_url'] ?? ''));
    $credit = trim((string) ($in['hero_credit'] ?? ''));
    $licence = trim((string) ($in['hero_license'] ?? ''));
    $source = trim((string) ($in['hero_source_url'] ?? ''));
    foreach ([['photograph', $hero], ['source page', $source]] as [$label, $u]) {
        if ($u !== '' && !preg_match('#^https://\S+$#', $u)) {
            $e[] = 'The ' . $label . ' has to be an https address.';
        }
    }
    if ($hero !== '' && ($credit === '' || $licence === '' || $source === '')) {
        $e[] = 'A photograph needs its credit, its licence and the page it came from.';
    }

    $slug = trim((string) ($in['slug'] ?? ''));
    if ($slug === '') $slug = slugify($name . ' ' . $country);
    if (!preg_match('/^[a-z0-9\-]{3,80}$/', $slug)) {
        $e[] = 'The web address may only be lowercase letters, digits and hyphens.';
    }
    if (q_one('SELECT 1 x FROM destinations WHERE slug = ?', [$slug])) {
        $e[] = 'There is already a city at that web address.';
    }
    $clash = q_one('SELECT id FROM destination_drafts WHERE slug = ?', [$slug]);
    if ($clash && (int) $clash['id'] !== $draftId) $e[] = 'There is already a draft at that web address.';

    return ['ok' => $e === [], 'errors' => $e, 'data' => [
        'slug' => $slug, 'name' => $name, 'country' => $country, 'region' => $region ?: null,
        'lat' => $lat, 'lng' => $lng, 'category' => $category ?: null, 'summary' => $summary ?: null,
        'hero_url' => $hero ?: null, 'hero_credit' => $credit ?: null,
        'hero_license' => $licence ?: null, 'hero_source_url' => $source ?: null,
    ]];
}

/** The columns a draft carries, in one place so the three writers cannot drift apart. */
const RMT_DEST_DRAFT_COLS = ['slug', 'name', 'country', 'region', 'lat', 'lng', 'category',
                             'summary', 'hero_url', 'hero_credit', 'hero_license', 'hero_source_url'];

/** Write a draft, new or existing. Returns its id. */
function rmt_dest_draft_save(array $data, int $userId, int $draftId = 0): int {
    $now = date('Y-m-d H:i:s');
    $cols = RMT_DEST_DRAFT_COLS;
    $args = array_map(static fn(string $c) => $data[$c] ?? null, $cols);
    if ($draftId > 0) {
        $set = implode(', ', array_map(static fn(string $c): string => $c . ' = ?', $cols));
        $args[] = $now;
        $args[] = $draftId;
        q_run('UPDATE destination_drafts SET ' . $set . ', updated_at = ? WHERE id = ?', $args);
        return $draftId;
    }
    $args[] = $userId;
    $args[] = $now;
    q_run('INSERT INTO destination_drafts (' . implode(',', $cols) . ', created_by, created_at)
           VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ', ?, ?)', $args);
    return (int) db()->lastInsertId();
}

/**
 * Turn a finished draft into a city.
 *
 * One insert, at which point every existing query sees a complete row and none of them needed to
 * change. The draft is kept and marked, so there is a record of who added the city and when.
 *
 * @return array{ok:bool,errors:list<string>,destination_id:?int,slug:?string}
 */
function rmt_dest_draft_publish(int $draftId): array {
    $d = q_one('SELECT * FROM destination_drafts WHERE id = ?', [$draftId]);
    if (!$d) return ['ok' => false, 'errors' => ['No such draft.'], 'destination_id' => null, 'slug' => null];
    if (!empty($d['published_destination_id'])) {
        return ['ok' => false, 'errors' => ['That draft is already a city.'],
                'destination_id' => (int) $d['published_destination_id'], 'slug' => (string) $d['slug']];
    }

    $v = rmt_dest_draft_validate((array) $d, true, $draftId);
    if (!$v['ok']) return ['ok' => false, 'errors' => $v['errors'], 'destination_id' => null, 'slug' => null];
    $data = $v['data'];

    $cols = ['slug', 'name', 'country', 'region', 'lat', 'lng', 'summary', 'category',
             'hero_url', 'hero_credit', 'hero_license', 'hero_source_url'];
    $args = array_map(static fn(string $c) => $data[$c] ?? null, $cols);
    if (function_exists('rmt_search_norm')) {
        $cols[] = 'name_norm';
        $args[] = rmt_search_norm((string) $data['name']);
    }
    q_run('INSERT INTO destinations (' . implode(',', $cols) . ')
           VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', $args);
    $id = (int) db()->lastInsertId();
    q_run('UPDATE destination_drafts SET published_destination_id = ?, updated_at = ? WHERE id = ?',
          [$id, date('Y-m-d H:i:s'), $draftId]);
    return ['ok' => true, 'errors' => [], 'destination_id' => $id, 'slug' => (string) $data['slug']];
}

/** Drafts that are not cities yet, newest first. */
function rmt_dest_drafts(): array {
    return q_all('SELECT * FROM destination_drafts WHERE published_destination_id IS NULL
                  ORDER BY id DESC LIMIT 100');
}
