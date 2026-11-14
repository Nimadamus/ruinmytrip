<?php
/**
 * "Right now" check-ins and the city room they open (migration 121).
 *
 * A check-in says "I am in this city until <time>". While it is live its holder is counted among
 * the travelers in town and can read and post in that city's room. The moment it expires they are
 * out of both, and so is everything they said there: the room is for people who are in the city
 * today, not a history of who passed through.
 *
 * The rule that makes expiry reliable: LIVE is always computed, never stored.
 *
 *     live  =  ended_at IS NULL AND expires_at > now
 *
 * Every read in this file carries that test (RMT_CHECKIN_LIVE), so nothing depends on a job having
 * run. rmt_checkin_sweep() is housekeeping: it stamps ended_at on rows that are already dead to
 * every reader, and deletes room messages past the retention window.
 *
 * Every function takes an optional $now ('Y-m-d H:i:s') so a test can move the clock.
 */
declare(strict_types=1);

/** Shortest and longest a check-in can last, in hours, and what "I'm here" means with no answer. */
const RMT_CHECKIN_MIN_HOURS = 1;
const RMT_CHECKIN_MAX_HOURS = 168;
const RMT_CHECKIN_DEFAULT_HOURS = 24;
/** What the form offers. Hours => words. */
const RMT_CHECKIN_CHOICES = [3 => 'A few hours', 12 => 'Today', 24 => '24 hours', 72 => '3 days', 168 => 'A week'];
const RMT_CITY_ROOM_BODY_MAX = 1000;
/** Messages are kept this long after they stop being shown, for moderation, then deleted. */
const RMT_CITY_ROOM_RETAIN_DAYS = 30;
/** The live test, for a table aliased `c`. Takes one bound parameter: now. */
const RMT_CHECKIN_LIVE = 'c.ended_at IS NULL AND c.expires_at > ?';

function rmt_checkin_now(?string $now = null): string { return $now ?? date('Y-m-d H:i:s'); }

/** "5 hours", "40 minutes", "2 days": how long a check-in has left, in words. */
function rmt_checkin_left(string $expiresAt, ?string $now = null): string {
    $s = max(0, (strtotime($expiresAt) ?: 0) - strtotime(rmt_checkin_now($now)));
    if ($s < 90) return 'a minute';
    if ($s < 5400) return (int) round($s / 60) . ' minutes';
    if ($s < 129600) { $h = (int) round($s / 3600); return $h . ($h === 1 ? ' hour' : ' hours'); }
    $d = (int) round($s / 86400);
    return $d . ($d === 1 ? ' day' : ' days');
}

/** The member's live check-in, or null. An expired row the sweep has not reached yet is null. */
function rmt_checkin_live(int $userId, ?string $now = null): ?array {
    if ($userId < 1) return null;
    return q_one('SELECT c.*, d.name dest_name, d.slug dest_slug FROM active_checkins c
                  JOIN destinations d ON d.id = c.destination_id
                  WHERE c.user_id = ? AND ' . RMT_CHECKIN_LIVE . ' ORDER BY c.id DESC LIMIT 1',
                 [$userId, rmt_checkin_now($now)]);
}

/**
 * Check in to a city. Any check-in the member still holds is closed first, so there is only ever
 * one: 'moved' if it was still live, 'expired' if it had simply run out.
 *
 * @return array{ok:bool, id?:int, expires_at?:string, error?:string}
 */
