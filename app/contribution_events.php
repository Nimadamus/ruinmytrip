<?php
declare(strict_types=1);

/**
 * The contribution funnel: where people give up between "I went there" and "it is published".
 *
 * This exists because the alternative is guessing. We are about to spend real effort on getting
 * the first traveler reviews, and every change to a CTA, a form or a signup step would otherwise be
 * an opinion. It answers a small number of specific questions and is built to answer nothing else:
 *
 *   - of the people who clicked "Write a review", how many reached the form?
 *   - how many of those had to sign up, and how many came back afterwards?
 *   - how many submitted, and how many of those actually published?
 *   - which surface -- a place page, a destination prompt, /contribute -- produces reviews?
 *
 * What it does not hold is as deliberate as what it does. No user id: "did a signed-in person
 * finish" is a flag, not an identity, and a table that cannot name anybody cannot leak anybody. No
 * IP, no user agent, no referrer, no review text. `journey` is a random token per session that
 * links one attempt's steps together; it identifies an attempt, not a person.
 */

/**
 * The events we record. A closed list, because an analytics table that accepts whatever a client
 * sends is a table that fills with junk and eventually with something we did not mean to store.
 */
const RMT_CONTRIB_EVENTS = [
    'review_cta_click',            // a "Write a review" control was clicked
    'contribute_search',           // somebody searched on /contribute
    'contribute_place_selected',   // ...and picked a place from the suggestions
    'review_form_start',           // the write form rendered for a signed-in user
    'review_signup_required',      // the form was asked for by somebody with no account
    'review_signup_completed',     // ...who then registered
    'review_login_completed',      // ...or signed in to an existing account
    'review_return_after_auth',    // ...and landed back on the form they wanted
    'review_submit_attempt',       // publish or save was pressed
    'review_verification_required',// publish held back pending email confirmation
    'review_publish_success',
    'review_publish_failure',
    'review_draft_restored',       // saved text was put back into an empty form
    'review_photo_added',
    'place_suggested',             // a place we do not have was suggested
    /* The signup funnel. The review funnel has been measured since it was built and the JOIN
       funnel never was, which is the wrong way round for a site whose stated problem is that it
       has no members: every change to the front door was an opinion with nothing behind it. */
    'join_view',                   // the join form was rendered
    'join_submit',                 // create account was pressed
    'join_failure',                // ...and refused, with a reason
    'join_created',                // an account now exists
    'join_confirmed',              // the email address was confirmed
    'join_first_action',           // dates, a room, a follow or a first post, in the first session
    /* The top of the funnel, and the only step with no row of its own behind it: a visit
       leaves nothing in the product to count. Recorded once per session, never per request,
       and it carries no address, agent or referrer -- it is an arrival, not a visitor. */
    'landing_view',                // the public front page was rendered for somebody signed out
    /* The social funnel, added 2026-09-15. The review funnel measures somebody writing something
       finished; this measures the loop the product is actually built around: land on a city, touch
       it, join, follow it, post a trip, ask a question, come back. Every one of these is recorded
       by the server from a thing that happened, except the two marked, which only the browser can
       see. */
    'destination_page_view',       // a city page was rendered, once per city per session
    'destination_return_visit',    // ...by a browser that had been here on an earlier visit
    'destination_follow_click',    // follow was pressed
    'destination_follow_success',  // ...and the city is now followed
    'ask_question_click',          // the city composer was focused (browser)
    'question_posted',             // a post was published with a city on it
    'post_created',                // any post was published, city or not
    'comment_created',
    'reaction_created',            // a like or a save
    'login_completed',
    'profile_viewed',
    'traveler_profile_clicked',    // ...arrived at from a discovery surface rather than anywhere
    'profile_edit_started',
    'profile_completed',           // saved with a name, a few words and a home city on it
    'trip_create_started',
    'trip_created',
    'overlapping_traveler_viewed', // a page of people whose dates cross the reader's was rendered
    'message_started',             // a first message to somebody, never the message
    /* The overlap loop, added 2026-09-15. A trip lands on somebody else's dates, they are told,
       they look, and they decide whether they want to meet. Five events, one per step, and the
       last two are the only ones that say anybody agreed to anything. */
    'overlap_notification_created',
    'overlap_notification_viewed',
    'overlap_profile_opened',
    'trip_connect_requested',
    'trip_connect_accepted',
    /* Messaging, 2026-09-15. Both of these take no arguments, on purpose: the simplest possible
       guarantee that no word of a private message can reach an analytics table is that there is
       nowhere to put one. 'message_started' already counts a conversation beginning. */
    'message_sent',
    'message_thread_viewed',
    // Written by app/buddies.php since the buddy feature shipped and dropped here every time,
    // because it was never added to this list. Nothing about a buddy post was ever counted.
    'buddy_post_created',
    /* Trip first, 2026-09-25. The visitor states the trip before being asked for an account, and
       each step is its own row so the drop between any two is a number, not an impression. */
    'human_interaction',           // browser: first tap, key or scroll of a visit (one bit, nothing else)
    'cta_click',                   // browser: a social call to action was pressed, detail = which
    'plan_view',                   // the trip first form was rendered
    'plan_started',                // browser: somebody put a value into it
    'plan_submitted',              // a valid trip came back
    'plan_signup_view',            // ...and was shown the account step with the trip held
];

