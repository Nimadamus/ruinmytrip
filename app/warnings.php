<?php
declare(strict_types=1);

/**
 * Travel warnings — the core domain of RuinMyTrip.
 *
 * A warning is one specific problem that could ruin a trip: a scam, a fee nobody mentions, a
 * closure, a neighbourhood that photographs better than it lives. Everything here exists to keep
 * that useful and honest rather than sensational:
 *
 *   * SEVERITY IS DEFINED, NOT VIBES. The four levels below have written definitions shown on the
 *     submission form, so "severe" means the same thing across submissions.
 *   * MODERATION AND EVIDENCE ARE SEPARATE. `status` answers "may this be published?";
 *     `verification` answers "has anyone corroborated it?". A published warning is an unverified
 *     traveler account until a moderator says otherwise, and it renders labelled that way.
 *   * NAMING A BUSINESS IS AN ALLEGATION. Anything with a provider_name is held to the same
 *     unverified label and the business can respond (warning_responses). We never render a
 *     traveler's account as an established fact about a named company.
 *   * DATES ARE LOAD-BEARING. Travel facts rot. Both the date experienced and the date submitted
 *     are required at render time, and rmt_warning_is_stale() flags old ones.
 */

/** The ten things that ruin trips. Order is the display order everywhere on the site. */
const RMT_WARNING_CATEGORIES = [
    'scams'              => ['label' => 'Scams',                'icon' => '🎭', 'blurb' => 'Setups aimed at tourists: fake officials, rigged taxis, bait pricing, street games.'],
    'hidden-costs'       => ['label' => 'Hidden Costs',         'icon' => '💸', 'blurb' => 'Resort fees, tourist taxes, service charges, card surcharges, deposits you did not budget for.'],
    'neighborhoods'      => ['label' => 'Neighborhood Choices', 'icon' => '🗺️', 'blurb' => 'Areas that look central on a map but are loud, isolated, or a long way from what you came for.'],
    'transportation'     => ['label' => 'Transportation',       'icon' => '🚇', 'blurb' => 'Transfers, ticket rules, rideshare bans, rail strikes, driving and parking traps.'],
    'weather'            => ['label' => 'Weather',              'icon' => '🌧️', 'blurb' => 'Rainy seasons, heat, hurricane windows, smog and wildfire smoke, cold that closes routes.'],
    'crowds'             => ['label' => 'Crowds',               'icon' => '👥', 'blurb' => 'Overtourism, cruise-ship days, festival weeks, timed-entry sellouts.'],
    'closures'           => ['label' => 'Closures',             'icon' => '🚧', 'blurb' => 'Restoration scaffolding, seasonal shutdowns, strikes, public holidays, construction.'],
    'health-safety'      => ['label' => 'Health & Safety',      'icon' => '🩺', 'blurb' => 'Petty crime patterns, water and food risk, altitude, medical access and costs.'],
    'entry-requirements' => ['label' => 'Entry Requirements',   'icon' => '🛂', 'blurb' => 'Visas and travel authorisations, passport validity, onward-ticket and insurance proof.'],
    'accommodation'      => ['label' => 'Accommodation',        'icon' => '🏨', 'blurb' => 'Listings that misrepresent, surprise charges, noise, cancellations, deposit disputes.'],
];

/**
 * Severity, with the definitions the submitter actually reads. Without these, "severe" drifts to
 * mean "annoyed me", which is exactly how a warnings site turns into a complaints site.
 */
const RMT_WARNING_SEVERITIES = [
    1 => ['label' => 'Minor',    'desc' => 'Mildly annoying. Worth knowing, would not change plans.'],
    2 => ['label' => 'Moderate', 'desc' => 'Cost real money or a couple of hours. Worth planning around.'],
    3 => ['label' => 'Serious',  'desc' => 'Ruined a day, or cost a significant amount. Would change how I booked.'],
    4 => ['label' => 'Severe',   'desc' => 'Ruined a large part of the trip, or was a genuine safety issue.'],
];

const RMT_WARNING_STATUSES     = ['draft', 'pending', 'approved', 'rejected', 'needs_revision'];
const RMT_WARNING_VERIFICATION = ['unverified', 'verified', 'disputed'];

