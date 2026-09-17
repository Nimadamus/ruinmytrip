<?php
declare(strict_types=1);

/**
 * Travel buddies: "I am going on this cruise / trip and I want company".
 *
 * Meetups are an evening in a city and trips are one person's dates in one city. Neither holds the
 * most common ask on every travel forum: a whole trip, often a ship rather than a place, looking for
 * somebody to share it with. A buddy post is that ask. People put a hand up with a short note, the
 * poster accepts the ones they want, and acceptance is what lets the two of them message privately.
 * Until then nobody can message anybody, which is the same rule the rest of the site keeps.
 */

const RMT_BUDDY_TYPES = [
    'cruise'      => 'Cruise',
    'trip'        => 'Trip',
    'road_trip'   => 'Road trip',
    'backpacking' => 'Backpacking',
    'festival'    => 'Festival or event',
    'adventure'   => 'Adventure or hiking',
];
const RMT_BUDDY_BUDGETS = ['any' => 'Any budget', 'budget' => 'Budget', 'mid' => 'Mid range', 'luxury' => 'Luxury'];
const RMT_BUDDY_NOTIFY_TYPES = ['buddy_interest', 'buddy_accepted'];

/** @return array{ok:bool, errors:string[], data:array} */
function rmt_buddy_validate(array $in, ?string $today = null): array {
    $today = $today ?? date('Y-m-d');
    $e = [];
    $type = (string) ($in['trip_type'] ?? '');
    if (!isset(RMT_BUDDY_TYPES[$type])) $e[] = 'Pick what kind of trip it is.';
    $title = trim((string) ($in['title'] ?? ''));
    if (mb_strlen($title) < 8 || mb_strlen($title) > 140) $e[] = 'The title needs 8 to 140 characters.';
    $where = trim((string) ($in['where_text'] ?? ''));
    if (mb_strlen($where) < 2 || mb_strlen($where) > 140) $e[] = 'Say where you are going, or which ship and route.';
    $destId = (int) ($in['destination_id'] ?? 0);
    if ($destId > 0 && !q_one('SELECT id FROM destinations WHERE id=?', [$destId])) $destId = 0;
    $from = (string) ($in['date_from'] ?? '');
    $to   = (string) ($in['date_to'] ?? '');
    $isDate = static fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    if (!$isDate($from) || !$isDate($to)) {
        $e[] = 'Both dates are needed. Tick "dates are flexible" if they could move.';
    } else {
        if ($from < $today) $e[] = 'The trip has to start today or later.';
        if ($to < $from) $e[] = 'The trip cannot end before it starts.';
        if ($from > date('Y-m-d', strtotime($today . ' +2 years'))) $e[] = 'Post trips starting within the next two years.';
    }
    $spots = (int) ($in['spots'] ?? 1);
    if ($spots < 1 || $spots > 20) $e[] = 'Looking for between 1 and 20 people.';
    $budget = (string) ($in['budget'] ?? 'any');
    if (!isset(RMT_BUDDY_BUDGETS[$budget])) $budget = 'any';
    $desc = trim((string) ($in['description'] ?? ''));
    if (mb_strlen($desc) < 20 || mb_strlen($desc) > 4000) $e[] = 'Describe the trip and who you are hoping to go with (20 to 4000 characters).';
    if (empty($in['safety_ack'])) $e[] = 'Please confirm the safety terms.';
    return ['ok' => !$e, 'errors' => $e, 'data' => [
        'trip_type' => $type, 'title' => $title, 'where_text' => $where, 'destination_id' => $destId ?: null,
        'date_from' => $from, 'date_to' => $to, 'flexible' => empty($in['flexible']) ? 0 : 1,
        'spots' => $spots, 'budget' => $budget, 'description' => $desc,
    ]];
}

/** Open posts whose trip has not ended, soonest first. */
function rmt_buddy_posts_open(?string $type = null, int $limit = 60): array {
    $sql = "SELECT b.*, d.name dest_name, d.slug dest_slug,
                   (SELECT COUNT(*) FROM buddy_interest i WHERE i.post_id=b.id) interest_count,
                   (SELECT COUNT(*) FROM buddy_interest i WHERE i.post_id=b.id AND i.state='accepted') accepted_count
              FROM buddy_posts b JOIN users u ON u.id=b.user_id AND u.status='active'
              LEFT JOIN destinations d ON d.id=b.destination_id
             WHERE b.status='open' AND b.date_to >= ?";
    $args = [date('Y-m-d')];
    if ($type !== null) { $sql .= ' AND b.trip_type = ?'; $args[] = $type; }
    return q_all($sql . ' ORDER BY b.date_from, b.id LIMIT ' . max(1, $limit), $args);
}

