<?php
declare(strict_types=1);

/**
 * The travelers in a city.
 *
 * Everything the site publishes about a destination was, until now, about the PLACE: what to see,
 * what a ticket costs, when it opens. That is the half of travel writing the internet already has
 * ten thousand versions of, and it is not what this site is. The question a traveler actually
 * arrives with and cannot answer anywhere good is "who else is going to be there, and can I meet
 * them" -- and that answer is made entirely of members.
 *
 * So this page is the members' page for a city: who is going and when, what meetups are on, what
 * is being asked, and who has actually been. Every row is somebody's own post. Nothing here is
 * written by us, and there is nothing to write: when a city is quiet the page says so and asks the
 * reader to be the first, which is the only honest version of an empty room and the only one that
 * has ever recruited anybody.
 */

/** Upcoming meetups in one city, soonest first, with a live going count. */
function rmt_city_meetups(int $destId, int $limit = 12): array {
    return q_all("SELECT m.*, (SELECT COUNT(*) FROM meetup_rsvps r
                                WHERE r.meetup_id = m.id AND r.status = 'going') going_count
                    FROM meetups m
                   WHERE m.destination_id = ? AND m.status = 'published' AND m.date_start >= ?
                   ORDER BY m.date_start LIMIT " . max(1, $limit),
                 [$destId, date('Y-m-d H:i:s')]);
}

/**
 * Travelers with dates in this city that have not finished yet.
 *
 * Visibility is decided by rmt_going_list_for_destination(), which is the one place that knows
 * what a viewer is allowed to see. Past trips are dropped here rather than there: a plan that
 * ended is still a true row on somebody's profile, it is just not an answer to "who is going".
 */
function rmt_city_going(int $destId, ?array $viewer, int $limit = 24): array {
    $today = date('Y-m-d');
    $rows = array_values(array_filter(
        rmt_going_list_for_destination($destId, $viewer),
        static fn(array $g) => (string) ($g['date_to'] ?? '') >= $today
    ));
    return array_slice($rows, 0, $limit);
}

/**
 * Members who have actually been: anybody with a published review or trip about this city.
 *
 * Editorial accounts are excluded on purpose. The whole point of the page is that these are real
 * travelers a reader could message, and a house byline in that list would make the other names
 * worth less rather than the list longer.
 *
 * @return list<array{user_id:int, username:string, avatar_url:?string, display_name:?string,
 *                    reviews:int, trips:int}>
 */
function rmt_city_travelers(int $destId, int $limit = 24): array {
    $rows = q_all(
        "SELECT u.id user_id, u.username, p.avatar_url, p.display_name,
                (SELECT COUNT(*) FROM reviews r2
                  WHERE r2.user_id = u.id AND r2.destination_id = ? AND r2.status = 'published') reviews,
                (SELECT COUNT(*) FROM trips t2
                  WHERE t2.user_id = u.id AND t2.destination_id = ? AND t2.status = 'published') trips
           FROM users u LEFT JOIN profiles p ON p.user_id = u.id
          WHERE u.status = 'active' AND u.role <> ?
            AND (EXISTS (SELECT 1 FROM reviews r WHERE r.user_id = u.id AND r.destination_id = ? AND r.status = 'published')
              OR EXISTS (SELECT 1 FROM trips t  WHERE t.user_id  = u.id AND t.destination_id  = ? AND t.status  = 'published'))
          ORDER BY (SELECT COUNT(*) FROM reviews r3
                     WHERE r3.user_id = u.id AND r3.destination_id = ? AND r3.status = 'published') DESC, u.id
          LIMIT " . max(1, $limit),
        [$destId, $destId, RMT_EDITORIAL_ROLE, $destId, $destId, $destId]
    );
    return array_map(static fn(array $r) => [
        'user_id' => (int) $r['user_id'], 'username' => (string) $r['username'],
        'avatar_url' => $r['avatar_url'] ?? null, 'display_name' => $r['display_name'] ?? null,
        'reviews' => (int) $r['reviews'], 'trips' => (int) $r['trips'],
    ], $rows);
}

/**
 * Everything the hub renders, in one call, so the controller does not assemble the page and the
 * view does not query.
 */
function rmt_city_traveler_hub(int $destId, ?array $viewer): array {
    $meetups = rmt_city_meetups($destId);
    $going   = rmt_city_going($destId, $viewer);
    $talk    = function_exists('rmt_posts_recent') ? rmt_posts_recent(8, $destId) : [];
    $people  = rmt_city_travelers($destId);
    /* Reviews by members, newest first. Editorial is excluded here on purpose: this page is the
       part of the site that is made of people, and our own writing has the rest of the city to
       live on. */
    $reviews = q_all("SELECT r.*, u.username, p.avatar_url
                        FROM reviews r JOIN users u ON u.id = r.user_id
                   LEFT JOIN profiles p ON p.user_id = u.id
                       WHERE r.destination_id = ? AND r.status = 'published'
                         AND u.status = 'active' AND u.role <> ?
                    ORDER BY r.created_at DESC, r.id DESC LIMIT 6", [$destId, RMT_EDITORIAL_ROLE]);
    /* The people who are there all the time. A traveler asking where to actually eat wants one of
       these more than they want another tourist, and until now the site had no way to say who they
       were even though the answer was sitting in profiles as free text. */
    $locals  = function_exists('rmt_city_locals') ? rmt_city_locals($destId) : [];
    /* How many of the people going said they travel alone. The question a solo traveler is really
       asking of a city page, and the one thing the site could not answer until profiles carried it. */
    $solo = 0;
    foreach ($going as $g) {
        if (($g['travel_style'] ?? null) === 'solo') $solo++;
    }
    return [
        'meetups' => $meetups, 'going' => $going, 'talk' => $talk, 'people' => $people,
        'locals' => $locals, 'reviews' => $reviews, 'solo' => $solo,
        // "Is anybody here" answered as one number, because that is the question the page is for.
        'active'  => count($meetups) + count($going) + count($talk) + count($people)
                     + count($locals) + count($reviews),
    ];
}
