<?php
declare(strict_types=1);

/**
 * Match alerts for people who have not made an account (2026-10-01, migration 105).
 *
 * Somebody reading the Yi Peng page who is going in November wants one thing: to hear when another
 * traveler is there on the same days. Asking for a username, a password and an age before they can
 * say so lost them. This asks for an email, the city and the dates, and nothing else.
 *
 * The rules that keep it from being a spam cannon, in order:
 *   1. Double opt in. The only email an unconfirmed address ever gets is the confirmation, and that
 *      is rate limited per address and per connection. Every later email needs the confirm click.
 *   2. Only real overlaps are sent: a real member's public trip or open buddy post in the window, or
 *      another confirmed alert for the same city and days. Never a newsletter, never a count of zero.
 *   3. Throttled per alert (one email a day at most, eight in an alert's life) and every email has
 *      a one click off switch that needs no account.
 *   4. Nobody is ever identified to anybody: an overlap email says a traveler, never who.
 *   5. The form never says whether an address is already known.
 *
 * When the address later becomes a confirmed account, the alert is turned into what a member has:
 * the trip on those dates (the form says so) and a follow of the city. See rmt_alerts_adopt().
 */

const RMT_ALERT_FLEX = [0 => 'Exact dates', 3 => 'Give or take 3 days', 7 => 'Give or take a week'];
const RMT_ALERT_MAX_MAILS = 8;
const RMT_ALERT_MAIL_GAP = 20 * 3600;
const RMT_ALERT_SESSION = 'alert_last';

function rmt_alert_email_norm(string $e): string {
    return mb_strtolower(trim($e));
}

/** Check the form. Returns the clean row to store, or the reasons it cannot be. */
function rmt_alert_validate(array $in, ?string $today = null): array {
    $today = $today ?? date('Y-m-d');
    $errors = [];
    $email = rmt_alert_email_norm((string) ($in['email'] ?? ''));
    if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Add an email address we can send the alert to.';
    $destId = (int) ($in['destination_id'] ?? 0);
    $d = $destId > 0 ? q_one('SELECT id, slug, name FROM destinations WHERE id = ?', [$destId]) : null;
    if (!$d) $errors[] = 'Pick the city you are going to.';
    $occ = (string) ($in['occasion'] ?? '');
    if ($occ !== '' && (!defined('RMT_OCCASIONS') || !isset(RMT_OCCASIONS[$occ]) || !$d || RMT_OCCASIONS[$occ]['dest'] !== $d['slug'])) $occ = '';
    $from = trim((string) ($in['date_from'] ?? ''));
    $to = trim((string) ($in['date_to'] ?? ''));
    $okDate = static fn(string $s): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && strtotime($s) !== false
                                            && date('Y-m-d', (int) strtotime($s)) === $s;
    if (!$okDate($from) || !$okDate($to)) {
        $errors[] = 'Add the day you arrive and the day you leave.';
    } else {
        if ($to < $from) $errors[] = 'The day you leave is before the day you arrive.';
        elseif ($to < $today) $errors[] = 'Those dates are over. Add a trip that is still ahead.';
        elseif ($from > date('Y-m-d', (int) strtotime($today . ' +730 days'))) $errors[] = 'Add dates within the next two years.';
        elseif ((strtotime($to) - strtotime($from)) / 86400 > 180) $errors[] = 'Keep the window under six months so the alert means something.';
    }
    $flex = (int) ($in['flex_days'] ?? 0);
    if (!isset(RMT_ALERT_FLEX[$flex])) $flex = 0;
    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'email' => $email, 'destination_id' => $destId, 'occasion' => $occ,
        'date_from' => $from, 'date_to' => $to, 'flex_days' => $flex,
    ], 'dest' => $d];
}

/** The window an alert listens on: its dates widened by the flexibility it asked for. */
function rmt_alert_window(array $a): array {
    $f = (int) ($a['flex_days'] ?? 0);
    return [date('Y-m-d', (int) strtotime($a['date_from'] . " -$f days")), date('Y-m-d', (int) strtotime($a['date_to'] . " +$f days"))];
}

