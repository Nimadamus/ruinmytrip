<?php
declare(strict_types=1);

/**
 * "Before you go" updates (2026-11-15, migration 126).
 *
 * Somebody reading the Amsterdam tourist tax or the Barcelona scam warnings is planning a trip, and
 * the one thing they would want later is to hear if what they read stops being true. This asks for
 * an email on the tax and warnings pages and nothing else.
 *
 * The rules, the same ones match alerts keep (app/match_alerts.php):
 *   1. Double opt in. An unconfirmed address only ever gets the confirmation, rate limited.
 *   2. Only real changes are sent: the city's tax entry or its official warnings changed since the
 *      last check (byg_state). Never a newsletter, never "nothing changed".
 *   3. At most one email a day per signup, and every email has a one click off switch.
 *   4. The form never says whether an address is already known.
 */

const RMT_BYG_TOPICS = ['tax' => 'tourist tax', 'scams' => 'official scam warnings'];
const RMT_BYG_MAIL_GAP = 20 * 3600;
const RMT_BYG_SESSION = 'byg_last';
const RMT_BYG_SOURCES = ['tax_calc', 'tax_note', 'warnings', 'city_scams', 'tax_2027', 'ticket_note'];

/**
 * One sign up ask on tax notes, ticket notes and warnings pages (Nima's yes 2026-11-21): a "Watch
 * {city}" card right after the answer, the calculator / checklist / story asks folded under it,
 * and the buddy, dates and star asks left to the city pages. RMT_CTA_OLD=1 brings the old blocks back.
 */
function rmt_cta_focus(): bool {
    return getenv('RMT_CTA_OLD') !== '1';
}

/** /updates/watch link: Google sign in, then one tap to watch the city. Null when Google is off. */
function rmt_byg_watch_url(string $topic, string $city, string $source): ?string {
    if (!function_exists('rmt_google_enabled') || !rmt_google_enabled()) return null;
    return url('updates/watch?' . http_build_query(['topic' => $topic, 'city' => $city, 'source' => $source]));
}

/** The cities a topic knows: key => display name. */
function rmt_byg_cities(string $topic): array {
    $out = [];
    if ($topic === 'tax' && function_exists('rmt_tt_cities')) {
        foreach (rmt_tt_cities() as $k => $c) $out[(string) $k] = (string) $c['name'];
    } elseif ($topic === 'scams' && function_exists('rmt_city_scams_all')) {
        foreach (rmt_city_scams_all() as $slug => $c) {
            $name = (string) ($c['name'] ?? '');
            if ($name === '') $name = ucwords(str_replace('-', ' ', (string) preg_replace('/-[a-z]+$/', '', (string) $slug)));
            $out[(string) $slug] = $name;
        }
    }
    return $out;
}

/** Check the form. Returns the clean row to store, or the reasons it cannot be. */
function rmt_byg_validate(array $in): array {
    $errors = [];
    $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
    if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a working email address.';
    $topic = (string) ($in['topic'] ?? '');
    if (!isset(RMT_BYG_TOPICS[$topic])) { $errors[] = 'Pick what you want to hear about.'; $topic = ''; }
    $city = (string) ($in['city'] ?? '');
    $name = '';
    if ($topic !== '' && $city !== '') {
        $cities = rmt_byg_cities($topic);
        if (!isset($cities[$city])) $errors[] = 'That city is not one we track yet.';
        else {
            $name = $cities[$city];
            if ($topic === 'scams' && function_exists('q_one')) {
                $d = q_one('SELECT name FROM destinations WHERE slug = ?', [$city]);
                if ($d && (string) $d['name'] !== '') $name = (string) $d['name'];
            }
        }
    }
    $source = (string) ($in['source'] ?? '');
    if (!in_array($source, RMT_BYG_SOURCES, true)) $source = '';
    return ['ok' => !$errors, 'errors' => $errors,
            'data' => ['email' => $email, 'topic' => $topic, 'city' => $city, 'city_name' => $name, 'source' => $source]];
}

function rmt_byg_label(array $s): string {
    $what = RMT_BYG_TOPICS[(string) $s['topic']] ?? 'updates';
    return (string) $s['city'] === '' ? 'Changes to the ' . $what . ' in every city we track'
                                      : ucfirst($what) . ' in ' . (string) $s['city_name'];
}

