<?php
declare(strict_types=1);

/**
 * The ten numbers Nima asked for (2026-10-02), one row per Pacific day, so "which actions produce
 * users" is read off a table instead of argued. Every value is a COUNT over event rows or product
 * rows; our own checks (utm_content=selfcheck) and house accounts (rmt_sc_real_user_sql) are out.
 * Google impressions and clicks are not here: they come from Search Console, joined in by
 * scripts/kpi_snapshot.py. Served alone on GET /cron/kpi so reading it never costs a full funnel.
 *
 *   visitors         distinct browsers that tapped, typed or scrolled (human_interaction)
 *   returning        of those, browsers first seen on an earlier Pacific day
 *   review_starts    distinct journeys that opened the review form, signed in or not
 *   review_held      reviews written signed out and carried to the account step
 *   reviews          reviews by real members, published now, by the day they were written
 *   accounts         real members created that day
 *   trips            trips by real members created that day
 *   buddy_searches   distinct journeys that used the buddy finder
 *   buddy_posts      buddy posts by real members
 *   messages         messages sent by real members
 *
 * One query per metric over the whole window, bucketed here: the first version ran one query per
 * metric per day and a 30 day read took the free instance past its time limit. Stored timestamps
 * are UTC (the app sets no timezone), so each row is moved to Pacific before it is bucketed.
 */

const RMT_KPI_TZ = 'America/Los_Angeles';
const RMT_KPI_METRICS = ['visitors', 'returning', 'review_starts', 'review_held', 'reviews', 'accounts', 'trips',
                         'buddy_searches', 'buddy_posts', 'messages'];

/** @return list<array{day:string, from:string, to:string}> oldest first, today last */
function rmt_kpi_days(int $days, ?string $now = null): array {
    $tz = new DateTimeZone(RMT_KPI_TZ);
    $utc = new DateTimeZone('UTC');
    $today = (new DateTimeImmutable($now ?? 'now', $utc))->setTimezone($tz)->setTime(0, 0);
    $out = [];
    for ($i = max(1, $days) - 1; $i >= 0; $i--) {
        $start = $today->modify("-{$i} day");
        $end = $start->modify('+1 day');
        $out[] = ['day' => $start->format('Y-m-d'),
                  'from' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
                  'to' => $end->setTimezone($utc)->format('Y-m-d H:i:s')];
    }
    return $out;
}

/** A stored UTC timestamp as its Pacific day. */
function rmt_kpi_day_of(string $utc): string {
    static $tz = null, $u = null;
    $tz ??= new DateTimeZone(RMT_KPI_TZ); $u ??= new DateTimeZone('UTC');
    return (new DateTimeImmutable(substr($utc, 0, 19), $u))->setTimezone($tz)->format('Y-m-d');
}

/** @return list<array<string,mixed>> */
function rmt_kpi_rows(string $sql, array $args): array {
    try { return q_all($sql, $args); } catch (Throwable $e) { return []; }
}

/** @return array{tz:string, metrics:list<string>, days:list<array<string,int|string>>, totals:array<string,int>} */
function rmt_kpi_daily(int $days = 14): array {
    $days = min(max($days, 1), 60);
    $span = rmt_kpi_days($days);
    $from = $span[0]['from']; $to = $span[count($span) - 1]['to'];
    $real = rmt_sc_real_user_sql('u');
    $notSelf = "COALESCE(acq_content, '') <> 'selfcheck'";

    $grid = [];
    foreach ($span as $d) $grid[$d['day']] = array_fill_keys(RMT_KPI_METRICS, 0);
    $distinct = [];   // metric => day => set of ids
    $put = static function (string $metric, string $at, ?string $id = null) use (&$grid, &$distinct): void {
        $day = rmt_kpi_day_of($at);
        if (!isset($grid[$day])) return;
        if ($id === null) { $grid[$day][$metric]++; return; }
        if ($id === '' || isset($distinct[$metric][$day][$id])) return;
        $distinct[$metric][$day][$id] = true;
        $grid[$day][$metric]++;
    };

    foreach (['review_form_start' => 'review_starts', 'review_held_for_join' => 'review_held',
              'buddy_search' => 'buddy_searches'] as $event => $metric) {
        foreach (rmt_kpi_rows("SELECT created_at, journey FROM contribution_events
                                WHERE event = ? AND created_at >= ? AND created_at < ? AND $notSelf",
                              [$event, $from, $to]) as $r) {
            $put($metric, (string) $r['created_at'], (string) ($r['journey'] ?? ''));
        }
    }

    // Visitors, and which of them were first seen on an earlier Pacific day.
    $seen = rmt_kpi_rows("SELECT created_at, visitor FROM contribution_events
                           WHERE event = 'human_interaction' AND created_at >= ? AND created_at < ?
                             AND visitor IS NOT NULL AND $notSelf", [$from, $to]);
    $first = [];
    foreach (rmt_kpi_rows("SELECT visitor, MIN(created_at) f FROM contribution_events
                            WHERE event = 'human_interaction' AND visitor IS NOT NULL AND created_at < ?
                            GROUP BY visitor", [$to]) as $r) {
        $first[(string) $r['visitor']] = rmt_kpi_day_of((string) $r['f']);
    }
    foreach ($seen as $r) {
        $v = (string) $r['visitor'];
        $put('visitors', (string) $r['created_at'], $v);
        if (isset($first[$v]) && $first[$v] < rmt_kpi_day_of((string) $r['created_at'])) {
            $put('returning', (string) $r['created_at'], $v);
        }
    }

    $product = [
        'reviews'     => "SELECT r.created_at FROM reviews r JOIN users u ON u.id = r.user_id
                           WHERE r.status = 'published' AND r.created_at >= ? AND r.created_at < ? AND $real",
        'accounts'    => "SELECT u.created_at FROM users u WHERE u.created_at >= ? AND u.created_at < ? AND $real",
        'trips'       => "SELECT t.created_at FROM trips t JOIN users u ON u.id = t.user_id
                           WHERE t.created_at >= ? AND t.created_at < ? AND $real",
        'buddy_posts' => "SELECT b.created_at FROM buddy_posts b JOIN users u ON u.id = b.user_id
                           WHERE b.created_at >= ? AND b.created_at < ? AND $real",
        'messages'    => "SELECT m.created_at FROM messages m JOIN users u ON u.id = m.sender_id
                           WHERE m.created_at >= ? AND m.created_at < ? AND $real",
    ];
    foreach ($product as $metric => $sql) {
        foreach (rmt_kpi_rows($sql, [$from, $to]) as $r) $put($metric, (string) $r['created_at']);
    }

    $rows = []; $totals = array_fill_keys(RMT_KPI_METRICS, 0);
    foreach ($grid as $day => $vals) {
        $rows[] = ['day' => $day] + $vals;
        foreach ($vals as $k => $n) $totals[$k] += $n;
    }
    return ['tz' => RMT_KPI_TZ, 'metrics' => RMT_KPI_METRICS, 'days' => $rows, 'totals' => $totals];
}

/** GET /cron/kpi?key=...&days=14  the daily rows alone, same key and same 404 as /cron/funnel. */
function cron_kpi(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = (string) input('key');
    if ($key === '' || $given === '' || !hash_equals($key, $given)) not_found();
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo json_encode(rmt_kpi_daily((int) (input('days') !== '' ? input('days') : 14)));
}