const RMT_TRAVELER_TYPES = [
    'solo' => 'Solo traveler', 'couple' => 'Couple', 'family' => 'Family with kids',
    'group' => 'Group of friends', 'business' => 'Business trip', 'backpacker' => 'Backpacker / budget',
    'senior' => 'Older traveler', 'accessibility' => 'Traveling with accessibility needs',
];

const RMT_PROVIDER_TYPES = [
    'hotel' => 'Hotel / accommodation', 'airline' => 'Airline', 'airport' => 'Airport',
    'transport' => 'Transport operator', 'tour' => 'Tour or activity operator',
    'restaurant' => 'Restaurant or bar', 'attraction' => 'Attraction', 'other' => 'Other business',
];

const RMT_WARNING_TITLE_MAX = 140;
const RMT_WARNING_BODY_MIN  = 80;
const RMT_WARNING_BODY_MAX  = 8000;
const RMT_WARNING_FIELD_MAX = 2000;
/** Submissions per user per hour. Deliberately low: a warnings queue is only as good as its floor. */
const RMT_WARNING_RATE_PER_HOUR = 6;

/* ---------------------------------------------------------------- labels */

function rmt_warning_category_label(?string $key): string {
    return RMT_WARNING_CATEGORIES[(string) $key]['label'] ?? 'Other';
}
function rmt_warning_category_icon(?string $key): string {
    return RMT_WARNING_CATEGORIES[(string) $key]['icon'] ?? '⚠️';
}
function rmt_severity_label(?int $n): string {
    return RMT_WARNING_SEVERITIES[(int) $n]['label'] ?? 'Moderate';
}
/** CSS modifier for severity chips; keeps colour decisions in one place. */
function rmt_severity_class(?int $n): string {
    return 'sev-' . max(1, min(4, (int) $n));
}
function rmt_traveler_type_label(?string $k): string {
    return RMT_TRAVELER_TYPES[(string) $k] ?? '';
}

/**
 * Overall destination risk, 1-4, with the same vocabulary as warning severity so a reader does
 * not have to learn two scales.
 */
const RMT_RISK_LEVELS = [
    1 => ['label' => 'Low friction',   'desc' => 'Few trip-ruining problems reported. Normal travel care is enough.'],
    2 => ['label' => 'Some friction',  'desc' => 'A handful of well-known traps. Easy to avoid once you know them.'],
    3 => ['label' => 'High friction',  'desc' => 'Several common, expensive or plan-changing problems. Read before booking.'],
    4 => ['label' => 'Plan carefully', 'desc' => 'Serious or widespread issues that regularly affect trips here.'],
];
function rmt_risk_level_label(?int $n): string {
    return RMT_RISK_LEVELS[(int) $n]['label'] ?? 'Not yet rated';
}

/* ------------------------------------------------------- risk report sections */

/**
 * The fixed spine of a destination risk report. Every destination page renders these in this
 * order, skipping the ones with no reviewed content rather than printing an empty heading.
 *
 * `type` is the default trust label for the section (see migration 041); a row may override it.
 */
function rmt_risk_section_defs(): array {
    return [
        'overview'           => ['heading' => 'Destination overview',              'type' => 'editorial'],
        'worth_visiting'     => ['heading' => 'Is it worth visiting?',             'type' => 'editorial'],
        'top_risks'          => ['heading' => 'Top things that could ruin the trip','type' => 'editorial'],
        'scams'              => ['heading' => 'Common scams',                      'type' => 'fact'],
        'hidden_costs'       => ['heading' => 'Hidden costs',                      'type' => 'fact'],
        'neighborhoods'      => ['heading' => 'Areas to research carefully',       'type' => 'editorial'],
        'transportation'     => ['heading' => 'Transportation mistakes',           'type' => 'fact'],
        'airport'            => ['heading' => 'Airport issues',                    'type' => 'fact'],
        'weather'            => ['heading' => 'Weather and seasonal concerns',     'type' => 'fact'],
        'crowds'             => ['heading' => 'Crowding and overtourism',          'type' => 'fact'],
        // Health and safety is one of the ten warning categories, so the report spine carries a
        // matching section — heat, air quality, water, altitude and petty-crime patterns are the
        // things a reader most often wants a sourced answer on, and they fit none of the others.
        'health_safety'      => ['heading' => 'Health and safety',                 'type' => 'fact'],
        'closures'           => ['heading' => 'Closures and construction',         'type' => 'alert'],
        'entry_requirements' => ['heading' => 'Entry requirements and documents',  'type' => 'fact'],
        'accommodation'      => ['heading' => 'Accommodation warnings',            'type' => 'editorial'],
    ];
}

