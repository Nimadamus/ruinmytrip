<?php
declare(strict_types=1);

/**
 * Travel-warning alerts. Intended to run on a schedule, not on every deploy.
 *
 * Two audiences, one set of rules:
 *   1. trip_watchlist / destination_follows — members, per-trip frequency and severity floor
 *   2. alert_subscriptions — email-only subscribers who completed double opt-in
 *
 * The whole design is "do not become spam", and the brakes are in data rather than in the
 * control flow below, so a cron misfire or a second concurrent run cannot double-send:
 *
 *   * alert_deliveries has a UNIQUE index on (recipient, warning_id, channel). The insert is
 *     attempted BEFORE the email is composed; if it fails, that warning was already sent to that
 *     address and is skipped. This is the real guard.
 *   * rmt_alert_window_open() refuses to build a batch for a recipient who heard from us more
 *     recently than their chosen frequency allows.
 *   * A recipient with nothing new gets nothing. There is no "here is your update: nothing
 *     happened" email anywhere in this file.
 *   * Only APPROVED warnings are ever mentioned, and only ones at or above the recipient's
 *     severity floor and inside their chosen categories.
 *
 * Usage:
 *   php scripts/send_alerts.php --dry-run    print exactly what would go to whom, send nothing
 *   php scripts/send_alerts.php              send for real
 *   php scripts/send_alerts.php --limit=50   cap the number of emails in one run
 */

