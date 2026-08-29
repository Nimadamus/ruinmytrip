<?php
declare(strict_types=1);

/**
 * Category-specific review subratings and traveler type.
 *
 * `reviews.rating` is the overall score and is untouched by everything in this file. It stays one
 * indexed column on the review row because it is read on the place page, the destination page, the
 * profile, the leaderboard and every aggregate on the site.
 *
 * Everything else a reviewer can score lives in `review_ratings` as one row per (review, aspect).
 * The vocabulary is closed and per-category: a restaurant review is never asked about hotel rooms,
 * and an aspect that does not belong to the reviewed category is not stored even if it is posted.
 *
 * Two things this file refuses to do:
 *
 *   1. Trust the form. The browser only renders the aspects that apply, which says nothing about
 *      what arrives in $_POST. Every aspect key and every value is checked server-side.
 *   2. Present one person's opinion as a community figure. An aspect average is only shown once
 *      RMT_ASPECT_MIN_SAMPLE people have rated it. The individual ratings are always stored; the
 *      threshold governs display, not storage.
 */

/**
 * Every aspect the site knows about, with the words that go beside each score on the form.
 *
 * The scale wording matters more than it looks: "3 — Fine" and "3 — Mixed" are answers to different
 * questions, and a shared generic scale is how a subrating stops meaning anything.
 */
const RMT_REVIEW_ASPECTS = [
    'rooms'         => ['label' => 'Rooms',          'scale' => ['', 'Grim', 'Tired', 'Fine', 'Good', 'Excellent']],
    'cleanliness'   => ['label' => 'Cleanliness',    'scale' => ['', 'Dirty', 'Patchy', 'Acceptable', 'Clean', 'Spotless']],
    'service'       => ['label' => 'Service',        'scale' => ['', 'Rude', 'Indifferent', 'Fine', 'Helpful', 'Outstanding']],
    'location'      => ['label' => 'Location',       'scale' => ['', 'Badly placed', 'Awkward', 'Workable', 'Convenient', 'Perfectly placed']],
    'food'          => ['label' => 'Food',           'scale' => ['', 'Bad', 'Forgettable', 'Decent', 'Very good', 'Exceptional']],
    'atmosphere'    => ['label' => 'Atmosphere',     'scale' => ['', 'Miserable', 'Flat', 'Pleasant', 'Great', 'Special']],
    'experience'    => ['label' => 'The experience', 'scale' => ['', 'Waste of time', 'Underwhelming', 'Worth a look', 'Very good', 'Unmissable']],
    'accessibility' => ['label' => 'Accessibility',  'scale' => ['', 'Inaccessible', 'Difficult', 'Manageable', 'Good', 'Fully accessible']],
    'crowds'        => ['label' => 'Crowds',         'scale' => ['', 'Unbearable', 'Packed', 'Busy', 'Manageable', 'Quiet']],
    'value'         => ['label' => 'Value for money','scale' => ['', 'Rip-off', 'Overpriced', 'Fair', 'Good value', 'Bargain']],
    'safety'        => ['label' => 'Safety',         'scale' => ['', 'Felt unsafe', 'Uneasy', 'Mixed', 'Mostly fine', 'Felt safe']],
];

/**
 * Which aspects each kind of review asks about, in the order they are shown.
 *
 * `safety` and `value` appear everywhere because the form has always asked them everywhere and
 * people have answered: dropping either would delete a working field and orphan real data. The rest
 * are per-category and deliberately few. A form with fifteen sliders does not get filled in.
 */
const RMT_ASPECTS_BY_CATEGORY = [
    'destination' => ['safety', 'value'],
    'hotel'       => ['rooms', 'cleanliness', 'service', 'location', 'value', 'safety'],
    'restaurant'  => ['food', 'service', 'atmosphere', 'value', 'safety'],
    'attraction'  => ['experience', 'crowds', 'accessibility', 'value', 'safety'],
    'experience'  => ['experience', 'service', 'value', 'safety'],
];

