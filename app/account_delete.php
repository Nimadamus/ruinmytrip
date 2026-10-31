<?php
declare(strict_types=1);

/**
 * Self service account deletion.
 *
 * One function does the work, rmt_account_delete(), and it is the same function the test drives.
 * The decisions it makes, table by table, are written out in RMT_ACCOUNT_DELETE_INVENTORY below so
 * the privacy page, the test and the code can be checked against one list.
 *
 * Shape of a deleted account:
 *   The users row is KEPT as a tombstone: status 'deleted', no password, no email the person can be
 *   reached at, no Google link, no birthdate, a username that registration can never produce
 *   (longer than the 24 characters register_user allows). It stays because a few records must
 *   keep pointing at an account id: reports somebody filed about this member, moderation history,
 *   messages that now belong to the other person's conversation, and comments other members
 *   replied to. None of those rows carries profile data, and the tombstone itself carries none.
 *   Everything else the person made or that describes them is deleted.
 *
 *   Because the tombstone keeps neither the old email nor the old username, both are free the
 *   moment the deletion commits: the same address can open a new account, and the old username can
 *   be taken by anyone, which register_user already allows for any name that is not in use.
 *
 * Media: rows that point at an upload are deleted in the transaction, and every upload key the
 * account owned is re-stamped onto the tombstone (media.owner_id) inside the same transaction.
 * The files themselves are removed after commit by rmt_account_delete_media_sweep(), which works
 * from media.owner_id of deleted accounts, so a failure halfway through the file removal is
 * picked up by the next run instead of leaving a file nobody can find again.
 */

const RMT_ACCOUNT_DELETE_TOKEN_KIND = 'account_delete';

/** Roles that cannot delete themselves here. Staff accounts are removed by another admin. */
const RMT_ACCOUNT_DELETE_STAFF_ROLES = ['admin', 'mod', 'moderator', 'editorial'];

/**
 * The decision for every table that references a member. delete: the rows go. anonymize: the row
 * stays for somebody else's sake and loses the link to this person (or keeps only the tombstone
 * id and no personal text). retain: kept because it is a safety or legal record.
 */
