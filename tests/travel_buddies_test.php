<?php
/**
 * Travel buddies (app/buddies.php, migrations 099 and 100).
 *
 * The question the section answers is "who else is going where I am going", so most of this file
 * plants people and asks it: Paris on overlapping dates, Thailand by country, the same ship on the
 * same day, a local who opted in, somebody there right now. The privacy rules are asked the same
 * way: a private trip, a trip marked "not looking to meet", a block, and a local who never opted in
 * must not appear, and nothing short of an accepted request opens a private message.
 *
 *   php tests/travel_buddies_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/profiles.php';
require BASE_PATH . '/app/plans.php';
require BASE_PATH . '/app/buddies.php';

function rmt_is_blocked(int $a, int $b): bool {
    return (bool) q_one('SELECT 1 FROM blocks WHERE (blocker_id=? AND blocked_id=?) OR (blocker_id=? AND blocked_id=?)', [$a, $b, $b, $a]);
}
function dest_by_id(int $id): ?array { return q_one('SELECT * FROM destinations WHERE id=?', [$id]); }

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT NOT NULL DEFAULT 'active', role TEXT NOT NULL DEFAULT 'user',
              birthdate TEXT, email_verified_at TEXT, created_at TEXT)");
$pdo->exec('CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, avatar_url TEXT, languages TEXT, travel_style TEXT,
              open_to_meeting INT NOT NULL DEFAULT 0, home_destination_id INT, home_city TEXT, bio TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT)');
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT, title TEXT NOT NULL DEFAULT '',
              slug TEXT NOT NULL DEFAULT '', body TEXT, status TEXT NOT NULL DEFAULT 'published', visibility TEXT NOT NULL DEFAULT 'public',
              date_from TEXT, date_to TEXT, visited_on TEXT, travel_style TEXT, open_to_meeting INT, created_at TEXT, updated_at TEXT)");
$pdo->exec('CREATE TABLE trip_members (trip_id INT, user_id INT, role TEXT, state TEXT)');
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT)');
$pdo->exec('CREATE TABLE blocks (blocker_id INT, blocked_id INT)');
$pdo->exec('CREATE TABLE profile_interests (user_id INT, interest TEXT)');
$pdo->exec('CREATE TABLE saves (user_id INT, target_type TEXT, target_id INT, created_at TEXT)');
$pdo->exec("CREATE TABLE trip_connects (id INTEGER PRIMARY KEY AUTOINCREMENT, trip_id INT, from_user_id INT, to_user_id INT, state TEXT, created_at TEXT, decided_at TEXT)");
$pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT,
              actor_id INT, target_type TEXT, target_id INT, read_at TEXT, created_at TEXT)');
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/099_travel_buddies.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/100_buddy_matching.sqlite.sql'));

$y = (int) date('Y') + 1;
$d = static fn(string $md): string => $y . '-' . $md;
$today = date('Y-m-d');
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'paris-france','Paris','France'),(2,'bangkok-thailand','Bangkok','Thailand'),
              (3,'chiang-mai-thailand','Chiang Mai','Thailand'),(4,'tokyo-japan','Tokyo','Japan'),(5,'phuket-thailand','Phuket','Thailand')");
$names = [1 => 'me', 2 => 'ana', 3 => 'leo', 4 => 'kim', 5 => 'sam', 6 => 'noor', 7 => 'priv', 8 => 'shy', 9 => 'blk', 10 => 'loc', 11 => 'nolo', 12 => 'now'];
foreach ($names as $id => $n) {
    q_run("INSERT INTO users (id,username,birthdate,email_verified_at,created_at) VALUES (?,?,?,?,?)",
          [$id, $n, ($id === 4 ? (date('Y') - 22) : (date('Y') - 40)) . '-01-01', $id === 2 ? '2026-01-01' : null, '2026-01-01']);
    q_run('INSERT INTO profiles (user_id, display_name) VALUES (?,?)', [$id, ucfirst($n) . ' Surname']);
}
$me = ['id' => 1];

// Paris, June 5 to 12 is the reader's question. ana posted a buddy post that overlaps, leo a trip that
// overlaps, kim a trip in Paris in August, priv a private overlapping trip, shy said no to meeting,
// blk is blocked by the reader.
$post = static function (int $uid, array $over) use ($d): int {
    $v = rmt_buddy_validate($over + ['trip_type' => 'trip', 'title' => 'Looking for company in town', 'where_text' => '',
         'date_from' => $d('06-01'), 'date_to' => $d('06-08'), 'spots' => '1', 'budget' => 'any',
         'description' => 'Would love someone to explore museums and cafes with.', 'safety_ack' => '1']);
    if (!$v['ok']) throw new RuntimeException(implode(' ', $v['errors']));
    return rmt_buddy_insert($uid, $v['data']);
};
$anaPost = $post(2, ['destination_id' => '1', 'travel_party' => 'solo', 'interests' => ['culture', 'food']]);
$trip = static function (int $uid, int $dest, string $from, string $to, array $extra = []): int {
    return (int) q_run('INSERT INTO trips (user_id,destination_id,title,slug,date_from,date_to,visibility,open_to_meeting,travel_style,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$uid, $dest, 'Trip ' . $uid, 't' . $uid, $from, $to, $extra['visibility'] ?? 'public', $extra['open'] ?? null, $extra['style'] ?? null, date('Y-m-d H:i:s')]);
};
$leoTrip = $trip(3, 1, $d('06-10'), $d('06-20'), ['style' => 'friends']);
$trip(4, 1, $d('08-01'), $d('08-09'));
$trip(7, 1, $d('06-06'), $d('06-09'), ['visibility' => 'private']);
$trip(8, 1, $d('06-06'), $d('06-09'), ['open' => 0]);
$trip(9, 1, $d('06-06'), $d('06-09'));
q_run('INSERT INTO blocks (blocker_id, blocked_id) VALUES (1, 9)');
q_run("INSERT INTO profile_interests (user_id, interest) VALUES (3, 'nightlife')");
// A month in Thailand, and somebody there right now.
$trip(5, 3, $d('02-01'), $d('02-28'));
$trip(12, 2, date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+3 days')));
// Locals: loc opted in, nolo lives in Paris but never said yes.
q_run('UPDATE profiles SET home_destination_id=1, open_to_meeting=1 WHERE user_id=10');
q_run('UPDATE profiles SET home_destination_id=1, open_to_meeting=0 WHERE user_id=11');

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}
$who = static fn(array $cards): array => array_map(static fn($c) => $c['kind'] . ':' . $c['username'], $cards);

/* ---- validation ---- */
$good = ['trip_type' => 'cruise', 'title' => 'Cabin mate for a Caribbean cruise', 'where_text' => '', 'cruise_line' => 'Royal Caribbean',
         'ship' => 'The Icon of the Seas', 'departure_port' => 'Miami', 'date_from' => $d('03-01'), 'date_to' => $d('03-08'), 'spots' => '1',
         'budget' => 'mid', 'description' => 'Booked an inside cabin and would like someone to split it with.', 'safety_ack' => '1'];
