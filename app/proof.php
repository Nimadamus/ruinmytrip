<?php
declare(strict_types=1);

/**
 * Proof: the numbers that say whether real people are arriving, joining and contributing.
 *
 * Why this exists (audit 2026-10-11, docs/AUDIT_20261011.md section 6). The daily scoreboard
 * counted a "visitor" as any browser that sent human_interaction, and a "review start" as any
 * request for the review form. On 2026-10-08 that read 2,326 visitors and 2,659 review starts,
 * with zero reviews written. On 2026-10-09 between 00:15 and 00:17 PDT fifteen browsers opened
 * /review/new (a path robots.txt tells crawlers to leave alone), each one "interacting" four to
 * seven seconds later. Headless browsers scroll. A number that wrong in the flattering direction
 * is worse than none.
 *
 * So every browser in the window is put in exactly one of three buckets, with the reason kept:
 *
 *   VERIFIED HUMAN   it did something only a person does: a trusted tap or keypress from a browser
 *                    that does not announce automation, at least 1.5 s after the page loaded
 *                    (event human_verified, sent by contribute-track.js since 2026-10-11), or it
 *                    submitted a real form (an action event below), or, for history before
 *                    human_verified existed, it sent human_interaction and stayed 10 s or more.
 *                    AND none of the automation signals below.
 *   SUSPECTED AUTOMATION, with one reason, checked in this order:
 *       webdriver    the browser itself said it is automated (navigator.webdriver)
 *       form_first   its first recorded event was a form page (review form, join step) with no
 *                    landing before it: people arrive on pages, crawlers fetch form links
 *       burst        it started inside a burst: 5 or more new browsers whose first event was the
 *                    same path (or event) within the same 60 seconds
 *       no_cookie    two or more requests, the cookie bit recorded, and not one carried our cookie back
 *   UNVERIFIED       everything else: one page and nothing after it looks the same from a bored
 *                    reader and a polite crawler, and this does not pretend to tell them apart.
 *
 * Only browsers with a visitor id are counted (the id is the rmt_v cookie value, nothing about the
 * person). Our own checks (utm_content=selfcheck) are excluded. Product counts (accounts, reviews,
 * trips, buddy posts, messages) come from their own tables and exclude house accounts
 * (rmt_sc_real_user_sql). Every day is a Pacific day. Nothing here writes.
 */

/** Things a person submits. Each is recorded by the server on a POST, or by a trusted click. */
const RMT_PROOF_ACTION_EVENTS = [
    'review_submit_attempt', 'review_held_for_join', 'review_publish_success', 'join_submit', 'join_created',
    'join_confirmed', 'login_completed', 'trip_created', 'post_created', 'comment_created', 'reaction_created',
    'question_posted', 'message_sent', 'destination_follow_success', 'buddy_post_created', 'plan_submitted',
    'alert_submitted', 'map_saved', 'card_created', 'trip_connect_requested',
];

/** Server recorded GET events for form pages. A browser whose first event is one of these skipped every page. */
const RMT_PROOF_FORM_FIRST = ['review_form_start', 'review_signup_required', 'join_view', 'plan_signup_view', 'plan_view'];

const RMT_PROOF_BURST_SIZE = 5;
const RMT_PROOF_BURST_SECONDS = 60;
const RMT_PROOF_LEGACY_DWELL = 10;

/** The metrics in /cron/proof, in the order the dashboard shows them, with the definition of each. */
const RMT_PROOF_DEFINITIONS = [
    'verified_humans'   => 'Browsers seen that day that the window classifies as a verified human (see app/proof.php).',
    'suspected_bots'    => 'Browsers seen that day classified as suspected automation, any reason.',
    'unverified'        => 'Browsers seen that day with no evidence either way.',
    'returning_humans'  => 'Verified humans seen that day who were also seen on an earlier Pacific day in the window.',
    'search_humans'     => 'Verified humans whose channel was search that day.',
    'referral_humans'   => 'Verified humans whose channel was anything other than direct or search (social, outreach, invites, launch sites).',
    'review_opened'     => 'Verified humans who opened the review form.',
    'review_submitted'  => 'Verified humans who pressed Post or Publish on a review (review_submit_attempt).',
    'review_held'       => 'Guest reviews validated and held for the account step (review_held_for_join).',
    'reviews_published' => 'Reviews by real members published, by the day they were written (reviews table).',
    'signups'           => 'Real member accounts created that day (users table).',
    'confirmed'         => 'Of those accounts, how many have confirmed their email by now.',
    'trips'             => 'Trips created by real members.',
    'buddy_posts'       => 'Travel buddy posts by real members.',
    'messages'          => 'Messages sent by real members.',
    'alerts_confirmed'  => 'Match alerts whose email link was clicked that day.',
];

