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
const RMT_ACQ_MEDIUMS = ['post', 'comment', 'reply', 'bio', 'story', 'group', 'dm', 'social', 'organic', 'email', 'referral', 'share', 'other'];

/* What a member's own share is called, so it is never confused with a campaign we published.
   `utm_medium=share` plus this campaign is one traveler handing a link to another; anything else is
   us. The distinction is the whole point of the referral loop: a channel that works because members
   use it is worth more than one that works because we posted in it. */
const RMT_ACQ_REFERRAL_CAMPAIGN = 'member-share';

/* Our own checks arrive through the front door like anybody else, because that is the only way to
   check anything honestly. They are real sessions and they are not people we acquired, so they are
   named here and reported apart from the human count rather than deleted or quietly counted.
   Anything driving traffic for a test uses one of these campaign labels. */
const RMT_ACQ_INTERNAL_CAMPAIGNS = ['qa', 'coldqa', 'attrib-qa', 'attrib-qa-crawler', 'live-check', 'zz1'];

function rmt_acq_is_internal(?string $campaign): bool {
    return $campaign !== null && in_array($campaign, RMT_ACQ_INTERNAL_CAMPAIGNS, true);
}

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
    // Day of the Dead: 1 and 2 November are fixed by the calendar, the vigils and the comparsas
    // start on the 31st. Oaxaca is in the destination title experiment, so the organic line stays
    // off there until that finishes; a campaign visitor still gets it.
    'day-of-the-dead' => ['slug' => 'oaxaca-mexico', 'from' => '2026-10-31', 'to' => '2026-11-02', 'label' => 'Day of the Dead'],
    // New Year: no organiser to verify, the date is the date. Peak of the Southeast Asia season.
    'new-year-2027'  => ['slug' => 'bangkok-thailand',    'from' => '2026-12-27', 'to' => '2027-01-02', 'label' => 'New Year'],
    // Rio Carnival 2027: Ash Wednesday falls on 10 February, so the street days are 5 to 9 February
    // and the champions parade is the 13th (riocarnaval.org, and the Easter calendar agrees).
    'rio-carnival'   => ['slug' => 'rio-de-janeiro-brazil', 'from' => '2027-02-05', 'to' => '2027-02-13', 'label' => 'Carnival'],
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

/**
 * The window a city is about to live through, for somebody who arrived without a campaign.
 *
 * A person who searches their way onto the Munich page four days before Oktoberfest wants the same
 * thing the campaign visitor wants, and until now only the campaign visitor was offered it. So the
 * window is shown to everybody once it is close, and not before: a December question in September
 * is clutter on a page that is read all year.
 *
 * Two deliberate refusals. A city in the destination title experiment is excluded, because changing
 * what that page says during the experiment is how a clean result turns into an unreadable one. And
 * a window that has closed shows nothing, which `rmt_acq_window()` already decides.
 *
 * @return array{slug:string, from:string, to:string, label:string, id:int}|null
 */
function rmt_acq_window_near(string $slug, int $withinDays = 30): ?array {
    if ($slug === '') return null;
    if (defined('RMT_DEST_SOCIAL_TITLE_TEST') && isset(RMT_DEST_SOCIAL_TITLE_TEST[$slug])) return null;
    $limit = gmdate('Y-m-d', strtotime('+' . max(0, $withinDays) . ' days'));
    foreach (RMT_ACQ_WINDOWS as $campaign => $w) {
        if ($w['slug'] !== $slug) continue;
        if ($w['from'] > $limit) continue;            // still too far off to be the reason they are here
        $resolved = rmt_acq_window($campaign);        // this is what checks the city exists and the window is open
        if ($resolved !== null) return $resolved;
    }
    return null;
}

/**
 * A shareable version of one of our own URLs, tagged so the click can be told apart.
 *
 * Two kinds of link leave this site and they are not the same thing. One is a link we published in
 * a campaign. The other is a member sending a page to somebody they know, which is the only channel
 * that compounds. Both are tagged; only the second carries `utm_medium=share`.
 *
 * It refuses to tag anything that is not ours, so a stray absolute URL cannot be decorated and
 * handed out looking like us, and it preserves an existing query string rather than trampling it.
 * Nothing private goes in: the input is a page address that is already public to whoever holds it.
 */
