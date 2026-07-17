<?php
declare(strict_types=1);

/* ---------- helpers shared by controllers ---------- */
function dest_by_slug(string $slug): ?array { return q_one('SELECT * FROM destinations WHERE slug = ?', [$slug]); }
function dest_by_id(int $id): ?array { return q_one('SELECT * FROM destinations WHERE id = ?', [$id]); }
function all_dests(): array { return q_all('SELECT * FROM destinations ORDER BY name'); }
function author(int $uid): ?array {
    return q_one('SELECT u.id,u.username,u.role,p.display_name,p.avatar_url,p.credibility_score
                  FROM users u LEFT JOIN profiles p ON p.user_id=u.id WHERE u.id=?', [$uid]);
}
function stars(int $n): string { return str_repeat('★', $n) . str_repeat('☆', 5 - $n); }
function not_found(): void { http_response_code(404); view('404', [], ['title'=>'Not found — RuinMyTrip']); exit; }

/* ---------- public pages ---------- */
function home(array $a): void {
    $trending = q_all('SELECT d.*, (SELECT COUNT(*) FROM trips t WHERE t.destination_id=d.id) AS trips
                       FROM destinations d ORDER BY trips DESC, d.name LIMIT 6');
    $stories = q_all("SELECT t.*, d.name dest_name, d.slug dest_slug FROM trips t
                      LEFT JOIN destinations d ON d.id=t.destination_id
                      WHERE t.status='published' ORDER BY t.created_at DESC, t.id DESC LIMIT 4");
    $reviews = q_all("SELECT r.*, d.slug dest_slug FROM reviews r
                      LEFT JOIN destinations d ON d.id=r.destination_id
                      WHERE r.status='published' ORDER BY r.verified DESC, r.id DESC LIMIT 4");
    $meetups = q_all("SELECT m.*, d.name dest_name, d.slug dest_slug FROM meetups m
                      LEFT JOIN destinations d ON d.id=m.destination_id
                      WHERE m.status='published' ORDER BY m.date_start ASC LIMIT 3");
    $guides = q_all("SELECT g.*, d.name dest_name FROM guides g
                     LEFT JOIN destinations d ON d.id=g.destination_id
                     WHERE g.status='published' ORDER BY g.id DESC LIMIT 3");
    foreach ($stories as &$s) $s['author'] = author((int)$s['user_id']); unset($s);
    foreach ($reviews as &$r) $r['author'] = author((int)$r['user_id']); unset($r);
    // Real total, not count($trending) — $trending is LIMIT 6 and would print "6" forever.
    $stat_destinations = (int)(q_one('SELECT COUNT(*) c FROM destinations')['c'] ?? 0);
    view('home', compact('trending','stories','reviews','meetups','guides','stat_destinations'), [
        'title' => 'RuinMyTrip — Real trips, honest reviews, safe travel meetups',
        'description' => 'Join a trustworthy travel community. Share trips, review destinations and stays, follow travelers you trust, and find safe public meetups — RuinMyTrip.',
        'jsonld' => jsonld(['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'RuinMyTrip','url'=>cfg('app_url'),
            'potentialAction'=>['@type'=>'SearchAction','target'=>url('search?q={q}'),'query-input'=>'required name=q']]),
    ]);
}

function explore(array $a): void {
    $qs = trim((string)($_GET['q'] ?? '')); $cat = trim((string)($_GET['category'] ?? ''));
    $sql = 'SELECT d.*, (SELECT COUNT(*) FROM reviews r WHERE r.destination_id=d.id) reviews,
            (SELECT COUNT(*) FROM trips t WHERE t.destination_id=d.id) trips FROM destinations d WHERE 1=1';
    $args = [];
    if ($qs !== '') { $sql .= ' AND (d.name LIKE ? OR d.country LIKE ? OR d.summary LIKE ?)'; $args[]="%$qs%";$args[]="%$qs%";$args[]="%$qs%"; }
    if ($cat !== '') { $sql .= ' AND d.category = ?'; $args[] = $cat; }
    $sql .= ' ORDER BY d.name';
    $dests = q_all($sql, $args);
    $cats = q_all('SELECT DISTINCT category FROM destinations WHERE category IS NOT NULL ORDER BY category');
    view('explore', compact('dests','cats','qs','cat'), [
        'title' => 'Explore destinations — RuinMyTrip',
        'description' => 'Browse traveler-reviewed destinations. Filter by style — culture, adventure, nature, food, city.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Explore','url'=>url('explore')]],
    ]);
}

function destination(array $a): void {
    $d = dest_by_slug($a['slug']); if (!$d) not_found();
    $id = (int)$d['id'];
    $trips = q_all("SELECT t.* FROM trips t WHERE t.destination_id=? AND t.status='published' ORDER BY t.id DESC LIMIT 8", [$id]);
    foreach ($trips as &$t) $t['author'] = author((int)$t['user_id']); unset($t);
    $reviews = q_all("SELECT r.* FROM reviews r WHERE r.destination_id=? AND r.status='published' ORDER BY r.verified DESC, r.id DESC", [$id]);
    foreach ($reviews as &$r) $r['author'] = author((int)$r['user_id']); unset($r);
    $guides = q_all("SELECT g.* FROM guides g WHERE g.destination_id=? AND g.status='published' ORDER BY g.id DESC", [$id]);
    $meetups = q_all("SELECT m.* FROM meetups m WHERE m.destination_id=? AND m.status='published' ORDER BY m.date_start", [$id]);
    $going = q_all("SELECT g.*, u.username, p.avatar_url, p.display_name FROM going g
                    JOIN users u ON u.id=g.user_id LEFT JOIN profiles p ON p.user_id=u.id
                    WHERE g.destination_id=? AND g.visibility='public' ORDER BY g.date_from", [$id]);
    $avg = q_one("SELECT ROUND(AVG(rating),1) a, COUNT(*) c FROM reviews WHERE destination_id=? AND status='published'", [$id]);
    view('destination', compact('d','trips','reviews','guides','meetups','going','avg'), [
        'title' => $d['name'].', '.$d['country'].' — travel guide, reviews & meetups | RuinMyTrip',
        'description' => $d['summary'],
        'og_image' => $d['hero_url'],
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Explore','url'=>url('explore')],['name'=>$d['name'],'url'=>url('d/'.$d['slug'])]],
        'jsonld' => jsonld(['@context'=>'https://schema.org','@type'=>'TouristDestination','name'=>$d['name'],
            'description'=>$d['summary'],'url'=>url('d/'.$d['slug']),
            'geo'=>['@type'=>'GeoCoordinates','latitude'=>$d['lat'],'longitude'=>$d['lng']],
            'aggregateRating'=>$avg && $avg['c']>0 ? ['@type'=>'AggregateRating','ratingValue'=>$avg['a'],'reviewCount'=>$avg['c']] : null]),
    ]);
}

function profile(array $a): void {
    $u = q_one('SELECT u.*, p.display_name,p.bio,p.home_city,p.avatar_url,p.cover_url,p.credibility_score
                FROM users u LEFT JOIN profiles p ON p.user_id=u.id WHERE u.username=?', [$a['username']]);
    if (!$u) not_found();
    $uid = (int)$u['id'];
    $me = current_user();
    $isMe = $me && (int)$me['id'] === $uid;

    $trips = q_all("SELECT t.*, d.name dest_name FROM trips t LEFT JOIN destinations d ON d.id=t.destination_id
                    WHERE t.user_id=? AND t.status='published' ORDER BY t.id DESC", [$uid]);
    $reviews = q_all("SELECT * FROM reviews WHERE user_id=? AND status='published' ORDER BY id DESC", [$uid]);
    $guides = q_all("SELECT * FROM guides WHERE user_id=? AND status='published' ORDER BY id DESC", [$uid]);

    // Every stat is a live COUNT — never a stored counter, never a placeholder.
    $stats = rmt_profile_stats($uid);
    $followers = $stats['followers'];
    $following = $stats['following'];
    $badges = rmt_user_badges($uid);

    $is_following = $me ? (bool) q_one('SELECT 1 FROM follows WHERE follower_id=? AND followee_id=?', [(int)$me['id'],$uid]) : false;
    view('profile', compact('u','trips','reviews','guides','followers','following','is_following','me','stats','badges','isMe'), [
        'title' => ($u['display_name'] ?: $u['username']).' (@'.$u['username'].') — RuinMyTrip',
        'description' => $u['bio'] ?: ('Traveler profile for @'.$u['username'].' on RuinMyTrip.'),
        'og_image' => $u['avatar_url'] ?: url('assets/img/og-default.svg'),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'@'.$u['username'],'url'=>url('u/'.$u['username'])]],
        'jsonld' => jsonld(['@context'=>'https://schema.org','@type'=>'ProfilePage',
            'mainEntity'=>['@type'=>'Person','name'=>$u['display_name'] ?: $u['username'],
                           'alternateName'=>'@'.$u['username'],
                           'description'=>$u['bio'] ?: null,
                           'url'=>url('u/'.$u['username'])]]),
    ]);
}

/** GET /u/{username}/followers */
function profile_followers(array $a): void {
    $u = q_one('SELECT id, username FROM users WHERE username = ?', [$a['username']]);
    if (!$u) not_found();
    $people = rmt_followers((int)$u['id']);
    view('people_list', ['u'=>$u, 'people'=>$people, 'mode'=>'followers', 'me'=>current_user()], [
        'title' => 'Followers of @'.$u['username'].' — RuinMyTrip',
        'description' => 'Travelers following @'.$u['username'].' on RuinMyTrip.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'@'.$u['username'],'url'=>url('u/'.$u['username'])],
                          ['name'=>'Followers','url'=>url('u/'.$u['username'].'/followers')]],
    ]);
}

/** GET /u/{username}/following */
function profile_following(array $a): void {
    $u = q_one('SELECT id, username FROM users WHERE username = ?', [$a['username']]);
    if (!$u) not_found();
    $people = rmt_following((int)$u['id']);
    view('people_list', ['u'=>$u, 'people'=>$people, 'mode'=>'following', 'me'=>current_user()], [
        'title' => 'Travelers @'.$u['username'].' follows — RuinMyTrip',
        'description' => 'Travelers followed by @'.$u['username'].' on RuinMyTrip.',
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'@'.$u['username'],'url'=>url('u/'.$u['username'])],
                          ['name'=>'Following','url'=>url('u/'.$u['username'].'/following')]],
    ]);
}

/** GET /u/{username}/edit — owner only. The canonical edit-profile page. */
function profile_edit_form(array $a): void {
    require_login();
    $me = current_user();
    if ($me['username'] !== $a['username']) { http_response_code(403); exit('403 — you can only edit your own profile.'); }
    view('profile_edit', ['me'=>$me, 'errors'=>[], 'p'=>$me], ['title'=>'Edit your profile — RuinMyTrip']);
}

/** POST /u/{username}/edit */
function profile_edit_submit(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    if ($me['username'] !== $a['username']) { http_response_code(403); exit('403 — you can only edit your own profile.'); }

    $v = rmt_profile_validate($_POST);
    if (!$v['ok']) {
        view('profile_edit', ['me'=>$me, 'errors'=>$v['errors'], 'p'=>array_merge($me, $_POST)],
             ['title'=>'Edit your profile — RuinMyTrip']); return;
    }
    $d = $v['data'];

    // An uploaded photo wins over the URL field.
    if (!empty($_FILES['avatar']['name'] ?? '')) {
        $res = rmt_upload_image($_FILES['avatar'], (int)$me['id']);
        if (!$res['ok']) {
            view('profile_edit', ['me'=>$me, 'errors'=>[$res['error']], 'p'=>array_merge($me, $_POST)],
                 ['title'=>'Edit your profile — RuinMyTrip']); return;
        }
        $d['avatar_url'] = $res['url'];
        $old = q_one('SELECT avatar_key FROM profiles WHERE user_id=?', [(int)$me['id']]);
        db()->prepare('UPDATE profiles SET avatar_key=? WHERE user_id=?')->execute([$res['key'], (int)$me['id']]);
        if (!empty($old['avatar_key'])) rmt_storage_delete((string)$old['avatar_key']);
    }

    db()->prepare('UPDATE profiles SET display_name=?, bio=?, home_city=?, avatar_url=? WHERE user_id=?')
        ->execute([$d['display_name'], $d['bio'], $d['home_city'], $d['avatar_url'], (int)$me['id']]);
    flash('Profile updated.');
    redirect('/u/'.$me['username']);
}

function feed(array $a): void {
    require_login(); $me = current_user(); $uid = (int)$me['id'];
    $items = q_all("SELECT t.*, d.name dest_name, d.slug dest_slug FROM trips t
                    LEFT JOIN destinations d ON d.id=t.destination_id
                    WHERE t.status='published' AND (t.user_id=? OR t.user_id IN (SELECT followee_id FROM follows WHERE follower_id=?))
                    ORDER BY t.created_at DESC, t.id DESC LIMIT 40", [$uid,$uid]);
    foreach ($items as &$t) $t['author'] = author((int)$t['user_id']); unset($t);
    view('feed', compact('items','me'), ['title'=>'Your feed — RuinMyTrip','description'=>'Latest trips from travelers you follow.']);
}

function trip_show(array $a): void {
    $t = q_one("SELECT t.*, d.name dest_name, d.slug dest_slug FROM trips t
                LEFT JOIN destinations d ON d.id=t.destination_id WHERE t.id=?", [(int)$a['id']]);
    if (!$t || $t['status']!=='published') not_found();
    $t['author'] = author((int)$t['user_id']);
    $photos = q_all('SELECT * FROM trip_photos WHERE trip_id=? ORDER BY sort,id', [(int)$t['id']]);
    $comments = q_all("SELECT c.*, u.username, p.avatar_url FROM comments c JOIN users u ON u.id=c.user_id
                       LEFT JOIN profiles p ON p.user_id=u.id
                       WHERE c.target_type='trip' AND c.target_id=? AND c.status='published' ORDER BY c.id", [(int)$t['id']]);
    view('trip_show', compact('t','photos','comments'), [
        'title' => $t['title'].' — RuinMyTrip',
        'description' => mb_substr(strip_tags((string)$t['body']),0,150),
        'og_image' => $t['cover_url'] ?: url('assets/img/og-default.svg'),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>$t['dest_name']?:'Trips','url'=>$t['dest_slug']?url('d/'.$t['dest_slug']):url('explore')],['name'=>$t['title'],'url'=>url('trip/'.$t['id'])]],
        'jsonld' => jsonld(['@context'=>'https://schema.org','@type'=>'Article','headline'=>$t['title'],
            'datePublished'=>$t['created_at'],'author'=>['@type'=>'Person','name'=>$t['author']['display_name']??$t['author']['username']]]),
    ]);
}

function reviews_index(array $a): void {
    $me = current_user();
    $mine = input('mine') === '1';
    $cat  = input('category');

    if ($mine) {
        // A user's own reviews INCLUDING drafts — the only place drafts are listed.
        require_login();
        $reviews = q_all("SELECT r.*, d.name dest_name, d.slug dest_slug FROM reviews r
                          LEFT JOIN destinations d ON d.id=r.destination_id
                          WHERE r.user_id = ? AND r.status <> 'removed'
                          ORDER BY CASE r.status WHEN 'draft' THEN 0 ELSE 1 END, r.id DESC",
                         [(int)$me['id']]);
    } else {
        $sql = "SELECT r.*, d.name dest_name, d.slug dest_slug FROM reviews r
                LEFT JOIN destinations d ON d.id=r.destination_id
                WHERE r.status='published'";
        $args = [];
        if (in_array($cat, RMT_REVIEW_CATEGORIES, true)) { $sql .= ' AND r.subject_type = ?'; $args[] = $cat; }
        $sql .= ' ORDER BY r.id DESC LIMIT 50';
        $reviews = q_all($sql, $args);
    }
    foreach ($reviews as &$r) $r['author'] = author((int)$r['user_id']); unset($r);
    view('reviews_index', compact('reviews','mine','cat','me'), [
        'title'=>$mine ? 'Your reviews — RuinMyTrip' : 'Traveler reviews — RuinMyTrip',
        'description'=>'Honest traveler reviews of destinations, hotels, restaurants, attractions and experiences.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Reviews','url'=>url('reviews')]],
    ]);
}

function guides_index(array $a): void {
    $guides = q_all("SELECT g.*, d.name dest_name FROM guides g LEFT JOIN destinations d ON d.id=g.destination_id
                     WHERE g.status='published' ORDER BY g.id DESC");
    foreach ($guides as &$g) $g['author'] = author((int)$g['user_id']); unset($g);
    view('guides_index', compact('guides'), [
        'title'=>'Travel guides & itineraries — RuinMyTrip',
        'description'=>'Detailed, traveler-written guides and day-by-day itineraries you can trust.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Guides','url'=>url('guides')]],
    ]);
}