const RMT_ACCOUNT_DELETE_INVENTORY = [
    'users'               => 'anonymize: tombstone row, status deleted, email/username/password/google/birthdate cleared',
    'profiles'            => 'delete: name, bio, home city, avatar, cover, links, languages, digest settings',
    'profile_interests'   => 'delete',
    'oauth_identities'    => 'delete: Google link',
    'auth_tokens'         => 'delete: verify, reset and deletion links',
    'sessions'            => 'delete: every signed in session',
    'push_subscriptions'  => 'delete',
    'notifications'       => 'delete: theirs, and every notification they caused for others',
    'follows'             => 'delete: both directions',
    'blocks'              => 'delete: both directions',
    'compliments'         => 'delete: both directions',
    'local_connects'      => 'delete: both directions',
    'trip_connects'       => 'delete: both directions, and any on trips that are deleted',
    'likes'               => 'delete: theirs, and likes on their deleted content',
    'saves'               => 'delete: theirs, and saves of their deleted content',
    'hidden_content'      => 'delete',
    'taggings'            => 'delete: tags on their deleted content',
    'review_votes'        => 'delete: theirs, and votes on their reviews',
    'poll_votes'          => 'delete',
    'meetup_rsvps'        => 'delete',
    'buddy_interest'      => 'delete: theirs, and interest in their buddy posts',
    'activity_joins'      => 'delete: theirs; decided_by cleared where they answered somebody',
    'collection_members'  => 'delete',
    'trip_members'        => 'delete; invited_by cleared',
    'user_badges'         => 'delete',
    'recognitions'        => 'delete',
    'user_countries'      => 'delete',
    'visits'              => 'delete',
    'going'               => 'delete',
    'going_cards'         => 'delete',
    'match_alerts'        => 'delete: by account and by their email address',
    'visitor_answers'     => 'delete',
    'held_work'           => 'delete',
    'reviews'             => 'delete, with review_photos, review_ratings and place photos made from them',
    'review_photos'       => 'delete',
    'trips'               => 'delete; a trip other members accepted passes to the earliest of them, emptied of the deleted member\'s notes, cover, photos and activities',
    'trip_photos'         => 'delete',
    'trip_messages'       => 'delete: their lines in trip chats; a trip deleted with them takes its chat',
    'trip_chat_reads'     => 'delete',
    'host_profiles'       => 'delete',
    'member_references'   => 'delete: references they wrote and references about them',
    'trip_activities'     => 'delete, with activity_joins and activity_photos on them',
    'activity_photos'     => 'delete',
    'guides'              => 'delete',
    'blog_posts'          => 'delete',
    'posts'               => 'delete, with their polls; updates posted on a deleted trip go with it',
    'meetups'             => 'delete (RSVPs to them go with them)',
    'buddy_posts'         => 'delete',
    'collections'         => 'delete; a collection other members joined passes to the earliest of them',
    'collection_items'    => 'anonymize: added_by cleared on items in collections that stay',
    'collection_invites'  => 'anonymize: created_by cleared',
    'comments'            => 'delete; a comment somebody replied to stays hidden, emptied, under the tombstone so the replies survive',
    'messages'            => 'anonymize: kept in the other member\'s conversation under "Deleted member"; deleted when both sides are gone',
    'conversations'       => 'anonymize: kept for the other member; deleted when both sides are gone',
    'place_photos'        => 'delete: their uploads',
    'media'               => 'delete: every upload, after commit',
    'places'              => 'anonymize: created_by cleared, the place stays in the directory',
    'place_suggestions'   => 'anonymize: suggested_by cleared',
    'feedback'            => 'anonymize: reported_by and contact email cleared',
    'destination_drafts'  => 'anonymize: created_by cleared',
    'reports'             => 'retain: reports they filed and reports about them, for safety',
    'moderation_log'      => 'retain',
];

/** Why this member may not delete their own account here, or null when they may. */
function rmt_account_delete_blocked_reason(?array $u): ?string {
    if (!$u) return 'Sign in first.';
    if (in_array((string) ($u['role'] ?? 'user'), RMT_ACCOUNT_DELETE_STAFF_ROLES, true)) {
        return 'Staff accounts cannot be deleted from this page, because moderation and editorial records point at them. Ask another admin to remove the account.';
    }
    if ((string) ($u['status'] ?? '') !== 'active') return 'This account is not active.';
    return null;
}

/** @return list<int> */
function rmt_ad_ids(string $sql, array $args): array {
    return array_map('intval', array_column(q_all($sql, $args), 'id'));
}

function rmt_ad_in(array $ids): string { return implode(',', array_fill(0, count($ids), '?')); }

/**
 * Delete an account. Safe to call again for the same id: a second call finds the tombstone, does
 * no database work and sweeps any files left from the first.
 *
 * @return array{ok:bool, already?:bool, error?:string, email?:string, username?:string, files?:int}
 */
