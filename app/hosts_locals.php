<?php
declare(strict_types=1);

/**
 * Hosts & Locals: travelers meeting the people who live where they are going.
 *
 * One account and one profile, three roles, and no new system where one already exists:
 *
 *   Traveler    every member. Trips, buddy posts and "who is going" are the existing travel buddy
 *               search (app/buddies.php), reused here rather than rebuilt.
 *   Local       profiles.open_to_meeting with a home city, which the buddy search already reads,
 *               plus what the local is happy to do (local_offers) and when (local_availability).
 *               Asking to meet one is the existing local_connects request, and messaging opens
 *               only once the local says yes (rmt_message_allowed).
 *   Host        a host_profiles row. It is an application: nobody sees it until a moderator has
 *               approved it, the host can pause it at any time, and its neighbourhood is the only
 *               location it ever holds. There is no address column, so none can leak.
 *
 * OVERNIGHT STAYS ARE OFF. rmt_stays_enabled() is false and does not read any setting, so a stay
 * cannot be requested or confirmed until identity verification, a stay-specific report flow and a
 * reviewed safety process exist and somebody changes this code on purpose. Host profiles can be
 * written, reviewed and shown as "interested in hosting" in the meantime, which is how the first
 * hosts are found before the feature opens.
 *
 * Nothing on these pages is invented. Every person shown is a real member who chose to be shown,
 * every count is a live COUNT, and an empty city says it is empty and asks the reader to be first.
 */

/** Overnight stays. Deliberately a constant false, not a setting: see the header. */
function rmt_stays_enabled(): bool { return false; }

/** What a local can offer. Never accommodation: that is hosting, and it is a different role. */
const RMT_LOCAL_OFFERS = [
    'coffee'        => 'Meet for a coffee or a drink',
    'show_around'   => 'Show a traveler around',
    'food'          => 'Restaurant and food tips',
    'transport'     => 'Help with getting around',
    'sights'        => 'Museums, landmarks and sights',
    'neighborhoods' => 'Neighbourhood walks',
    'group'         => 'Group outings with other travelers',
    'advice'        => 'Answer questions before the trip',
];

const RMT_HOST_SPACES = [
    'private_room' => 'Private room',
    'shared_room'  => 'Shared room',
    'couch'        => 'Couch or sofa bed',
    'other'        => 'Something else (described below)',
];
const RMT_HOST_PETS = [
    'none'     => 'No pets live here',
    'has_pets' => 'Pets live here',
];
const RMT_HOST_SMOKING = [
    'no'      => 'No smoking',
    'outside' => 'Smoking outside only',
    'ok'      => 'Smoking allowed',
];
/** Host application states. Only 'approved' is ever shown to anybody but the host and staff. */
const RMT_HOST_STATUSES = ['pending', 'approved', 'paused', 'rejected', 'withdrawn'];

/** Cities the hub features when no city has members yet. Real destinations, editorial choice. */
const RMT_HL_FEATURED = ['tokyo-japan', 'paris-france', 'lisbon-portugal', 'mexico-city-mexico',
                         'bangkok-thailand', 'barcelona-spain', 'new-york-city-united-states', 'rome-italy'];

/* ---------- roles ---------- */

/** @return list<string> */
function rmt_local_offers_for(?string $stored): array {
    if ($stored === null || $stored === '') return [];
    $set = array_flip(explode(',', $stored));
    return array_values(array_filter(array_keys(RMT_LOCAL_OFFERS), static fn(string $k) => isset($set[$k])));
}

function rmt_host_profile(int $uid): ?array {
    return $uid > 0 ? q_one('SELECT h.*, d.name dest_name, d.slug dest_slug, d.country dest_country
                               FROM host_profiles h LEFT JOIN destinations d ON d.id = h.destination_id
                              WHERE h.user_id = ?', [$uid]) : null;
}

/**
 * The roles a member holds, as other people should see them.
 *
 * @return array{traveler:bool, local:bool, host:bool, host_status:?string, home:?array}
 */
function rmt_member_roles(int $uid): array {
    $p = q_one("SELECT p.open_to_meeting, p.home_destination_id, d.name, d.slug FROM profiles p
                  LEFT JOIN destinations d ON d.id = p.home_destination_id WHERE p.user_id = ?", [$uid]);
    $h = q_one('SELECT status FROM host_profiles WHERE user_id = ?', [$uid]);
    return [
        'traveler'    => true,
        'local'       => $p && (int) $p['open_to_meeting'] === 1 && !empty($p['home_destination_id']),
        'host'        => $h && $h['status'] === 'approved',
        'host_status' => $h ? (string) $h['status'] : null,
        'home'        => $p && !empty($p['slug']) ? ['name' => (string) $p['name'], 'slug' => (string) $p['slug']] : null,
    ];
}

/* ---------- validation ---------- */

/** @return array{ok:bool, errors:list<string>, data:array} */
function rmt_local_validate(array $in): array {
    $errors = [];
    $dest = q_one('SELECT id, name, slug FROM destinations WHERE id = ?', [(int) ($in['destination_id'] ?? 0)]);
    if (!$dest) $errors[] = 'Pick the city you live in.';
    $offers = array_values(array_intersect(array_keys(RMT_LOCAL_OFFERS), array_map('strval', (array) ($in['offers'] ?? []))));
    if (!$offers) $errors[] = 'Pick at least one thing you would be happy to do.';
    $avail = mb_substr(trim((string) ($in['availability'] ?? '')), 0, 160);
    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'destination_id' => $dest ? (int) $dest['id'] : 0, 'dest' => $dest,
        'offers' => $offers, 'availability' => $avail,
    ]];
}