/** Which warning category a risk section corresponds to, for cross-linking. Not all map. */
function rmt_section_to_category(string $key): ?string {
    return [
        'scams' => 'scams', 'hidden_costs' => 'hidden-costs', 'neighborhoods' => 'neighborhoods',
        'transportation' => 'transportation', 'airport' => 'transportation', 'weather' => 'weather',
        'crowds' => 'crowds', 'health_safety' => 'health-safety', 'closures' => 'closures',
        'entry_requirements' => 'entry-requirements', 'accommodation' => 'accommodation',
    ][$key] ?? null;
}

/** Reviewed risk sections for a destination, keyed by section_key, in spine order. */
function rmt_risk_sections(int $destId): array {
    $rows = q_all('SELECT * FROM destination_risk_sections WHERE destination_id = ?', [$destId]);
    $by = [];
    foreach ($rows as $r) $by[(string) $r['section_key']] = $r;
    $out = [];
    foreach (rmt_risk_section_defs() as $key => $def) {
        if (isset($by[$key]) && trim((string) $by[$key]['body']) !== '') {
            $row = $by[$key];
            $row['heading'] = $row['heading'] ?: $def['heading'];
            $out[$key] = $row;
        }
    }
    return $out;
}

function rmt_destination_faqs(int $destId): array {
    return q_all('SELECT * FROM destination_faqs WHERE destination_id = ? ORDER BY sort, id', [$destId]);
}

/** Decoded sources list for a section/page row, or []. */
function rmt_sources(?string $json): array {
    if (!$json) return [];
    $v = json_decode($json, true);
    return is_array($v) ? $v : [];
}

/* ------------------------------------------------------------- validation */

/**
 * Validate and normalise a submitted warning.
 *
 * Drafts are held to a lower bar on purpose — a half-written warning the user can come back to is
 * better than one abandoned at a validation wall. Publishing (which here means "enter the
 * moderation queue") requires the whole thing, including the genuine-experience attestation.
 *
 * @return array{ok:bool, errors:string[], data:array<string,mixed>}
 */
