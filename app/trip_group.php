<?php
declare(strict_types=1);

/**
 * Group trip link (2026-11-23, migration 132).
 *
 * The research behind it (rmt_research/reports/Getting RuinMyTrip its first members.md): group
 * tools that grew without ads all spread the same way. One person organises and the tool is useful
 * to them alone; the invite goes as a link into the group chat the friends already have; the friend
 * who taps it sees the real plan at once and can act with a first name, and is asked to make an
 * account only after that. Nobody walks into an empty room, because the room is their own group.
 *
 * So a trip gets one link, /trip/join/{code}, that the owner makes and can reset. Anybody holding
 * it sees the trip, who is in, the before you go checklist, the group's tourist tax and the date
 * vote, and can join with a name. A joined guest can tick and claim checklist items and vote on
 * dates. Saving it to an account turns the guest into an ordinary trip member (trip_members).
 *
 * The link is a capability, like a shared document link: holding it is the permission, and the
 * owner can reset it, which cuts off nobody already joined but stops the old link working.
 */

const RMT_TG_COOKIE = 'rmt_tg';
const RMT_TG_MAX_GUESTS = 20;
const RMT_TG_MAX_OPTIONS = 4;
const RMT_TG_NAME_MAX = 40;

/** A clean first name, or '' when there is nothing usable in it. */
function rmt_tg_clean_name(string $n): string {
    $n = trim((string) preg_replace('/\s+/u', ' ', strip_tags($n)));
    $n = (string) preg_replace('/[^\p{L}\p{M}\p{N} .\'-]/u', '', $n);
    return mb_substr(trim($n), 0, RMT_TG_NAME_MAX);
}

/** The trip's group code, made on first use. */
function rmt_tg_code(array $trip, bool $reset = false): string {
    $code = (string) ($trip['group_code'] ?? '');
    if ($code !== '' && !$reset) return $code;
    $code = bin2hex(random_bytes(10));
    q_run('UPDATE trips SET group_code = ? WHERE id = ?', [$code, (int) $trip['id']]);
    return $code;
}

function rmt_tg_url(string $code): string {
    return abs_url(url('trip/join/' . $code));
}

