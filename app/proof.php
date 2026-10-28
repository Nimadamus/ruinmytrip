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
 * So every browser in the window is put in exactly one of three buckets, with the reason kept.
 * THE BUCKETS ARE ESTIMATES, NOT FACTS. A patient bot can earn "verified human"; a real person on a
 * shared connection can land in a burst. The reasons are reported so the call can be checked.
 *
 *   VERIFIED HUMAN   it did something a person does: a trusted tap or keypress from a browser
 *                    that does not announce automation, at least 1.5 s after the page loaded
 *                    (event human_verified, sent by contribute-track.js since 2026-10-11), or it
 *                    submitted a real form (an action event below), or, for history before
 *                    human_verified existed, it sent human_interaction and stayed 10 s or more.
 *                    AND none of the automation signals below.
 *   SUSPECTED BOT, with one reason, checked in this order:
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
 * trips, buddy posts, messages, saves, follows) come from their own tables and exclude house
 * accounts (rmt_sc_real_user_sql). Every day is a Pacific day. Nothing here writes.
 *
 * MEMORY. At production's volume (measured 2026-10-11: about 52,000 events and 30,000 browsers in
 * 31 days) the first version of this file peaked at 96MB, and three times that volume ran out of
 * the 256MB limit. Events are now read in slices of 20,000 by id and each browser is a handful of
 * integers with its days as bits, so memory grows with browsers, not events.
 * scripts/ci/proof_load_check.php measures it.
 */

/** Things a person submits. Each is recorded by the server on a POST, or by a trusted click. */
const RMT_PROOF_ACTION_EVENTS = [
    'review_submit_attempt', 'review_held_for_join', 'review_publish_success', 'join_submit', 'join_created',
    'join_confirmed', 'login_completed', 'trip_created', 'post_created', 'comment_created', 'reaction_created',
    'question_posted', 'message_sent', 'destination_follow_success', 'buddy_post_created', 'plan_submitted',
    'alert_submitted', 'map_saved', 'card_created', 'trip_connect_requested',
];

/** Server recorded GET events for form pages. A browser whose first event is one of these skipped every page. */
const RMT_PROOF_FORM_FIRST = ['review_form_start', 'review_signup_required', 'join_view', 'plan_signup_view'];

/** Events that count as using the travel map. */
const RMT_PROOF_MAP_EVENTS = ['map_view', 'map_saved'];

const RMT_PROOF_BURST_SIZE = 5;
const RMT_PROOF_BURST_SECONDS = 60;
const RMT_PROOF_LEGACY_DWELL = 10;
/** Stop reading past this many events (production: about 52,000 in 31 days; 300,000 measured at 110MB). */
const RMT_PROOF_MAX_EVENTS = 400000;

/** The metrics in /cron/proof, in the order the dashboard shows them, with the definition of each. */
const RMT_PROOF_DEFINITIONS = [
    'verified_humans'   => 'Browsers seen that day that the window classifies as a verified human. An estimate: see the classification rules.',
    'suspected_bots'    => 'Browsers seen that day classified as suspected automation, any reason. An estimate.',
    'unverified'        => 'Browsers seen that day with no evidence either way (mostly one page and gone).',
    'returning_humans'  => 'Verified humans seen that day who were also seen on an earlier Pacific day in the window.',
    'search_humans'     => 'Verified humans whose first touch channel was search, counted on their first day.',
    'referral_humans'   => 'Verified humans from any channel other than direct or search (social, outreach, invites, launch sites).',
    'map_users'         => 'Verified humans who opened or saved the travel map.',
    'map_saves'         => 'Travel maps saved to an account (map_saved), any browser.',
    'review_opened'     => 'Verified humans who opened the review form.',
    'review_submitted'  => 'Verified humans who pressed Post or Publish on a review (review_submit_attempt).',
    'review_held'       => 'Guest reviews validated and held for the account step (review_held_for_join), distinct journeys.',
    'reviews_published' => 'Reviews by real members now published, by the day they were written (reviews table).',
    'signups'           => 'Real member accounts created that day (users table).',
    'confirmed'         => 'Of those accounts, how many have confirmed their email by now.',
    'active_members'    => 'Distinct real members who wrote something that day: a review, post, comment, trip, buddy post, message, save or follow.',
    'trips'             => 'Trips created by real members.',
    'buddy_posts'       => 'Travel buddy posts by real members.',
    'messages'          => 'Messages sent by real members.',
    'alerts_confirmed'  => 'Match alerts whose email link was clicked that day.',
];

