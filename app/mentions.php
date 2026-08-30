<?php
declare(strict_types=1);

/**
 * @mentions.
 *
 * Same content model as hashtags (app/tags.php): mentions live in the text a traveler already
 * writes, never a separate field, and one regex is shared by extraction and linkification so the
 * set of users notified and the set of names highlighted can never disagree.
 *
 * The pattern mirrors register_user()'s username rule ([A-Za-z0-9_]{3,24}) and refuses a word
 * character or '@' immediately before the '@' so email addresses (nima@example.com) and doubled
 * '@@' are never mistaken for mentions.
 *
 * Only names that resolve to a real active user count — for notification AND for linkification —
 * so "@everyone" stays plain text instead of becoming a dead link.
 */
const RMT_MENTION_RX = '/(?<![\w@])@([A-Za-z0-9_]{3,24})/';
const RMT_MENTIONS_MAX_PER_ITEM = 10;

/**
 * Active users mentioned in the given texts: [id => username(stored case)], capped at
 * RMT_MENTIONS_MAX_PER_ITEM. Lookup is case-insensitive (usernames are unique, and @NiMa should
 * still reach @nima) but the returned casing is the account's own.
 */
function rmt_extract_mentions(?string ...$texts): array {
    $names = [];
    foreach ($texts as $t) {
        if ($t === null || $t === '') continue;
        if (preg_match_all(RMT_MENTION_RX, $t, $m)) {
            foreach ($m[1] as $name) $names[strtolower($name)] = true;
        }
    }
    if (!$names) return [];
    $names = array_slice(array_keys($names), 0, RMT_MENTIONS_MAX_PER_ITEM);
    $ph = implode(',', array_fill(0, count($names), '?'));
    $rows = q_all("SELECT id, username FROM users WHERE status='active' AND LOWER(username) IN ($ph)", $names);
    $out = [];
    foreach ($rows as $r) $out[(int)$r['id']] = (string)$r['username'];
    return $out;
}

/**
 * Notify every active user mentioned in the texts, except the author, anyone in $skipUserIds
 * (e.g. a content owner who is already getting a 'comment' notification for the same action), and
 * anyone already holding a mention notification for the same target (edits must not re-ping).
 * target_type/target_id point
 * at the CONTENT the mention appears on (trip/review/guide/blog_post/meetup), so the notification
 * can link somewhere readable even when the mention itself sits in a comment.
 */
function rmt_notify_mentions(string $targetType, int $targetId, int $actorId, array $skipUserIds, ?string ...$texts): void {
    $users = rmt_extract_mentions(...$texts);
    unset($users[$actorId]);
    foreach ($skipUserIds as $skip) unset($users[(int)$skip]);
    if (!$users) return;
    $now = date('Y-m-d H:i:s');
    foreach ($users as $uid => $_name) {
        $dup = q_one("SELECT 1 FROM notifications WHERE user_id=? AND type='mention' AND target_type=? AND target_id=?",
                     [$uid, $targetType, $targetId]);
        if ($dup) continue;
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
              [$uid, 'mention', $actorId, $targetType, $targetId, $now]);

        /* Being named is addressed to one person, so it is one of the few things worth an email
           the same day. Capped hard in rmt_notify_email_direct(); silent when over. */
        $actor = q_one("SELECT username FROM users WHERE id=?", [$actorId]);
        $href = rmt_notification_target_url($targetType, $targetId, (int) $uid);
        if ($actor && $href) {
            rmt_notify_email_direct((int) $uid, 'You were mentioned on RuinMyTrip',
                '@' . $actor['username'] . ' mentioned you.', $href);
        }
    }
}

/**
 * Wrap the @names in an ALREADY-ESCAPED body in profile links. Same contract as
 * rmt_linkify_tags(): input must have been through e() (and optionally nl2br) first, and this is
 * never used on editorial bodies. Names that are not an active account stay plain text.
 */
function rmt_linkify_mentions(string $escapedHtml): string {
    if (!preg_match_all(RMT_MENTION_RX, $escapedHtml, $m)) return $escapedHtml;
    $known = [];
    foreach (rmt_extract_mentions($escapedHtml) as $name) $known[strtolower($name)] = $name;
    return (string) preg_replace_callback(RMT_MENTION_RX, function ($m) use ($known) {
        $stored = $known[strtolower($m[1])] ?? null;
        if ($stored === null) return $m[0];
        return '<a class="mention-link" href="' . e(url('u/' . $stored)) . '">@' . e($m[1]) . '</a>';
    }, $escapedHtml);
}

/**
 * Where a notification about a piece of content should land. Returns null when the content is
 * gone or unpublished, so the view can degrade to plain text instead of linking to a 404.
 */
function rmt_notification_target_url(string $type, int $id, int $forUserId = 0): ?string {
    switch ($type) {
        case 'conversation':
            // $id is a conversations.id; resolve to the OTHER participant's thread URL, not the
            // recipient's own — a notification always links to where the recipient should go.
            $r = q_one('SELECT user_lo_id, user_hi_id FROM conversations WHERE id=?', [$id]);
            if (!$r) return null;
            $otherId = (int)$r['user_lo_id'] === $forUserId ? (int)$r['user_hi_id'] : (int)$r['user_lo_id'];
            $u = q_one("SELECT username FROM users WHERE id=? AND status='active'", [$otherId]);
            return $u ? url('messages/' . $u['username']) : null;
        case 'trip':
            $r = q_one("SELECT id, slug FROM trips WHERE id=? AND status='published'", [$id]);
            return $r ? url('trip/' . (int)$r['id'] . '/' . $r['slug']) : null;
        case 'review':
            $r = q_one("SELECT * FROM reviews WHERE id=? AND status='published'", [$id]);
            return $r ? url(ltrim(rmt_review_path($r), '/')) : null;
        case 'guide':
            $r = q_one("SELECT slug FROM guides WHERE id=? AND status='published'", [$id]);
            return $r ? url('g/' . $r['slug']) : null;
        case 'blog_post':
            $r = q_one("SELECT slug FROM blog_posts WHERE id=? AND status='published'", [$id]);
            return $r ? url('blog/' . $r['slug']) : null;
        case 'meetup':
            $r = q_one('SELECT id FROM meetups WHERE id=?', [$id]);
            return $r ? url('meetup/' . (int)$r['id']) : null;
        case 'going':
            $r = q_one("SELECT d.slug FROM going g JOIN destinations d ON d.id=g.destination_id WHERE g.id=?", [$id]);
            return $r ? url('d/' . $r['slug']) : url('going');
        case 'collection':
            $r = q_one("SELECT slug FROM collections WHERE id=? AND status='published'", [$id]);
            return $r ? url('c/' . $r['slug']) : null;
        case 'post':
            $r = q_one("SELECT id FROM posts WHERE id=? AND status='published'", [$id]);
            return $r ? url('post/' . (int)$r['id']) : null;
        case 'destination':
            $r = q_one('SELECT slug FROM destinations WHERE id=?', [$id]);
            return $r ? url('d/' . $r['slug']) : null;
        case 'user':
            $r = q_one("SELECT username FROM users WHERE id=? AND status='active'", [$id]);
            return $r ? url('u/' . $r['username']) : null;
    }
    return null;
}
