<?php
declare(strict_types=1);

/**
 * Where a visit came from, in one word this code owns.
 *
 * The question this exists to answer: did that post produce a signup? Until now the event table
 * knew which of OUR pages somebody was on and nothing about how they got to the site, so every
 * channel decision was going to be made on feeling.
 *
 * What it does NOT keep, and why. Not the referrer, because a referrer is a URL and a URL carries
 * a path, a query and sometimes somebody's own words. Not an address. Not a user agent. The
 * referring HOST is read for the length of one comparison, mapped to a word from the list below,
 * and dropped, in exactly the way the crawler check reads an agent it never writes down.
 *
 * FIRST TOUCH WINS. The channel is decided by the first arrival in a visit that names one, and
 * held from there, so somebody who lands from Reddit, reads three pages and signs up on the fourth
 * is a Reddit signup and not a direct one. It is also held in a cookie for ninety days, because a
 * person who comes back tomorrow to finish signing up came from the same post.
 */

/** The channels. A closed list, so nothing a stranger types can become a category. */
const RMT_ACQ_SOURCES = [
    'reddit', 'facebook', 'instagram', 'tiktok', 'x', 'youtube', 'linkedin', 'pinterest',
    'whatsapp', 'telegram', 'discord', 'search', 'referral', 'email', 'direct', 'other',
];

/** How it was shared, when a link says so. Also closed. */
const RMT_ACQ_MEDIUMS = ['post', 'comment', 'reply', 'bio', 'story', 'group', 'dm', 'social', 'organic', 'email', 'referral', 'other'];

const RMT_ACQ_COOKIE = 'rmt_acq';
const RMT_ACQ_TTL    = 90 * 86400;

/** Hosts we can name. Matched on the registrable part, so a regional or mobile host still counts. */
const RMT_ACQ_HOSTS = [
    'reddit.com' => 'reddit', 'redd.it' => 'reddit',
    'facebook.com' => 'facebook', 'fb.com' => 'facebook', 'fb.me' => 'facebook', 'm.facebook.com' => 'facebook',
    'instagram.com' => 'instagram', 'l.instagram.com' => 'instagram',
    'tiktok.com' => 'tiktok',
    'x.com' => 'x', 'twitter.com' => 'x', 't.co' => 'x',
    'youtube.com' => 'youtube', 'youtu.be' => 'youtube',
    'linkedin.com' => 'linkedin', 'lnkd.in' => 'linkedin',
    'pinterest.com' => 'pinterest',
    'whatsapp.com' => 'whatsapp', 'wa.me' => 'whatsapp',
    't.me' => 'telegram', 'telegram.me' => 'telegram',
    'discord.com' => 'discord', 'discord.gg' => 'discord',
    'google.com' => 'search', 'bing.com' => 'search', 'duckduckgo.com' => 'search',
    'search.yahoo.com' => 'search', 'ecosia.org' => 'search', 'brave.com' => 'search',
    'startpage.com' => 'search', 'yandex.com' => 'search', 'baidu.com' => 'search',
];

/** Letters, digits, dash and underscore, lowercased and capped. A label, never a sentence. */
function rmt_acq_slug(?string $v, int $max = 40): ?string {
    $v = strtolower(trim((string) $v));
    if ($v === '') return null;
    $v = preg_replace('/[^a-z0-9_\-]+/', '-', $v) ?? '';
    $v = trim($v, '-');
    return $v === '' ? null : mb_substr($v, 0, $max);
}

/**
 * Read the channel off this request: a utm parameter or a short `?ref=` first, then the referring
 * host, then nothing.
 *
 * @return array{source:?string, medium:?string, campaign:?string, content:?string}
 */
function rmt_acq_from_request(): array {
    $src = rmt_acq_slug((string) (input('utm_source') ?: input('ref')));
    if ($src !== null && !in_array($src, RMT_ACQ_SOURCES, true)) {
        /* A name we do not publish is still a real arrival, so it is kept as 'other' rather than
           thrown away or allowed to invent a category. */
        $src = 'other';
    }

    if ($src === null) {
        /* No parameter, so ask the referrer. The host is all that is read, and only to choose a
           word from the list; the rest of the URL is never touched and never stored. */
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = strtolower((string) (parse_url($ref, PHP_URL_HOST) ?: ''));
        if ($host !== '') {
            $self = strtolower((string) (parse_url((string) cfg('app_url'), PHP_URL_HOST) ?: ''));
            if ($host === $self || str_ends_with($host, '.' . $self)) return ['source' => null, 'medium' => null, 'campaign' => null, 'content' => null];
            $host = preg_replace('/^www\./', '', $host) ?? $host;
            $src = RMT_ACQ_HOSTS[$host] ?? null;
            if ($src === null) {
                foreach (RMT_ACQ_HOSTS as $known => $word) {
                    if (str_ends_with($host, '.' . $known)) { $src = $word; break; }
                }
            }
            if ($src === null) $src = 'referral';
        }
    }

    $medium = rmt_acq_slug((string) input('utm_medium'));
    if ($medium !== null && !in_array($medium, RMT_ACQ_MEDIUMS, true)) $medium = 'other';

    return [
        'source'   => $src,
        'medium'   => $medium,
        'campaign' => rmt_acq_slug((string) input('utm_campaign')),
        'content'  => rmt_acq_slug((string) input('utm_content')),
    ];
}