/** @return array{ok:bool, errors:list<string>, data:array} */
function rmt_host_validate(array $in): array {
    $errors = [];
    $s = static fn(string $k, int $max): string => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);
    $dest = q_one('SELECT id, name, slug FROM destinations WHERE id = ?', [(int) ($in['destination_id'] ?? 0)]);
    if (!$dest) $errors[] = 'Pick the city you would host in.';
    $space = (string) ($in['space_type'] ?? '');
    if (!isset(RMT_HOST_SPACES[$space])) $errors[] = 'Say what kind of space a guest would have.';
    $guests = (int) ($in['max_guests'] ?? 0);
    if ($guests < 1 || $guests > 6) $errors[] = 'Guests must be between 1 and 6.';
    $min = (int) ($in['min_nights'] ?? 0);
    $max = (int) ($in['max_nights'] ?? 0);
    if ($min < 1 || $min > 14) $errors[] = 'The shortest stay must be between 1 and 14 nights.';
    if ($max < $min || $max > 30) $errors[] = 'The longest stay must be at least the shortest, and 30 nights at most.';
    $about = $s('about_space', 1500);
    if (mb_strlen($about) < 40) $errors[] = 'Describe the space in a few sentences (40 characters or more).';
    $hood = $s('neighborhood', 80);
    /* The neighbourhood is shown to members, so it must not be an address. A house number, a
       postcode or the word "street" is refused rather than trusted. */
    if ($hood !== '' && (preg_match('/\d{2,}/', $hood) || preg_match('/\b(street|st\.|road|rd\.|avenue|ave\.|apt|apartment|flat|floor|calle|rua|rue|strasse|straße|via)\b/iu', $hood))) {
        $errors[] = 'Give the neighbourhood only, never a street, a number or a postcode.';
    }
    $pets = (string) ($in['pets'] ?? '');
    if (!isset(RMT_HOST_PETS[$pets])) $errors[] = 'Say whether pets live there.';
    $smoke = (string) ($in['smoking'] ?? '');
    if (!isset(RMT_HOST_SMOKING[$smoke])) $errors[] = 'Say what the smoking rule is.';
    if (empty($in['agree'])) $errors[] = 'Tick the box to confirm you have read the hosting guidelines.';
    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'destination_id' => $dest ? (int) $dest['id'] : 0, 'neighborhood' => $hood, 'space_type' => $space,
        'max_guests' => $guests, 'min_nights' => $min, 'max_nights' => $max,
        'available_note' => $s('available_note', 160), 'about_space' => $about,
        'house_rules' => $s('house_rules', 1000), 'accessibility' => $s('accessibility', 500),
        'pets' => $pets, 'smoking' => $smoke,
    ]];
}

/**
 * Save a host application. A new one, or any change to an approved one's description, goes back
 * to review, because what a moderator approved is what members see.
 */