function rmt_buddy_get(int $id): ?array {
    return q_one("SELECT b.*, d.name dest_name, d.slug dest_slug FROM buddy_posts b
                  LEFT JOIN destinations d ON d.id=b.destination_id WHERE b.id=?", [$id]);
}

/** Accepted on each other's buddy post, in either direction. Used by rmt_message_allowed(). */
function rmt_buddy_mutual(int $a, int $b): bool {
    return (bool) q_one("SELECT 1 FROM buddy_interest i JOIN buddy_posts p ON p.id=i.post_id
                          WHERE i.state='accepted'
                            AND ((p.user_id=? AND i.user_id=?) OR (p.user_id=? AND i.user_id=?)) LIMIT 1",
                        [$a, $b, $b, $a]);
}

function rmt_buddy_notify(int $userId, string $type, int $actorId, int $postId): void {
    if ($userId <= 0 || $userId === $actorId || !in_array($type, RMT_BUDDY_NOTIFY_TYPES, true)) return;
    q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
          [$userId, $type, $actorId, 'buddy', $postId, date('Y-m-d H:i:s')]);
}

/**
 * Put a hand up, or take it back. Returns what happened so the controller can say it.
 * @return array{ok:bool, action?:string, error?:string}
 */
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

/* ---------- controllers ---------- */

function buddies_index(array $a): void {
    $type = isset($a['type']) ? str_replace('-', '_', (string) $a['type']) : null;
    if ($type !== null && !isset(RMT_BUDDY_TYPES[$type])) not_found();
    $posts = rmt_buddy_posts_open($type);
    $authors = authors_by_ids(array_column($posts, 'user_id'));
    foreach ($posts as &$p) $p['author'] = $authors[(int) $p['user_id']] ?? null; unset($p);
    $me = current_user();
    $label = $type ? RMT_BUDDY_TYPES[$type] : null;
    $path = $type ? 'buddies/' . str_replace('_', '-', $type) : 'buddies';
    $crumbs = [['name' => 'Home', 'url' => url()], ['name' => 'Travel buddies', 'url' => url('buddies')]];
    if ($type) $crumbs[] = ['name' => $label, 'url' => url($path)];
    view('buddies_index', compact('posts', 'me', 'type', 'label'), [
        'title' => $type === 'cruise' ? 'Find a cruise buddy: travelers looking for cruise companions'
                 : ($type ? 'Find a ' . strtolower($label) . ' buddy | RuinMyTrip' : 'Find a travel buddy: people looking for cruise and trip companions'),
        'description' => 'Members posting the cruise or trip they are taking and the kind of company they want. Put your hand up, and once the poster accepts you can message each other. 18+, free.',
        'canonical' => url($path),
        'breadcrumbs' => $crumbs,
    ]);
}

function buddy_new_form(array $a): void {
    require_login();
    if (!can_host_meetups(current_user())) { flash('Travel buddies is 18+.'); redirect('/buddies'); }
    $pre = [];
    $t = (string) input('type');
    if (isset(RMT_BUDDY_TYPES[$t])) $pre['trip_type'] = $t;
    view('buddy_new', ['dests' => all_dests(), 'errors' => [], 'b' => $pre], [
        'title' => 'Find a travel buddy | RuinMyTrip',
        'description' => 'Post the cruise or trip you are taking and who you would like to go with.',
    ]);
}