/**
 * Store an alert. The same address and city is one alert: asking again updates its dates rather
 * than piling up rows, and issues a fresh confirmation link. Returns the row id and the raw token.
 *
 * @return array{id:int, token:string}
 */
function rmt_alert_save(array $data): array {
    $raw = bin2hex(random_bytes(24));
    $hash = hash('sha256', $raw);
    $now = date('Y-m-d H:i:s');
    $occ = $data['occasion'] !== '' ? $data['occasion'] : null;
    $ex = q_one("SELECT id FROM match_alerts WHERE email = ? AND destination_id = ? AND status IN ('pending', 'active')
                  ORDER BY id DESC LIMIT 1", [$data['email'], (int) $data['destination_id']]);
    if ($ex) {
        q_run('UPDATE match_alerts SET date_from = ?, date_to = ?, flex_days = ?, occasion = ?, token_hash = ? WHERE id = ?',
              [$data['date_from'], $data['date_to'], (int) $data['flex_days'], $occ, $hash, (int) $ex['id']]);
        return ['id' => (int) $ex['id'], 'token' => $raw];
    }
    $visitor = function_exists('rmt_visitor_id') ? rmt_visitor_id() : null;
    $acq = function_exists('rmt_acq_current') ? rmt_acq_current() : [];
    $id = (int) q_run('INSERT INTO match_alerts (email, destination_id, occasion, date_from, date_to, flex_days, status, token_hash, visitor, created_at,
                                                 acq_source, acq_medium, acq_campaign, acq_content)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                      [$data['email'], (int) $data['destination_id'], $occ, $data['date_from'], $data['date_to'],
                       (int) $data['flex_days'], 'pending', $hash, $visitor, $now,
                       $acq['source'] ?? null, $acq['medium'] ?? null, $acq['campaign'] ?? null, $acq['content'] ?? null]);
    return ['id' => $id, 'token' => $raw];
}

/**
 * The confirm link is usually opened in a mail app's browser, a fresh session that knows nothing
 * about the post that brought this person. Hand it the channel the alert was set from, so the
 * confirmation and any signup that follows are credited to that post. First touch still wins: a
 * session that already holds a channel keeps it.
 */
function rmt_alert_carry_channel(array $alert): void {
    if (empty($alert['acq_source']) || !empty($_SESSION['_acq']['source'])) return;
    $_SESSION['_acq'] = ['source' => (string) $alert['acq_source'], 'medium' => $alert['acq_medium'] ?? null,
                         'campaign' => $alert['acq_campaign'] ?? null, 'content' => $alert['acq_content'] ?? null];
    unset($GLOBALS['_rmt_acq_resolved']);
}

function rmt_alert_by_token(string $raw): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $raw)) return null;
    return q_one('SELECT * FROM match_alerts WHERE token_hash = ?', [hash('sha256', $raw)]);
}

function rmt_alert_by_id(int $id): ?array {
    return $id > 0 ? q_one('SELECT * FROM match_alerts WHERE id = ?', [$id]) : null;
}

/** The off switch in every email. Signed, so it needs no account and cannot be guessed. */
function rmt_alert_off_sig(int $id, string $email): string {
    return substr(hash_hmac('sha256', 'alert-off:' . $id . ':' . $email, (string) cfg('security_salt')), 0, 32);
}

function rmt_alert_off_url(array $a): string {
    return abs_url(url('alerts/off?a=' . (int) $a['id'] . '&s=' . rmt_alert_off_sig((int) $a['id'], (string) $a['email'])));
}

/**
 * Who this alert would meet today: real members' public trips and open buddy posts in the window,
 * and other confirmed alerts for the city on crossing days. Counted, never shown when zero, and
 * never with a name: an alert holder has no account, so nobody is identified to them.
 *
 * @return array{travelers:int, buddies:int, alerts:int, total:int}
 */