/* One browser's state: a short list of integers. Flags are bits; days are bits of one integer. */
const RMT_PF_VERIFIED = 1, RMT_PF_ACTED = 2, RMT_PF_INTERACT = 4, RMT_PF_WEBDRIVER = 8,
      RMT_PF_COOKIE_KNOWN = 16, RMT_PF_COOKIE_BACK = 32, RMT_PF_JOINED = 64, RMT_PF_REVIEWED = 128, RMT_PF_MAP = 256;
const RMT_PS_FIRST = 0, RMT_PS_LAST = 1, RMT_PS_FORMFIRST = 2, RMT_PS_KEY = 3, RMT_PS_FLAGS = 4, RMT_PS_N = 5,
      RMT_PS_SRC = 6, RMT_PS_DAYS = 7, RMT_PS_OPENED = 8, RMT_PS_SUBMITTED = 9, RMT_PS_MAPDAYS = 10;

/**
 * Fold one event into the per browser state. $dayIdx turns a stored UTC timestamp into a day
 * index (0 up to 61) or -1 when it falls outside the window.
 */
function rmt_proof_fold(array &$by, array &$keys, array &$srcs, array $r, callable $dayIdx): void {
    $v = (string) ($r['visitor'] ?? '');
    if ($v === '') return;
    $ts = (int) strtotime((string) $r['created_at']);
    $ev = (string) $r['event'];
    $path = (string) ($r['path'] ?? '');
    $kid = $keys[$path !== '' ? $path : $ev] ??= count($keys);
    $di = $dayIdx((string) $r['created_at']);
    $bit = $di >= 0 ? (1 << $di) : 0;
    $src = (string) ($r['acq_source'] ?? '');
    $formFirst = in_array($ev, RMT_PROOF_FORM_FIRST, true) ? 1 : 0;
    if (!isset($by[$v])) {
        $by[$v] = [$ts, $ts, $formFirst, $kid, 0, 0, $src !== '' ? ($srcs[$src] ??= count($srcs)) : -1, 0, 0, 0, 0];
    }
    $b = &$by[$v];
    if ($ts < $b[RMT_PS_FIRST]) {      // rows arrive in id order, which is nearly but not always time order
        $b[RMT_PS_FIRST] = $ts; $b[RMT_PS_FORMFIRST] = $formFirst; $b[RMT_PS_KEY] = $kid;
    }
    if ($ts > $b[RMT_PS_LAST]) $b[RMT_PS_LAST] = $ts;
    $b[RMT_PS_N]++;
    $b[RMT_PS_DAYS] |= $bit;
    if ($b[RMT_PS_SRC] < 0 && $src !== '') $b[RMT_PS_SRC] = $srcs[$src] ??= count($srcs);
    $f = 0;
    if ($ev === 'human_verified') $f |= RMT_PF_VERIFIED;
    elseif ($ev === 'human_interaction') $f |= RMT_PF_INTERACT;
    elseif ($ev === 'automation_detected') $f |= RMT_PF_WEBDRIVER;
    if (in_array($ev, RMT_PROOF_ACTION_EVENTS, true)) $f |= RMT_PF_ACTED;
    if ($ev === 'join_created') $f |= RMT_PF_JOINED;
    if ($ev === 'review_submit_attempt') { $f |= RMT_PF_REVIEWED; $b[RMT_PS_SUBMITTED] |= $bit; }
    if ($ev === 'review_form_start') $b[RMT_PS_OPENED] |= $bit;
    if (in_array($ev, RMT_PROOF_MAP_EVENTS, true)
        || ($ev === 'cta_click' && (string) ($r['detail'] ?? '') === 'map_tap')) {
        $f |= RMT_PF_MAP; $b[RMT_PS_MAPDAYS] |= $bit;
    }
    $ck = $r['cookied'] ?? null;
    if ($ck !== null && $ck !== '') { $f |= RMT_PF_COOKIE_KNOWN; if ((int) $ck === 1) $f |= RMT_PF_COOKIE_BACK; }
    $b[RMT_PS_FLAGS] |= $f;
    unset($b);
}

