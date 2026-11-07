<?php
declare(strict_types=1);

/**
 * Stay With a Local: asking an approved host for a free place to stay.
 *
 * The flow, each step a decision by one person:
 *
 *   guest asks   dates, how many people, and an introduction. Only to an approved host who is not
 *                paused, only within the host's own limits, and only by an adult with a confirmed
 *                email and a profile that says something about them.
 *   host decides yes or no. A no is not explained to anybody.
 *   guest confirms after a yes. That is the stay. Private messages open at the yes, and that is
 *                where the two agree where to meet: no address is ever stored here.
 *   either side  can cancel before the first night, or report the stay at any time.
 *   afterwards   once the last night has passed, each can leave a reference (app/hosts_locals.php).
 *
 * Hosting is free. There is no price, payment, deposit or fee anywhere in this file, on purpose.
 */

const RMT_STAY_INTRO_MIN = 50;
const RMT_STAY_INTRO_MAX = 1500;
/** Open requests one guest may have waiting at once, and new requests per day. */
const RMT_STAY_OPEN_MAX = 3;
const RMT_STAY_PER_DAY = 5;
/** How far ahead a stay may be asked for. */
const RMT_STAY_MAX_AHEAD_DAYS = 365;

function rmt_stay_nights(string $from, string $to): int {
    return (int) round((strtotime($to) - strtotime($from)) / 86400);
}

function rmt_stay(int $id): ?array {
    return $id > 0 ? q_one("SELECT s.*, g.username guest_username, g.status guest_status, h.username host_username, h.status host_status,
                                   gp.display_name guest_name, gp.avatar_url guest_avatar, hp.display_name host_name, hp.avatar_url host_avatar,
                                   hpr.neighborhood, hpr.space_type, hpr.max_guests, d.name dest_name, d.slug dest_slug
                              FROM stay_requests s
                              JOIN users g ON g.id = s.guest_id JOIN users h ON h.id = s.host_id
                         LEFT JOIN profiles gp ON gp.user_id = s.guest_id LEFT JOIN profiles hp ON hp.user_id = s.host_id
                         LEFT JOIN host_profiles hpr ON hpr.user_id = s.host_id
                         LEFT JOIN destinations d ON d.id = hpr.destination_id
                             WHERE s.id = ?", [$id]) : null;
}

/** Is this person the guest or the host of this stay. */
function rmt_stay_party(?array $stay, ?array $u): ?string {
    if (!$stay || !$u) return null;
    if ((int) $stay['guest_id'] === (int) $u['id']) return 'guest';
    if ((int) $stay['host_id'] === (int) $u['id']) return 'host';
    return null;
}

/** Why this member may not ask this host, or null when they may. */
function rmt_stay_blocked_reason(?array $guest, ?array $host): ?string {
    if (!rmt_stays_enabled()) return 'Stay requests are not open.';
    if (rmt_hosting_paused()) return RMT_HOSTING_PAUSED_NOTE;
    if (!$guest) return 'Sign in first.';
    if (!$host) return 'This member is not hosting.';
    if ((int) $guest['id'] === (int) $host['user_id']) return 'That is your own host profile.';
    if (($host['status'] ?? '') !== 'approved') return 'This member is not taking stay requests right now.';
    if (empty($guest['email_verified_at'])) return 'Confirm your email address first.';
    if (!can_host_meetups($guest)) return 'Stays are for members 18 and over.';
    if (rmt_is_blocked((int) $guest['id'], (int) $host['user_id'])) return 'This member is not available.';
    $p = q_one('SELECT display_name, bio FROM profiles WHERE user_id = ?', [(int) $guest['id']]);
    if (!$p || trim((string) ($p['display_name'] ?? '')) === '' || mb_strlen(trim((string) ($p['bio'] ?? ''))) < 20) {
        return 'Add your name and a sentence about yourself to your profile first, so a host knows who is asking.';
    }
    if (q_one("SELECT 1 FROM stay_requests WHERE guest_id = ? AND host_id = ? AND state IN ('requested','accepted')", [(int) $guest['id'], (int) $host['user_id']])) {
        return 'You already have an open request with this host.';
    }
    $open = (int) q_one("SELECT COUNT(*) n FROM stay_requests WHERE guest_id = ? AND state = 'requested'", [(int) $guest['id']])['n'];
    if ($open >= RMT_STAY_OPEN_MAX) return 'You have ' . $open . ' requests waiting for an answer. Wait for one, or cancel one, before asking again.';
    return null;
}

/** @return array{ok:bool, errors:list<string>, data:array} */
function rmt_stay_validate(array $in, array $host): array {
    $errors = [];
    $date = static fn(string $k): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in[$k] ?? '')) ? (string) $in[$k] : '';
    $from = $date('date_from'); $to = $date('date_to');
    $today = date('Y-m-d');
    if ($from === '' || $to === '') $errors[] = 'Pick the night you arrive and the day you leave.';
    elseif ($from <= $today) $errors[] = 'The first night must be after today.';
    elseif ($from > date('Y-m-d', strtotime('+' . RMT_STAY_MAX_AHEAD_DAYS . ' days'))) $errors[] = 'Ask no more than a year ahead.';
    elseif ($to <= $from) $errors[] = 'The day you leave must be after the day you arrive.';
    else {
        $n = rmt_stay_nights($from, $to);
        if ($n < (int) $host['min_nights'] || $n > (int) $host['max_nights']) {
            $errors[] = 'This host takes stays of ' . (int) $host['min_nights'] . ' to ' . (int) $host['max_nights'] . ' nights.';
        }
        if ($clash = rmt_host_unavailable_clash($host, $from, $to)) $errors[] = 'This host is not hosting ' . rmt_date_range_label($clash) . '. Pick other dates.';
    }
    $guests = (int) ($in['guests'] ?? 0);
    if ($guests < 1 || $guests > (int) $host['max_guests']) $errors[] = 'This host has room for up to ' . (int) $host['max_guests'] . ' ' . ((int) $host['max_guests'] === 1 ? 'guest' : 'guests') . '.';
    $intro = trim((string) ($in['intro'] ?? ''));
    if (mb_strlen($intro) < RMT_STAY_INTRO_MIN) $errors[] = 'Introduce yourself in at least ' . RMT_STAY_INTRO_MIN . ' characters: who you are and why you are coming.';
    if (mb_strlen($intro) > RMT_STAY_INTRO_MAX) $errors[] = 'Keep the introduction under ' . RMT_STAY_INTRO_MAX . ' characters.';
    if (empty($in['agree'])) $errors[] = 'Tick the box to confirm you have read the safety guide.';
    return ['ok' => !$errors, 'errors' => $errors, 'data' => ['date_from' => $from, 'date_to' => $to, 'guests' => $guests, 'intro' => $intro]];
}

