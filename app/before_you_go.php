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
    // The trip date is optional. A date in the past or more than two years out is a typo, not a trip.
    $date = trim((string) ($in['travel_date'] ?? ''));
    if ($date !== '') {
        $ts = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? strtotime($date . ' 00:00:00') : false;
        $today = strtotime(date('Y-m-d') . ' 00:00:00');
        if ($ts === false || date('Y-m-d', $ts) !== $date) { $errors[] = 'Enter the date your trip starts, or leave it empty.'; $date = ''; }
        elseif ($ts < $today || $ts > $today + 731 * 86400) { $errors[] = 'Pick a trip date between today and two years from now.'; $date = ''; }
    }
    return ['ok' => !$errors, 'errors' => $errors,
            'data' => ['email' => $email, 'topic' => $topic, 'city' => $city, 'city_name' => $name, 'source' => $source,
                       'travel_date' => $date, 'page' => rmt_byg_clean_page((string) ($in['page'] ?? ''))]];
}

/** Only our own answer pages may be named as the page a signup came from. */
function rmt_byg_clean_page(string $p): string {
    $p = trim($p);
    // The calculator keeps its inputs, so "email me this calculation" can send the same answer.
    if (preg_match('~^/tourist-tax-calculator\?([^#]*)~', $p, $m)) {
        parse_str($m[1], $q);
        $keep = [];
        if (isset($q['city']) && preg_match('/^[a-z0-9-]{2,40}$/', (string) $q['city'])) $keep['city'] = (string) $q['city'];
        if (isset($q['tier']) && preg_match('/^[a-z0-9_-]{1,20}$/i', (string) $q['tier'])) $keep['tier'] = (string) $q['tier'];
        foreach (['nights' => 60, 'people' => 20, 'rooms' => 10] as $k => $max) {
            if (isset($q[$k]) && ctype_digit((string) $q[$k])) $keep[$k] = (string) max(1, min($max, (int) $q[$k]));
        }
        if (isset($q['price']) && is_numeric($q['price'])) $keep['price'] = (string) max(0, min(100000000, round((float) $q['price'], 2)));
        return '/tourist-tax-calculator' . ($keep ? '?' . http_build_query($keep) : '');
    }
    $p = (string) preg_replace('/[?#].*$/s', '', $p);
    return preg_match('~^/(blog|warnings)/[a-z0-9-]{1,120}$~', $p) || $p === '/tourist-tax-calculator' || $p === '/tourist-tax-2027' ? $p : '';
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
    $date = (string) ($data['travel_date'] ?? '');
    $page = (string) ($data['page'] ?? '');
    if ($ex) {
        // A new trip date is a new trip: its reminders start again.
        if ($date !== '') q_run('UPDATE byg_signups SET travel_date = ?, reminded_14 = NULL, reminded_3 = NULL WHERE id = ? AND (travel_date IS NULL OR travel_date <> ?)',
                                [$date, (int) $ex['id'], $date]);
        if ($page !== '') q_run('UPDATE byg_signups SET page = ? WHERE id = ?', [$page, (int) $ex['id']]);
        q_run('UPDATE byg_signups SET token_hash = ? WHERE id = ?', [$hash, (int) $ex['id']]);
        return ['id' => (int) $ex['id'], 'token' => $raw];
    }
    $visitor = function_exists('rmt_visitor_id') ? rmt_visitor_id() : null;
    $acq = function_exists('rmt_acq_current') ? rmt_acq_current() : [];
    $id = (int) q_run('INSERT INTO byg_signups (email, topic, city, city_name, status, token_hash, source, visitor, created_at,
                                                acq_source, acq_medium, acq_campaign, acq_content, travel_date, page)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                      [$data['email'], $data['topic'], $data['city'], $data['city_name'], 'pending', $hash,
                       $data['source'] !== '' ? $data['source'] : null, $visitor, date('Y-m-d H:i:s'),
                       $acq['source'] ?? null, $acq['medium'] ?? null, $acq['campaign'] ?? null, $acq['content'] ?? null,
                       $date !== '' ? $date : null, $page !== '' ? $page : null]);
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
    $sum = rmt_byg_summary($s);
    $when = rmt_byg_when($s);
    $html = rmt_mail_layout($sum['title'], rmt_byg_summary_html($sum)
        . '<p style="margin-top:18px"><b>Want a reminder before you go?</b> Confirm and we email you '
        . ($when !== '' ? '14 days and 3 days before ' . e($when) . ', and ' : '')
        . 'once if the official ' . e(RMT_BYG_TOPICS[(string) $s['topic']] ?? 'rules') . ' change. No newsletter, and every email has a one click off switch.</p>',
        'Confirm my reminders', $link);
    $text = $sum['title'] . "\n\n" . implode("\n", $sum['lines']) . "\nRead it: " . $sum['url']
          . "\n\nConfirm your reminders ($label):\n$link\n\nIf you did not ask for this, ignore this email and nothing more will be sent.";
    $r = rmt_mail_send((string) $s['email'], $sum['title'], $html, $text);
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