function buddy_create(array $a): void {
    require_verified_email(); csrf_check(); $me = current_user();
    if (!can_host_meetups($me)) { flash('Travel buddies is 18+.'); redirect('/buddies'); }
    $opts = ['title' => 'Find a travel buddy | RuinMyTrip'];
    if (!rmt_submit_ok('buddy_new', input('_submit'))) { flash('That post was already published.'); redirect('/buddies'); return; }
    if (!rmt_rate_ok('buddy_create', (string) $me['id'], 5, 3600)) {
        view('buddy_new', ['dests' => all_dests(), 'errors' => ['You are posting very fast. Try again later.'], 'b' => $_POST], $opts);
        return;
    }
    $v = rmt_buddy_validate($_POST);
    if (!$v['ok']) { view('buddy_new', ['dests' => all_dests(), 'errors' => $v['errors'], 'b' => $_POST], $opts); return; }
    $d = $v['data'];
    $id = (int) q_run("INSERT INTO buddy_posts (user_id,trip_type,title,where_text,destination_id,date_from,date_to,
                                                flexible,spots,budget,description,status,created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?, 'open', ?)",
        [(int) $me['id'], $d['trip_type'], $d['title'], $d['where_text'], $d['destination_id'], $d['date_from'],
         $d['date_to'], $d['flexible'], $d['spots'], $d['budget'], $d['description'], date('Y-m-d H:i:s')]);
    flash('Posted. You will get a notification when somebody wants to come along.');
    redirect('/buddy/' . $id);
}

function buddy_show(array $a): void {
    $b = rmt_buddy_get((int) $a['id']);
    if (!$b || !in_array($b['status'], ['open', 'closed'], true)) not_found();
    $b['author'] = author((int) $b['user_id']);
    if (!$b['author']) not_found();
    $me = current_user();
    $isOwner = $me && (int) $me['id'] === (int) $b['user_id'];
    $interest = q_all("SELECT i.*, u.username, p.avatar_url, p.display_name FROM buddy_interest i
                       JOIN users u ON u.id=i.user_id AND u.status='active' LEFT JOIN profiles p ON p.user_id=u.id
                       WHERE i.post_id=? ORDER BY i.created_at", [(int) $b['id']]);
    $mine = null;
    if ($me) foreach ($interest as $i) if ((int) $i['user_id'] === (int) $me['id']) $mine = $i;
    $accepted = count(array_filter($interest, static fn($i) => $i['state'] === 'accepted'));
    $isPast = (string) $b['date_to'] < date('Y-m-d');
    $hostStats = rmt_profile_stats((int) $b['user_id']);
    view('buddy_show', compact('b', 'me', 'isOwner', 'interest', 'mine', 'accepted', 'isPast', 'hostStats'), [
        'title' => $b['title'] . ' | Travel buddy wanted',
        'description' => mb_substr(RMT_BUDDY_TYPES[$b['trip_type']] . ': ' . $b['where_text'] . '. ' . $b['description'], 0, 155),
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Travel buddies', 'url' => url('buddies')],
                          ['name' => $b['title'], 'url' => url('buddy/' . (int) $b['id'])]],
    ]);
}

function buddy_interest(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $id = (int) $a['id'];
    if (!can_host_meetups($me)) { flash('Travel buddies is 18+.'); redirect('/buddy/' . $id); }
    $b = rmt_buddy_get($id); if (!$b) not_found();
    if (!rmt_rate_ok('buddy_interest', (string) $me['id'], 30, 3600)) { flash('Slow down a little and try again soon.'); redirect('/buddy/' . $id); }
    $r = rmt_buddy_toggle_interest($b, (int) $me['id'], (string) input('note'));
    flash(!$r['ok'] ? $r['error'] : ($r['action'] === 'interested'
        ? 'Sent. @' . (author((int) $b['user_id'])['username'] ?? 'the poster') . ' will see your note, and you can message each other once they accept.'
        : 'You took your hand back down.'));
    redirect('/buddy/' . $id);
}

function buddy_decide(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $b = rmt_buddy_get((int) $a['id']); if (!$b) not_found();
    rmt_buddy_decide($b, (int) $me['id'], (int) $a['user_id'], (string) input('answer'));
    redirect('/buddy/' . (int) $b['id']);
}

function buddy_status(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $b = rmt_buddy_get((int) $a['id']); if (!$b) not_found();
    $to = (string) input('status');
    if ((int) $me['id'] === (int) $b['user_id'] && in_array($to, ['open', 'closed', 'removed'], true)) {
        q_run('UPDATE buddy_posts SET status=?, updated_at=? WHERE id=?', [$to, date('Y-m-d H:i:s'), (int) $b['id']]);
        if ($to === 'removed') { flash('Post removed.'); redirect('/buddies'); }
    }
    redirect('/buddy/' . (int) $b['id']);
}