function guide_show(array $a): void {
    $g = q_one("SELECT g.*, d.name dest_name, d.slug dest_slug FROM guides g
                LEFT JOIN destinations d ON d.id=g.destination_id WHERE g.slug=?", [$a['slug']]);
    if (!$g || $g['status']!=='published') not_found();
    $g['author'] = author((int)$g['user_id']);
    view('guide_show', compact('g'), [
        'title'=>$g['title'].' — RuinMyTrip',
        'description'=>$g['summary'],
        'og_image'=>$g['cover_url'] ?: url('assets/img/og-default.svg'),
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Guides','url'=>url('guides')],['name'=>$g['title'],'url'=>url('g/'.$g['slug'])]],
        'jsonld'=>jsonld(['@context'=>'https://schema.org','@type'=>'Article','headline'=>$g['title'],'datePublished'=>$g['created_at']]),
    ]);
}

function meetups_index(array $a): void {
    $meetups = q_all("SELECT m.*, d.name dest_name, d.slug dest_slug,
                      (SELECT COUNT(*) FROM meetup_rsvps r WHERE r.meetup_id=m.id AND r.status='going') going
                      FROM meetups m LEFT JOIN destinations d ON d.id=m.destination_id
                      WHERE m.status='published' ORDER BY m.date_start");
    foreach ($meetups as &$m) $m['host'] = author((int)$m['host_id']); unset($m);
    view('meetups_index', compact('meetups'), [
        'title'=>'Public travel meetups — RuinMyTrip',
        'description'=>'Optional, public, safety-first travel meetups. Meet fellow travelers in a destination — never dating, never precise location sharing.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Meetups','url'=>url('meetups')]],
    ]);
}