$v = rmt_buddy_validate($good, $today);
ok('a complete cruise post validates', $v['ok'], json_encode($v['errors']));
ok('a cruise without a where gets one from the ship and port', str_contains($v['data']['where_text'], 'Icon of the Seas') && str_contains($v['data']['where_text'], 'Miami'));
ok('ship names share a key however they are typed', $v['data']['ship_key'] === rmt_buddy_ship_key('icon of the seas') && $v['data']['ship_key'] === 'iconoftheseas');
$vt = rmt_buddy_validate(['title' => ''] + $good, $today);
ok('a blank title is filled in from the ship and date', $vt['ok'] && str_starts_with($vt['data']['title'], 'The Icon of the Seas sailing, '), json_encode($vt['data']['title'] ?? $vt['errors']));
ok('a too short title is still refused', !rmt_buddy_validate(['title' => 'short'] + $good, $today)['ok']);
ok('a cruise needs a line or a ship', !rmt_buddy_validate(['cruise_line' => '', 'ship' => ''] + $good, $today)['ok']);
ok('a non cruise drops cruise fields', rmt_buddy_validate(['trip_type' => 'trip', 'destination_id' => '1'] + $good, $today)['data']['ship'] === null);
ok('an unknown destination is dropped, not trusted', rmt_buddy_validate(['trip_type' => 'trip', 'destination_id' => '99', 'where_text' => 'Somewhere'] + $good, $today)['data']['destination_id'] === null);
ok('a trip that has ended is refused', !rmt_buddy_validate(['date_from' => '2020-01-01', 'date_to' => '2020-01-05'] + $good, $today)['ok']);
ok('ending before it starts is refused', !rmt_buddy_validate(['date_to' => $d('02-01')] + $good, $today)['ok']);
ok('an unknown trip type is refused', !rmt_buddy_validate(['trip_type' => 'dating'] + $good, $today)['ok']);
ok('ages under 18 are refused', !rmt_buddy_validate(['age_min' => '16'] + $good, $today)['ok']);
ok('unknown interests are dropped', rmt_buddy_validate(['interests' => ['food', 'hookups']] + $good, $today)['data']['interests'] === 'food');
$noAck = $good; unset($noAck['safety_ack']);
ok('the safety terms are required', !rmt_buddy_validate($noAck, $today)['ok']);