/** @return array<string,array{0:string,1:string}> browser => [class, reason]; class is human, bot or unverified */
function rmt_proof_verdicts(array $by): array {
    $starts = [];
    foreach ($by as $v => $b) $starts[$b[RMT_PS_KEY]][] = [$b[RMT_PS_FIRST], $v];
    $inBurst = [];
    foreach ($starts as $list) {
        if (count($list) < RMT_PROOF_BURST_SIZE) continue;
        usort($list, static fn($a, $b) => $a[0] <=> $b[0]);
        $n = count($list); $lo = 0;
        for ($hi = 0; $hi < $n; $hi++) {
            while ($list[$hi][0] - $list[$lo][0] > RMT_PROOF_BURST_SECONDS) $lo++;
            if ($hi - $lo + 1 >= RMT_PROOF_BURST_SIZE) for ($k = $lo; $k <= $hi; $k++) $inBurst[$list[$k][1]] = true;
        }
    }
    unset($starts);
    $out = [];
    foreach ($by as $v => $b) {
        $f = $b[RMT_PS_FLAGS];
        $evidence = ($f & RMT_PF_VERIFIED) ? 'trusted_input'
            : (($f & RMT_PF_ACTED) ? 'submitted_a_form'
            : ((($f & RMT_PF_INTERACT) && $b[RMT_PS_LAST] - $b[RMT_PS_FIRST] >= RMT_PROOF_LEGACY_DWELL) ? 'interacted_and_stayed' : ''));
        $suspect = ($f & RMT_PF_WEBDRIVER) ? 'webdriver'
            : ($b[RMT_PS_FORMFIRST] ? 'form_first'
            : (isset($inBurst[$v]) ? 'burst'
            : ((($f & RMT_PF_COOKIE_KNOWN) && $b[RMT_PS_N] >= 2 && !($f & RMT_PF_COOKIE_BACK)) ? 'no_cookie' : '')));
        $out[$v] = $suspect !== '' ? ['bot', $suspect] : ($evidence !== '' ? ['human', $evidence] : ['unverified', '']);
    }
    return $out;
}

/**
 * Classify every browser in a set of rows: the same fold and verdict the dashboard uses, expanded
 * into a readable shape. For tests and small reads.
 *
 * @param iterable<array{visitor:string,event:string,path:?string,created_at:string,cookied:?int,acq_source:?string}> $rows
 * @return array<string,array{class:string,reason:string,days:array<string,true>,first:string,source:string}>
 */
function rmt_proof_classify(iterable $rows): array {
    $by = []; $keys = []; $srcs = []; $dayList = [];
    $dayIdx = static function (string $utc) use (&$dayList): int {
        $d = rmt_kpi_day_of($utc);
        $i = array_search($d, $dayList, true);
        if ($i === false) { $dayList[] = $d; $i = count($dayList) - 1; }
        return $i < 62 ? (int) $i : -1;
    };
    foreach ($rows as $r) rmt_proof_fold($by, $keys, $srcs, $r, $dayIdx);
    $names = array_flip($srcs);
    $out = [];
    foreach (rmt_proof_verdicts($by) as $v => [$class, $reason]) {
        $b = $by[$v]; $days = [];
        foreach ($dayList as $i => $d) if ($b[RMT_PS_DAYS] & (1 << $i)) $days[$d] = true;
        $out[$v] = ['class' => $class, 'reason' => $reason, 'days' => $days, 'first' => gmdate('Y-m-d H:i:s', $b[RMT_PS_FIRST]),
                    'source' => $b[RMT_PS_SRC] >= 0 ? (string) $names[$b[RMT_PS_SRC]] : ''];
    }
    return $out;
}