function rmt_warning_validate(array $in, bool $isDraft): array {
    $errors = [];

    $destId   = (int) ($in['destination_id'] ?? 0);
    $category = trim((string) ($in['category'] ?? ''));
    $title    = trim((string) ($in['title'] ?? ''));
    $body     = trim((string) ($in['body'] ?? ''));
    $advice   = trim((string) ($in['advice'] ?? ''));
    $where    = trim((string) ($in['location_detail'] ?? ''));
    $provType = trim((string) ($in['provider_type'] ?? ''));
    $provName = trim((string) ($in['provider_name'] ?? ''));
    $travel   = trim((string) ($in['traveler_type'] ?? ''));
    $when     = trim((string) ($in['date_experienced'] ?? ''));
    $severity = (int) ($in['severity'] ?? 0);
    $costRaw  = trim((string) ($in['cost_impact_usd'] ?? ''));
    $attested = !empty($in['attested']);

    if ($destId <= 0 || !dest_by_id($destId)) {
        $errors[] = 'Choose a destination from the suggestions as you type.';
    }
    if (!array_key_exists($category, RMT_WARNING_CATEGORIES)) {
        $errors[] = 'Choose the category that best fits the problem.';
    }
    if ($provType !== '' && !array_key_exists($provType, RMT_PROVIDER_TYPES)) $provType = '';
    if ($travel !== '' && !array_key_exists($travel, RMT_TRAVELER_TYPES)) $travel = '';

    if (!$isDraft) {
        if ($title === '')   $errors[] = 'Add a short title that says what the problem was.';
        if ($severity < 1 || $severity > 4) $errors[] = 'Choose how badly this affected the trip.';
        if (mb_strlen($body) < RMT_WARNING_BODY_MIN) {
            $errors[] = 'Explain what happened in at least ' . RMT_WARNING_BODY_MIN
                      . ' characters so another traveler can recognise it.';
        }
        if ($when === '') $errors[] = 'Tell us when you experienced this — travel problems date fast.';
        if (!$attested)   $errors[] = 'Please confirm this is your own genuine experience.';
    } elseif ($severity !== 0 && ($severity < 1 || $severity > 4)) {
        $errors[] = 'Severity must be between 1 and 4.';
    }

    if (mb_strlen($title)    > RMT_WARNING_TITLE_MAX) $errors[] = 'Title is too long.';
    if (mb_strlen($body)     > RMT_WARNING_BODY_MAX)  $errors[] = 'Explanation is too long.';
    if (mb_strlen($advice)   > RMT_WARNING_FIELD_MAX) $errors[] = 'Advice is too long.';
    if (mb_strlen($where)    > 200) $errors[] = 'That location is too long.';
    if (mb_strlen($provName) > 200) $errors[] = 'That business name is too long.';

    // A future date would mean warning about something that has not happened.
    $month = null;
    if ($when !== '') {
        $norm = preg_match('/^\d{4}-\d{2}$/', $when) ? $when . '-01' : $when;
        $ts = strtotime($norm);
        if ($ts === false)                     $errors[] = 'That date is not a valid date.';
        elseif ($ts > time())                  $errors[] = 'That date is in the future.';
        elseif ($ts < strtotime('-15 years'))  $errors[] = 'That date is too far in the past to still be useful.';
        else                                   $month = (int) date('n', $ts);
    }

    $cost = null;
    if ($costRaw !== '') {
        $clean = preg_replace('/[^0-9.]/', '', $costRaw);
        if ($clean === '' || !is_numeric($clean)) {
            $errors[] = 'Estimated cost must be a number in US dollars.';
        } else {
            $cost = (int) round((float) $clean);
            if ($cost < 0 || $cost > 1000000) $errors[] = 'That estimated cost looks out of range.';
        }
    }

    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'destination_id'   => $destId ?: null,
        'category'         => array_key_exists($category, RMT_WARNING_CATEGORIES) ? $category : 'scams',
        'title'            => $title,
        'body'             => $body,
        'advice'           => $advice ?: null,
        'location_detail'  => $where ?: null,
        'provider_type'    => $provType ?: null,
        'provider_name'    => $provName ?: null,
        'traveler_type'    => $travel ?: null,
        'date_experienced' => $when ?: null,
        'season_month'     => $month,
        'severity'         => max(1, min(4, $severity ?: 2)),
        'cost_impact_usd'  => $cost,
        'attested'         => $attested ? 1 : 0,
    ]];
}

/**
 * Fingerprint used to catch duplicate submissions.
 *
 * Deliberately coarse: destination + category + a normalised, stop-word-stripped bag of the
 * title's significant words. Two people describing the same airport taxi scam in different words
 * SHOULD both be recorded (that is corroboration, and the moderator wants to see it) — what this
 * catches is the same person posting the same text twice, or a bot varying punctuation.
 */
function rmt_warning_dedupe_hash(int $destId, string $category, string $title): string {
    $t = mb_strtolower($title);
    $t = preg_replace('/[^a-z0-9\s]/u', ' ', $t) ?? '';
    $words = array_filter(preg_split('/\s+/', trim($t)) ?: [], static fn($w) =>
        $w !== '' && mb_strlen($w) > 2 &&
        !in_array($w, ['the','and','for','was','with','you','your','are','not','but','from','they','this','that','have','had','out','get','got'], true));
    sort($words);
    return hash('sha256', $destId . '|' . $category . '|' . implode(' ', $words));
}

/** Has an identical-looking warning already been filed by this user? */
function rmt_warning_duplicate_id(int $userId, string $hash, int $excludeId = 0): ?int {
    $row = q_one('SELECT id FROM warnings WHERE user_id = ? AND dedupe_hash = ? AND id <> ? LIMIT 1',
                 [$userId, $hash, $excludeId]);
    return $row ? (int) $row['id'] : null;
}

/* ------------------------------------------------------------------ rows */

function rmt_warning_slug(array $w): string {
    $slug = slugify((string) ($w['title'] ?? ''));
    if ($slug === 'item' || $slug === '') $slug = 'warning';
    return mb_substr($slug, 0, 70);
}

function rmt_warning_path(array $w): string {
    return '/w/' . (int) $w['id'] . '/' . (($w['slug'] ?? '') ?: rmt_warning_slug($w));
}