function rmt_host_save(int $uid, array $d): string {
    $now = date('Y-m-d H:i:s');
    $have = q_one('SELECT status FROM host_profiles WHERE user_id = ?', [$uid]);
    $cols = ['destination_id', 'neighborhood', 'space_type', 'max_guests', 'min_nights', 'max_nights',
             'available_note', 'about_space', 'house_rules', 'accessibility', 'pets', 'smoking'];
    $vals = array_map(static fn(string $c) => $d[$c], $cols);
    if (!$have) {
        q_run('INSERT INTO host_profiles (user_id, status, ' . implode(', ', $cols) . ', created_at, updated_at)
               VALUES (?, \'pending\', ' . implode(', ', array_fill(0, count($cols), '?')) . ', ?, ?)',
              array_merge([$uid], $vals, [$now, $now]));
        return 'pending';
    }
    $status = in_array($have['status'], ['rejected', 'withdrawn', 'approved'], true) ? 'pending' : (string) $have['status'];
    q_run('UPDATE host_profiles SET ' . implode(', ', array_map(static fn(string $c) => "$c = ?", $cols))
          . ', status = ?, updated_at = ? WHERE user_id = ?', array_merge($vals, [$status, $now, $uid]));
    return $status;
}

/* ---------- discovery ---------- */

/** Who blocked or was blocked by the viewer, as SQL against a user id column. @return array{string,array} */
function rmt_hl_block_sql(string $col, ?int $viewerId): array {
    if (!$viewerId) return ['1=1', []];
    return ["NOT EXISTS (SELECT 1 FROM blocks b WHERE (b.blocker_id = ? AND b.blocked_id = $col)
                                                OR (b.blocked_id = ? AND b.blocker_id = $col))", [$viewerId, $viewerId]];
}

/**
 * Locals open to meeting travelers, optionally in one city, speaking a language, with an interest
 * or offering one thing.
 *
 * @return list<array<string,mixed>>
 */
function rmt_hl_locals(array $f, ?int $viewerId, int $limit = 24): array {
    $w = ['COALESCE(p.open_to_meeting, 0) = 1', 'p.home_destination_id IS NOT NULL', "u.status = 'active'"];
    $a = [];
    if (defined('RMT_EDITORIAL_ROLE')) { $w[] = 'u.role <> ?'; $a[] = RMT_EDITORIAL_ROLE; }
    if ($viewerId) { $w[] = 'u.id <> ?'; $a[] = $viewerId; }
    if (!empty($f['dest_id'])) { $w[] = 'p.home_destination_id = ?'; $a[] = (int) $f['dest_id']; }
    if (($f['lang'] ?? '') !== '') { $w[] = "(',' || COALESCE(p.languages, '') || ',') LIKE ?"; $a[] = '%,' . $f['lang'] . ',%'; }
    if (($f['offer'] ?? '') !== '') { $w[] = "(',' || COALESCE(p.local_offers, '') || ',') LIKE ?"; $a[] = '%,' . $f['offer'] . ',%'; }
    if (($f['interest'] ?? '') !== '') { $w[] = 'EXISTS (SELECT 1 FROM profile_interests pi WHERE pi.user_id = u.id AND pi.interest = ?)'; $a[] = $f['interest']; }
    [$bs, $ba] = rmt_hl_block_sql('u.id', $viewerId); $w[] = $bs; array_push($a, ...$ba);
    return q_all("SELECT u.id user_id, u.username, u.email_verified_at, p.display_name, p.avatar_url, p.bio,
                         p.languages, p.local_offers, p.local_availability, d.name dest_name, d.slug dest_slug
                    FROM profiles p JOIN users u ON u.id = p.user_id
                    JOIN destinations d ON d.id = p.home_destination_id
                   WHERE " . implode(' AND ', $w) . ' ORDER BY u.id DESC LIMIT ' . max(1, min(60, $limit)), $a);
}

/**
 * Approved, unpaused hosts. Never a street or a number: the neighbourhood is all there is.
 *
 * @return list<array<string,mixed>>
 */
function rmt_hl_hosts(array $f, ?int $viewerId, int $limit = 24): array {
    $w = ["h.status = 'approved'", "u.status = 'active'"];
    $a = [];
    if ($viewerId) { $w[] = 'u.id <> ?'; $a[] = $viewerId; }
    if (!empty($f['dest_id'])) { $w[] = 'h.destination_id = ?'; $a[] = (int) $f['dest_id']; }
    if (($f['lang'] ?? '') !== '') { $w[] = "(',' || COALESCE(p.languages, '') || ',') LIKE ?"; $a[] = '%,' . $f['lang'] . ',%'; }
    if (($f['interest'] ?? '') !== '') { $w[] = 'EXISTS (SELECT 1 FROM profile_interests pi WHERE pi.user_id = u.id AND pi.interest = ?)'; $a[] = $f['interest']; }
    [$bs, $ba] = rmt_hl_block_sql('u.id', $viewerId); $w[] = $bs; array_push($a, ...$ba);
    return q_all("SELECT h.*, u.id user_id, u.username, u.email_verified_at, p.display_name, p.avatar_url, p.bio, p.languages,
                         d.name dest_name, d.slug dest_slug
                    FROM host_profiles h JOIN users u ON u.id = h.user_id
               LEFT JOIN profiles p ON p.user_id = h.user_id
               LEFT JOIN destinations d ON d.id = h.destination_id
                   WHERE " . implode(' AND ', $w) . ' ORDER BY h.reviewed_at DESC, h.user_id DESC LIMIT ' . max(1, min(60, $limit)), $a);
}

/** Live counts for one city. @return array{locals:int, hosts:int, going:int} */
function rmt_hl_city_counts(int $destId): array {
    $today = date('Y-m-d');
    return [
        'locals' => (int) q_one("SELECT COUNT(*) n FROM profiles p JOIN users u ON u.id = p.user_id AND u.status = 'active'
                                  WHERE p.home_destination_id = ? AND COALESCE(p.open_to_meeting, 0) = 1", [$destId])['n'],
        'hosts'  => (int) q_one("SELECT COUNT(*) n FROM host_profiles h JOIN users u ON u.id = h.user_id AND u.status = 'active'
                                  WHERE h.destination_id = ? AND h.status = 'approved'", [$destId])['n'],
        'going'  => (int) q_one("SELECT COUNT(DISTINCT t.user_id) n FROM trips t JOIN users u ON u.id = t.user_id AND u.status = 'active'
                                  WHERE t.destination_id = ? AND t.status = 'published' AND COALESCE(t.visibility, 'public') = 'public'
                                    AND t.date_to >= ?", [$destId, $today])['n']
                  + (int) q_one("SELECT COUNT(DISTINCT b.user_id) n FROM buddy_posts b JOIN users u ON u.id = b.user_id AND u.status = 'active'
                                  WHERE b.destination_id = ? AND b.status = 'open' AND b.date_to >= ?", [$destId, $today])['n'],
    ];
}

/**
 * Cities for the hub: the ones with real people first, then the editorial picks, each with an
 * image and its live counts. Never more than eight.
 *
 * @return list<array<string,mixed>>
 */
function rmt_hl_featured_cities(int $n = 8): array {
    $ids = [];
    foreach (q_all("SELECT p.home_destination_id id, COUNT(*) c FROM profiles p JOIN users u ON u.id = p.user_id AND u.status = 'active'
                     WHERE COALESCE(p.open_to_meeting, 0) = 1 AND p.home_destination_id IS NOT NULL
                  GROUP BY p.home_destination_id ORDER BY c DESC LIMIT 8") as $r) $ids[(int) $r['id']] = true;
    foreach (q_all("SELECT destination_id id FROM host_profiles WHERE status = 'approved' AND destination_id IS NOT NULL LIMIT 8") as $r) $ids[(int) $r['id']] = true;
    $out = [];
    $seen = [];
    $add = static function (?array $d) use (&$out, &$seen): void {
        if (!$d || isset($seen[(int) $d['id']])) return;
        $seen[(int) $d['id']] = true;
        $out[] = $d + rmt_hl_city_counts((int) $d['id']);
    };
    foreach (array_keys($ids) as $id) $add(q_one('SELECT id, slug, name, country, hero_url FROM destinations WHERE id = ?', [$id]));
    foreach (RMT_HL_FEATURED as $slug) $add(q_one('SELECT id, slug, name, country, hero_url FROM destinations WHERE slug = ?', [$slug]));
    /* Fill the grid: a featured slug the database does not have would otherwise leave a hole. */
    if (count($out) < $n) foreach (q_all("SELECT id, slug, name, country, hero_url FROM destinations
                                          WHERE hero_url IS NOT NULL AND hero_url <> '' ORDER BY id LIMIT 20") as $d) {
        if (count($out) >= $n) break;
        $add($d);
    }
    return array_slice($out, 0, $n);
}

/** Read the hub's search and filter boxes. */
function rmt_hl_filters(array $g): array {
    $f = [
        'dest' => null, 'dest_id' => 0, 'where' => mb_substr(trim((string) ($g['where'] ?? '')), 0, 80),
        'lang' => isset(RMT_LANGUAGES[$g['lang'] ?? '']) ? (string) $g['lang'] : '',
        'interest' => isset(RMT_INTERESTS[$g['interest'] ?? '']) ? (string) $g['interest'] : '',
        'offer' => isset(RMT_LOCAL_OFFERS[$g['offer'] ?? '']) ? (string) $g['offer'] : '',
        'role' => in_array($g['role'] ?? '', ['locals', 'hosts', 'travelers'], true) ? (string) $g['role'] : '',
        'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($g['from'] ?? '')) ? (string) $g['from'] : '',
        'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($g['to'] ?? '')) ? (string) $g['to'] : '',
    ];
    $slug = (string) ($g['dest'] ?? '');
    if ($slug !== '') $f['dest'] = q_one('SELECT id, slug, name, country, hero_url, summary FROM destinations WHERE slug = ?', [$slug]);
    if (!$f['dest'] && $f['where'] !== '') {
        $w = mb_strtolower(preg_replace('/\s*,.*$/', '', $f['where']));
        $f['dest'] = q_one('SELECT id, slug, name, country, hero_url, summary FROM destinations WHERE LOWER(name) = ? OR LOWER(slug) = ? ORDER BY id LIMIT 1', [$w, $w]);
    }
    if ($f['dest']) $f['dest_id'] = (int) $f['dest']['id'];
    return $f;
}

/* ---------- references ---------- */

const RMT_REF_KINDS = [
    'met'       => 'Met as a local and a traveler',
    'traveled'  => 'Traveled together',
];
const RMT_REF_BODY_MIN = 30;
const RMT_REF_BODY_MAX = 2000;

/**
 * The real, accepted connection two members share, or null. A reference can only be written on
 * top of one, and only once it is at least a day old, so a reference cannot be the first thing
 * two strangers do with each other.
 *
 * @return ?array{basis:string, kind:string}
 */
function rmt_ref_basis(int $from, int $to): ?array {
    if ($from < 1 || $to < 1 || $from === $to) return null;
    $cut = date('Y-m-d H:i:s', time() - 86400);
    if (q_one("SELECT 1 FROM local_connects WHERE state = 'accepted' AND COALESCE(decided_at, created_at) <= ?
                AND ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)) LIMIT 1",
              [$cut, $from, $to, $to, $from])) return ['basis' => 'local_connect', 'kind' => 'met'];
    if (q_one("SELECT 1 FROM trip_connects WHERE state = 'accepted' AND COALESCE(decided_at, created_at) <= ?
                AND ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)) LIMIT 1",
              [$cut, $from, $to, $to, $from])) return ['basis' => 'trip_connect', 'kind' => 'traveled'];
    if (q_one("SELECT 1 FROM buddy_interest i JOIN buddy_posts p ON p.id = i.post_id
                WHERE i.state = 'accepted' AND COALESCE(i.decided_at, i.created_at) <= ?
                  AND ((p.user_id = ? AND i.user_id = ?) OR (p.user_id = ? AND i.user_id = ?)) LIMIT 1",
              [$cut, $from, $to, $to, $from])) return ['basis' => 'buddy', 'kind' => 'traveled'];
    /* On the same trip: one owns it and the other joined it, or both joined it. */
    if (q_one("SELECT 1 FROM trips t
                WHERE t.status = 'published'
                  AND (t.user_id IN (?, ?) OR EXISTS (SELECT 1 FROM trip_members m0 WHERE m0.trip_id = t.id AND m0.user_id = ? AND m0.state = 'active'))
                  AND EXISTS (SELECT 1 FROM trip_members m WHERE m.trip_id = t.id AND m.state = 'active' AND m.created_at <= ?
                                AND m.user_id IN (?, ?) AND m.user_id <> t.user_id)
                  AND (t.user_id = ? OR EXISTS (SELECT 1 FROM trip_members a WHERE a.trip_id = t.id AND a.user_id = ? AND a.state = 'active'))
                  AND (t.user_id = ? OR EXISTS (SELECT 1 FROM trip_members b WHERE b.trip_id = t.id AND b.user_id = ? AND b.state = 'active'))
                LIMIT 1", [$from, $to, $from, $cut, $from, $to, $from, $from, $to, $to])) return ['basis' => 'trip_member', 'kind' => 'traveled'];
    return null;
}

/** Why $me may not write a reference for $them, or null when they may. */
function rmt_ref_blocked_reason(?array $me, array $them): ?string {
    if (!$me) return 'Sign in first.';
    if ((int) $me['id'] === (int) $them['id']) return 'You cannot write a reference for yourself.';
    if (empty($me['email_verified_at'])) return 'Confirm your email address first.';
    if (($them['status'] ?? '') !== 'active') return 'This member is not active.';
    if (rmt_is_blocked((int) $me['id'], (int) $them['id'])) return 'You cannot write a reference for this member.';
    if (!rmt_ref_basis((int) $me['id'], (int) $them['id'])) {
        return 'References are for people you actually connected with here: a local who said yes to meeting you, or somebody you traveled with. It opens a day after you connect.';
    }
    return null;
}

/** @return array{ok:bool, errors:list<string>, data:array} */
function rmt_ref_validate(array $in): array {
    $errors = [];
    $star = static fn(string $k, bool $req) => (($v = (int) ($in[$k] ?? 0)) >= 1 && $v <= 5) ? $v : ($req ? 0 : null);
    $overall = $star('rating_overall', true);
    if (!$overall) $errors[] = 'Give an overall rating from 1 to 5.';
    $body = trim((string) ($in['body'] ?? ''));
    if (mb_strlen($body) < RMT_REF_BODY_MIN) $errors[] = 'Write at least ' . RMT_REF_BODY_MIN . ' characters about what happened.';
    if (mb_strlen($body) > RMT_REF_BODY_MAX) $errors[] = 'Keep it under ' . RMT_REF_BODY_MAX . ' characters.';
    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'rating_overall' => $overall, 'rating_communication' => $star('rating_communication', false),
        'rating_reliability' => $star('rating_reliability', false), 'rating_accuracy' => $star('rating_accuracy', false),
        'body' => $body,
    ]];
}

/** Write or update the one reference $from has for $to. @return array{ok:bool, error?:string, created?:bool} */
function rmt_ref_save(array $from, array $to, array $d): array {
    $why = rmt_ref_blocked_reason($from, $to);
    if ($why) return ['ok' => false, 'error' => $why];
    $basis = rmt_ref_basis((int) $from['id'], (int) $to['id']);
    $now = date('Y-m-d H:i:s');
    $have = q_one('SELECT id, status FROM member_references WHERE from_user_id = ? AND to_user_id = ? AND kind = ?',
                  [(int) $from['id'], (int) $to['id'], $basis['kind']]);
    if ($have && $have['status'] !== 'published') return ['ok' => false, 'error' => 'This reference is with our moderators and cannot be changed right now.'];
    if ($have) {
        q_run('UPDATE member_references SET rating_overall = ?, rating_communication = ?, rating_reliability = ?, rating_accuracy = ?,
                      body = ?, basis = ?, updated_at = ? WHERE id = ?',
              [$d['rating_overall'], $d['rating_communication'], $d['rating_reliability'], $d['rating_accuracy'],
               $d['body'], $basis['basis'], $now, (int) $have['id']]);
        return ['ok' => true, 'created' => false];
    }
    if (!rmt_rate_ok('reference', (string) $from['id'], 10, 86400)) return ['ok' => false, 'error' => 'You have written a lot of references today. Try again tomorrow.'];
    q_run('INSERT INTO member_references (from_user_id, to_user_id, kind, basis, rating_overall, rating_communication,
                  rating_reliability, rating_accuracy, body, status, created_at, updated_at)
           VALUES (?,?,?,?,?,?,?,?,?, \'published\', ?, ?)',
          [(int) $from['id'], (int) $to['id'], $basis['kind'], $basis['basis'], $d['rating_overall'], $d['rating_communication'],
           $d['rating_reliability'], $d['rating_accuracy'], $d['body'], $now, $now]);
    q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
          [(int) $to['id'], 'reference', (int) $from['id'], 'user', (int) $to['id'], $now]);
    return ['ok' => true, 'created' => true];
}

/** Published references about a member, newest first, minus anybody the viewer blocked. */
function rmt_refs_for(int $uid, ?int $viewerId): array {
    $rows = q_all("SELECT r.*, u.username, u.status author_status, p.display_name, p.avatar_url
                     FROM member_references r JOIN users u ON u.id = r.from_user_id
                LEFT JOIN profiles p ON p.user_id = r.from_user_id
                    WHERE r.to_user_id = ? AND r.status = 'published' AND u.status = 'active'
                 ORDER BY r.id DESC LIMIT 50", [$uid]);
    return $viewerId ? rmt_without_blocked($rows, $viewerId, 'from_user_id') : $rows;
}

/* ---------- controllers ---------- */

function rmt_hl_meta(string $title, string $desc, string $path, bool $index = true): array {
    return ['title' => $title, 'description' => $desc, 'canonical' => url(ltrim($path, '/')),
            'robots' => $index ? 'index, follow' : 'noindex, follow', 'css' => ['assets/css/hosts-locals.css']];
}

/** GET /hosts-locals */
function hosts_locals_hub(array $a): void {
    $me = current_user();
    $f = rmt_hl_filters($_GET);
    if ($f['dest']) redirect('/hosts-locals/destinations/' . $f['dest']['slug'] . rmt_hl_query($f));
    $cities = rmt_hl_featured_cities();
    $locals = rmt_hl_locals([], $me ? (int) $me['id'] : null, 6);
    $hosts = rmt_hl_hosts([], $me ? (int) $me['id'] : null, 3);
    $notFound = $f['where'] !== '' ? $f['where'] : '';
    $jsonld = '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org', '@type' => 'WebPage',
        'name' => 'Hosts & Locals', 'url' => url('hosts-locals'),
        'description' => 'Meet locals, find travel buddies and, soon, stay with hosts on RuinMyTrip.',
        'isPartOf' => ['@type' => 'WebSite', 'name' => 'RuinMyTrip', 'url' => url()],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    view('hosts_locals_hub', compact('me', 'cities', 'locals', 'hosts', 'notFound'), rmt_hl_meta(
        'Hosts & Locals: meet the people who live there | RuinMyTrip',
        'Going somewhere new? Meet a local who knows it best, find a travel buddy on your dates, and connect with future hosts. Free, with real members only.',
        '/hosts-locals') + ['jsonld' => $jsonld, 'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Hosts & Locals', 'url' => url('hosts-locals')]]]);
}

function rmt_hl_query(array $f): string {
    $q = array_filter(['lang' => $f['lang'], 'interest' => $f['interest'], 'offer' => $f['offer'], 'role' => $f['role'],
                       'from' => $f['from'], 'to' => $f['to']], static fn($v) => $v !== '');
    return $q ? '?' . http_build_query($q) : '';
}

/** GET /hosts-locals/destinations */
function hosts_locals_destinations(array $a): void {
    $rows = q_all('SELECT id, slug, name, country, hero_url FROM destinations ORDER BY country, name');
    foreach ($rows as &$r) $r += rmt_hl_city_counts((int) $r['id']);
    unset($r);
    usort($rows, static fn($x, $y) => [($y['locals'] + $y['hosts'] + $y['going']), $x['name']] <=> [($x['locals'] + $x['hosts'] + $x['going']), $y['name']]);
    view('hosts_locals_destinations', ['rows' => $rows], rmt_hl_meta(
        'Hosts & Locals by city | RuinMyTrip',
        'Every city on RuinMyTrip with the locals, future hosts and travelers who are there, counted live.',
        '/hosts-locals/destinations') + ['breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Hosts & Locals', 'url' => url('hosts-locals')], ['name' => 'Cities', 'url' => url('hosts-locals/destinations')]]]);
}

/** GET /hosts-locals/destinations/{slug} */
function hosts_locals_city(array $a): void {
    $me = current_user();
    $d = q_one('SELECT * FROM destinations WHERE slug = ?', [(string) $a['slug']]);
    if (!$d) not_found();
    $flt = $f = rmt_hl_filters($_GET + ['dest' => $d['slug']]);
    $vid = $me ? (int) $me['id'] : null;
    $locals = $f['role'] === '' || $f['role'] === 'locals' ? rmt_hl_locals($f, $vid, 24) : [];
    $hosts = $f['role'] === '' || $f['role'] === 'hosts' ? rmt_hl_hosts($f, $vid, 12) : [];
    $travelers = [];
    if ($f['role'] === '' || $f['role'] === 'travelers') {
        $bf = rmt_buddy_filters(['dest' => $d['slug'], 'from' => $f['from'], 'to' => $f['to'], 'interest' => $f['interest'], 'show' => 'going']);
        $travelers = rmt_buddy_search_run($bf, $me, $flt['role'] === 'travelers' ? 24 : 6);
    }
    $myLocal = [];
    if ($vid && $locals) foreach (q_all('SELECT to_user_id, state FROM local_connects WHERE from_user_id = ?', [$vid]) as $r) $myLocal[(int) $r['to_user_id']] = (string) $r['state'];
    $counts = rmt_hl_city_counts((int) $d['id']);
    $reviews = (int) (q_one("SELECT COUNT(*) n FROM reviews r LEFT JOIN places pl ON pl.id = r.place_id
                                WHERE (r.destination_id = ? OR pl.destination_id = ?) AND r.status = 'published'", [(int) $d['id'], (int) $d['id']])['n'] ?? 0);
    /* A city page is worth indexing once a real member is on it. Until then it is a useful page
       for a visitor and a thin one for a search engine, so it says noindex. */
    $index = ($counts['locals'] + $counts['hosts'] + $counts['going']) > 0;
    view('hosts_locals_city', compact('me', 'd', 'flt', 'locals', 'hosts', 'travelers', 'counts', 'reviews', 'myLocal'), rmt_hl_meta(
        'Locals, hosts and travelers in ' . $d['name'] . ' | RuinMyTrip',
        'Meet locals in ' . $d['name'] . ', find travelers on your dates and see who is interested in hosting. Real members only, counted live.',
        '/hosts-locals/destinations/' . $d['slug'], $index) + ['breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Hosts & Locals', 'url' => url('hosts-locals')], ['name' => 'Cities', 'url' => url('hosts-locals/destinations')], ['name' => $d['name'], 'url' => url('hosts-locals/destinations/' . $d['slug'])]]]);
}

/** GET /hosts-locals/how-it-works and /hosts-locals/safety: written pages. */
function hosts_locals_page(array $a): void {
    $page = (string) ($a['page'] ?? 'how-it-works');
    $meta = [
        'how-it-works' => ['How Hosts & Locals works | RuinMyTrip', 'How meeting a local, finding a travel buddy and hosting work on RuinMyTrip, step by step, and what is not open yet.'],
        'safety'       => ['Safety for Hosts & Locals | RuinMyTrip', 'What RuinMyTrip checks, what it does not, how to meet a stranger safely, how to report and block, and what to do in an emergency.'],
    ][$page] ?? null;
    if (!$meta) not_found();
    view('hosts_locals_' . str_replace('-', '_', $page), ['me' => current_user()], rmt_hl_meta($meta[0], $meta[1], '/hosts-locals/' . $page)
        + ['breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Hosts & Locals', 'url' => url('hosts-locals')], ['name' => $page === 'safety' ? 'Safety' : 'How it works', 'url' => url('hosts-locals/' . $page)]]]);
}