/**
 * The channel for this visit, decided once and then held.
 *
 * Held in the session for the visit and in a cookie for ninety days, because somebody who comes
 * back tomorrow to finish signing up came from the same post. Never overwritten by a later
 * arrival: first touch is the one that did the work.
 *
 * @return array{source:?string, medium:?string, campaign:?string, content:?string}
 */
function rmt_acq_current(): array {
    /* Cached per request in a global rather than a static, for the same reason the visitor token
       is: one request serves one visit, and a test simulating the next request has to be able to
       clear it. A static cannot be cleared from outside and quietly makes the second case in a
       suite a rerun of the first. */
    if (isset($GLOBALS['_rmt_acq_resolved'])) return (array) $GLOBALS['_rmt_acq_resolved'];

    $held = null;
    /* Read from the session array itself rather than from session_status(). In production a
       session is always running by the time this is reached; asking the status as well only makes
       the read fail in the one place it is easiest to verify. Writing is still guarded below. */
    if (!empty($_SESSION['_acq']['source'])) {
        $held = $_SESSION['_acq'];
    } elseif (!empty($_COOKIE[RMT_ACQ_COOKIE])) {
        $parts = explode('|', (string) $_COOKIE[RMT_ACQ_COOKIE], 4);
        $s = rmt_acq_slug($parts[0] ?? null);
        if ($s !== null && in_array($s, RMT_ACQ_SOURCES, true)) {
            $held = ['source' => $s, 'medium' => rmt_acq_slug($parts[1] ?? null),
                     'campaign' => rmt_acq_slug($parts[2] ?? null), 'content' => rmt_acq_slug($parts[3] ?? null)];
        }
    }

    $fresh = rmt_acq_from_request();
    /* A NAMED campaign on this request wins over a held one only when nothing was held at all.
       Otherwise the first post somebody clicked keeps the credit, which is the honest answer to
       "what produced this signup". */
    $use = $held ?? ($fresh['source'] !== null ? $fresh : null);

    if ($use !== null) {
        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['_acq'] = $use;
        if ($held === null && !headers_sent()) {
            setcookie(RMT_ACQ_COOKIE,
                implode('|', [(string) $use['source'], (string) $use['medium'], (string) $use['campaign'], (string) $use['content']]),
                ['expires' => time() + RMT_ACQ_TTL, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
                 'secure' => (string) cfg('app_env') === 'production']);
        }
    }
    return $GLOBALS['_rmt_acq_resolved'] = ($use ?? ['source' => null, 'medium' => null, 'campaign' => null, 'content' => null]);
}

/**
 * Visits, signups, confirmations and trips, by the channel that brought them.
 *
 * The table this reads holds no identity, so this cannot follow a person; it counts sessions that
 * carried a channel and what those sessions went on to do. Crawlers are excluded before anything
 * is written, and the human classification is applied on top, so a channel's "arrivals" are people
 * rather than requests.
 *
 * @return list<array<string,mixed>>
 */