function meetup_show(array $a): void {
    $m = q_one("SELECT m.*, d.name dest_name, d.slug dest_slug FROM meetups m
                LEFT JOIN destinations d ON d.id=m.destination_id WHERE m.id=?", [(int)$a['id']]);
    if (!$m || $m['status']!=='published') not_found();
    $m['host'] = author((int)$m['host_id']);
    $rsvps = q_all("SELECT r.*, u.username, p.avatar_url, p.display_name FROM meetup_rsvps r
                    JOIN users u ON u.id=r.user_id LEFT JOIN profiles p ON p.user_id=u.id
                    WHERE r.meetup_id=? AND r.status='going'", [(int)$m['id']]);
    $me = current_user();
    $mine = $me ? (bool) q_one('SELECT 1 FROM meetup_rsvps WHERE meetup_id=? AND user_id=?', [(int)$m['id'],(int)$me['id']]) : false;
    view('meetup_show', compact('m','rsvps','me','mine'), [
        'title'=>$m['title'].' — RuinMyTrip meetup',
        'description'=>mb_substr((string)$m['description'],0,150),
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>'Meetups','url'=>url('meetups')],['name'=>$m['title'],'url'=>url('meetup/'.$m['id'])]],
    ]);
}

function going_index(array $a): void {
    $rows = q_all("SELECT g.*, d.name dest_name, d.slug dest_slug, u.username, p.avatar_url, p.display_name
                   FROM going g JOIN destinations d ON d.id=g.destination_id JOIN users u ON u.id=g.user_id
                   LEFT JOIN profiles p ON p.user_id=u.id
                   WHERE g.visibility='public' ORDER BY g.date_from");
    view('going_index', compact('rows'), [
        'title'=>"Who's going — find travelers by destination & date | RuinMyTrip",
        'description'=>'Discover travelers heading to the same destination in your date range. Destination and date-range only — never precise location.',
        'breadcrumbs'=>[['name'=>'Home','url'=>url()],['name'=>"Who's going",'url'=>url('going')]],
    ]);
}

function search(array $a): void {
    $qs = trim((string)($_GET['q'] ?? ''));
    $dests=$trips=$guides=[];
    if ($qs !== '') {
        $like = "%$qs%";
        $dests = q_all('SELECT * FROM destinations WHERE name LIKE ? OR country LIKE ? OR summary LIKE ? LIMIT 10', [$like,$like,$like]);
        $trips = q_all("SELECT t.*,d.slug dest_slug FROM trips t LEFT JOIN destinations d ON d.id=t.destination_id WHERE t.status='published' AND (t.title LIKE ? OR t.body LIKE ?) LIMIT 10", [$like,$like]);
        $guides = q_all("SELECT * FROM guides WHERE status='published' AND (title LIKE ? OR summary LIKE ?) LIMIT 10", [$like,$like]);
    }
    view('search', compact('qs','dests','trips','guides'), [
        'title'=>($qs!==''?('Search: '.$qs.' — '):'Search — ').'RuinMyTrip',
        'description'=>'Search destinations, trips, and guides across RuinMyTrip.',
    ]);
}

function notifications(array $a): void {
    require_login(); $me = current_user();
    $items = q_all("SELECT n.*, u.username actor FROM notifications n LEFT JOIN users u ON u.id=n.actor_id
                    WHERE n.user_id=? ORDER BY n.id DESC LIMIT 50", [(int)$me['id']]);
    db()->prepare("UPDATE notifications SET read_at=? WHERE user_id=? AND read_at IS NULL")->execute([date('Y-m-d H:i:s'),(int)$me['id']]);
    view('notifications', compact('items','me'), ['title'=>'Notifications — RuinMyTrip','description'=>'Your RuinMyTrip activity.']);
}

/* ---------- forms & writes ---------- */
function trip_new_form(array $a): void {
    require_login();
    view('trip_new', ['dests'=>all_dests(),'errors'=>[]], ['title'=>'Share a trip — RuinMyTrip','description'=>'Post a trip story with photos.']);
}
function trip_create(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $title = input('title'); $body = input('body'); $dest = (int)input('destination_id');
    $cover = trim((string) input('cover_url')); $visited = input('visited_on');
    $errors = [];
    if (!rmt_rate_ok('trip_create', (string)$me['id'], 20, 3600)) $errors[] = 'You are posting very fast. Try again later.';
    if (strlen($title) < 5) $errors[] = 'Give your trip a title (5+ characters).';
    if (strlen($body) < 20) $errors[] = 'Add a bit more to your story (20+ characters).';
    if (mb_strlen($title) > 140) $errors[] = 'That title is too long.';
    if (mb_strlen($body) > 20000) $errors[] = 'That story is too long.';
    // Same restriction as profile photos: an unvalidated URL rendered into <img src> is a
    // javascript:/data: delivery vector.
    if ($cover !== '' && (!filter_var($cover, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $cover))) {
        $errors[] = 'Cover photo URL must be a full https:// web address.';
    }
    if ($dest > 0 && !dest_by_id($dest)) $errors[] = 'That destination does not exist.';
    if ($visited !== '' && (strtotime($visited) === false || strtotime($visited) > time())) {
        $errors[] = 'That trip date is not valid.';
    }
    if ($errors) { view('trip_new', ['dests'=>all_dests(),'errors'=>$errors], ['title'=>'Share a trip — RuinMyTrip']); return; }
    $d = $dest ? dest_by_id($dest) : null;
    $cover = $cover ?: ($d['hero_url'] ?? '');
    $id = q_run("INSERT INTO trips (user_id,destination_id,title,slug,body,cover_url,visited_on,verified,status,created_at)
                 VALUES (?,?,?,?,?,?,?,?, 'published', ?)",
        [(int)$me['id'], $dest ?: null, $title, slugify($title), $body, $cover, $visited ?: null, 0, date('Y-m-d H:i:s')]);
    flash('Trip published.');
    redirect('/trip/'.$id.'/'.slugify($title));
}

function review_new_form(array $a): void {
    require_login();
    view('review_new', ['dests'=>all_dests(), 'errors'=>[], 'r'=>null],
         ['title'=>'Write a review — RuinMyTrip']);
}

function review_create(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    if (!rmt_rate_ok('review_create', (string)$me['id'], 20, 3600)) {
        view('review_new', ['dests'=>all_dests(), 'errors'=>['You are posting very fast. Try again later.'], 'r'=>null],
             ['title'=>'Write a review — RuinMyTrip']); return;
    }
    $isDraft = input('action') === 'draft';
    $v = rmt_review_validate($_POST, $isDraft);
    if (!$v['ok']) {
        view('review_new', ['dests'=>all_dests(), 'errors'=>$v['errors'], 'r'=>$_POST],
             ['title'=>'Write a review — RuinMyTrip']); return;
    }
    $d = $v['data'];
    $now = date('Y-m-d H:i:s');
    $status = $isDraft ? 'draft' : 'published';
    $id = (int) q_run("INSERT INTO reviews
        (user_id,destination_id,subject_type,subject_name,rating,title,body,what_great,what_ruined,
         visited_on,safety_rating,value_rating,verified,status,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?)",
        [(int)$me['id'], $d['destination_id'], $d['subject_type'], $d['subject_name'], $d['rating'],
         $d['title'], $d['body'], $d['what_great'], $d['what_ruined'], $d['visited_on'],
         $d['safety_rating'], $d['value_rating'], $status, $now, $now]);

    $slug = rmt_review_slug($d + ['id'=>$id]);
    db()->prepare('UPDATE reviews SET slug = ? WHERE id = ?')->execute([$slug, $id]);

    // Photo failures must never be silent: the review still publishes (losing written text
    // because one image failed would be worse), but the user is told exactly what happened.
    $photoErrors = rmt_attach_review_photos($id, (int)$me['id']);

    // Badges are evaluated against real activity, never granted by hand.
    if (!$isDraft) rmt_award_badges((int)$me['id']);

    $msg = $isDraft ? 'Draft saved. Only you can see it.' : 'Review published.';
    if ($photoErrors) $msg .= ' Some photos were not added: ' . implode(' ', array_unique($photoErrors));
    flash($msg);
    redirect($isDraft ? '/reviews?mine=1' : '/review/'.$id.'/'.$slug);
}

/**
 * Store any photos submitted with a review. Upload failures are reported to the user but never
 * discard the review itself — losing written text because one image failed would be worse than
 * a missing photo.
 * @return string[] error messages
 */
function rmt_attach_review_photos(int $reviewId, int $ownerId): array {
    $errors = [];
    if (empty($_FILES['photos']) || !is_array($_FILES['photos']['name'] ?? null)) return $errors;

    $existing = (int)(q_one('SELECT COUNT(*) c FROM review_photos WHERE review_id=?', [$reviewId])['c'] ?? 0);
    $slots = max(0, 6 - $existing);   // cap photos per review

    $n = count($_FILES['photos']['name']);
    for ($i = 0; $i < $n; $i++) {
        if ((int)$_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($slots <= 0) { $errors[] = 'You can attach up to 6 photos per review.'; break; }
        if (!rmt_rate_ok('upload', (string)$ownerId, 40, 3600)) { $errors[] = 'Too many uploads. Try again later.'; break; }

        $file = [
            'name'     => $_FILES['photos']['name'][$i],
            'type'     => $_FILES['photos']['type'][$i],
            'tmp_name' => $_FILES['photos']['tmp_name'][$i],
            'error'    => $_FILES['photos']['error'][$i],
            'size'     => $_FILES['photos']['size'][$i],
        ];
        $res = rmt_upload_image($file, $ownerId);
        if (!$res['ok']) { $errors[] = $res['error']; continue; }

        q_run('INSERT INTO review_photos (review_id, url, storage_key, caption, width, height, bytes, sort, created_at)
               VALUES (?,?,?,?,?,?,?,?,?)',
              [$reviewId, $res['url'], $res['key'], null, $res['w'], $res['h'], $res['bytes'],
               $existing + $i, date('Y-m-d H:i:s')]);
        $slots--;
    }
    return $errors;
}

/** GET /review/{id}/{slug} — public permalink. */
function review_show(array $a): void {
    $r = rmt_review_get((int)$a['id']);
    if (!$r) not_found();
    $me = current_user();
    if (!rmt_review_can_view($r, $me)) not_found();

    // Canonicalise: a wrong or missing slug redirects to the real one rather than serving the
    // same content on many URLs.
    $slug = $r['slug'] ?: rmt_review_slug($r);
    if (($a['slug'] ?? '') !== $slug) redirect(rmt_review_path($r));

    $author = author((int)$r['user_id']);
    $photos = q_all('SELECT * FROM review_photos WHERE review_id = ? ORDER BY sort, id', [(int)$r['id']]);
    // No robots directive: a draft/hidden review 404s for anyone but its author (see
    // rmt_review_can_view), so crawlers cannot reach it. Access control, not noindex.
    view('review_show', compact('r','author','photos','me'), [
        'title' => ($r['title'] ?: $r['subject_name']).' — review by @'.$r['username'].' | RuinMyTrip',
        'description' => mb_strimwidth(strip_tags((string)$r['body']), 0, 155, '…'),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'Reviews','url'=>url('reviews')],
                          ['name'=>$r['title'] ?: $r['subject_name'],'url'=>url(ltrim(rmt_review_path($r),'/'))]],
        'jsonld' => $r['status']==='published' ? jsonld(['@context'=>'https://schema.org','@type'=>'Review',
            'itemReviewed'=>['@type'=>'Place','name'=>$r['subject_name']],
            'reviewRating'=>['@type'=>'Rating','ratingValue'=>(int)$r['rating'],'bestRating'=>5,'worstRating'=>1],
            'author'=>['@type'=>'Person','name'=>'@'.$r['username']],
            'datePublished'=>substr((string)$r['created_at'],0,10),
            'reviewBody'=>mb_strimwidth(strip_tags((string)$r['body']),0,500,'…')]) : '',
    ]);
}

function review_edit_form(array $a): void {
    require_login();
    $r = rmt_review_get((int)$a['id']);
    if (!$r) not_found();
    if (!rmt_review_can_edit($r, current_user())) { http_response_code(403); exit('403 — that is not your review.'); }
    $photos = q_all('SELECT * FROM review_photos WHERE review_id=? ORDER BY sort, id', [(int)$r['id']]);
    view('review_edit', ['r'=>$r, 'dests'=>all_dests(), 'errors'=>[], 'photos'=>$photos],
         ['title'=>'Edit review — RuinMyTrip']);
}

function review_edit_submit(array $a): void {
    require_login(); csrf_check();
    $r = rmt_review_get((int)$a['id']);
    if (!$r) not_found();
    if (!rmt_review_can_edit($r, current_user())) { http_response_code(403); exit('403 — that is not your review.'); }

    $isDraft = input('action') === 'draft';
    $v = rmt_review_validate($_POST, $isDraft);
    if (!$v['ok']) {
        $photos = q_all('SELECT * FROM review_photos WHERE review_id=? ORDER BY sort, id', [(int)$r['id']]);
        view('review_edit', ['r'=>array_merge($r, $_POST), 'dests'=>all_dests(), 'errors'=>$v['errors'], 'photos'=>$photos],
             ['title'=>'Edit review — RuinMyTrip']); return;
    }
    $d = $v['data'];
    // A hidden/removed review stays that way on edit — editing must not let a user undo moderation.
    $status = in_array($r['status'], ['hidden','removed'], true)
        ? $r['status']
        : ($isDraft ? 'draft' : 'published');
    $slug = rmt_review_slug($d + ['id'=>(int)$r['id']]);
    db()->prepare("UPDATE reviews SET destination_id=?, subject_type=?, subject_name=?, rating=?, title=?,
                   body=?, what_great=?, what_ruined=?, visited_on=?, safety_rating=?, value_rating=?,
                   status=?, slug=?, updated_at=? WHERE id=?")
        ->execute([$d['destination_id'], $d['subject_type'], $d['subject_name'], $d['rating'], $d['title'],
                   $d['body'], $d['what_great'], $d['what_ruined'], $d['visited_on'], $d['safety_rating'],
                   $d['value_rating'], $status, $slug, date('Y-m-d H:i:s'), (int)$r['id']]);
    $photoErrors = rmt_attach_review_photos((int)$r['id'], (int)current_user()['id']);

    // Remove any photos the author unticked.
    foreach ((array)($_POST['remove_photo'] ?? []) as $pid) {
        $ph = q_one('SELECT * FROM review_photos WHERE id=? AND review_id=?', [(int)$pid, (int)$r['id']]);
        if ($ph) {
            db()->prepare('DELETE FROM review_photos WHERE id=?')->execute([(int)$ph['id']]);
            rmt_storage_delete((string)$ph['storage_key']);
        }
    }

    if ($status === 'published') rmt_award_badges((int)current_user()['id']);
    $msg = 'Review updated.';
    if ($photoErrors) $msg .= ' Some photos were not added: ' . implode(' ', array_unique($photoErrors));
    flash($msg);
    redirect($status === 'draft' ? '/reviews?mine=1' : '/review/'.(int)$r['id'].'/'.$slug);
}

/** POST /review/{id}/delete — soft delete. Rows are never destroyed. */
function review_delete(array $a): void {
    require_login(); csrf_check();
    $r = rmt_review_get((int)$a['id']);
    if (!$r) not_found();
    if (!rmt_review_can_edit($r, current_user())) { http_response_code(403); exit('403 — that is not your review.'); }
    db()->prepare("UPDATE reviews SET status='removed', updated_at=? WHERE id=?")
        ->execute([date('Y-m-d H:i:s'), (int)$r['id']]);
    flash('Review deleted.');
    redirect('/u/'.current_user()['username']);
}

function follow_action(array $a): void {
    require_login(); csrf_check(); $me=current_user();
    $target=(int)input('user_id'); if (!$target || $target===(int)$me['id']) redirect('/');
    $exists = q_one('SELECT 1 FROM follows WHERE follower_id=? AND followee_id=?', [(int)$me['id'],$target]);
    if ($exists) db()->prepare('DELETE FROM follows WHERE follower_id=? AND followee_id=?')->execute([(int)$me['id'],$target]);
    else {
        q_run('INSERT INTO follows (follower_id,followee_id,created_at) VALUES (?,?,?)', [(int)$me['id'],$target,date('Y-m-d H:i:s')]);
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES (?,?,?,?,?,?)',
            [$target,'follow',(int)$me['id'],'user',(int)$me['id'],date('Y-m-d H:i:s')]);
    }
    redirect(input('return','/'));
}

/**
 * Interactable content types -> table. Same allow-list discipline as reports: a type that
 * reaches a table name is never taken raw from the request.
 */
const RMT_INTERACT_TARGETS = [
    'trip'   => 'trips',
    'review' => 'reviews',
    'guide'  => 'guides',
    'meetup' => 'meetups',
];

/**
 * Is $tt#$tid something $user is allowed to interact with (comment on, like, save)?
 *
 * You may only touch content you can actually SEE. Without this, a stranger could comment on and
 * like another user's unpublished draft purely by guessing its id — the draft 404s for them, but
 * the interaction endpoints never checked. Proven before this fix: @snoop landed a comment and a
 * like on a draft they could not view.
 */
function rmt_can_interact(string $tt, int $tid, ?array $user): bool {
    $table = RMT_INTERACT_TARGETS[$tt] ?? null;
    if (!$table || $tid < 1) return false;

    $row = q_one("SELECT user_id, status FROM {$table} WHERE id = ?", [$tid]);
    if (!$row) return false;                       // must exist — no ghost interactions
    if (($row['status'] ?? '') === 'published') return true;
    if (!$user) return false;
    if ((int) ($row['user_id'] ?? 0) === (int) $user['id']) return true;   // own draft
    return in_array($user['role'] ?? '', ['admin', 'mod'], true);
}

function react_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $kind = input('kind', 'like') === 'save' ? 'save' : 'like';
    $tbl  = $kind === 'save' ? 'saves' : 'likes';
    $tt   = (string) input('target_type');
    $tid  = (int) input('target_id');

    if (!rmt_can_interact($tt, $tid, $me)) redirect(input('return', '/'));
    if (!rmt_rate_ok('react', (string)$me['id'], 120, 3600)) {
        flash('You are doing that very fast. Try again shortly.');
        redirect(input('return', '/'));
    }

    $has = q_one("SELECT 1 FROM $tbl WHERE user_id=? AND target_type=? AND target_id=?", [(int)$me['id'],$tt,$tid]);
    if ($has) db()->prepare("DELETE FROM $tbl WHERE user_id=? AND target_type=? AND target_id=?")->execute([(int)$me['id'],$tt,$tid]);
    else db()->prepare("INSERT INTO $tbl (user_id,target_type,target_id) VALUES (?,?,?)")->execute([(int)$me['id'],$tt,$tid]);
    redirect(input('return','/'));
}

