<?php
declare(strict_types=1);

/**
 * Trip watchlist and warning alerts.
 *
 * This is the payoff for holding an account: tell the site where and when you are going, and it
 * tells you what changed before you leave. The design constraint that shapes everything here is
 * NOT SENDING TOO MUCH — an alert product that cries wolf gets filtered, and then the one alert
 * that mattered never gets read.
 *
 * Four separate brakes, all enforced in data rather than in the sender's control flow:
 *   1. Per-trip frequency (immediate / daily / weekly / off). Default is weekly.
 *   2. Per-trip minimum severity, so "only tell me about things that would change my plans"
 *      is a real setting.
 *   3. alert_deliveries has a unique index on (recipient, warning_id, channel): the same warning
 *      physically cannot be mailed to the same person twice, however many times a sender runs.
 *   4. A frequency window check before any batch is built, so a cron misfire cannot double-send.
 */

const RMT_ALERT_FREQUENCIES = [
    'immediate' => 'As soon as something serious is posted',
    'daily'     => 'A daily summary, at most',
    'weekly'    => 'A weekly summary (recommended)',
    'none'      => 'No emails — I will check the site',
];

/** Hours that must pass before another alert email may be built for a given frequency. */
function rmt_alert_window_hours(string $freq): ?int {
    return ['immediate' => 1, 'daily' => 20, 'weekly' => 144][$freq] ?? null; // null = never send
}

/* ------------------------------------------------------------- watchlist */

/** A user's trips, soonest first, with the destination joined in. */
function rmt_watchlist(int $userId, bool $upcomingOnly = false): array {
    $sql = 'SELECT tw.*, d.name dest_name, d.slug dest_slug, d.country dest_country,
                   d.hero_url, d.risk_level
            FROM trip_watchlist tw JOIN destinations d ON d.id = tw.destination_id
            WHERE tw.user_id = ?';
    $args = [$userId];
    if ($upcomingOnly) {
        // A trip with no end date is treated as still relevant; only an explicitly past one drops out.
        $sql .= ' AND (tw.date_to IS NULL OR tw.date_to = \'\' OR tw.date_to >= ?)';
        $args[] = date('Y-m-d');
    }
    // NULL dates sort last so a dated, imminent trip is never pushed below an undated idea.
    return q_all($sql . ' ORDER BY (tw.date_from IS NULL), tw.date_from, tw.id DESC', $args);
}

