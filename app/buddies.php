<?php
declare(strict_types=1);

/**
 * Travel buddies: who else is going where I am going.
 *
 * This is the one search over every way a member can say "I will be somewhere and I would like
 * company", so a reader asks the question once instead of learning which table it lives in:
 *
 *   post   a buddy post (buddy_posts): a cruise, a road trip, a month in Thailand, with who they
 *          are hoping to go with. Answered with buddy_interest, decided by the poster.
 *   trip   a dated trip (trips) its owner did not say no to meeting on. Answered with the existing
 *          trip connect (app/connects.php), exactly as /matches does.
 *   local  somebody who lives in a city and switched on "open to meeting travelers"
 *          (profiles.open_to_meeting). Answered with local_connects.
 *
 * Nothing new is published about anybody. A card is built only from what its owner already made
 * public: a city or a ship, a date range, a trip type, their own interests and languages. Never a
 * hotel, a cabin, an address, a live position or a contact detail, because none of those are
 * stored. Messaging stays downstream of an accepted request in all three cases.
 */

const RMT_BUDDY_TYPES = [
    'trip'        => 'City trip',
    'cruise'      => 'Cruise',
    'backpacking' => 'Backpacking',
    'road_trip'   => 'Road trip',
    'resort'      => 'Resort or vacation',
    'adventure'   => 'Adventure or hiking',
    'festival'    => 'Festival or event',
    'business'    => 'Business travel',
];
/** Who is travelling. The four profile travel styles, plus a group. */
const RMT_BUDDY_PARTIES = [
    'solo'    => 'Travelling solo',
    'couple'  => 'As a couple',
    'friends' => 'With friends',
    'family'  => 'With family',
    'group'   => 'Group trip',
];
const RMT_BUDDY_BUDGETS = ['any' => 'Any budget', 'budget' => 'Budget', 'mid' => 'Mid range', 'luxury' => 'Luxury'];
const RMT_BUDDY_NOTIFY_TYPES = ['buddy_interest', 'buddy_accepted', 'buddy_match', 'buddy_sailing', 'buddy_city', 'local_connect', 'local_accepted'];
/** What a post can be. Completed and removed posts are the owner's history, never listed. */
const RMT_BUDDY_VISIBLE_STATUSES = ['open', 'closed', 'completed'];
/** How far "flexible dates" stretches a range, each side. */
const RMT_BUDDY_FLEX_DAYS = 7;
const RMT_BUDDY_MATCH_NOTIFY_MAX = 40;

/* ---------- small helpers ---------- */

/** A ship name as a key two people typing it slightly differently still share. */
function rmt_buddy_ship_key(?string $ship): ?string {
    $k = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $ship));
    $k = preg_replace('/^(the|ms|mv|ss)(?=[a-z])/', '', (string) $k);
    return $k !== '' ? $k : null;
}

/** @return list<string> interest keys we still publish */
function rmt_buddy_interest_keys(?string $csv): array {
    $out = [];
    foreach (explode(',', (string) $csv) as $k) {
        $k = trim($k);
        if ($k !== '' && defined('RMT_INTERESTS') && isset(RMT_INTERESTS[$k])) $out[] = $k;
    }
    return array_values(array_unique($out));
}

function rmt_buddy_nights(string $from, string $to): int {
    $a = strtotime($from . ' 00:00:00 UTC'); $b = strtotime($to . ' 00:00:00 UTC');
    return ($a && $b && $b >= $a) ? (int) round(($b - $a) / 86400) : 0;
}

function rmt_buddy_date_shift(string $ymd, int $days): string {
    return gmdate('Y-m-d', (int) strtotime($ymd . ' 00:00:00 UTC') + $days * 86400);
}

/** The first word of a display name, which is as much of a name as a card needs. */
function rmt_buddy_first_name(?string $display): string {
    $d = trim((string) $display);
    return $d === '' ? '' : (string) preg_split('/\s+/', $d)[0];
}

/* ---------- posts: validate, read, answer ---------- */

/** @return array{ok:bool, errors:string[], data:array} */
function rmt_buddy_validate(array $in, ?string $today = null): array {
    $today = $today ?? date('Y-m-d');
    $e = [];
    $type = (string) ($in['trip_type'] ?? '');
    if (!isset(RMT_BUDDY_TYPES[$type])) $e[] = 'Pick what kind of trip it is.';
    $title = trim((string) ($in['title'] ?? ''));
    /* A blank title is filled in below from the place and dates. Inventing a headline is the one
       field on this form that asks for writing rather than facts, and it stopped people. */
    if ($title !== '' && (mb_strlen($title) < 8 || mb_strlen($title) > 140)) $e[] = 'The title needs 8 to 140 characters, or leave it blank.';
    $where = trim((string) ($in['where_text'] ?? ''));
    $destId = (int) ($in['destination_id'] ?? 0);
    if ($destId > 0 && !q_one('SELECT id FROM destinations WHERE id=?', [$destId])) $destId = 0;

    $cruise = [];
    foreach (['cruise_line' => 80, 'ship' => 80, 'departure_port' => 80, 'itinerary' => 200] as $k => $max) {
        $cruise[$k] = mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);
    }
    if ($type === 'cruise') {
        if ($cruise['cruise_line'] === '' && $cruise['ship'] === '') $e[] = 'Add the cruise line or the ship, so people on the same sailing can find you.';
        if ($where === '') $where = trim(implode(', ', array_filter([$cruise['ship'] ?: $cruise['cruise_line'], $cruise['departure_port'] ? 'from ' . $cruise['departure_port'] : ''])));
    } else {
        $cruise = ['cruise_line' => '', 'ship' => '', 'departure_port' => '', 'itinerary' => ''];
        if ($where === '' && $destId > 0) $where = (string) (q_one('SELECT name FROM destinations WHERE id=?', [$destId])['name'] ?? '');
    }
    if (mb_strlen($where) < 2 || mb_strlen($where) > 140) $e[] = 'Say where you are going, or which ship and route.';

    $from = (string) ($in['date_from'] ?? '');
    $to   = (string) ($in['date_to'] ?? '');
    $isDate = static fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    if (!$isDate($from) || !$isDate($to)) {
        $e[] = 'Both dates are needed. Tick "dates are flexible" if they could move.';
    } else {
        if ($to < $today) $e[] = 'The trip has to end today or later.';
        if ($to < $from) $e[] = 'The trip cannot end before it starts.';
        if ($from > rmt_buddy_date_shift($today, 730)) $e[] = 'Post trips starting within the next two years.';
        if (rmt_buddy_nights($from, $to) > 400) $e[] = 'That range is longer than a year.';
    }
    $spots = (int) ($in['spots'] ?? 1);
    if ($spots < 1 || $spots > 20) $e[] = 'Looking for between 1 and 20 people.';
    $budget = (string) ($in['budget'] ?? 'any');
    if (!isset(RMT_BUDDY_BUDGETS[$budget])) $budget = 'any';
    $party = (string) ($in['travel_party'] ?? '');
    if (!isset(RMT_BUDDY_PARTIES[$party])) $party = '';
    $ints = $in['interests'] ?? [];
    $ints = rmt_buddy_interest_keys(implode(',', is_array($ints) ? array_map('strval', $ints) : [(string) $ints]));
    $ageMin = (int) ($in['age_min'] ?? 0); $ageMax = (int) ($in['age_max'] ?? 0);
    if ($ageMin && ($ageMin < 18 || $ageMin > 99)) $e[] = 'Ages start at 18.';
    if ($ageMax && ($ageMax < 18 || $ageMax > 99)) $e[] = 'Ages start at 18.';
    if ($ageMin && $ageMax && $ageMax < $ageMin) $e[] = 'The age range is the wrong way round.';
    $desc = trim((string) ($in['description'] ?? ''));
    if (mb_strlen($desc) < 20 || mb_strlen($desc) > 4000) $e[] = 'Describe the trip and who you are hoping to go with (20 to 4000 characters).';
    if (empty($in['safety_ack'])) $e[] = 'Please confirm the safety terms.';
    if ($title === '' && !$e) {
        $span = date('M j', strtotime($from)) . ($to !== $from ? ' to ' . date(date('M', strtotime($from)) === date('M', strtotime($to)) ? 'j' : 'M j', strtotime($to)) : '');
        $title = mb_substr(($type === 'cruise' ? ($cruise['ship'] ?: $cruise['cruise_line']) . ' sailing' : $where) . ', ' . $span, 0, 140);
    }
    return ['ok' => !$e, 'errors' => $e, 'data' => [
        'trip_type' => $type, 'title' => $title, 'where_text' => $where, 'destination_id' => $destId ?: null,
        'date_from' => $from, 'date_to' => $to, 'flexible' => empty($in['flexible']) ? 0 : 1,
        'spots' => $spots, 'budget' => $budget, 'description' => $desc,
        'travel_party' => $party ?: null, 'interests' => $ints ? implode(',', $ints) : null,
        'age_min' => $ageMin ?: null, 'age_max' => $ageMax ?: null,
        'cruise_line' => $cruise['cruise_line'] ?: null, 'ship' => $cruise['ship'] ?: null,
        'departure_port' => $cruise['departure_port'] ?: null, 'itinerary' => $cruise['itinerary'] ?: null,
        'ship_key' => rmt_buddy_ship_key($cruise['ship']),
    ]];
}

const RMT_BUDDY_WRITE_COLS = ['trip_type', 'title', 'where_text', 'destination_id', 'date_from', 'date_to', 'flexible',
    'spots', 'budget', 'description', 'travel_party', 'interests', 'age_min', 'age_max', 'cruise_line', 'ship',
    'departure_port', 'itinerary', 'ship_key'];

function rmt_buddy_insert(int $userId, array $d): int {
    $cols = RMT_BUDDY_WRITE_COLS;
    $vals = array_map(static fn($c) => $d[$c] ?? null, $cols);
    $id = (int) q_run('INSERT INTO buddy_posts (user_id,' . implode(',', $cols) . ",status,created_at)
                        VALUES (?," . implode(',', array_fill(0, count($cols), '?')) . ", 'open', ?)",
                       array_merge([$userId], $vals, [date('Y-m-d H:i:s')]));
    // Announced like trips and reviews: an open post is in the sitemap, and the index should hear.
    if ($id > 0 && function_exists('rmt_seo_announce')) rmt_seo_announce('/buddy/' . $id);
    return $id;
}

