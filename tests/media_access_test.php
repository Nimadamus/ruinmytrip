<?php
/**
 * Who may fetch a stored photo (rmt_media_access() in app/controllers.php, 2026-10-30).
 *
 * What must hold:
 *   - a photo on a private trip is refused to a stranger and to nobody signed in, served to the
 *     owner and to a member of the trip, and never marked cacheable by a shared cache.
 *   - a followers trip's photo reaches a follower and nobody else.
 *   - a photo on a public trip is served to anyone, without a shared cache (the trip can go private).
 *   - a photo on a deleted trip, or a removed photo, is refused to everyone but its owner.
 *   - a private activity's photo, and its album, are refused to a stranger.
 *   - a key on no trip and no activity (avatars, reviews, places) stays public and immutable.
 *
 *   php tests/media_access_test.php   (uses database/dev.sqlite inside a transaction it rolls back)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/database/dev.sqlite')) { echo "SKIP  no dev database\n"; exit(0); }
putenv('DATABASE_URL');
require $root . '/app/bootstrap.php';
require_once $root . '/app/controllers.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$now = date('Y-m-d H:i:s');
$pdo = db();
$pdo->beginTransaction();
try {
    $users = [];
    foreach (['owner', 'stranger', 'member', 'follower', 'mod'] as $u) {
        q_run("INSERT INTO users (username, email, password_hash, role, status, created_at) VALUES (?,?,?,?,?,?)",
              ['mediatest_' . $u, "mediatest_$u@example.invalid", 'x', $u === 'mod' ? 'mod' : 'member', 'active', $now]);
        $users[$u] = q_one('SELECT * FROM users WHERE username = ?', ['mediatest_' . $u]);
    }
    $oid = (int) $users['owner']['id'];
    $trip = function (string $vis, string $status = 'published') use ($oid, $now): int {
        $slug = 'mediatest-' . bin2hex(random_bytes(4));
        q_run('INSERT INTO trips (user_id, title, slug, status, visibility, created_at) VALUES (?,?,?,?,?,?)',
              [$oid, 'Media test', $slug, $status, $vis, $now]);
        return (int) q_one('SELECT id FROM trips WHERE slug = ?', [$slug])['id'];
    };
    $photo = function (int $tripId, string $status = 'published') use ($oid, $now): string {
        $key = bin2hex(random_bytes(16)) . '.jpg';
        q_run('INSERT INTO trip_photos (trip_id, user_id, url, storage_key, status, created_at) VALUES (?,?,?,?,?,?)',
              [$tripId, $oid, '/media/' . $key, $key, $status, $now]);
        return $key;
    };

    $priv = $photo($tid = $trip('private'));
    q_run("INSERT INTO trip_members (trip_id, user_id, role, state, created_at) VALUES (?,?,?,?,?)",
          [$tid, (int) $users['member']['id'], 'member', 'active', $now]);
    ok('private: signed out is refused', rmt_media_access($priv, null) === 'deny');
    ok('private: a stranger is refused', rmt_media_access($priv, $users['stranger']) === 'deny');
    ok('private: the owner gets it, never shared-cached', rmt_media_access($priv, $users['owner']) === 'private');
    ok('private: a trip member gets it', rmt_media_access($priv, $users['member']) === 'private');
    ok('private: no shared cache header', rmt_media_cache_header('private') === 'private, no-store');

    $fol = $photo($trip('followers'));
    q_run('INSERT INTO follows (follower_id, followee_id, created_at) VALUES (?,?,?)', [(int) $users['follower']['id'], $oid, $now]);
    ok('followers: a follower gets it', rmt_media_access($fol, $users['follower']) === 'private');
    ok('followers: a stranger is refused', rmt_media_access($fol, $users['stranger']) === 'deny');
    ok('followers: signed out is refused', rmt_media_access($fol, null) === 'deny');

    $pub = $photo($trip('public'));
    ok('public trip: signed out gets it', rmt_media_access($pub, null) === 'trip');
    ok('public trip: no shared cache', !str_contains(rmt_media_cache_header('trip'), 'public'));

    $gone = $photo($trip('public', 'deleted'));
    ok('deleted trip: signed out is refused', rmt_media_access($gone, null) === 'deny');
    ok('deleted trip: the owner still gets it', rmt_media_access($gone, $users['owner']) === 'private');
    $removed = $photo($trip('public'), 'removed');
    ok('removed photo: a stranger is refused', rmt_media_access($removed, $users['stranger']) === 'deny');
    ok('removed photo: a moderator gets it', rmt_media_access($removed, $users['mod']) === 'private');

    $atrip = $trip('public');
    $akey = bin2hex(random_bytes(16)) . '.jpg';
    q_run("INSERT INTO trip_activities (trip_id, user_id, title, storage_key, visibility, status, created_at) VALUES (?,?,?,?,?,?,?)",
          [$atrip, $oid, 'Private dinner', $akey, 'private', 'published', $now]);
    $aid = (int) q_one('SELECT id FROM trip_activities WHERE storage_key = ?', [$akey])['id'];
    $album = bin2hex(random_bytes(16)) . '.jpg';
    q_run('INSERT INTO activity_photos (activity_id, user_id, url, storage_key, status, created_at) VALUES (?,?,?,?,?,?)',
          [$aid, $oid, '/media/' . $album, $album, 'published', $now]);
    ok('private activity photo: a stranger is refused', rmt_media_access($akey, $users['stranger']) === 'deny');
    ok('private activity album: signed out is refused', rmt_media_access($album, null) === 'deny');
    ok('private activity album: the owner gets it', rmt_media_access($album, $users['owner']) === 'private');

    $loose = bin2hex(random_bytes(16)) . '.jpg';
    ok('a key on no trip stays public', rmt_media_access($loose, null) === 'public');
    ok('public keys stay immutable', rmt_media_cache_header('public') === 'public, max-age=31536000, immutable');
} finally {
    $pdo->rollBack();
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