/** Events in the window, read in slices by id so no single result set holds all of them. */
function rmt_proof_stream(string $from, string $to, int $chunk = 20000): Generator {
    $notSelf = "COALESCE(acq_content, '') <> 'selfcheck'";
    $last = 0;
    while (true) {
        $rows = rmt_kpi_rows("SELECT id, visitor, event, path, detail, created_at, cookied, acq_source FROM contribution_events
                               WHERE created_at >= ? AND created_at < ? AND id > ? AND visitor IS NOT NULL AND visitor <> ''
                                 AND $notSelf ORDER BY id LIMIT $chunk", [$from, $to, $last]);
        if (!$rows) return;
        $last = (int) $rows[count($rows) - 1]['id'];
        $n = count($rows);
        foreach ($rows as $r) yield $r;
        unset($rows);
        if ($n < $chunk) return;
    }
}

/** @return array<string,mixed> the daily table, the totals, the funnel and the reasons, for N Pacific days */
function rmt_proof(int $days = 14, ?string $now = null): array {
    $days = min(max($days, 1), 31);
    $span = rmt_kpi_days($days, $now);
    $from = $span[0]['from']; $to = $span[count($span) - 1]['to'];
    $notSelf = "COALESCE(acq_content, '') <> 'selfcheck'";
    $bounds = array_column($span, 'from');
    $dayIdx = static function (string $utc) use ($bounds, $to): int {
        if ($utc < $bounds[0] || $utc >= $to) return -1;
        $lo = 0; $hi = count($bounds) - 1;
        while ($lo < $hi) { $mid = intdiv($lo + $hi + 1, 2); if ($bounds[$mid] <= $utc) $lo = $mid; else $hi = $mid - 1; }
        return $lo;
    };
    $by = []; $keys = []; $srcs = []; $events = 0;
    $truncated = false;
    foreach (rmt_proof_stream($from, $to) as $r) {
        rmt_proof_fold($by, $keys, $srcs, $r, $dayIdx);
        // A hard stop far above any real volume, so a flood of junk rows cannot take the instance down.
        if (++$events >= RMT_PROOF_MAX_EVENTS) { $truncated = true; break; }
    }
    unset($keys);
    $verdict = rmt_proof_verdicts($by);
    $srcName = array_flip($srcs);

    $grid = [];
    foreach ($span as $d) $grid[$d['day']] = array_fill_keys(array_keys(RMT_PROOF_DEFINITIONS), 0);
    $dayNames = array_column($span, 'day');
    $nd = count($dayNames);
    $reasons = ['human' => [], 'bot' => []];
    $buckets = ['human' => 0, 'bot' => 0, 'unverified' => 0];
    $returning = 0;
    $byChannel = [];
    foreach ($by as $v => $b) {
        [$class, $why] = $verdict[$v];
        $buckets[$class]++;
        if ($class !== 'unverified') $reasons[$class][$why] = ($reasons[$class][$why] ?? 0) + 1;
        $col = $class === 'human' ? 'verified_humans' : ($class === 'bot' ? 'suspected_bots' : 'unverified');
        $seen = 0; $firstDay = -1;
        for ($i = 0; $i < $nd; $i++) {
            $bit = 1 << $i;
            if (!($b[RMT_PS_DAYS] & $bit)) continue;
            $grid[$dayNames[$i]][$col]++;
            if ($class !== 'human') continue;
            if ($seen) $grid[$dayNames[$i]]['returning_humans']++;
            if ($firstDay < 0) $firstDay = $i;
            $seen++;
            if ($b[RMT_PS_OPENED] & $bit) $grid[$dayNames[$i]]['review_opened']++;
            if ($b[RMT_PS_SUBMITTED] & $bit) $grid[$dayNames[$i]]['review_submitted']++;
            if ($b[RMT_PS_MAPDAYS] & $bit) $grid[$dayNames[$i]]['map_users']++;
        }
        if ($class !== 'human') continue;
        if ($seen >= 2) $returning++;
        $src = $b[RMT_PS_SRC] >= 0 ? (string) $srcName[$b[RMT_PS_SRC]] : '';
        if ($firstDay >= 0) {
            if ($src === 'search') $grid[$dayNames[$firstDay]]['search_humans']++;
            elseif ($src !== '' && $src !== 'direct') $grid[$dayNames[$firstDay]]['referral_humans']++;
        }
        $ch = $src !== '' ? $src : 'unknown';
        $c = $byChannel[$ch] ?? ['humans' => 0, 'joined' => 0, 'reviewed' => 0, 'used_map' => 0];
        $c['humans']++;
        if ($b[RMT_PS_FLAGS] & RMT_PF_JOINED) $c['joined']++;
        if ($b[RMT_PS_FLAGS] & RMT_PF_REVIEWED) $c['reviewed']++;
        if ($b[RMT_PS_FLAGS] & RMT_PF_MAP) $c['used_map']++;
        $byChannel[$ch] = $c;
    }
    $browsers = count($by);
    unset($by, $verdict);
    uasort($byChannel, static fn($a, $b) => $b['humans'] <=> $a['humans']);

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
    foreach (rmt_kpi_rows("SELECT created_at FROM contribution_events WHERE event = 'map_saved'
                            AND created_at >= ? AND created_at < ? AND $notSelf", [$from, $to]) as $r) {
        $day = rmt_kpi_day_of((string) $r['created_at']);
        if (isset($grid[$day])) $grid[$day]['map_saves']++;
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

    /* Active members: distinct real members who wrote something that day. Each is a row a person
       made; reading a page is not participation. likes has no timestamp and is left out. */
    $active = []; $activeAll = [];
    foreach ([
        "SELECT r.user_id uid, r.created_at FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.status = 'published' AND r.created_at >= ? AND r.created_at < ? AND $real",
        "SELECT p.user_id uid, p.created_at FROM posts p JOIN users u ON u.id = p.user_id WHERE p.created_at >= ? AND p.created_at < ? AND $real",
        "SELECT c.user_id uid, c.created_at FROM comments c JOIN users u ON u.id = c.user_id WHERE c.created_at >= ? AND c.created_at < ? AND $real",
        "SELECT t.user_id uid, t.created_at FROM trips t JOIN users u ON u.id = t.user_id WHERE t.created_at >= ? AND t.created_at < ? AND $real",
        "SELECT b.user_id uid, b.created_at FROM buddy_posts b JOIN users u ON u.id = b.user_id WHERE b.created_at >= ? AND b.created_at < ? AND $real",
        "SELECT m.sender_id uid, m.created_at FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.created_at >= ? AND m.created_at < ? AND $real",
        "SELECT s.user_id uid, s.created_at FROM saves s JOIN users u ON u.id = s.user_id WHERE s.created_at >= ? AND s.created_at < ? AND $real",
        "SELECT f.follower_id uid, f.created_at FROM follows f JOIN users u ON u.id = f.follower_id WHERE f.created_at >= ? AND f.created_at < ? AND $real",
    ] as $sql) {
        foreach (rmt_kpi_rows($sql, [$from, $to]) as $r) {
            $day = rmt_kpi_day_of((string) $r['created_at']);
            if (!isset($grid[$day])) continue;
            $active[$day][(int) $r['uid']] = true;
            $activeAll[(int) $r['uid']] = true;
        }
    }
    foreach ($active as $day => $set) $grid[$day]['active_members'] = count($set);

    $out = []; $tot = array_fill_keys(array_keys(RMT_PROOF_DEFINITIONS), 0);
    foreach ($grid as $day => $vals) { $out[] = ['day' => $day] + $vals; foreach ($vals as $k => $n) $tot[$k] += $n; }
    // Browser and member totals are distinct over the window, not a sum of days.
    $tot['verified_humans'] = $buckets['human'];
    $tot['suspected_bots']  = $buckets['bot'];
    $tot['unverified']      = $buckets['unverified'];
    $tot['returning_humans'] = $returning;
    $tot['active_members']  = count($activeAll);

    $rate = static fn(int $a, int $b): ?float => $b > 0 ? round(100 * $a / $b, 1) : null;
    return [
        'tz' => RMT_KPI_TZ, 'from_utc' => $from, 'to_utc' => $to, 'browsers_read' => $browsers, 'events_read' => $events, 'truncated' => $truncated,
        'note' => 'Human and bot buckets are estimates from behaviour, not identities. Read the reasons with the numbers.',
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
            'review_per_human_pct'   => $rate($tot['review_submitted'], $tot['verified_humans']),
            'confirm_rate_pct'       => $rate($tot['confirmed'], $tot['signups']),
            'returning_pct'          => $rate($tot['returning_humans'], $tot['verified_humans']),
        ],
        'reasons' => $reasons, 'channels' => $byChannel,
    ];
}

/** GET /cron/proof?key=...&days=14  JSON, same key and same 404 as /cron/kpi. */
function cron_proof(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = rmt_cron_key_given();
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
