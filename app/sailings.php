<?php
/**
 * Sailing pages: one ship, one departure day (2026-11-18).
 *
 * A cruise is the one trip where thousands of strangers are already in the same place on the same
 * dates, so it is the smallest room RuinMyTrip can fill. /sailing/{ship}/{date} is that room: the
 * members who posted that ship and day, a way in for a member ("I'm on this sailing"), and for
 * somebody with no account an email alert that fires only when a real member joins the same sailing.
 *
 * Nothing here states a fact about a ship. The ship name is what the traveler typed, keyed with
 * rmt_buddy_ship_key() so "Norwegian Encore" and "NCL Norwegian encore" do not split the room more
 * than they have to. Pages are made on demand; only a sailing with a member on it is linked.
 */

const RMT_SAILING_ALERT_MAX_MAILS = 8;
const RMT_SAILING_ALERT_GAP = 72000;   // twenty hours between emails to one alert
const RMT_SAILING_SESSION = 'sailing_alert_id';

/** The ship part of a sailing URL: lowercase words joined by hyphens. */
function rmt_sailing_ship_slug(string $ship): string {
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($ship))), '-');
    return mb_substr($s, 0, 60);
}

/** The ship as people read it, from its slug: "norwegian-encore" -> "Norwegian Encore". */
function rmt_sailing_ship_name(string $slug): string {
    return ucwords(str_replace('-', ' ', $slug));
}

function rmt_sailing_valid_date(string $d): bool {
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false && date('Y-m-d', (int) strtotime($d)) === $d;
}

function rmt_sailing_path(string $ship, string $date): string {
    return '/sailing/' . rmt_sailing_ship_slug($ship) . '/' . $date;
}

function rmt_sailing_date_label(string $date): string {
    return date('j F Y', (int) strtotime($date));
}

/** Members whose open (or filled) cruise post is this ship, leaving this day or a day either side. */
function rmt_sailing_members(string $shipKey, string $date, ?int $viewerId = null): array {
    $rows = q_all("SELECT b.id, b.title, b.date_from, b.date_to, b.cruise_line, b.ship, b.departure_port, b.status, b.user_id,
                          u.username, p.avatar_url, p.display_name, p.home_city
                     FROM buddy_posts b JOIN users u ON u.id = b.user_id AND u.status = 'active'
                          AND COALESCE(u.email, '') NOT LIKE '%+rmtqa%'
                LEFT JOIN profiles p ON p.user_id = b.user_id
                    WHERE b.trip_type = 'cruise' AND b.status IN ('open', 'closed') AND b.ship_key = ?
                      AND b.date_from >= ? AND b.date_from <= ?
                 ORDER BY b.id",
                   [$shipKey, rmt_buddy_date_shift($date, -1), rmt_buddy_date_shift($date, 1)]);
    if ($viewerId && function_exists('rmt_hl_block_sql')) {
        $blocked = array_map('intval', array_column(q_all('SELECT blocked_id x FROM blocks WHERE blocker_id = ? UNION SELECT blocker_id x FROM blocks WHERE blocked_id = ?',
                                                          [$viewerId, $viewerId]), 'x'));
        $rows = array_values(array_filter($rows, static fn($r) => !in_array((int) $r['user_id'], $blocked, true)));
    }
    return $rows;
}

/** How many confirmed alerts are watching this sailing. Counted, never named. */
function rmt_sailing_watchers(string $shipKey, string $date): int {
    return (int) (q_one("SELECT COUNT(*) n FROM sailing_alerts WHERE ship_key = ? AND sail_date = ? AND status = 'active'",
                        [$shipKey, $date])['n'] ?? 0);
}