function comment_action(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $tt   = (string) input('target_type');
    $tid  = (int) input('target_id');
    $body = trim((string) input('body'));

    if ($body === '' || !rmt_can_interact($tt, $tid, $me)) redirect(input('return','/'));
    if (!rmt_rate_ok('comment', (string)$me['id'], 30, 3600)) {
        flash('You are commenting very fast. Try again shortly.');
        redirect(input('return','/'));
    }

    q_run("INSERT INTO comments (user_id,target_type,target_id,body,status,created_at) VALUES (?,?,?,?, 'published', ?)",
        [(int)$me['id'], $tt, $tid, mb_substr($body, 0, 2000), date('Y-m-d H:i:s')]);
    redirect(input('return','/'));
}

function meetup_rsvp(array $a): void {
    require_login(); csrf_check(); $me=current_user();
    if (!can_host_meetups($me)) { flash('You must be 18+ to RSVP to meetups.'); redirect('/meetup/'.(int)$a['id']); }
    $mid=(int)$a['id']; $m=q_one('SELECT * FROM meetups WHERE id=?', [$mid]); if(!$m) not_found();
    $has = q_one('SELECT 1 FROM meetup_rsvps WHERE meetup_id=? AND user_id=?', [$mid,(int)$me['id']]);
    if ($has) db()->prepare('DELETE FROM meetup_rsvps WHERE meetup_id=? AND user_id=?')->execute([$mid,(int)$me['id']]);
    else db()->prepare("INSERT INTO meetup_rsvps (meetup_id,user_id,status) VALUES (?,?, 'going')")->execute([$mid,(int)$me['id']]);
    redirect('/meetup/'.$mid);
}

