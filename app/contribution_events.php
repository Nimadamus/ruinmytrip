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
];

/** Where an attempt began. Also a closed list: a free-text source is a source nobody can group by. */
const RMT_CONTRIB_SOURCES = [
    'place', 'destination', 'browse', 'contribute', 'profile', 'search', 'home', 'review', 'other',
];

/**
 * Why a publish did not happen. Operational reasons only -- never the content that failed.
 */
const RMT_CONTRIB_REASONS = ['validation', 'auth', 'verification', 'rate_limit', 'permission', 'duplicate', 'server', 'other'];

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
 * @param array{source?:string,place_id?:int,destination_id?:int,reason?:string} $ctx
 */
function rmt_track(string $event, array $ctx = []): void {
    if (!in_array($event, RMT_CONTRIB_EVENTS, true)) return;

    $source = (string) ($ctx['source'] ?? '');
    if (!in_array($source, RMT_CONTRIB_SOURCES, true)) $source = null;

    $reason = (string) ($ctx['reason'] ?? '');
    if (!in_array($reason, RMT_CONTRIB_REASONS, true)) $reason = null;

    try {
        q_run('INSERT INTO contribution_events
               (event, source, journey, place_id, destination_id, is_authed, reason, created_at)
               VALUES (?,?,?,?,?,?,?,?)',
              [$event, $source, rmt_journey_id(),
               !empty($ctx['place_id']) ? (int) $ctx['place_id'] : null,
               !empty($ctx['destination_id']) ? (int) $ctx['destination_id'] : null,
               function_exists('is_logged_in') && is_logged_in() ? 1 : 0,
               $reason, date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        // Measuring the funnel must never break the funnel.
    }
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