function rmt_hl_destinations_for_select(): array {
    return q_all('SELECT id, name, country FROM destinations ORDER BY name');
}

/** GET /hosts-locals/become-a-host */
function hosts_locals_become_host(array $a): void {
    $me = current_user();
    $host = $me ? rmt_host_profile((int) $me['id']) : null;
    view('hosts_locals_become_host', ['me' => $me, 'host' => $host, 'errors' => [], 'old' => $host ?? [],
        'dests' => rmt_hl_destinations_for_select()], rmt_hl_meta(
        'Become a host | RuinMyTrip Hosts & Locals',
        'Offer travelers a free room or couch in your city. How hosting will work on RuinMyTrip, what we review, and how to apply early.',
        '/hosts-locals/become-a-host') + ['breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Hosts & Locals', 'url' => url('hosts-locals')], ['name' => 'Become a host', 'url' => url('hosts-locals/become-a-host')]]]);
}

/** POST /hosts-locals/become-a-host */
function hosts_locals_become_host_submit(array $a): void {
    require_verified_email(); csrf_check();
    $me = current_user();
    if (!can_host_meetups($me)) rmt_adult_gate($me, '/hosts-locals/become-a-host', 'Hosting is for members 18 and over.', '/hosts-locals');
    if (!rmt_rate_ok('host_apply', (string) $me['id'], 10, 3600)) { flash('Try again shortly.'); redirect('/hosts-locals/become-a-host'); }
    $v = rmt_host_validate($_POST);
    if (!$v['ok']) {
        view('hosts_locals_become_host', ['me' => $me, 'host' => rmt_host_profile((int) $me['id']), 'errors' => $v['errors'],
            'old' => $_POST, 'dests' => rmt_hl_destinations_for_select()],
            rmt_hl_meta('Become a host | RuinMyTrip Hosts & Locals', 'Apply to host on RuinMyTrip.', '/hosts-locals/become-a-host', false));
        return;
    }
    $status = rmt_host_save((int) $me['id'], $v['data']);
    if (function_exists('rmt_track')) rmt_track('host_applied');
    flash($status === 'pending'
        ? 'Thank you. Your host profile is with our moderators. Nobody sees it until it is approved, and stay requests open only once identity checks are live.'
        : 'Saved.');
    redirect('/hosts-locals/me');
}