/* ---------- auth ---------- */
function login_form(array $a): void { if (is_logged_in()) redirect('/feed'); view('auth/login', ['errors'=>[]], ['title'=>'Sign in — RuinMyTrip']); }
function login_submit(array $a): void {
    csrf_check();
    $email = input('email');
    // Two limits: per-IP stops broad credential stuffing, per-email stops a targeted attack on
    // one account from a botnet. Either tripping blocks the attempt.
    if (!rmt_rate_ok('login_ip', rmt_client_ip(), 20, 900) || !rmt_rate_ok('login_email', $email, 10, 900)) {
        $mins = (int)ceil(rmt_rate_retry_after(900) / 60);
        view('auth/login', ['errors'=>["Too many sign-in attempts. Try again in about {$mins} minute(s)."]],
             ['title'=>'Sign in — RuinMyTrip']); return;
    }
    if (attempt_login($email, input('password'))) { flash('Welcome back.'); redirect('/feed'); }
    view('auth/login', ['errors'=>['Incorrect email or password.']], ['title'=>'Sign in — RuinMyTrip']);
}
function register_form(array $a): void { if (is_logged_in()) redirect('/feed'); view('auth/register', ['errors'=>[]], ['title'=>'Join RuinMyTrip']); }
function register_submit(array $a): void {
    csrf_check();
    if (!rmt_rate_ok('register_ip', rmt_client_ip(), 5, 3600)) {
        view('auth/register', ['errors'=>['Too many accounts created from this connection. Try again later.']],
             ['title'=>'Join RuinMyTrip']); return;
    }
    $r = register_user(input('username'), input('email'), input('password'), input('birthdate'));
    if ($r['ok']) {
        flash(($r['mail_ok'] ?? false)
            ? 'Welcome to RuinMyTrip. Check your email to confirm your address.'
            : 'Welcome to RuinMyTrip. We could not send the confirmation email — request a new link below.');
        redirect('/verify-email');
    }
    view('auth/register', ['errors'=>$r['errors']], ['title'=>'Join RuinMyTrip']);
}
function logout_action(array $a): void { logout(); flash('Signed out.'); redirect('/'); }