/** Upcoming sailings that have at least one member on them, most people first. For hub lists and the sitemap. */
function rmt_sailings_live(int $limit = 50): array {
    $rows = q_all("SELECT b.ship, b.ship_key, b.cruise_line, b.date_from, COUNT(DISTINCT b.user_id) n
                     FROM buddy_posts b JOIN users u ON u.id = b.user_id AND u.status = 'active' AND COALESCE(u.email, '') NOT LIKE '%+rmtqa%'
                    WHERE b.trip_type = 'cruise' AND b.status IN ('open', 'closed') AND b.ship_key IS NOT NULL AND b.ship IS NOT NULL
                      AND b.date_from >= ?
                 GROUP BY b.ship, b.ship_key, b.cruise_line, b.date_from
                 ORDER BY n DESC, b.date_from LIMIT " . max(1, $limit), [date('Y-m-d')]);
    return $rows;
}

/* ---------- alerts ---------- */

/** @return array{ok:bool, errors:list<string>, data:array} */
function rmt_sailing_alert_validate(array $in, ?string $today = null): array {
    $today = $today ?? date('Y-m-d');
    $errors = [];
    $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
    if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Add an email address we can send the alert to.';
    $ship = mb_substr(trim((string) ($in['ship'] ?? '')), 0, 80);
    $key = rmt_buddy_ship_key($ship);
    if ($ship === '' || $key === null || mb_strlen($ship) < 3) $errors[] = 'Add the name of your ship.';
    $date = trim((string) ($in['sail_date'] ?? ''));
    if (!rmt_sailing_valid_date($date)) $errors[] = 'Add the day your cruise leaves.';
    elseif ($date < $today) $errors[] = 'That sailing has already left. Add one that is still ahead.';
    elseif ($date > rmt_buddy_date_shift($today, 730)) $errors[] = 'Add a sailing within the next two years.';
    return ['ok' => !$errors, 'errors' => $errors,
            'data' => ['email' => $email, 'ship_name' => $ship, 'ship_key' => (string) $key, 'sail_date' => $date]];
}

/** One alert per address and sailing: asking again only issues a fresh confirmation link. */
function rmt_sailing_alert_save(array $data, ?int $userId = null): array {
    $raw = bin2hex(random_bytes(24));
    $hash = hash('sha256', $raw);
    $ex = q_one("SELECT id FROM sailing_alerts WHERE email = ? AND ship_key = ? AND sail_date = ? AND status IN ('pending', 'active')
                  ORDER BY id DESC LIMIT 1", [$data['email'], $data['ship_key'], $data['sail_date']]);
    if ($ex) {
        q_run('UPDATE sailing_alerts SET token_hash = ? WHERE id = ?', [$hash, (int) $ex['id']]);
        return ['id' => (int) $ex['id'], 'token' => $raw];
    }
    $acq = function_exists('rmt_acq_current') ? rmt_acq_current() : [];
    $id = (int) q_run('INSERT INTO sailing_alerts (email, ship_key, ship_name, sail_date, status, token_hash, user_id, visitor, created_at,
                                                   acq_source, acq_medium, acq_campaign, acq_content)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                      [$data['email'], $data['ship_key'], $data['ship_name'], $data['sail_date'], 'pending', $hash, $userId,
                       function_exists('rmt_visitor_id') ? rmt_visitor_id() : null, date('Y-m-d H:i:s'),
                       $acq['source'] ?? null, $acq['medium'] ?? null, $acq['campaign'] ?? null, $acq['content'] ?? null]);
    return ['id' => $id, 'token' => $raw];
}

function rmt_sailing_alert_by_id(int $id): ?array {
    return $id > 0 ? q_one('SELECT * FROM sailing_alerts WHERE id = ?', [$id]) : null;
}

function rmt_sailing_alert_by_token(string $raw): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $raw)) return null;
    return q_one('SELECT * FROM sailing_alerts WHERE token_hash = ?', [hash('sha256', $raw)]);
}

function rmt_sailing_alert_label(array $a): string {
    return (string) $a['ship_name'] . ', leaving ' . rmt_sailing_date_label((string) $a['sail_date']);
}

function rmt_sailing_alert_page(array $a): string {
    return rmt_sailing_path((string) $a['ship_name'], (string) $a['sail_date']);
}

function rmt_sailing_off_sig(int $id, string $email): string {
    return substr(hash_hmac('sha256', 'sailing-off:' . $id . ':' . $email, (string) cfg('security_salt')), 0, 32);
}

function rmt_sailing_off_url(array $a): string {
    return abs_url(url('sailing-alerts/off?a=' . (int) $a['id'] . '&s=' . rmt_sailing_off_sig((int) $a['id'], (string) $a['email'])));
}

function rmt_sailing_join_url(array $a): string {
    return url('buddies/new?' . http_build_query(['type' => 'cruise', 'ship' => $a['ship_name'], 'from' => $a['sail_date'], 'cta' => 'sailing_alert']));
}