function rmt_stay_notify(int $to, string $type, int $actor, int $stayId, string $subject, string $line): void {
    q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
          [$to, $type, $actor, 'stay', $stayId, date('Y-m-d H:i:s')]);
    rmt_notify_email_direct($to, $subject, $line, '/stay/' . $stayId, 'somebody answered about a stay');
}

/** @return array{ok:bool, error?:string, id?:int} */
function rmt_stay_create(array $guest, array $host, array $d): array {
    $why = rmt_stay_blocked_reason($guest, $host);
    if ($why) return ['ok' => false, 'error' => $why];
    if (!rmt_rate_ok('stay_request', (string) $guest['id'], RMT_STAY_PER_DAY, 86400)) {
        return ['ok' => false, 'error' => 'You have asked a lot of hosts today. Try again tomorrow.'];
    }
    $now = date('Y-m-d H:i:s');
    q_run("INSERT INTO stay_requests (guest_id, host_id, date_from, date_to, guests, intro, state, created_at, updated_at)
           VALUES (?,?,?,?,?,?, 'requested', ?, ?)",
          [(int) $guest['id'], (int) $host['user_id'], $d['date_from'], $d['date_to'], $d['guests'], $d['intro'], $now, $now]);
    $id = (int) q_one('SELECT MAX(id) id FROM stay_requests WHERE guest_id = ? AND host_id = ?', [(int) $guest['id'], (int) $host['user_id']])['id'];
    rmt_stay_notify((int) $host['user_id'], 'stay_requested', (int) $guest['id'], $id, 'Somebody asked to stay with you',
        '@' . $guest['username'] . ' asked to stay ' . rmt_stay_nights($d['date_from'], $d['date_to']) . ' nights from ' . date('M j', strtotime($d['date_from'])) . '.');
    if (function_exists('rmt_track')) rmt_track('stay_requested');
    return ['ok' => true, 'id' => $id];
}

/**
 * Move a stay to a new state, if this person may. Every allowed move is listed here and nowhere
 * else. @return array{ok:bool, error?:string}
 */