/* ---------- email verification ---------- */

/** GET /verify-email — with ?token= consumes it; without, shows the "check your inbox" page. */
function verify_email(array $a): void {
    $raw = (string) input('token');
    if ($raw === '') {
        $me = current_user();
        view('auth/verify_notice', ['me'=>$me, 'verified'=>email_is_verified($me)],
             ['title'=>'Confirm your email — RuinMyTrip']);
        return;
    }
    $row = rmt_token_lookup($raw, 'verify');
    if (!$row) {
        view('auth/verify_notice', ['me'=>current_user(), 'verified'=>false,
             'errors'=>['That confirmation link is invalid or has expired. Request a new one below.']],
             ['title'=>'Confirm your email — RuinMyTrip']);
        return;
    }
    db()->prepare('UPDATE users SET email_verified_at = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), (int)$row['user_id']]);
    rmt_token_consume((int)$row['id']);
    // Confirming the address proves control of the account — log them in.
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$row['user_id'];
    flash('Email confirmed. Welcome to RuinMyTrip.');
    redirect('/feed');
}

/** POST /verify-email/resend */
function verify_email_resend(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    if (email_is_verified($me)) { flash('Your email is already confirmed.'); redirect('/feed'); }
    if (!rmt_rate_ok('verify_resend', (string)$me['email'], 3, 3600)) {
        flash('Too many emails requested. Try again in an hour.'); redirect('/verify-email');
    }
    [$ok, $detail] = send_verification_email($me);
    flash($ok ? 'Confirmation email sent. Check your inbox.'
              : 'We could not send that email right now. Please try again shortly.');
    redirect('/verify-email');
}

/* ---------- password reset ---------- */

function forgot_form(array $a): void {
    view('auth/forgot', ['errors'=>[], 'sent'=>false], ['title'=>'Reset your password — RuinMyTrip']);
}

/**
 * POST /forgot-password
 * Always renders the same "if that address exists, we sent a link" result — revealing whether
 * an email is registered would leak membership, which for a travel/meetup product is a privacy
 * problem, not just an auth one.
 */
function forgot_submit(array $a): void {
    csrf_check();
    $email = strtolower(trim(input('email')));
    $allowed = rmt_rate_ok('forgot_ip', rmt_client_ip(), 10, 3600)
            && rmt_rate_ok('forgot_email', $email, 3, 3600);
    if ($allowed && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($u && $u['status'] !== 'suspended') send_password_reset_email($u);
    }
    view('auth/forgot', ['errors'=>[], 'sent'=>true], ['title'=>'Reset your password — RuinMyTrip']);
}

function reset_form(array $a): void {
    $raw = (string) input('token');
    $row = rmt_token_lookup($raw, 'reset');
    view('auth/reset', ['token'=>$raw, 'valid'=>(bool)$row, 'errors'=>[]],
         ['title'=>'Choose a new password — RuinMyTrip']);
}

function reset_submit(array $a): void {
    csrf_check();
    $raw = (string) input('token');
    $row = rmt_token_lookup($raw, 'reset');
    if (!$row) {
        view('auth/reset', ['token'=>$raw, 'valid'=>false, 'errors'=>['That reset link is invalid or has expired.']],
             ['title'=>'Choose a new password — RuinMyTrip']); return;
    }
    $pw = (string) input('password');
    $pw2 = (string) input('password_confirm');
    $errors = [];
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== $pw2)    $errors[] = 'Those passwords do not match.';
    if ($errors) {
        view('auth/reset', ['token'=>$raw, 'valid'=>true, 'errors'=>$errors],
             ['title'=>'Choose a new password — RuinMyTrip']); return;
    }
    $uid = (int) $row['user_id'];
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($pw, PASSWORD_BCRYPT), $uid]);
    rmt_token_consume((int)$row['id']);
    rmt_token_burn_all($uid, 'reset');   // any other outstanding reset links die with this one

    // Completing a reset proves control of the mailbox, so it also confirms the address.
    db()->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $uid]);

    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    flash('Password updated. You are signed in.');
    redirect('/feed');
}

