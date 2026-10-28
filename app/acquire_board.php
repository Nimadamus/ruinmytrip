<?php
declare(strict_types=1);

/**
 * The acquisition scoreboard.
 *
 * /cron/funnel groups every page view in PHP and a 31 day read of it times out, so the morning
 * file cannot say which post produced a signup. This reads only the actions that matter
 * (an account, a finished profile, a review, a buddy post, a trip) and groups them by the
 * utm source, campaign and content stored on that action. First touch is written onto the
 * event by rmt_track(). Our own checks (utm_content=selfcheck) are left out.
 *
 * Product totals come from the tables, not from the event stream, so a row written before
 * tracking existed is still counted. Nothing here writes.
 */

const RMT_ACQUIRE_EVENTS = [
    'join_created',
    'profile_completed',
    'review_publish_success',
    'review_held_for_join',
    'buddy_post_created',
    'trip_created',
];

/** @return array{since:string,days:int,events:list<array{source:string,campaign:string,content:string,event:string,n:int}>,product:array<string,?int>,errors:list<string>,note:string} */
function rmt_acquire_board(int $days = 31): array {
    $days = min(max($days, 1), 31);
    $since = rmt_funnel_since($days);
    $events = [];
    $errors = [];
    try {
        $in = implode(',', array_fill(0, count(RMT_ACQUIRE_EVENTS), '?'));
        $rows = q_all(
            "SELECT COALESCE(acq_source, 'direct') src,
                    COALESCE(acq_campaign, '') campaign,
                    COALESCE(acq_content, '') content,
                    event,
                    COUNT(*) n
               FROM contribution_events
              WHERE created_at >= ?
                AND event IN ($in)
                AND COALESCE(acq_content, '') <> 'selfcheck'
           GROUP BY 1, 2, 3, 4
           ORDER BY n DESC, src, campaign, content, event",
            array_merge([$since], RMT_ACQUIRE_EVENTS));
        foreach ($rows as $r) {
            $events[] = [
                'source'   => (string) $r['src'],
                'campaign' => (string) $r['campaign'],
                'content'  => (string) $r['content'],
                'event'    => (string) $r['event'],
                'n'        => (int) $r['n'],
            ];
        }
    } catch (Throwable $e) {
        $events = [];
        $errors = ['events'];
    }

    $real = rmt_sc_real_user_sql('u');
    $count = static function (string $sql, array $args, string $name) use (&$errors): ?int {
        try { return (int) (q_one($sql, $args)['c'] ?? 0); }
        catch (Throwable $e) { $errors[] = $name; return null; }
    };
    $product = [
        /* Window: accounts created since $since. */
        'signups' => $count("SELECT COUNT(*) c FROM users u WHERE $real AND u.created_at >= ?", [$since], 'signups'),
        'confirmed' => $count("SELECT COUNT(*) c FROM users u WHERE $real AND u.created_at >= ?
                                 AND u.email_verified_at IS NOT NULL", [$since], 'confirmed'),
        /* A member invite (?ref=username) writes users.invited_by. The count is the number of
           real accounts, never the referrer's name. */
        'referrals' => $count("SELECT COUNT(*) c FROM users u WHERE $real AND u.created_at >= ?
                                 AND u.invited_by IS NOT NULL", [$since], 'referrals'),
        /* Current state, not a window. A profile another traveler can use: a name, a sentence,
           and a home city. */
        'profiles_completed' => $count(
            "SELECT COUNT(*) c FROM users u JOIN profiles p ON p.user_id = u.id
              WHERE $real
                AND TRIM(COALESCE(p.display_name, '')) <> ''
                AND LENGTH(TRIM(COALESCE(p.bio, ''))) >= 20
                AND TRIM(COALESCE(p.home_city, '')) <> ''", [], 'profiles_completed'),
        'reviews_published' => $count("SELECT COUNT(*) c FROM reviews r JOIN users u ON u.id = r.user_id
                                        WHERE $real AND r.status = 'published'", [], 'reviews_published'),
        'buddy_posts' => $count("SELECT COUNT(*) c FROM buddy_posts b JOIN users u ON u.id = b.user_id WHERE $real", [], 'buddy_posts'),
        'buddy_open' => $count("SELECT COUNT(*) c FROM buddy_posts b JOIN users u ON u.id = b.user_id
                                 WHERE $real AND b.status = 'open' AND b.date_to >= ?", [date('Y-m-d')], 'buddy_open'),
        'trips_public_upcoming' => $count(
            "SELECT COUNT(*) c FROM trips t JOIN users u ON u.id = t.user_id
              WHERE $real AND t.status = 'published' AND COALESCE(t.visibility, 'public') = 'public'
                AND t.date_from IS NOT NULL AND t.date_to >= ?", [date('Y-m-d')], 'trips_public_upcoming'),
    ];

    return [
        'since' => $since,
        'days' => $days,
        'events' => $events,
        'product' => $product,
        'errors' => $errors,
        'note' => 'Events are the utm values stored on that action (first touch), in the window. selfcheck is excluded. signups, confirmed and referrals are accounts created in the window. referrals counts users.invited_by and does not name anyone. profiles_completed, reviews_published and buddy_posts are current rows. A failed query is listed in errors and is null, never reported as zero.',
    ];
}

/** GET /cron/acquire?days=31  JSON. Same key and the same 404 as /cron/proof. Not a page view scan. */
function cron_acquire(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = function_exists('rmt_cron_key_given') ? rmt_cron_key_given() : '';
    if ($key === '' || $given === '' || !hash_equals($key, $given)) not_found();
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $days = (int) (function_exists('input') && input('days') !== '' ? input('days') : 31);
    echo json_encode(rmt_acquire_board($days));
}
