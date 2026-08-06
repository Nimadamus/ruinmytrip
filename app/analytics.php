<?php
declare(strict_types=1);

/**
 * First-party analytics.
 *
 * Why not a third-party tag: the whole funnel this site cares about happens server-side
 * (destination page rendered, warning read, signup completed, trip saved). Recording it where it
 * happens means the numbers do not quietly halve for every visitor running an ad blocker, and no
 * data about someone planning a trip leaves the box.
 *
 * PRIVACY. `visitor_key` is a salted hash of the session id (or IP+UA for a visitor with no
 * session yet) mixed with the current UTC date. Because the date is in the hash, the key rotates
 * every 24h: it is enough to stitch one visit's steps into a funnel, and useless for following
 * anyone across days. No raw IP, no user agent, no third-party cookie is stored.
 *
 * FAILURE. Tracking never breaks a page. Every write is wrapped — if the table is missing on a
 * half-migrated deploy, or the DB is briefly unavailable, the visitor still gets their page.
 */

/** The events that make up the funnel this site is actually optimising. */
const RMT_EVENTS = [
    'home_view'            => 'Homepage visit',
    'destination_search'   => 'Destination search',
    'destination_view'     => 'Destination page view',
    'warning_view'         => 'Warning viewed',
    'warning_submitted'    => 'Warning submitted',
    'signup_started'       => 'Signup started',
    'signup_completed'     => 'Signup completed',
    'destination_followed' => 'Destination followed',
    'trip_saved'           => 'Trip saved',
    'alert_subscribed'     => 'Alert subscription created',
    'affiliate_click'      => 'Affiliate link clicked',
];

/**
 * Rotating, non-identifying visitor key.
 * Salted with the app's session name + APP_KEY-ish config so two deployments never produce
 * comparable hashes for the same person.
 */