function settings_form(array $a): void {
    // /settings predates /u/{username}/edit and is still linked from older pages. Keep it working
    // by sending it to the canonical editor rather than maintaining two forms that can drift.
    require_login();
    redirect('/u/'.current_user()['username'].'/edit');
}
function settings_save(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $v = rmt_profile_validate($_POST);
    if (!$v['ok']) {
        view('profile_edit', ['me'=>$me, 'errors'=>$v['errors'], 'p'=>array_merge($me, $_POST)],
             ['title'=>'Edit your profile — RuinMyTrip']); return;
    }
    $d = $v['data'];
    db()->prepare('UPDATE profiles SET display_name=?, bio=?, home_city=?, avatar_url=? WHERE user_id=?')
        ->execute([$d['display_name'], $d['bio'], $d['home_city'], $d['avatar_url'], (int)$me['id']]);
    flash('Profile updated.'); redirect('/u/'.$me['username']);
}

/* ---------- report & admin ---------- */
/**
 * Reportable content types -> the table each lives in. An allow-list, not a free-text field:
 * target_type reaching a table name must never be attacker-controlled.
 */
const RMT_REPORT_TARGETS = [
    'review'  => 'reviews',
    'trip'    => 'trips',
    'guide'   => 'guides',
    'meetup'  => 'meetups',
    'comment' => 'comments',
    'user'    => 'users',
];
const RMT_REPORT_REASONS = ['abuse', 'spam', 'misinformation', 'unsafe', 'off_topic', 'other'];

function report_form(array $a): void {
    require_login();
    view('report', ['tt'=>input('target_type'),'tid'=>input('target_id'),'errors'=>[]],
         ['title'=>'Report content — RuinMyTrip']);
}

/**
 * POST /report
 *
 * A report queue is only useful if what lands in it is real. Without these checks the queue is
 * trivially floodable: before this, one account filed 31 reports on the same review, plus a
 * report against a nonexistent id and one with an invented target_type.
 */
function report_submit(array $a): void {
    require_login(); csrf_check(); $me = current_user();

    $tt     = (string) input('target_type');
    $tid    = (int) input('target_id');
    $reason = (string) input('reason');
    $details= trim((string) input('details'));
    $errors = [];

    if (!isset(RMT_REPORT_TARGETS[$tt])) $errors[] = 'That is not something you can report.';
    if (!in_array($reason, RMT_REPORT_REASONS, true)) $errors[] = 'Choose a reason for the report.';
    if (mb_strlen($details) > 2000) $errors[] = 'Please keep the details under 2000 characters.';

    // The target must actually exist — a queue full of reports against nothing wastes the one
    // resource moderation has, which is attention.
    if (!$errors) {
        $table = RMT_REPORT_TARGETS[$tt];
        if (!q_one("SELECT 1 FROM {$table} WHERE id = ?", [$tid])) {
            $errors[] = 'That content no longer exists.';
        }
    }

    // You cannot report your own content, and you cannot report the same thing twice while the
    // first report is still open.
    if (!$errors && $tt !== 'user') {
        $table = RMT_REPORT_TARGETS[$tt];
        $owner = q_one("SELECT user_id FROM {$table} WHERE id = ?", [$tid]);
        if ($owner && (int)($owner['user_id'] ?? 0) === (int)$me['id']) {
            $errors[] = 'You cannot report your own content. Edit or delete it instead.';
        }
    }
    if (!$errors && $tt === 'user' && $tid === (int)$me['id']) {
        $errors[] = 'You cannot report yourself.';
    }
    if (!$errors) {
        $dupe = q_one("SELECT 1 FROM reports WHERE reporter_id=? AND target_type=? AND target_id=? AND status='open'",
                      [(int)$me['id'], $tt, $tid]);
        if ($dupe) $errors[] = 'You have already reported this. Our moderators are looking at it.';
    }

    // Rate limit regardless of outcome, so probing for what exists is also throttled.
    if (!rmt_rate_ok('report', (string)$me['id'], 10, 3600)) {
        $errors[] = 'You have sent a lot of reports. Try again later.';
    }

    if ($errors) {
        view('report', ['tt'=>$tt, 'tid'=>$tid, 'errors'=>$errors],
             ['title'=>'Report content — RuinMyTrip']); return;
    }

    q_run("INSERT INTO reports (reporter_id,target_type,target_id,reason,details,status,created_at)
           VALUES (?,?,?,?,?, 'open', ?)",
        [(int)$me['id'], $tt, $tid, $reason, $details ?: null, date('Y-m-d H:i:s')]);
    flash('Thanks — our moderators will review this.');
    redirect('/');
}