function rmt_warning_get(int $id): ?array {
    return q_one('SELECT w.*, d.name dest_name, d.slug dest_slug, d.country dest_country,
                         u.username, u.role author_role, u.status user_status
                  FROM warnings w
                  JOIN destinations d ON d.id = w.destination_id
                  JOIN users u ON u.id = w.user_id
                  WHERE w.id = ?', [$id]);
}

/**
 * Who can see a warning?
 * Approved = everyone. Anything else = its author and moderators, so a rejected or pending
 * report never silently vanishes from the person who wrote it.
 */
function rmt_warning_can_view(array $w, ?array $user): bool {
    if ($w['status'] === 'approved') return true;
    if (!$user) return false;
    if ((int) $w['user_id'] === (int) $user['id']) return true;
    return in_array($user['role'], ['admin', 'mod'], true);
}

/** Authors edit their own; moderators change state but never rewrite someone's words. */
function rmt_warning_can_edit(array $w, ?array $user): bool {
    return $user !== null && (int) $w['user_id'] === (int) $user['id'];
}

function rmt_is_moderator(?array $user): bool {
    return $user !== null && in_array($user['role'] ?? '', ['admin', 'mod'], true);
}

/** Untouched for over a year — prices, rules and closures have probably moved on. */
function rmt_warning_is_stale(array $w): bool {
    $ts = strtotime((string) (($w['last_reviewed_at'] ?? '') ?: ($w['updated_at'] ?? '') ?: ($w['created_at'] ?? '')));
    return $ts !== false && $ts < strtotime('-365 days');
}

/** Human date for "experienced in", tolerant of both YYYY-MM and YYYY-MM-DD. */
function rmt_experienced_label(?string $d): string {
    $d = (string) $d;
    if ($d === '') return '';
    $ts = strtotime(preg_match('/^\d{4}-\d{2}$/', $d) ? $d . '-01' : $d);
    if ($ts === false) return '';
    return preg_match('/^\d{4}-\d{2}$/', $d) ? date('F Y', $ts) : date('M j, Y', $ts);
}

/* --------------------------------------------------------------- querying */

/**
 * One query builder behind every warning list on the site — destination pages, /warnings,
 * category pages, search, the moderation queue, a user's own submissions.
 *
 * Filters (all optional): destination_id, category, severity_min, verification, status,
 * traveler_type, month, provider_name, user_id, since, q.
 * Sorts: recent | helpful | severity | experienced | oldest.
 *
 * @return array{rows:array,total:int}
 */
function rmt_warning_query(array $f = [], int $limit = 20, int $offset = 0): array {
    $where = [];
    $args  = [];

    // Public callers pass no status and get only approved rows. The moderation queue passes one
    // explicitly. There is no code path that shows unapproved content by omission.
    $status = $f['status'] ?? 'approved';
    if ($status === 'any') {
        // no clause
    } elseif (is_array($status)) {
        $where[] = 'w.status IN (' . implode(',', array_fill(0, count($status), '?')) . ')';
        foreach ($status as $s) $args[] = $s;
    } else {
        $where[] = 'w.status = ?'; $args[] = $status;
    }

    if (!empty($f['destination_id'])) { $where[] = 'w.destination_id = ?'; $args[] = (int) $f['destination_id']; }
    if (!empty($f['category']))       { $where[] = 'w.category = ?';       $args[] = (string) $f['category']; }
    if (!empty($f['severity_min']))   { $where[] = 'w.severity >= ?';      $args[] = (int) $f['severity_min']; }
    if (!empty($f['verification']))   { $where[] = 'w.verification = ?';   $args[] = (string) $f['verification']; }
    if (!empty($f['traveler_type']))  { $where[] = 'w.traveler_type = ?';  $args[] = (string) $f['traveler_type']; }
    if (!empty($f['month']))          { $where[] = 'w.season_month = ?';   $args[] = (int) $f['month']; }
    if (!empty($f['user_id']))        { $where[] = 'w.user_id = ?';        $args[] = (int) $f['user_id']; }
    if (!empty($f['since']))          { $where[] = 'w.created_at >= ?';    $args[] = (string) $f['since']; }
    if (!empty($f['featured']))       { $where[] = 'w.featured = 1'; }
    if (!empty($f['provider_name'])) {
        $where[] = 'LOWER(w.provider_name) LIKE ?';
        $args[] = '%' . mb_strtolower((string) $f['provider_name']) . '%';
    }
    // LOWER() on both sides: LIKE is case-insensitive on SQLite but case-SENSITIVE on Postgres.
    if (!empty($f['q'])) {
        $needle = '%' . mb_strtolower((string) $f['q']) . '%';
        $where[] = '(LOWER(w.title) LIKE ? OR LOWER(w.body) LIKE ? OR LOWER(COALESCE(w.provider_name,\'\')) LIKE ?'
                 . ' OR LOWER(COALESCE(w.location_detail,\'\')) LIKE ?)';
        array_push($args, $needle, $needle, $needle, $needle);
    }

    $sql = 'FROM warnings w JOIN destinations d ON d.id = w.destination_id JOIN users u ON u.id = w.user_id';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);

    $total = (int) (q_one("SELECT COUNT(*) c $sql", $args)['c'] ?? 0);

    $order = match ((string) ($f['sort'] ?? 'recent')) {
        'helpful'     => 'w.helpful_count DESC, w.severity DESC, w.id DESC',
        'severity'    => 'w.severity DESC, w.helpful_count DESC, w.id DESC',
        'experienced' => 'w.date_experienced DESC, w.id DESC',
        'oldest'      => 'w.id ASC',
        'verified'    => "(w.verification = 'verified') DESC, w.helpful_count DESC, w.id DESC",
        default       => 'w.created_at DESC, w.id DESC',
    };

    $limit  = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $rows = q_all("SELECT w.*, d.name dest_name, d.slug dest_slug, d.country dest_country,
                          u.username, u.role author_role $sql ORDER BY $order LIMIT $limit OFFSET $offset", $args);
    if (function_exists('authors_fill')) authors_fill($rows);
    return ['rows' => $rows, 'total' => $total];
}

/** Per-category counts for one destination, used by the risk-report nav and the destination card. */
function rmt_warning_category_counts(int $destId): array {
    $rows = q_all("SELECT category, COUNT(*) c, MAX(severity) max_sev FROM warnings
                   WHERE destination_id = ? AND status = 'approved' GROUP BY category", [$destId]);
    $out = [];
    foreach ($rows as $r) $out[(string) $r['category']] = ['c' => (int) $r['c'], 'max_sev' => (int) $r['max_sev']];
    return $out;
}

/** Approved-warning count per destination id, for list pages. @return array<int,int> */
function rmt_warning_counts_by_destination(array $destIds): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $destIds))));
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rows = q_all("SELECT destination_id, COUNT(*) c FROM warnings
                   WHERE status = 'approved' AND destination_id IN ($ph) GROUP BY destination_id", $ids);
    $out = [];
    foreach ($rows as $r) $out[(int) $r['destination_id']] = (int) $r['c'];
    return $out;
}