function rmt_stay_move(array $stay, array $actor, string $to): array {
    $party = rmt_stay_party($stay, $actor);
    $from = (string) $stay['state'];
    $started = (string) $stay['date_from'] <= date('Y-m-d');
    $allowed = [
        'accepted'  => $party === 'host'  && $from === 'requested' && !$started,
        'declined'  => $party === 'host'  && $from === 'requested',
        'confirmed' => $party === 'guest' && $from === 'accepted' && !$started,
        'cancelled' => $party !== null && in_array($from, ['requested', 'accepted', 'confirmed'], true) && !$started,
    ];
    if (empty($allowed[$to])) return ['ok' => false, 'error' => 'That cannot be changed now.'];
    if ($to === 'accepted' && rmt_is_blocked((int) $stay['guest_id'], (int) $stay['host_id'])) return ['ok' => false, 'error' => 'That cannot be changed now.'];
    $now = date('Y-m-d H:i:s');
    q_run('UPDATE stay_requests SET state = ?, updated_at = ?, decided_at = CASE WHEN ? IN (\'accepted\', \'declined\') THEN ? ELSE decided_at END,
                  cancelled_by = CASE WHEN ? = \'cancelled\' THEN ? ELSE cancelled_by END WHERE id = ? AND state = ?',
          [$to, $now, $to, $now, $to, (int) $actor['id'], (int) $stay['id'], $from]);
    $other = $party === 'host' ? (int) $stay['guest_id'] : (int) $stay['host_id'];
    $who = '@' . $actor['username'];
    $msg = [
        'accepted'  => ['Your stay request was accepted', $who . ' said yes. Confirm the stay, and message them to agree where to meet.'],
        'declined'  => ['About your stay request', 'Your stay request did not work out this time. You can ask another host.'],
        'confirmed' => ['A stay is confirmed', $who . ' confirmed the stay. Message them to agree the details.'],
        'cancelled' => ['A stay was cancelled', $who . ' cancelled the stay.'],
    ][$to];
    rmt_stay_notify($other, 'stay_' . $to, (int) $actor['id'], (int) $stay['id'], $msg[0], $msg[1]);
    if (function_exists('rmt_track')) rmt_track('stay_' . $to);
    return ['ok' => true];
}