function admin_dashboard(array $a): void {
    require_role('admin','mod');
    $reports = q_all("SELECT r.*, u.username reporter FROM reports r JOIN users u ON u.id=r.reporter_id
                      WHERE r.status='open' ORDER BY r.id DESC");
    $stats = [
        'users'=>(int)(q_one('SELECT COUNT(*) c FROM users')['c']??0),
        'trips'=>(int)(q_one('SELECT COUNT(*) c FROM trips')['c']??0),
        'reviews'=>(int)(q_one('SELECT COUNT(*) c FROM reviews')['c']??0),
        'meetups'=>(int)(q_one('SELECT COUNT(*) c FROM meetups')['c']??0),
        'open_reports'=>count($reports),
    ];
    view('admin', compact('reports','stats'), ['title'=>'Moderation — RuinMyTrip']);
}
function admin_resolve(array $a): void {
    require_role('admin','mod'); csrf_check(); $me = current_user();
    $rid = (int) input('report_id');
    $action = (string) input('action');
    $rep = q_one('SELECT * FROM reports WHERE id=?', [$rid]);
    if (!$rep) redirect('/admin');

    $tt = (string) $rep['target_type'];
    $table = RMT_REPORT_TARGETS[$tt] ?? null;

    // 'user' has no status column of this kind; suspending an account is a separate action.
    if ($table && $tt !== 'user' && in_array($action, ['hide','restore'], true)) {
        $newStatus = $action === 'hide' ? 'hidden' : 'published';
        db()->prepare("UPDATE {$table} SET status=? WHERE id=?")->execute([$newStatus, (int)$rep['target_id']]);
    }
    db()->prepare("UPDATE reports SET status='resolved', resolved_by=? WHERE id=?")
        ->execute([(int)$me['id'], $rid]);
    flash($action === 'hide' ? 'Content hidden and report resolved.'
         : ($action === 'restore' ? 'Content restored and report resolved.' : 'Report dismissed.'));
    redirect('/admin');
}

/* ---------- media ---------- */
/**
 * GET /media/{key} — serve a stored file.
 *
 * Content-Type is taken from the DB (set when we re-encoded the image), never from the request,
 * and X-Content-Type-Options: nosniff stops a browser second-guessing it. Together with the
 * re-encode on upload, a stored file cannot be coerced into executing as HTML or script.
 */
function media_show(array $a): void {
    $key = (string) ($a['key'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $key)) not_found();
    $m = rmt_storage_get($key);
    if (!$m) not_found();

    header('Content-Type: ' . $m['mime']);
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'none\'; sandbox');
    header('Content-Length: ' . strlen($m['bytes']));
    // Keys are random and content-addressed in practice, so a key's bytes never change.
    header('Cache-Control: public, max-age=31536000, immutable');
    echo $m['bytes'];
}

/* ---------- admin diagnostics ---------- */
/**
 * GET /admin/mail-check — admin-only. Reports whether this container can actually send mail and
 * which transport it would use. Exposes no secret values (key length only, never the key).
 */
function admin_mail_check(array $a): void {
    require_role('admin');
    header('Content-Type: text/plain');
    foreach (rmt_mail_diagnostics() as $k => $v) {
        printf("%-16s %s
", $k, is_bool($v) ? ($v ? 'yes' : 'NO') : (string)$v);
    }
    // Optional: /admin/mail-check?smtp=1 probes whether this host can open an outbound SMTP
    // connection at all. Render is documented (and previously measured on another service) to
    // block outbound SMTP; this measures it from THIS container rather than assuming.
    if (input('smtp') === '1') {
        foreach ([['smtp.gmail.com',465],['smtp.gmail.com',587],['smtp.gmail.com',2525]] as [$h,$port]) {
            $t0 = microtime(true);
            $fp = @fsockopen($h, $port, $errno, $errstr, 8);
            $ms = (int)round((microtime(true)-$t0)*1000);
            if ($fp) { $banner = trim((string)@fgets($fp, 128)); fclose($fp);
                       printf("smtp %s:%-5d OPEN  %dms  banner=%s
", $h, $port, $ms, substr($banner,0,40)); }
            else     { printf("smtp %s:%-5d BLOCKED %dms  (%d %s)
", $h, $port, $ms, $errno, substr($errstr,0,40)); }
        }
    }

    // Optional live probe: /admin/mail-check?send=1 sends one email to the admin's own address.
    if (input('send') === '1') {
        $me = current_user();
        [$ok, $detail] = rmt_mail_send((string)$me['email'], 'RuinMyTrip mail check',
            '<p>Transport probe from production. If you received this, outbound mail works.</p>');
        printf("
%-16s %s
%-16s %s
", 'probe_sent', $ok ? 'yes' : 'NO', 'probe_detail', $detail);
    }
}

/* ---------- legal / safety ---------- */
function page_terms(array $a): void { view('legal/terms', [], ['title'=>'Terms of Service — RuinMyTrip']); }
function page_privacy(array $a): void { view('legal/privacy', [], ['title'=>'Privacy Policy — RuinMyTrip']); }
function page_guidelines(array $a): void { view('legal/guidelines', [], ['title'=>'Community Guidelines — RuinMyTrip']); }
function page_affiliate(array $a): void { view('legal/affiliate', [], ['title'=>'Affiliate Disclosure — RuinMyTrip']); }
function page_safety(array $a): void { view('legal/safety', [], ['title'=>'Meetup Safety — RuinMyTrip']); }

/* ---------- health check (Render) ---------- */
// Liveness only — NO DB call, so health never flaps on DB latency (that caused Render edge 404s).
function healthz(array $a): void {
    header('Content-Type: text/plain');
    echo 'ok';
}
// Separate readiness probe that DOES check the DB (for manual/diagnostic use, not the Render health path).
function readyz(array $a): void {
    header('Content-Type: text/plain');
    try { db()->query('SELECT 1'); echo 'ready db=ok'; }
    catch (Throwable $e) { http_response_code(503); echo 'db=down'; }
}

/* ---------- sitemap ---------- */
function sitemap(array $a): void {
    header('Content-Type: application/xml; charset=utf-8');
    $urls = [url(), url('explore'), url('reviews'), url('guides'), url('meetups'), url('going'),
             url('terms'), url('privacy'), url('guidelines'), url('affiliate'), url('safety')];
    foreach (q_all('SELECT slug FROM destinations') as $d) $urls[] = url('d/'.$d['slug']);
    foreach (q_all("SELECT id,slug FROM trips WHERE status='published'") as $t) $urls[] = url('trip/'.$t['id'].'/'.$t['slug']);
    foreach (q_all("SELECT slug FROM guides WHERE status='published'") as $g) $urls[] = url('g/'.$g['slug']);
    // Published reviews only — drafts/hidden/removed are never listed. Rows missing a slug
    // (pre-Phase-4) fall back to a generated one so the URL still resolves.
    foreach (q_all("SELECT id, slug, title, subject_name FROM reviews WHERE status='published'") as $rv) {
        $urls[] = url('review/'.$rv['id'].'/'.($rv['slug'] ?: rmt_review_slug($rv)));
    }
    foreach (q_all("SELECT username FROM users WHERE status='active'") as $u) $urls[] = url('u/'.$u['username']);
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach ($urls as $u) echo '  <url><loc>'.e($u).'</loc></url>'."\n";
    echo '</urlset>';
}