/** The two or three categories a destination is most warned about. */
function rmt_top_categories_by_destination(array $destIds, int $per = 3): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $destIds))));
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rows = q_all("SELECT destination_id, category, COUNT(*) c FROM warnings
                   WHERE status = 'approved' AND destination_id IN ($ph)
                   GROUP BY destination_id, category ORDER BY destination_id, c DESC", $ids);
    $out = [];
    foreach ($rows as $r) {
        $d = (int) $r['destination_id'];
        $out[$d] ??= [];
        if (count($out[$d]) < $per) $out[$d][] = (string) $r['category'];
    }
    return $out;
}

/**
 * Trending warnings for the homepage.
 *
 * "Trending" here means recent and consequential, not merely popular: severity and helpful votes
 * both count, and anything older than the window drops out entirely so the homepage cannot keep
 * showing a strike that ended six months ago. Editor-featured rows are pinned above the rest.
 */
function rmt_trending_warnings(int $limit = 6, int $days = 120): array {
    $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    $rows = q_all("SELECT w.*, d.name dest_name, d.slug dest_slug, d.country dest_country,
                          u.username, u.role author_role
                   FROM warnings w
                   JOIN destinations d ON d.id = w.destination_id
                   JOIN users u ON u.id = w.user_id
                   WHERE w.status = 'approved' AND (w.featured = 1 OR w.created_at >= ?)
                   ORDER BY w.featured DESC,
                            (w.severity * 3 + w.helpful_count) DESC,
                            w.created_at DESC, w.id DESC
                   LIMIT " . max(1, min(24, $limit)), [$since]);
    if (function_exists('authors_fill')) authors_fill($rows);
    return $rows;
}

/* ------------------------------------------------------------------ votes */

/**
 * Recompute the denormalised vote counters from the votes table.
 * Always derived, never incremented in place — a double-submitted form can then never inflate a
 * count, because the number is a function of the rows that exist.
 */
function rmt_warning_recount_votes(int $warningId): void {
    $row = q_one("SELECT
                    SUM(CASE WHEN vote = 'helpful' THEN 1 ELSE 0 END) h,
                    SUM(CASE WHEN vote = 'not_helpful' THEN 1 ELSE 0 END) n
                  FROM warning_votes WHERE warning_id = ?", [$warningId]);
    q_exec('UPDATE warnings SET helpful_count = ?, not_helpful_count = ? WHERE id = ?',
           [(int) ($row['h'] ?? 0), (int) ($row['n'] ?? 0), $warningId]);
}

/** This user's vote on a warning, or null. */
function rmt_warning_my_vote(int $warningId, ?int $userId): ?string {
    if (!$userId) return null;
    $r = q_one('SELECT vote FROM warning_votes WHERE warning_id = ? AND user_id = ?', [$warningId, $userId]);
    return $r ? (string) $r['vote'] : null;
}

/* ------------------------------------------------------------- moderation */

/**
 * Change one moderated field and record who did it and why.
 *
 * Every state change goes through here so the audit log cannot be bypassed by a controller that
 * forgot to write it. Returns false (and logs nothing) when the value is unchanged, so the log
 * stays a record of decisions rather than of clicks.
 */
function rmt_warning_moderate(int $warningId, string $field, string $value, int $actorId, string $note = ''): bool {
    if (!in_array($field, ['status', 'verification', 'featured'], true)) return false;
    $w = q_one('SELECT * FROM warnings WHERE id = ?', [$warningId]);
    if (!$w) return false;
    $from = (string) ($w[$field] ?? '');
    if ($from === $value) return false;

    if ($field === 'status'       && !in_array($value, RMT_WARNING_STATUSES, true)) return false;
    if ($field === 'verification' && !in_array($value, RMT_WARNING_VERIFICATION, true)) return false;
    if ($field === 'featured'     && !in_array($value, ['0', '1'], true)) return false;

    $now = date('Y-m-d H:i:s');
    q_exec("UPDATE warnings SET {$field} = ?, moderated_by = ?, moderated_at = ?, moderation_note = ?,
                   last_reviewed_at = ? WHERE id = ?",
           [$value, $actorId, $now, ($note !== '' ? $note : $w['moderation_note']), $now, $warningId]);
    q_exec('INSERT INTO warning_moderation_log (warning_id, actor_id, field, from_value, to_value, note, created_at)
           VALUES (?,?,?,?,?,?,?)', [$warningId, $actorId, $field, $from, $value, $note ?: null, $now]);
    return true;
}

function rmt_warning_moderation_log(int $warningId): array {
    return q_all('SELECT l.*, u.username actor_username FROM warning_moderation_log l
                  LEFT JOIN users u ON u.id = l.actor_id
                  WHERE l.warning_id = ? ORDER BY l.id DESC', [$warningId]);
}

/** Approved right-of-reply responses shown under a warning. */
function rmt_warning_responses(int $warningId, bool $includePending = false): array {
    $sql = 'SELECT * FROM warning_responses WHERE warning_id = ?';
    $args = [$warningId];
    if (!$includePending) { $sql .= " AND status = 'approved'"; }
    return q_all($sql . ' ORDER BY id', $args);
}

/**
 * Contributor history shown next to a byline: how many approved warnings this person has, and
 * how many people found them helpful. A reader deserves to know whether an account has a record
 * or turned up today.
 */
function rmt_contributor_stats(int $userId): array {
    $r = q_one("SELECT COUNT(*) c, COALESCE(SUM(helpful_count),0) helpful, MIN(created_at) first_at
                FROM warnings WHERE user_id = ? AND status = 'approved'", [$userId]);
    return [
        'approved' => (int) ($r['c'] ?? 0),
        'helpful'  => (int) ($r['helpful'] ?? 0),
        'since'    => $r['first_at'] ?? null,
    ];
}