/**
 * The two aspects that also live as columns on `reviews`.
 *
 * They predate this table. rmt_place_stats() and the place page read the columns, so the write path
 * keeps them in step by deriving them from the aspect values on every save. Derived on write, never
 * edited independently, so the two cannot disagree.
 */
const RMT_ASPECT_MIRROR_COLUMNS = ['safety' => 'safety_rating', 'value' => 'value_rating'];

/**
 * How many people must rate an aspect before its average is shown as a community figure.
 *
 * Three. One rating is a person, not a consensus, and putting "Service 5.0" on a page off a single
 * vote is the same borrowed-credibility problem as inventing a review. Two is a coin toss. Three is
 * low enough that a young page can still say something and high enough that one outlier cannot
 * define an aspect on its own. The raw ratings are always stored either way, so raising or lowering
 * this later changes what is displayed and nothing else.
 */
const RMT_ASPECT_MIN_SAMPLE = 3;

/** Who the reviewer was travelling as. Optional everywhere. */
const RMT_TRAVELER_TYPES = ['solo', 'couple', 'family', 'friends', 'business', 'other'];

/** Human labels for traveler type. */
function rmt_traveler_type_label(?string $t): ?string {
    return [
        'solo' => 'Solo', 'couple' => 'Couple', 'family' => 'Family',
        'friends' => 'With friends', 'business' => 'Business', 'other' => 'Other',
    ][$t] ?? null;
}

/**
 * A traveler type we accept, or null.
 *
 * Null for anything unrecognised rather than an error: this is one optional dropdown, and a value
 * we do not know is a value we do not store. The Postgres CHECK constraint is the backstop.
 */
function rmt_traveler_type_clean($raw): ?string {
    $v = strtolower(trim((string) $raw));
    return in_array($v, RMT_TRAVELER_TYPES, true) ? $v : null;
}

/**
 * The aspects a review of this category asks about.
 * @return list<string>
 */
function rmt_aspects_for_category(string $category): array {
    return RMT_ASPECTS_BY_CATEGORY[$category] ?? [];
}

/** Does this aspect belong to this category? */
function rmt_aspect_applies(string $category, string $aspect): bool {
    return in_array($aspect, rmt_aspects_for_category($category), true);
}

/** Is this a key the site knows at all? */
function rmt_aspect_exists(string $aspect): bool {
    return isset(RMT_REVIEW_ASPECTS[$aspect]);
}

/** Display label for an aspect, falling back to the raw key for anything historic. */
function rmt_aspect_label(string $aspect): string {
    return RMT_REVIEW_ASPECTS[$aspect]['label'] ?? ucfirst(str_replace('_', ' ', $aspect));
}

/**
 * Read submitted aspect ratings.
 *
 * Three outcomes, and the difference between them is the whole point:
 *
 *   - a valid aspect for this category with a value 1-5  -> kept
 *   - a valid aspect for this category left blank        -> null, which CLEARS any existing rating
 *   - an aspect that exists but belongs to another category -> dropped, silently and without error
 *
 * The last case is not laxness. A writer who changes "Hotel" to "Restaurant" with JavaScript off
 * still has the hotel fields in their form; refusing the whole submission would throw away the
 * review they wrote over a field they cannot see. Nothing invalid is stored either way.
 *
 * An aspect key that is not in the vocabulary at all, or a value outside 1-5, is a malformed
 * submission and IS an error: that cannot come from a person using the form.
 *
 * @param  array<string,mixed> $in    raw $_POST
 * @return array{ok:bool,errors:list<string>,values:array<string,?int>,dropped:list<string>}
 */