/** The page a signup is about, so every email can send the reader back to it. */
function rmt_byg_page(array $s): string {
    $city = (string) $s['city'];
    if ((string) $s['topic'] === 'tax') return url('tourist-tax-calculator' . ($city !== '' ? '?city=' . rawurlencode($city) : ''));
    return $city !== '' ? url('warnings/' . $city) : url('ruined');
}

function rmt_byg_save(array $data): array {
    $raw = bin2hex(random_bytes(24));
    $hash = hash('sha256', $raw);
    $ex = q_one("SELECT id FROM byg_signups WHERE email = ? AND topic = ? AND city = ? AND status IN ('pending', 'active')
                  ORDER BY id DESC LIMIT 1", [$data['email'], $data['topic'], $data['city']]);
    if ($ex) {
        q_run('UPDATE byg_signups SET token_hash = ? WHERE id = ?', [$hash, (int) $ex['id']]);
        return ['id' => (int) $ex['id'], 'token' => $raw];
    }
    $visitor = function_exists('rmt_visitor_id') ? rmt_visitor_id() : null;
    $acq = function_exists('rmt_acq_current') ? rmt_acq_current() : [];
    $id = (int) q_run('INSERT INTO byg_signups (email, topic, city, city_name, status, token_hash, source, visitor, created_at,
                                                acq_source, acq_medium, acq_campaign, acq_content)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                      [$data['email'], $data['topic'], $data['city'], $data['city_name'], 'pending', $hash,
                       $data['source'] !== '' ? $data['source'] : null, $visitor, date('Y-m-d H:i:s'),
                       $acq['source'] ?? null, $acq['medium'] ?? null, $acq['campaign'] ?? null, $acq['content'] ?? null]);
    return ['id' => $id, 'token' => $raw];
}

function rmt_byg_by_token(string $raw): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $raw)) return null;
    return q_one('SELECT * FROM byg_signups WHERE token_hash = ?', [hash('sha256', $raw)]);
}

function rmt_byg_by_id(int $id): ?array {
    return $id > 0 ? q_one('SELECT * FROM byg_signups WHERE id = ?', [$id]) : null;
}

/** The off switch in every email. Signed, so it needs no account and cannot be guessed. */
function rmt_byg_off_sig(int $id, string $email): string {
    return substr(hash_hmac('sha256', 'byg-off:' . $id . ':' . $email, (string) cfg('security_salt')), 0, 32);
}

function rmt_byg_off_url(array $s): string {
    return abs_url(url('updates/off?a=' . (int) $s['id'] . '&s=' . rmt_byg_off_sig((int) $s['id'], (string) $s['email'])));
}

function rmt_byg_send_confirm(array $s, string $raw): bool {
    $link = abs_url(url('updates/confirm/' . $raw));
    $label = rmt_byg_label($s);
    $html = rmt_mail_layout('Confirm your updates',
        '<p>You asked to hear about: <b>' . e($label) . '</b>.</p>'
        . '<p>Confirm it and we email you only when it changes. No newsletter, and every email has a one click off switch.</p>',
        'Confirm', $link);
    $text = "Confirm your RuinMyTrip updates ($label):\n$link\n\nIf you did not ask for this, ignore this email and nothing will be sent.";
    $r = rmt_mail_send((string) $s['email'], 'Confirm: ' . $label, $html, $text);
    return (bool) ($r[0] ?? false);
}

/* ---------- change detection ---------- */

/** One hash per topic and city of what the page says, so a change is a different hash. */
function rmt_byg_current_hashes(): array {
    $out = [];
    if (function_exists('rmt_tt_cities')) {
        foreach (rmt_tt_cities() as $k => $c) {
            $keep = array_diff_key($c, array_flip(['name', 'note', 'dest']));
            ksort($keep);
            $out['tax'][(string) $k] = hash('sha256', (string) json_encode($keep));
        }
    }
    if (function_exists('rmt_city_scams_all')) {
        foreach (rmt_city_scams_all() as $slug => $c) {
            $items = array_map(static fn($it) => [$it['name'] ?? '', $it['what'] ?? '', $it['avoid'] ?? ''], (array) ($c['items'] ?? []));
            $out['scams'][(string) $slug] = hash('sha256', (string) json_encode($items));
        }
    }
    return $out;
}

/**
 * Compare against the last check and email confirmed signups about what changed. The first sight
 * of a city only records it. Returns ['changed' => [topic => [city...]], 'sent' => n].
 * $send false is a dry run: nothing is written and nothing is sent.
 */
