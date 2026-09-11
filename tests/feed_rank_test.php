<?php
/**
 * What the feed puts first.
 *
 * The feed has to answer one question: what matters to my travel life right now. That means the
 * ordering is not "newest", and it is not "most liked" either. A plan on a day the reader is
 * actually in that city, which they are allowed to join, beats a newer post from somebody they
 * follow, and popularity is only ever a tiebreak.
 *
 * The reason line is checked as carefully as the score. Every row that moves up says why in plain
 * words, because a feed that reorders silently is a feed nobody trusts, and the reason must never
 * be something the reader cannot verify.
 *
 *   php tests/feed_rank_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/plans.php';
require BASE_PATH . '/app/going.php';
require BASE_PATH . '/app/matching.php';
require BASE_PATH . '/app/feed_scope.php';
require BASE_PATH . '/app/feed_home.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active')");
$pdo->exec("CREATE TABLE profiles (user_id INT, avatar_url TEXT, display_name TEXT, open_to_meeting INT, home_city TEXT, home_destination_id INT, travel_style TEXT)");
$pdo->exec("CREATE TABLE follows (followee_id INT, follower_id INT)");
$pdo->exec("CREATE TABLE blocks (blocker_id INT, blocked_id INT)");
$pdo->exec("CREATE TABLE saves (user_id INT, target_type TEXT, target_id INT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, status TEXT DEFAULT 'published', visibility TEXT DEFAULT 'public',
              date_from TEXT, date_to TEXT)");
$pdo->exec("CREATE TABLE trip_members (trip_id INT, user_id INT, role TEXT, state TEXT,
              invited_by INT, created_at TEXT, decided_at TEXT, PRIMARY KEY (trip_id, user_id))");

$from = date('Y-m-d', strtotime('+10 days'));
$to   = date('Y-m-d', strtotime('+17 days'));
$mid  = date('Y-m-d', strtotime('+12 days'));
$after = date('Y-m-d', strtotime('+40 days'));

$pdo->exec("INSERT INTO users (id,username) VALUES (1,'me'),(2,'ben'),(3,'cara')");
$pdo->exec("INSERT INTO destinations VALUES (1,'Lisbon','lisbon'),(2,'Porto','porto')");
// The reader is going to Lisbon in ten days.
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,date_from,date_to)
            VALUES (1,1,1,'Mine','mine','$from','$to')");

$now = date('Y-m-d H:i:s');
$hourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));

$item = static fn(array $over): array => $over + [
    'id' => 0, 'kind' => 'activity', 'user_id' => 2, 'destination_id' => 1,
    'dest_name' => 'Lisbon', 'created_at' => $hourAgo, 'day' => null, 'join_mode' => 'no',
];

$items = [
    $item(['id' => 1, 'day' => $mid, 'join_mode' => 'open']),      // on my dates, joinable
    $item(['id' => 2, 'day' => $after, 'join_mode' => 'open']),    // same city, not my dates
    $item(['id' => 3, 'day' => $mid, 'join_mode' => 'no']),        // my dates, cannot come
    $item(['id' => 4, 'kind' => 'post', 'created_at' => $now]),    // newer, from the same city
    $item(['id' => 5, 'destination_id' => 2, 'dest_name' => 'Porto', 'day' => $mid, 'join_mode' => 'open']),
];

$ranked = rmt_feed_rank($items, 1);
$order = array_map(static fn(array $r) => (int) $r['id'], $ranked);
$byId = [];
foreach ($ranked as $r) $byId[(int) $r['id']] = $r;

ok($order[0] === 1, 'a plan on your dates that you can join goes first');
ok(array_search(3, $order, true) < array_search(2, $order, true),
   'your dates beat the same city on another week');
ok($byId[1]['feed_score'] > $byId[3]['feed_score'],
   'and being able to come beats not being able to');
ok($byId[1]['feed_score'] > $byId[4]['feed_score'],
   'a newer post does not outrank something you could turn up to');
ok($byId[5]['feed_score'] < $byId[1]['feed_score'], 'another city is another city');

ok($byId[1]['feed_reason'] === 'On while you are in Lisbon',
   'and it says why, in words the reader can check');
ok($byId[2]['feed_reason'] !== 'On while you are in Lisbon',
   'a plan outside your dates never claims to be on while you are there');
ok($byId[5]['feed_reason'] === 'Open to other travelers',
   'a joinable plan elsewhere says the true thing about itself');

/* Your own rows are not news to you, and they must not be dressed up as a reason either. */
$mineRanked = rmt_feed_rank([$item(['id' => 9, 'user_id' => 1, 'day' => $mid, 'join_mode' => 'open'])], 1);
ok($mineRanked[0]['feed_reason'] === '', 'your own plan is not explained back to you');