function rmt_review_parse_aspects(array $in, string $category): array {
    $posted = $in['aspect'] ?? [];
    if (!is_array($posted)) {
        return ['ok' => false, 'errors' => ['Those ratings were not submitted correctly.'],
                'values' => [], 'dropped' => []];
    }

    $values = [];
    $dropped = [];
    $errors = [];

    foreach ($posted as $aspect => $raw) {
        $aspect = is_string($aspect) ? strtolower(trim($aspect)) : '';
        if (!rmt_aspect_exists($aspect)) { $errors[] = 'Unknown rating field.'; continue; }
        if (!rmt_aspect_applies($category, $aspect)) { $dropped[] = $aspect; continue; }

        if (is_array($raw)) { $errors[] = 'Unknown rating field.'; continue; }
        $raw = trim((string) $raw);
        if ($raw === '') { $values[$aspect] = null; continue; }        // explicit clear
        if (!preg_match('/^[1-5]$/', $raw)) { $errors[] = 'Ratings must be from 1 to 5.'; continue; }
        $values[$aspect] = (int) $raw;
    }

    // Any aspect this category asks about that was not posted at all is also a clear. A form that
    // renders five selects and posts four means the fifth was removed, not left alone.
    foreach (rmt_aspects_for_category($category) as $aspect) {
        if (!array_key_exists($aspect, $values)) $values[$aspect] = null;
    }

    return ['ok' => !$errors, 'errors' => array_values(array_unique($errors)),
            'values' => $values, 'dropped' => $dropped];
}

/**
 * The aspect values as the writer just typed them, for re-rendering a form after a validation
 * error. Not validated against a category: the point is to hand back exactly what they had so a
 * failed submission does not silently blank the selects they already filled in.
 *
 * @return array<string,int>
 */
function rmt_posted_aspect_values(array $in): array {
    $out = [];
    foreach ((array) ($in['aspect'] ?? []) as $aspect => $raw) {
        if (!is_string($aspect) || is_array($raw)) continue;
        if (!rmt_aspect_exists($aspect)) continue;
        $raw = trim((string) $raw);
        if (preg_match('/^[1-5]$/', $raw)) $out[$aspect] = (int) $raw;
    }
    return $out;
}

/**
 * Write a review's aspect ratings: insert new ones, update changed ones, delete cleared ones.
 *
 * Only the aspects present in $values are touched, so a caller that parsed one category cannot wipe
 * a rating belonging to another. Runs in one transaction: a half-applied set of subratings is a set
 * of numbers that never came from anybody.
 *
 * @param array<string,?int> $values aspect => 1-5, or null to remove
 */