/** The calls to action cta_click may name. Closed, like everything else here. */
const RMT_CTA_KEYS = [
    'cta_dates', 'cta_buddy', 'cta_ask', 'cta_avoid', 'cta_ruined', 'cta_review', 'cta_travelers',
    'cta_community', 'cta_prompt', 'home_plan', 'home_buddy', 'buddy_landing', 'travelers_hub',
    'nav_plan', 'feed_plan',
    // The banner on a page reached from one of our social posts (app/social_landing.php).
    'social_answer', 'social_plan',
];

/** Where an attempt began. Also a closed list: a free-text source is a source nobody can group by. */
const RMT_CONTRIB_SOURCES = [
    'place', 'destination', 'browse', 'contribute', 'profile', 'search', 'home', 'review', 'feed',
    // The surfaces a signup can come from, so "which page recruits" is a question with an answer.
    'travelers', 'going', 'meetups', 'talk', 'blog', 'matches', 'invite',
    // The trip composer, which is its own surface: somebody posting dates is not on a city page.
    'trip',
    // The trip first form, and the buddy pages that send people to it.
    'plan', 'buddies',
    'other',
];

/**
 * Why a publish did not happen. Operational reasons only -- never the content that failed.
 */
const RMT_CONTRIB_REASONS = ['validation', 'auth', 'verification', 'rate_limit', 'permission', 'duplicate', 'server', 'other'];

/**
 * Is this request a crawler?
 *
 * Measured, not guessed at: the join form showed 996 views against 2 submissions in thirty days,
 * which is not a conversion problem, it is a page that search engines like to visit. A funnel whose
 * denominator is mostly robots reports a catastrophe every week and teaches you to ignore it.
 *
 * The user agent is READ here and never stored. That distinction is the whole point: deciding not
 * to write a row needs the string for the length of one comparison, and keeping it would turn an
 * aggregate counter into a record of who visited. Nothing below reaches the database.
 *
 * Deliberately a substring match on the words crawlers put in their own agents rather than a list of
 * known bots. A list goes stale; "bot", "spider" and "crawl" do not, and anything that lies about
 * being a crawler was going to be counted by any method.
 */
function rmt_is_crawler(): bool {
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') return true;        // no agent at all is a script, not a traveler
    /* The link preview fetchers of the platforms we post on. None of them says "bot": Facebook
       identifies as facebookexternalhit and meta-externalagent, and WhatsApp as WhatsApp/. They were
       first noticed on 2026-09-16, when composing a Page post made Facebook fetch the link once per
       character typed, writing campaign names like "oktoberf" and "new-yea" into the channel table.
       They never counted as human, so no conversion number was wrong, but a campaign table full of
       fragments is its own kind of lie. */
    foreach (['bot', 'spider', 'crawl', 'slurp', 'headless', 'preview', 'fetcher',
              'monitor', 'curl/', 'wget', 'python-requests', 'okhttp', 'java/',
              'externalhit', 'externalagent', 'facebookcatalog', 'whatsapp/'] as $mark) {
        if (str_contains($ua, $mark)) return true;
    }
    return false;
}

/**
 * The token tying one attempt's steps together.
 *
 * Session-scoped and random. It is not derived from anything about the person, it is never shown,
 * and it is rotated after a publish so the next review is counted as a new attempt rather than a
 * continuation of the last one.
 */
function rmt_journey_id(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) return '';
    if (empty($_SESSION['_journey'])) {
        $_SESSION['_journey'] = bin2hex(random_bytes(8));
    }
    return (string) $_SESSION['_journey'];
}

/**
 * The token that outlives the session, so a second visit can be recognised as one.
 *
 * Sixteen random hex characters in a first party cookie. It is not derived from the person in any
 * way: not their address, not their agent, not a fingerprint, not a hash of any of those. It is
 * random bytes, so it cannot be recomputed from somebody, only recognised when the same browser
 * sends it back, and it is never joined to an account because this table holds no account.
 *
 * Returns an empty string when the response has already started, which is the only case where a
 * cookie cannot be set. A missing visitor token loses one number on a dashboard; a warning printed
 * into the middle of a page loses the page.
 */
