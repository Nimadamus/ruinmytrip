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
    $trips = q_all("SELECT t.*, d.name dest_name FROM trips t LEFT JOIN destinations d ON d.id=t.destination_id
                    WHERE t.user_id=? AND t.status='published' ORDER BY t.id DESC", [$uid]);
    $reviews = q_all("SELECT * FROM reviews WHERE user_id=? AND status='published' ORDER BY id DESC", [$uid]);
    $guides = q_all("SELECT * FROM guides WHERE user_id=? AND status='published' ORDER BY id DESC", [$uid]);
    $followers = (int)(q_one('SELECT COUNT(*) c FROM follows WHERE followee_id=?', [$uid])['c'] ?? 0);
    $following = (int)(q_one('SELECT COUNT(*) c FROM follows WHERE follower_id=?', [$uid])['c'] ?? 0);
    $me = current_user();
    $is_following = $me ? (bool) q_one('SELECT 1 FROM follows WHERE follower_id=? AND followee_id=?', [(int)$me['id'],$uid]) : false;
    view('profile', compact('u','trips','reviews','guides','followers','following','is_following','me'), [
        'title' => ($u['display_name'] ?: $u['username']).' (@'.$u['username'].') — RuinMyTrip',
        'description' => $u['bio'] ?: ('Traveler profile for @'.$u['username'].' on RuinMyTrip.'),
        'og_image' => $u['avatar_url'] ?: url('assets/img/og-default.svg'),
        'breadcrumbs' => [['name'=>'Home','url'=>url()],['name'=>'@'.$u['username'],'url'=>url('u/'.$u['username'])]],
    ]);
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
    $reviews = q_all("SELECT r.*, d.name dest_name, d.slug dest_slug FROM reviews r
                      LEFT JOIN destinations d ON d.id=r.destination_id
                      WHERE r.status='published' ORDER BY r.verified DESC, r.id DESC LIMIT 50");
    foreach ($reviews as &$r) $r['author'] = author((int)$r['user_id']); unset($r);
    view('reviews_index', compact('reviews'), [
        'title'=>'Traveler reviews — RuinMyTrip',
        'description'=>'Honest, verified traveler reviews of destinations, hotels, restaurants, nightlife, attractions and tours.',
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
    $cover = input('cover_url'); $visited = input('visited_on');
    $errors = [];
    if (strlen($title) < 5) $errors[] = 'Give your trip a title (5+ characters).';
    if (strlen($body) < 20) $errors[] = 'Add a bit more to your story (20+ characters).';
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
    view('review_new', ['dests'=>all_dests(),'errors'=>[]], ['title'=>'Write a review — RuinMyTrip','description'=>'Share an honest, verified review.']);
}
function review_create(array $a): void {
    require_login(); csrf_check(); $me = current_user();
    $dest=(int)input('destination_id'); $type=input('subject_type','destination'); $subject=input('subject_name');
    $rating=max(1,min(5,(int)input('rating'))); $title=input('title'); $body=input('body');
    $errors=[];
    if (!$subject) $errors[]='Name what you are reviewing.';
    if (strlen($body) < 15) $errors[]='Add a few words to your review (15+ characters).';
    if ($errors) { view('review_new', ['dests'=>all_dests(),'errors'=>$errors], ['title'=>'Write a review — RuinMyTrip']); return; }
    q_run("INSERT INTO reviews (user_id,destination_id,subject_type,subject_name,rating,title,body,verified,status,created_at)
           VALUES (?,?,?,?,?,?,?,0,'published',?)",
        [(int)$me['id'], $dest ?: null, $type, $subject, $rating, $title, $body, date('Y-m-d H:i:s')]);
    flash('Review posted.');
    redirect($dest ? '/d/'.(dest_by_id($dest)['slug'] ?? '') : '/reviews');
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

function react_action(array $a): void {
    require_login(); csrf_check(); $me=current_user();
    $kind=input('kind','like'); $tt=input('target_type'); $tid=(int)input('target_id');
    $tbl = $kind==='save' ? 'saves' : 'likes';
    if (!in_array($tt,['trip','review','guide','meetup'],true) || !$tid) redirect(input('return','/'));
    $has = q_one("SELECT 1 FROM $tbl WHERE user_id=? AND target_type=? AND target_id=?", [(int)$me['id'],$tt,$tid]);
    if ($has) db()->prepare("DELETE FROM $tbl WHERE user_id=? AND target_type=? AND target_id=?")->execute([(int)$me['id'],$tt,$tid]);
    else db()->prepare("INSERT INTO $tbl (user_id,target_type,target_id) VALUES (?,?,?)")->execute([(int)$me['id'],$tt,$tid]);
    redirect(input('return','/'));
}

function comment_action(array $a): void {
    require_login(); csrf_check(); $me=current_user();
    $tt=input('target_type'); $tid=(int)input('target_id'); $body=input('body');
    if ($body!=='' && in_array($tt,['trip','review','guide'],true) && $tid) {
        q_run("INSERT INTO comments (user_id,target_type,target_id,body,status,created_at) VALUES (?,?,?,?, 'published', ?)",
            [(int)$me['id'],$tt,$tid,mb_substr($body,0,2000),date('Y-m-d H:i:s')]);
    }
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
    require_login(); view('settings', ['me'=>current_user(),'errors'=>[]], ['title'=>'Settings — RuinMyTrip']);
}
function settings_save(array $a): void {
    require_login(); csrf_check(); $me=current_user();
    db()->prepare('UPDATE profiles SET display_name=?, bio=?, home_city=?, avatar_url=? WHERE user_id=?')
        ->execute([input('display_name'),input('bio'),input('home_city'),input('avatar_url'),(int)$me['id']]);
    flash('Profile updated.'); redirect('/u/'.$me['username']);
}

/* ---------- report & admin ---------- */
function report_form(array $a): void {
    require_login();
    view('report', ['tt'=>input('target_type'),'tid'=>input('target_id')], ['title'=>'Report content — RuinMyTrip']);
}
function report_submit(array $a): void {
    require_login(); csrf_check(); $me=current_user();
    q_run("INSERT INTO reports (reporter_id,target_type,target_id,reason,details,status,created_at) VALUES (?,?,?,?,?, 'open', ?)",
        [(int)$me['id'],input('target_type'),(int)input('target_id'),input('reason'),input('details'),date('Y-m-d H:i:s')]);
    flash('Thanks — our moderators will review this.'); redirect('/');
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
    require_role('admin','mod'); csrf_check(); $me=current_user();
    $rid=(int)input('report_id'); $action=input('action');
    $rep=q_one('SELECT * FROM reports WHERE id=?', [$rid]); if(!$rep){redirect('/admin');}
    if ($action==='hide' && in_array($rep['target_type'],['trip','review','guide','meetup','comment'],true)) {
        $tbl=['trip'=>'trips','review'=>'reviews','guide'=>'guides','meetup'=>'meetups','comment'=>'comments'][$rep['target_type']];
        db()->prepare("UPDATE $tbl SET status='hidden' WHERE id=?")->execute([(int)$rep['target_id']]);
    }
    db()->prepare("UPDATE reports SET status='resolved', resolved_by=? WHERE id=?")->execute([(int)$me['id'],$rid]);
    flash('Report resolved.'); redirect('/admin');
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
    foreach (q_all("SELECT username FROM users WHERE status='active'") as $u) $urls[] = url('u/'.$u['username']);
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach ($urls as $u) echo '  <url><loc>'.e($u).'</loc></url>'."\n";
    echo '</urlset>';
}