/* ---------- the answer by email, and reminders before the trip ---------- */

/** "Sunday 14 December 2026", or '' when the signup has no trip date. */
function rmt_byg_when(array $s): string {
    $d = (string) ($s['travel_date'] ?? '');
    $ts = $d !== '' ? strtotime($d . ' 00:00:00') : false;
    return $ts ? date('l j F Y', $ts) : '';
}

/**
 * What the signup was reading, ready for an email: the note's own title and summary, the city's tax
 * line, or the first official warnings. Always ends in a link back to the page, which stays free.
 */
function rmt_byg_summary(array $s): array {
    $topic = (string) $s['topic'];
    $city = (string) $s['city'];
    $name = (string) ($s['city_name'] ?? '');
    $page = (string) ($s['page'] ?? '');
    $title = $name !== '' ? ucfirst(RMT_BYG_TOPICS[$topic] ?? 'updates') . ' in ' . $name : rmt_byg_label($s);
    $lines = [];
    if (preg_match('~^/blog/([a-z0-9-]+)$~', $page, $m) && function_exists('q_one')) {
        try { $p = q_one('SELECT title, summary FROM blog_posts WHERE slug = ?', [$m[1]]); } catch (Throwable $e) { $p = null; }
        if ($p) { $title = (string) $p['title']; if (trim((string) $p['summary']) !== '') $lines[] = trim((string) $p['summary']); }
    }
    if (str_starts_with($page, '/tourist-tax-calculator?') && function_exists('rmt_tt_estimate')) {
        $calc = rmt_byg_calc_line($page);
        if ($calc !== null) { $title = $calc['title']; $lines[] = $calc['line']; }
    }
    if ($topic === 'tax' && $city !== '' && function_exists('rmt_tt_cities') && count($lines) < 2) {
        $c = rmt_tt_cities()[$city] ?? null;
        if ($c) {
            if (!empty($c['rate'])) $lines[] = 'Tourist tax in ' . (string) $c['name'] . ': ' . (string) $c['rate'] . '.';
            if (!empty($c['tip'])) $lines[] = (string) $c['tip'];
        }
    }
    if (!$lines && $topic === 'scams' && $city !== '' && function_exists('rmt_city_scams_all')) {
        foreach (array_slice((array) (rmt_city_scams_all()[$city]['items'] ?? []), 0, 3) as $it) {
            $n = trim((string) ($it['name'] ?? ''));
            $avoid = trim((string) ($it['avoid'] ?? ''));
            if ($n !== '') $lines[] = $n . ($avoid !== '' ? ': ' . $avoid : '');
        }
    }
    $url = abs_url($page !== '' ? url(ltrim($page, '/')) : rmt_byg_page($s));
    return ['title' => $title, 'lines' => $lines, 'url' => $url];
}