function rmt_byg_notify(bool $send = true): array {
    $now = date('Y-m-d H:i:s');
    $changed = [];
    foreach (rmt_byg_current_hashes() as $topic => $cities) {
        foreach ($cities as $city => $hash) {
            $row = q_one('SELECT hash FROM byg_state WHERE topic = ? AND city = ?', [$topic, $city]);
            if (!$row) {
                if ($send) q_run('INSERT INTO byg_state (topic, city, hash, changed_at) VALUES (?,?,?,?)', [$topic, $city, $hash, $now]);
                continue;
            }
            if ((string) $row['hash'] === $hash) continue;
            $changed[$topic][] = $city;
            if ($send) q_run('UPDATE byg_state SET hash = ?, changed_at = ? WHERE topic = ? AND city = ?', [$hash, $now, $topic, $city]);
        }
    }
    $sent = 0;
    if (!$send || !$changed) return ['changed' => $changed, 'sent' => 0];
    foreach ($changed as $topic => $list) {
        $names = rmt_byg_cities($topic);
        $ph = implode(',', array_fill(0, count($list), '?'));
        $subs = q_all("SELECT * FROM byg_signups WHERE status = 'active' AND topic = ? AND (city = '' OR city IN ($ph))",
                      array_merge([$topic], $list));
        foreach ($subs as $s) {
            if (!empty($s['notified_at']) && time() - (int) strtotime((string) $s['notified_at']) < RMT_BYG_MAIL_GAP) continue;
            $mine = (string) $s['city'] === '' ? $list : [(string) $s['city']];
            if (rmt_byg_mail_change($s, array_map(static fn($c) => $names[$c] ?? $c, $mine))) $sent++;
        }
    }
    return ['changed' => $changed, 'sent' => $sent];
}

function rmt_byg_mail_change(array $s, array $cityNames): bool {
    $what = RMT_BYG_TOPICS[(string) $s['topic']] ?? 'information';
    $where = implode(', ', $cityNames);
    $page = abs_url(rmt_byg_page($s));
    $off = rmt_byg_off_url($s);
    $html = rmt_mail_layout('The ' . $what . ' changed',
        '<p>What we show for the ' . e($what) . ' in <b>' . e($where) . '</b> was just updated from the official source.</p>'
        . '<p>Check it before you book or pay.</p>', 'See what changed', $page)
        . '<p style="font-family:sans-serif;font-size:12px;color:#8895a3;text-align:center">'
        . '<a href="' . e($off) . '" style="color:#8895a3">Stop these emails</a></p>';
    $text = "The $what in $where was just updated.\nSee it: $page\nStop these emails: $off";
    $r = rmt_mail_send((string) $s['email'], 'Updated: ' . $what . ' in ' . $where, $html, $text, rmt_mail_unsub_headers($off));
    if (!($r[0] ?? false)) return false;
    q_run('UPDATE byg_signups SET notified_at = ?, notify_count = notify_count + 1 WHERE id = ?', [date('Y-m-d H:i:s'), (int) $s['id']]);
    return true;
}

/* ---------- controllers ---------- */

/** POST /updates  the form on tax and warnings pages. */
function byg_submit(array $a): void {
    csrf_check();
    $back = rmt_return_to('/');
    if (trim((string) input('website')) !== '') redirect('/updates/sent');
    $v = rmt_byg_validate($_POST);
    if (!$v['ok']) {
        flash(implode(' ', $v['errors']));
        redirect($back . (str_contains($back, '#') ? '' : '#byg'));
    }
    $data = $v['data'];
    if (!rmt_rate_ok('byg_ip', rmt_client_ip(), 6, 3600) || !rmt_rate_ok('byg_email', $data['email'], 4, 86400)) {
        flash('That was a lot of signups in a short time. Try again a little later.');
        redirect($back);
    }
    $saved = rmt_byg_save($data);
    $s = rmt_byg_by_id($saved['id']);
    if ($s && $s['status'] === 'pending') rmt_byg_send_confirm($s, $saved['token']);
    rmt_track('byg_submitted', ['source' => 'other']);
    $_SESSION[RMT_BYG_SESSION] = (int) $saved['id'];
    redirect('/updates/sent');
}