const RMT_VISITOR_COOKIE = 'rmt_v';
const RMT_VISITOR_TTL    = 180 * 86400;

function rmt_visitor_id(): string {
    /* Cached per request, in a global rather than a static: one request serves one browser, and a
       test that simulates the next request has to be able to clear it. */
    if (isset($GLOBALS['_rmt_visitor_id'])) return (string) $GLOBALS['_rmt_visitor_id'];

    $seen = (string) ($_COOKIE[RMT_VISITOR_COOKIE] ?? '');
    $known = (bool) preg_match('/^[a-f0-9]{16}$/', $seen);
    /* Decided once per session and remembered there, which is the difference between "this
       browser has been here before" and "this browser has loaded a page before". The cookie is
       written on the first page of a first visit, so by the second page of that same first visit
       it is present and every request after it would otherwise report a returning visitor. That
       is not somebody coming back, it is somebody still here. */
    if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION['_v_returning'])) {
        $_SESSION['_v_returning'] = $known ? 1 : 0;
    }
    if ($known) return $GLOBALS['_rmt_visitor_id'] = $seen;

    $fresh = bin2hex(random_bytes(8));
    /* Minted here, which means the client did not send one. The flag survives the line below that
       puts it into $_COOKIE so the rest of the request agrees with itself, because otherwise every
       first page of every visit would look like a returning browser to the check above. */
    $GLOBALS['_rmt_visitor_minted'] = true;
    if (!headers_sent()) {
        setcookie(RMT_VISITOR_COOKIE, $fresh, [
            'expires'  => time() + RMT_VISITOR_TTL, 'path' => '/',
            'httponly' => true, 'samesite' => 'Lax',
            'secure'   => (string) (function_exists('cfg') ? cfg('app_env') : '') === 'production',
        ]);
        $_COOKIE[RMT_VISITOR_COOKIE] = $fresh;   // so the rest of this request agrees with itself
    }
    return $GLOBALS['_rmt_visitor_id'] = $fresh;
}

/**
 * Did this request arrive carrying a token we had already issued?
 *
 * The one signal that separates a browser from a fetcher without keeping anything about the
 * person: we set the cookie on the first response of every visit, a browser sends it back on the
 * next request, and almost no crawler does. It is recorded per event so a session can be read
 * afterwards as "returned state at least once" or "never returned state at all", and only the
 * second of those is evidence, because a real person's first page has no cookie either.
 */
function rmt_visitor_presented_cookie(): bool {
    return (bool) preg_match('/^[a-f0-9]{16}$/', (string) ($_COOKIE[RMT_VISITOR_COOKIE] ?? ''))
        && !($GLOBALS['_rmt_visitor_minted'] ?? false);
}

/** Was this browser here before this session started? False for one we have never seen. */
function rmt_visitor_is_returning(): bool {
    rmt_visitor_id();
    if (session_status() === PHP_SESSION_ACTIVE) return (int) ($_SESSION['_v_returning'] ?? 0) === 1;
    return (bool) preg_match('/^[a-f0-9]{16}$/', (string) ($_COOKIE[RMT_VISITOR_COOKIE] ?? ''));
}

/** Start a fresh attempt. Called after a publish, so the funnel counts attempts and not sessions. */
function rmt_journey_rotate(): void {
    if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['_journey'] = bin2hex(random_bytes(8));
}

/**
 * Record one funnel event.
 *
 * Never throws and never blocks: an analytics row is not worth a failed review. An unknown event
 * name is dropped rather than stored, so a stale client or a hostile one cannot define new columns
 * of meaning in this table by accident.
 *
 * Returns whether a row was actually written. That return value is load bearing: rmt_track_once()
 * used to spend the session's one slot for an event BEFORE finding out the row was dropped, so a
 * crawler visit, or any other refusal, silently deafened the rest of that session to that event.
 * Measured the hard way: three pages instrumented correctly recorded nothing at all, because a
 * headless browser had opened them earlier in the same session and burned the marker.
 *
 * @param array{source?:string,place_id?:int,destination_id?:int,reason?:string} $ctx
 */