function rmt_review_save_aspects(int $reviewId, array $values): void {
    if ($reviewId <= 0 || !$values) return;

    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        foreach ($values as $aspect => $value) {
            if (!rmt_aspect_exists((string) $aspect)) continue;   // never trust a caller either
            if ($value === null) {
                q_run('DELETE FROM review_ratings WHERE review_id = ? AND aspect = ?', [$reviewId, $aspect]);
                continue;
            }
            $v = max(1, min(5, (int) $value));
            // The unique index on (review_id, aspect) makes a duplicate impossible; this is the
            // upsert that respects it without needing driver-specific ON CONFLICT syntax.
            q_run('UPDATE review_ratings SET value = ? WHERE review_id = ? AND aspect = ?',
                  [$v, $reviewId, $aspect]);
            if (!q_one('SELECT 1 FROM review_ratings WHERE review_id = ? AND aspect = ?', [$reviewId, $aspect])) {
                q_run('INSERT INTO review_ratings (review_id, aspect, value) VALUES (?,?,?)',
                      [$reviewId, $aspect, $v]);
            }
        }
        rmt_review_sync_mirror_columns($reviewId);
        if ($own) $pdo->commit();
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Keep reviews.safety_rating / reviews.value_rating equal to the corresponding aspect rows.
 *
 * Those columns predate review_ratings and are still read by rmt_place_stats() and the place page.
 * Deriving them here, on the one path that writes aspects, means there is one source of truth and a
 * mirror that cannot disagree with it.
 */
function rmt_review_sync_mirror_columns(int $reviewId): void {
    $set = [];
    $args = [];
    foreach (RMT_ASPECT_MIRROR_COLUMNS as $aspect => $column) {
        $row = q_one('SELECT value FROM review_ratings WHERE review_id = ? AND aspect = ?', [$reviewId, $aspect]);
        $set[] = $column . ' = ?';
        $args[] = $row ? (int) $row['value'] : null;
    }
    if (!$set) return;
    $args[] = $reviewId;
    q_run('UPDATE reviews SET ' . implode(', ', $set) . ' WHERE id = ?', $args);
}

/**
 * One review's aspect ratings.
 * @return array<string,int> aspect => 1-5
 */
function rmt_review_aspect_values(int $reviewId): array {
    $out = [];
    foreach (q_all('SELECT aspect, value FROM review_ratings WHERE review_id = ?', [$reviewId]) as $r) {
        $out[(string) $r['aspect']] = (int) $r['value'];
    }
    return $out;
}

/**
 * Aspect ratings for many reviews at once.
 *
 * One query for the whole set. The alternative — asking per review while rendering a list — is the
 * N+1 that turns a 50-review page into 51 round trips.
 *
 * @param  list<int> $reviewIds
 * @return array<int,array<string,int>> review id => aspect => value
 */
function rmt_review_aspect_map(array $reviewIds): array {
    $ids = array_values(array_unique(array_map('intval', $reviewIds)));
    $ids = array_values(array_filter($ids, static fn(int $i) => $i > 0));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $out = [];
    foreach (q_all('SELECT review_id, aspect, value FROM review_ratings WHERE review_id IN (' . $in . ')', $ids) as $r) {
        $out[(int) $r['review_id']][(string) $r['aspect']] = (int) $r['value'];
    }
    return $out;
}

/**
 * Community aspect averages for one place.
 *
 * A single grouped query. Editorial ratings are excluded by role, exactly as the overall average
 * excludes them: an aspect score always means "what travelers said".
 *
 * Every aspect with at least one rating is returned, with its count, so a caller can decide what to
 * do below the threshold. `show` is the answer to "may this be printed as a community figure".
 *
 * @return list<array{aspect:string,label:string,avg:float,count:int,show:bool}>
 */
function rmt_place_aspect_averages(int $placeId): array {
    if ($placeId <= 0) return [];
    $rows = q_all(
        "SELECT ra.aspect, AVG(ra.value * 1.0) a, COUNT(*) c
           FROM review_ratings ra
           JOIN reviews r ON r.id = ra.review_id
           JOIN users u   ON u.id = r.user_id
          WHERE r.place_id = ? AND r.status = 'published' AND u.role <> ?
          GROUP BY ra.aspect",
        [$placeId, RMT_EDITORIAL_ROLE]
    );
    $type = (string) (q_one('SELECT type FROM places WHERE id = ?', [$placeId])['type'] ?? '');

    $out = [];
    foreach ($rows as $r) {
        $aspect = (string) $r['aspect'];
        $count = (int) $r['c'];
        $out[] = [
            'aspect' => $aspect,
            'label'  => rmt_aspect_label($aspect),
            'avg'    => round((float) $r['a'], 1),
            'count'  => $count,
            'show'   => $count >= RMT_ASPECT_MIN_SAMPLE,
        ];
    }

    // Present them in the order this kind of place asks them on the form, so the page and the form
    // agree. Anything outside that list -- an aspect from a category the place used to be filed
    // under, or one retired from the vocabulary -- keeps its rating and sorts after.
    $order = array_flip(rmt_aspects_for_category($type));
    $tail = count($order);
    usort($out, static fn(array $x, array $y) =>
        ($order[$x['aspect']] ?? $tail) <=> ($order[$y['aspect']] ?? $tail)
        ?: strcmp($x['aspect'], $y['aspect']));
    return $out;
}

/** Only the aspect averages a place has enough ratings to publish. */
function rmt_place_aspect_averages_shown(int $placeId): array {
    return array_values(array_filter(rmt_place_aspect_averages($placeId), static fn(array $r) => $r['show']));
}