/** GET /updates/sent  check your inbox. Says the same thing whether the address was known or not. */
function byg_sent(array $a): void {
    $s = rmt_byg_by_id((int) ($_SESSION[RMT_BYG_SESSION] ?? 0));
    if (!$s) redirect('/');
    view('byg_sent', ['s' => $s, 'label' => rmt_byg_label($s), 'confirmed' => false],
         ['title' => 'Check your inbox | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}

/** GET /updates/confirm/{token}  one button, so a mail scanner opening the link switches nothing on. */
function byg_confirm_form(array $a): void {
    $s = rmt_byg_by_token((string) ($a['token'] ?? ''));
    view('byg_confirm', ['s' => $s, 'token' => (string) ($a['token'] ?? ''), 'label' => $s ? rmt_byg_label($s) : ''],
         ['title' => 'Confirm your updates | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}

/** POST /updates/confirm */
function byg_confirm_submit(array $a): void {
    csrf_check();
    $s = rmt_byg_by_token((string) input('token'));
    if (!$s || !in_array($s['status'], ['pending', 'active'], true)) {
        flash('That link has expired. Sign up again from the page you were reading.');
        redirect('/');
    }
    if ($s['status'] === 'pending') {
        q_run("UPDATE byg_signups SET status = 'active', confirmed_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), (int) $s['id']]);
        $s['status'] = 'active';
        rmt_track('byg_confirmed', ['source' => 'other']);
    }
    view('byg_sent', ['s' => $s, 'label' => rmt_byg_label($s), 'confirmed' => true],
         ['title' => 'You are signed up | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}

/** GET /updates/off?a=&s=  and POST: the off switch in every email. */
function byg_off(array $a): void {
    $id = (int) input('a');
    $sig = (string) input('s');
    $s = rmt_byg_by_id($id);
    $valid = $s && hash_equals(rmt_byg_off_sig($id, (string) $s['email']), $sig);
    $done = false;
    if ($valid && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        q_run("UPDATE byg_signups SET status = 'off' WHERE id = ?", [$id]);
        $done = true;
    }
    view('byg_off', ['valid' => $valid, 'done' => $done || ($s['status'] ?? '') === 'off', 'id' => $id, 'sig' => $sig,
                     'label' => $s ? rmt_byg_label($s) : ''],
         ['title' => 'Updates | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}

/** GET /updates/watch  signed in: one button to watch the city. Signed out: Google first, then back here. */
function byg_watch_form(array $a): void {
    $v = rmt_byg_validate(['email' => 'x@example.com'] + $_GET);
    $back = '/updates/watch?' . http_build_query(['topic' => $v['data']['topic'], 'city' => $v['data']['city'], 'source' => $v['data']['source']]);
    if (!$v['ok'] || $v['data']['city'] === '') { flash(implode(' ', $v['errors']) ?: 'Pick a city to watch.'); redirect('/'); }
    if (!current_user()) {
        if (!rmt_google_enabled()) redirect('/login?return=' . rawurlencode($back));
        redirect('/auth/google?return=' . rawurlencode($back));
    }
    view('byg_watch', ['label' => rmt_byg_label($v['data']), 'd' => $v['data']],
         ['title' => 'Watch a city | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}

/** POST /updates/watch  a verified account email is switched on at once; otherwise the usual confirm email. */
function byg_watch_submit(array $a): void {
    csrf_check();
    $me = current_user();
    if (!$me) redirect('/login');
    $v = rmt_byg_validate(['email' => (string) $me['email']] + $_POST);
    if (!$v['ok'] || $v['data']['city'] === '') { flash(implode(' ', $v['errors']) ?: 'Pick a city to watch.'); redirect('/'); }
    $saved = rmt_byg_save($v['data']);
    $s = rmt_byg_by_id($saved['id']);
    if ($s && $s['status'] === 'pending') {
        if (email_is_verified($me)) {
            q_run("UPDATE byg_signups SET status = 'active', confirmed_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), (int) $s['id']]);
            $s['status'] = 'active';
            rmt_track('byg_confirmed', ['source' => 'other', 'detail' => 'account']);
        } else {
            rmt_byg_send_confirm($s, $saved['token']);
        }
    }
    rmt_track('byg_submitted', ['source' => 'other', 'detail' => 'account']);
    $_SESSION[RMT_BYG_SESSION] = (int) $saved['id'];
    if ($s && $s['status'] === 'active') {
        view('byg_sent', ['s' => $s, 'label' => rmt_byg_label($s), 'confirmed' => true],
             ['title' => 'You are signed up | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
        return;
    }
    redirect('/updates/sent');
}