function rmt_track(string $event, array $ctx = []): bool {
    if (!in_array($event, RMT_CONTRIB_EVENTS, true)) return false;
    if (rmt_is_crawler()) return false;

    $source = (string) ($ctx['source'] ?? '');
    if (!in_array($source, RMT_CONTRIB_SOURCES, true)) $source = null;

    $reason = (string) ($ctx['reason'] ?? '');
    if (!in_array($reason, RMT_CONTRIB_REASONS, true)) $reason = null;

    try {
        /* The channel that brought this visit, decided on first touch and held. One word from a
           list this code owns; no referrer, no address, no agent. See app/acquisition.php. */
        $acq = function_exists('rmt_acq_current')
            ? rmt_acq_current()
            : ['source' => null, 'medium' => null, 'campaign' => null, 'content' => null];
        $cols = ['event', 'source', 'journey', 'visitor', 'cookied', 'place_id', 'destination_id', 'is_authed',
                 'reason', 'acq_source', 'acq_medium', 'acq_campaign', 'acq_content', 'created_at'];
        $vals = [$event, $source, rmt_journey_id(), rmt_visitor_id(),
               rmt_visitor_presented_cookie() ? 1 : 0,
               !empty($ctx['place_id']) ? (int) $ctx['place_id'] : null,
               !empty($ctx['destination_id']) ? (int) $ctx['destination_id'] : null,
               function_exists('is_logged_in') && is_logged_in() ? 1 : 0,
               $reason,
               $acq['source'], $acq['medium'], $acq['campaign'], $acq['content'],
               date('Y-m-d H:i:s')];
        /* Named only when there is something to put in them, so a row that has neither is written
           exactly as it was before migration 102. */
        $path = rmt_track_path((string) ($ctx['path'] ?? ''));
        if ($path !== null) { $cols[] = 'path'; $vals[] = $path; }
        $detail = (string) ($ctx['detail'] ?? '');
        if ($detail !== '' && in_array($detail, RMT_CTA_KEYS, true)) { $cols[] = 'detail'; $vals[] = $detail; }
        q_run('INSERT INTO contribution_events (' . implode(', ', $cols) . ') VALUES ('
              . implode(',', array_fill(0, count($cols), '?')) . ')', $vals);
        return true;
    } catch (Throwable $e) {
        // Measuring the funnel must never break the funnel.
        return false;
    }
}

/**
 * A landing path fit to store: the path alone, never a query string or a fragment, which is where
 * tokens and search terms live. Letters, digits and the few characters our own routes use.
 */
function rmt_track_path(string $p): ?string {
    if ($p === '') return null;
    $p = (string) strtok($p, '?#');
    if ($p === '' || $p[0] !== '/') return null;
    $p = preg_replace('#[^\p{L}\p{N}/_\-.]+#u', '', $p) ?? '';
    return $p === '' ? null : mb_substr($p, 0, 160);
}

/**
 * Record an event at most once per session.
 *
 * For the steps where a repeat is noise rather than signal. A visitor who reloads the front page
 * four times is one arrival, and the honest way to say so is to write one row, rather than to write
 * four and hope every reader of the table remembers to count distinct journeys.
 */
function rmt_track_once(string $event, array $ctx = []): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { rmt_track($event, $ctx); return; }
    $seen = $_SESSION['_tracked'] ?? [];
    if (isset($seen[$event])) return;
    // Marked only if it was written. See rmt_track() for why that order matters.
    if (!rmt_track($event, $ctx)) return;
    $seen[$event] = 1;
    $_SESSION['_tracked'] = $seen;
}

/**
 * Record an event at most once per session PER KEY.
 *
 * rmt_track_once() keys on the event alone, which is right for "the front page was seen" and wrong
 * for "a city page was seen": the first city somebody opened would be the only one ever counted,
 * and every other city would read as having no traffic. The key here is the city.
 */
function rmt_track_once_for(string $event, string $key, array $ctx = []): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { rmt_track($event, $ctx); return; }
    $slot = $event . ':' . $key;
    $seen = $_SESSION['_tracked_keyed'] ?? [];
    if (isset($seen[$slot])) return;
    /* A session that opened two hundred city pages would otherwise carry two hundred keys in the
       cookie jar forever. Keep the most recent fifty, which is far more than any real reading
       session and small enough to stay a rounding error in the session file. */
    if (count($seen) > 50) $seen = array_slice($seen, -25, null, true);
    if (!rmt_track($event, $ctx)) return;
    $seen[$slot] = 1;
    $_SESSION['_tracked_keyed'] = $seen;
}

/**
 * Distinct attempts that produced each event in a window.
 *
 * Counted by journey rather than by row: somebody who clicks "Write a review" three times before
 * the page loads is one person trying once, and a funnel that counted the clicks would report a
 * drop-off that never happened.
 *
 * @return array<string,int> event => attempts
 */