/* ---- who else is going to Paris, June 5 to 12 ---- */
$f = rmt_buddy_filters(['where' => 'Paris', 'from' => $d('06-05'), 'to' => $d('06-12')]);
ok('"Paris" resolves to the destination', $f['dest_id'] === 1);
$res = rmt_buddy_search($f, $me);
$names = $who($res['cards']);
ok('an overlapping buddy post is found', in_array('post:ana', $names, true), json_encode($names));
ok('an overlapping trip is found', in_array('trip:leo', $names, true), json_encode($names));
ok('a local who opted in is found', in_array('local:loc', $names, true), json_encode($names));
ok('Paris in August does not overlap June', !in_array('trip:kim', $names, true));
ok('a private trip never appears', !in_array('trip:priv', $names, true));
ok('a trip marked not looking to meet never appears', !in_array('trip:shy', $names, true));
ok('a blocked member never appears', !in_array('trip:blk', $names, true));
ok('a local who never opted in never appears', !in_array('local:nolo', $names, true));
$ana = array_values(array_filter($res['cards'], static fn($c) => $c['username'] === 'ana'))[0] ?? [];
ok('the card counts the overlap in days', ($ana['overlap_days'] ?? 0) === 4, (string) ($ana['overlap_days'] ?? ''));
ok('the card uses a first name only', ($ana['name'] ?? '') === 'Ana');
ok('the card says the email is confirmed when it is', !empty($ana['verified']));
ok('the card carries no private fields', !array_intersect(array_keys($ana), ['email', 'birthdate', 'password_hash', 'home_city']));
ok('the blocked side does not see the reader either', !in_array('post:me', $who(rmt_buddy_search($f, ['id' => 9])['cards']), true));

$byDates = rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'from' => $d('06-13'), 'to' => $d('06-14')]), $me);
ok('outside the post dates, only overlapping people remain', $who($byDates['cards']) === ['trip:leo', 'local:loc'], json_encode($who($byDates['cards'])));
$flex = rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'from' => $d('06-13'), 'to' => $d('06-14'), 'flexible' => '1']), $me);
ok('flexible dates reach a week either side', in_array('post:ana', $who($flex['cards']), true));
$wide = rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'from' => $d('12-01'), 'to' => $d('12-05')]), $me);
ok('nobody on those dates widens to the same place at other times', $wide['widened'] && in_array('trip:kim', $who($wide['cards']), true));

ok('filter by interest uses the post and the profile',
   $who(rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'interest' => 'culture', 'show' => 'going']), $me)['cards']) === ['post:ana']
   && $who(rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'interest' => 'nightlife', 'show' => 'going']), $me)['cards']) === ['trip:leo']);
