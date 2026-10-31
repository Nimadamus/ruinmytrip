<?php
/**
 * Self service account deletion, end to end against the real schema.
 *
 * Builds a throwaway SQLite database from the baseline schema plus every migration, seeds a member
 * (Dana) with a row in every table that references a member, plus a second member (Omar) who
 * follows, messages, comments, replies, joins her trip and her group. Then deletes Dana and checks:
 *   - nothing personal of hers remains anywhere (every user column of every table is scanned)
 *   - Omar's own content, his trip activities and his side of the conversation are untouched
 *   - what is kept (reports, a replied to comment, her messages to Omar) carries no personal data
 *   - she cannot sign in, her email and username are free, and a second run is harmless
 *   - staff cannot delete themselves
 *
 *   php tests/account_delete_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$dbFile = tempnam(sys_get_temp_dir(), 'rmt_acctdel_');
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => $dbFile,
];
putenv('RESEND_API_KEY');          // no mail leaves a test
putenv('STORAGE_DRIVER=pg');

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/migrator.php';
require BASE_PATH . '/database/seed.php';
require BASE_PATH . '/app/mail.php';
require BASE_PATH . '/app/tokens.php';
require BASE_PATH . '/app/storage.php';
require BASE_PATH . '/app/auth.php';
require BASE_PATH . '/app/account_delete.php';

$pdo = db();
rmt_apply_schema($pdo, 'sqlite');
rmt_migrate($pdo, 'sqlite');

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if ($cond) { echo "  PASS  $name\n"; return; }
    $fails++;
    echo "  FAIL  $name" . ($detail !== '' ? "  ($detail)" : '') . "\n";
}
function cnt(string $sql, array $a = []): int { return (int) (q_one($sql, $a)['c'] ?? 0); }
function ins(string $table, array $row): int {
    $cols = array_keys($row);
    q_run("INSERT INTO {$table} (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($row));
    return (int) db()->lastInsertId();
}

$now = date('Y-m-d H:i:s');
$dest = ins('destinations', ['slug' => 'lisbon-t', 'name' => 'Lisbon', 'country' => 'Portugal']);
$mkUser = function (string $name, string $role = 'user') use ($now): int {
    $id = ins('users', ['username' => $name, 'email' => "$name@example.com",
        'password_hash' => password_hash('correct horse', PASSWORD_BCRYPT), 'role' => $role,
        'birthdate' => '1990-01-01', 'status' => 'active', 'created_at' => $now,
        'email_verified_at' => $now]);
    ins('profiles', ['user_id' => $id, 'display_name' => ucfirst($name) . ' Person', 'bio' => "secret bio of $name",
        'home_city' => 'Porto', 'avatar_url' => "/media/av_$name", 'avatar_key' => "av_$name",
        'links_json' => '{"ig":"' . $name . '"}', 'credibility_score' => 0]);
    return $id;
};
$dana = $mkUser('dana');
$omar = $mkUser('omar');
$admin = $mkUser('boss', 'admin');
q_run('UPDATE users SET google_sub = ?, invited_by = ? WHERE id = ?', ['g-123', $omar, $dana]);
q_run('UPDATE users SET invited_by = ? WHERE id = ?', [$dana, $omar]);

// Media she uploaded (and one Omar uploaded, which must survive).
foreach (['av_dana', 'trip_dana', 'rev_dana', 'post_dana', 'act_dana', 'av_omar', 'omar_trip'] as $k) {
    $owner = str_contains($k, 'omar') ? $omar : $dana;
    ins('media', ['storage_key' => $k, 'driver' => 'pg', 'owner_id' => $owner, 'mime' => 'image/jpeg',
        'bytes' => 3, 'width' => 1, 'height' => 1, 'sha256' => sha1($k), 'data' => 'abc', 'created_at' => $now]);
}
// Legacy upload with no owner recorded, only referenced from her post.
ins('media', ['storage_key' => 'legacy_dana', 'driver' => 'pg', 'owner_id' => null, 'mime' => 'image/jpeg',
    'bytes' => 3, 'width' => 1, 'height' => 1, 'sha256' => 'x', 'data' => 'abc', 'created_at' => $now]);

// --- Dana's content ------------------------------------------------------------------------
$tripSolo = ins('trips', ['user_id' => $dana, 'destination_id' => $dest, 'title' => 'Dana solo', 'slug' => 'dana-solo',
    'body' => 'my private diary', 'status' => 'published', 'created_at' => $now, 'visibility' => 'private']);
$tripShared = ins('trips', ['user_id' => $dana, 'destination_id' => $dest, 'title' => 'Group Lisbon', 'slug' => 'group-lisbon',
    'body' => 'dana notes', 'cover_url' => '/media/trip_dana', 'status' => 'published', 'created_at' => $now]);
ins('trip_members', ['trip_id' => $tripShared, 'user_id' => $omar, 'role' => 'editor', 'state' => 'active', 'invited_by' => $dana, 'created_at' => $now]);
ins('trip_photos', ['trip_id' => $tripSolo, 'url' => '/media/trip_dana', 'storage_key' => 'trip_dana', 'user_id' => $dana, 'status' => 'published', 'created_at' => $now]);
ins('trip_photos', ['trip_id' => $tripShared, 'url' => '/media/omar_trip', 'storage_key' => 'omar_trip', 'user_id' => $omar, 'status' => 'published', 'created_at' => $now]);
$actDana = ins('trip_activities', ['trip_id' => $tripShared, 'user_id' => $dana, 'title' => 'Dana dinner', 'storage_key' => 'act_dana', 'status' => 'published', 'created_at' => $now, 'updated_at' => $now]);
$actOmar = ins('trip_activities', ['trip_id' => $tripShared, 'user_id' => $omar, 'title' => 'Omar tram ride', 'status' => 'published', 'created_at' => $now, 'updated_at' => $now]);
ins('trip_messages', ['trip_id' => $tripShared, 'user_id' => $dana, 'body' => 'Dana in the trip chat', 'created_at' => $now]);
ins('trip_messages', ['trip_id' => $tripShared, 'user_id' => $omar, 'body' => 'Omar in the trip chat', 'created_at' => $now]);
ins('trip_chat_reads', ['trip_id' => $tripShared, 'user_id' => $dana, 'last_read_id' => 1]);
ins('host_profiles', ['user_id' => $dana, 'status' => 'approved', 'destination_id' => $dest, 'space_type' => 'couch', 'max_guests' => 1,
    'min_nights' => 1, 'max_nights' => 2, 'about_space' => 'Dana couch', 'pets' => 'none', 'smoking' => 'no', 'created_at' => $now, 'updated_at' => $now]);
ins('member_references', ['from_user_id' => $omar, 'to_user_id' => $dana, 'kind' => 'met', 'basis' => 'local_connect', 'rating_overall' => 5,
    'body' => 'Omar about Dana', 'status' => 'published', 'created_at' => $now, 'updated_at' => $now]);
ins('member_references', ['from_user_id' => $dana, 'to_user_id' => $omar, 'kind' => 'met', 'basis' => 'local_connect', 'rating_overall' => 5,
    'body' => 'Dana about Omar', 'status' => 'published', 'created_at' => $now, 'updated_at' => $now]);
ins('activity_joins', ['activity_id' => $actOmar, 'user_id' => $dana, 'state' => 'accepted', 'created_at' => $now, 'decided_by' => $omar]);
ins('activity_joins', ['activity_id' => $actDana, 'user_id' => $omar, 'state' => 'accepted', 'created_at' => $now, 'decided_by' => $dana]);
ins('activity_photos', ['activity_id' => $actOmar, 'user_id' => $dana, 'url' => '/media/act_dana', 'storage_key' => 'act_dana', 'status' => 'published', 'created_at' => $now]);
ins('trip_connects', ['trip_id' => $tripSolo, 'from_user_id' => $omar, 'to_user_id' => $dana, 'state' => 'interested', 'created_at' => $now]);
$review = ins('reviews', ['user_id' => $dana, 'destination_id' => $dest, 'subject_type' => 'destination', 'subject_name' => 'Lisbon',
    'rating' => 2, 'title' => 'Dana review', 'body' => 'dana words about lisbon', 'status' => 'published', 'created_at' => $now, 'slug' => 'dana-review']);
$rp = ins('review_photos', ['review_id' => $review, 'url' => '/media/rev_dana', 'storage_key' => 'rev_dana', 'user_id' => $dana, 'status' => 'published', 'created_at' => $now]);
ins('review_votes', ['review_id' => $review, 'user_id' => $omar, 'vote_type' => 'helpful', 'created_at' => $now]);
$omarReview = ins('reviews', ['user_id' => $omar, 'destination_id' => $dest, 'subject_type' => 'destination', 'subject_name' => 'Lisbon',
    'rating' => 5, 'title' => 'Omar review', 'body' => 'omar words', 'status' => 'published', 'created_at' => $now, 'slug' => 'omar-review']);
ins('review_votes', ['review_id' => $omarReview, 'user_id' => $dana, 'vote_type' => 'helpful', 'created_at' => $now]);
$place = ins('places', ['destination_id' => $dest, 'slug' => 'cafe-t', 'name' => 'Cafe', 'name_key' => 'cafe', 'type' => 'cafe',
    'created_by' => $dana, 'status' => 'published', 'created_at' => $now, 'updated_at' => $now]);
ins('place_photos', ['place_id' => $place, 'review_photo_id' => $rp, 'storage_key' => 'rev_dana', 'url' => '/media/rev_dana', 'uploaded_by' => $dana, 'status' => 'published', 'created_at' => $now]);
$post = ins('posts', ['user_id' => $dana, 'destination_id' => $dest, 'body' => 'dana post', 'status' => 'published', 'created_at' => $now, 'image_key' => 'post_dana']);
ins('posts', ['user_id' => $dana, 'body' => 'dana legacy photo post', 'status' => 'published', 'created_at' => $now, 'image_key' => 'legacy_dana']);
ins('post_polls', ['post_id' => $post, 'created_at' => $now]);
$opt = ins('poll_options', ['post_id' => $post, 'position' => 1, 'label' => 'Yes']);
ins('poll_votes', ['post_id' => $post, 'option_id' => $opt, 'user_id' => $omar, 'created_at' => $now]);
$omarPost = ins('posts', ['user_id' => $omar, 'body' => 'omar post', 'status' => 'published', 'created_at' => $now]);
ins('likes', ['user_id' => $dana, 'target_type' => 'post', 'target_id' => $omarPost]);
ins('likes', ['user_id' => $omar, 'target_type' => 'post', 'target_id' => $post]);
ins('saves', ['user_id' => $dana, 'target_type' => 'review', 'target_id' => $omarReview, 'created_at' => $now]);
ins('guides', ['user_id' => $dana, 'destination_id' => $dest, 'slug' => 'dana-guide', 'title' => 'Guide', 'body' => 'g', 'status' => 'published', 'created_at' => $now]);
ins('blog_posts', ['user_id' => $dana, 'slug' => 'dana-blog', 'title' => 'Blog', 'body' => 'b', 'status' => 'published', 'created_at' => $now]);
$meet = ins('meetups', ['host_id' => $dana, 'destination_id' => $dest, 'title' => 'Dana meetup', 'date_start' => $now, 'status' => 'published', 'created_at' => $now]);
ins('meetup_rsvps', ['meetup_id' => $meet, 'user_id' => $omar, 'status' => 'going']);
$buddy = ins('buddy_posts', ['user_id' => $dana, 'trip_type' => 'city', 'title' => 'Dana buddy', 'where_text' => 'Lisbon', 'date_from' => '2026-12-01', 'date_to' => '2026-12-05', 'description' => 'd', 'status' => 'open', 'created_at' => $now]);
ins('buddy_interest', ['post_id' => $buddy, 'user_id' => $omar, 'state' => 'pending', 'created_at' => $now]);
$omarBuddy = ins('buddy_posts', ['user_id' => $omar, 'trip_type' => 'city', 'title' => 'Omar buddy', 'where_text' => 'Lisbon', 'date_from' => '2026-12-01', 'date_to' => '2026-12-05', 'description' => 'o', 'status' => 'open', 'created_at' => $now]);
ins('buddy_interest', ['post_id' => $omarBuddy, 'user_id' => $dana, 'note' => 'dana note', 'state' => 'pending', 'created_at' => $now]);
$collSolo = ins('collections', ['user_id' => $dana, 'slug' => 'dana-list', 'title' => 'Dana list', 'status' => 'published', 'created_at' => $now]);
ins('collection_members', ['collection_id' => $collSolo, 'user_id' => $dana, 'role' => 'owner', 'status' => 'active', 'joined_at' => $now]);
$collGroup = ins('collections', ['user_id' => $dana, 'slug' => 'group-list', 'title' => 'Group list', 'status' => 'published', 'created_at' => $now]);
ins('collection_members', ['collection_id' => $collGroup, 'user_id' => $dana, 'role' => 'owner', 'status' => 'active', 'joined_at' => $now]);
ins('collection_members', ['collection_id' => $collGroup, 'user_id' => $omar, 'role' => 'member', 'status' => 'active', 'joined_at' => $now]);
ins('collection_items', ['collection_id' => $collGroup, 'destination_id' => $dest, 'note' => 'added by dana', 'added_by' => $dana]);
ins('collection_invites', ['collection_id' => $collGroup, 'token' => 'tok1', 'created_by' => $dana, 'created_at' => $now]);
$omarGroupPost = ins('posts', ['user_id' => $omar, 'collection_id' => $collGroup, 'body' => 'omar in group', 'status' => 'published', 'created_at' => $now]);

// Comments: one on Omar's post with Omar's reply under it, one standalone, Omar's own top level.
$cReplied = ins('comments', ['user_id' => $dana, 'target_type' => 'post', 'target_id' => $omarPost, 'body' => 'dana comment with my phone 555', 'status' => 'published', 'created_at' => $now]);
$cOmarReply = ins('comments', ['user_id' => $omar, 'target_type' => 'post', 'target_id' => $omarPost, 'body' => 'omar reply', 'status' => 'published', 'created_at' => $now, 'parent_id' => $cReplied]);
$cDanaLeaf = ins('comments', ['user_id' => $dana, 'target_type' => 'post', 'target_id' => $omarPost, 'body' => 'dana lonely comment', 'status' => 'published', 'created_at' => $now, 'parent_id' => $cReplied]);
$cOmarTop = ins('comments', ['user_id' => $omar, 'target_type' => 'review', 'target_id' => $review, 'body' => 'omar on dana review', 'status' => 'published', 'created_at' => $now]);

// Social graph, messages, notifications, tokens, sessions, alerts, misc.
ins('follows', ['follower_id' => $dana, 'followee_id' => $omar, 'created_at' => $now]);
ins('follows', ['follower_id' => $omar, 'followee_id' => $dana, 'created_at' => $now]);
ins('blocks', ['blocker_id' => $dana, 'blocked_id' => $admin]);
ins('compliments', ['from_user_id' => $omar, 'to_user_id' => $dana, 'type' => 'helpful', 'created_at' => $now]);
ins('local_connects', ['from_user_id' => $dana, 'to_user_id' => $omar, 'state' => 'pending', 'created_at' => $now]);
[$lo, $hi] = [min($dana, $omar), max($dana, $omar)];
$conv = ins('conversations', ['user_lo_id' => $lo, 'user_hi_id' => $hi, 'last_message_at' => $now, 'created_at' => $now]);
$mDana = ins('messages', ['conversation_id' => $conv, 'sender_id' => $dana, 'body' => 'hi omar from dana', 'created_at' => $now]);
$mOmar = ins('messages', ['conversation_id' => $conv, 'sender_id' => $omar, 'body' => 'hi dana from omar', 'created_at' => $now]);
ins('notifications', ['user_id' => $dana, 'type' => 'follow', 'actor_id' => $omar, 'created_at' => $now]);
ins('notifications', ['user_id' => $omar, 'type' => 'follow', 'actor_id' => $dana, 'created_at' => $now]);
ins('notifications', ['user_id' => $omar, 'type' => 'like', 'actor_id' => $admin, 'target_type' => 'post', 'target_id' => $omarPost, 'created_at' => $now]);
ins('auth_tokens', ['user_id' => $dana, 'kind' => 'reset', 'token_hash' => sha1('t'), 'expires_at' => $now, 'created_at' => $now]);
ins('oauth_identities', ['provider' => 'google', 'provider_uid' => 'g-123', 'user_id' => $dana, 'created_at' => $now]);
ins('push_subscriptions', ['user_id' => $dana, 'endpoint' => 'https://push.example/dana', 'p256dh' => 'k', 'auth' => 'a', 'created_at' => $now]);
q_run('INSERT INTO sessions (id, data, updated_at) VALUES (?,?,?)', ['sessdana', 'uid|i:' . $dana . ';', time()]);
q_run('INSERT INTO sessions (id, data, updated_at) VALUES (?,?,?)', ['sessomar', 'uid|i:' . $omar . ';', time()]);
$alert = ins('match_alerts', ['email' => 'dana@example.com', 'destination_id' => $dest, 'date_from' => '2026-12-01', 'date_to' => '2026-12-05', 'token_hash' => 'th1', 'status' => 'active', 'user_id' => null, 'created_at' => $now]);
ins('going_cards', ['code' => 'gc1', 'user_id' => $dana, 'alert_id' => $alert, 'destination_id' => $dest, 'date_from' => '2026-12-01', 'date_to' => '2026-12-05', 'status' => 'active', 'created_at' => $now]);
ins('going', ['user_id' => $dana, 'destination_id' => $dest, 'date_from' => '2026-12-01', 'date_to' => '2026-12-05', 'created_at' => $now]);
ins('visits', ['user_id' => $dana, 'destination_id' => $dest, 'created_at' => $now]);
ins('user_countries', ['user_id' => $dana, 'country' => 'PT', 'created_at' => $now]);
ins('profile_interests', ['user_id' => $dana, 'interest' => 'food']);
ins('visitor_answers', ['user_id' => $dana, 'question' => 'q', 'answer' => 'a', 'created_at' => $now]);
ins('held_work', ['user_id' => $dana, 'payload' => '{"draft":"dana"}', 'created_at' => $now]);
ins('hidden_content', ['user_id' => $dana, 'target_type' => 'post', 'target_id' => $omarPost, 'created_at' => $now]);
$badge = ins('badges', ['slug' => 'b1', 'name' => 'B', 'description' => 'd', 'icon' => 'x']);
ins('user_badges', ['user_id' => $dana, 'badge_id' => $badge, 'awarded_at' => $now]);
ins('recognitions', ['user_id' => $dana, 'kind' => 'first', 'destination_id' => $dest, 'earned_at' => $now]);
ins('taggings', ['tag_id' => ins('tags', ['name' => 'beach', 'created_at' => $now]), 'target_type' => 'trip', 'target_id' => $tripSolo, 'created_at' => $now]);
ins('place_suggestions', ['name' => 'Bar', 'city' => 'Lisbon', 'type' => 'bar', 'suggested_by' => $dana, 'status' => 'pending', 'created_at' => $now]);
ins('feedback', ['kind' => 'closed', 'place_id' => $place, 'message' => 'it closed', 'contact_email' => 'dana@example.com', 'reported_by' => $dana, 'status' => 'open', 'created_at' => $now]);
$repAbout = ins('reports', ['reporter_id' => $omar, 'target_type' => 'user', 'target_id' => $dana, 'reason' => 'spam', 'status' => 'open', 'created_at' => $now]);
$repBy = ins('reports', ['reporter_id' => $dana, 'target_type' => 'post', 'target_id' => $omarPost, 'reason' => 'spam', 'status' => 'open', 'created_at' => $now]);
ins('moderation_log', ['actor_id' => $admin, 'target_type' => 'user', 'target_id' => $dana, 'action' => 'warn', 'created_at' => $now]);

// Every table that has a member column must be covered by this test. A new table added later
// without a decision in RMT_ACCOUNT_DELETE_INVENTORY fails here.
$userCols = ['user_id', 'author_id', 'actor_id', 'owner_id', 'host_id', 'sender_id', 'reporter_id',
             'follower_id', 'followee_id', 'blocker_id', 'blocked_id', 'from_user_id', 'to_user_id',
             'created_by', 'added_by', 'suggested_by', 'uploaded_by', 'reported_by', 'invited_by',
             'decided_by', 'resolved_by', 'user_lo_id', 'user_hi_id'];
$tables = array_column(q_all("SELECT name FROM sqlite_master WHERE type='table'"), 'name');
$linked = [];
foreach ($tables as $t) {
    foreach (q_all("PRAGMA table_info(\"$t\")") as $c) {
        if (in_array($c['name'], $userCols, true)) $linked[$t][] = $c['name'];
    }
}
$undecided = array_diff(array_keys($linked), array_keys(RMT_ACCOUNT_DELETE_INVENTORY), ['contribution_events']);
ok('every table with a member column has a recorded decision', !$undecided, implode(',', $undecided));

// --- Staff cannot delete themselves -------------------------------------------------------
$r = rmt_account_delete($admin);
ok('admin self deletion is refused', !$r['ok'] && cnt('SELECT COUNT(*) c FROM users WHERE id=? AND status=?', [$admin, 'active']) === 1);
ok('settings explain why for staff', rmt_account_delete_blocked_reason(q_one('SELECT * FROM users WHERE id=?', [$admin])) !== null
    && rmt_account_delete_blocked_reason(q_one('SELECT * FROM users WHERE id=?', [$dana])) === null);

// --- Delete Dana ----------------------------------------------------------------------------
$res = rmt_account_delete($dana);
ok('deletion succeeds', $res['ok'] ?? false, json_encode($res));
ok('returns the old address for the confirmation email', ($res['email'] ?? '') === 'dana@example.com');

$t = q_one('SELECT * FROM users WHERE id = ?', [$dana]);
ok('tombstone status deleted', $t['status'] === 'deleted');
ok('tombstone holds no email, birthdate, google link or password',
    !str_contains((string) $t['email'], 'dana@') && $t['birthdate'] === null && $t['google_sub'] === null
    && $t['email_verified_at'] === null && !password_verify('correct horse', (string) $t['password_hash']));
ok('tombstone username can never be registered', !preg_match('/^[a-zA-Z0-9_]{3,24}$/', (string) $t['username'])
    && preg_match('/^[A-Za-z0-9_]+$/', (string) $t['username']) === 1);

// Nothing of hers left in any table except the retained/anonymized set.
$allowedRemaining = [
    'users' => 1,             // the tombstone
    'reports' => 1,           // the report she filed
    'comments' => 1,          // the one Omar replied to, emptied
    'messages' => 1,          // her message, now in Omar's conversation
    'conversations' => 1,     // that conversation
    'media' => 0,             // swept after commit
];
foreach ($linked as $table => $cols) {
    $where = implode(' OR ', array_map(fn($c) => "\"$c\" = ?", $cols));
    $n = cnt("SELECT COUNT(*) c FROM \"$table\" WHERE $where", array_fill(0, count($cols), $dana));
    if ($table === 'users') $n = cnt('SELECT COUNT(*) c FROM users WHERE id = ? OR invited_by = ?', [$dana, $dana]);
    $max = $allowedRemaining[$table] ?? 0;
    ok("no rows of hers left in {$table}" . ($max ? " beyond {$max} kept" : ''), $n <= $max, "found $n");
}
// Her personal strings appear nowhere in the database.
$needles = ['secret bio of dana', 'dana@example.com', 'my private diary', 'dana notes', 'dana words',
            'dana comment with my phone', 'dana lonely', 'dana note', '"draft":"dana"', 'Dana Person', 'g-123'];
$hits = [];
foreach ($tables as $tb) {
    if (str_ends_with($tb, '_fts') || str_contains($tb, '_fts_') || $tb === 'sqlite_sequence') continue;
    $cols = array_column(q_all("PRAGMA table_info(\"$tb\")"), 'name');
    foreach ($cols as $c) {
        foreach ($needles as $nd) {
            if (cnt("SELECT COUNT(*) c FROM \"$tb\" WHERE CAST(\"$c\" AS TEXT) LIKE ?", ['%' . $nd . '%']) > 0) $hits[] = "$tb.$c~$nd";
        }
    }
}
ok('none of her personal text remains in any table', !$hits, implode(' ', $hits));
ok('her media files are gone, legacy upload too', cnt("SELECT COUNT(*) c FROM media WHERE storage_key IN ('av_dana','trip_dana','rev_dana','post_dana','act_dana','legacy_dana')") === 0);
ok('her sessions are gone, Omar keeps his', cnt("SELECT COUNT(*) c FROM sessions WHERE id='sessdana'") === 0
    && cnt("SELECT COUNT(*) c FROM sessions WHERE id='sessomar'") === 1);
ok('alerts signed up by her address are gone', cnt('SELECT COUNT(*) c FROM match_alerts') === 0);
ok('place she added stays, unlinked', cnt('SELECT COUNT(*) c FROM places WHERE id=? AND created_by IS NULL', [$place]) === 1);
ok('feedback stays without her address', cnt('SELECT COUNT(*) c FROM feedback WHERE contact_email IS NULL AND reported_by IS NULL') === 1);
ok('likes and tags on her deleted content are cleaned', cnt("SELECT COUNT(*) c FROM likes WHERE target_type='post' AND target_id=?", [$post]) === 0
    && cnt('SELECT COUNT(*) c FROM taggings') === 0);

// Retained rows carry no personal data.
$kept = q_one('SELECT * FROM comments WHERE id = ?', [$cReplied]);
ok('replied to comment kept hidden and empty', $kept && $kept['body'] === '' && $kept['status'] === 'removed');
ok('her unreplied comment is deleted', cnt('SELECT COUNT(*) c FROM comments WHERE id=?', [$cDanaLeaf]) === 0);
ok('reports about her and by her are retained', cnt('SELECT COUNT(*) c FROM reports WHERE id IN (?,?)', [$repAbout, $repBy]) === 2);
ok('moderation history retained', cnt('SELECT COUNT(*) c FROM moderation_log WHERE target_id=?', [$dana]) === 1);

// Omar's data is intact.
ok("Omar's account and profile intact", cnt("SELECT COUNT(*) c FROM users u JOIN profiles p ON p.user_id=u.id WHERE u.id=? AND u.status='active' AND p.bio='secret bio of omar'", [$omar]) === 1);
ok("Omar's review, post, group post, buddy post intact",
    cnt('SELECT COUNT(*) c FROM reviews WHERE id=?', [$omarReview]) === 1 && cnt('SELECT COUNT(*) c FROM posts WHERE id IN (?,?)', [$omarPost, $omarGroupPost]) === 2
    && cnt('SELECT COUNT(*) c FROM buddy_posts WHERE id=?', [$omarBuddy]) === 1);
ok("Omar's comments intact, reply still under the kept parent",
    cnt('SELECT COUNT(*) c FROM comments WHERE id=? AND parent_id=? AND body=?', [$cOmarReply, $cReplied, 'omar reply']) === 1
    && cnt('SELECT COUNT(*) c FROM comments WHERE id=?', [$cOmarTop]) === 1);
ok('conversation intact for Omar, both messages present',
    cnt('SELECT COUNT(*) c FROM messages WHERE id IN (?,?)', [$mDana, $mOmar]) === 2);
ok('shared trip passed to Omar, emptied of her notes and cover',
    cnt("SELECT COUNT(*) c FROM trips WHERE id=? AND user_id=? AND body='' AND cover_url IS NULL", [$tripShared, $omar]) === 1);
ok('her host profile and every reference by or about her are gone',
    cnt('SELECT COUNT(*) c FROM host_profiles WHERE user_id=?', [$dana]) === 0
    && cnt('SELECT COUNT(*) c FROM member_references WHERE from_user_id=? OR to_user_id=?', [$dana, $dana]) === 0);
ok('her trip chat lines are gone and those of Omar stay',
    cnt("SELECT COUNT(*) c FROM trip_messages WHERE body LIKE 'Dana%'") === 0
    && cnt("SELECT COUNT(*) c FROM trip_messages WHERE trip_id=? AND user_id=?", [$tripShared, $omar]) === 1);
ok("Omar's activity and photo on the shared trip intact", cnt('SELECT COUNT(*) c FROM trip_activities WHERE id=?', [$actOmar]) === 1
    && cnt("SELECT COUNT(*) c FROM trip_photos WHERE storage_key='omar_trip'") === 1
    && cnt("SELECT COUNT(*) c FROM media WHERE storage_key IN ('omar_trip','av_omar')") === 2);
ok('her solo trip is deleted', cnt('SELECT COUNT(*) c FROM trips WHERE id=?', [$tripSolo]) === 0);
ok('group collection passed to Omar as owner', cnt("SELECT COUNT(*) c FROM collections c JOIN collection_members m ON m.collection_id=c.id AND m.user_id=c.user_id AND m.role='owner' WHERE c.id=? AND c.user_id=?", [$collGroup, $omar]) === 1);
ok('her solo collection is deleted', cnt('SELECT COUNT(*) c FROM collections WHERE id=?', [$collSolo]) === 0);
ok("Omar's unrelated notification kept, the one she caused removed",
    cnt('SELECT COUNT(*) c FROM notifications WHERE user_id=?', [$omar]) === 1);
ok('Omar invited_by link to her cleared', q_one('SELECT invited_by FROM users WHERE id=?', [$omar])['invited_by'] === null);

// Cannot sign in; email and username are free.
ok('cannot sign in with old email and password', attempt_login('dana@example.com', 'correct horse') === false);
ok('cannot sign in with the tombstone address', attempt_login((string) $t['email'], '!') === false);
ok('old email and username are free for a new account',
    !q_one('SELECT id FROM users WHERE email = ?', ['dana@example.com']) && !q_one('SELECT id FROM users WHERE username = ?', ['dana']));

// Idempotent.
$again = rmt_account_delete($dana);
ok('second run is harmless', ($again['ok'] ?? false) && !empty($again['already']));

// When Omar deletes too, the conversation goes.
$r2 = rmt_account_delete($omar);
ok('Omar can delete too', $r2['ok'] ?? false);
ok('conversation with nobody left is deleted', cnt('SELECT COUNT(*) c FROM conversations WHERE id=?', [$conv]) === 0
    && cnt('SELECT COUNT(*) c FROM messages WHERE conversation_id=?', [$conv]) === 0);

// Deletion links expire in 30 minutes and are single use.
$boss2 = $mkUser('erin');
$raw = rmt_token_issue($boss2, RMT_ACCOUNT_DELETE_TOKEN_KIND);
$row = rmt_token_lookup($raw, RMT_ACCOUNT_DELETE_TOKEN_KIND);
ok('deletion link valid for 30 minutes', $row && abs(strtotime((string) $row['expires_at']) - time() - RMT_TOKEN_DELETE_TTL) < 5);
ok('deletion link works once', rmt_token_consume((int) $row['id']) && !rmt_token_lookup($raw, RMT_ACCOUNT_DELETE_TOKEN_KIND));

// Source checks on the controller: POST only, CSRF, rate limited, password or link required.
$src = file_get_contents(BASE_PATH . '/app/account_delete.php');
$routes = file_get_contents(BASE_PATH . '/public/index.php');
ok('deletion route is POST only', str_contains($routes, "['POST', '#^/account/delete$#'") && !preg_match("~'GET',\s*'#\^/account/delete\\$#',\s*'account_delete_submit'~", $routes));
preg_match('~function account_delete_submit\(.*?\n}\n~s', $src, $m);
$fn = $m[0] ?? '';
ok('submit checks CSRF, rate limit, username and password or link',
    str_contains($fn, 'csrf_check()') && str_contains($fn, 'rmt_rate_ok(') && str_contains($fn, 'confirm_username')
    && str_contains($fn, 'password_verify(') && str_contains($fn, 'rmt_token_consume('));

@unlink($dbFile);
echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