/* Popularity is a tiebreak and never the ranking: forty likes on a plan in another city must not
   beat one you can walk into. */
$eng = ['likes' => ['activity:5' => 40], 'comments' => [], 'mine' => []];
$ranked2 = rmt_feed_rank($items, 1, $eng);
ok((int) $ranked2[0]['id'] === 1, 'forty likes elsewhere do not outrank your own dates');


// --- the cases a ranking has to get right to be worth having -------------------------------------
/* Each of these is a pair the feed could plausibly get backwards, written as "this beats that"
   rather than as a score, because a score is an implementation detail and an order is a promise. */
$pdo->exec("INSERT INTO follows VALUES (3,1)");          // the reader follows Cara
$dayOld = date('Y-m-d H:i:s', strtotime('-4 days'));
$minsOld = date('Y-m-d H:i:s', strtotime('-10 minutes'));

$beats = static function (array $a, array $b, string $what) use ($item): void {
    $r = rmt_feed_rank([$a, $b], 1);
    ok((int) $r[0]['id'] === (int) $a['id'], $what);
};

// Old but relevant beats new but not.
$beats(
    $item(['id' => 11, 'day' => $mid, 'join_mode' => 'open', 'created_at' => $dayOld]),
    $item(['id' => 12, 'kind' => 'post', 'destination_id' => 2, 'dest_name' => 'Porto',
           'user_id' => 3, 'created_at' => $minsOld]),
    'a four day old plan on your dates beats a ten minute old post from somewhere else'
);

// Somebody on your dates beats somebody you follow, somewhere you are not going.
$beats(
    $item(['id' => 13, 'kind' => 'trip', 'day' => null, 'user_id' => 2, 'created_at' => $hourAgo]),
    $item(['id' => 14, 'kind' => 'trip', 'destination_id' => 2, 'dest_name' => 'Porto',
           'user_id' => 3, 'created_at' => $hourAgo]),
    'a traveler in the city you are going to beats one you follow who is not'
);

// Something you can join beats something you can only look at.
$beats(
    $item(['id' => 15, 'day' => $mid, 'join_mode' => 'ask', 'created_at' => $hourAgo]),
    $item(['id' => 16, 'kind' => 'photo', 'created_at' => $hourAgo]),
    'a plan you can ask to join beats a photograph of the same city'
);

// Tomorrow beats six months away.
$soonDay = date('Y-m-d', strtotime('+11 days'));
$farDay  = date('Y-m-d', strtotime('+180 days'));
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,date_from,date_to)
            VALUES (2,1,1,'Later','later','$farDay','" . date('Y-m-d', strtotime('+187 days')) . "')");
$beats(
    $item(['id' => 17, 'day' => $soonDay, 'join_mode' => 'open', 'created_at' => $hourAgo]),
    $item(['id' => 18, 'day' => $farDay, 'join_mode' => 'open', 'created_at' => $hourAgo]),
    'a plan on the trip that starts next week beats one on the trip six months out'
);

/* And the rule that keeps all of it honest: nothing is promoted without a reason the reader can
   read. A row that moved up and says nothing is a feed asking to be trusted. */
$all = rmt_feed_rank([
    $item(['id' => 21, 'day' => $mid, 'join_mode' => 'open']),
    $item(['id' => 22, 'day' => $farDay, 'join_mode' => 'open']),
    $item(['id' => 23, 'kind' => 'photo']),
    $item(['id' => 24, 'kind' => 'post', 'destination_id' => 2, 'dest_name' => 'Porto', 'user_id' => 3]),
], 1);
$unexplained = [];
foreach ($all as $row) {
    if (!empty($row['feed_boosted']) && trim((string) $row['feed_reason']) === '') {
        $unexplained[] = (int) $row['id'];
    }
}
ok($unexplained === [], 'every row the feed pushed up says why'
   . ($unexplained ? ': ' . implode(', ', $unexplained) : ''));

echo "feed_rank_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