define('RMT_NO_AUTOSEED', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$args   = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$limit  = 0;
foreach ($args as $a) { if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = (int) $m[1]; }

function out(string $s): void { echo $s . PHP_EOL; }

if (!$dryRun && !rmt_mail_enabled()) {
    fwrite(STDERR, "RESEND_API_KEY is not set — refusing to run for real. Use --dry-run.\n");
    exit(1);
}

$now  = date('Y-m-d H:i:s');
$sent = 0; $skippedQuiet = 0; $skippedEmpty = 0; $skippedDupe = 0;

/**
 * Turn warning rows into the shape rmt_mail_warning_alert() renders.
 * Kept here rather than in the mailer so the mailer stays a template and nothing else.
 */
function alert_items(array $rows): array {
    $items = [];
    foreach ($rows as $w) {
        $items[] = [
            'dest'     => (string) $w['dest_name'],
            'title'    => (string) $w['title'],
            'severity' => rmt_severity_label((int) $w['severity']),
            'category' => rmt_warning_category_label((string) $w['category']),
            'url'      => url(ltrim(rmt_warning_path($w), '/')),
            'when'     => rmt_experienced_label($w['date_experienced'] ?? null),
        ];
    }
    return $items;
}

/* ------------------------------------------------------------------ *
 * 1. Members with saved trips and followed destinations.
 *
 * A member's trips and follows are merged into ONE email per person per run. Someone watching
 * four destinations should get one message, not four.
 * ------------------------------------------------------------------ */
out('-- members --');

$members = q_all("SELECT DISTINCT u.id, u.username, u.email, u.email_verified_at
                  FROM users u
                  WHERE u.status = 'active' AND u.role <> 'editorial'
                    AND (EXISTS (SELECT 1 FROM trip_watchlist tw WHERE tw.user_id = u.id AND tw.alert_frequency <> 'none')
                      OR EXISTS (SELECT 1 FROM destination_follows df WHERE df.user_id = u.id AND df.alert_frequency <> 'none'))");

foreach ($members as $u) {
    if ($limit && $sent >= $limit) break;
    if (!email_is_verified($u)) continue;
    $email = (string) $u['email'];

    // Watchlist entries and follows share one row shape as far as rmt_new_warnings_for() cares.
    $watches = array_merge(
        q_all("SELECT tw.*, d.name dest_name FROM trip_watchlist tw JOIN destinations d ON d.id = tw.destination_id
               WHERE tw.user_id = ? AND tw.alert_frequency <> 'none'", [(int) $u['id']]),
        q_all("SELECT df.*, NULL AS id, d.name dest_name FROM destination_follows df JOIN destinations d ON d.id = df.destination_id
               WHERE df.user_id = ? AND df.alert_frequency <> 'none'", [(int) $u['id']])
    );
    if (!$watches) continue;

    // The loudest setting across this person's watches decides the window — someone with a trip
    // next week on 'immediate' should not be held back by an unrelated 'weekly' entry.
    $order = ['immediate' => 3, 'daily' => 2, 'weekly' => 1];
    $freq = 'weekly';
    foreach ($watches as $w) {
        if (($order[$w['alert_frequency']] ?? 0) > ($order[$freq] ?? 0)) $freq = (string) $w['alert_frequency'];
    }
    if (!rmt_alert_window_open($email, $freq)) { $skippedQuiet++; continue; }

    // Collect, de-duplicate by warning id (two watches can cover the same destination).
    $byId = [];
    foreach ($watches as $w) {
        foreach (rmt_new_warnings_for($w, 10) as $row) $byId[(int) $row['id']] = $row;
    }
    if (!$byId) { $skippedEmpty++; continue; }

    // Reserve every warning BEFORE composing. A row that fails the unique index was already sent.
    $fresh = [];
    foreach ($byId as $wid => $row) {
        if ($dryRun) { $fresh[] = $row; continue; }
        if (rmt_alert_log_delivery($email, $wid, 'email', null, null)) $fresh[] = $row;
        else $skippedDupe++;
    }
    if (!$fresh) { $skippedEmpty++; continue; }

    usort($fresh, static fn($a2, $b2) => (int) $b2['severity'] <=> (int) $a2['severity']);
    $items = alert_items($fresh);
    $names = array_values(array_unique(array_column($items, 'dest')));
    $hint  = count($names) === 1 ? $names[0] : '';

    out(sprintf('  %-32s %d warning(s) [%s]%s', $email, count($items), $freq, $dryRun ? ' (dry run)' : ''));
    foreach ($items as $i) out('      - ' . $i['severity'] . ' · ' . $i['dest'] . ' · ' . $i['title']);

    if (!$dryRun) {
        rmt_mail_warning_alert($email,
            'Hi @' . $u['username'] . ' — here is what has been reported for your trips since we last wrote.',
            $items, rmt_unsubscribe_url((int) $u['id']), $hint);
        q_exec('UPDATE trip_watchlist SET last_alerted_at = ? WHERE user_id = ?', [$now, (int) $u['id']]);
        q_exec('UPDATE destination_follows SET last_alerted_at = ? WHERE user_id = ?', [$now, (int) $u['id']]);
    }
    $sent++;
}

/* ------------------------------------------------------------------ *
 * 2. Email-only subscribers (double opt-in completed, not unsubscribed).
 * ------------------------------------------------------------------ */
out('-- email-only subscribers --');

$subs = q_all('SELECT s.*, d.name dest_name FROM alert_subscriptions s
               LEFT JOIN destinations d ON d.id = s.destination_id
               WHERE s.confirmed_at IS NOT NULL AND s.unsubscribed_at IS NULL');

foreach ($subs as $s) {
    if ($limit && $sent >= $limit) break;
    $email = (string) $s['email'];
    if (!rmt_alert_window_open($email, (string) $s['frequency'])) { $skippedQuiet++; continue; }

    // A subscription's "new since" is its own last send, falling back to when it was confirmed —
    // never "all time", which would mail a year of history to a new subscriber.
    $since = (string) ($s['last_sent_at'] ?: $s['confirmed_at']);
    $args2 = [$since, (int) $s['min_severity']];
    $sql = "SELECT w.*, d.name dest_name, d.slug dest_slug FROM warnings w
            JOIN destinations d ON d.id = w.destination_id
            WHERE w.status = 'approved' AND w.created_at > ? AND w.severity >= ?";
    if (!empty($s['destination_id'])) { $sql .= ' AND w.destination_id = ?'; $args2[] = (int) $s['destination_id']; }
    $cats = rmt_categories_decode($s['categories_json'] ?? null);
    if ($cats) {
        $sql .= ' AND w.category IN (' . implode(',', array_fill(0, count($cats), '?')) . ')';
        foreach ($cats as $c) $args2[] = $c;
    }
    $rows = q_all($sql . ' ORDER BY w.severity DESC, w.created_at DESC LIMIT 10', $args2);
    if (!$rows) { $skippedEmpty++; continue; }

    $fresh = [];
    foreach ($rows as $row) {
        if ($dryRun) { $fresh[] = $row; continue; }
        if (rmt_alert_log_delivery($email, (int) $row['id'], 'email', null, (int) $s['id'])) $fresh[] = $row;
        else $skippedDupe++;
    }
    if (!$fresh) { $skippedEmpty++; continue; }

    $items = alert_items($fresh);
    out(sprintf('  %-32s %d warning(s) [%s]%s', $email, count($items), $s['frequency'], $dryRun ? ' (dry run)' : ''));
    foreach ($items as $i) out('      - ' . $i['severity'] . ' · ' . $i['dest'] . ' · ' . $i['title']);

    if (!$dryRun) {
        rmt_mail_warning_alert($email,
            'New travel warnings for ' . ($s['dest_name'] ?: 'the destinations you follow') . '.',
            $items, rmt_alert_unsubscribe_url($s), (string) ($s['dest_name'] ?? ''));
        q_exec('UPDATE alert_subscriptions SET last_sent_at = ? WHERE id = ?', [$now, (int) $s['id']]);
    }
    $sent++;
}

out('');
out(sprintf('%s: %d email(s), %d too soon, %d nothing new, %d already delivered',
    $dryRun ? 'DRY RUN' : 'sent', $sent, $skippedQuiet, $skippedEmpty, $skippedDupe));