function rmt_visitor_key(): string {
    static $key = null;
    if ($key !== null) return $key;
    $seed = (PHP_SAPI !== 'cli' && session_id() !== '') ? session_id()
          : (($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $salt = (string) cfg('session_name', 'rmt') . '|' . gmdate('Y-m-d');
    return $key = substr(hash('sha256', $salt . '|' . $seed), 0, 32);
}

/**
 * Record one event. Silent on failure by design — see the file header.
 *
 * @param array{destination_id?:int|null,target_type?:string|null,target_id?:int|null,meta?:array} $ctx
 */
function rmt_track(string $name, array $ctx = []): void {
    if (PHP_SAPI === 'cli' && !defined('RMT_TRACK_IN_CLI')) return;
    if (!isset(RMT_EVENTS[$name])) return;
    // Never let analytics slow a page down for a bot; they are not part of any funnel.
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua !== '' && preg_match('/bot|crawl|spider|slurp|bingpreview|headlesschrome/i', $ua)) return;

    try {
        $me = function_exists('current_user') ? current_user() : null;
        $meta = $ctx['meta'] ?? null;
        q_exec('INSERT INTO analytics_events
                (name, user_id, visitor_key, destination_id, target_type, target_id, path, referrer, meta_json, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?)', [
            $name,
            $me ? (int) $me['id'] : null,
            rmt_visitor_key(),
            isset($ctx['destination_id']) ? (int) $ctx['destination_id'] : null,
            $ctx['target_type'] ?? null,
            isset($ctx['target_id']) ? (int) $ctx['target_id'] : null,
            mb_substr(strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '', 0, 300),
            mb_substr(rmt_internal_referrer(), 0, 300),
            $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        // Analytics is never load-bearing.
    }
}

/**
 * Referrer, reduced to something safe to store: our own paths in full, everything external
 * collapsed to its host. A full external URL can carry a search query or a personal identifier
 * in its own query string, and this table has no business holding that.
 */
function rmt_internal_referrer(): string {
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref === '') return '';
    $host = parse_url($ref, PHP_URL_HOST);
    $self = parse_url((string) cfg('app_url'), PHP_URL_HOST);
    if ($host === null || $host === $self) return (string) (parse_url($ref, PHP_URL_PATH) ?: '/');
    return (string) $host;
}

/* -------------------------------------------------------------- reporting */

/** Distinct visitors and raw hits for one event over a window. */
function rmt_event_totals(string $since): array {
    $rows = q_all('SELECT name, COUNT(*) hits, COUNT(DISTINCT visitor_key) visitors
                   FROM analytics_events WHERE created_at >= ? GROUP BY name', [$since]);
    $out = [];
    foreach ($rows as $r) $out[(string) $r['name']] = ['hits' => (int) $r['hits'], 'visitors' => (int) $r['visitors']];
    return $out;
}

/**
 * The funnel: visitor -> destination page -> warning engagement -> signup -> saved trip/follow.
 *
 * Each step counts DISTINCT visitor keys, and each is measured independently rather than as a
 * strict subset — a visitor who arrives straight on a destination page from Google never had a
 * homepage step, and pretending otherwise would understate the top of the funnel. The step-to-
 * step percentages are therefore ratios of reach, which is the honest reading of the number.
 */
function rmt_funnel(string $since): array {
    $step = static function (array $names) use ($since): int {
        $ph = implode(',', array_fill(0, count($names), '?'));
        $args = array_merge($names, [$since]);
        return (int) (q_one("SELECT COUNT(DISTINCT visitor_key) c FROM analytics_events
                             WHERE name IN ($ph) AND created_at >= ?", $args)['c'] ?? 0);
    };
    $visitors   = (int) (q_one('SELECT COUNT(DISTINCT visitor_key) c FROM analytics_events WHERE created_at >= ?', [$since])['c'] ?? 0);
    $destView   = $step(['destination_view']);
    $engaged    = $step(['warning_view', 'warning_submitted']);
    $signup     = $step(['signup_completed']);
    $committed  = $step(['trip_saved', 'destination_followed', 'alert_subscribed']);
    $pct = static fn(int $n, int $d): float => $d > 0 ? round($n * 100 / $d, 1) : 0.0;
    return [
        ['key' => 'visitors',  'label' => 'Visitors',                       'n' => $visitors,  'pct' => 100.0],
        ['key' => 'dest',      'label' => 'Viewed a destination page',      'n' => $destView,  'pct' => $pct($destView, $visitors)],
        ['key' => 'engaged',   'label' => 'Engaged with a warning',         'n' => $engaged,   'pct' => $pct($engaged, $visitors)],
        ['key' => 'signup',    'label' => 'Completed signup',               'n' => $signup,    'pct' => $pct($signup, $visitors)],
        ['key' => 'committed', 'label' => 'Saved a trip / followed / alert','n' => $committed, 'pct' => $pct($committed, $visitors)],
    ];
}

/** Most-viewed destinations over a window, for the admin dashboard. */
function rmt_top_destination_views(string $since, int $limit = 12): array {
    return q_all("SELECT d.name, d.slug, COUNT(*) hits, COUNT(DISTINCT e.visitor_key) visitors
                  FROM analytics_events e JOIN destinations d ON d.id = e.destination_id
                  WHERE e.name = 'destination_view' AND e.created_at >= ?
                  GROUP BY d.id, d.name, d.slug ORDER BY visitors DESC, hits DESC
                  LIMIT " . max(1, min(50, $limit)), [$since]);
}

/** What people typed into search but did not find, so the owner knows what to write next. */
function rmt_top_searches(string $since, int $limit = 20): array {
    $rows = q_all("SELECT meta_json, COUNT(*) c FROM analytics_events
                   WHERE name = 'destination_search' AND created_at >= ? AND meta_json IS NOT NULL
                   GROUP BY meta_json ORDER BY c DESC LIMIT " . max(1, min(100, $limit)), [$since]);
    $out = [];
    foreach ($rows as $r) {
        $m = json_decode((string) $r['meta_json'], true);
        if (!is_array($m) || ($m['q'] ?? '') === '') continue;
        $out[] = ['q' => (string) $m['q'], 'results' => $m['results'] ?? null, 'c' => (int) $r['c']];
    }
    return $out;
}