ok('filter solo finds the solo traveler', $who(rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'party' => 'solo']), $me)['cards']) === ['post:ana']);
ok('filter group finds people travelling with friends', $who(rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'party' => 'group']), $me)['cards']) === ['trip:leo']);
// Age: only the poster's stated preference, checked against the reader's own age. kim is 22, me is 40.
q_run('UPDATE buddy_posts SET age_min = 20, age_max = 30 WHERE id = ?', [$anaPost]);
ok('"open to my age" hides a post whose preferred ages exclude me', !in_array('post:ana', $who(rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'myage' => '1']), $me)['cards']), true));
ok('and shows it to somebody inside the range', in_array('post:ana', $who(rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'myage' => '1']), ['id' => 4])['cards']), true));
ok('without the option, the preference hides nothing', in_array('post:ana', $who(rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris']), $me)['cards']), true));
ok('no search ever reads another member birthdate', !str_contains((string) file_get_contents(BASE_PATH . '/app/buddies.php'), 'u.birthdate'));
q_run('UPDATE buddy_posts SET age_min = NULL, age_max = NULL WHERE id = ?', [$anaPost]);
// The exact cases from the brief: Paris June 5 to 12 against June 8 to 15, and against July.
$p815 = $trip(6, 1, $d('06-08'), $d('06-15'));
$pj = rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'from' => $d('06-05'), 'to' => $d('06-12')]), $me)['cards'];
$noor = array_values(array_filter($pj, static fn($c) => $c['username'] === 'noor'));
ok('Paris June 5 to 12 matches a June 8 to 15 trip', count($noor) === 1);
ok('and reports 5 overlapping days', ($noor[0]['overlap_days'] ?? 0) === 5);
ok('Paris June 5 to 12 does not match July', !array_filter(rmt_buddy_search_run(rmt_buddy_filters(['where' => 'Paris', 'from' => $d('07-01'), 'to' => $d('07-10'), 'show' => 'going']), $me, 60), static fn($c) => $c['username'] === 'noor'));
q_run("UPDATE trips SET status='removed' WHERE id=?", [$p815]);
ok('show locals lists only locals', $who(rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'show' => 'locals']), $me)['cards']) === ['local:loc']);

$thai = rmt_buddy_filters(['where' => 'thailand']);
ok('"thailand" resolves to the country', $thai['country'] === 'Thailand');
$phuketPost = $post(11, ['destination_id' => '5', 'date_from' => $d('04-01'), 'date_to' => $d('04-09')]);
$thaiCards = rmt_buddy_search($thai, $me)['cards'];
ok('a country search finds Chiang Mai, Bangkok and Phuket', count(array_intersect(['trip:sam', 'trip:now', 'post:nolo'], $who($thaiCards))) === 3, json_encode($who($thaiCards)));
ok('a country search never reaches another country', !array_filter($thaiCards, static fn($c) => $c['country'] !== 'Thailand'));
$here = rmt_buddy_search(rmt_buddy_filters(['where' => 'Bangkok', 'show' => 'here']), $me);
ok('there right now finds the traveler whose dates cover today', $who($here['cards']) === ['trip:now'] && $here['cards'][0]['here_now']);

/* ---- one card per person per place ---- */
$annaTrip = $trip(2, 1, $d('06-01'), $d('06-08'));
ok('a member with a post and a trip for Paris appears once', count(array_filter(rmt_buddy_search($f, $me)['cards'], static fn($c) => $c['username'] === 'ana')) === 1);

/* ---- cruises ---- */
$c1 = rmt_buddy_insert(2, $v['data']);
$c2 = rmt_buddy_insert(3, rmt_buddy_validate(['ship' => 'icon of the seas'] + $good, $today)['data']);
$c3 = rmt_buddy_insert(4, rmt_buddy_validate(['ship' => 'Wonder of the Seas', 'date_from' => $d('03-10'), 'date_to' => $d('03-17')] + $good, $today)['data']);
$c4 = rmt_buddy_insert(5, rmt_buddy_validate(['date_from' => $d('03-15'), 'date_to' => $d('03-22')] + $good, $today)['data']);
$rk = rmt_buddy_search(rmt_buddy_filters(['type' => 'cruise', 'ship' => 'Icon of the Seas', 'from' => $d('03-15'), 'to' => $d('03-15'), 'flexible' => '1']), $me)['cards'];
ok('the exact sailing ranks first', ($rk[0]['username'] ?? '') === 'sam' && $rk[0]['rank'] === 3, json_encode(array_map(fn($c) => $c['username'] . $c['rank'], $rk)));
$rl = rmt_buddy_search(rmt_buddy_filters(['type' => 'cruise', 'line' => 'Royal', 'ship' => 'Wonder of the Seas']), $me)['cards'];
ok('the named ship ranks above the rest of the line', ($rl[0]['username'] ?? '') === 'kim' && $rl[0]['rank'] === 2, json_encode(array_map(fn($c) => $c['username'] . $c['rank'], $rl)));
q_run("UPDATE buddy_posts SET status='removed' WHERE id=?", [$c4]);
$sail = rmt_buddy_same_sailing(rmt_buddy_get($c1));
ok('the same ship on the same day is the same sailing', array_column($sail, 'id') == [$c2]);
ok('a sister ship from the same port nine days later is a similar sailing', array_column(rmt_buddy_similar_sailings(rmt_buddy_get($c1)), 'id') == [$c3]);
$cr = rmt_buddy_search(rmt_buddy_filters(['type' => 'cruise', 'ship' => 'Icon of the Seas']), $me);
ok('searching a ship finds both people on it', $who($cr['cards']) === ['post:ana', 'post:leo'], json_encode($who($cr['cards'])));
ok('each card says how many are on the exact sailing', ($cr['cards'][0]['on_sailing'] ?? -1) === 1);
$groups = rmt_buddy_group_sailings(rmt_buddy_search(rmt_buddy_filters(['type' => 'cruise']), $me)['cards']);
ok('cruise results group into sailings, the fullest first', count($groups) === 2 && count($groups[0]['cards']) === 2);