/** A yes on a stay opens private messages, the same as any accepted request. */
function rmt_stay_mutual(int $a, int $b): bool {
    return (bool) q_one("SELECT 1 FROM stay_requests WHERE state IN ('accepted','confirmed')
                          AND ((guest_id = ? AND host_id = ?) OR (guest_id = ? AND host_id = ?)) LIMIT 1", [$a, $b, $b, $a]);
}

/** One member's stays, both sides, newest first. */
function rmt_stays_for(int $uid): array {
    return q_all("SELECT s.*, g.username guest_username, h.username host_username, gp.display_name guest_name, hp.display_name host_name,
                         gp.avatar_url guest_avatar, hp.avatar_url host_avatar
                    FROM stay_requests s JOIN users g ON g.id = s.guest_id JOIN users h ON h.id = s.host_id
               LEFT JOIN profiles gp ON gp.user_id = s.guest_id LEFT JOIN profiles hp ON hp.user_id = s.host_id
                   WHERE (s.guest_id = ? OR s.host_id = ?) ORDER BY s.id DESC LIMIT 60", [$uid, $uid]);
}

const RMT_STAY_STATE_LABELS = [
    'requested' => 'Waiting for the host',
    'accepted'  => 'Accepted, waiting for the guest to confirm',
    'declined'  => 'Did not work out',
    'confirmed' => 'Confirmed',
    'cancelled' => 'Cancelled',
];

/* ---------- controllers ---------- */

function rmt_stay_host_by_username(string $username): ?array {
    return q_one("SELECT h.*, u.username, u.status user_status, p.display_name, p.avatar_url, d.name dest_name
                    FROM host_profiles h JOIN users u ON u.id = h.user_id AND u.status = 'active'
               LEFT JOIN profiles p ON p.user_id = h.user_id LEFT JOIN destinations d ON d.id = h.destination_id
                   WHERE u.username = ?", [$username]);
}

/** GET /stay/new/{username} */
function stay_new_form(array $a): void {
    require_login();
    $me = current_user();
    $host = rmt_stay_host_by_username((string) $a['username']);
    if (!$host || $host['status'] !== 'approved') not_found();
    $d = static fn(string $k): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET[$k] ?? '')) ? (string) $_GET[$k] : '';
    view('stay_new', ['host' => $host, 'why' => rmt_stay_blocked_reason($me, $host), 'errors' => [], 'old' => ['date_from' => $d('from'), 'date_to' => $d('to')]],
         rmt_hl_meta('Ask to stay with @' . $host['username'] . ' | RuinMyTrip', 'Ask a host for a free stay.', '/stay/new/' . $host['username'], false));
}

/** POST /stay/new/{username} */
function stay_new_submit(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $host = rmt_stay_host_by_username((string) $a['username']);
    if (!$host || $host['status'] !== 'approved') not_found();
    $why = rmt_stay_blocked_reason($me, $host);
    $v = rmt_stay_validate($_POST, $host);
    if ($why || !$v['ok']) {
        view('stay_new', ['host' => $host, 'why' => $why, 'errors' => $v['errors'], 'old' => $_POST],
             rmt_hl_meta('Ask to stay | RuinMyTrip', 'Ask a host for a free stay.', '/stay/new/' . $host['username'], false));
        return;
    }
    $r = rmt_stay_create($me, $host, $v['data']);
    if (!$r['ok']) { flash((string) $r['error']); redirect('/stay/new/' . $host['username']); }
    flash('Sent. @' . $host['username'] . ' will say yes or no. You are told either way.');
    redirect('/stay/' . (int) $r['id']);
}

/** GET /stays */
function stays_index(array $a): void {
    require_login();
    $me = current_user();
    view('stays_index', ['stays' => rmt_stays_for((int) $me['id']), 'me' => $me],
         rmt_hl_meta('Your stays | RuinMyTrip', 'Stay requests you sent and received.', '/stays', false) + ['app_shell' => true]);
}

/** GET /stay/{id} */
function stay_show(array $a): void {
    require_login();
    $me = current_user();
    $s = rmt_stay((int) $a['id']);
    $party = rmt_stay_party($s, $me);
    if (!$party) not_found();
    q_run("UPDATE notifications SET read_at = ? WHERE user_id = ? AND target_type = 'stay' AND target_id = ? AND read_at IS NULL",
          [date('Y-m-d H:i:s'), (int) $me['id'], (int) $s['id']]);
    view('stay_show', ['s' => $s, 'party' => $party, 'me' => $me],
         rmt_hl_meta('Stay request | RuinMyTrip', 'A stay request.', '/stay/' . (int) $s['id'], false) + ['app_shell' => true]);
}

/** POST /stay/{id}/{action} */
function stay_action(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $s = rmt_stay((int) $a['id']);
    if (!rmt_stay_party($s, $me)) not_found();
    $to = ['accept' => 'accepted', 'decline' => 'declined', 'confirm' => 'confirmed', 'cancel' => 'cancelled'][(string) $a['action']] ?? '';
    $r = $to !== '' ? rmt_stay_move($s, $me, $to) : ['ok' => false, 'error' => 'Unknown action.'];
    flash($r['ok'] ? [
        'accepted' => 'Accepted. Messages are open: agree where and when to meet.',
        'declined' => 'Declined. They are told only that it did not work out.',
        'confirmed' => 'Confirmed. Message your host to agree the details, and read the safety guide.',
        'cancelled' => 'Cancelled. The other person has been told.',
    ][$to] : (string) $r['error']);
    redirect('/stay/' . (int) $s['id']);
}

/**
 * Reminders for confirmed stays, written once each: both people two days before the first night, and
 * both a prompt to write a reference after the last night (only while they have not written one).
 * Idempotent: an existing notification of the same type for the same stay and person means skip.
 */
function rmt_stay_reminders_sweep(?string $today = null): int {
    $today ??= date('Y-m-d');
    $sent = 0;
    $once = static function (int $to, string $type, int $actor, int $stayId): bool {
        return !q_one('SELECT 1 x FROM notifications WHERE user_id = ? AND type = ? AND target_type = ? AND target_id = ?', [$to, $type, 'stay', $stayId]);
    };
    $soon = q_all("SELECT id, guest_id, host_id, date_from FROM stay_requests WHERE state = 'confirmed' AND date_from > ? AND date_from <= ?",
                  [$today, date('Y-m-d', strtotime($today . ' +2 days'))]);
    foreach ($soon as $s) foreach ([[(int) $s['guest_id'], (int) $s['host_id']], [(int) $s['host_id'], (int) $s['guest_id']]] as [$to, $other]) {
        if (!$once($to, 'stay_soon', $other, (int) $s['id'])) continue;
        rmt_stay_notify($to, 'stay_soon', $other, (int) $s['id'], 'Your stay starts soon',
                        'Your stay starts on ' . date('j M', strtotime((string) $s['date_from'])) . '. Check arrival details in your messages, and read the safety guide.');
        $sent++;
    }
    $ended = q_all("SELECT id, guest_id, host_id FROM stay_requests WHERE state = 'confirmed' AND date_to < ? AND date_to >= ?",
                   [$today, date('Y-m-d', strtotime($today . ' -14 days'))]);
    foreach ($ended as $s) foreach ([[(int) $s['guest_id'], (int) $s['host_id']], [(int) $s['host_id'], (int) $s['guest_id']]] as [$to, $other]) {
        if (!$once($to, 'stay_reference', $other, (int) $s['id'])) continue;
        if (q_one('SELECT 1 x FROM member_references WHERE from_user_id = ? AND to_user_id = ?', [$to, $other])) continue;
        $u = q_one('SELECT username FROM users WHERE id = ?', [$other]);
        if (!$u) continue;
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
              [$to, 'stay_reference', $other, 'stay', (int) $s['id'], date('Y-m-d H:i:s')]);
        rmt_notify_email_direct($to, 'How did the stay go?', 'Write a short reference for @' . $u['username'] . ' so other members know what to expect.',
                                '/u/' . $u['username'] . '/references', 'your stay ended');
        $sent++;
    }
    return $sent;
}