function rmt_checkin_start(int $userId, int $destinationId, int $hours = RMT_CHECKIN_DEFAULT_HOURS,
                           ?int $neighborhoodId = null, string $note = '', ?string $now = null): array {
    $now = rmt_checkin_now($now);
    if ($userId < 1) return ['ok' => false, 'error' => 'Sign in to check in.'];
    if (!q_one('SELECT 1 FROM destinations WHERE id = ?', [$destinationId])) {
        return ['ok' => false, 'error' => 'Pick a city to check in to.'];
    }
    if ($neighborhoodId !== null
        && !q_one('SELECT 1 FROM neighborhoods WHERE id = ? AND destination_id = ?', [$neighborhoodId, $destinationId])) {
        $neighborhoodId = null;   // a neighborhood of some other city is dropped, not an error
    }
    $hours = max(RMT_CHECKIN_MIN_HOURS, min(RMT_CHECKIN_MAX_HOURS, $hours));
    $expires = date('Y-m-d H:i:s', strtotime($now) + $hours * 3600);
    $note = mb_substr(trim($note), 0, 200);

    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        /* Lock this member's row so two taps cannot both pass the "no open check-in" step and
           then collide on the one-open-per-person index. */
        q_run('UPDATE users SET status = status WHERE id = ?', [$userId]);
        q_run("UPDATE active_checkins
                  SET end_reason = CASE WHEN expires_at > ? THEN 'moved' ELSE 'expired' END,
                      ended_at   = CASE WHEN expires_at > ? THEN ? ELSE expires_at END
                WHERE user_id = ? AND ended_at IS NULL", [$now, $now, $now, $userId]);
        $id = (int) q_run('INSERT INTO active_checkins (user_id, destination_id, neighborhood_id, note, created_at, expires_at)
                           VALUES (?,?,?,?,?,?)', [$userId, $destinationId, $neighborhoodId, $note === '' ? null : $note, $now, $expires]);
        rmt_city_room_id($destinationId, $now);
        if ($own) $pdo->commit();
    } catch (\Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true, 'id' => $id, 'expires_at' => $expires];
}

/** Leave early. True when there was a live check-in to end. */
function rmt_checkin_end(int $userId, string $reason = 'checked_out', ?string $now = null): bool {
    $now = rmt_checkin_now($now);
    if (!in_array($reason, ['checked_out', 'moderated'], true)) $reason = 'checked_out';
    $st = db()->prepare('UPDATE active_checkins SET ended_at = ?, end_reason = ?
                          WHERE user_id = ? AND ended_at IS NULL AND expires_at > ?');
    $st->execute([$now, $reason, $userId, $now]);
    return $st->rowCount() > 0;
}

/** The city's room, made the first time somebody checks in there. */
function rmt_city_room_id(int $destinationId, ?string $now = null): int {
    $row = q_one('SELECT id FROM city_rooms WHERE destination_id = ?', [$destinationId]);
    if ($row) return (int) $row['id'];
    /* Prepared directly, not q_run(): q_run asks Postgres for lastval() after an INSERT, and when
       the conflict clause inserts nothing inside a transaction that can abort it (see app/db.php). */
    db()->prepare('INSERT INTO city_rooms (destination_id, created_at) VALUES (?,?) ON CONFLICT (destination_id) DO NOTHING')
        ->execute([$destinationId, rmt_checkin_now($now)]);
    return (int) (q_one('SELECT id FROM city_rooms WHERE destination_id = ?', [$destinationId])['id'] ?? 0);
}

/** How many active members are checked in to a city right now. Safe to show to anybody. */
function rmt_city_room_count(int $destinationId, ?string $now = null): int {
    return (int) (q_one("SELECT COUNT(DISTINCT c.user_id) n FROM active_checkins c
                         JOIN users u ON u.id = c.user_id AND u.status = 'active'
                         WHERE c.destination_id = ? AND " . RMT_CHECKIN_LIVE,
                        [$destinationId, rmt_checkin_now($now)])['n'] ?? 0);
}

/** May this member read and post in this city's room? Only while checked in there. */
function rmt_city_room_member(int $userId, int $destinationId, ?string $now = null): ?array {
    $c = rmt_checkin_live($userId, $now);
    return ($c && (int) $c['destination_id'] === $destinationId) ? $c : null;
}

/**
 * Who is in the room, soonest to leave last. Members only, and never somebody the viewer has
 * blocked or been blocked by.
 *
 * @return list<array<string,mixed>>
 */
function rmt_city_room_roster(int $destinationId, int $viewerId, ?string $now = null, int $limit = 100): array {
    $now = rmt_checkin_now($now);
    if (!rmt_city_room_member($viewerId, $destinationId, $now)) return [];
    $rows = q_all("SELECT c.id checkin_id, c.user_id, c.neighborhood_id, c.note, c.created_at, c.expires_at,
                          u.username, p.display_name, p.avatar_url
                     FROM active_checkins c
                     JOIN users u ON u.id = c.user_id AND u.status = 'active'
                     LEFT JOIN profiles p ON p.user_id = c.user_id
                    WHERE c.destination_id = ? AND " . RMT_CHECKIN_LIVE . '
                    ORDER BY c.created_at DESC, c.id DESC LIMIT ' . max(1, min(500, $limit)),
                  [$destinationId, $now]);
    return array_values(array_filter($rows, static fn(array $r): bool =>
        (int) $r['user_id'] === $viewerId || !rmt_is_blocked($viewerId, (int) $r['user_id'])));
}

/**
 * Say something in the room. The author must hold a live check-in for this city.
 *
 * @return array{ok:bool, id?:int, error?:string}
 */
function rmt_city_room_post(int $userId, int $destinationId, string $body, ?string $now = null): array {
    $now = rmt_checkin_now($now);
    $c = rmt_city_room_member($userId, $destinationId, $now);
    if (!$c) return ['ok' => false, 'error' => 'Check in to this city to join its room.'];
    $body = trim($body);
    if ($body === '') return ['ok' => false, 'error' => 'Write something first.'];
    if (mb_strlen($body) > RMT_CITY_ROOM_BODY_MAX) {
        return ['ok' => false, 'error' => 'Keep it under ' . RMT_CITY_ROOM_BODY_MAX . ' characters.'];
    }
    $roomId = rmt_city_room_id($destinationId, $now);
    $id = (int) q_run('INSERT INTO city_room_messages (room_id, user_id, checkin_id, body, created_at) VALUES (?,?,?,?,?)',
                      [$roomId, $userId, (int) $c['id'], $body, $now]);
    return ['ok' => true, 'id' => $id];
}

/**
 * The room's messages, oldest first, newer than $afterId. Members only. A message is shown only
 * while the check-in it was written under is live, so a traveler who has left takes their
 * messages with them the second they expire, sweep or no sweep.
 *
 * @return list<array<string,mixed>>
 */
function rmt_city_room_feed(int $destinationId, int $viewerId, int $afterId = 0, ?string $now = null, int $limit = 100): array {
    $now = rmt_checkin_now($now);
    if (!rmt_city_room_member($viewerId, $destinationId, $now)) return [];
    $limit = max(1, min(200, $limit));
    $rows = q_all("SELECT m.id, m.user_id, m.body, m.created_at, u.username, p.display_name, p.avatar_url
                     FROM city_room_messages m
                     JOIN city_rooms r ON r.id = m.room_id
                     JOIN active_checkins c ON c.id = m.checkin_id
                     JOIN users u ON u.id = m.user_id AND u.status = 'active'
                     LEFT JOIN profiles p ON p.user_id = m.user_id
                    WHERE r.destination_id = ? AND m.id > ? AND m.hidden_at IS NULL AND " . RMT_CHECKIN_LIVE . "
                    ORDER BY m.id DESC LIMIT $limit",
                  [$destinationId, max(0, $afterId), $now]);
    $rows = array_filter($rows, static fn(array $r): bool =>
        (int) $r['user_id'] === $viewerId || !rmt_is_blocked($viewerId, (int) $r['user_id']));
    return array_reverse(array_values($rows));
}

/**
 * Housekeeping, run by GET /cron/checkins. Safe to run as often or as rarely as anybody likes:
 * no page reads the things it changes.
 *
 *  - stamps ended_at on check-ins whose time has passed (ended_at = the moment they expired, not
 *    the moment the job got to them)
 *  - deletes room messages older than the retention window
 *  - stamps completed_at on buddy trips whose last day has passed
 *
 * @return array{expired:int, messages_deleted:int, trips_completed:int}
 */
function rmt_checkin_sweep(?string $now = null): array {
    $now = rmt_checkin_now($now);
    $pdo = db();

    $st = $pdo->prepare("UPDATE active_checkins SET ended_at = expires_at, end_reason = 'expired'
                          WHERE ended_at IS NULL AND expires_at <= ?");
    $st->execute([$now]);
    $expired = $st->rowCount();

    $cut = date('Y-m-d H:i:s', strtotime($now) - RMT_CITY_ROOM_RETAIN_DAYS * 86400);
    $st = $pdo->prepare('DELETE FROM city_room_messages WHERE created_at < ?');
    $st->execute([$cut]);
    $deleted = $st->rowCount();

    return ['expired' => $expired, 'messages_deleted' => $deleted, 'trips_completed' => rmt_buddy_complete_past($now)];
}

/* ---------- controllers ---------- */

/** POST /checkin: destination_id, hours (optional), neighborhood_id (optional), note (optional). */
function checkin_start(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $back = rmt_return_to('/buddies');
    if (!can_host_meetups($me)) rmt_adult_gate($me, $back, 'Checking in to meet travelers is 18+.', $back);
    if (!rmt_rate_ok('checkin', (string) $me['id'], 10, 3600)) { flash('Slow down a little and try again soon.'); redirect($back); }
    $hours = (int) input('hours', (string) RMT_CHECKIN_DEFAULT_HOURS);
    $hood = (int) input('neighborhood_id');
    $dest = (int) input('destination_id');
    $r = rmt_checkin_start((int) $me['id'], $dest, $hours > 0 ? $hours : RMT_CHECKIN_DEFAULT_HOURS,
                           $hood > 0 ? $hood : null, (string) input('note'));
    if (!$r['ok']) { flash($r['error']); redirect($back); }
    /* One way to say "I am here": the check-in opens the room, and the same act lists the member
       as in town for travelers searching the city (what the old "Already there?" card did). */
    if (function_exists('rmt_here_now_publish')) rmt_here_now_publish((int) $me['id'], $dest, substr($r['expires_at'], 0, 10));
    flash('You are checked in for the next ' . rmt_checkin_left($r['expires_at']) . '. This is the room for travelers who are here right now.');
    redirect('/city-room/' . $dest);
}

/** POST /checkin/end */
function checkin_end(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    flash(rmt_checkin_end((int) $me['id']) ? 'You are checked out.' : 'You were not checked in anywhere.');
    redirect(rmt_return_to('/buddies'));
}

/**
 * GET /city-room/{destination id}: the room for a member, the way in for anybody else signed in.
 * Never indexed: it is a private conversation between people who are in a city today.
 */
function city_room(array $a): void {
    require_login(); $me = current_user();
    $d = q_one('SELECT * FROM destinations WHERE id = ?', [(int) $a['id']]);
    if (!$d) not_found();
    $dest = (int) $d['id'];
    $checkin = rmt_city_room_member((int) $me['id'], $dest);
    view('city_room', [
        'd' => $d, 'checkin' => $checkin, 'count' => rmt_city_room_count($dest),
        'roster' => $checkin ? rmt_city_room_roster($dest, (int) $me['id']) : [],
        'lines' => $checkin ? rmt_city_room_feed($dest, (int) $me['id']) : [],
    ], ['title' => $d['name'] . ' travelers right now | RuinMyTrip', 'canonical' => '', 'robots' => 'noindex,nofollow']);
}

/** POST /city-room/{id}/message: say something, then back to the room. */
function city_room_post(array $a): void {
    require_verified_email(); csrf_check(); $me = current_user();
    $dest = (int) $a['id'];
    $back = '/city-room/' . $dest;
    if (!rmt_submit_ok('city_room_' . $dest, input('_submit'))) redirect($back);
    if (!rmt_rate_ok('city_room_post', (string) $me['id'], 60, 3600)) { flash('Slow down a little and try again soon.'); redirect($back); }
    $r = rmt_city_room_post((int) $me['id'], $dest, (string) input('body'));
    if (!$r['ok']) flash((string) $r['error']);
    redirect($back . (!empty($r['id']) ? '#m' . (int) $r['id'] : ''));
}

/**
 * GET /city-room/{id}/feed?after=<message id>: what the open page polls. Members only; 403 the
 * moment the check-in has expired, which is the page's cue to reload into the way-in view.
 */
function city_room_feed(array $a): void {
    require_login(); $me = current_user();
    $dest = (int) $a['id'];
    $meId = (int) $me['id'];
    $c = rmt_city_room_member($meId, $dest);
    if (!$c) rmt_city_room_json(['ok' => false, 'error' => 'Check in to this city to join its room.'], 403);
    rmt_city_room_json([
        'ok' => true,
        'count' => rmt_city_room_count($dest),
        /* Every line the reader may see right now. The page drops any line it is showing that is
           not in this list, which is how a traveler whose check-in ran out leaves an open room. */
        'ids' => array_map(static fn(array $m): int => (int) $m['id'], rmt_city_room_feed($dest, $meId, 0, null, 200)),
        'lines' => array_map(static fn(array $m): array => [
            'id'   => (int) $m['id'],
            'mine' => (int) $m['user_id'] === $meId,
            'who'  => (string) ($m['display_name'] ?: $m['username']),
            'html' => rmt_linkify_mentions(e((string) $m['body'])),
            'when' => ago((string) $m['created_at']),
            'report' => (int) $m['user_id'] === $meId ? null : url('report?target_type=room_message&target_id=' . (int) $m['id']),
        ], rmt_city_room_feed($dest, $meId, max(0, (int) input('after')))),
    ]);
}

function rmt_city_room_json(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** GET /cron/checkins */
function cron_checkins(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = rmt_cron_key_given();
    if ($key === '' || $given === '' || !hash_equals($key, $given)) not_found();
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $r = rmt_checkin_sweep();
    echo "expired={$r['expired']} messages_deleted={$r['messages_deleted']} trips_completed={$r['trips_completed']}\n";
    exit;
}