function rmt_sailing_send_confirm(array $a, string $raw): bool {
    $link = abs_url(url('sailing-alerts/confirm/' . $raw));
    $label = rmt_sailing_alert_label($a);
    $html = rmt_mail_layout('Switch on your sailing alert',
        '<p>You asked to hear when another traveler joins <b>' . e($label) . '</b> on RuinMyTrip.</p>'
        . '<p>Confirm it and we email you only when a real member posts the same ship and day. '
        . 'No newsletter, and every email has a one click off switch.</p>',
        'Switch on my alert', $link);
    $text = "Switch on your RuinMyTrip sailing alert for $label:\n$link\n\nIf you did not ask for this, ignore this email and nothing will be sent.";
    $r = rmt_mail_send((string) $a['email'], 'Confirm your sailing alert: ' . $label, $html, $text);
    return (bool) ($r[0] ?? false);
}

/** One email to a confirmed alert, if it may have one now. */
function rmt_sailing_mail_match(array $a, string $what): bool {
    if (($a['status'] ?? '') !== 'active') return false;
    if ((string) $a['sail_date'] < date('Y-m-d')) return false;
    if ((int) $a['notify_count'] >= RMT_SAILING_ALERT_MAX_MAILS) return false;
    if (!empty($a['notified_at']) && time() - (int) strtotime((string) $a['notified_at']) < RMT_SAILING_ALERT_GAP) return false;
    $label = rmt_sailing_alert_label($a);
    $page = abs_url(url(ltrim(rmt_sailing_alert_page($a), '/')));
    $html = rmt_mail_layout('Somebody is on your sailing',
        '<p>' . e($what) . ' <b>' . e($label) . '</b>.</p>'
        . '<p>Make a free account and post your sailing to see who it is and say hello. Nobody can message you until you say yes.</p>'
        . '<p style="margin:18px 0 0"><a href="' . e($page) . '">See the sailing</a></p>',
        'Post my sailing', abs_url(rmt_sailing_join_url($a)))
        . '<p style="font-family:sans-serif;font-size:12px;color:#8895a3;text-align:center">'
        . '<a href="' . e(rmt_sailing_off_url($a)) . '" style="color:#8895a3">Turn this alert off</a></p>';
    $text = "$what $label.\nSee the sailing: $page\nPost your sailing: " . abs_url(rmt_sailing_join_url($a)) . "\nTurn this alert off: " . rmt_sailing_off_url($a);
    $r = rmt_mail_send((string) $a['email'], 'A traveler joined your sailing: ' . (string) $a['ship_name'], $html, $text,
                       rmt_mail_unsub_headers(rmt_sailing_off_url($a)));
    if (!($r[0] ?? false)) return false;
    q_run('UPDATE sailing_alerts SET notified_at = ?, notify_count = notify_count + 1 WHERE id = ?', [date('Y-m-d H:i:s'), (int) $a['id']]);
    rmt_track('sailing_alert_match', ['source' => 'alert']);
    return true;
}

/**
 * A member just posted a cruise: tell the confirmed alerts on the same ship and departure day (a day
 * either side, as the member search does). Never the poster's own address, never a test account.
 */
function rmt_sailing_alert_targets(array $post): array {
    if (($post['trip_type'] ?? '') !== 'cruise' || empty($post['ship_key']) || empty($post['date_from'])) return [];
    if (function_exists('rmt_is_qa_user') && rmt_is_qa_user((int) $post['user_id'])) return [];
    $own = mb_strtolower((string) (q_one('SELECT email FROM users WHERE id = ?', [(int) $post['user_id']])['email'] ?? ''));
    $date = (string) $post['date_from'];
    return array_values(array_filter(
        q_all("SELECT * FROM sailing_alerts WHERE ship_key = ? AND status = 'active' AND sail_date >= ? AND sail_date <= ?",
              [(string) $post['ship_key'], rmt_buddy_date_shift($date, -1), rmt_buddy_date_shift($date, 1)]),
        static fn($a) => $own === '' || $own !== (string) $a['email']));
}

function rmt_sailing_alerts_on_post(array $post): int {
    $sent = 0;
    foreach (rmt_sailing_alert_targets($post) as $a) if (rmt_sailing_mail_match($a, 'A traveler just posted')) $sent++;
    return $sent;
}