function rmt_alert_overlaps(array $a): array {
    [$from, $to] = rmt_alert_window($a);
    $did = (int) $a['destination_id'];
    $real = function_exists('rmt_sc_real_user_sql') ? rmt_sc_real_user_sql('u') : "u.status = 'active' AND SUBSTR(u.username, 1, 5) <> 'team_'";
    $email = (string) $a['email'];
    $out = ['travelers' => 0, 'buddies' => 0, 'alerts' => 0];
    try {
        $out['travelers'] = (int) (q_one("SELECT COUNT(DISTINCT t.user_id) c FROM trips t JOIN users u ON u.id = t.user_id
                                          WHERE t.destination_id = ? AND t.status = 'published' AND t.visibility = 'public'
                                            AND t.date_from IS NOT NULL AND t.date_from <= ? AND t.date_to >= ?
                                            AND LOWER(u.email) <> ? AND $real", [$did, $to, $from, $email])['c'] ?? 0);
    } catch (Throwable $e) { /* no trips table in a narrow test */ }
    try {
        $out['buddies'] = (int) (q_one("SELECT COUNT(DISTINCT b.user_id) c FROM buddy_posts b JOIN users u ON u.id = b.user_id
                                         WHERE b.destination_id = ? AND b.status = 'open' AND b.date_from <= ? AND b.date_to >= ?
                                           AND LOWER(u.email) <> ? AND $real", [$did, $to, $from, $email])['c'] ?? 0);
    } catch (Throwable $e) { /* no buddy table */ }
    $out['alerts'] = count(rmt_alert_crossing($a));
    $out['total'] = $out['travelers'] + $out['buddies'] + $out['alerts'];
    return $out;
}

/** Other confirmed alerts for the same city whose windows cross this one, one per address. */
function rmt_alert_crossing(array $a): array {
    [$from, $to] = rmt_alert_window($a);
    $rows = q_all("SELECT * FROM match_alerts WHERE destination_id = ? AND status = 'active' AND email <> ? AND id <> ?",
                  [(int) $a['destination_id'], (string) $a['email'], (int) $a['id']]);
    $out = [];
    foreach ($rows as $r) {
        [$f, $t] = rmt_alert_window($r);
        if ($f <= $to && $t >= $from) $out[$r['email']] = $r;
    }
    return array_values($out);
}

/** "Chiang Mai, 22 to 27 November 2026" */
function rmt_alert_label(array $a): string {
    $d = q_one('SELECT name FROM destinations WHERE id = ?', [(int) $a['destination_id']]);
    $name = (string) ($d['name'] ?? 'your city');
    $occ = (string) ($a['occasion'] ?? '');
    if ($occ !== '' && defined('RMT_OCCASIONS') && isset(RMT_OCCASIONS[$occ])) $name .= ' for ' . RMT_OCCASIONS[$occ]['short'];
    return $name . ', ' . rmt_alert_dates((string) $a['date_from'], (string) $a['date_to']);
}

function rmt_alert_dates(string $from, string $to): string {
    $f = strtotime($from); $t = strtotime($to);
    if (date('Y-m', $f) === date('Y-m', $t)) return date('j', $f) . ' to ' . date('j F Y', $t);
    if (date('Y', $f) === date('Y', $t)) return date('j F', $f) . ' to ' . date('j F Y', $t);
    return date('j F Y', $f) . ' to ' . date('j F Y', $t);
}

/** Where an alert's emails point: the occasion page if there is one, else the city's travelers. */
function rmt_alert_page(array $a): string {
    $occ = (string) ($a['occasion'] ?? '');
    if ($occ !== '' && defined('RMT_OCCASIONS') && isset(RMT_OCCASIONS[$occ])) return url('e/' . $occ);
    $d = q_one('SELECT slug FROM destinations WHERE id = ?', [(int) $a['destination_id']]);
    return url('d/' . ($d['slug'] ?? '') . '/travelers');
}

/** The account step with this alert's trip already held, so nothing is typed twice. */
function rmt_alert_join_url(array $a): string {
    $d = q_one('SELECT slug FROM destinations WHERE id = ?', [(int) $a['destination_id']]);
    return url('plan?' . http_build_query(['d' => $d['slug'] ?? '', 'from' => $a['date_from'], 'to' => $a['date_to'], 'cta' => 'alert_join']));
}

function rmt_alert_send_confirm(array $a, string $raw): bool {
    $link = abs_url(url('alerts/confirm/' . $raw));
    $label = rmt_alert_label($a);
    $html = rmt_mail_layout('Switch on your match alert',
        '<p>You asked to hear when another traveler will be in <b>' . e($label) . '</b>.</p>'
        . '<p>Confirm it and we email you only when somebody real posts dates that overlap yours. '
        . 'No newsletter, and every email has a one click off switch.</p>',
        'Switch on my alert', $link);
    $text = "Switch on your RuinMyTrip match alert for $label:\n$link\n\nIf you did not ask for this, ignore this email and nothing will be sent.";
    $r = rmt_mail_send((string) $a['email'], 'Confirm your match alert: ' . $label, $html, $text);
    return (bool) ($r[0] ?? false);
}

/**
 * Send one overlap email to an alert, if it may have one now: confirmed, still ahead, not emailed in
 * the last twenty hours, and under its lifetime cap. Returns whether it was sent.
 */
function rmt_alert_mail_overlap(array $a, string $what): bool {
    if (($a['status'] ?? '') !== 'active') return false;
    [, $to] = rmt_alert_window($a);
    if ($to < date('Y-m-d')) return false;
    if ((int) $a['notify_count'] >= RMT_ALERT_MAX_MAILS) return false;
    if (!empty($a['notified_at']) && time() - (int) strtotime((string) $a['notified_at']) < RMT_ALERT_MAIL_GAP) return false;
    $label = rmt_alert_label($a);
    $html = rmt_mail_layout('Someone overlaps your dates',
        '<p>' . e($what) . ' <b>' . e($label) . '</b>.</p>'
        . '<p>Post your trip to see who it is and say hello. Your dates are already filled in, and nobody can '
        . 'message you until you say yes.</p>'
        . '<p style="margin:18px 0 0"><a href="' . e(abs_url(rmt_alert_page($a))) . '">See who is going</a></p>',
        'Post my trip', abs_url(rmt_alert_join_url($a)))
        . '<p style="font-family:sans-serif;font-size:12px;color:#8895a3;text-align:center">'
        . '<a href="' . e(rmt_alert_off_url($a)) . '" style="color:#8895a3">Turn this alert off</a></p>';
    $text = "$what $label.\nPost your trip: " . abs_url(rmt_alert_join_url($a)) . "\nTurn this alert off: " . rmt_alert_off_url($a);
    $r = rmt_mail_send((string) $a['email'], 'A traveler overlaps your dates in ' . $label, $html, $text);
    if (!($r[0] ?? false)) return false;
    q_run('UPDATE match_alerts SET notified_at = ?, notify_count = notify_count + 1 WHERE id = ?', [date('Y-m-d H:i:s'), (int) $a['id']]);
    if (function_exists('rmt_track')) rmt_track('alert_match', ['source' => 'alert', 'destination_id' => (int) $a['destination_id']]);
    return true;
}

/**
 * A member just published a trip: tell the confirmed alerts it overlaps. Public trips only, never
 * the traveler's own alert, and never a house account.
 */
function rmt_alerts_on_trip(int $actorId, int $destId, string $from, string $to, string $visibility): int {
    if ($visibility !== 'public' || $from === '' || $to === '') return 0;
    $real = function_exists('rmt_sc_real_user_sql') ? rmt_sc_real_user_sql('u') : "u.status = 'active'";
    try {
        $u = q_one("SELECT u.email FROM users u WHERE u.id = ? AND $real", [$actorId]);
        if (!$u) return 0;
        $rows = q_all("SELECT * FROM match_alerts WHERE destination_id = ? AND status = 'active' AND email <> ?",
                      [$destId, rmt_alert_email_norm((string) $u['email'])]);
    } catch (Throwable $e) { return 0; }
    $sent = 0;
    foreach ($rows as $r) {
        [$f, $t] = rmt_alert_window($r);
        if ($f <= $to && $t >= $from && rmt_alert_mail_overlap($r, 'A traveler just posted dates that overlap yours in')) $sent++;
    }
    return $sent;
}

/** An alert was just confirmed: tell the other confirmed alerts whose days it crosses. */
function rmt_alerts_on_alert(array $a): int {
    $sent = 0;
    foreach (rmt_alert_crossing($a) as $r) {
        if (rmt_alert_mail_overlap($r, 'Another traveler just set an alert for days that overlap yours in')) $sent++;
    }
    return $sent;
}

/**
 * The address is now a confirmed member: their alerts become what a member has. The city is
 * followed, and the dates become their trip unless they already have one there on crossing days.
 * Returns how many alerts were converted.
 */
function rmt_alerts_adopt(array $user): int {
    $uid = (int) ($user['id'] ?? 0);
    $email = rmt_alert_email_norm((string) ($user['email'] ?? ''));
    if ($uid < 1 || $email === '' || !function_exists('email_is_verified') || !email_is_verified($user)) return 0;
    try {
        $rows = q_all("SELECT * FROM match_alerts WHERE email = ? AND status IN ('pending', 'active') AND date_to >= ?",
                      [$email, date('Y-m-d')]);
    } catch (Throwable $e) { return 0; }
    $n = 0;
    foreach ($rows as $a) {
        $did = (int) $a['destination_id'];
        if (function_exists('rmt_follow_destination')) rmt_follow_destination($uid, $did, 'alert');
        $has = q_one("SELECT id FROM trips WHERE user_id = ? AND destination_id = ? AND status = 'published'
                        AND date_from IS NOT NULL AND date_from <= ? AND date_to >= ?",
                     [$uid, $did, (string) $a['date_to'], (string) $a['date_from']]);
        if (!$has && function_exists('rmt_plan_first_validate')) {
            $v = rmt_plan_first_validate(['destination_id' => $did, 'date_from' => $a['date_from'], 'date_to' => $a['date_to'],
                                          'meet' => 'yes', 'visibility' => 'public', 'flexible' => (int) $a['flex_days'] > 0 ? '1' : '']);
            if ($v['ok']) rmt_plan_first_publish($user, $v['inputs']);
        }
        q_run("UPDATE match_alerts SET status = 'converted', user_id = ?, converted_at = ? WHERE id = ?",
              [$uid, date('Y-m-d H:i:s'), (int) $a['id']]);
        if (function_exists('rmt_track')) rmt_track('alert_converted', ['source' => 'alert', 'destination_id' => $did]);
        $n++;
    }
    return $n;
}

/* ---------- controllers ---------- */

/** POST /alerts  the signed out form on a city, occasion or share page. */
function alert_submit(array $a): void {
    csrf_check();
    $back = rmt_return_to('/');
    // A field people never see. Anything that fills it is a script; it is told it worked.
    if (trim((string) input('website')) !== '') redirect('/alerts/sent');
    $v = rmt_alert_validate($_POST);
    if (!$v['ok']) {
        flash(implode(' ', $v['errors']));
        redirect($back . (str_contains($back, '#') ? '' : '#match-alert'));
    }
    $data = $v['data'];
    if (!rmt_rate_ok('alert_ip', rmt_client_ip(), 6, 3600) || !rmt_rate_ok('alert_email', $data['email'], 4, 86400)) {
        flash('That was a lot of alerts in a short time. Try again a little later.');
        redirect($back);
    }
    $saved = rmt_alert_save($data);
    $alert = rmt_alert_by_id($saved['id']);
    if ($alert && $alert['status'] === 'pending') {
        rmt_alert_send_confirm($alert, $saved['token']);
    } elseif ($alert) {
        // Already switched on: the dates were updated, so nothing needs confirming again.
        flash('Your alert is updated with the new dates.');
    }
    rmt_track('alert_submitted', ['source' => in_array((string) input('source'), RMT_CONTRIB_SOURCES, true) ? (string) input('source') : 'other',
                                  'destination_id' => (int) $data['destination_id']]);
    /* Hold the trip the way /plan does, so "make an account" lands on the account step with these
       dates already in it, and keep the address for that form. Nothing here is published. */
    if (function_exists('rmt_plan_first_validate')) {
        $pv = rmt_plan_first_validate(['destination_id' => $data['destination_id'], 'date_from' => $data['date_from'],
                                       'date_to' => $data['date_to'], 'meet' => 'yes', 'visibility' => 'public']);
        if ($pv['ok']) {
            $_SESSION[RMT_PLAN_DRAFT_KEY] = ['inputs' => $pv['inputs'], 'raw' => ['destination_id' => $data['destination_id'],
                                             'date_from' => $data['date_from'], 'date_to' => $data['date_to'], 'meet' => 'yes', 'visibility' => 'public']];
        }
    }
    $_SESSION['alert_email'] = $data['email'];
    $_SESSION[RMT_ALERT_SESSION] = (int) $saved['id'];
    redirect('/alerts/sent');
}

/** GET /alerts/sent  check your inbox, and the two things worth doing while you wait. */
function alert_sent(array $a): void {
    $alert = rmt_alert_by_id((int) ($_SESSION[RMT_ALERT_SESSION] ?? 0));
    if (!$alert) redirect('/');
    view('alert_sent', ['alert' => $alert, 'label' => rmt_alert_label($alert), 'over' => rmt_alert_overlaps($alert),
                        'confirmed' => false],
         ['title' => 'Check your inbox | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}

/** GET /alerts/confirm/{token}  one button, so a mail scanner opening the link switches nothing on. */
function alert_confirm_form(array $a): void {
    $alert = rmt_alert_by_token((string) ($a['token'] ?? ''));
    if ($alert) rmt_alert_carry_channel($alert);
    view('alert_confirm', ['alert' => $alert, 'token' => (string) ($a['token'] ?? ''), 'label' => $alert ? rmt_alert_label($alert) : ''],
         ['title' => 'Switch on your alert | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}

/** POST /alerts/confirm */
function alert_confirm_submit(array $a): void {
    csrf_check();
    $alert = rmt_alert_by_token((string) input('token'));
    if (!$alert || !in_array($alert['status'], ['pending', 'active'], true)) {
        flash('That link has expired. Set the alert again from the city page.');
        redirect('/');
    }
    rmt_alert_carry_channel($alert);
    if ($alert['status'] === 'pending') {
        q_run("UPDATE match_alerts SET status = 'active', confirmed_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), (int) $alert['id']]);
        $alert['status'] = 'active';
        rmt_track('alert_confirmed', ['source' => 'alert', 'destination_id' => (int) $alert['destination_id']]);
        rmt_alerts_on_alert($alert);
    }
    $_SESSION[RMT_ALERT_SESSION] = (int) $alert['id'];
    $_SESSION['alert_email'] = (string) $alert['email'];
    view('alert_sent', ['alert' => $alert, 'label' => rmt_alert_label($alert), 'over' => rmt_alert_overlaps($alert),
                        'confirmed' => true],
         ['title' => 'Your alert is on | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}

/** GET /alerts/off?a=&s=  and POST: the off switch in every email. */
function alert_off(array $a): void {
    $id = (int) input('a');
    $sig = (string) input('s');
    $alert = rmt_alert_by_id($id);
    $valid = $alert && hash_equals(rmt_alert_off_sig($id, (string) $alert['email']), $sig);
    $done = false;
    if ($valid && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        q_run("UPDATE match_alerts SET status = 'off' WHERE id = ?", [$id]);
        $done = true;
    }
    view('alert_off', ['valid' => $valid, 'done' => $done || ($alert['status'] ?? '') === 'off', 'id' => $id, 'sig' => $sig,
                       'label' => $alert ? rmt_alert_label($alert) : ''],
         ['title' => 'Match alert | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}