/**
 * Classify every browser seen in [$from, $to). Pure over rows so it can be tested without a database.
 *
 * @param list<array{visitor:string,event:string,path:?string,created_at:string,cookied:?int,acq_source:?string}> $rows
 *        ordered by created_at
 * @return array<string,array{class:string,reason:string,days:array<string,true>,first:string,source:string,events:array<string,int>}>
 */
function rmt_proof_classify(array $rows): array {
    $by = [];
    foreach ($rows as $r) {
        $v = (string) $r['visitor'];
        if ($v === '') continue;
        $b = &$by[$v];
        if (!isset($b)) {
            $b = ['first' => (string) $r['created_at'], 'last' => (string) $r['created_at'],
                  'first_event' => (string) $r['event'], 'first_key' => (string) ($r['path'] ?: $r['event']),
                  'events' => [], 'days' => [], 'n' => 0, 'cookie_known' => false, 'cookie_back' => false,
                  'source' => (string) ($r['acq_source'] ?? '')];
        }
        $b['last'] = (string) $r['created_at'];
        $b['events'][(string) $r['event']] = ($b['events'][(string) $r['event']] ?? 0) + 1;
        $b['days'][rmt_kpi_day_of((string) $r['created_at'])] = true;
        $b['n']++;
        if ($r['cookied'] !== null && $r['cookied'] !== '') {
            $b['cookie_known'] = true;
            if ((int) $r['cookied'] === 1) $b['cookie_back'] = true;
        }
        if ($b['source'] === '' && !empty($r['acq_source'])) $b['source'] = (string) $r['acq_source'];
        unset($b);
    }

    // Bursts: new browsers bucketed by their first path (or event) and the minute window they began in.
    $starts = [];
    foreach ($by as $v => $b) $starts[$b['first_key']][] = [strtotime($b['first']), $v];
    $inBurst = [];
    foreach ($starts as $list) {
        if (count($list) < RMT_PROOF_BURST_SIZE) continue;
        usort($list, static fn($a, $b) => $a[0] <=> $b[0]);
        $n = count($list); $lo = 0;
        for ($hi = 0; $hi < $n; $hi++) {
            while ($list[$hi][0] - $list[$lo][0] > RMT_PROOF_BURST_SECONDS) $lo++;
            if ($hi - $lo + 1 >= RMT_PROOF_BURST_SIZE) {
                for ($k = $lo; $k <= $hi; $k++) $inBurst[$list[$k][1]] = true;
            }
        }
    }

    $out = [];
    foreach ($by as $v => $b) {
        $ev = $b['events'];
        $acted = false;
        foreach (RMT_PROOF_ACTION_EVENTS as $a) { if (isset($ev[$a])) { $acted = true; break; } }
        $dwell = max(0, strtotime($b['last']) - strtotime($b['first']));
        $evidence = isset($ev['human_verified']) ? 'trusted_input'
            : ($acted ? 'submitted_a_form'
            : (isset($ev['human_interaction']) && $dwell >= RMT_PROOF_LEGACY_DWELL ? 'interacted_and_stayed' : ''));

        $suspect = '';
        if (isset($ev['automation_detected'])) $suspect = 'webdriver';
        elseif (in_array($b['first_event'], RMT_PROOF_FORM_FIRST, true)) $suspect = 'form_first';
        elseif (isset($inBurst[$v])) $suspect = 'burst';
        elseif ($b['cookie_known'] && $b['n'] >= 2 && !$b['cookie_back']) $suspect = 'no_cookie';

        $class = $suspect !== '' ? 'bot' : ($evidence !== '' ? 'human' : 'unverified');
        $out[$v] = ['class' => $class, 'reason' => $suspect !== '' ? $suspect : $evidence,
                    'days' => $b['days'], 'first' => $b['first'], 'source' => $b['source'], 'events' => $ev];
    }
    return $out;
}