/** At most every half hour, after any request has been answered. */
function rmt_stay_reminders_maybe(): void {
    $flag = sys_get_temp_dir() . '/rmt_stay_remind';
    if (is_file($flag) && time() - (int) filemtime($flag) < 1800) return;
    @touch($flag);
    try { rmt_stay_reminders_sweep(); } catch (Throwable $e) { /* a reminder never breaks a page */ }
}

/* ---------- host availability ---------- */

const RMT_HOST_UNAVAILABLE_MAX = 20;

/** The host's upcoming "not hosting" ranges, sorted, past ones left out. @return list<array{0:string,1:string}> */
function rmt_host_unavailable(array $host, ?string $today = null): array {
    $today ??= date('Y-m-d');
    $raw = json_decode((string) ($host['unavailable'] ?? ''), true);
    $out = [];
    foreach (is_array($raw) ? $raw : [] as $r) {
        if (!is_array($r) || count($r) !== 2) continue;
        [$a, $b] = array_map('strval', array_values($r));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $a) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $b) || $b < $a || $b < $today) continue;
        $out[] = [$a, $b];
    }
    usort($out, fn($x, $y) => $x[0] <=> $y[0]);
    return $out;
}

/** The first "not hosting" range that one of the nights from $from to the night before $to falls in. */
function rmt_host_unavailable_clash(array $host, string $from, string $to): ?array {
    foreach (rmt_host_unavailable($host) as $r) if ($from <= $r[1] && $to > $r[0]) return $r;
    return null;
}

function rmt_date_range_label(array $r): string {
    return $r[0] === $r[1] ? date('j M Y', strtotime($r[0])) : date('j M', strtotime($r[0])) . ' to ' . date('j M Y', strtotime($r[1]));
}

/** POST /hosts-locals/host/unavailable: add a range (from, to) or remove one (remove = index). */
function hosts_locals_host_unavailable(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $host = rmt_host_profile((int) $me['id']);
    if (!$host) redirect('/hosts-locals/become-a-host');
    $list = rmt_host_unavailable($host);
    if (input('remove') !== null && input('remove') !== '') {
        array_splice($list, (int) input('remove'), 1);
        $msg = 'Removed.';
    } else {
        $from = (string) input('from'); $to = (string) input('to') ?: $from;
        $ok = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);
        if (!$ok) $msg = 'Pick the first and last date you are not hosting.';
        elseif ($to < $from) $msg = 'The last date must be on or after the first.';
        elseif ($to < date('Y-m-d')) $msg = 'Those dates have already passed.';
        elseif (count($list) >= RMT_HOST_UNAVAILABLE_MAX) $msg = 'You can list up to ' . RMT_HOST_UNAVAILABLE_MAX . ' date ranges. Remove one first.';
        else { $list[] = [$from, $to]; $msg = 'Saved. Travelers cannot ask for those nights.'; }
    }
    usort($list, fn($x, $y) => $x[0] <=> $y[0]);
    q_run('UPDATE host_profiles SET unavailable = ?, updated_at = ? WHERE user_id = ?', [json_encode(array_values($list)), date('Y-m-d H:i:s'), (int) $me['id']]);
    flash($msg);
    redirect('/hosts-locals/me#host-dates');
}