/** The published trip behind a code, with its city, or null. */
function rmt_tg_trip(string $code): ?array {
    if (!preg_match('/^[a-f0-9]{20}$/', $code)) return null;
    $t = q_one("SELECT t.*, d.name dest_name, d.slug dest_slug FROM trips t
                LEFT JOIN destinations d ON d.id = t.destination_id
                WHERE t.group_code = ? AND t.status = 'published'", [$code]);
    return $t ?: null;
}

/* ---------- who is looking ---------- */

function rmt_tg_cookie_name(int $tripId): string { return RMT_TG_COOKIE . $tripId; }

/** The guest row this browser joined this trip as, or null. */
function rmt_tg_guest(int $tripId, ?string $rawKey = null): ?array {
    $raw = $rawKey ?? (string) ($_COOKIE[rmt_tg_cookie_name($tripId)] ?? '');
    if (!preg_match('/^[a-f0-9]{48}$/', $raw)) return null;
    $g = q_one('SELECT * FROM trip_guests WHERE trip_id = ? AND key_hash = ?', [$tripId, hash('sha256', $raw)]);
    return $g ?: null;
}

/**
 * Who the viewer is on this trip: ['kind' => owner|member|guest|null, 'name', 'voter'].
 * voter is the key used for votes: u:{user id} for an account, g:{guest id} for a guest.
 */
function rmt_tg_viewer(array $trip, ?array $me, ?array $guest): array {
    $role = $me ? rmt_trip_role($trip, $me) : null;
    if ($role !== null) return ['kind' => $role === 'owner' ? 'owner' : 'member', 'name' => (string) $me['username'], 'voter' => 'u:' . (int) $me['id']];
    if ($guest) return ['kind' => 'guest', 'name' => (string) $guest['name'], 'voter' => 'g:' . (int) $guest['id']];
    return ['kind' => null, 'name' => '', 'voter' => ''];
}

/** Everybody in: the owner, members with accounts, then guests who have not made one. */
function rmt_tg_people(array $trip): array {
    $out = [];
    $owner = q_one('SELECT username FROM users WHERE id = ?', [(int) $trip['user_id']]);
    $out[] = ['name' => (string) ($owner['username'] ?? 'Organiser'), 'kind' => 'owner'];
    foreach (q_all("SELECT u.username FROM trip_members m JOIN users u ON u.id = m.user_id
                    WHERE m.trip_id = ? AND m.state = 'active' ORDER BY m.created_at", [(int) $trip['id']]) as $m) {
        $out[] = ['name' => (string) $m['username'], 'kind' => 'member'];
    }
    foreach (q_all('SELECT name FROM trip_guests WHERE trip_id = ? AND user_id IS NULL ORDER BY id', [(int) $trip['id']]) as $g) {
        $out[] = ['name' => (string) $g['name'], 'kind' => 'guest'];
    }
    return $out;
}

/** Join as a guest. Returns ['ok', 'error', 'key', 'guest']. */
function rmt_tg_join(array $trip, string $name): array {
    $name = rmt_tg_clean_name($name);
    if ($name === '') return ['ok' => false, 'error' => 'Put your first name so the group knows who you are.'];
    $n = (int) q_one('SELECT COUNT(*) n FROM trip_guests WHERE trip_id = ?', [(int) $trip['id']])['n'];
    if ($n >= RMT_TG_MAX_GUESTS) return ['ok' => false, 'error' => 'This group is full.'];
    $raw = bin2hex(random_bytes(24));
    $id = (int) q_run('INSERT INTO trip_guests (trip_id, name, key_hash, created_at) VALUES (?,?,?,?)',
                      [(int) $trip['id'], $name, hash('sha256', $raw), date('Y-m-d H:i:s')]);
    return ['ok' => true, 'error' => '', 'key' => $raw, 'guest' => q_one('SELECT * FROM trip_guests WHERE id = ?', [$id])];
}

/* ---------- checklist, claims, dates ---------- */

/** item key => who is sorting it */
function rmt_tg_claims(int $tripId): array {
    $out = [];
    foreach (q_all('SELECT item_key, who FROM trip_checklist_claims WHERE trip_id = ?', [$tripId]) as $r) $out[(string) $r['item_key']] = (string) $r['who'];
    return $out;
}

/** Tick, untick, claim or unclaim one item. Only keys the city's checklist really has. */
function rmt_tg_checklist_act(array $trip, string $item, string $act, array $viewer, ?int $userId): bool {
    if ($viewer['kind'] === null) return false;
    if (!in_array($item, array_column(rmt_trip_checklist_items((string) $trip['dest_slug']), 'key'), true)) return false;
    $tid = (int) $trip['id'];
    $now = date('Y-m-d H:i:s');
    if ($act === 'done') {
        if (!q_one('SELECT 1 x FROM trip_checklist WHERE trip_id = ? AND item_key = ?', [$tid, $item])) {
            q_run('INSERT INTO trip_checklist (trip_id, item_key, user_id, done_at) VALUES (?,?,?,?)', [$tid, $item, $userId, $now]);
        }
    } elseif ($act === 'undone') {
        q_run('DELETE FROM trip_checklist WHERE trip_id = ? AND item_key = ?', [$tid, $item]);
    } elseif ($act === 'claim') {
        q_run('DELETE FROM trip_checklist_claims WHERE trip_id = ? AND item_key = ?', [$tid, $item]);
        q_run('INSERT INTO trip_checklist_claims (trip_id, item_key, who, created_at) VALUES (?,?,?,?)', [$tid, $item, $viewer['name'], $now]);
    } elseif ($act === 'unclaim') {
        q_run('DELETE FROM trip_checklist_claims WHERE trip_id = ? AND item_key = ? AND who = ?', [$tid, $item, $viewer['name']]);
    } else {
        return false;
    }
    return true;
}

/** Date options with their votes, and whether the viewer voted for each. */
function rmt_tg_options(int $tripId, string $voter = ''): array {
    $out = [];
    foreach (q_all('SELECT * FROM trip_date_options WHERE trip_id = ? ORDER BY date_from, id', [$tripId]) as $o) {
        $votes = q_all('SELECT voter FROM trip_date_votes WHERE option_id = ?', [(int) $o['id']]);
        $o['votes'] = count($votes);
        $o['mine'] = $voter !== '' && in_array($voter, array_column($votes, 'voter'), true);
        $out[] = $o;
    }
    return $out;
}

/** Owner adds a date option. Returns an error string, '' on success. */
function rmt_tg_add_option(array $trip, string $from, string $to): string {
    $ok = static fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && date('Y-m-d', (int) strtotime($d)) === $d;
    if (!$ok($from) || !$ok($to)) return 'Pick both dates.';
    if ($to < $from) return 'The last day comes after the first.';
    if ($from < date('Y-m-d')) return 'Pick dates from today on.';
    if ((strtotime($to) - strtotime($from)) / 86400 > 60) return 'Keep it under two months.';
    $n = (int) q_one('SELECT COUNT(*) n FROM trip_date_options WHERE trip_id = ?', [(int) $trip['id']])['n'];
    if ($n >= RMT_TG_MAX_OPTIONS) return 'Up to ' . RMT_TG_MAX_OPTIONS . ' options. Remove one first.';
    if (q_one('SELECT 1 x FROM trip_date_options WHERE trip_id = ? AND date_from = ? AND date_to = ?', [(int) $trip['id'], $from, $to])) return 'Those dates are already an option.';
    q_run('INSERT INTO trip_date_options (trip_id, date_from, date_to, created_at) VALUES (?,?,?,?)', [(int) $trip['id'], $from, $to, date('Y-m-d H:i:s')]);
    return '';
}

/** Vote for or against one option. A vote only counts once per person. */
function rmt_tg_vote(array $trip, int $optionId, string $voter, bool $yes): bool {
    if ($voter === '') return false;
    if (!q_one('SELECT 1 x FROM trip_date_options WHERE id = ? AND trip_id = ?', [$optionId, (int) $trip['id']])) return false;
    q_run('DELETE FROM trip_date_votes WHERE option_id = ? AND voter = ?', [$optionId, $voter]);
    if ($yes) q_run('INSERT INTO trip_date_votes (option_id, voter, created_at) VALUES (?,?,?)', [$optionId, $voter, date('Y-m-d H:i:s')]);
    return true;
}

/** Owner settles the dates: the trip takes them and the vote closes. */
function rmt_tg_pick(array $trip, int $optionId): bool {
    $o = q_one('SELECT * FROM trip_date_options WHERE id = ? AND trip_id = ?', [$optionId, (int) $trip['id']]);
    if (!$o) return false;
    q_run('UPDATE trips SET date_from = ?, date_to = ? WHERE id = ?', [$o['date_from'], $o['date_to'], (int) $trip['id']]);
    q_run('DELETE FROM trip_date_options WHERE trip_id = ?', [(int) $trip['id']]);
    return true;
}

/* ---------- the group's tourist tax ---------- */

/**
 * The tourist tax for the whole group, from the same data as the calculator. A flat per person or
 * per room rate gives a total; a percentage of the room price cannot be totalled without the room
 * price, so it is stated as the rate. Null when the city has no sourced tax.
 */
function rmt_tg_tax(array $trip, int $people): ?array {
    $key = !empty($trip['dest_slug']) ? rmt_tt_key_for_dest((string) $trip['dest_slug']) : null;
    if ($key === null) return null;
    $c = rmt_tt_cities()[$key];
    $people = max(1, $people);
    $nights = 0;
    if (!empty($trip['date_from']) && !empty($trip['date_to'])) {
        $nights = (int) round((strtotime((string) $trip['date_to']) - strtotime((string) $trip['date_from'])) / 86400);
    }
    $out = ['city' => (string) $c['name'], 'rate' => (string) $c['rate'], 'people' => $people, 'nights' => $nights,
            'total' => null, 'per_person' => null, 'calc' => url('tourist-tax-calculator?city=' . $key)];
    $tiers = array_values($c['tiers']);
    $tier = $tiers[0] ?? [];
    $flat = count($tiers) === 1 && !isset($tier['pct']) && !isset($tier['bands']);
    if ($flat && $nights > 0) {
        $rooms = (int) ceil($people / 2);
        $e = rmt_tt_estimate($c, $tier, $nights, $people, $rooms, 0.0);
        $out['total'] = rmt_tt_money((float) $e['total'], (string) $c['cur']);
        $out['per_person'] = rmt_tt_money(round((float) $e['total'] / $people, 2), (string) $c['cur']);
        $out['rooms'] = $rooms;
    }
    return $out;
}

/* ---------- controllers ---------- */

function rmt_tg_load(array $a): array {
    $t = rmt_tg_trip((string) ($a['code'] ?? ''));
    if (!$t) not_found();
    $me = current_user();
    $guest = rmt_tg_guest((int) $t['id']);
    return [$t, $me, $guest, rmt_tg_viewer($t, $me, $guest)];
}

function rmt_tg_back(array $t, string $hash = ''): void {
    redirect('/trip/join/' . (string) $t['group_code'] . $hash);
}

/** GET /trip/join/{code} */
function trip_group_show(array $a): void {
    [$t, $me, $guest, $viewer] = rmt_tg_load($a);
    $people = rmt_tg_people($t);
    view('trip_group', [
        't' => $t, 'me' => $me, 'guest' => $guest, 'viewer' => $viewer, 'people' => $people,
        'items' => !empty($t['dest_slug']) ? rmt_trip_checklist_items((string) $t['dest_slug']) : [],
        'done' => rmt_trip_checklist_done((int) $t['id']), 'claims' => rmt_tg_claims((int) $t['id']),
        'options' => rmt_tg_options((int) $t['id'], $viewer['voter']), 'tax' => rmt_tg_tax($t, count($people)),
        'link' => rmt_tg_url((string) $t['group_code']),
    ], ['title' => (string) $t['title'] . ' | Group trip | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}

/** POST /trip/join/{code}  join with a first name */
function trip_group_join(array $a): void {
    csrf_check();
    [$t, $me, $guest, $viewer] = rmt_tg_load($a);
    if ($viewer['kind'] !== null) rmt_tg_back($t);
    if (trim((string) input('website')) !== '') rmt_tg_back($t);
    if (!rmt_rate_ok('tg_join', rmt_client_ip(), 10, 3600)) { flash('Lots of joins from here just now. Try again in a while.'); rmt_tg_back($t); }
    $r = rmt_tg_join($t, (string) input('name'));
    if (!$r['ok']) { flash($r['error']); rmt_tg_back($t, '#join'); }
    setcookie(rmt_tg_cookie_name((int) $t['id']), $r['key'], [
        'expires' => time() + 400 * 86400, 'path' => '/trip/join/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => (string) cfg('app_env') === 'production',
    ]);
    rmt_track('group_guest_joined', ['source' => 'trip', 'destination_id' => (int) $t['destination_id'] ?: null]);
    flash('You are in. Tick what you sort out, and vote on the dates.');
    rmt_tg_back($t);
}

/** POST /trip/join/{code}/item  tick, untick, claim, unclaim */
function trip_group_item(array $a): void {
    csrf_check();
    [$t, $me, $guest, $viewer] = rmt_tg_load($a);
    $act = (string) input('act');
    if (rmt_tg_checklist_act($t, (string) input('item'), $act, $viewer, $me && $viewer['kind'] !== 'guest' ? (int) $me['id'] : null) && $act === 'done') {
        rmt_track('checklist_ticked', ['source' => 'trip', 'destination_id' => (int) $t['destination_id'] ?: null]);
    }
    rmt_tg_back($t, '#checklist');
}

/** POST /trip/join/{code}/vote */
function trip_group_vote(array $a): void {
    csrf_check();
    [$t, $me, $guest, $viewer] = rmt_tg_load($a);
    rmt_tg_vote($t, (int) input('option'), $viewer['voter'], input('yes') === '1');
    rmt_tg_back($t, '#dates');
}

/** POST /trip/join/{code}/dates  owner: add an option, or pick one */
function trip_group_dates(array $a): void {
    csrf_check();
    [$t, $me, $guest, $viewer] = rmt_tg_load($a);
    if ($viewer['kind'] !== 'owner') rmt_tg_back($t);
    if (input('pick') !== null && input('pick') !== '') {
        if (rmt_tg_pick($t, (int) input('pick'))) flash('Dates set. The trip now shows them.');
    } else {
        $err = rmt_tg_add_option($t, (string) input('date_from'), (string) input('date_to'));
        if ($err !== '') flash($err);
    }
    rmt_tg_back($t, '#dates');
}

/** POST /trip/join/{code}/save  a signed in guest keeps the trip on their account */
function trip_group_save(array $a): void {
    csrf_check();
    [$t, $me, $guest, $viewer] = rmt_tg_load($a);
    if (!$me) redirect('/register?return=' . rawurlencode('/trip/join/' . (string) $t['group_code']));
    if ($viewer['kind'] === 'guest' || $viewer['kind'] === null) {
        if (!q_one('SELECT 1 x FROM trip_members WHERE trip_id = ? AND user_id = ?', [(int) $t['id'], (int) $me['id']])) {
            q_run("INSERT INTO trip_members (trip_id, user_id, role, state, invited_by, created_at, decided_at) VALUES (?,?,?,?,?,?,?)",
                  [(int) $t['id'], (int) $me['id'], 'editor', 'active', (int) $t['user_id'], date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        } else {
            q_run("UPDATE trip_members SET state = 'active', decided_at = ? WHERE trip_id = ? AND user_id = ?", [date('Y-m-d H:i:s'), (int) $t['id'], (int) $me['id']]);
        }
        if ($guest) q_run('UPDATE trip_guests SET user_id = ? WHERE id = ?', [(int) $me['id'], (int) $guest['id']]);
        rmt_track('group_saved', ['source' => 'trip', 'destination_id' => (int) $t['destination_id'] ?: null]);
        flash('Saved to your account. You will find it under your trips.');
    }
    rmt_tg_back($t);
}

/** POST /trip/{id}/group-link  owner: make the link, or reset it */
function trip_group_link(array $a): void {
    csrf_check();
    require_login();
    $me = current_user();
    $t = q_one('SELECT * FROM trips WHERE id = ?', [(int) $a['id']]);
    if (!$t || !rmt_trip_can_admin($t, $me)) not_found();
    $reset = input('reset') === '1';
    rmt_tg_code($t, $reset);
    if (!$reset) rmt_track('group_link_created', ['source' => 'trip', 'destination_id' => (int) $t['destination_id'] ?: null]);
    flash($reset ? 'New link made. The old one no longer works; everybody already in stays in.' : 'Your group link is ready. Send it to the group chat.');
    redirect('/trip/' . (int) $t['id'] . '#group');
}