/** @return array<string,mixed> the daily table, the totals, the funnel and the reasons, for N Pacific days */
function rmt_proof(int $days = 14, ?string $now = null): array {
    $days = min(max($days, 1), 31);
    $span = rmt_kpi_days($days, $now);
    $from = $span[0]['from']; $to = $span[count($span) - 1]['to'];
    $notSelf = "COALESCE(acq_content, '') <> 'selfcheck'";
    $rows = rmt_kpi_rows("SELECT visitor, event, path, created_at, cookied, acq_source FROM contribution_events
                           WHERE created_at >= ? AND created_at < ? AND visitor IS NOT NULL AND visitor <> '' AND $notSelf
                        ORDER BY created_at, id", [$from, $to]);
    $cls = rmt_proof_classify($rows);

    $grid = [];
    foreach ($span as $d) $grid[$d['day']] = array_fill_keys(array_keys(RMT_PROOF_DEFINITIONS), 0);

    $reasons = ['human' => [], 'bot' => []];
    $humanDays = [];
    foreach ($cls as $v => $c) {
        if ($c['class'] !== 'unverified') $reasons[$c['class']][$c['reason']] = ($reasons[$c['class']][$c['reason']] ?? 0) + 1;
        $seenBefore = false;
        foreach (array_keys($c['days']) as $day) {
            if (!isset($grid[$day])) continue;
            $grid[$day][$c['class'] === 'human' ? 'verified_humans' : ($c['class'] === 'bot' ? 'suspected_bots' : 'unverified')]++;
            if ($c['class'] === 'human') {
                if ($seenBefore) $grid[$day]['returning_humans']++;
                $seenBefore = true;
                $humanDays[$v] = true;
            }
        }
        if ($c['class'] === 'human') {
            $d0 = rmt_kpi_day_of($c['first']);
            if (isset($grid[$d0])) {
                if ($c['source'] === 'search') $grid[$d0]['search_humans']++;
                elseif ($c['source'] !== '' && $c['source'] !== 'direct') $grid[$d0]['referral_humans']++;
            }
        }
    }

    // Review steps, verified humans only, on the day the step happened, once per browser per day.
    $once = [];
    foreach ($rows as $r) {
        $c = $cls[(string) $r['visitor']] ?? null;
        if (!$c || $c['class'] !== 'human') continue;
        $day = rmt_kpi_day_of((string) $r['created_at']);
        if (!isset($grid[$day])) continue;
        $k = $r['visitor'] . '|' . $r['event'] . '|' . $day;
        if (isset($once[$k])) continue;
        $once[$k] = true;
        if ($r['event'] === 'review_form_start') $grid[$day]['review_opened']++;
        elseif ($r['event'] === 'review_submit_attempt') $grid[$day]['review_submitted']++;
    }
    // Distinct journeys per day, the same rule /cron/kpi uses, so the two can be reconciled.
    $heldSeen = [];
    foreach (rmt_kpi_rows("SELECT created_at, journey FROM contribution_events WHERE event = 'review_held_for_join'
                            AND created_at >= ? AND created_at < ? AND $notSelf", [$from, $to]) as $r) {
        $day = rmt_kpi_day_of((string) $r['created_at']);
        $j = (string) ($r['journey'] ?? '');
        if (!isset($grid[$day]) || $j === '' || isset($heldSeen[$day][$j])) continue;
        $heldSeen[$day][$j] = true;
        $grid[$day]['review_held']++;
    }

    $real = rmt_sc_real_user_sql('u');
    $product = [
        'reviews_published' => "SELECT r.created_at FROM reviews r JOIN users u ON u.id = r.user_id
                                 WHERE r.status = 'published' AND r.created_at >= ? AND r.created_at < ? AND $real",
        'signups'   => "SELECT u.created_at FROM users u WHERE u.created_at >= ? AND u.created_at < ? AND $real",
        'confirmed' => "SELECT u.created_at FROM users u WHERE u.created_at >= ? AND u.created_at < ? AND $real
                          AND u.email_verified_at IS NOT NULL",
        'trips'     => "SELECT t.created_at FROM trips t JOIN users u ON u.id = t.user_id
                         WHERE t.created_at >= ? AND t.created_at < ? AND $real",
        'buddy_posts' => "SELECT b.created_at FROM buddy_posts b JOIN users u ON u.id = b.user_id
                           WHERE b.created_at >= ? AND b.created_at < ? AND $real",
        'messages'  => "SELECT m.created_at FROM messages m JOIN users u ON u.id = m.sender_id
                         WHERE m.created_at >= ? AND m.created_at < ? AND $real",
        'alerts_confirmed' => "SELECT confirmed_at AS created_at FROM match_alerts
                                WHERE confirmed_at >= ? AND confirmed_at < ?",
    ];
    foreach ($product as $metric => $sql) {
        foreach (rmt_kpi_rows($sql, [$from, $to]) as $r) {
            $day = rmt_kpi_day_of((string) $r['created_at']);
            if (isset($grid[$day])) $grid[$day][$metric]++;
        }
    }

    $out = []; $tot = array_fill_keys(array_keys(RMT_PROOF_DEFINITIONS), 0);
    foreach ($grid as $day => $vals) { $out[] = ['day' => $day] + $vals; foreach ($vals as $k => $n) $tot[$k] += $n; }
    // Totals for browser buckets are distinct browsers over the window, not a sum of days.
    $tot['verified_humans'] = count(array_filter($cls, static fn($c) => $c['class'] === 'human'));
    $tot['suspected_bots']  = count(array_filter($cls, static fn($c) => $c['class'] === 'bot'));
    $tot['unverified']      = count(array_filter($cls, static fn($c) => $c['class'] === 'unverified'));
    $tot['returning_humans'] = count(array_filter($cls, static fn($c) => $c['class'] === 'human' && count($c['days']) >= 2));

    $rate = static fn(int $a, int $b): ?float => $b > 0 ? round(100 * $a / $b, 1) : null;
    $byChannel = [];
    foreach ($cls as $c) {
        if ($c['class'] !== 'human') continue;
        $ch = $c['source'] !== '' ? $c['source'] : 'unknown';
        $byChannel[$ch]['humans'] = ($byChannel[$ch]['humans'] ?? 0) + 1;
        $acted = isset($c['events']['join_created']);
        $byChannel[$ch]['joined'] = ($byChannel[$ch]['joined'] ?? 0) + ($acted ? 1 : 0);
        $byChannel[$ch]['reviewed'] = ($byChannel[$ch]['reviewed'] ?? 0) + (isset($c['events']['review_submit_attempt']) ? 1 : 0);
    }
    arsort($byChannel);

    return [
        'tz' => RMT_KPI_TZ, 'from_utc' => $from, 'to_utc' => $to, 'browsers_read' => count($cls), 'events_read' => count($rows),
        'definitions' => RMT_PROOF_DEFINITIONS,
        'classification' => [
            'human_evidence' => ['trusted_input' => 'human_verified event: a trusted tap or key, no webdriver flag, 1.5 s after load',
                                 'submitted_a_form' => 'one of: ' . implode(', ', RMT_PROOF_ACTION_EVENTS),
                                 'interacted_and_stayed' => 'human_interaction and ' . RMT_PROOF_LEGACY_DWELL . ' s or more between first and last event'],
            'bot_reasons' => ['webdriver' => 'automation_detected event (navigator.webdriver)',
                              'form_first' => 'first event was one of: ' . implode(', ', RMT_PROOF_FORM_FIRST),
                              'burst' => RMT_PROOF_BURST_SIZE . '+ new browsers on the same first path within ' . RMT_PROOF_BURST_SECONDS . ' s',
                              'no_cookie' => '2+ requests with the cookie bit recorded and none returned our cookie'],
        ],
        'days' => $out, 'totals' => $tot,
        'rates' => [
            'signup_rate_pct'        => $rate($tot['signups'], $tot['verified_humans']),
            'review_submit_rate_pct' => $rate($tot['review_submitted'], $tot['review_opened']),
            'confirm_rate_pct'       => $rate($tot['confirmed'], $tot['signups']),
        ],
        'reasons' => $reasons, 'channels' => $byChannel,
    ];
}

/** GET /cron/proof?key=...&days=14  JSON, same key and same 404 as /cron/kpi. */
function cron_proof(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = (string) input('key');
    if ($key === '' || $given === '' || !hash_equals($key, $given)) not_found();
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo json_encode(rmt_proof((int) (input('days') !== '' ? input('days') : 14)));
}

/** GET /admin/proof  the same numbers as a page, for staff. */
function admin_proof(array $a): void {
    require_login();
    $me = current_user();
    if (!in_array((string) ($me['role'] ?? ''), ['admin', 'mod'], true)) not_found();
    $days = (int) (input('days') !== '' ? input('days') : 14);
    view('admin_proof', ['p' => rmt_proof($days), 'daysAsked' => $days],
         ['title' => 'Proof | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}