/** GET /hosts-locals/me: the member's own roles, in one place. */
function hosts_locals_me(array $a): void {
    require_login();
    $me = current_user();
    $uid = (int) $me['id'];
    if (function_exists('rmt_profile_ensure')) rmt_profile_ensure($uid);
    $profile = q_one('SELECT * FROM profiles WHERE user_id = ?', [$uid]) ?? [];
    view('hosts_locals_me', ['me' => $me, 'profile' => $profile, 'roles' => rmt_member_roles($uid),
        'host' => rmt_host_profile($uid), 'dests' => rmt_hl_destinations_for_select(), 'errors' => [],
        'incoming' => q_all("SELECT c.from_user_id, c.created_at, u.username, p.display_name, p.avatar_url
                               FROM local_connects c JOIN users u ON u.id = c.from_user_id AND u.status = 'active'
                          LEFT JOIN profiles p ON p.user_id = c.from_user_id
                              WHERE c.to_user_id = ? AND c.state = 'interested' ORDER BY c.created_at DESC LIMIT 20", [$uid])],
        rmt_hl_meta('Your Hosts & Locals profile | RuinMyTrip', 'Manage being a local and a host on RuinMyTrip.', '/hosts-locals/me', false) + ['app_shell' => true]);
}

/** POST /hosts-locals/local: be a local, change what you offer, or stop. */
function hosts_locals_local_submit(array $a): void {
    require_verified_email(); csrf_check();
    $me = current_user();
    if (!can_host_meetups($me)) rmt_adult_gate($me, '/hosts-locals/me', 'Meeting travelers is for members 18 and over.', '/hosts-locals');
    $uid = (int) $me['id'];
    if (function_exists('rmt_profile_ensure')) rmt_profile_ensure($uid);
    if (input('off') === '1') {
        q_run('UPDATE profiles SET open_to_meeting = 0 WHERE user_id = ?', [$uid]);
        flash('You are no longer listed as a local. Your settings are kept for when you switch it back on.');
        redirect('/hosts-locals/me');
    }
    $v = rmt_local_validate($_POST);
    if (!$v['ok']) { flash(implode(' ', $v['errors'])); redirect('/hosts-locals/me#local'); }
    $d = $v['data'];
    q_run("UPDATE profiles SET home_destination_id = ?, open_to_meeting = 1, local_offers = ?, local_availability = ?,
                  home_city = COALESCE(NULLIF(home_city, ''), ?) WHERE user_id = ?",
          [$d['destination_id'], implode(',', $d['offers']), $d['availability'], (string) $d['dest']['name'], $uid]);
    if (function_exists('rmt_track')) rmt_track('local_listed');
    flash('You are listed as a local in ' . $d['dest']['name'] . '. Travelers see your city and what you offer, never where you live. Nobody can message you until you accept.');
    redirect('/hosts-locals/destinations/' . $d['dest']['slug']);
}

/** POST /hosts-locals/host/status: pause, resume or withdraw. */
function hosts_locals_host_status(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $h = q_one('SELECT status FROM host_profiles WHERE user_id = ?', [(int) $me['id']]);
    if (!$h) redirect('/hosts-locals/become-a-host');
    $want = (string) input('status');
    $from = (string) $h['status'];
    $to = null;
    if ($want === 'paused' && $from === 'approved') $to = 'paused';
    elseif ($want === 'approved' && $from === 'paused') $to = 'approved';
    elseif ($want === 'withdrawn' && in_array($from, ['pending', 'approved', 'paused'], true)) $to = 'withdrawn';
    if ($to) q_run('UPDATE host_profiles SET status = ?, updated_at = ? WHERE user_id = ?', [$to, date('Y-m-d H:i:s'), (int) $me['id']]);
    flash($to === 'paused' ? 'Hosting paused. Your profile is hidden until you resume.'
        : ($to === 'approved' ? 'Hosting resumed.' : ($to === 'withdrawn' ? 'Your host profile is withdrawn and hidden.' : 'Nothing changed.')));
    redirect('/hosts-locals/me#host');
}

/** GET /u/{username}/references */
function member_references_page(array $a): void {
    $me = current_user();
    $u = q_one("SELECT u.*, p.display_name, p.avatar_url FROM users u LEFT JOIN profiles p ON p.user_id = u.id
                 WHERE u.username = ? AND u.status = 'active'", [(string) $a['username']]);
    if (!$u) not_found();
    if ($me && rmt_is_blocked((int) $me['id'], (int) $u['id'])) not_found();
    $refs = rmt_refs_for((int) $u['id'], $me ? (int) $me['id'] : null);
    $mine = $me ? q_one('SELECT * FROM member_references WHERE from_user_id = ? AND to_user_id = ? ORDER BY id DESC LIMIT 1', [(int) $me['id'], (int) $u['id']]) : null;
    $why = rmt_ref_blocked_reason($me, $u);
    view('member_references', ['u' => $u, 'refs' => $refs, 'mine' => $mine, 'why' => $why, 'errors' => [], 'old' => $mine ?? [],
        'basis' => $me ? rmt_ref_basis((int) $me['id'], (int) $u['id']) : null],
        rmt_hl_meta('References for @' . $u['username'] . ' | RuinMyTrip', 'What members who met @' . $u['username'] . ' through RuinMyTrip say.',
                    '/u/' . $u['username'] . '/references', count($refs) > 0));
}

/** POST /u/{username}/references */
function member_references_submit(array $a): void {
    require_verified_email(); csrf_check();
    $me = current_user();
    $u = q_one("SELECT u.*, p.display_name, p.avatar_url FROM users u LEFT JOIN profiles p ON p.user_id = u.id
                 WHERE u.username = ? AND u.status = 'active'", [(string) $a['username']]);
    if (!$u) not_found();
    $back = '/u/' . $u['username'] . '/references';
    $v = rmt_ref_validate($_POST);
    if (!$v['ok']) {
        view('member_references', ['u' => $u, 'refs' => rmt_refs_for((int) $u['id'], (int) $me['id']), 'mine' => null,
            'why' => rmt_ref_blocked_reason($me, $u), 'errors' => $v['errors'], 'old' => $_POST,
            'basis' => rmt_ref_basis((int) $me['id'], (int) $u['id'])],
            rmt_hl_meta('References for @' . $u['username'] . ' | RuinMyTrip', 'References.', $back, false));
        return;
    }
    $r = rmt_ref_save($me, $u, $v['data']);
    flash($r['ok'] ? ($r['created'] ? 'Thank you. Your reference is published.' : 'Your reference is updated.') : (string) $r['error']);
    redirect($back);
}

/* ---------- admin ---------- */

/** GET /admin/hosts */
function admin_hosts(array $a): void {
    require_role('admin', 'mod');
    $status = in_array($_GET['status'] ?? '', RMT_HOST_STATUSES, true) ? (string) $_GET['status'] : 'pending';
    $rows = q_all("SELECT h.*, u.username, u.email_verified_at, u.created_at joined_at, u.birthdate, d.name dest_name,
                          (SELECT COUNT(*) FROM reports r WHERE r.target_type = 'user' AND r.target_id = u.id) reports
                     FROM host_profiles h JOIN users u ON u.id = h.user_id
                LEFT JOIN destinations d ON d.id = h.destination_id
                    WHERE h.status = ? ORDER BY h.updated_at", [$status]);
    $counts = [];
    foreach (q_all('SELECT status, COUNT(*) n FROM host_profiles GROUP BY status') as $r) $counts[(string) $r['status']] = (int) $r['n'];
    $growth = [
        'members_7d' => (int) q_one("SELECT COUNT(*) n FROM users WHERE status = 'active' AND created_at >= ?", [date('Y-m-d H:i:s', time() - 7 * 86400)])['n'],
        'locals'     => (int) q_one("SELECT COUNT(*) n FROM profiles p JOIN users u ON u.id = p.user_id AND u.status = 'active' WHERE COALESCE(p.open_to_meeting,0) = 1")['n'],
        'references' => (int) q_one("SELECT COUNT(*) n FROM member_references WHERE status = 'published'")['n'],
        'disputed'   => (int) q_one("SELECT COUNT(*) n FROM reports WHERE target_type = 'reference' AND status = 'open'")['n'],
    ];
    view('admin_hosts', compact('rows', 'status', 'counts', 'growth'), ['title' => 'Host applications | RuinMyTrip admin', 'robots' => 'noindex, nofollow']);
}

/** POST /admin/hosts/decide */
function admin_hosts_decide(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $me = current_user();
    $uid = (int) input('user_id');
    $to = (string) input('decision');
    if (!in_array($to, ['approved', 'rejected', 'paused'], true)) { flash('Unknown decision.'); redirect('/admin/hosts'); }
    $h = q_one('SELECT status FROM host_profiles WHERE user_id = ?', [$uid]);
    if (!$h) { flash('No such application.'); redirect('/admin/hosts'); }
    $note = mb_substr(trim((string) input('note')), 0, 500);
    $now = date('Y-m-d H:i:s');
    q_run('UPDATE host_profiles SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ? WHERE user_id = ?',
          [$to, $note, (int) $me['id'], $now, $now, $uid]);
    q_run('INSERT INTO moderation_log (actor_id, action, target_type, target_id, from_status, to_status, note, created_at) VALUES (?,?,?,?,?,?,?,?)',
          [(int) $me['id'], 'host_' . $to, 'host_profile', $uid, (string) $h['status'], $to, $note, $now]);
    q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
          [$uid, 'host_' . $to, null, 'host_profile', $uid, $now]);
    flash('Saved: ' . $to . '.');
    redirect('/admin/hosts?status=' . rawurlencode((string) $h['status']));
}