/** A second person confirmed an alert on the same sailing: tell the first ones somebody else is watching. */
function rmt_sailing_alerts_on_alert(array $alert): int {
    $sent = 0;
    foreach (q_all("SELECT * FROM sailing_alerts WHERE ship_key = ? AND sail_date = ? AND status = 'active' AND id <> ? AND email <> ?",
                   [(string) $alert['ship_key'], (string) $alert['sail_date'], (int) $alert['id'], (string) $alert['email']]) as $a) {
        if (rmt_sailing_mail_match($a, 'Another traveler is watching')) $sent++;
    }
    return $sent;
}

/* ---------- pages ---------- */

/** GET /sailing/find?ship=&date=  the form on the cruise pages, sent to its sailing. */
function sailing_find(array $a): void {
    $ship = trim((string) input('ship'));
    $date = trim((string) input('date'));
    if (rmt_buddy_ship_key($ship) === null || mb_strlen($ship) < 3 || !rmt_sailing_valid_date($date)) {
        flash('Add your ship and the day it leaves.');
        redirect(rmt_return_to('/buddies/cruise'));
    }
    redirect(rmt_sailing_path($ship, $date));
}

/** GET /sailing/{ship}/{date} */
function sailing_show(array $a): void {
    $slug = (string) $a['ship'];
    $date = (string) $a['date'];
    if (!rmt_sailing_valid_date($date) || $slug === '' || strlen($slug) > 60) not_found();
    $me = current_user();
    $key = (string) rmt_buddy_ship_key(str_replace('-', '', $slug));
    $members = rmt_sailing_members($key, $date, $me ? (int) $me['id'] : null);
    // The ship as the first member wrote it, when somebody has; the slug otherwise.
    $ship = $members ? (string) ($members[0]['ship'] ?: rmt_sailing_ship_name($slug)) : rmt_sailing_ship_name($slug);
    $canonicalSlug = rmt_sailing_ship_slug($ship);
    if ($canonicalSlug !== $slug && $canonicalSlug !== '') redirect_permanent(url(ltrim(rmt_sailing_path($ship, $date), '/')));
    $line = '';
    foreach ($members as $m) if (!empty($m['cruise_line'])) { $line = (string) $m['cruise_line']; break; }
    $mine = $me ? array_values(array_filter($members, static fn($m) => (int) $m['user_id'] === (int) $me['id'])) : [];
    $others = $me ? array_values(array_filter($members, static fn($m) => (int) $m['user_id'] !== (int) $me['id'])) : $members;
    // The cards the buddy pages show (request to connect, blocks respected), kept to this sailing.
    $cards = [];
    if (function_exists('rmt_buddy_search')) {
        $res = rmt_buddy_search(rmt_buddy_filters(['type' => 'cruise', 'ship' => $ship, 'from' => $date, 'to' => $date]), $me, 24);
        $lo = rmt_buddy_date_shift($date, -1); $hi = rmt_buddy_date_shift($date, 1);
        foreach ($res['cards'] ?? [] as $c) if (($c['from'] ?? '') >= $lo && ($c['from'] ?? '') <= $hi) $cards[] = $c;
        if ($cards && function_exists('rmt_buddy_card_interests')) $cards = rmt_buddy_card_interests($cards);
    }
    $watchers = rmt_sailing_watchers($key, $date);
    $past = $date < date('Y-m-d');
    $label = rmt_sailing_date_label($date);
    rmt_track('sailing_view', ['source' => 'buddies']);
    $title = $ship . ' sailing ' . $label . ': cruise buddies';
    $n = count($members);
    $desc = $n > 0
        ? $n . ($n === 1 ? ' traveler has' : ' travelers have') . ' posted the ' . $ship . ' sailing leaving ' . $label . ' on RuinMyTrip. Meet them before you board.'
        : 'Sailing on ' . $ship . ' on ' . $label . '? Post your sailing and get told when another traveler on the same ship joins.';
    view('sailing_show', compact('me', 'ship', 'slug', 'date', 'label', 'line', 'members', 'mine', 'others', 'cards', 'watchers', 'past'), [
        'title' => rmt_meta_title($title),
        'description' => $desc,
        'canonical' => abs_url(url(ltrim(rmt_sailing_path($ship, $date), '/'))),
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Cruise buddies', 'url' => url('buddies/cruise')],
                          ['name' => $ship . ', ' . $label, 'url' => url(ltrim(rmt_sailing_path($ship, $date), '/'))]],
    ]);
}