function rmt_acq_report(int $days = 30): array {
    $since = rmt_funnel_since($days);
    $rows = q_all(
        "SELECT COALESCE(acq_source, 'direct') src,
                COALESCE(acq_campaign, '') campaign,
                journey,
                COUNT(*) events,
                COUNT(DISTINCT event) kinds,
                MIN(created_at) first_at,
                MAX(created_at) last_at,
                MAX(COALESCE(cookied, 0)) gave_cookie_back,
                COUNT(cookied) rows_with_the_bit,
                MAX(CASE WHEN event IN ('landing_view','destination_page_view') THEN 1 ELSE 0 END) landed,
                MAX(CASE WHEN event = 'join_submit'  THEN 1 ELSE 0 END) signup_started,
                MAX(CASE WHEN event = 'join_created' THEN 1 ELSE 0 END) signed_up,
                MAX(CASE WHEN event = 'join_confirmed' THEN 1 ELSE 0 END) confirmed,
                MAX(CASE WHEN event = 'trip_created' THEN 1 ELSE 0 END) tripped,
                MAX(CASE WHEN event IN ('destination_follow_click','ask_question_click','reaction_created',
                                        'comment_created','question_posted','post_created','trip_create_started',
                                        'join_submit','login_completed','trip_connect_requested') THEN 1 ELSE 0 END) acted
           FROM contribution_events
          WHERE created_at >= ? AND journey IS NOT NULL AND journey <> ''
       GROUP BY COALESCE(acq_source, 'direct'), COALESCE(acq_campaign, ''), journey", [$since]);

    $agg = [];
    foreach ($rows as $r) {
        $k = $r['src'] . '|' . $r['campaign'];
        $agg[$k] ??= ['source' => (string) $r['src'], 'campaign' => (string) $r['campaign'],
                      'sessions' => 0, 'human' => 0, 'landed' => 0, 'signup_started' => 0, 'signed_up' => 0,
                      'confirmed' => 0, 'trips' => 0];
        $agg[$k]['sessions']++;

        /* Human, by the same rules the traffic report uses, because a channel's conversion rate
           divided by crawlers is the mistake this whole measurement exists to stop making. Did
           something only a person does, or returned a cookie we set, or read more than one thing
           over human time. */
        $span = max(0, strtotime((string) $r['last_at']) - strtotime((string) $r['first_at']));
        $human = ((int) $r['acted'] === 1)
              || ((int) $r['gave_cookie_back'] === 1)
              || ((int) $r['kinds'] >= 2 && $span >= 20);
        if ($human) $agg[$k]['human']++;

        foreach (['landed' => 'landed', 'signup_started' => 'signup_started', 'signed_up' => 'signed_up',
                  'confirmed' => 'confirmed', 'tripped' => 'trips'] as $col => $key) {
            if ((int) $r[$col] === 1) $agg[$k][$key]++;
        }
    }
    $out = array_values($agg);
    $pct = static fn(int $a, int $b): ?float => $b > 0 ? round($a * 100 / $b, 1) : null;
    foreach ($out as &$row) {
        /* Every rate is a share of the HUMAN sessions for that channel, and a rate with a zero
           denominator is left out rather than printed as a zero, which reads as a failure that
           did not happen. */
        $row['visit_to_signup_pct'] = $pct($row['signed_up'], $row['human']);
        $row['signup_to_trip_pct']  = $pct($row['trips'], $row['signed_up']);
        $row['visit_to_trip_pct']   = $pct($row['trips'], $row['human']);
        // Kept under their old names so nothing that already reads this breaks.
        $row['signup_rate_pct'] = $row['visit_to_signup_pct'];
        $row['trip_rate_pct']   = $row['signup_to_trip_pct'];
    }
    unset($row);
    usort($out, static fn(array $a, array $b) => [$b['signed_up'], $b['human'], $b['sessions']]
                                             <=> [$a['signed_up'], $a['human'], $a['sessions']]);
    return $out;
}

/**
 * The campaigns that name a real travel window, and what that window is.
 *
 * Why this exists. Somebody who clicks a link about Oktoberfest already knows which city and
 * roughly which fortnight; making them pick both again from a blank form is asking them to do work
 * we have already done. So a campaign can carry a destination and a date range, and the pages a
 * campaign visitor sees offer that as the obvious next action.
 *
 * It never creates anything. The dates are a suggestion pre filled into a form the person still
 * submits themselves, and they can change both before they do.
 *
 * Every window here is checked against a primary source and recorded in docs/ACQUISITION_COHORTS.md
 * with what was verified. A campaign that is not in this list still tracks perfectly well; it just
 * does not know a date to suggest.
 *
 * @return array{slug:string, id:int, from:string, to:string, label:string}|null
 */
const RMT_ACQ_WINDOWS = [
    // Oktoberfest 2026: 19 September to 4 October, Theresienwiese (muenchen.de, oktoberfest.de).
    'oktoberfest'    => ['slug' => 'munich-germany',      'from' => '2026-09-19', 'to' => '2026-10-04', 'label' => 'Oktoberfest'],
    // Web Summit 2026: 9 to 12 November, Altice Arena and FIL (websummit.com).
    'web-summit'     => ['slug' => 'lisbon-portugal',     'from' => '2026-11-09', 'to' => '2026-11-12', 'label' => 'Web Summit'],
    // Yi Peng and Loy Krathong: guides give 23 to 25 November 2026 and disagree on the exact night.
    'yi-peng'        => ['slug' => 'chiang-mai-thailand', 'from' => '2026-11-23', 'to' => '2026-11-25', 'label' => 'Yi Peng'],
    // Miami Art Week: fairs in the first week of December, the travel window is the whole week.
    'miami-art-week' => ['slug' => 'miami-usa',           'from' => '2026-12-01', 'to' => '2026-12-07', 'label' => 'Art Week'],
];

function rmt_acq_window(?string $campaign = null): ?array {
    $c = $campaign ?? (rmt_acq_current()['campaign'] ?? null);
    if ($c === null || !isset(RMT_ACQ_WINDOWS[$c])) return null;
    $w = RMT_ACQ_WINDOWS[$c];
    $d = q_one('SELECT id FROM destinations WHERE slug = ?', [$w['slug']]);
    if (!$d) return null;                      // a city we no longer hold is not a suggestion
    if ($w['to'] < gmdate('Y-m-d')) return null;   // a window that has closed suggests nothing
    return $w + ['id' => (int) $d['id']];
}

/** The link that opens a trip form with the city and both dates already in it. */
function rmt_acq_trip_link(array $w): string {
    return url('trip/new?destination_id=' . (int) $w['id']
             . '&date_from=' . rawurlencode($w['from']) . '&date_to=' . rawurlencode($w['to']));
}