function rmt_share_url(string $url, string $channel, ?string $campaign = null): string {
    $self = strtolower((string) (parse_url((string) cfg('app_url'), PHP_URL_HOST) ?: ''));
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
    if ($host !== '' && $self !== '' && $host !== $self && !str_ends_with($host, '.' . $self)) return $url;

    $src = rmt_acq_slug($channel) ?? 'other';
    if (!in_array($src, RMT_ACQ_SOURCES, true)) $src = 'other';
    $q = http_build_query([
        'utm_source'   => $src,
        'utm_medium'   => 'share',
        'utm_campaign' => rmt_acq_slug($campaign) ?? RMT_ACQ_REFERRAL_CAMPAIGN,
    ]);
    return $url . (str_contains($url, '?') ? '&' : '?') . $q;
}

/**
 * The operating view: the same channels over three windows at once.
 *
 * One table over thirty days answers "did that campaign ever work". It does not answer the question
 * somebody running a campaign actually has, which is "is it working now". So the report is taken
 * over a day, a week and the whole period, and the three are lined up per channel.
 *
 * `rmt_acq_report()` already does the counting and the human filtering, so this adds no new rules:
 * it runs it three times and joins the answers. Channels are ordered by the recent end, because a
 * channel that produced a signup yesterday matters more than one that produced ten in August.
 *
 * @return array{windows:array<string,int>, rows:list<array<string,mixed>>, totals:array<string,int>}
 */
function rmt_acq_command_center(int $days = 90): array {
    $windows = ['d1' => 1, 'd7' => 7, 'all' => max(1, $days)];
    $byWindow = [];
    foreach ($windows as $key => $n) {
        foreach (rmt_acq_report($n) as $row) {
            $byWindow[$key][$row['source'] . '|' . $row['campaign']] = $row;
        }
    }

    $keys = [];
    foreach ($byWindow as $rows) foreach (array_keys($rows) as $k) $keys[$k] = true;

    $rows = [];
    foreach (array_keys($keys) as $k) {
        [$source, $campaign] = array_pad(explode('|', (string) $k, 2), 2, '');
        $row = ['source' => $source, 'campaign' => $campaign];
        foreach (array_keys($windows) as $w) {
            $r = $byWindow[$w][$k] ?? null;
            $row[$w] = [
                'human'     => (int) ($r['human'] ?? 0),
                'signups'   => (int) ($r['signed_up'] ?? 0),
                'confirmed' => (int) ($r['confirmed'] ?? 0),
                'trips'     => (int) ($r['trips'] ?? 0),
                'visit_to_signup_pct' => $r['visit_to_signup_pct'] ?? null,
                'signup_to_trip_pct'  => $r['signup_to_trip_pct'] ?? null,
                'visit_to_trip_pct'   => $r['visit_to_trip_pct'] ?? null,
                /* Kept beside the human number rather than folded into it, so the gap between what
                   arrived and what was a person stays visible instead of being quietly corrected. */
                'sessions'  => (int) ($r['sessions'] ?? 0),
            ];
        }
        $rows[] = $row;
    }
    usort($rows, static fn(array $a, array $b) =>
        [$b['d1']['signups'], $b['d7']['signups'], $b['d7']['human'], $b['all']['human']]
    <=> [$a['d1']['signups'], $a['d7']['signups'], $a['d7']['human'], $a['all']['human']]);

    /* A row we generated ourselves is marked rather than removed, so the table still adds up and
       nobody has to remember which campaign labels were ours. */
    foreach ($rows as &$row) $row['internal'] = rmt_acq_is_internal($row['campaign'] === '' ? null : $row['campaign']);
    unset($row);

    $totals = [];
    foreach (array_keys($windows) as $w) {
        $totals[$w] = ['human' => 0, 'signups' => 0, 'confirmed' => 0, 'trips' => 0, 'sessions' => 0, 'internal_human' => 0];
        foreach ($rows as $r) {
            foreach (['human', 'signups', 'confirmed', 'trips', 'sessions'] as $m) {
                if ($r['internal'] && $m !== 'sessions') continue;   // ours is not acquisition
                $totals[$w][$m] += (int) $r[$w][$m];
            }
            if ($r['internal']) $totals[$w]['internal_human'] += (int) $r[$w]['human'];
        }
    }
    return ['windows' => $windows, 'rows' => $rows, 'totals' => $totals];
}