/** POST /sailing-alerts */
function sailing_alert_submit(array $a): void {
    csrf_check();
    $back = rmt_return_to('/buddies/cruise');
    if (trim((string) input('website')) !== '') redirect('/sailing-alerts/sent');
    $v = rmt_sailing_alert_validate($_POST);
    if (!$v['ok']) {
        flash(implode(' ', $v['errors']));
        redirect($back . '#sailing-alert');
    }
    $data = $v['data'];
    if (!rmt_rate_ok('sailing_alert_ip', rmt_client_ip(), 6, 3600) || !rmt_rate_ok('sailing_alert_email', $data['email'], 4, 86400)) {
        flash('That was a lot of alerts in a short time. Try again a little later.');
        redirect($back);
    }
    $me = current_user();
    $saved = rmt_sailing_alert_save($data, $me ? (int) $me['id'] : null);
    $alert = rmt_sailing_alert_by_id($saved['id']);
    if ($alert && $alert['status'] === 'pending') rmt_sailing_send_confirm($alert, $saved['token']);
    elseif ($alert) flash('Your alert for this sailing is already on.');
    rmt_track('sailing_alert_submitted', ['source' => 'buddies']);
    $_SESSION[RMT_SAILING_SESSION] = (int) $saved['id'];
    redirect('/sailing-alerts/sent');
}

/** GET /sailing-alerts/sent */
function sailing_alert_sent(array $a): void {
    $alert = rmt_sailing_alert_by_id((int) ($_SESSION[RMT_SAILING_SESSION] ?? 0));
    if (!$alert) redirect('/buddies/cruise');
    view('sailing_alert', ['state' => 'sent', 'alert' => $alert, 'label' => rmt_sailing_alert_label($alert), 'token' => ''],
         ['title' => 'Check your inbox | RuinMyTrip', 'canonical' => '', 'private' => true]);
}

/** GET /sailing-alerts/confirm/{token}  one button, so a mail scanner opening the link switches nothing on. */
function sailing_alert_confirm_form(array $a): void {
    $alert = rmt_sailing_alert_by_token((string) ($a['token'] ?? ''));
    view('sailing_alert', ['state' => $alert && in_array($alert['status'], ['pending', 'active'], true) ? 'confirm' : 'expired',
                           'alert' => $alert, 'label' => $alert ? rmt_sailing_alert_label($alert) : '', 'token' => (string) ($a['token'] ?? '')],
         ['title' => 'Switch on your sailing alert | RuinMyTrip', 'canonical' => '', 'private' => true]);
}

/** POST /sailing-alerts/confirm */
function sailing_alert_confirm_submit(array $a): void {
    csrf_check();
    $alert = rmt_sailing_alert_by_token((string) input('token'));
    if (!$alert || !in_array($alert['status'], ['pending', 'active'], true)) {
        flash('That link has expired. Set the alert again from the sailing page.');
        redirect('/buddies/cruise');
    }
    if ($alert['status'] === 'pending') {
        q_run("UPDATE sailing_alerts SET status = 'active', confirmed_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), (int) $alert['id']]);
        $alert['status'] = 'active';
        rmt_track('sailing_alert_confirmed', ['source' => 'alert']);
        rmt_sailing_alerts_on_alert($alert);
    }
    view('sailing_alert', ['state' => 'on', 'alert' => $alert, 'label' => rmt_sailing_alert_label($alert), 'token' => ''],
         ['title' => 'Your sailing alert is on | RuinMyTrip', 'canonical' => '', 'private' => true]);
}

/** GET|POST /sailing-alerts/off?a=&s= */
function sailing_alert_off(array $a): void {
    $id = (int) input('a');
    $sig = (string) input('s');
    $alert = rmt_sailing_alert_by_id($id);
    $valid = $alert && hash_equals(rmt_sailing_off_sig($id, (string) $alert['email']), $sig);
    if ($valid && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        q_run("UPDATE sailing_alerts SET status = 'off' WHERE id = ?", [$id]);
        $alert['status'] = 'off';
    }
    view('sailing_alert', ['state' => !$valid ? 'expired' : (($alert['status'] ?? '') === 'off' ? 'off' : 'off_ask'),
                           'alert' => $alert, 'label' => $alert ? rmt_sailing_alert_label($alert) : '', 'token' => '', 'id' => $id, 'sig' => $sig],
         ['title' => 'Sailing alert | RuinMyTrip', 'canonical' => '', 'private' => true]);
}