function rmt_account_delete(int $uid): array {
    $pdo = db();
    $u = q_one('SELECT * FROM users WHERE id = ?', [$uid]);
    if (!$u) return ['ok' => false, 'error' => 'No such account.'];
    if ((string) $u['status'] === 'deleted') {
        return ['ok' => true, 'already' => true, 'files' => rmt_account_delete_media_sweep()];
    }
    if (in_array((string) $u['role'], RMT_ACCOUNT_DELETE_STAFF_ROLES, true)) {
        return ['ok' => false, 'error' => 'Staff accounts cannot be deleted this way.'];
    }
    $oldEmail = strtolower((string) $u['email']);
    $oldUsername = (string) $u['username'];
    $now = date('Y-m-d H:i:s');
    $x = static function (string $sql, array $args = []) use ($pdo): int {
        $st = $pdo->prepare($sql); $st->execute($args); return $st->rowCount();
    };
    $delTargets = static function (string $type, array $ids) use ($x): void {
        if (!$ids) return;
        $in = rmt_ad_in($ids);
        foreach (['likes', 'saves', 'hidden_content', 'taggings', 'notifications'] as $t) {
            $x("DELETE FROM {$t} WHERE target_type = ? AND target_id IN ($in)", array_merge([$type], $ids));
        }
    };

    $pdo->beginTransaction();
    try {
        /* First, and conditional on the row still being active: in Postgres this takes the row
           lock, so a double submit waits here and then finds nothing to do. */
        $tomb = 'deleted_member_' . $uid . '_' . bin2hex(random_bytes(4));
        $n = $x("UPDATE users SET status = 'deleted', username = ?, email = ?, password_hash = ?,
                        birthdate = NULL, email_verified_at = NULL, google_sub = NULL,
                        age_confirmed_at = NULL, invited_by = NULL
                  WHERE id = ? AND status = 'active'",
                [$tomb, $tomb . '@deleted.invalid', '!', $uid]);
        if ($n !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'This account is not active.'];
        }

        /* Every upload key this account can be tied to, from the media table and from every row
           that names a key, so a file uploaded before owner_id was recorded is not missed. */
        $keys = array_column(q_all('SELECT storage_key FROM media WHERE owner_id = ?', [$uid]), 'storage_key');
        $keySql = [
            'SELECT avatar_key k FROM profiles WHERE user_id = ?',
            'SELECT cover_key k FROM profiles WHERE user_id = ?',
            'SELECT image_key k FROM posts WHERE user_id = ?',
            // Photos on her trips that somebody else uploaded are collected below, only for trips that are deleted.
            'SELECT storage_key k FROM trip_photos WHERE user_id = ?',
            'SELECT storage_key k FROM review_photos WHERE user_id = ? OR review_id IN (SELECT id FROM reviews WHERE user_id = ?)',
            'SELECT storage_key k FROM activity_photos WHERE user_id = ?',
            'SELECT storage_key k FROM trip_activities WHERE user_id = ?',
            'SELECT storage_key k FROM place_photos WHERE uploaded_by = ?',
        ];
        foreach ($keySql as $sql) {
            $args = substr_count($sql, '?') === 2 ? [$uid, $uid] : [$uid];
            foreach (q_all($sql, $args) as $r) if (!empty($r['k'])) $keys[] = (string) $r['k'];
        }
        $keys = array_values(array_unique($keys));

        /* Trips. One that other members accepted is theirs too: it passes to the earliest of them,
           without the deleted member's notes, cover, photos or activities. The rest are deleted. */
        $tripIds = rmt_ad_ids('SELECT id FROM trips WHERE user_id = ?', [$uid]);
        $dropTrips = [];
        foreach ($tripIds as $tid) {
            $heir = q_one("SELECT user_id FROM trip_members WHERE trip_id = ? AND user_id <> ? AND state = 'active'
                            ORDER BY created_at, user_id LIMIT 1", [$tid, $uid]);
            if ($heir) {
                $x('UPDATE trips SET user_id = ?, body = ?, cover_url = NULL, updated_at = ? WHERE id = ?',
                   [(int) $heir['user_id'], '', $now, $tid]);
                $x('DELETE FROM trip_members WHERE trip_id = ? AND user_id = ?', [$tid, (int) $heir['user_id']]);
            } else {
                $dropTrips[] = $tid;
            }
        }
        // Their activities anywhere, plus every activity on a trip being deleted.
        $actIds = rmt_ad_ids('SELECT id FROM trip_activities WHERE user_id = ?', [$uid]);
        if ($dropTrips) {
            $actIds = array_merge($actIds, rmt_ad_ids('SELECT id FROM trip_activities WHERE trip_id IN (' . rmt_ad_in($dropTrips) . ')', $dropTrips));
            $actIds = array_values(array_unique($actIds));
        }
        if ($actIds) {
            $in = rmt_ad_in($actIds);
            foreach (q_all("SELECT storage_key k FROM activity_photos WHERE activity_id IN ($in)
                            UNION SELECT storage_key k FROM trip_activities WHERE id IN ($in)", array_merge($actIds, $actIds)) as $r) {
                if (!empty($r['k'])) $keys[] = (string) $r['k'];
            }
            $x("DELETE FROM activity_joins WHERE activity_id IN ($in)", $actIds);
            $x("DELETE FROM activity_photos WHERE activity_id IN ($in)", $actIds);
            $x("DELETE FROM trip_activities WHERE id IN ($in)", $actIds);
            $delTargets('activity', $actIds);
        }
        $x('DELETE FROM activity_photos WHERE user_id = ?', [$uid]);
        $x('DELETE FROM activity_joins WHERE user_id = ?', [$uid]);
        $x('UPDATE activity_joins SET decided_by = NULL WHERE decided_by = ?', [$uid]);
        $x('DELETE FROM trip_photos WHERE user_id = ?', [$uid]);
        if ($dropTrips) {
            $in = rmt_ad_in($dropTrips);
            foreach (q_all("SELECT storage_key k FROM trip_photos WHERE trip_id IN ($in)", $dropTrips) as $r) {
                if (!empty($r['k'])) $keys[] = (string) $r['k'];
            }
            $x("DELETE FROM trip_photos WHERE trip_id IN ($in)", $dropTrips);
            $x("DELETE FROM trip_messages WHERE trip_id IN ($in)", $dropTrips);
            $x("DELETE FROM trip_chat_reads WHERE trip_id IN ($in)", $dropTrips);
            $x("DELETE FROM trip_members WHERE trip_id IN ($in)", $dropTrips);
            $x("DELETE FROM trip_connects WHERE trip_id IN ($in)", $dropTrips);
            // Postgres cascades these (posts.trip_id ON DELETE CASCADE); SQLite's column has no action.
            $x("DELETE FROM posts WHERE trip_id IN ($in)", $dropTrips);
            $delTargets('trip', $dropTrips);
            $x("DELETE FROM trips WHERE id IN ($in)", $dropTrips);
        }
        $x('DELETE FROM trip_members WHERE user_id = ?', [$uid]);
        $x('UPDATE trip_members SET invited_by = NULL WHERE invited_by = ?', [$uid]);
        $x('DELETE FROM trip_connects WHERE from_user_id = ? OR to_user_id = ?', [$uid, $uid]);

        // Reviews, with their photos, aspect ratings, votes and the place photos made from them.
        $reviewIds = rmt_ad_ids('SELECT id FROM reviews WHERE user_id = ?', [$uid]);
        if ($reviewIds) {
            $in = rmt_ad_in($reviewIds);
            $x("DELETE FROM place_photos WHERE review_photo_id IN (SELECT id FROM review_photos WHERE review_id IN ($in))", $reviewIds);
            $x("DELETE FROM review_photos WHERE review_id IN ($in)", $reviewIds);
            $x("DELETE FROM review_ratings WHERE review_id IN ($in)", $reviewIds);
            $x("DELETE FROM review_votes WHERE review_id IN ($in)", $reviewIds);
            $delTargets('review', $reviewIds);
            $x("DELETE FROM reviews WHERE id IN ($in)", $reviewIds);
        }
        $x('DELETE FROM place_photos WHERE review_photo_id IN (SELECT id FROM review_photos WHERE user_id = ?)', [$uid]);
        $x('DELETE FROM review_photos WHERE user_id = ?', [$uid]);
        $x('DELETE FROM place_photos WHERE uploaded_by = ?', [$uid]);
        if ($keys) {
            foreach (array_chunk($keys, 500) as $chunk) {
                $x('DELETE FROM place_photos WHERE storage_key IN (' . rmt_ad_in($chunk) . ')', $chunk);
            }
        }

        // Posts (polls go with them), guides, blog posts, meetups, buddy posts.
        $postIds = rmt_ad_ids('SELECT id FROM posts WHERE user_id = ?', [$uid]);
        if ($postIds) {
            $in = rmt_ad_in($postIds);
            $x("DELETE FROM poll_votes WHERE post_id IN ($in)", $postIds);
            $x("DELETE FROM poll_options WHERE post_id IN ($in)", $postIds);
            $x("DELETE FROM post_polls WHERE post_id IN ($in)", $postIds);
            $delTargets('post', $postIds);
            $x("DELETE FROM posts WHERE id IN ($in)", $postIds);
        }
        foreach ([['guides', 'guide', 'user_id'], ['blog_posts', 'blog_post', 'user_id'],
                  ['meetups', 'meetup', 'host_id'], ['buddy_posts', 'buddy', 'user_id']] as [$table, $type, $col]) {
            $ids = rmt_ad_ids("SELECT id FROM {$table} WHERE {$col} = ?", [$uid]);
            if (!$ids) continue;
            $in = rmt_ad_in($ids);
            if ($table === 'meetups') $x("DELETE FROM meetup_rsvps WHERE meetup_id IN ($in)", $ids);
            if ($table === 'buddy_posts') $x("DELETE FROM buddy_interest WHERE post_id IN ($in)", $ids);
            $delTargets($type, $ids);
            $x("DELETE FROM {$table} WHERE id IN ($in)", $ids);
        }

        /* Collections. One with other active members is a group: it passes to the member who
           joined first. One nobody else joined is deleted with its items. */
        foreach (rmt_ad_ids('SELECT id FROM collections WHERE user_id = ?', [$uid]) as $cid) {
            $heir = q_one("SELECT user_id FROM collection_members WHERE collection_id = ? AND user_id <> ? AND status = 'active'
                            ORDER BY joined_at, id LIMIT 1", [$cid, $uid]);
            if ($heir) {
                $x('UPDATE collections SET user_id = ?, updated_at = ? WHERE id = ?', [(int) $heir['user_id'], $now, $cid]);
                $x("UPDATE collection_members SET role = 'owner' WHERE collection_id = ? AND user_id = ?", [$cid, (int) $heir['user_id']]);
            } else {
                $x('DELETE FROM posts WHERE collection_id = ?', [$cid]);
                $x('DELETE FROM collection_items WHERE collection_id = ?', [$cid]);
                $x('DELETE FROM collection_invites WHERE collection_id = ?', [$cid]);
                $x('DELETE FROM collection_members WHERE collection_id = ?', [$cid]);
                $delTargets('collection', [$cid]);
                $x('DELETE FROM collections WHERE id = ?', [$cid]);
            }
        }
        $x('DELETE FROM collection_members WHERE user_id = ?', [$uid]);
        $x('UPDATE collection_items SET added_by = NULL WHERE added_by = ?', [$uid]);
        $x('UPDATE collection_invites SET created_by = NULL WHERE created_by = ?', [$uid]);

        /* Comments. Deleting one that has replies would take the replies with it (parent_id
           cascades), and those are other people's words. So: delete every comment of theirs with
           nothing under it, repeat until none is left, and what remains has somebody else's reply
           beneath it. That remainder stays, hidden (status removed) and emptied of its text. */
        do {
            $n = $x('DELETE FROM comments WHERE user_id = ? AND NOT EXISTS (SELECT 1 FROM comments r WHERE r.parent_id = comments.id)', [$uid]);
        } while ($n > 0);
        $x("UPDATE comments SET body = '', status = 'removed', updated_at = ? WHERE user_id = ?", [$now, $uid]);

        // Everything that is about the person and nobody else.
        foreach ([
            'DELETE FROM likes WHERE user_id = ?',
            'DELETE FROM saves WHERE user_id = ?',
            'DELETE FROM hidden_content WHERE user_id = ?',
            'DELETE FROM review_votes WHERE user_id = ?',
            'DELETE FROM poll_votes WHERE user_id = ?',
            'DELETE FROM trip_messages WHERE user_id = ?',
            'DELETE FROM trip_chat_reads WHERE user_id = ?',
            'DELETE FROM host_profiles WHERE user_id = ?',
            'DELETE FROM member_references WHERE from_user_id = ? OR to_user_id = ?',
            'DELETE FROM meetup_rsvps WHERE user_id = ?',
            'DELETE FROM buddy_interest WHERE user_id = ?',
            'DELETE FROM notifications WHERE user_id = ? OR actor_id = ?',
            'DELETE FROM follows WHERE follower_id = ? OR followee_id = ?',
            'DELETE FROM blocks WHERE blocker_id = ? OR blocked_id = ?',
            'DELETE FROM compliments WHERE from_user_id = ? OR to_user_id = ?',
            'DELETE FROM local_connects WHERE from_user_id = ? OR to_user_id = ?',
            'DELETE FROM push_subscriptions WHERE user_id = ?',
            'DELETE FROM oauth_identities WHERE user_id = ?',
            'DELETE FROM auth_tokens WHERE user_id = ?',
            'DELETE FROM held_work WHERE user_id = ?',
            'DELETE FROM visitor_answers WHERE user_id = ?',
            'DELETE FROM user_countries WHERE user_id = ?',
            'DELETE FROM visits WHERE user_id = ?',
            'DELETE FROM going_cards WHERE user_id = ?',
            'DELETE FROM going WHERE user_id = ?',
            'DELETE FROM profile_interests WHERE user_id = ?',
            'DELETE FROM user_badges WHERE user_id = ?',
            'DELETE FROM recognitions WHERE user_id = ?',
            'DELETE FROM profiles WHERE user_id = ?',
        ] as $sql) {
            $x($sql, substr_count($sql, '?') === 2 ? [$uid, $uid] : [$uid]);
        }
        // Alerts signed up for by address before the account existed are theirs too.
        $x('DELETE FROM going_cards WHERE alert_id IN (SELECT id FROM match_alerts WHERE user_id = ? OR LOWER(email) = ?)', [$uid, $oldEmail]);
        $x('DELETE FROM match_alerts WHERE user_id = ? OR LOWER(email) = ?', [$uid, $oldEmail]);

        // Shared records that stay, without the link to this person.
        $x('UPDATE places SET created_by = NULL WHERE created_by = ?', [$uid]);
        $x('UPDATE place_suggestions SET suggested_by = NULL WHERE suggested_by = ?', [$uid]);
        $x('UPDATE feedback SET contact_email = NULL, reported_by = NULL WHERE reported_by = ?', [$uid]);
        $x('UPDATE destination_drafts SET created_by = NULL WHERE created_by = ?', [$uid]);
        $x('UPDATE users SET invited_by = NULL WHERE invited_by = ?', [$uid]);

        /* A conversation stays for the other member. When the other side is already gone too,
           nobody can read it any more and it goes. */
        $x("DELETE FROM messages WHERE conversation_id IN (
                SELECT c.id FROM conversations c
                  JOIN users o ON o.id = (CASE WHEN c.user_lo_id = ? THEN c.user_hi_id ELSE c.user_lo_id END)
                 WHERE (c.user_lo_id = ? OR c.user_hi_id = ?) AND o.status = 'deleted')", [$uid, $uid, $uid]);
        $x("DELETE FROM conversations WHERE (user_lo_id = ? OR user_hi_id = ?)
               AND NOT EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = conversations.id)", [$uid, $uid]);

        // Every upload key goes on the tombstone, so the sweep can always find it again.
        foreach (array_chunk($keys, 500) as $chunk) {
            $x('UPDATE media SET owner_id = ? WHERE storage_key IN (' . rmt_ad_in($chunk) . ')', array_merge([$uid], $chunk));
        }

        // Signed out everywhere, inside the same transaction as everything else.
        $x('DELETE FROM sessions WHERE data LIKE ? OR data LIKE ? OR data LIKE ?',
           ['uid|i:' . $uid . ';%', '%;uid|i:' . $uid . ';%', '%}uid|i:' . $uid . ';%']);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[account_delete] failed for user ' . $uid . ': ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Something went wrong and nothing was deleted. Try again in a minute.'];
    }

    $files = rmt_account_delete_media_sweep();
    return ['ok' => true, 'email' => $oldEmail, 'username' => $oldUsername, 'files' => $files];
}

/**
 * Remove every stored file still owned by a deleted account. Runs after each deletion, so a
 * file that failed to go last time goes now. Returns how many were removed.
 */
function rmt_account_delete_media_sweep(int $limit = 500): int {
    $rows = q_all("SELECT m.storage_key FROM media m JOIN users u ON u.id = m.owner_id
                    WHERE u.status = 'deleted' ORDER BY m.id LIMIT " . max(1, $limit));
    $n = 0;
    foreach ($rows as $r) {
        try { rmt_storage_delete((string) $r['storage_key']); $n++; }
        catch (\Throwable $e) { error_log('[account_delete] media sweep: ' . $e->getMessage()); }
    }
    return $n;
}

function rmt_mail_account_deleted(string $to, string $username): array {
    $contact = rtrim((string) cfg('app_url'), '/') . '/contact';
    $html = rmt_mail_layout(
        'Your account was deleted',
        '<p>Hi @' . e($username) . ', the RuinMyTrip account for this address was deleted just now, '
        . 'at your request. Your profile, reviews, trips, photos and posts are gone, and you have been '
        . 'signed out everywhere.</p>'
        . '<p>This is the last email we will send to this address about the account. If you did not '
        . 'do this, tell us right away at ' . e($contact) . '.</p>',
        '', '', 'You are receiving this because an account was deleted for this address.'
    );
    $text = "Hi @{$username},\n\nThe RuinMyTrip account for this address was deleted just now, at your request. "
          . "Your profile, reviews, trips, photos and posts are gone, and you have been signed out everywhere.\n\n"
          . "If you did not do this, tell us right away at {$contact}.";
    return rmt_mail_send($to, 'Your RuinMyTrip account was deleted', $html, $text);
}

function rmt_mail_account_delete_link(string $to, string $username, string $link): array {
    $html = rmt_mail_layout(
        'Confirm deleting your account',
        '<p>Hi @' . e($username) . ', someone signed in to your RuinMyTrip account asked to delete it. '
        . 'Open the link to finish. It expires in 30 minutes and works once.</p>',
        'Continue to delete my account', $link,
        'If you did not ask for this, ignore this email. Your account stays as it is.'
    );
    $text = "Hi @{$username},\n\nSomeone signed in to your RuinMyTrip account asked to delete it. Open this link to finish:\n{$link}\n\n"
          . "It expires in 30 minutes and works once. If you did not ask for this, ignore this email.";
    return rmt_mail_send($to, 'Confirm deleting your RuinMyTrip account', $html, $text);
}

/* ------------------------------------------------------------------ pages */

/** GET /account/delete */
function account_delete_form(array $a, array $errors = [], string $notice = ''): void {
    require_login();
    $me = current_user();
    $tokenRow = null;
    $raw = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
    if ($raw !== '') {
        $tokenRow = rmt_token_lookup($raw, RMT_ACCOUNT_DELETE_TOKEN_KIND);
        if (!$tokenRow || (int) $tokenRow['user_id'] !== (int) $me['id']) {
            $tokenRow = null; $raw = '';
            $errors[] = 'That link has expired or was already used. Ask for a new one below.';
        }
    }
    view('account_delete', [
        'me' => $me,
        'blocked' => rmt_account_delete_blocked_reason($me),
        'errors' => $errors,
        'notice' => $notice,
        'token' => $tokenRow ? $raw : '',
        'googleOnly' => !empty($me['google_sub']),
    ], ['title' => 'Delete your account | RuinMyTrip', 'robots' => 'noindex,nofollow']);
}

/** POST /account/delete: the deletion itself. */
function account_delete_submit(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $uid = (int) $me['id'];
    if (($why = rmt_account_delete_blocked_reason($me)) !== null) { account_delete_form($a, [$why]); return; }
    if (!rmt_rate_ok('account_delete', (string) $uid, 5, 3600) || !rmt_rate_ok('account_delete_ip', rmt_client_ip(), 20, 3600)) {
        account_delete_form($a, ['Too many attempts. Wait an hour and try again.']); return;
    }
    $errors = [];
    if (strcasecmp(trim((string) ($_POST['confirm_username'] ?? '')), (string) $me['username']) !== 0) {
        $errors[] = 'Type your username exactly as shown to confirm.';
    }
    $raw = (string) ($_POST['token'] ?? '');
    $tokenRow = null;
    if ($raw !== '') {
        $tokenRow = rmt_token_lookup($raw, RMT_ACCOUNT_DELETE_TOKEN_KIND);
        if (!$tokenRow || (int) $tokenRow['user_id'] !== $uid) {
            $errors[] = 'That link has expired or was already used. Ask for a new one below.';
            $_POST['token'] = '';
            $tokenRow = null;
        }
    } else {
        $row = q_one('SELECT password_hash FROM users WHERE id = ?', [$uid]);
        if (!password_verify((string) ($_POST['password'] ?? ''), (string) ($row['password_hash'] ?? ''))) {
            $errors[] = 'That password is not right.';
        }
    }
    if ($errors) { account_delete_form($a, $errors); return; }
    if ($tokenRow && !rmt_token_consume((int) $tokenRow['id'])) {
        account_delete_form($a, ['That link was already used. Ask for a new one below.']); return;
    }

    $res = rmt_account_delete($uid);
    if (!$res['ok']) { account_delete_form($a, [(string) ($res['error'] ?? 'Nothing was deleted.')]); return; }

    if (!empty($res['email'])) {
        try { rmt_mail_account_deleted((string) $res['email'], (string) $res['username']); }
        catch (\Throwable $e) { error_log('[account_delete] confirmation mail: ' . $e->getMessage()); }
    }
    // The database rows for every session are gone already; this ends the one in hand.
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
        session_start();
        session_regenerate_id(true);
        $_SESSION['rmt_account_deleted'] = 1;
    }
    redirect('/account/deleted');
}

/** POST /account/delete/email: send a one time link, for members who sign in with Google. */
function account_delete_email(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    if (($why = rmt_account_delete_blocked_reason($me)) !== null) { account_delete_form($a, [$why]); return; }
    if (!rmt_rate_ok('account_delete_mail', (string) $me['id'], 3, 3600)) {
        account_delete_form($a, ['We already sent a few links this hour. Use the newest one, or wait an hour.']); return;
    }
    $raw = rmt_token_issue((int) $me['id'], RMT_ACCOUNT_DELETE_TOKEN_KIND);
    $link = rtrim((string) cfg('app_url'), '/') . '/account/delete?token=' . $raw;
    $sent = rmt_mail_account_delete_link((string) $me['email'], (string) $me['username'], $link);
    if (!$sent[0]) error_log('[account_delete] link mail: ' . $sent[1]);
    account_delete_form($a, [], 'We sent a link to ' . $me['email'] . '. It expires in 30 minutes. Open it in this browser to finish.');
}

/** GET /account/deleted */
function account_deleted_page(array $a): void {
    if (empty($_SESSION['rmt_account_deleted'])) redirect('/');
    unset($_SESSION['rmt_account_deleted']);
    view('account_deleted', [], ['title' => 'Account deleted | RuinMyTrip', 'robots' => 'noindex,nofollow']);
}