function rmt_funnel_counts(int $days = 30): array {
    $since = rmt_funnel_since($days);
    $out = array_fill_keys(RMT_CONTRIB_EVENTS, 0);
    foreach (q_all("SELECT event, COUNT(DISTINCT COALESCE(journey, CAST(id AS TEXT))) c
                      FROM contribution_events WHERE created_at >= ? GROUP BY event", [$since]) as $r) {
        $out[(string) $r['event']] = (int) $r['c'];
    }
    return $out;
}

/** The window's lower bound. 0 days means everything ever recorded. */
function rmt_funnel_since(int $days): string {
    return $days <= 0 ? '0000-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
}

/**
 * How many attempts reached publication, split by whether they began signed in.
 *
 * The split matters more than the total: an anonymous attempt has two extra steps in front of it,
 * and lumping the two together hides whichever one is broken.
 *
 * @return array{anonymous:array{started:int,published:int}, authed:array{started:int,published:int}}
 */
function rmt_funnel_by_auth(int $days = 30): array {
    $since = rmt_funnel_since($days);
    $rows = q_all("SELECT journey,
                          MAX(CASE WHEN event = 'review_publish_success' THEN 1 ELSE 0 END) published,
                          MIN(is_authed) started_authed
                     FROM contribution_events
                    WHERE created_at >= ? AND journey IS NOT NULL AND journey <> ''
                    GROUP BY journey", [$since]);
    $out = ['anonymous' => ['started' => 0, 'published' => 0], 'authed' => ['started' => 0, 'published' => 0]];
    foreach ($rows as $r) {
        $k = ((int) $r['started_authed']) === 1 ? 'authed' : 'anonymous';
        $out[$k]['started']++;
        if ((int) $r['published'] === 1) $out[$k]['published']++;
    }
    return $out;
}

/**
 * Which surface an attempt started from, and how many of those attempts published.
 *
 * This is the question "which CTA placement actually produces reviews", which is not the same as
 * "which CTA gets clicked" and is frequently a different answer.
 *
 * @return list<array{source:string,attempts:int,published:int}>
 */
function rmt_funnel_by_source(int $days = 30): array {
    $since = rmt_funnel_since($days);
    // The source is only on the events that have one; the publish event does not. Filtering the
    // whole journey down to rows WITH a source therefore threw the publish away and reported every
    // successful attempt as unpublished -- the one number this view exists to give. So: take the
    // source from wherever it appears in the journey, and take the outcome from the whole journey.
    $rows = q_all("SELECT j.journey,
                          (SELECT MIN(e2.source) FROM contribution_events e2
                            WHERE e2.journey = j.journey AND e2.source IS NOT NULL) source,
                          MAX(CASE WHEN j.event = 'review_publish_success' THEN 1 ELSE 0 END) published
                     FROM contribution_events j
                    WHERE j.created_at >= ? AND j.journey IS NOT NULL AND j.journey <> ''
                    GROUP BY j.journey", [$since]);
    $agg = [];
    foreach ($rows as $r) {
        $src = (string) ($r['source'] ?? '');
        if ($src === '') continue;      // an attempt with no surface recorded tells us nothing here
        if (!isset($agg[$src])) $agg[$src] = ['source' => $src, 'attempts' => 0, 'published' => 0];
        $agg[$src]['attempts']++;
        if ((int) $r['published'] === 1) $agg[$src]['published']++;
    }
    usort($agg, static fn($a, $b) => [$b['published'], $b['attempts']] <=> [$a['published'], $a['attempts']]);
    return array_values($agg);
}

/** Why publishes failed, most common first. Operational reasons only. */
function rmt_funnel_failures(int $days = 30): array {
    $since = rmt_funnel_since($days);
    return q_all("SELECT COALESCE(reason, 'other') reason, COUNT(*) n
                    FROM contribution_events
                   WHERE event = 'review_publish_failure' AND created_at >= ?
                   GROUP BY COALESCE(reason, 'other') ORDER BY n DESC", [$since]);
}

/**
 * The funnel as a list of steps, each with how many attempts reached it.
 *
 * Ordered the way the journey actually happens so a drop can be read off the page rather than
 * reconstructed. Steps that apply to only some attempts (signup, verification) are marked, because
 * reading them as a straight-line loss would be wrong.
 *
 * @return list<array{key:string,label:string,count:int,branch:bool}>
 */
function rmt_funnel_steps(int $days = 30): array {
    $c = rmt_funnel_counts($days);
    $step = static fn(string $k, string $label, bool $branch = false): array =>
        ['key' => $k, 'label' => $label, 'count' => 0, 'branch' => $branch];

    $steps = [
        $step('review_cta_click', 'Clicked write a review'),
        $step('review_form_start', 'Reached the form'),
        $step('review_signup_required', 'Needed an account', true),
        $step('review_return_after_auth', 'Came back after signing in', true),
        $step('review_submit_attempt', 'Pressed publish'),
        $step('review_verification_required', 'Held for email confirmation', true),
        $step('review_publish_success', 'Published'),
    ];
    foreach ($steps as &$s) $s['count'] = (int) ($c[$s['key']] ?? 0);
    unset($s);
    return $steps;
}

/**
 * Which surface sent somebody to the join form.
 *
 * Read from the return path the form already carries, because that is the page they were on when
 * they decided an account was worth it. Anything unrecognised is 'other' rather than a guess: a
 * source nobody can group by is worse than no source.
 */
function rmt_join_source(string $return): string {
    $path = (string) (parse_url($return, PHP_URL_PATH) ?: '');
    if ($path === '') return 'other';
    if (preg_match('#^/d/[a-z0-9\-]+/travelers$#', $path)) return 'travelers';
    if (preg_match('#^/d/[a-z0-9\-]+#', $path))            return 'destination';
    /* The campaign path. It was falling through to 'other', so the surface that recruits most of
       the people a campaign sends could not be told from anything else. */
    if ($path === '/trip/new' || str_starts_with($path, '/trip/')) return 'trip';
    if ($path === '/going')                                 return 'going';
    if ($path === '/matches')                               return 'matches';
    if ($path === '/meetups' || str_starts_with($path, '/meetup/')) return 'meetups';
    if ($path === '/talk' || str_starts_with($path, '/post/'))      return 'talk';
    if (str_starts_with($path, '/blog'))                    return 'blog';
    if (str_starts_with($path, '/p/'))                      return 'place';
    if (str_starts_with($path, '/review'))                  return 'review';
    if ($path === '/invite')                                return 'invite';
    if ($path === '/')                                      return 'home';
    if ($path === '/travelers')                             return 'travelers';
    if (str_starts_with($path, '/buddies') || str_starts_with($path, '/buddy/')) return 'buddies';
    return 'other';
}

/**
 * The join funnel: how many saw the form, how many finished, how many confirmed, how many did
 * anything at all afterwards, and which page each of them came from.
 *
 * Counted by journey like the rest of this table, so one person reloading the form twice is one
 * person. Sources are only counted on the view, since that is the step that knows where they were.
 *
 * @return array{steps:array<string,int>, by_source:array<string,array{views:int,created:int}>}
 */
function rmt_signup_funnel(int $days = 30): array {
    $since = rmt_funnel_since($days);
    $steps = [];
    foreach (['join_view', 'join_submit', 'join_created', 'join_confirmed', 'join_first_action', 'join_failure'] as $e) {
        $steps[$e] = 0;
    }
    foreach (q_all("SELECT event, COUNT(DISTINCT COALESCE(journey, CAST(id AS TEXT))) c
                      FROM contribution_events
                     WHERE created_at >= ? AND event LIKE 'join_%' GROUP BY event", [$since]) as $r) {
        $steps[(string) $r['event']] = (int) $r['c'];
    }

    $by = [];
    foreach (q_all("SELECT COALESCE(source,'other') s, event,
                           COUNT(DISTINCT COALESCE(journey, CAST(id AS TEXT))) c
                      FROM contribution_events
                     WHERE created_at >= ? AND event IN ('join_view','join_created')
                  GROUP BY COALESCE(source,'other'), event", [$since]) as $r) {
        $src = (string) $r['s'];
        $by[$src] ??= ['views' => 0, 'created' => 0];
        $by[$src][$r['event'] === 'join_created' ? 'created' : 'views'] = (int) $r['c'];
    }
    // Busiest first, and deterministic when two pages tie: the one that actually converted leads,
    // so a tie in a quiet week is not reported in whatever order the database happened to group.
    uasort($by, static fn(array $x, array $y) => [$y['views'], $y['created']] <=> [$x['views'], $x['created']]);
    return ['steps' => $steps, 'by_source' => $by];
}

/* ------------------------------------------------------------------------- *
 * The social funnel, 2026-09-15.
 *
 * The question this answers, in one line: somebody lands on a city page, and what share of them
 * touch it, join, follow it, post a trip, ask something, and come back?
 *
 * Counted by journey, like everything else here, except the two places where the honest unit is a
 * browser rather than an attempt: unique and returning visitors. Both are floors, because clearing
 * cookies makes somebody new again, and a floor stated as a floor is worth more than a precise
 * number built out of the person.
 * ------------------------------------------------------------------------- */

/**
 * The landing to return funnel, as ordered steps with the attempts that reached each.
 *
 * "Touched it" is deliberately any of follow, ask, reaction or comment rather than a single event:
 * the page offers several first touches and which one somebody uses is a later question than
 * whether they used any.
 *
 * @return list<array{key:string,label:string,count:int,note:string}>
 */
function rmt_social_funnel(int $days = 30): array {
    $since = rmt_funnel_since($days);
    $interact = "('destination_follow_click','ask_question_click','reaction_created','comment_created')";
    $rows = q_all("SELECT journey,
                     MAX(CASE WHEN event = 'destination_page_view' THEN 1 ELSE 0 END) landed,
                     MAX(CASE WHEN event IN $interact THEN 1 ELSE 0 END) interacted,
                     MAX(CASE WHEN event = 'join_submit' THEN 1 ELSE 0 END) signup_started,
                     MAX(CASE WHEN event = 'join_created' THEN 1 ELSE 0 END) signup_done,
                     MAX(CASE WHEN event = 'destination_follow_success' THEN 1 ELSE 0 END) followed,
                     MAX(CASE WHEN event = 'trip_created' THEN 1 ELSE 0 END) tripped,
                     MAX(CASE WHEN event IN ('question_posted','post_created') THEN 1 ELSE 0 END) posted,
                     MAX(CASE WHEN event = 'destination_return_visit' THEN 1 ELSE 0 END) returned
                   FROM contribution_events
                  WHERE created_at >= ? AND journey IS NOT NULL AND journey <> ''
                  GROUP BY journey", [$since]);

    $n = ['landed' => 0, 'interacted' => 0, 'signup_started' => 0, 'signup_done' => 0,
          'followed' => 0, 'tripped' => 0, 'posted' => 0, 'returned' => 0];
    foreach ($rows as $r) {
        foreach (array_keys($n) as $k) if ((int) $r[$k] === 1) $n[$k]++;
    }
    return [
        ['key' => 'landed',         'label' => 'Landed on a destination', 'count' => $n['landed'],         'note' => ''],
        ['key' => 'interacted',     'label' => 'Touched it',              'count' => $n['interacted'],     'note' => 'follow, ask, react or comment'],
        ['key' => 'signup_started', 'label' => 'Started signing up',      'count' => $n['signup_started'], 'note' => ''],
        ['key' => 'signup_done',    'label' => 'Finished signing up',     'count' => $n['signup_done'],    'note' => ''],
        ['key' => 'followed',       'label' => 'Followed a destination',  'count' => $n['followed'],       'note' => ''],
        ['key' => 'tripped',        'label' => 'Created a trip',          'count' => $n['tripped'],        'note' => ''],
        ['key' => 'posted',         'label' => 'Posted or asked',         'count' => $n['posted'],         'note' => ''],
        ['key' => 'returned',       'label' => 'Came back later',         'count' => $n['returned'],       'note' => 'a browser we had seen on an earlier visit'],
    ];
}

/**
 * Browsers, not attempts: how many distinct ones were seen, and how many of those had been here
 * before. Both are floors. A browser with cookies cleared is a new one to us and there is no
 * honest way around that which does not involve identifying the person.
 *
 * @return array{unique:int,returning:int}
 */
function rmt_visitor_counts(int $days = 30): array {
    $since = rmt_funnel_since($days);
    $u = (int) (q_one("SELECT COUNT(DISTINCT visitor) c FROM contribution_events
                        WHERE created_at >= ? AND visitor IS NOT NULL AND visitor <> ''", [$since])['c'] ?? 0);
    $r = (int) (q_one("SELECT COUNT(DISTINCT visitor) c FROM contribution_events
                        WHERE created_at >= ? AND event = 'destination_return_visit'
                          AND visitor IS NOT NULL AND visitor <> ''", [$since])['c'] ?? 0);
    return ['unique' => $u, 'returning' => $r];
}

/**
 * The cities people actually do something in, busiest first.
 *
 * Views and actions are separate columns rather than one score, because a city with a thousand
 * views and no follows is a different problem from a city with fifty of each, and a single number
 * would hide which one we have.
 *
 * @return list<array{destination_id:int,name:string,slug:string,views:int,follows:int,questions:int,acts:int}>
 */
function rmt_top_communities(int $days = 30, int $limit = 12): array {
    $since = rmt_funnel_since($days);
    $rows = q_all("SELECT destination_id,
                          COUNT(DISTINCT CASE WHEN event = 'destination_page_view' THEN journey END) views,
                          COUNT(DISTINCT CASE WHEN event = 'destination_follow_success' THEN journey END) follows,
                          COUNT(DISTINCT CASE WHEN event = 'question_posted' THEN journey END) questions
                     FROM contribution_events
                    WHERE created_at >= ? AND destination_id IS NOT NULL
                    GROUP BY destination_id", [$since]);
    $out = [];
    foreach ($rows as $r) {
        $d = q_one('SELECT name, slug FROM destinations WHERE id = ?', [(int) $r['destination_id']]);
        if (!$d) continue;      // a city that has since been removed is not a row worth printing
        $out[] = ['destination_id' => (int) $r['destination_id'],
                  'name' => (string) $d['name'], 'slug' => (string) $d['slug'],
                  'views' => (int) $r['views'], 'follows' => (int) $r['follows'],
                  'questions' => (int) $r['questions'],
                  'acts' => (int) $r['follows'] + (int) $r['questions']];
    }
    usort($out, static fn(array $a, array $b) => [$b['acts'], $b['views']] <=> [$a['acts'], $a['views']]);
    return array_slice($out, 0, $limit);
}

/**
 * Which city was on screen when somebody signed up.
 *
 * This is the attribution question, and it is answered without following anybody anywhere: the
 * journey token already links the steps of one session, so the city viewed in the same session as
 * the account creation is the city that recruited them. No referrer is stored, no identity is
 * involved, and nothing leaves this site.
 *
 * @return list<array{name:string,slug:string,signups:int}>
 */
function rmt_signup_attribution(int $days = 30, int $limit = 12): array {
    $since = rmt_funnel_since($days);
    $joined = q_all("SELECT DISTINCT journey FROM contribution_events
                      WHERE created_at >= ? AND event = 'join_created'
                        AND journey IS NOT NULL AND journey <> ''", [$since]);
    $tally = [];
    foreach ($joined as $j) {
        $d = q_one("SELECT destination_id FROM contribution_events
                     WHERE journey = ? AND destination_id IS NOT NULL
                       AND event IN ('destination_page_view','destination_follow_success','question_posted',
                                     'plan_view','plan_submitted')
                     ORDER BY id LIMIT 1", [(string) $j['journey']]);
        if (!$d) continue;
        $id = (int) $d['destination_id'];
        $tally[$id] = ($tally[$id] ?? 0) + 1;
    }
    $rows = [];
    foreach ($tally as $id => $n) {
        $d = q_one('SELECT name, slug FROM destinations WHERE id = ?', [$id]);
        if ($d) $rows[] = ['name' => (string) $d['name'], 'slug' => (string) $d['slug'], 'signups' => $n];
    }
    usort($rows, static fn(array $a, array $b) => $b['signups'] <=> $a['signups']);
    return array_slice($rows, 0, $limit);
}

/**
 * The plain counters the dashboard leads with, in one pass.
 *
 * @return array<string,int>
 */
function rmt_social_counts(int $days = 30): array {
    $c = rmt_funnel_counts($days);
    $keys = ['destination_page_view','destination_follow_click','destination_follow_success',
             'ask_question_click','question_posted','post_created','comment_created','reaction_created',
             'join_view','join_submit','join_created','login_completed','trip_create_started','trip_created',
             'profile_viewed','traveler_profile_clicked','profile_edit_started','profile_completed',
             'overlapping_traveler_viewed','message_started','destination_return_visit',
             /* The overlap and messaging events. These were added in later tasks and this list was
                not updated with them, so the dashboard's counter block silently omitted every
                number about connecting and messaging. Found by reading the JSON rather than the
                page: a missing key is invisible on a dashboard and loud in a parser. */
             'overlap_notification_created','overlap_notification_viewed','overlap_profile_opened',
             'trip_connect_requested','trip_connect_accepted','message_sent','message_thread_viewed'];
    $out = [];
    foreach ($keys as $k) $out[$k] = (int) ($c[$k] ?? 0);
    return $out;
}

/**
 * Which surface each session was on, as the closed vocabulary this table stores.
 *
 * This is NOT a referrer report and cannot become one: `source` is a word out of a fixed list,
 * derived from an internal path, and no external referrer, campaign tag or address has ever been
 * collected. So it answers "which of our own pages were people on", and cannot answer "did they
 * come from Google", which only Search Console can.
 *
 * @return array<string,int> source => sessions
 */
function rmt_funnel_sources(int $days = 30): array {
    $out = [];
    foreach (q_all("SELECT COALESCE(source, 'not recorded') s, COUNT(DISTINCT journey) c
                      FROM contribution_events WHERE created_at >= ?
                  GROUP BY COALESCE(source, 'not recorded')", [rmt_funnel_since($days)]) as $r) {
        $out[(string) $r['s']] = (int) $r['c'];
    }
    arsort($out);
    return $out;
}
