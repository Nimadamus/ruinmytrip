<?php
/**
 * What one member missed, for the email that brings them back.
 *
 * This used to live inside scripts/send_digest.php, which meant the single question the whole
 * retention loop rests on -- "is there anything worth emailing this person about" -- was written
 * in a place nothing could test and nothing else could reuse. It also stopped where the site did
 * a year ago: followers, votes, compliments, reviews. None of the things members now actually do
 * to each other appeared in it, so somebody could get three replies and a meetup in their own
 * travel dates and receive an email saying nothing had happened.
 *
 * The rule that matters is unchanged and load bearing: a member with nothing waiting gets no
 * email. An empty digest is spam, and the fastest way to teach somebody to filter you out.
 */
declare(strict_types=1);

/**
 * @return array{
 *   any:bool, followers:int, follower_names:list<string>, votes:int, compliments:int,
 *   reviews:list<array{title:string,author:string,url:string}>,
 *   replies:list<array{text:string,url:string,author:string}>,
 *   community:list<array{text:string,url:string,author:string,community:string}>,
 *   matches:int, meetups:list<array{title:string,url:string,when:string}>, unread_messages:int
 * }
 */
function rmt_digest_activity(int $uid, string $since): array {
    $followers = q_all("SELECT u2.username FROM follows f JOIN users u2 ON u2.id = f.follower_id
                         WHERE f.followee_id = ? AND f.created_at > ? ORDER BY f.created_at DESC LIMIT 5",
                       [$uid, $since]);
    $followerCount = (int) (q_one('SELECT COUNT(*) c FROM follows WHERE followee_id = ? AND created_at > ?',
                                  [$uid, $since])['c'] ?? 0);
    $votes = (int) (q_one("SELECT COUNT(*) c FROM review_votes rv JOIN reviews r ON r.id = rv.review_id
                            WHERE r.user_id = ? AND r.status='published' AND rv.created_at > ?",
                          [$uid, $since])['c'] ?? 0);
    $compliments = (int) (q_one('SELECT COUNT(*) c FROM compliments WHERE to_user_id = ? AND created_at > ?',
                                [$uid, $since])['c'] ?? 0);

    $followedReviews = q_all("SELECT r.id, r.title, r.subject_name, r.slug, u2.username
                                FROM reviews r JOIN follows f ON f.followee_id = r.user_id
                           LEFT JOIN users u2 ON u2.id = r.user_id
                               WHERE f.follower_id = ? AND r.status = 'published' AND r.created_at > ?
                            ORDER BY r.created_at DESC LIMIT 5", [$uid, $since]);
    $watchedReviews = q_all("SELECT r.id, r.title, r.subject_name, r.slug, d.name dest_name
                               FROM reviews r JOIN saves s ON s.target_type='destination' AND s.target_id=r.destination_id
                          LEFT JOIN destinations d ON d.id=r.destination_id
                               JOIN users ru ON ru.id=r.user_id
                              WHERE s.user_id = ? AND r.status='published' AND ru.role <> 'editorial'
                                AND r.created_at > ? AND r.user_id <> ?
                           ORDER BY r.created_at DESC LIMIT 5", [$uid, $since, $uid]);
    foreach ($watchedReviews as $wr) {
        $followedReviews[] = $wr + ['username' => ($wr['dest_name'] ?? 'a destination')];
    }

    /* Somebody answering you is the single most likely reason to come back, and it was not in the
       email at all. Own replies are excluded: nobody needs telling what they themselves said. */
    $replies = q_all("SELECT c.body, c.target_id, u.username
                        FROM comments c
                        JOIN posts p ON p.id = c.target_id
                        JOIN users u ON u.id = c.user_id
                       WHERE c.target_type='post' AND c.status='published'
                         AND p.user_id = ? AND c.user_id <> ? AND p.status='published'
                         AND c.created_at > ?
                    ORDER BY c.created_at DESC LIMIT 5", [$uid, $uid, $since]);

    $community = q_all("SELECT p.id, p.body, u.username, col.title community
                          FROM posts p
                          JOIN collection_members m ON m.collection_id = p.collection_id
                                                   AND m.user_id = ? AND m.status='active'
                          JOIN collections col ON col.id = p.collection_id
                          JOIN users u ON u.id = p.user_id
                         WHERE p.status='published' AND p.user_id <> ? AND p.created_at > ?
                      ORDER BY p.created_at DESC LIMIT 5", [$uid, $uid, $since]);

    /* Matches and nearby meetups are read from the notifications the site already decided were
       worth making, rather than recomputed here. Two places asking the same question in two
       different ways is how an email ends up disagreeing with the page it links to. */
    $matches = (int) (q_one("SELECT COUNT(*) c FROM notifications
                              WHERE user_id=? AND type='trip_match' AND created_at > ?",
                            [$uid, $since])['c'] ?? 0);
    $meetups = q_all("SELECT m.id, m.title, m.date_start
                        FROM notifications n JOIN meetups m ON m.id = n.target_id
                       WHERE n.user_id = ? AND n.type = 'meetup_nearby' AND n.created_at > ?
                         AND m.status = 'published'
                    ORDER BY m.date_start LIMIT 5", [$uid, $since]);

    $unread = (int) (q_one("SELECT COUNT(*) c FROM messages ms
                              JOIN conversations cv ON cv.id = ms.conversation_id
                             WHERE ms.sender_id <> ? AND ms.read_at IS NULL
                               AND (cv.user_lo_id = ? OR cv.user_hi_id = ?)",
                           [$uid, $uid, $uid])['c'] ?? 0);

    $out = [
        'followers' => $followerCount,
        'follower_names' => array_column($followers, 'username'),
        'votes' => $votes,
        'compliments' => $compliments,
        'reviews' => array_map(static fn(array $r): array => [
            'title'  => (string) ($r['title'] ?: $r['subject_name']),
            'author' => (string) ($r['username'] ?: 'a traveler'),
            'url'    => url(ltrim(rmt_review_path($r), '/')),
        ], $followedReviews),
        'replies' => array_map(static fn(array $r): array => [
            'text'   => mb_strimwidth(strip_tags((string) $r['body']), 0, 120, '…'),
            'author' => (string) $r['username'],
            'url'    => url('post/' . (int) $r['target_id']),
        ], $replies),
        'community' => array_map(static fn(array $r): array => [
            'text'      => mb_strimwidth(strip_tags((string) $r['body']), 0, 120, '…'),
            'author'    => (string) $r['username'],
            'community' => (string) $r['community'],
            'url'       => url('post/' . (int) $r['id']),
        ], $community),
        'matches' => $matches,
        'meetups' => array_map(static fn(array $m): array => [
            'title' => (string) $m['title'],
            'when'  => date('D M j', strtotime((string) $m['date_start'])),
            'url'   => url('meetup/' . (int) $m['id']),
        ], $meetups),
        'unread_messages' => $unread,
    ];
    $out['any'] = $out['followers'] > 0 || $out['votes'] > 0 || $out['compliments'] > 0
                || $out['reviews'] || $out['replies'] || $out['community']
                || $out['matches'] > 0 || $out['meetups'] || $out['unread_messages'] > 0;
    return $out;
}

/** One line summarising a digest, for the script's log and for a dry run. */
function rmt_digest_summary(array $a): string {
    return sprintf('%d follower(s), %d vote(s), %d compliment(s), %d review(s), %d repl(y/ies), '
                 . '%d community post(s), %d match(es), %d meetup(s), %d unread message(s)',
        (int) $a['followers'], (int) $a['votes'], (int) $a['compliments'], count($a['reviews']),
        count($a['replies']), count($a['community']), (int) $a['matches'], count($a['meetups']),
        (int) $a['unread_messages']);
}
