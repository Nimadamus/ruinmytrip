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

/* Some checks have to use the REAL campaign name, because what they are checking is what a real
   campaign visitor sees. Those carry this in utm_content instead, so the session can be excluded
   without inventing a fake campaign. Any script driving traffic at production adds
   `utm_content=selfcheck` unless it is deliberately measuring the unmarked path. */
const RMT_ACQ_INTERNAL_CONTENT = 'selfcheck';

/* Before this moment the marker did not exist, so verification traffic was recorded under real
   campaign names and cannot be told apart now. It is not rewritten: a row is what happened. It is
   labelled, everywhere a number from that period is shown, and the honest reading of anything dated
   inside it is "this was us unless a post was actually published".
   The marker went in at 2026-09-16 00:30 Pacific. The boundary below is set LATER than that on
   purpose: the campaign validation run that checked all seven windows went out in the minutes
   before the marker existed, under the real campaign names, and left eleven human sessions that
   are ours. Rather than rewrite those rows or pretend they are travelers, the clean window starts
   after them. Nothing genuine is lost, because nothing had been published anywhere. */
const RMT_ACQ_CLEAN_FROM = '2026-09-16 08:20:00';

function rmt_acq_window_is_contaminated(int $days): bool {
    if ($days <= 0) return true;                                  // all time always includes it
    return rmt_funnel_since($days) < RMT_ACQ_CLEAN_FROM;
}

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
function rmt_acq_report(int $days = 30, ?string $sinceOverride = null): array {
    /* A day count cannot express "since 08:20 this morning", and the clean window is a moment
       rather than a number of days: moving the boundary a few hours forward changed nothing at all
       while this rounded up to one whole day. So a caller that knows the exact moment passes it. */
    $since = $sinceOverride ?? rmt_funnel_since($days);
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
                MAX(CASE WHEN acq_content = ? THEN 1 ELSE 0 END) selfcheck,
                MAX(CASE WHEN event IN ('destination_follow_click','ask_question_click','reaction_created',
                                        'comment_created','question_posted','post_created','trip_create_started',
                                        'join_submit','login_completed','trip_connect_requested') THEN 1 ELSE 0 END) acted
           FROM contribution_events
          WHERE created_at >= ? AND journey IS NOT NULL AND journey <> ''
       GROUP BY COALESCE(acq_source, 'direct'), COALESCE(acq_campaign, ''), journey",
        [RMT_ACQ_INTERNAL_CONTENT, $since]);

    $agg = [];
    foreach ($rows as $r) {
        $k = $r['src'] . '|' . $r['campaign'];
        $agg[$k] ??= ['source' => (string) $r['src'], 'campaign' => (string) $r['campaign'],
                      'sessions' => 0, 'human' => 0, 'landed' => 0, 'signup_started' => 0, 'signed_up' => 0,
                      'confirmed' => 0, 'trips' => 0, 'selfcheck_human' => 0];
        $agg[$k]['sessions']++;

        /* Human, by the same rules the traffic report uses, because a channel's conversion rate
           divided by crawlers is the mistake this whole measurement exists to stop making. Did
           something only a person does, or returned a cookie we set, or read more than one thing
           over human time. */
        $span = max(0, strtotime((string) $r['last_at']) - strtotime((string) $r['first_at']));
        $human = ((int) $r['acted'] === 1)
              || ((int) $r['gave_cookie_back'] === 1)
              || ((int) $r['kinds'] >= 2 && $span >= 20);
        if ($human) {
            $agg[$k]['human']++;
            /* Ours, driven at production to check what a campaign visitor sees. Counted here so the
               number can be taken back out rather than quietly left in. */
            if ((int) ($r['selfcheck'] ?? 0) === 1) $agg[$k]['selfcheck_human']++;
        }

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
/* One sentence per window, for the events page. Why a stranger's presence that week changes the
   trip, which is the only reason any of these is on the list. */
const RMT_ACQ_WINDOW_WHY = [
    'oktoberfest'     => 'A tent table is a group activity. Arriving without one is the thing everybody complains about.',
    'day-of-the-dead' => 'The vigils are in cemeteries outside the city, after dark. Almost nobody wants to do that alone.',
    'web-summit'      => 'The conference app matches you with attendees. Nothing matches you for the weekend either side.',
    'yi-peng'         => 'The mass lantern releases are ticketed and out of town, so getting there costs one other person.',
    'miami-art-week'  => 'The fairs are easy. Which days are worth staying for is the question, and it is better with company.',
    'new-year-2027'   => 'New Year in a city you landed in yesterday is the exact problem this site was built for.',
    'rio-carnival'    => 'Blocos have no ticket and no door. Which one, on which morning, with whom, is the whole problem.',
];

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
                /* Of those human sessions, how many were ours checking what a campaign visitor
                   sees. Kept on the row so the table stays the whole truth. */
                'selfcheck_human' => (int) ($r['selfcheck_human'] ?? 0),
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
            /* A check that had to use the real campaign name is taken out of the human count of an
               otherwise real row, rather than taking the whole row out. */
            if (!$r['internal']) $totals[$w]['human'] -= (int) $r[$w]['selfcheck_human'];
            $totals[$w]['internal_human'] += $r['internal']
                ? (int) $r[$w]['human']
                : (int) $r[$w]['selfcheck_human'];
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
function rmt_acq_daily(int $days = 90): array {
    $cc = rmt_acq_command_center($days);
    $real = array_values(array_filter($cc['rows'], static fn(array $r) => !$r['internal']));

    /* Top anything is top REAL anything. A verification campaign winning "top campaign" is how a
       dashboard starts lying to the person reading it. */
    $topSource = null;
    foreach ($real as $r) {
        $h = (int) $r['d7']['human'] - (int) $r['d7']['selfcheck_human'];
        if ($h <= 0) continue;
        if ($topSource === null || $h > $topSource[1]) $topSource = [$r['source'], $h];
    }
    $topCampaign = null;
    foreach ($real as $r) {
        if ($r['campaign'] === '') continue;
        $h = (int) $r['d7']['human'] - (int) $r['d7']['selfcheck_human'];
        if ($h <= 0) continue;
        if ($topCampaign === null || $h > $topCampaign[1]) $topCampaign = [$r['campaign'], $h];
    }
    /* Best conversion, not best volume, and only where there is a denominator worth dividing by.
       A single visit that signed up is 100% and means nothing. */
    $bestConv = null;
    foreach ($real as $r) {
        if ((int) $r['d7']['human'] - (int) $r['d7']['selfcheck_human'] < 5) continue;
        $rate = $r['d7']['visit_to_signup_pct'];
        if ($rate === null) continue;
        if ($bestConv === null || $rate > $bestConv['rate']) {
            $bestConv = ['source' => $r['source'], 'campaign' => $r['campaign'], 'rate' => $rate];
        }
    }

    $shape   = function_exists('rmt_traffic_shape') ? rmt_traffic_shape(1) : [];
    $shape7  = function_exists('rmt_traffic_shape') ? rmt_traffic_shape(7) : [];
    $landing = $shape['landing_destinations']['human'] ?? [];
    $topLanding = null;
    foreach ($landing as $slug => $n) { $topLanding = ['slug' => $slug, 'n' => (int) $n]; break; }

    $social = function_exists('rmt_social_counts') ? rmt_social_counts(7) : [];

    $d1 = $cc['totals']['d1']; $d7 = $cc['totals']['d7'];
    /* The one line worth reading first: is today different from the week it sits in. A day is a
       seventh of a week, so the week divided by seven is the only fair comparison. */
    $expected = $d7['human'] / 7;
    $change = $expected > 0 ? round(($d1['human'] - $expected) / $expected * 100) : null;

    return [
        'as_of' => gmdate('Y-m-d H:i') . ' UTC',
        /* Four classes, never folded into one another. Real is what is left after our own checks
           and the crawlers are taken out; uncertain stays visible rather than being split by
           guesswork. */
        'traffic' => [
            'real_human'  => ['today' => (int) $d1['human'], 'week' => (int) $d7['human']],
            'self_check'  => ['today' => (int) ($d1['internal_human'] ?? 0), 'week' => (int) ($d7['internal_human'] ?? 0)],
            'automated'   => ['week' => (int) ($shape7['sessions']['likely_automated'] ?? 0)],
            'uncertain'   => ['week' => (int) ($shape7['sessions']['uncertain'] ?? 0)],
        ],
        'human_visits'    => ['today' => (int) $d1['human'], 'week' => (int) $d7['human']],
        'signups'         => ['today' => (int) $d1['signups'], 'week' => (int) $d7['signups']],
        'confirmed'       => ['today' => (int) $d1['confirmed'], 'week' => (int) $d7['confirmed']],
        'trips'           => ['today' => (int) $d1['trips'], 'week' => (int) $d7['trips']],
        /* The steps past acquisition, because a visit that never becomes a connection has not done
           what this site exists for. Counted over the week, from the event table. */
        'matches_viewed'      => (int) ($social['overlapping_traveler_viewed'] ?? 0),
        'connection_requests' => (int) ($social['trip_connect_requested'] ?? 0),
        'connections_made'    => (int) ($social['trip_connect_accepted'] ?? 0),
        'messages_sent'       => (int) ($social['message_sent'] ?? 0),
        'top_real_source'   => $topSource ? $topSource[0] : null,
        'top_real_campaign' => $topCampaign ? $topCampaign[0] : null,
        'top_landing'       => $topLanding,
        'best_conversion'   => $bestConv,
        'notable_change'    => $change === null ? null : $change . '% against the weekly daily average',
        'our_own_checks_excluded' => (int) ($d7['internal_human'] ?? 0),
        'contaminated_window' => rmt_acq_window_is_contaminated(7),
        /* The milestone counter, and the only numbers allowed anywhere near it: everything since
           the marker existed, with our own checks and the crawlers already out. If that is zero it
           prints zero, which is the entire point of having it. */
        'clean' => rmt_acq_clean_totals(),
        'note' => 'Real human excludes automated traffic and our own verification. A rate with fewer '
                . 'than five human sessions behind it is not reported. Sessions before '
                . RMT_ACQ_CLEAN_FROM . ' UTC predate the self check marker and are our own traffic '
                . 'unless a post was published.',
    ];
}

/**
 * Everything since the self check marker existed, which is the only traffic we can honestly call
 * acquisition. Before that date our own verification ran under real campaign names and cannot be
 * separated, so it is not counted rather than guessed at.
 *
 * @return array<string,mixed>
 */
function rmt_acq_clean_totals(): array {
    $days = max(1, (int) ceil((time() - strtotime(RMT_ACQ_CLEAN_FROM)) / 86400));
    $human = 0; $direct = 0; $signups = 0; $confirmed = 0; $trips = 0;
    foreach (rmt_acq_report($days, RMT_ACQ_CLEAN_FROM) as $r) {
        if (rmt_acq_is_internal($r['campaign'] === '' ? null : $r['campaign'])) continue;
        $n = (int) $r['human'] - (int) ($r['selfcheck_human'] ?? 0);
        /* Direct is counted apart and NOT toward the milestone. On this site 778 sessions arrived
           direct in one day against 30 that passed the human test, and with nothing published there
           is no external link for a person to have followed. A session with no source we can name is
           indistinguishable from the automated floor, and a milestone that counts it is a milestone
           that congratulates us for crawlers. When a real link is published this will be obvious:
           the named channels will move and this number will not. */
        if ($r['source'] === 'direct' || $r['source'] === '') { $direct += max(0, $n); continue; }
        $human     += $n;
        $signups   += (int) $r['signed_up'];
        $confirmed += (int) $r['confirmed'];
        $trips     += (int) $r['trips'];
    }
    return [
        'since'       => RMT_ACQ_CLEAN_FROM . ' UTC',
        'days'        => $days,
        'human_visits'=> max(0, $human),
        'direct_human_not_counted' => $direct,
        'note' => 'Milestone 1 counts human visits carrying a channel we can name. Direct is shown '
                . 'beside it and not counted: with nothing published there is no external link for '
                . 'somebody to have followed, so a direct session cannot be told from the automated '
                . 'floor.',
        'signups'     => $signups,
        'confirmed'   => $confirmed,
        'trips'       => $trips,
        'milestones'  => [
            ['target' => 100, 'now' => max(0, $human), 'what' => 'genuine external human visits'],
            ['target' => 25,  'now' => $signups,       'what' => 'real signups'],
            ['target' => 10,  'now' => $trips,         'what' => 'real trips with dates'],
            /* Reading a table that may not exist yet must never take the dashboard down, which is
               the same rule the tracker itself follows. */
            ['target' => 1,   'now' => rmt_acq_accepted_connects(),
             'what' => 'two travelers who actually connected'],
        ],
    ];
}

/** Accepted connections, or zero if that table is not there yet. Never throws. */
function rmt_acq_accepted_connects(): int {
    try {
        return (int) (q_one('SELECT COUNT(*) c FROM trip_connects WHERE state = ?', ['accepted'])['c'] ?? 0);
    } catch (Throwable) {
        return 0;
    }
}

/**
 * The windows that are still ahead, for the events page.
 *
 * Built from the same list the campaign links use, so there is one place where a date lives and the
 * page cannot drift from the campaign. A window whose last day has passed drops off on its own.
 *
 * @return list<array<string,mixed>>
 */
function rmt_acq_upcoming_events(): array {
    $out = [];
    foreach (array_keys(RMT_ACQ_WINDOWS) as $campaign) {
        $w = rmt_acq_window($campaign);
        if ($w === null) continue;                       // closed, or a city we no longer hold
        $d = q_one('SELECT name FROM destinations WHERE id = ?', [(int) $w['id']]);
        $from = (int) strtotime($w['from']); $to = (int) strtotime($w['to']);
        $out[] = [
            'campaign'  => $campaign,
            'label'     => $w['label'],
            'slug'      => $w['slug'],
            'city'      => (string) ($d['name'] ?? $w['slug']),
            'from'      => $w['from'],
            'to'        => $w['to'],
            'dates'     => date('j F', $from) . ' to ' . date('j F Y', $to),
            'why'       => RMT_ACQ_WINDOW_WHY[$campaign] ?? '',
            'trip_link' => rmt_acq_trip_link($w),
        ];
    }
    usort($out, static fn(array $a, array $b) => strcmp($a['from'], $b['from']));
    return $out;
}
