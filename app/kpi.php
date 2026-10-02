<?php
declare(strict_types=1);

/**
 * The ten numbers Nima asked for (2026-10-02), one row per Pacific day, so "which actions produce
 * users" is read off a table instead of argued. Every value is a COUNT over event rows or product
 * rows; our own checks (utm_content=selfcheck) and house accounts (rmt_sc_real_user_sql) are out.
 * Google impressions and clicks are not here: they come from Search Console, joined in by
 * scripts/kpi_snapshot.py.
 *
 *   visitors         distinct browsers that tapped, typed or scrolled (human_interaction)
 *   returning        of those, browsers that had also been here on an earlier Pacific day
 *   review_starts    distinct journeys that opened the review form, signed in or not
 *   review_held      reviews written signed out and carried to the account step
 *   reviews          reviews by real members, published now, by the day they were written
 *   accounts         real members created that day
 *   trips            trips by real members created that day
 *   buddy_searches   distinct journeys that used the buddy finder
 *   buddy_posts      buddy posts by real members
 *   messages         messages sent by real members
 *
 * Stored timestamps are UTC (the app sets no timezone), so each Pacific day is turned into its
 * UTC bounds rather than truncating the stored text.
 */

const RMT_KPI_TZ = 'America/Los_Angeles';

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

function rmt_kpi_count(string $sql, array $args): int {
    try { return (int) (q_one($sql, $args)['c'] ?? 0); } catch (Throwable $e) { return 0; }
}

/** @return array{tz:string, metrics:list<string>, days:list<array<string,int|string>>, totals:array<string,int>} */
function rmt_kpi_daily(int $days = 14): array {
    $days = min(max($days, 1), 60);
    $real = rmt_sc_real_user_sql('u');
    $notSelf = "COALESCE(acq_content, '') <> 'selfcheck'";
    $ev = static fn(string $event, string $what = 'journey'): string =>
        "SELECT COUNT(DISTINCT $what) c FROM contribution_events
          WHERE event = '$event' AND created_at >= ? AND created_at < ? AND $notSelf";
    $q = [
        'visitors'       => $ev('human_interaction', 'visitor'),
        'returning'      => "SELECT COUNT(DISTINCT e.visitor) c FROM contribution_events e
                              WHERE e.event = 'human_interaction' AND e.created_at >= ? AND e.created_at < ?
                                AND COALESCE(e.acq_content, '') <> 'selfcheck' AND e.visitor IS NOT NULL
                                AND EXISTS (SELECT 1 FROM contribution_events p WHERE p.visitor = e.visitor
                                             AND p.event = 'human_interaction' AND p.created_at < ?)",
        'review_starts'  => $ev('review_form_start'),
        'review_held'    => $ev('review_held_for_join'),
        'reviews'        => "SELECT COUNT(*) c FROM reviews r JOIN users u ON u.id = r.user_id
                              WHERE r.status = 'published' AND r.created_at >= ? AND r.created_at < ? AND $real",
        'accounts'       => "SELECT COUNT(*) c FROM users u WHERE u.created_at >= ? AND u.created_at < ? AND $real",
        'trips'          => "SELECT COUNT(*) c FROM trips t JOIN users u ON u.id = t.user_id
                              WHERE t.created_at >= ? AND t.created_at < ? AND $real",
        'buddy_searches' => $ev('buddy_search'),
        'buddy_posts'    => "SELECT COUNT(*) c FROM buddy_posts b JOIN users u ON u.id = b.user_id
                              WHERE b.created_at >= ? AND b.created_at < ? AND $real",
        'messages'       => "SELECT COUNT(*) c FROM messages m JOIN users u ON u.id = m.sender_id
                              WHERE m.created_at >= ? AND m.created_at < ? AND $real",
    ];
    $rows = [];
    $totals = array_fill_keys(array_keys($q), 0);
    foreach (rmt_kpi_days($days) as $d) {
        $row = ['day' => $d['day']];
        foreach ($q as $k => $sql) {
            $args = $k === 'returning' ? [$d['from'], $d['to'], $d['from']] : [$d['from'], $d['to']];
            $row[$k] = rmt_kpi_count($sql, $args);
            $totals[$k] += $row[$k];
        }
        $rows[] = $row;
    }
    return ['tz' => RMT_KPI_TZ, 'metrics' => array_keys($q), 'days' => $rows, 'totals' => $totals];
}