function rmt_watchlist_get(int $id, int $userId): ?array {
    return q_one('SELECT tw.*, d.name dest_name, d.slug dest_slug FROM trip_watchlist tw
                  JOIN destinations d ON d.id = tw.destination_id
                  WHERE tw.id = ? AND tw.user_id = ?', [$id, $userId]);
}

/** Is this destination already on the user's watchlist? */
function rmt_watchlist_has(int $userId, int $destId): bool {
    return (bool) q_one('SELECT 1 FROM trip_watchlist WHERE user_id = ? AND destination_id = ?', [$userId, $destId]);
}

/**
 * Validate trip dates. Both optional (a watchlist entry with no dates is a perfectly good
 * "thinking about it"), but if both are given the end may not precede the start.
 * @return array{ok:bool,errors:string[],from:?string,to:?string}
 */
function rmt_watchlist_validate_dates(string $from, string $to): array {
    $errors = [];
    $from = trim($from); $to = trim($to);
    foreach ([['start', $from], ['end', $to]] as [$which, $v]) {
        if ($v !== '' && strtotime($v) === false) $errors[] = "That {$which} date is not a valid date.";
    }
    if ($from !== '' && $to !== '' && strtotime($to) !== false && strtotime($from) !== false
        && strtotime($to) < strtotime($from)) {
        $errors[] = 'The return date is before the departure date.';
    }
    if ($from !== '' && strtotime($from) !== false && strtotime($from) > strtotime('+5 years')) {
        $errors[] = 'That departure date is a very long way off — check the year.';
    }
    return ['ok' => !$errors, 'errors' => $errors, 'from' => $from ?: null, 'to' => $to ?: null];
}

/** Categories stored as a JSON array; empty/absent means "all categories". */
function rmt_categories_decode(?string $json): array {
    $v = $json ? json_decode($json, true) : null;
    if (!is_array($v)) return [];
    return array_values(array_intersect($v, array_keys(RMT_WARNING_CATEGORIES)));
}

function rmt_categories_encode(array $cats): ?string {
    $clean = array_values(array_intersect($cats, array_keys(RMT_WARNING_CATEGORIES)));
    return $clean ? json_encode($clean) : null;
}

/* ------------------------------------------------------- what's new for me */

/**
 * Approved warnings for a destination that a watcher has not seen yet.
 *
 * "Not seen" is last_seen_at, which the dashboard updates on view — so the count on the dashboard
 * and the contents of the alert email are the same set, computed the same way, rather than two
 * definitions of "new" that drift apart.
 */
function rmt_new_warnings_for(array $watch, int $limit = 10): array {
    $since = (string) ($watch['last_seen_at'] ?: $watch['created_at']);
    $args  = [(int) $watch['destination_id'], $since, (int) ($watch['min_severity'] ?: 1)];
    $sql   = "SELECT w.*, d.name dest_name, d.slug dest_slug FROM warnings w
              JOIN destinations d ON d.id = w.destination_id
              WHERE w.destination_id = ? AND w.status = 'approved' AND w.created_at > ? AND w.severity >= ?";
    $cats = rmt_categories_decode($watch['categories_json'] ?? null);
    if ($cats) {
        $sql .= ' AND w.category IN (' . implode(',', array_fill(0, count($cats), '?')) . ')';
        foreach ($cats as $c) $args[] = $c;
    }
    return q_all($sql . ' ORDER BY w.severity DESC, w.created_at DESC LIMIT ' . max(1, min(50, $limit)), $args);
}

/** Just the count, for dashboard badges. */
function rmt_new_warning_count(array $watch): int {
    $rows = rmt_new_warnings_for($watch, 50);
    return count($rows);
}

/**
 * Preparation checklist for an upcoming trip.
 *
 * Deliberately derived from what this destination is actually warned about rather than a generic
 * packing list — a checklist that says the same thing for Reykjavik and Cancun is noise.
 */
function rmt_trip_prep_actions(array $watch): array {
    $destId = (int) $watch['destination_id'];
    $counts = rmt_warning_category_counts($destId);
    $days   = null;
    if (!empty($watch['date_from']) && ($ts = strtotime((string) $watch['date_from'])) !== false) {
        $days = (int) floor(($ts - time()) / 86400);
    }
    $out = [];
    if (isset($counts['entry-requirements'])) {
        $out[] = ['label' => 'Check entry requirements and passport validity',
                  'why' => $counts['entry-requirements']['c'] . ' traveler ' . ($counts['entry-requirements']['c'] === 1 ? 'report' : 'reports') . ' about documents here',
                  'urgent' => $days !== null && $days <= 60];
    }
    if (isset($counts['hidden-costs'])) {
        $out[] = ['label' => 'Budget for the fees that are not in the headline price',
                  'why' => 'Reported hidden costs at this destination', 'urgent' => false];
    }
    if (isset($counts['neighborhoods'])) {
        $out[] = ['label' => 'Check the neighbourhood before you confirm accommodation',
                  'why' => 'Travelers have flagged area choices here', 'urgent' => $days !== null && $days <= 30];
    }
    if (isset($counts['transportation'])) {
        $out[] = ['label' => 'Plan your airport transfer in advance',
                  'why' => 'Transport problems are among the most reported issues here', 'urgent' => false];
    }
    if (isset($counts['weather']) && !empty($watch['date_from'])) {
        $out[] = ['label' => 'Check seasonal weather for your dates',
                  'why' => 'Weather warnings exist for this destination', 'urgent' => false];
    }
    if (isset($counts['closures'])) {
        $out[] = ['label' => 'Confirm opening hours and closures before booking timed entry',
                  'why' => 'Closures and construction reported here', 'urgent' => $days !== null && $days <= 14];
    }
    return $out;
}

/* ------------------------------------------------- email-only subscriptions */

/** Stateless per-subscription token; also the unsubscribe key, so one link always works. */
function rmt_alert_token(string $email, ?int $destId): string {
    return hash_hmac('sha256', mb_strtolower($email) . '|' . (int) $destId, (string) cfg('security_salt'));
}

/**
 * Create or refresh an email alert subscription. Idempotent on (email, destination) so a repeat
 * signup never creates a second row or a second confirmation email storm.
 *
 * @return array{status:'created'|'exists'|'reconfirm', row:array}
 */
function rmt_alert_subscribe(string $email, ?int $destId, array $opts = []): array {
    $email = mb_strtolower(trim($email));
    $token = rmt_alert_token($email, $destId);
    $now   = date('Y-m-d H:i:s');
    $cats  = rmt_categories_encode($opts['categories'] ?? []);
    $freq  = in_array(($opts['frequency'] ?? ''), array_keys(RMT_ALERT_FREQUENCIES), true) ? $opts['frequency'] : 'weekly';
    $sev   = max(1, min(4, (int) ($opts['min_severity'] ?? 2)));

    $existing = q_one('SELECT * FROM alert_subscriptions WHERE email = ? AND destination_id ' .
                      ($destId ? '= ?' : 'IS NULL'), $destId ? [$email, $destId] : [$email]);
    if ($existing) {
        q_exec('UPDATE alert_subscriptions SET categories_json = ?, min_severity = ?, frequency = ?,
                       unsubscribed_at = NULL, token = ? WHERE id = ?',
               [$cats, $sev, $freq, $token, (int) $existing['id']]);
        $existing['token'] = $token;
        return ['status' => $existing['confirmed_at'] ? 'exists' : 'reconfirm', 'row' => $existing];
    }
    $id = (int) q_run('INSERT INTO alert_subscriptions
            (email, user_id, destination_id, categories_json, min_severity, frequency, token, source, created_at)
            VALUES (?,?,?,?,?,?,?,?,?)',
        [$email, $opts['user_id'] ?? null, $destId, $cats, $sev, $freq, $token, $opts['source'] ?? null, $now]);
    return ['status' => 'created', 'row' => q_one('SELECT * FROM alert_subscriptions WHERE id = ?', [$id]) ?? []];
}

function rmt_alert_confirm_url(array $sub): string {
    return url('alerts/confirm?e=' . rawurlencode((string) $sub['email']) . '&t=' . $sub['token']);
}
function rmt_alert_unsubscribe_url(array $sub): string {
    return url('alerts/unsubscribe?e=' . rawurlencode((string) $sub['email']) . '&t=' . $sub['token']);
}

/** Look up a subscription by the emailed token pair, constant-time compared. */
function rmt_alert_by_token(string $email, string $token): ?array {
    $email = mb_strtolower(trim($email));
    foreach (q_all('SELECT * FROM alert_subscriptions WHERE email = ?', [$email]) as $row) {
        if (hash_equals((string) $row['token'], $token)) return $row;
    }
    return null;
}

/**
 * Has enough time passed to send this recipient another alert?
 * Reads the delivery log rather than a column on the subscriber, so a shared address across a
 * watchlist entry and a standalone subscription still respects one combined rhythm.
 */
function rmt_alert_window_open(string $recipient, string $freq): bool {
    $hours = rmt_alert_window_hours($freq);
    if ($hours === null) return false;
    $last = q_one('SELECT MAX(created_at) m FROM alert_deliveries WHERE recipient = ?', [$recipient]);
    $m = $last['m'] ?? null;
    return !$m || strtotime((string) $m) < strtotime("-{$hours} hours");
}

/**
 * Record that a warning was sent to a recipient. Returns false if the unique index rejected it,
 * which is the signal that it had already been sent — the caller uses that to skip.
 */
function rmt_alert_log_delivery(string $recipient, int $warningId, string $channel = 'email',
                                ?int $watchlistId = null, ?int $subscriptionId = null): bool {
    try {
        q_exec('INSERT INTO alert_deliveries (channel, recipient, warning_id, watchlist_id, subscription_id, created_at)
                VALUES (?,?,?,?,?,?)',
               [$channel, $recipient, $warningId, $watchlistId, $subscriptionId, date('Y-m-d H:i:s')]);
        return true;
    } catch (Throwable $e) {
        return false; // duplicate: already delivered
    }
}
