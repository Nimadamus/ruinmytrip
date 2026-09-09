<?php
declare(strict_types=1);

/**
 * Who lives in a city.
 *
 * The site knows who is VISITING a city, because they said so with dates. It has never known who
 * is THERE all the time, and a traveler asking "where do people actually eat" wants a local more
 * than another tourist. The data was almost present: profiles.home_city has always held free text
 * like "Lisbon, PT" or "Lisboa", which is fine on a profile and useless for a query.
 *
 * So there are two halves. The member's own words stay untouched in home_city, including for the
 * many people who live somewhere this site has no page for. home_destination_id is the resolved
 * link, set when they pick a city we have, and it is what every "locals" query reads.
 */

/**
 * Resolve free text to one of our destinations, or null.
 *
 * Deliberately conservative: an exact match on the city name, or on the leading part before a
 * comma, after case folding and trimming. "Lisbon, PT" and "lisbon" both resolve; "Lisbon Valley"
 * does not, and should not, because a fuzzy match here puts somebody on the wrong city's page as a
 * resident of it.
 */
function rmt_resolve_home_destination(?string $text): ?int {
    $t = trim((string) $text);
    if ($t === '') return null;
    $head = trim(explode(',', $t)[0]);
    if ($head === '') return null;
    $row = q_one('SELECT id FROM destinations WHERE LOWER(name) = LOWER(?)', [$head]);
    return $row ? (int) $row['id'] : null;
}

/**
 * Members who live in this city.
 *
 * Editorial accounts are excluded for the same reason they are excluded everywhere else on these
 * pages: the value of the list is that these are real people a reader could message.
 *
 * @return list<array{user_id:int, username:string, avatar_url:?string, display_name:?string,
 *                    reviews:int, home_city:?string}>
 */
function rmt_city_locals(int $destId, int $limit = 24): array {
    if ($destId < 1) return [];
    $rows = q_all(
        "SELECT u.id user_id, u.username, p.avatar_url, p.display_name, p.home_city,
                (SELECT COUNT(*) FROM reviews r
                  WHERE r.user_id = u.id AND r.destination_id = ? AND r.status = 'published') reviews
           FROM users u JOIN profiles p ON p.user_id = u.id
          WHERE p.home_destination_id = ? AND u.status = 'active' AND u.role <> ?
       ORDER BY reviews DESC, u.id
          LIMIT " . max(1, $limit),
        [$destId, $destId, RMT_EDITORIAL_ROLE]
    );
    return array_map(static fn(array $r) => [
        'user_id' => (int) $r['user_id'], 'username' => (string) $r['username'],
        'avatar_url' => $r['avatar_url'] ?? null, 'display_name' => $r['display_name'] ?? null,
        'reviews' => (int) $r['reviews'], 'home_city' => $r['home_city'] ?? null,
    ], $rows);
}

/** How many people call this city home. Counted, never stored. */
function rmt_city_local_count(int $destId): int {
    if ($destId < 1) return 0;
    return (int) (q_one("SELECT COUNT(*) c FROM profiles p JOIN users u ON u.id = p.user_id
                          WHERE p.home_destination_id = ? AND u.status = 'active' AND u.role <> ?",
                        [$destId, RMT_EDITORIAL_ROLE])['c'] ?? 0);
}