/** The calculator's answer for a saved query, worked out from today's rates. Null when it cannot be. */
function rmt_byg_calc_line(string $page): ?array {
    parse_str((string) parse_url($page, PHP_URL_QUERY), $q);
    $c = rmt_tt_cities()[(string) ($q['city'] ?? '')] ?? null;
    if (!$c) return null;
    $tk = (string) ($q['tier'] ?? '');
    $tierKey = isset($c['tiers'][$tk]) ? $tk : (string) array_key_first($c['tiers']);
    $nights = max(1, (int) ($q['nights'] ?? 3));
    $people = max(1, (int) ($q['people'] ?? 2));
    $rooms = max(1, (int) ($q['rooms'] ?? 1));
    $needsPrice = rmt_tt_needs($c)['price'];
    if ($needsPrice && !isset($q['price'])) return null;
    $e = rmt_tt_estimate($c, $c['tiers'][$tierKey], $nights, $people, $rooms, (float) ($q['price'] ?? 0));
    $label = (string) ($c['tiers'][$tierKey]['label'] ?? '');
    $line = 'Your stay: ' . $nights . ' ' . ($nights === 1 ? 'night' : 'nights') . ', ' . $people . ' ' . ($people === 1 ? 'person' : 'people')
          . ', ' . $rooms . ' ' . ($rooms === 1 ? 'room' : 'rooms') . ($label !== '' ? ', ' . $label : '')
          . ($needsPrice ? ', ' . rmt_tt_money((float) $q['price'], (string) $c['cur']) . ' a night' : '')
          . '. Tourist tax: ' . rmt_tt_money((float) $e['total'], (string) $c['cur'])
          . ' (' . rmt_tt_money((float) $e['per_night'], (string) $c['cur']) . ' a night' . ($e['paid_nights'] < $e['nights'] ? ', charged for ' . $e['paid_nights'] . ' nights' : '') . ').';
    return ['title' => $c['name'] . ' tourist tax for your stay: ' . rmt_tt_money((float) $e['total'], (string) $c['cur']), 'line' => $line];
}

function rmt_byg_summary_html(array $sum): string {
    $h = '';
    foreach ($sum['lines'] as $l) $h .= '<p>' . e($l) . '</p>';
    return $h . '<p><a href="' . e($sum['url']) . '">Read the full page with sources</a></p>';
}

/** Which reminder is due today for a confirmed signup: 14, 3, or null. */
function rmt_byg_reminder_due(array $s, string $today): ?int {
    if ((string) ($s['status'] ?? '') !== 'active' || empty($s['travel_date'])) return null;
    $days = (int) round((strtotime((string) $s['travel_date'] . ' 00:00:00') - strtotime($today . ' 00:00:00')) / 86400);
    if ($days < 0) return null;
    if ($days <= 3) return empty($s['reminded_3']) ? 3 : null;
    // Signed up 10 days out: the 14 day email still goes, once, as the first reminder.
    if ($days <= 14) return empty($s['reminded_14']) ? 14 : null;
    return null;
}

/** Send the reminders due today. $send false is a dry run. Returns ['due' => n, 'sent' => n]. */
function rmt_byg_remind(bool $send = true, ?string $today = null): array {
    $today = $today ?? date('Y-m-d');
    $max = date('Y-m-d', strtotime($today . ' 00:00:00') + 14 * 86400);
    $due = 0; $sent = 0;
    foreach (q_all("SELECT * FROM byg_signups WHERE status = 'active' AND travel_date IS NOT NULL AND travel_date >= ? AND travel_date <= ?", [$today, $max]) as $s) {
        $which = rmt_byg_reminder_due($s, $today);
        if ($which === null) continue;
        $due++;
        if ($send && rmt_byg_mail_reminder($s, $which, $today)) $sent++;
    }
    return ['due' => $due, 'sent' => $sent];
}

function rmt_byg_mail_reminder(array $s, int $which, string $today): bool {
    $days = (int) round((strtotime((string) $s['travel_date'] . ' 00:00:00') - strtotime($today . ' 00:00:00')) / 86400);
    $name = (string) ($s['city_name'] ?? '') !== '' ? (string) $s['city_name'] : 'your trip';
    $head = $days === 0 ? 'Your trip to ' . $name . ' starts today' : 'Your trip to ' . $name . ' is in ' . $days . ' day' . ($days === 1 ? '' : 's');
    $sum = rmt_byg_summary($s);
    $off = rmt_byg_off_url($s);
    $plan = abs_url(url('plan'));
    $html = rmt_mail_layout($head, '<p>Here is what we have for ' . e($name) . ' today, checked against the official source.</p>'
            . '<p><b>' . e($sum['title']) . '</b></p>' . rmt_byg_summary_html($sum)
            . '<p>Still things to sort out? Make a free before you go checklist for the trip: <a href="' . e($plan) . '">' . e($plan) . '</a></p>',
            'Check the page', $sum['url'])
        . '<p style="font-family:sans-serif;font-size:12px;color:#8895a3;text-align:center">'
        . '<a href="' . e($off) . '" style="color:#8895a3">Stop these emails</a></p>';
    $text = $head . "\n\n" . $sum['title'] . "\n" . implode("\n", $sum['lines']) . "\nRead it: " . $sum['url']
          . "\nChecklist: $plan\nStop these emails: $off";
    $r = rmt_mail_send((string) $s['email'], $head, $html, $text, rmt_mail_unsub_headers($off));
    if (!($r[0] ?? false)) return false;
    $now = date('Y-m-d H:i:s');
    // A 3 day email also stands in for a 14 day one that never went.
    if ($which === 3) q_run('UPDATE byg_signups SET reminded_3 = ?, reminded_14 = COALESCE(reminded_14, ?) WHERE id = ?', [$now, $now, (int) $s['id']]);
    else q_run('UPDATE byg_signups SET reminded_14 = ? WHERE id = ?', [$now, (int) $s['id']]);
    if (function_exists('rmt_track')) rmt_track('byg_reminder_sent', ['source' => 'other', 'detail' => 'byg_d' . $which]);
    return true;
}