/* ---- answering, and what opens messages ---- */
$anaP = rmt_buddy_get($anaPost);
ok('the poster cannot request their own post', !rmt_buddy_toggle_interest($anaP, 2)['ok']);
$r = rmt_buddy_toggle_interest($anaP, 1, 'Hi, I am in Paris then');
ok('someone else can', $r['ok'] && $r['action'] === 'interested');
ok('the poster is notified', (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=2 AND type='buddy_interest'")['n'] === 1);
ok('the card shows the request as sent', (rmt_buddy_search($f, $me)['cards'][0]['viewer_state']['state'] ?? '') === 'interested');
ok('a request alone does not open messages', !rmt_buddy_mutual(1, 2));
ok('only the poster can accept', !rmt_buddy_decide($anaP, 3, 1, 'accepted'));
ok('the poster accepts', rmt_buddy_decide($anaP, 2, 1, 'accepted'));
ok('acceptance opens messages both ways', rmt_buddy_mutual(1, 2) && rmt_buddy_mutual(2, 1));
ok('it does not open them for anyone else', !rmt_buddy_mutual(1, 3) && !rmt_buddy_mutual(2, 3));
q_run('INSERT INTO blocks (blocker_id, blocked_id) VALUES (3, 6)');
ok('a blocked member cannot request', !rmt_buddy_toggle_interest(rmt_buddy_get($c2), 6)['ok']);

ok('a local who never opted in cannot be asked', !rmt_local_connect_request(1, 11)['ok']);
$lc = rmt_local_connect_request(1, 10);
ok('a local who opted in can be asked', $lc['ok'] && $lc['created']);
ok('asking twice is not a second request', rmt_local_connect_request(1, 10)['created'] === false);
ok('the local is told', (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=10 AND type='local_connect'")['n'] === 1);
ok('asking a local does not open messages', !rmt_buddy_mutual(1, 10));
ok('only the local can accept', !rmt_local_connect_decide(3, 1, 'accepted'));
ok('the local accepts, and messages open', rmt_local_connect_decide(10, 1, 'accepted') && rmt_buddy_mutual(10, 1));

/* ---- match notifications ---- */
$tokyoTrip = $trip(6, 4, $d('09-01'), $d('09-10'));
$tokyoPost = $post(5, ['destination_id' => '4', 'date_from' => $d('09-05'), 'date_to' => $d('09-12')]);
ok('a new post tells the traveler whose trip it lands on', rmt_buddy_notify_matches('buddy', $tokyoPost) === 1
   && (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=6 AND type='buddy_match'")['n'] === 1);
ok('and never twice for the same post', rmt_buddy_notify_matches('buddy', $tokyoPost) === 0);
$late = $trip(4, 4, $d('09-11'), $d('09-15'));
ok('a new trip tells the buddy poster it lands on', rmt_buddy_notify_matches('trip', $late) === 1
   && (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=5 AND type='buddy_match'")['n'] === 1);
ok('a new sailing tells the people on the same ship, as a sailing', rmt_buddy_notify_matches('buddy', $c2) >= 1
   && (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=2 AND type='buddy_sailing' AND target_id=?", [$c2])['n'] === 1);
q_run("INSERT INTO saves (user_id,target_type,target_id,created_at) VALUES (12,'destination',4,?)", [date('Y-m-d H:i:s')]);
$tokyo2 = $post(3, ['destination_id' => '4', 'date_from' => $d('10-01'), 'date_to' => $d('10-05')]);
rmt_buddy_notify_matches('buddy', $tokyo2);
ok('a post to a saved city tells the member who saved it', (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=12 AND type='buddy_city' AND target_id=?", [$tokyo2])['n'] === 1);

/* ---- the member's own page ---- */
$dash = rmt_buddy_dashboard(2);
ok('the dashboard lists my open posts', count($dash['posts']) === 2);
ok('the dashboard lists my trips', count($dash['trips']) === 1 && (int) $dash['trips'][0]['id'] === $annaTrip);
ok('accepted people are my buddies', isset($dash['buddies'][1]));
$dash1 = rmt_buddy_dashboard(1);
ok('the requests I sent are listed with their state', count($dash1['sent']) === 2);
ok('each upcoming trip says how many travelers line up', ($dash['matches']['trip:' . $annaTrip]['count'] ?? -1) >= 1);
$dashLeo = rmt_buddy_dashboard(3);
ok('a pending request waits on the right person', count($dashLeo['received']) === 0);
rmt_buddy_toggle_interest(rmt_buddy_get($c2), 1);
ok('and appears once somebody asks', count(rmt_buddy_dashboard(3)['received']) === 1);

/* ---- closing ---- */
$r = rmt_buddy_toggle_interest($anaP, 1);
ok('withdrawing removes the request and the connection', $r['action'] === 'withdrawn' && !rmt_buddy_mutual(1, 2));
q_run("UPDATE buddy_posts SET status='closed' WHERE id=?", [$anaPost]);
ok('a closed post takes no new requests', !rmt_buddy_toggle_interest(rmt_buddy_get($anaPost), 3)['ok']);
ok('and is not listed', !in_array('post:ana', $who(rmt_buddy_search(rmt_buddy_filters(['where' => 'Paris', 'show' => 'going']), $me)['cards']), true));

/* ---- completed and past ---- */
$startedPost = $post(12, ['destination_id' => '2', 'date_from' => date('Y-m-d', strtotime('-3 days')), 'date_to' => date('Y-m-d', strtotime('+2 days'))]);
q_run("UPDATE buddy_posts SET status='completed' WHERE id=?", [$startedPost]);
$dn = rmt_buddy_dashboard(12);
ok('a completed trip moves to past trips', count($dn['pastPosts']) === 1 && !array_filter($dn['posts'], static fn($x) => (int) $x['id'] === $startedPost));
ok('a completed trip is not listed', !in_array('post:now', $who(rmt_buddy_search(rmt_buddy_filters(['where' => 'Bangkok', 'show' => 'going']), $me)['cards']), true));

/* ---- examples ---- */
require BASE_PATH . '/app/buddy_examples.php';
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (6,'rome-italy','Rome','Italy'),(7,'tulum-mexico','Tulum','Mexico'),(8,'lisbon-portugal','Lisbon','Portugal'),(9,'barcelona-spain','Barcelona','Spain')");
$ex = rmt_buddy_examples();
ok('examples cover the brief', count(array_intersect(['Tokyo', 'Bangkok', 'Paris', 'Rome', 'Tulum', 'Lisbon', 'Barcelona'], array_column($ex, 'dest_name'))) === 7
   && count(array_filter($ex, fn($c) => $c['type'] === 'cruise')) >= 2 && array_filter($ex, fn($c) => $c['here_now']) && array_filter($ex, fn($c) => $c['kind'] === 'local'));
ok('every example is flagged, has no account and no person', !array_filter($ex, fn($c) => !$c['example'] || $c['user_id'] !== 0 || $c['name'] !== 'Example traveler' || $c['avatar_url'] !== null));
ok('no example is in the past', !array_filter($ex, fn($c) => $c['to'] !== '' && $c['to'] < date('Y-m-d')));
ok('examples follow a country filter', array_column(rmt_buddy_examples_for(rmt_buddy_filters(['where' => 'thailand'])), 'dest_name') === ['Bangkok']);
ok('examples follow a ship filter', count(rmt_buddy_examples_for(rmt_buddy_filters(['type' => 'cruise', 'ship' => 'icon of the seas']))) === 2);
ok('examples follow a date filter', rmt_buddy_examples_for(rmt_buddy_filters(['where' => 'Tokyo', 'from' => date('Y-m-d', strtotime('+200 days'))])) === []);
ok('two examples share a sailing', (rmt_buddy_examples_for(rmt_buddy_filters(['type' => 'cruise', 'ship' => 'Icon of the Seas']))[0]['on_sailing'] ?? 0) === 1);
ok('examples never touch the database', (int) q_one('SELECT COUNT(*) n FROM buddy_posts WHERE title LIKE ?', ['%Tokyo and Kyoto%'])['n'] === 0);

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