/**
 * The daily line. Nine numbers, so it is obvious in one screen whether this is working.
 *
 * Deliberately not a second analytics product: it reads the same report everything else reads and
 * picks out the things somebody running a campaign checks in the morning. Our own verification
 * traffic is excluded from every figure, because a number that counts our own checks as travelers
 * is worse than no number.
 *
 * @return array<string,mixed>
 */
function rmt_acq_daily(): array {
    $cc = rmt_acq_command_center(90);
    $real = array_values(array_filter($cc['rows'], static fn(array $r) => !$r['internal']));

    $pick = static function (array $rows, string $window, string $metric): ?array {
        $best = null;
        foreach ($rows as $r) {
            if ((int) $r[$window][$metric] <= 0) continue;
            if ($best === null || (int) $r[$window][$metric] > (int) $best[$window][$metric]) $best = $r;
        }
        return $best;
    };

    $topSource   = $pick($real, 'd1', 'human') ?? $pick($real, 'd7', 'human');
    $topCampaign = null;
    foreach ($real as $r) {
        if ($r['campaign'] === '') continue;
        if ((int) $r['d7']['human'] <= 0) continue;
        if ($topCampaign === null || (int) $r['d7']['human'] > (int) $topCampaign['d7']['human']) $topCampaign = $r;
    }
    /* Best conversion, not best volume, and only where there is a denominator worth dividing by.
       A single visit that signed up is 100% and means nothing. */
    $bestConv = null;
    foreach ($real as $r) {
        if ((int) $r['d7']['human'] < 5) continue;
        $rate = $r['d7']['visit_to_signup_pct'];
        if ($rate === null) continue;
        if ($bestConv === null || $rate > $bestConv['rate']) {
            $bestConv = ['source' => $r['source'], 'campaign' => $r['campaign'], 'rate' => $rate];
        }
    }

    $shape = function_exists('rmt_traffic_shape') ? rmt_traffic_shape(1) : [];
    $landing = $shape['landing_destinations']['human'] ?? [];
    $topLanding = null;
    foreach ($landing as $slug => $n) { $topLanding = ['slug' => $slug, 'n' => (int) $n]; break; }

    $d1 = $cc['totals']['d1']; $d7 = $cc['totals']['d7'];
    /* The one line worth reading first: is today different from the week it sits in. A day is a
       seventh of a week, so the week divided by seven is the only fair comparison. */
    $expected = $d7['human'] / 7;
    $change = $expected > 0 ? round(($d1['human'] - $expected) / $expected * 100) : null;

    return [
        'as_of'           => gmdate('Y-m-d H:i') . ' UTC',
        'human_visits'    => ['today' => (int) $d1['human'], 'week' => (int) $d7['human']],
        'signups'         => ['today' => (int) $d1['signups'], 'week' => (int) $d7['signups']],
        'confirmed'       => ['today' => (int) $d1['confirmed'], 'week' => (int) $d7['confirmed']],
        'trips'           => ['today' => (int) $d1['trips'], 'week' => (int) $d7['trips']],
        'top_source'      => $topSource ? $topSource['source'] : null,
        'top_campaign'    => $topCampaign ? $topCampaign['campaign'] : null,
        'top_landing'     => $topLanding,
        'best_conversion' => $bestConv,
        'notable_change'  => $change === null ? null : $change . '% against the weekly daily average',
        'our_own_checks_excluded' => (int) ($d7['internal_human'] ?? 0),
        'note' => 'Every figure excludes automated traffic and our own verification campaigns. A rate with fewer than five human sessions behind it is not reported at all.',
    ];
}
