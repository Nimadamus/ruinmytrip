<?php
declare(strict_types=1);

/**
 * What belongs in a member's feed.
 *
 * The feed used to answer one question -- "who do you follow" -- and the site had a second follow
 * gesture that fed nothing: saving a destination on its page. Somebody who saved Lisbon, Porto and
 * Naples had told us exactly which conversations they wanted and got an empty feed for it, while
 * the same page counted them into a "wants to go" number.
 *
 * So the scope is now two things, in one condition: people you follow (and yourself), and the
 * cities you saved. Both are deliberate acts by the member. Nothing is inferred from what they
 * happened to read.
 *
 * Kept in its own file, and built as SQL text rather than inlined per query, because the feed
 * assembles seven separate statements with different table aliases and the previous version
 * patched one of them with str_replace() on the column name -- which a subquery mentioning
 * user_id would have silently corrupted.
 */

/**
 * The scope condition for one feed query.
 *
 * $userCol is the column naming the author, qualified with the alias the query uses. $destCol is
 * the column naming the destination, or null for content that has none (blog posts, lists) -- for
 * those the condition is follows-only, with two placeholders instead of three.
 *
 * Placeholders are positional and always in this order: author-is-me, author-is-followed, and
 * (when $destCol is given) destination-is-saved. rmt_feed_scope_args() returns them.
 */
function rmt_feed_scope_sql(string $userCol, ?string $destCol = null): string {
    $sql = "($userCol = ? OR $userCol IN (SELECT followee_id FROM follows WHERE follower_id = ?)";
    if ($destCol !== null) {
        $sql .= " OR $destCol IN (SELECT target_id FROM saves WHERE user_id = ? AND target_type = 'destination')";
        /* And the cities you are actually going to. Posting dates for Munich is a louder statement
           of interest than saving it, and a member with a trip there in a fortnight was not being
           shown a single post about Munich unless they had also pressed Follow. Upcoming only: a
           trip that is over is a place you have been, not a conversation you are waiting for. */
        $sql .= " OR $destCol IN (SELECT tr.destination_id FROM trips tr
                                   WHERE tr.user_id = ? AND tr.status = 'published'
                                     AND tr.destination_id IS NOT NULL
                                     AND tr.date_to IS NOT NULL AND tr.date_to >= ?)";
    }
    return $sql . ')';
}

/** The bind values for rmt_feed_scope_sql(), in the order it emits its placeholders. */
function rmt_feed_scope_args(int $userId, bool $withDestination = true): array {
    return $withDestination ? [$userId, $userId, $userId, $userId, date('Y-m-d')] : [$userId, $userId];
}

/**
 * The cities a member follows by having saved them. Used to say so on the feed rather than
 * quietly mixing strangers' posts in with the people they chose.
 *
 * @return array<int, array{id:int, name:string, slug:string}>
 */
function rmt_feed_followed_destinations(int $userId, int $limit = 12): array {
    /* Named on the feed with the same scope the feed uses, so a city that appears because of an
       upcoming trip is said out loud rather than mixed in silently. */
    $rows = q_all("SELECT d.id, d.name, d.slug FROM destinations d
                    WHERE d.id IN (SELECT target_id FROM saves WHERE user_id = ? AND target_type = 'destination')
                       OR d.id IN (SELECT destination_id FROM trips
                                    WHERE user_id = ? AND status = 'published' AND destination_id IS NOT NULL
                                      AND date_to IS NOT NULL AND date_to >= ?)
                    ORDER BY d.name LIMIT " . max(1, $limit), [$userId, $userId, date('Y-m-d')]);
    return array_map(static fn(array $r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'],
                                             'slug' => (string) $r['slug']], $rows);
}