function rmt_buddy_update(int $postId, array $d): void {
    $cols = RMT_BUDDY_WRITE_COLS;
    q_run('UPDATE buddy_posts SET ' . implode('=?,', $cols) . '=?, updated_at=? WHERE id=?',
          array_merge(array_map(static fn($c) => $d[$c] ?? null, $cols), [date('Y-m-d H:i:s'), $postId]));
}

function rmt_buddy_get(int $id): ?array {
    return q_one("SELECT b.*, d.name dest_name, d.slug dest_slug, d.country dest_country FROM buddy_posts b
                  LEFT JOIN destinations d ON d.id=b.destination_id WHERE b.id=?", [$id]);
}

/** Accepted on a buddy post or a local request, either direction. Used by rmt_message_allowed(). */
function rmt_buddy_mutual(int $a, int $b): bool {
    if ($a < 1 || $b < 1) return false;
    if (q_one("SELECT 1 FROM buddy_interest i JOIN buddy_posts p ON p.id=i.post_id
                WHERE i.state='accepted'
                  AND ((p.user_id=? AND i.user_id=?) OR (p.user_id=? AND i.user_id=?)) LIMIT 1", [$a, $b, $b, $a])) return true;
    return (bool) q_one("SELECT 1 FROM local_connects WHERE state='accepted'
                          AND ((from_user_id=? AND to_user_id=?) OR (from_user_id=? AND to_user_id=?)) LIMIT 1", [$a, $b, $b, $a]);
}

function rmt_buddy_notify(int $userId, string $type, int $actorId, int $targetId, string $targetType = 'buddy'): bool {
    if ($userId <= 0 || $userId === $actorId || !in_array($type, RMT_BUDDY_NOTIFY_TYPES, true)) return false;
    q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
          [$userId, $type, $actorId, $targetType, $targetId, date('Y-m-d H:i:s')]);
    return true;
}

/** @return array{ok:bool, action?:string, error?:string} */
function rmt_buddy_toggle_interest(array $post, int $userId, string $note = ''): array {
    $pid = (int) $post['id'];
    if ($userId === (int) $post['user_id']) return ['ok' => false, 'error' => 'This is your own post.'];
    $has = q_one('SELECT state FROM buddy_interest WHERE post_id=? AND user_id=?', [$pid, $userId]);
    if ($has) {
        q_run('DELETE FROM buddy_interest WHERE post_id=? AND user_id=?', [$pid, $userId]);
        return ['ok' => true, 'action' => 'withdrawn'];
    }
    if ($post['status'] !== 'open' || (string) $post['date_to'] < date('Y-m-d')) {
        return ['ok' => false, 'error' => 'That post is no longer looking for buddies.'];
    }
    if (function_exists('rmt_is_blocked') && rmt_is_blocked($userId, (int) $post['user_id'])) {
        return ['ok' => false, 'error' => 'You cannot respond to that post.'];
    }
    try {
        q_run("INSERT INTO buddy_interest (post_id,user_id,note,state,created_at) VALUES (?,?,?, 'interested', ?)",
              [$pid, $userId, mb_substr(trim($note), 0, 500), date('Y-m-d H:i:s')]);
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        return ['ok' => true, 'action' => 'interested'];
    }
    rmt_buddy_notify((int) $post['user_id'], 'buddy_interest', $userId, $pid);
    return ['ok' => true, 'action' => 'interested'];
}

/** The poster answers one person. Only the poster, only an existing hand. */
function rmt_buddy_decide(array $post, int $ownerId, int $userId, string $answer): bool {
    if ($ownerId !== (int) $post['user_id'] || !in_array($answer, ['accepted', 'declined'], true)) return false;
    $row = q_one('SELECT state FROM buddy_interest WHERE post_id=? AND user_id=?', [(int) $post['id'], $userId]);
    if (!$row || $row['state'] === $answer) return false;
    q_run('UPDATE buddy_interest SET state=?, decided_at=? WHERE post_id=? AND user_id=?',
          [$answer, date('Y-m-d H:i:s'), (int) $post['id'], $userId]);
    if ($answer === 'accepted') rmt_buddy_notify($userId, 'buddy_accepted', $ownerId, (int) $post['id']);
    return true;
}

/* ---------- locals ---------- */

/** @return array{ok:bool, state?:string, created?:bool, error?:string} */
function rmt_local_connect_request(int $fromId, int $toId): array {
    if ($fromId === $toId) return ['ok' => false, 'error' => 'That is you.'];
    $p = q_one("SELECT p.open_to_meeting FROM profiles p JOIN users u ON u.id=p.user_id AND u.status='active' WHERE p.user_id=?", [$toId]);
    if (!$p || (int) $p['open_to_meeting'] !== 1) return ['ok' => false, 'error' => 'That member is not open to meeting travelers right now.'];
    if (function_exists('rmt_is_blocked') && rmt_is_blocked($fromId, $toId)) return ['ok' => false, 'error' => 'That member is not available.'];
    $has = q_one('SELECT state FROM local_connects WHERE from_user_id=? AND to_user_id=?', [$fromId, $toId]);
    if ($has) return ['ok' => true, 'state' => (string) $has['state'], 'created' => false];
    try {
        q_run("INSERT INTO local_connects (from_user_id,to_user_id,state,created_at) VALUES (?,?, 'interested', ?)",
              [$fromId, $toId, date('Y-m-d H:i:s')]);
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        return ['ok' => true, 'state' => 'interested', 'created' => false];
    }
    rmt_buddy_notify($toId, 'local_connect', $fromId, $fromId, 'user');
    return ['ok' => true, 'state' => 'interested', 'created' => true];
}

function rmt_local_connect_decide(int $localId, int $fromId, string $answer): bool {
    if (!in_array($answer, ['accepted', 'declined'], true)) return false;
    $row = q_one('SELECT state FROM local_connects WHERE from_user_id=? AND to_user_id=?', [$fromId, $localId]);
    if (!$row || $row['state'] !== 'interested') return false;
    q_run('UPDATE local_connects SET state=?, decided_at=? WHERE from_user_id=? AND to_user_id=?',
          [$answer, date('Y-m-d H:i:s'), $fromId, $localId]);
    if ($answer === 'accepted') rmt_buddy_notify($fromId, 'local_accepted', $localId, $localId, 'user');
    return true;
}

function rmt_local_connect_withdraw(int $fromId, int $toId): void {
    q_run("DELETE FROM local_connects WHERE from_user_id=? AND to_user_id=? AND state='interested'", [$fromId, $toId]);
}

/* ---------- search ---------- */

/**
 * Read the search form. Everything is optional; an empty form is "everyone going anywhere soon".
 * $where is one box: a city we know, a country we know, or free text (a ship, a region, a port).
 */
function rmt_buddy_filters(array $g): array {
    $s = static fn(string $k, int $max = 80): string => mb_substr(trim((string) ($g[$k] ?? '')), 0, $max);
    $date = static fn(string $k): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($g[$k] ?? '')) ? (string) $g[$k] : '';
    $f = [
        'where' => $s('where'), 'dest_id' => 0, 'dest' => null, 'country' => '', 'text' => '',
        'from' => $date('from'), 'to' => $date('to'),
        'type' => isset(RMT_BUDDY_TYPES[$g['type'] ?? '']) ? (string) $g['type'] : '',
        'party' => isset(RMT_BUDDY_PARTIES[$g['party'] ?? '']) ? (string) $g['party'] : '',
        'interest' => (defined('RMT_INTERESTS') && isset(RMT_INTERESTS[$g['interest'] ?? ''])) ? (string) $g['interest'] : '',
        /* Age is never searched on other people's birthdates: that would let a stranger narrow
           members down by how old they are. The only age question is the poster's own stated
           preference, asked of the reader's own age, which the reader never sees for anybody else. */
        'myage' => !empty($g['myage']),
        'show' => in_array($g['show'] ?? '', ['going', 'here', 'locals'], true) ? (string) $g['show'] : 'all',
        'flexible' => !empty($g['flexible']),
        /* What the other person is after: an actual travel companion (a buddy post is exactly that
           request) or to meet up casually (a trip open to meeting, or a local). */
        'want' => in_array($g['want'] ?? '', ['companion', 'meet'], true) ? (string) $g['want'] : '',
        'line' => $s('line'), 'ship' => $s('ship'), 'port' => $s('port'),
    ];
    if ($f['from'] !== '' && $f['to'] === '') $f['to'] = $f['from'];
    if ($f['to'] !== '' && $f['from'] === '') $f['from'] = $f['to'];
    if ($f['from'] !== '' && $f['to'] < $f['from']) [$f['from'], $f['to']] = [$f['to'], $f['from']];
    if (!empty($g['dest'])) {
        $d = q_one('SELECT id, slug, name, country FROM destinations WHERE slug=?', [(string) $g['dest']]);
        if ($d) { $f['dest'] = $d; $f['dest_id'] = (int) $d['id']; $f['where'] = $f['where'] ?: (string) $d['name']; }
    }
    if ($f['where'] !== '' && !$f['dest_id']) {
        $w = mb_strtolower($f['where']);
        $city = preg_replace('/\s*,.*$/', '', $w);
        $d = q_one('SELECT id, slug, name, country FROM destinations WHERE LOWER(name)=? OR LOWER(slug)=? ORDER BY id LIMIT 1', [$city, $w]);
        if ($d) { $f['dest'] = $d; $f['dest_id'] = (int) $d['id']; }
        elseif ($c = q_one('SELECT country FROM destinations WHERE LOWER(country)=? LIMIT 1', [$w])) $f['country'] = (string) $c['country'];
        else $f['text'] = $w;
    }
    return $f;
}

/** Did the reader narrow the search by anything at all. */
function rmt_buddy_filters_active(array $f): bool {
    foreach (['where', 'from', 'type', 'party', 'interest', 'line', 'ship', 'port', 'want'] as $k) if (($f[$k] ?? '') !== '') return true;
    return $f['show'] !== 'all' || $f['flexible'] || $f['myage'];
}

/**
 * What "Post your trip" carries from a search.
 *
 * A city that matched a destination goes across as that city. A country or a
 * phrase that did not ("Iceland", "Ring Road") has to go across as where, or
 * the signup page and the form both open blank and the search is thrown away.
 *
 * @param array<string,mixed> $f
 * @return array<string,string>
 */
function rmt_buddy_post_query(array $f): array {
    $q = [
        'type' => (string) ($f['type'] ?? ''),
        'dest' => (string) ($f['dest']['slug'] ?? ''),
        'from' => (string) ($f['from'] ?? ''),
        'to'   => (string) ($f['to'] ?? ''),
        'ship' => (string) ($f['ship'] ?? ''),
        'line' => (string) ($f['line'] ?? ''),
        'port' => (string) ($f['port'] ?? ''),
    ];
    if ($q['dest'] === '' && (string) ($f['where'] ?? '') !== '') $q['where'] = (string) $f['where'];
    return array_filter($q, static fn($v) => $v !== '');
}

/** The same form as a query string, for links and "widen the search". */
function rmt_buddy_query(array $f, array $override = []): string {
    $q = ['where' => $f['where'], 'from' => $f['from'], 'to' => $f['to'], 'type' => $f['type'], 'party' => $f['party'],
          'interest' => $f['interest'], 'myage' => $f['myage'] ? '1' : '', 'show' => $f['show'] === 'all' ? '' : $f['show'],
          'flexible' => $f['flexible'] ? '1' : '', 'line' => $f['line'], 'ship' => $f['ship'], 'port' => $f['port']];
    $q = array_filter(array_merge($q, $override), static fn($v) => $v !== '' && $v !== null);
    return $q ? '?' . http_build_query($q) : '';
}

/**
 * Run a search.
 *
 * @return array{cards:list<array>, widened:bool, counts:array<string,int>}
 */
function rmt_buddy_search(array $f, ?array $viewer, int $limit = 60): array {
    $cards = rmt_buddy_search_run($f, $viewer, $limit);
    $widened = false;
    $travelers = array_filter($cards, static fn($c) => $c['kind'] !== 'local');
    // Nobody on those exact days. The people going to the same place at another time are the next
    // best answer, and a page that says so beats an empty one.
    if (!$travelers && $f['from'] !== '' && $f['show'] !== 'locals' && ($f['dest_id'] || $f['country'] !== '' || $f['text'] !== '' || $f['ship'] !== '' || $f['line'] !== '')) {
        $wider = rmt_buddy_search_run(array_merge($f, ['from' => '', 'to' => '']), $viewer, $limit);
        if (array_filter($wider, static fn($c) => $c['kind'] !== 'local')) { $cards = $wider; $widened = true; }
    }
    if (($f['want'] ?? '') !== '') {
        $keep = $f['want'] === 'companion' ? ['post'] : ['trip', 'local'];
        $cards = array_values(array_filter($cards, static fn($c) => in_array($c['kind'], $keep, true)));
    }
    $counts = ['post' => 0, 'trip' => 0, 'local' => 0, 'here' => 0];
    foreach ($cards as $c) { $counts[$c['kind']]++; if (!empty($c['here_now'])) $counts['here']++; }
    return ['cards' => $cards, 'widened' => $widened, 'counts' => $counts];
}

function rmt_buddy_search_run(array $f, ?array $viewer, int $limit): array {
    $today = date('Y-m-d');
    $vid = $viewer ? (int) $viewer['id'] : 0;
    $hasDates = $f['from'] !== '';
    $slack = $f['flexible'] ? RMT_BUDDY_FLEX_DAYS : 0;
    $qFrom = $hasDates ? rmt_buddy_date_shift($f['from'], -$slack) : '';
    $qTo   = $hasDates ? rmt_buddy_date_shift($f['to'], $slack) : '';
    $myAge = null;
    if ($f['myage'] && $vid && function_exists('age_from')) {
        $bd = (string) (q_one('SELECT birthdate FROM users WHERE id = ?', [$vid])['birthdate'] ?? '');
        if ($bd !== '') $myAge = age_from($bd);
    }
    $cruiseOnly = $f['type'] === 'cruise' || $f['line'] !== '' || $f['ship'] !== '' || $f['port'] !== '';
    $like = static fn(string $v): string => '%' . str_replace(['%', '_'], '', mb_strtolower($v)) . '%';
    $blocks = static function (string $col) use ($vid): array {
        if (!$vid) return ['1=1', []];
        return ["NOT EXISTS (SELECT 1 FROM blocks bl WHERE (bl.blocker_id = ? AND bl.blocked_id = $col) OR (bl.blocker_id = $col AND bl.blocked_id = ?))", [$vid, $vid]];
    };
    $out = [];

    /* ---- buddy posts ---- */
    if ($f['show'] !== 'locals') {
        $w = ["b.status='open'", 'b.date_to >= ?']; $a = [$today];
        if ($vid) { $w[] = 'b.user_id <> ?'; $a[] = $vid; }
        if ($hasDates) {
            // A flexible post stretches too, so "around those dates" finds it.
            $w[] = '(b.date_from <= ? OR (b.flexible = 1 AND b.date_from <= ?))';
            $w[] = '(b.date_to >= ? OR (b.flexible = 1 AND b.date_to >= ?))';
            array_push($a, $qTo, rmt_buddy_date_shift($qTo, RMT_BUDDY_FLEX_DAYS), $qFrom, rmt_buddy_date_shift($qFrom, -RMT_BUDDY_FLEX_DAYS));
        }
        if ($f['show'] === 'here') { $w[] = 'b.date_from <= ?'; $a[] = $today; }
        if ($f['dest_id']) { $w[] = 'b.destination_id = ?'; $a[] = $f['dest_id']; }
        if ($f['country'] !== '') { $w[] = '(d.country = ? OR LOWER(b.where_text) LIKE ?)'; array_push($a, $f['country'], $like($f['country'])); }
        if ($f['text'] !== '') {
            $w[] = "(LOWER(b.where_text) LIKE ? OR LOWER(COALESCE(b.ship,'')) LIKE ? OR LOWER(COALESCE(b.cruise_line,'')) LIKE ?
                     OR LOWER(COALESCE(b.departure_port,'')) LIKE ? OR LOWER(COALESCE(b.itinerary,'')) LIKE ?
                     OR LOWER(COALESCE(d.name,'')) LIKE ? OR LOWER(COALESCE(d.country,'')) LIKE ? OR LOWER(b.title) LIKE ?)";
            array_push($a, ...array_fill(0, 8, $like($f['text'])));
        }
        if ($f['type'] !== '') { $w[] = 'b.trip_type = ?'; $a[] = $f['type']; }
        if ($f['party'] !== '') { $w[] = 'b.travel_party = ?'; $a[] = $f['party']; }
        if ($f['interest'] !== '') {
            $w[] = '(LOWER(COALESCE(b.interests,\'\')) LIKE ? OR EXISTS (SELECT 1 FROM profile_interests pi WHERE pi.user_id = b.user_id AND pi.interest = ?))';
            array_push($a, $like($f['interest']), $f['interest']);
        }
        if ($f['line'] !== '') { $w[] = "LOWER(COALESCE(b.cruise_line,'')) LIKE ?"; $a[] = $like($f['line']); }
        if ($f['ship'] !== '') {
            $w[] = "(b.ship_key = ? OR LOWER(COALESCE(b.ship,'')) LIKE ?)";
            array_push($a, (string) rmt_buddy_ship_key($f['ship']), $like($f['ship']));
        }
        if ($f['port'] !== '') { $w[] = "LOWER(COALESCE(b.departure_port,'')) LIKE ?"; $a[] = $like($f['port']); }
        if ($myAge !== null) { $w[] = '(b.age_min IS NULL OR b.age_min <= ?) AND (b.age_max IS NULL OR b.age_max >= ?)'; array_push($a, $myAge, $myAge); }
        [$bs, $ba] = $blocks('b.user_id'); $w[] = $bs; array_push($a, ...$ba);
        $rows = q_all("SELECT b.*, d.name dest_name, d.slug dest_slug, d.country dest_country,
                              u.username, u.email_verified_at, p.avatar_url, p.display_name, p.languages, p.travel_style profile_style,
                              (SELECT COUNT(*) FROM buddy_interest i WHERE i.post_id=b.id) interest_count
                         FROM buddy_posts b JOIN users u ON u.id=b.user_id AND u.status='active'
                    LEFT JOIN destinations d ON d.id=b.destination_id
                    LEFT JOIN profiles p ON p.user_id=b.user_id
                        WHERE " . implode(' AND ', $w) . ' ORDER BY b.date_from, b.id LIMIT ' . max(1, $limit), $a);
        foreach ($rows as $r) {
            $party = (string) ($r['travel_party'] ?: ($r['profile_style'] ?? ''));
            $out[] = rmt_buddy_card('post', $r, [
                'id' => (int) $r['id'], 'url' => url('buddy/' . (int) $r['id']),
                'type' => (string) $r['trip_type'], 'party' => $party, 'title' => (string) $r['title'],
                'summary' => (string) $r['description'], 'where' => (string) $r['where_text'],
                'from' => (string) $r['date_from'], 'to' => (string) $r['date_to'], 'flexible' => (int) $r['flexible'] === 1,
                'post_interests' => rmt_buddy_interest_keys($r['interests'] ?? ''),
                'spots' => (int) $r['spots'], 'interest_count' => (int) $r['interest_count'],
                'cruise_line' => (string) ($r['cruise_line'] ?? ''), 'ship' => (string) ($r['ship'] ?? ''),
                'ship_key' => (string) ($r['ship_key'] ?? ''), 'departure_port' => (string) ($r['departure_port'] ?? ''),
                'nights' => rmt_buddy_nights((string) $r['date_from'], (string) $r['date_to']),
                'itinerary' => (string) ($r['itinerary'] ?? ''),
                'age_min' => (int) ($r['age_min'] ?? 0), 'age_max' => (int) ($r['age_max'] ?? 0),
            ], $f);
        }
    }

    /* How many other travelers are on each cruise card's exact sailing, in one grouped read. */
    $keys = array_values(array_unique(array_filter(array_map(static fn($c) => $c['kind'] === 'post' ? $c['ship_key'] : '', $out))));
    if ($keys) {
        $n = [];
        foreach (q_all("SELECT ship_key, date_from, COUNT(*) c FROM buddy_posts WHERE status IN ('open','closed') AND ship_key IN ("
                       . implode(',', array_fill(0, count($keys), '?')) . ') GROUP BY ship_key, date_from', $keys) as $r) {
            $n[$r['ship_key'] . '|' . substr((string) $r['date_from'], 0, 10)] = (int) $r['c'];
        }
        foreach ($out as &$c) if ($c['ship_key'] !== '') $c['on_sailing'] = max(0, ($n[$c['ship_key'] . '|' . $c['from']] ?? 1) - 1);
        unset($c);
    }

    /* ---- dated trips ---- */
    if ($f['show'] !== 'locals' && !$cruiseOnly && $f['text'] === '' ) {
        [$vis, $visArgs] = function_exists('rmt_plan_visibility_sql') ? rmt_plan_visibility_sql('t', $viewer) : ["t.visibility = 'public'", []];
        $w = ["t.status = 'published'", 't.date_from IS NOT NULL', 't.date_to IS NOT NULL', 't.date_to >= ?',
              't.destination_id IS NOT NULL', 'COALESCE(t.open_to_meeting, 1) = 1', $vis];
        $a = array_merge([$today], $visArgs);
        if ($vid) { $w[] = 't.user_id <> ?'; $a[] = $vid; }
        if ($hasDates) { $w[] = 't.date_from <= ? AND t.date_to >= ?'; array_push($a, $qTo, $qFrom); }
        if ($f['show'] === 'here') { $w[] = 't.date_from <= ?'; $a[] = $today; }
        if ($f['dest_id']) { $w[] = 't.destination_id = ?'; $a[] = $f['dest_id']; }
        if ($f['country'] !== '') { $w[] = 'd.country = ?'; $a[] = $f['country']; }
        if ($f['type'] !== '') { $w[] = "COALESCE(t.trip_type, 'trip') = ?"; $a[] = $f['type']; }
        if ($f['party'] !== '') {
            $w[] = 'COALESCE(t.travel_style, p.travel_style) = ?';
            $a[] = $f['party'] === 'group' ? 'friends' : $f['party'];
        }
        if ($f['interest'] !== '') { $w[] = 'EXISTS (SELECT 1 FROM profile_interests pi WHERE pi.user_id = t.user_id AND pi.interest = ?)'; $a[] = $f['interest']; }
        [$bs, $ba] = $blocks('t.user_id'); $w[] = $bs; array_push($a, ...$ba);
        // The visibility clause is in here: $vis, from rmt_plan_visibility_sql(), is the first filter.
        $whereSql = implode(' AND ', $w);
        $rows = q_all("SELECT t.id, t.user_id, t.title, t.body, t.date_from, t.date_to, t.trip_type, t.slug,
                              COALESCE(t.travel_style, p.travel_style) style,
                              d.name dest_name, d.slug dest_slug, d.country dest_country,
                              u.username, u.email_verified_at, p.avatar_url, p.display_name, p.languages
                         FROM trips t JOIN users u ON u.id=t.user_id AND u.status='active'
                         JOIN destinations d ON d.id=t.destination_id
                    LEFT JOIN profiles p ON p.user_id=t.user_id
                        WHERE $whereSql ORDER BY t.date_from, t.id LIMIT " . max(1, $limit), $a);
        // One card per person per place: somebody with a buddy post for Tokyo does not also need a
        // second card for the trip to Tokyo it came from.
        $postKeys = [];
        foreach ($out as $c) if ($c['dest_slug'] !== '') $postKeys[$c['user_id'] . ':' . $c['dest_slug']] = true;
        foreach ($rows as $r) {
            if (isset($postKeys[$r['user_id'] . ':' . $r['dest_slug']])) continue;
            $postKeys[$r['user_id'] . ':' . $r['dest_slug']] = true;
            $body = trim(strip_tags((string) ($r['body'] ?? '')));
            $out[] = rmt_buddy_card('trip', $r, [
                'id' => (int) $r['id'], 'url' => url('trip/' . (int) $r['id'] . '/' . $r['slug']),
                'type' => (string) ($r['trip_type'] ?: 'trip'), 'party' => (string) ($r['style'] ?? ''),
                'title' => (string) $r['title'], 'summary' => $body, 'where' => (string) $r['dest_name'],
                'from' => (string) $r['date_from'], 'to' => (string) $r['date_to'], 'flexible' => false,
                'post_interests' => [],
            ], $f);
        }
    }

    /* ---- locals ---- */
    $place = $f['dest_id'] || $f['country'] !== '';
    if (($f['show'] === 'locals' || ($f['show'] === 'all' && $place)) && !$cruiseOnly && $f['type'] === '' && $f['party'] === '') {
        $w = ['COALESCE(p.open_to_meeting, 0) = 1', 'p.home_destination_id IS NOT NULL'];
        $a = [];
        if (defined('RMT_EDITORIAL_ROLE')) { $w[] = 'u.role <> ?'; $a[] = RMT_EDITORIAL_ROLE; }
        if ($vid) { $w[] = 'u.id <> ?'; $a[] = $vid; }
        if ($f['dest_id']) { $w[] = 'p.home_destination_id = ?'; $a[] = $f['dest_id']; }
        if ($f['country'] !== '') { $w[] = 'd.country = ?'; $a[] = $f['country']; }
        if ($f['text'] !== '') { $w[] = '(LOWER(d.name) LIKE ? OR LOWER(d.country) LIKE ?)'; array_push($a, $like($f['text']), $like($f['text'])); }
        if ($f['interest'] !== '') { $w[] = 'EXISTS (SELECT 1 FROM profile_interests pi WHERE pi.user_id = u.id AND pi.interest = ?)'; $a[] = $f['interest']; }
        [$bs, $ba] = $blocks('u.id'); $w[] = $bs; array_push($a, ...$ba);
        $rows = q_all("SELECT u.id user_id, u.username, u.email_verified_at, p.avatar_url, p.display_name, p.languages,
                              p.bio, p.travel_style, d.name dest_name, d.slug dest_slug, d.country dest_country
                         FROM profiles p JOIN users u ON u.id=p.user_id AND u.status='active'
                         JOIN destinations d ON d.id=p.home_destination_id
                        WHERE " . implode(' AND ', $w) . ' ORDER BY u.id DESC LIMIT ' . max(1, min(24, $limit)), $a);
        foreach ($rows as $r) {
            $out[] = rmt_buddy_card('local', $r, [
                'id' => (int) $r['user_id'], 'url' => url('u/' . $r['username']),
                'type' => '', 'party' => '', 'title' => 'Lives in ' . $r['dest_name'],
                'summary' => (string) ($r['bio'] ?? ''), 'where' => (string) $r['dest_name'],
                'from' => '', 'to' => '', 'flexible' => false, 'post_interests' => [],
            ], $f);
        }
    }

    /* ---- the reader's own state on each card, in three lookups rather than one per card ---- */
    if ($vid && $out) {
        $postIds = []; $tripIds = []; $localIds = [];
        foreach ($out as $c) {
            if ($c['kind'] === 'post') $postIds[] = $c['id'];
            elseif ($c['kind'] === 'trip') $tripIds[] = $c['id'];
            else $localIds[] = $c['id'];
        }
        $state = [];
        $in = static fn(array $ids): string => implode(',', array_fill(0, count($ids), '?'));
        if ($postIds) foreach (q_all('SELECT post_id, state FROM buddy_interest WHERE user_id=? AND post_id IN (' . $in($postIds) . ')', array_merge([$vid], $postIds)) as $r)
            $state['post:' . $r['post_id']] = ['state' => $r['state']];
        if ($tripIds) foreach (q_all('SELECT id, trip_id, state FROM trip_connects WHERE from_user_id=? AND trip_id IN (' . $in($tripIds) . ')', array_merge([$vid], $tripIds)) as $r)
            $state['trip:' . $r['trip_id']] = ['state' => $r['state'], 'connect_id' => (int) $r['id']];
        if ($localIds) foreach (q_all('SELECT to_user_id, state FROM local_connects WHERE from_user_id=? AND to_user_id IN (' . $in($localIds) . ')', array_merge([$vid], $localIds)) as $r)
            $state['local:' . $r['to_user_id']] = ['state' => $r['state']];
        $saved = [];
        if ($postIds || $tripIds) foreach (q_all("SELECT target_type, target_id FROM saves WHERE user_id=? AND target_type IN ('buddy','trip')", [$vid]) as $r)
            $saved[$r['target_type'] . ':' . $r['target_id']] = true;
        foreach ($out as &$c) {
            $c['viewer_state'] = $state[$c['kind'] . ':' . $c['id']] ?? null;
            $c['saved'] = isset($saved[($c['kind'] === 'post' ? 'buddy' : 'trip') . ':' . $c['id']]);
        }
        unset($c);
    }

    /* ---- order: best overlap first when the reader gave dates, soonest otherwise, locals last ---- */
    usort($out, static function (array $x, array $y) use ($hasDates): int {
        $rank = ['post' => 0, 'trip' => 0, 'local' => 1];
        if ($rank[$x['kind']] !== $rank[$y['kind']]) return $rank[$x['kind']] <=> $rank[$y['kind']];
        if ($x['rank'] !== $y['rank']) return $y['rank'] <=> $x['rank'];
        if ($hasDates && $x['overlap_days'] !== $y['overlap_days']) return $y['overlap_days'] <=> $x['overlap_days'];
        return strcmp($x['from'], $y['from']);
    });
    return array_slice($out, 0, $limit);
}

/** One card, from whichever row it came from. Only public, only what the member published. */
function rmt_buddy_card(string $kind, array $r, array $c, array $f): array {
    $today = date('Y-m-d');
    $uid = (int) $r['user_id'];
    $langs = function_exists('rmt_languages_for') ? rmt_language_labels(rmt_languages_for($r['languages'] ?? null)) : [];
    $overlap = 0;
    if ($f['from'] !== '' && $c['from'] !== '') $overlap = rmt_buddy_overlap_days($f['from'], $f['to'], $c['from'], $c['to']);
    return array_merge([
        'kind' => $kind, 'user_id' => $uid, 'username' => (string) $r['username'],
        'name' => rmt_buddy_first_name($r['display_name'] ?? ''), 'avatar_url' => $r['avatar_url'] ?? null,
        'verified' => !empty($r['email_verified_at']), 'languages' => array_slice($langs, 0, 3),
        'dest_name' => (string) ($r['dest_name'] ?? ''), 'dest_slug' => (string) ($r['dest_slug'] ?? ''),
        'country' => (string) ($r['dest_country'] ?? ''),
        'here_now' => $c['from'] !== '' && $c['from'] <= $today && $c['to'] >= $today,
        'overlap_days' => $overlap, 'viewer_state' => null, 'saved' => false,
        'spots' => 0, 'interest_count' => 0, 'cruise_line' => '', 'ship' => '', 'ship_key' => '', 'departure_port' => '', 'nights' => 0,
        'itinerary' => '', 'age_min' => 0, 'age_max' => 0, 'example' => false, 'on_sailing' => 0,
    ], $c, ['rank' => rmt_buddy_rank($c, $f)]);
}

/**
 * How strong a match is beyond date overlap. For a cruise search: the exact sailing (same ship,
 * same departure day) beats the same ship on another day, which beats the same line or port.
 */
function rmt_buddy_rank(array $c, array $f): int {
    if (($c['type'] ?? '') !== 'cruise') return 0;
    $rank = 0;
    $key = rmt_buddy_ship_key($f['ship'] ?? '');
    if ($key !== null && ($c['ship_key'] ?? '') === $key) {
        $rank = 2;
        if (($f['from'] ?? '') !== '' && ($c['from'] ?? '') === $f['from']) $rank = 3;
    } elseif ((($f['line'] ?? '') !== '' && stripos((string) ($c['cruise_line'] ?? ''), (string) $f['line']) !== false)
           || (($f['port'] ?? '') !== '' && stripos((string) ($c['departure_port'] ?? ''), (string) $f['port']) !== false)) {
        $rank = 1;
    }
    return $rank;
}

function rmt_buddy_overlap_days(string $aFrom, string $aTo, string $bFrom, string $bTo): int {
    if (function_exists('rmt_match_overlap_days')) return rmt_match_overlap_days($aFrom, $aTo, $bFrom, $bTo);
    $s = max($aFrom, $bFrom); $e = min($aTo, $bTo);
    return $s > $e ? 0 : rmt_buddy_nights($s, $e) + 1;
}

/** Interest keys for a card: the post's own, then the person's profile, deduplicated. */
function rmt_buddy_card_interests(array $cards): array {
    $byUser = function_exists('rmt_interests_for_many') ? rmt_interests_for_many(array_column($cards, 'user_id')) : [];
    foreach ($cards as &$c) $c['interests'] = array_slice(array_values(array_unique(array_merge($c['post_interests'], $byUser[$c['user_id']] ?? []))), 0, 4);
    unset($c);
    return $cards;
}

/* ---------- cruises ---------- */

/** Other posts on the same ship within a day of the same departure. */
function rmt_buddy_same_sailing(array $post, ?array $viewer = null, int $limit = 20): array {
    if (($post['trip_type'] ?? '') !== 'cruise' || empty($post['ship_key'])) return [];
    return q_all("SELECT b.id, b.title, b.date_from, b.date_to, b.user_id, u.username, p.avatar_url, p.display_name
                    FROM buddy_posts b JOIN users u ON u.id=b.user_id AND u.status='active'
               LEFT JOIN profiles p ON p.user_id=b.user_id
                   WHERE b.status IN ('open','closed','completed') AND b.id <> ? AND b.ship_key = ?
                     AND b.date_from >= ? AND b.date_from <= ?
                ORDER BY b.date_from LIMIT " . (int) $limit,
                 [(int) $post['id'], (string) $post['ship_key'],
                  rmt_buddy_date_shift((string) $post['date_from'], -1), rmt_buddy_date_shift((string) $post['date_from'], 1)]);
}

/** Same line or same port, sailing within three weeks, not the same sailing. */
function rmt_buddy_similar_sailings(array $post, int $limit = 8): array {
    if (($post['trip_type'] ?? '') !== 'cruise') return [];
    $line = mb_strtolower(trim((string) ($post['cruise_line'] ?? '')));
    $port = mb_strtolower(trim((string) ($post['departure_port'] ?? '')));
    if ($line === '' && $port === '') return [];
    return q_all("SELECT b.id, b.title, b.ship, b.cruise_line, b.departure_port, b.date_from, b.date_to, u.username
                    FROM buddy_posts b JOIN users u ON u.id=b.user_id AND u.status='active'
                   WHERE b.status='open' AND b.trip_type='cruise' AND b.id <> ? AND b.date_to >= ?
                     AND COALESCE(b.ship_key,'') <> ?
                     AND ((? <> '' AND LOWER(COALESCE(b.cruise_line,'')) = ?) OR (? <> '' AND LOWER(COALESCE(b.departure_port,'')) = ?))
                     AND b.date_from >= ? AND b.date_from <= ?
                ORDER BY b.date_from LIMIT " . (int) $limit,
                 [(int) $post['id'], date('Y-m-d'), (string) ($post['ship_key'] ?? ''), $line, $line, $port, $port,
                  rmt_buddy_date_shift((string) $post['date_from'], -21), rmt_buddy_date_shift((string) $post['date_from'], 21)]);
}

/** Cards grouped into sailings: one ship, one departure day. */
function rmt_buddy_group_sailings(array $cards): array {
    $g = [];
    foreach ($cards as $c) {
        if ($c['kind'] !== 'post' || $c['type'] !== 'cruise') continue;
        $k = ($c['ship_key'] ?: 'line:' . mb_strtolower($c['cruise_line'])) . '|' . $c['from'];
        $g[$k] ??= ['ship' => $c['ship'], 'cruise_line' => $c['cruise_line'], 'port' => $c['departure_port'], 'from' => $c['from'], 'nights' => $c['nights'], 'cards' => []];
        $g[$k]['cards'][] = $c;
    }
    uasort($g, static fn($a, $b) => [count($b['cards']), $a['from']] <=> [count($a['cards']), $b['from']]);
    return array_values($g);
}

/* ---------- match notifications ---------- */

/**
 * Tell the people a new post or trip lands on. One notification per recipient per target, only
 * active members, blocks respected, capped. Returns how many were written.
 *
 * Three kinds, most specific first, and a person gets only the most specific one:
 *   buddy_sailing  somebody joined the same ship on the same departure day
 *   buddy_match    somebody's city and dates overlap a trip or post of theirs
 *   buddy_city     somebody posted a trip to a city they saved
 *
 * $kind 'buddy' for a buddy post, 'trip' for a dated trip (whose trip owners and city watchers the
 * trip code already tells; this only adds the buddy posters it lands on).
 */
function rmt_buddy_notify_matches(string $kind, int $targetId): int {
    $recipients = [];   // uid => type, most specific wins
    $today = date('Y-m-d');
    $add = static function (array $rows, string $type) use (&$recipients): void {
        foreach ($rows as $r) { $uid = (int) $r['user_id']; if (!isset($recipients[$uid])) $recipients[$uid] = $type; }
    };
    if ($kind === 'buddy') {
        $p = rmt_buddy_get($targetId);
        if (!$p || $p['status'] !== 'open') return 0;
        $actor = (int) $p['user_id'];
        if ($p['trip_type'] === 'cruise') $add(rmt_buddy_same_sailing($p, null, RMT_BUDDY_MATCH_NOTIFY_MAX), 'buddy_sailing');
        if (!empty($p['destination_id'])) {
            $add(q_all("SELECT DISTINCT user_id FROM trips WHERE destination_id=? AND status='published' AND visibility='public'
                          AND date_from IS NOT NULL AND date_to IS NOT NULL AND date_from <= ? AND date_to >= ? AND date_to >= ?
                          AND COALESCE(open_to_meeting,1)=1",
                        [(int) $p['destination_id'], $p['date_to'], $p['date_from'], $today]), 'buddy_match');
            $add(q_all("SELECT DISTINCT user_id FROM buddy_posts WHERE destination_id=? AND status='open' AND id<>?
                          AND date_from <= ? AND date_to >= ? AND date_to >= ?",
                        [(int) $p['destination_id'], $targetId, $p['date_to'], $p['date_from'], $today]), 'buddy_match');
            $add(q_all("SELECT s.user_id FROM saves s WHERE s.target_type='destination' AND s.target_id=?", [(int) $p['destination_id']]), 'buddy_city');
        }
        $targetType = 'buddy';
    } else {
        $t = q_one("SELECT * FROM trips WHERE id=? AND status='published'", [$targetId]);
        if (!$t || ($t['visibility'] ?? 'public') !== 'public' || empty($t['destination_id']) || empty($t['date_from'])) return 0;
        if ($t['open_to_meeting'] !== null && (int) $t['open_to_meeting'] === 0) return 0;
        $actor = (int) $t['user_id'];
        $add(q_all("SELECT DISTINCT user_id FROM buddy_posts WHERE destination_id=? AND status='open'
                      AND date_from <= ? AND date_to >= ? AND date_to >= ?",
                    [(int) $t['destination_id'], $t['date_to'], $t['date_from'], $today]), 'buddy_match');
        $targetType = 'trip';
    }
    $mail = [
        'buddy_sailing' => ['Somebody joined your sailing', 'A traveler posted the same cruise and departure day as you on RuinMyTrip.', 'somebody joined your sailing'],
        'buddy_match'   => ['A traveler is heading your way', 'Somebody posted a trip that lines up with yours on RuinMyTrip.', 'a new trip lines up with yours'],
        'buddy_city'    => ['A trip to a city you saved', 'A traveler is looking for company in a city you saved on RuinMyTrip.', 'you saved that city'],
    ];
    $sent = 0;
    foreach (array_slice($recipients, 0, RMT_BUDDY_MATCH_NOTIFY_MAX, true) as $uid => $type) {
        if ($uid === $actor) continue;
        if (function_exists('rmt_is_blocked') && rmt_is_blocked($uid, $actor)) continue;
        if (q_one("SELECT 1 x FROM notifications WHERE user_id=? AND type IN ('buddy_match','buddy_sailing','buddy_city') AND target_type=? AND target_id=?",
                  [$uid, $targetType, $targetId])) continue;
        if (!q_one("SELECT 1 x FROM users WHERE id=? AND status='active'", [$uid])) continue;
        rmt_buddy_notify($uid, $type, $actor, $targetId, $targetType);
        // A saved city is a softer signal than a matching trip, so it stays in the app and never emails.
        if ($type !== 'buddy_city' && function_exists('rmt_notify_email_direct')) {
            rmt_notify_email_direct($uid, $mail[$type][0], $mail[$type][1], $targetType === 'buddy' ? '/buddy/' . $targetId : '/buddies/mine', $mail[$type][2]);
        }
        $sent++;
    }
    return $sent;
}

/* ---------- the member's own side ---------- */

/** Everything /buddies/mine shows, for one member. */
function rmt_buddy_dashboard(int $uid): array {
    $today = date('Y-m-d');
    $posts = q_all("SELECT b.*, d.name dest_name, d.slug dest_slug,
                           (SELECT COUNT(*) FROM buddy_interest i WHERE i.post_id=b.id AND i.state='interested') waiting,
                           (SELECT COUNT(*) FROM buddy_interest i WHERE i.post_id=b.id AND i.state='accepted') accepted
                      FROM buddy_posts b LEFT JOIN destinations d ON d.id=b.destination_id
                     WHERE b.user_id=? AND b.status IN ('open','closed') AND b.date_to >= ? ORDER BY b.date_from", [$uid, $today]);
    $trips = q_all("SELECT t.*, d.name dest_name, d.slug dest_slug FROM trips t LEFT JOIN destinations d ON d.id=t.destination_id
                     WHERE t.user_id=? AND t.status='published' AND t.date_from IS NOT NULL AND t.date_to >= ? ORDER BY t.date_from", [$uid, $today]);
    // Trips that are over, or that the member marked as done, newest first.
    $pastPosts = q_all("SELECT b.*, d.name dest_name, d.slug dest_slug FROM buddy_posts b LEFT JOIN destinations d ON d.id=b.destination_id
                         WHERE b.user_id=? AND (b.status='completed' OR (b.status IN ('open','closed') AND b.date_to < ?))
                      ORDER BY b.date_from DESC LIMIT 20", [$uid, $today]);
    $pastTrips = q_all("SELECT t.*, d.name dest_name, d.slug dest_slug FROM trips t LEFT JOIN destinations d ON d.id=t.destination_id
                         WHERE t.user_id=? AND t.status='published' AND t.date_from IS NOT NULL AND t.date_to < ?
                      ORDER BY t.date_from DESC LIMIT 20", [$uid, $today]);

    $received = array_merge(
        array_map(static fn($r) => $r + ['kind' => 'post'], q_all(
            "SELECT i.user_id from_id, i.note, i.state, i.created_at, b.id target_id, b.title target_title, u.username, p.avatar_url, p.display_name
               FROM buddy_interest i JOIN buddy_posts b ON b.id=i.post_id JOIN users u ON u.id=i.user_id AND u.status='active'
          LEFT JOIN profiles p ON p.user_id=i.user_id
              WHERE b.user_id=? AND i.state='interested' ORDER BY i.created_at DESC", [$uid])),
        array_map(static fn($r) => $r + ['kind' => 'trip', 'note' => ''], q_all(
            "SELECT c.id connect_id, c.from_user_id from_id, c.state, c.created_at, t.id target_id, t.title target_title, u.username, p.avatar_url, p.display_name
               FROM trip_connects c JOIN trips t ON t.id=c.trip_id JOIN users u ON u.id=c.from_user_id AND u.status='active'
          LEFT JOIN profiles p ON p.user_id=c.from_user_id
              WHERE c.to_user_id=? AND c.state='interested' ORDER BY c.id DESC", [$uid])),
        array_map(static fn($r) => $r + ['kind' => 'local', 'note' => '', 'target_id' => 0, 'target_title' => 'Meeting a local'], q_all(
            "SELECT l.from_user_id from_id, l.state, l.created_at, u.username, p.avatar_url, p.display_name
               FROM local_connects l JOIN users u ON u.id=l.from_user_id AND u.status='active'
          LEFT JOIN profiles p ON p.user_id=l.from_user_id
              WHERE l.to_user_id=? AND l.state='interested' ORDER BY l.created_at DESC", [$uid]))
    );
    usort($received, static fn($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

    $sent = array_merge(
        array_map(static fn($r) => $r + ['kind' => 'post'], q_all(
            "SELECT i.state, i.created_at, b.id target_id, b.title target_title, u.username
               FROM buddy_interest i JOIN buddy_posts b ON b.id=i.post_id JOIN users u ON u.id=b.user_id
              WHERE i.user_id=? AND b.date_to >= ? ORDER BY i.created_at DESC", [$uid, $today])),
        array_map(static fn($r) => $r + ['kind' => 'trip'], q_all(
            "SELECT c.id connect_id, c.state, c.created_at, t.id target_id, t.title target_title, t.slug, u.username
               FROM trip_connects c JOIN trips t ON t.id=c.trip_id JOIN users u ON u.id=c.to_user_id
              WHERE c.from_user_id=? AND c.state IN ('interested','accepted','declined') AND t.date_to >= ? ORDER BY c.id DESC", [$uid, $today])),
        array_map(static fn($r) => $r + ['kind' => 'local', 'target_id' => 0, 'target_title' => 'Meeting a local'], q_all(
            "SELECT l.to_user_id to_id, l.state, l.created_at, u.username
               FROM local_connects l JOIN users u ON u.id=l.to_user_id WHERE l.from_user_id=? ORDER BY l.created_at DESC", [$uid]))
    );

    // Accepted, from either side, one row per person.
    $buddies = [];
    $acc = q_all("SELECT CASE WHEN b.user_id=? THEN i.user_id ELSE b.user_id END other_id, b.title what, i.decided_at
                    FROM buddy_interest i JOIN buddy_posts b ON b.id=i.post_id
                   WHERE i.state='accepted' AND (b.user_id=? OR i.user_id=?)", [$uid, $uid, $uid]);
    $acc = array_merge($acc,
        q_all("SELECT CASE WHEN c.to_user_id=? THEN c.from_user_id ELSE c.to_user_id END other_id, t.title what, c.decided_at
                 FROM trip_connects c JOIN trips t ON t.id=c.trip_id
                WHERE c.state='accepted' AND (c.to_user_id=? OR c.from_user_id=?)", [$uid, $uid, $uid]),
        q_all("SELECT CASE WHEN l.to_user_id=? THEN l.from_user_id ELSE l.to_user_id END other_id, 'Meeting a local' what, l.decided_at
                 FROM local_connects l WHERE l.state='accepted' AND (l.to_user_id=? OR l.from_user_id=?)", [$uid, $uid, $uid]));
    foreach ($acc as $r) {
        $oid = (int) $r['other_id'];
        if (isset($buddies[$oid]) || $oid === $uid) continue;
        $u = q_one("SELECT u.username, p.avatar_url, p.display_name FROM users u LEFT JOIN profiles p ON p.user_id=u.id WHERE u.id=? AND u.status='active'", [$oid]);
        if ($u && !(function_exists('rmt_is_blocked') && rmt_is_blocked($uid, $oid))) $buddies[$oid] = $u + ['what' => $r['what']];
    }

    $saved = q_all("SELECT b.id, b.title, b.date_from, b.date_to, b.where_text, b.status, u.username FROM saves s
                      JOIN buddy_posts b ON b.id=s.target_id AND b.status IN ('open','closed','completed') JOIN users u ON u.id=b.user_id
                     WHERE s.user_id=? AND s.target_type='buddy' ORDER BY b.date_from", [$uid]);

    // How many people each of my upcoming plans already lines up with.
    $me = ['id' => $uid];
    $matches = [];
    foreach ($posts as $p) {
        $f = rmt_buddy_filters(['from' => $p['date_from'], 'to' => $p['date_to'], 'dest' => $p['dest_slug'] ?? '',
                                'ship' => $p['trip_type'] === 'cruise' ? (string) ($p['ship'] ?? '') : '',
                                'flexible' => $p['flexible'] ? '1' : '']);
        if (!$f['dest_id'] && $f['ship'] === '') continue;
        $matches['post:' . $p['id']] = ['count' => count(rmt_buddy_search_run($f, $me, 60)), 'query' => rmt_buddy_query($f)];
    }
    foreach ($trips as $t) {
        if (empty($t['dest_slug'])) continue;
        $f = rmt_buddy_filters(['from' => $t['date_from'], 'to' => $t['date_to'], 'dest' => $t['dest_slug']]);
        $matches['trip:' . $t['id']] = ['count' => count(rmt_buddy_search_run($f, $me, 60)), 'query' => rmt_buddy_query($f)];
    }
    $profile = q_one('SELECT p.open_to_meeting, p.home_destination_id, d.name home_name, d.slug home_slug FROM profiles p
                      LEFT JOIN destinations d ON d.id=p.home_destination_id WHERE p.user_id=?', [$uid]) ?: [];
    return compact('posts', 'trips', 'pastPosts', 'pastTrips', 'received', 'sent', 'buddies', 'saved', 'matches', 'profile');
}

/* ---------- controllers ---------- */

function buddies_index(array $a): void {
    $g = $_GET;
    if (isset($a['type'])) $g['type'] = str_replace('-', '_', (string) $a['type']);
    if (isset($a['type']) && !isset(RMT_BUDDY_TYPES[$g['type']])) not_found();
    $me = current_user();
    $f = rmt_buddy_filters($g);
    $res = rmt_buddy_search($f, $me, 60);
    $cards = rmt_buddy_card_interests($res['cards']);
    $sailings = $f['type'] === 'cruise' ? rmt_buddy_group_sailings($cards) : [];
    /* While the real list is thin, labeled examples show what the section does. Never stored,
       never counted; see app/buddy_examples.php. ?examples=0 hides them. */
    $realPeople = count($cards);
    $examples = ($realPeople < RMT_BUDDY_EXAMPLES_BELOW && input('examples') !== '0') ? rmt_buddy_examples_for($f) : [];
    $dests = all_dests();
    $countries = array_values(array_unique(array_filter(array_column($dests, 'country'))));
    sort($countries);
    /* "Who else is going where I am going", one tap: the member's own upcoming plans as ready made
       searches. Their own rows only. */
    $mine = [];
    if ($me) {
        foreach (q_all("SELECT d.slug, d.name, t.date_from, t.date_to FROM trips t JOIN destinations d ON d.id = t.destination_id
                         WHERE t.user_id = ? AND t.status = 'published' AND t.date_from IS NOT NULL AND t.date_to >= ?
                         ORDER BY t.date_from LIMIT 6", [(int) $me['id'], date('Y-m-d')]) as $t) {
            $mine[] = ['label' => $t['name'] . ', ' . date('M j', strtotime((string) $t['date_from'])),
                       'href' => url('buddies') . '?' . http_build_query(['dest' => $t['slug'], 'from' => $t['date_from'], 'to' => $t['date_to']])];
        }
        foreach (q_all("SELECT b.trip_type, b.ship, b.title, b.date_from, b.date_to, d.slug FROM buddy_posts b LEFT JOIN destinations d ON d.id = b.destination_id
                         WHERE b.user_id = ? AND b.status = 'open' AND b.date_to >= ? ORDER BY b.date_from LIMIT 6", [(int) $me['id'], date('Y-m-d')]) as $b) {
            if ($b['trip_type'] === 'cruise' && !empty($b['ship'])) {
                $mine[] = ['label' => $b['ship'] . ', ' . date('M j', strtotime((string) $b['date_from'])),
                           'href' => url('buddies/cruise') . '?' . http_build_query(['ship' => $b['ship'], 'from' => $b['date_from'], 'to' => $b['date_from']])];
            } elseif (!empty($b['slug'])) {
                $mine[] = ['label' => $b['title'], 'href' => url('buddies') . '?' . http_build_query(['dest' => $b['slug'], 'from' => $b['date_from'], 'to' => $b['date_to'], 'flexible' => '1'])];
            }
        }
    }
    $label = $f['type'] ? RMT_BUDDY_TYPES[$f['type']] : null;
    $path = isset($a['type']) ? 'buddies/' . str_replace('_', '-', $f['type']) : 'buddies';
    $crumbs = [['name' => 'Home', 'url' => url()], ['name' => 'Travel buddies', 'url' => url('buddies')]];
    if (isset($a['type'])) $crumbs[] = ['name' => $label, 'url' => url($path)];
    $title = $f['type'] === 'cruise' && isset($a['type']) ? 'Find a cruise buddy: travelers on your sailing'
           : (isset($a['type']) ? 'Find a ' . strtolower((string) $label) . ' buddy'
           : ($f['dest'] ? 'Travel buddies in ' . $f['dest']['name'] . ': who is going and who lives there'
           : 'Find a travel buddy: meet people going where you are going'));
    $bf = $f;
    view('buddies_index', compact('bf', 'res', 'cards', 'sailings', 'examples', 'me', 'dests', 'countries', 'label', 'mine'), [
        'title' => $title . ' | RuinMyTrip',
        'description' => 'Going somewhere? Find people heading the same way. Meet travelers on your dates, locals open to meeting, and people on the same cruise. Join free at 16. Posting a trip is 18+, and nobody can message you until you say yes.',
        'canonical' => url($path),
        'breadcrumbs' => $crumbs,
    ]);
}

function buddies_mine(array $a): void {
    require_login(); $me = current_user();
    $data = rmt_buddy_dashboard((int) $me['id']);
    view('buddies_mine', $data + ['me' => $me, 'dests' => all_dests()], [
        'title' => 'Your travel buddies | RuinMyTrip',
        'description' => 'Your upcoming trips, requests, and the travelers you have connected with.',
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Travel buddies', 'url' => url('buddies')], ['name' => 'Yours', 'url' => url('buddies/mine')]],
    ]);
}

function buddy_new_form(array $a): void {
    /* Signed out, a city trip goes to the trip first form with the buddy box already ticked, so a
       stranger states the trip before being asked for an account. A cruise keeps its own form,
       which has the ship fields, and still asks for an account first. */
    if (!is_logged_in() && (string) input('type') !== 'cruise') {
        $q = ['buddy' => '1', 'cta' => 'cta_buddy'];
        if ((string) input('dest') !== '') $q['d'] = (string) input('dest');
        foreach (['from', 'to'] as $k) if (input($k) !== '') $q[$k] = (string) input($k);
        redirect('/plan?' . http_build_query($q));
    }
    require_login();
    if (!can_host_meetups(current_user())) { flash('Travel buddies is 18+.'); redirect('/buddies'); }
    $pre = [];
    $t = (string) input('type');
    if (isset(RMT_BUDDY_TYPES[$t])) $pre['trip_type'] = $t;
    if (($d = q_one('SELECT id FROM destinations WHERE slug=?', [(string) input('dest')]))) $pre['destination_id'] = (string) $d['id'];
    foreach (['date_from' => 'from', 'date_to' => 'to', 'ship' => 'ship', 'cruise_line' => 'line', 'departure_port' => 'port', 'where_text' => 'where'] as $col => $q) {
        if (input($q) !== '') $pre[$col] = input($q);
    }
    view('buddy_new', ['dests' => all_dests(), 'errors' => [], 'b' => $pre, 'isEdit' => false], [
        'title' => 'Find a travel buddy | RuinMyTrip',
        'description' => 'Post the cruise or trip you are taking and who you would like to go with.',
    ]);
}

function buddy_create(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    if (!can_host_meetups($me)) { flash('Travel buddies is 18+.'); redirect('/buddies'); }
    /* Same gate as before: nothing is published until the address is confirmed. A valid post is
       held and goes live on confirmation (app/onboarding_pending.php) instead of being discarded. */
    if (!email_is_verified($me)) {
        $hold = rmt_buddy_validate($_POST);
        if (!$hold['ok']) { view('buddy_new', ['dests' => all_dests(), 'errors' => $hold['errors'], 'b' => $_POST, 'isEdit' => false], ['title' => 'Find a travel buddy | RuinMyTrip']); return; }
        rmt_pending_stash(['buddy' => $_POST]);
        flash('Your post is saved. It goes live the moment you confirm your email address.');
        redirect('/verify-email');
    }
    $opts = ['title' => 'Find a travel buddy | RuinMyTrip'];
    if (!rmt_submit_ok('buddy_new', input('_submit'))) { flash('That post was already published.'); redirect('/buddies/mine'); return; }
    if (!rmt_rate_ok('buddy_create', (string) $me['id'], 5, 3600)) {
        view('buddy_new', ['dests' => all_dests(), 'errors' => ['You are posting very fast. Try again later.'], 'b' => $_POST, 'isEdit' => false], $opts);
        return;
    }
    $v = rmt_buddy_validate($_POST);
    if (!$v['ok']) { view('buddy_new', ['dests' => all_dests(), 'errors' => $v['errors'], 'b' => $_POST, 'isEdit' => false], $opts); return; }
    $id = rmt_buddy_insert((int) $me['id'], $v['data']);
    $n = rmt_buddy_notify_matches('buddy', $id);
    if (function_exists('rmt_track')) rmt_track('buddy_post_created', ['destination_id' => $v['data']['destination_id']]);
    flash($n > 0 ? 'Posted. ' . $n . ($n === 1 ? ' traveler whose plans line up has' : ' travelers whose plans line up have') . ' been told.'
                 : 'Posted. You will get a notification when somebody wants to come along, or when a matching trip is posted.');
    redirect('/buddy/' . $id);
}

function buddy_edit_form(array $a): void {
    require_login(); $me = current_user();
    $b = rmt_buddy_get((int) $a['id']);
    if (!$b || (int) $b['user_id'] !== (int) $me['id'] || !in_array($b['status'], ['open', 'closed'], true)) not_found();
    $b['interests'] = rmt_buddy_interest_keys($b['interests'] ?? '');
    view('buddy_new', ['dests' => all_dests(), 'errors' => [], 'b' => $b, 'isEdit' => true], ['title' => 'Edit your trip | RuinMyTrip']);
}

function buddy_edit_submit(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $b = rmt_buddy_get((int) $a['id']);
    if (!$b || (int) $b['user_id'] !== (int) $me['id'] || !in_array($b['status'], ['open', 'closed'], true)) not_found();
    $v = rmt_buddy_validate($_POST);
    if (!$v['ok']) { view('buddy_new', ['dests' => all_dests(), 'errors' => $v['errors'], 'b' => $_POST + $b, 'isEdit' => true], ['title' => 'Edit your trip | RuinMyTrip']); return; }
    rmt_buddy_update((int) $b['id'], $v['data']);
    if ($v['data']['date_from'] !== $b['date_from'] || $v['data']['date_to'] !== $b['date_to'] || (int) $v['data']['destination_id'] !== (int) $b['destination_id']) {
        rmt_buddy_notify_matches('buddy', (int) $b['id']);
    }
    flash('Saved.');
    redirect('/buddy/' . (int) $b['id']);
}

function buddy_show(array $a): void {
    $b = rmt_buddy_get((int) $a['id']);
    if (!$b || !in_array($b['status'], RMT_BUDDY_VISIBLE_STATUSES, true)) not_found();
    $b['author'] = author((int) $b['user_id']);
    if (!$b['author']) not_found();
    $me = current_user();
    $isOwner = $me && (int) $me['id'] === (int) $b['user_id'];
    // A block holds on the page too: neither side is shown the other's trip.
    if ($me && !$isOwner && function_exists('rmt_is_blocked') && rmt_is_blocked((int) $me['id'], (int) $b['user_id'])) not_found();
    $interest = q_all("SELECT i.*, u.username, p.avatar_url, p.display_name FROM buddy_interest i
                       JOIN users u ON u.id=i.user_id AND u.status='active' LEFT JOIN profiles p ON p.user_id=u.id
                       WHERE i.post_id=? ORDER BY i.created_at", [(int) $b['id']]);
    $mine = null;
    if ($me) foreach ($interest as $i) if ((int) $i['user_id'] === (int) $me['id']) $mine = $i;
    $accepted = count(array_filter($interest, static fn($i) => $i['state'] === 'accepted'));
    $isPast = (string) $b['date_to'] < date('Y-m-d');
    $interests = rmt_interest_labels(array_values(array_unique(array_merge(rmt_buddy_interest_keys($b['interests'] ?? ''), rmt_interests_for((int) $b['user_id'])))));
    $langs = function_exists('rmt_languages_for_user') ? rmt_language_labels(rmt_languages_for_user((int) $b['user_id'])) : [];
    $sameSailing = rmt_buddy_same_sailing($b, $me);
    $similar = rmt_buddy_similar_sailings($b);
    $alsoGoing = [];
    if (!empty($b['dest_slug'])) {
        $f = rmt_buddy_filters(['dest' => $b['dest_slug'], 'from' => $b['date_from'], 'to' => $b['date_to'], 'flexible' => $b['flexible'] ? '1' : '']);
        $alsoGoing = array_values(array_filter(rmt_buddy_search_run($f, $me, 12), static fn($c) => !($c['kind'] === 'post' && $c['id'] === (int) $b['id']) && $c['user_id'] !== (int) $b['user_id']));
    }
    $saved = $me && q_one("SELECT 1 FROM saves WHERE user_id=? AND target_type='buddy' AND target_id=?", [(int) $me['id'], (int) $b['id']]);
    view('buddy_show', compact('b', 'me', 'isOwner', 'interest', 'mine', 'accepted', 'isPast', 'interests', 'langs', 'sameSailing', 'similar', 'alsoGoing', 'saved'), [
        'title' => $b['title'] . ' | Travel buddy wanted',
        'description' => mb_substr((RMT_BUDDY_TYPES[$b['trip_type']] ?? 'Trip') . ': ' . $b['where_text'] . '. ' . $b['description'], 0, 155),
        /* What a link to this page looks like in a chat or a feed: the ask, and the city's picture
           rather than the site's default. Share fields only; the title and description the search
           engines read are untouched. */
        'og_title' => 'Travel buddy wanted: ' . $b['title'],
        'og_description' => 'Going to ' . ($b['dest_name'] ?: $b['where_text']) . ' around then? Say hello on RuinMyTrip. Nobody can message anyone until both say yes.',
        'og_image' => !empty($b['dest_slug']) ? rmt_card_url('city', (string) $b['dest_slug']) : rmt_default_og_image(),
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Travel buddies', 'url' => url('buddies')],
                          ['name' => $b['title'], 'url' => url('buddy/' . (int) $b['id'])]],
    ]);
}

function buddy_interest(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $id = (int) $a['id'];
    $back = rmt_return_to('/buddy/' . $id);
    if (!can_host_meetups($me)) { flash('Travel buddies is 18+.'); redirect($back); }
    $b = rmt_buddy_get($id); if (!$b) not_found();
    if (!rmt_rate_ok('buddy_interest', (string) $me['id'], 30, 3600)) { flash('Slow down a little and try again soon.'); redirect($back); }
    $r = rmt_buddy_toggle_interest($b, (int) $me['id'], (string) input('note'));
    if ($r['ok'] && $r['action'] === 'interested' && function_exists('rmt_notify_email_direct')) {
        rmt_notify_email_direct((int) $b['user_id'], 'Somebody wants to join your trip', 'A traveler put their hand up on a trip you posted.', '/buddy/' . $id, 'somebody wants to join your trip');
    }
    flash(!$r['ok'] ? $r['error'] : ($r['action'] === 'interested'
        ? 'Sent. @' . (author((int) $b['user_id'])['username'] ?? 'the poster') . ' will see your note, and you can message each other once they accept.'
        : 'You took your hand back down.'));
    redirect($back);
}

function buddy_decide(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $b = rmt_buddy_get((int) $a['id']); if (!$b) not_found();
    $answer = (string) input('answer');
    // Acceptance opens messaging, the same moment a trip connect emails about, so it emails too.
    if (rmt_buddy_decide($b, (int) $me['id'], (int) $a['user_id'], $answer) && $answer === 'accepted' && function_exists('rmt_notify_email_direct')) {
        rmt_notify_email_direct((int) $a['user_id'], 'You can message your travel buddy now', 'A traveler accepted your request to join their trip on RuinMyTrip.', '/buddy/' . (int) $b['id'], 'your request was accepted');
    }
    redirect(rmt_return_to('/buddy/' . (int) $b['id']));
}

function buddy_status(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $b = rmt_buddy_get((int) $a['id']); if (!$b) not_found();
    $to = (string) input('status');
    $mine = (int) $me['id'] === (int) $b['user_id'];
    $allowed = ['open', 'closed', 'removed'];
    // Done is only offered once the trip has started: a trip nobody has taken yet is not complete.
    if ((string) $b['date_from'] <= date('Y-m-d')) $allowed[] = 'completed';
    if ($mine && in_array($b['status'], RMT_BUDDY_VISIBLE_STATUSES, true) && in_array($to, $allowed, true)) {
        if ($to === 'open' && (string) $b['date_to'] < date('Y-m-d')) { flash('That trip is over, so it cannot be reopened.'); redirect('/buddy/' . (int) $b['id']); }
        q_run('UPDATE buddy_posts SET status=?, updated_at=? WHERE id=?', [$to, date('Y-m-d H:i:s'), (int) $b['id']]);
        if ($to === 'removed') { flash('Trip cancelled and removed.'); redirect('/buddies/mine'); }
        if ($to === 'completed') { flash('Marked as completed. It stays in your past trips, and is no longer listed.'); redirect('/buddies/mine'); }
    }
    redirect(rmt_return_to('/buddy/' . (int) $b['id']));
}

/** POST /buddies/here: "I am in this city now", as a public trip covering today. */
function buddies_here_now(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    if (!can_host_meetups($me)) { flash('Travel buddies is 18+.'); redirect('/buddies'); }
    $today = date('Y-m-d');
    $until = (string) input('until');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) || $until < $today) $until = $today;
    $v = rmt_plan_validate(['destination_id' => input('destination_id'), 'date_from' => $today, 'date_to' => $until, 'visibility' => 'public']);
    if (!$v['ok']) { flash(implode(' ', $v['errors'])); redirect('/buddies'); }
    $tripId = rmt_plan_upsert((int) $me['id'], $v['data']);
    q_run('UPDATE trips SET open_to_meeting = 1 WHERE id = ? AND user_id = ?', [$tripId, (int) $me['id']]);
    if (function_exists('rmt_match_notify')) rmt_match_notify((int) $me['id'], $tripId, (int) $v['data']['destination_id'], $today, $until, 'public');
    rmt_buddy_notify_matches('trip', $tripId);
    $slug = (string) (q_one('SELECT slug FROM destinations WHERE id=?', [(int) $v['data']['destination_id']])['slug'] ?? '');
    flash('You are marked as in town until ' . date('M j', strtotime($until)) . '. Travelers searching this city will see you. It shows the city and dates only.');
    redirect('/buddies?dest=' . rawurlencode($slug) . '&show=here');
}

/** POST /buddies/local: "I live here and I am open to meeting travelers", or switch it off. */
function buddies_local(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    if (!can_host_meetups($me)) { flash('Travel buddies is 18+.'); redirect('/buddies'); }
    $uid = (int) $me['id'];
    if (function_exists('rmt_profile_ensure')) rmt_profile_ensure($uid);
    if (input('off') === '1') {
        q_run('UPDATE profiles SET open_to_meeting = 0 WHERE user_id = ?', [$uid]);
        flash('You are no longer listed as a local open to meeting travelers.');
        redirect(rmt_return_to('/buddies/mine'));
    }
    $d = q_one('SELECT id, name, slug FROM destinations WHERE id=?', [(int) input('destination_id')]);
    if (!$d) { flash('Pick the city you live in.'); redirect(rmt_return_to('/buddies')); }
    q_run('UPDATE profiles SET home_destination_id = ?, open_to_meeting = 1, home_city = COALESCE(NULLIF(home_city, \'\'), ?) WHERE user_id = ?',
          [(int) $d['id'], (string) $d['name'], $uid]);
    flash('You are listed as a local in ' . $d['name'] . ' who is open to meeting travelers. Only the city shows. Switch it off any time from Your buddies.');
    redirect('/buddies?dest=' . rawurlencode((string) $d['slug']) . '&show=locals');
}

function buddies_local_connect(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $back = rmt_return_to('/buddies');
    if (!can_host_meetups($me)) { flash('Travel buddies is 18+.'); redirect($back); }
    if (!rmt_rate_ok('connect', (string) $me['id'], 60, 3600)) { flash('You are doing that very fast. Try again shortly.'); redirect($back); }
    $to = (int) $a['user_id'];
    if (input('withdraw') === '1') { rmt_local_connect_withdraw((int) $me['id'], $to); flash('Taken back.'); redirect($back); }
    $r = rmt_local_connect_request((int) $me['id'], $to);
    if (!$r['ok']) { flash($r['error']); redirect($back); }
    if (!empty($r['created']) && function_exists('rmt_notify_email_direct')) {
        rmt_notify_email_direct($to, 'A traveler would like to meet a local', 'A traveler asked to connect with you as a local on RuinMyTrip.', '/buddies/mine', 'a traveler asked to meet a local');
    }
    flash(!empty($r['created']) ? 'Sent. They will see it and decide. You can message each other if they accept.' : 'Already sent.');
    redirect($back);
}

function buddies_local_decide(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $answer = input('answer') === 'accepted' ? 'accepted' : 'declined';
    if (rmt_local_connect_decide((int) $me['id'], (int) $a['user_id'], $answer) && $answer === 'accepted' && function_exists('rmt_notify_email_direct')) {
        rmt_notify_email_direct((int) $a['user_id'], 'A local said yes', 'A local you asked to meet accepted on RuinMyTrip. You can message each other now.', '/buddies/mine', 'your request was accepted');
    }
    redirect(rmt_return_to('/buddies/mine'));
}