/* ---------- after the trip: what went wrong? ---------- */

const RMT_BYG_STORY_AFTER = 4;   // days after the trip date
const RMT_BYG_STORY_UNTIL = 30;  // past this, the trip is too long ago to ask

/** Is the one "how did it go" email due today for this signup? */
function rmt_byg_story_due(array $s, string $today): bool {
    if ((string) ($s['status'] ?? '') !== 'active' || empty($s['travel_date']) || !empty($s['story_asked'])) return false;
    $days = (int) round((strtotime($today . ' 00:00:00') - strtotime((string) $s['travel_date'] . ' 00:00:00')) / 86400);
    return $days >= RMT_BYG_STORY_AFTER && $days <= RMT_BYG_STORY_UNTIL;
}

/** The story form for this signup's city: /story?topic=tax:rome */
function rmt_byg_story_url(array $s): string {
    $topic = ((string) $s['topic'] === 'tax' ? 'tax:' : 'warnings:') . (string) $s['city'];
    return abs_url(url('story?' . http_build_query(['topic' => $topic])));
}

/** Ask everybody due today, once each. Returns ['due' => n, 'sent' => n]. */
function rmt_byg_story_run(bool $send = true, ?string $today = null): array {
    $today = $today ?? date('Y-m-d');
    $from = date('Y-m-d', strtotime($today . ' 00:00:00') - RMT_BYG_STORY_UNTIL * 86400);
    $to = date('Y-m-d', strtotime($today . ' 00:00:00') - RMT_BYG_STORY_AFTER * 86400);
    $due = 0; $sent = 0;
    foreach (q_all("SELECT * FROM byg_signups WHERE status = 'active' AND city <> '' AND travel_date IS NOT NULL
                     AND story_asked IS NULL AND travel_date >= ? AND travel_date <= ?", [$from, $to]) as $s) {
        if (!rmt_byg_story_due($s, $today)) continue;
        $due++;
        if ($send && rmt_byg_mail_story($s)) $sent++;
    }
    return ['due' => $due, 'sent' => $sent];
}

function rmt_byg_mail_story(array $s): bool {
    $name = (string) ($s['city_name'] ?? '') !== '' ? (string) $s['city_name'] : 'your trip';
    $head = 'How was ' . $name . '?';
    $link = rmt_byg_story_url($s);
    $off = rmt_byg_off_url($s);
    $html = rmt_mail_layout($head,
        '<p>You read up on ' . e($name) . ' before you went. Did anything go wrong, or nearly? A charge nobody mentioned, a scam, a rule, a queue.</p>'
        . '<p>Two lines is plenty. An editor checks every story before anything from it is published, and it is what the next person going to ' . e($name) . ' reads.</p>',
        'Tell us what happened', $link)
        . '<p style="font-family:sans-serif;font-size:12px;color:#8895a3;text-align:center">'
        . '<a href="' . e($off) . '" style="color:#8895a3">Stop these emails</a></p>';
    $text = "$head\n\nDid anything go wrong, or nearly? Tell us in two lines: $link\nAn editor checks every story before anything is published.\n\nStop these emails: $off";
    $r = rmt_mail_send((string) $s['email'], $head, $html, $text, rmt_mail_unsub_headers($off));
    if (!($r[0] ?? false)) return false;
    q_run('UPDATE byg_signups SET story_asked = ? WHERE id = ?', [date('Y-m-d H:i:s'), (int) $s['id']]);
    if (function_exists('rmt_track')) rmt_track('byg_story_asked', ['source' => 'other']);
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
    rmt_track('byg_submitted', ['source' => 'other', 'detail' => $data['travel_date'] !== '' ? 'byg_dated' : 'byg_undated']);
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
