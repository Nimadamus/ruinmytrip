<?php
/**
 * Share cards: visibility rules, wrapping, and that the PNG is really a 1200x630 PNG.
 *
 *   php tests/share_card_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/cards.php';
require BASE_PATH . '/app/plans.php';
const RMT_MEETUP_STATUSES = ['published', 'cancelled'];

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT, role TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INT, display_name TEXT, bio TEXT, home_city TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec("CREATE TABLE collections (id INTEGER PRIMARY KEY, user_id INT, slug TEXT, title TEXT, summary TEXT, status TEXT DEFAULT 'published')");
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT, collection_id INT, repost_of INT, body TEXT, status TEXT DEFAULT 'published', created_at TEXT)");
$pdo->exec("CREATE TABLE comments (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, target_type TEXT, target_id INT, status TEXT DEFAULT 'published')");
$pdo->exec("CREATE TABLE post_polls (post_id INTEGER PRIMARY KEY, closes_at TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, place_id INT, subject_name TEXT, rating INT, title TEXT, body TEXT, what_ruined TEXT, status TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY, name TEXT)");
$pdo->exec("CREATE TABLE follows (follower_id INT, followee_id INT)");
$pdo->exec("CREATE TABLE meetups (id INTEGER PRIMARY KEY, host_id INT, destination_id INT, title TEXT, date_start TEXT, status TEXT)");
$pdo->exec("CREATE TABLE meetup_rsvps (meetup_id INT, user_id INT, status TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT, slug TEXT, body TEXT, cover_url TEXT, date_from TEXT, date_to TEXT, visibility TEXT DEFAULT 'public', status TEXT DEFAULT 'published', created_at TEXT)");
$pdo->exec("CREATE TABLE trip_photos (id INTEGER PRIMARY KEY, trip_id INT, url TEXT, sort INT)");
$pdo->exec("CREATE TABLE trip_activities (id INTEGER PRIMARY KEY, trip_id INT, user_id INT,
              destination_id INT, day TEXT, start_time TEXT, title TEXT, category TEXT,
              visibility TEXT DEFAULT 'trip', join_mode TEXT DEFAULT 'no', capacity INT,
              cancelled_at TEXT, status TEXT DEFAULT 'published', created_at TEXT)");
$pdo->exec("CREATE TABLE activity_joins (activity_id INT, user_id INT, state TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE tags (id INTEGER PRIMARY KEY, name TEXT)");
$pdo->exec("CREATE TABLE taggings (id INTEGER PRIMARY KEY, tag_id INT, target_type TEXT, target_id INT)");
$pdo->exec("INSERT INTO users VALUES (1,'ana','active','user'),(2,'gone','suspended','user')");
$pdo->exec("INSERT INTO profiles VALUES (1,'Ana R.','Slow traveler. Hates queues.','Lisbon')");
$pdo->exec("INSERT INTO destinations VALUES (1,'lisbon-portugal','Lisbon')");
$pdo->exec("INSERT INTO collections VALUES (1,1,'solo-women-se-asia','Solo women in SE Asia','Routes, scams, and the good hostels.','published')");
$pdo->exec("INSERT INTO posts (user_id,destination_id,collection_id,body,status,created_at) VALUES
  (1,1,1,'The Tram 28 queue at 9am was ninety minutes. Walk it instead, the route is two miles.','published','2026-01-01'),
  (1,NULL,NULL,'removed one','removed','2026-01-01'),
  (2,NULL,NULL,'by a suspended member','published','2026-01-01')");
$pdo->exec("INSERT INTO comments (user_id,target_type,target_id) VALUES (1,'post',1),(1,'post',1)");
$pdo->exec("INSERT INTO reviews VALUES (1,1,1,NULL,'Hotel Foo',2,'Lovely lobby, broken everything else','body','No hot water for three days','published')");
$pdo->exec("INSERT INTO meetups VALUES (1,1,1,'Sunset walk to Miradouro','2026-10-04','published'),(2,1,1,'draft','2026-10-04','draft')");
$soon  = date('Y-m-d', strtotime('+40 days'));
$soon2 = date('Y-m-d', strtotime('+47 days'));
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,date_from,date_to,visibility,status) VALUES
  (1,1,1,'Lisbon, slowly','lisbon-slowly','$soon','$soon2','public','published'),
  (2,2,1,'Same week','same-week','$soon','$soon2','public','published'),
  (3,1,1,'Only me','only-me','$soon','$soon2','private','published'),
  (4,1,1,'Draft','draft','$soon','$soon2','public','draft'),
  (5,1,1,'No dates','no-dates',NULL,NULL,'public','published'),
  (6,1,1,'Followers only','followers-only','$soon','$soon2','followers','published')");
$pdo->exec("INSERT INTO trip_photos VALUES (1,1,'/media/a.jpg',0)");
/* Plans: one on a public trip and open to anybody, one on a private trip, one marked private
   itself. A card is drawn for a stranger holding a link, so the last two must draw nothing. */
$pdo->exec("INSERT INTO trip_activities (id,trip_id,user_id,destination_id,day,start_time,title,category,visibility,join_mode)
            VALUES (1,1,1,1,'2026-10-13','20:00','Benfica vs Porto','sport','trip','open'),
                   (2,3,1,1,'2026-10-13',NULL,'On a private trip','other','trip','open'),
                   (3,1,1,1,'2026-10-13',NULL,'A private plan','other','private','open')");
$pdo->exec("INSERT INTO activity_joins VALUES (1,2,'going','2026-09-01')");
$pdo->exec("INSERT INTO tags VALUES (1,'scams')");
$pdo->exec("INSERT INTO taggings VALUES (1,1,'post',1)");

$pass = 0; $fail = 0;
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $msg\n"; } }

ok(rmt_card_available(), 'GD + bundled font present');

$s = rmt_card_spec('post', '1');
ok($s !== null && str_starts_with($s['title'], 'The Tram 28') && $s['meta'] === '@ana · Lisbon', 'post spec carries quote, author, city');
ok(in_array('Solo women in SE Asia', $s['pills'], true) && in_array('2 replies', $s['pills'], true), 'post pills: community + replies');
ok($s['kicker'] === 'Travel talk', 'post kicker');
$pdo->exec("INSERT INTO post_polls VALUES (1,'2099-01-01','2026-01-01')");
ok(rmt_card_spec('post', '1')['kicker'] === 'Poll', 'poll post says Poll');
ok(rmt_card_spec('post', '2') === null, 'removed post has no card');
ok(rmt_card_spec('post', '3') === null, 'suspended author has no card');
ok(rmt_card_spec('post', '999') === null, 'unknown post has no card');

$s = rmt_card_spec('review', '1');
ok($s !== null && $s['rating'] === 2 && $s['meta'] === '@ana · Hotel Foo, Lisbon' && $s['kicker'] === 'Honest review', 'review spec');
ok(str_starts_with($s['pills'][0] ?? '', 'What ruined it:'), 'review pill quotes what ruined it');

$s = rmt_card_spec('c', 'solo-women-se-asia');
ok($s !== null && $s['title'] === 'Solo women in SE Asia' && in_array('1 post', $s['pills'], true), 'community spec');
ok(rmt_card_spec('c', 'nope') === null, 'unknown community');

$s = rmt_card_spec('u', 'ana');
ok($s !== null && $s['title'] === 'Ana R.' && str_starts_with($s['meta'], '@ana · Lisbon · Slow traveler') && in_array('1 review', $s['pills'], true) && in_array('1 post', $s['pills'], true), 'profile spec');
ok(rmt_card_spec('u', 'gone') === null, 'suspended profile has no card');

$s = rmt_card_spec('meetup', '1');
ok($s !== null && $s['title'] === 'Sunset walk to Miradouro' && str_contains($s['meta'], 'Lisbon') && str_contains($s['meta'], '@ana'), 'meetup spec');
ok(rmt_card_spec('meetup', '2') === null, 'draft meetup has no card');

$s = rmt_card_spec('tag', 'scams');
ok($s !== null && $s['title'] === '#scams' && $s['pills'] === ['1 post'], 'tag spec');
ok(rmt_card_spec('bogus', '1') === null, 'unknown kind');

/* Trips. A trip is the object people actually paste into a group chat, so the card has to carry
   the city, the dates and the fact that somebody else is there in the same week, and it has to
   refuse to draw anything at all for a trip its owner did not make public. */
$dot = " \u{b7} ";
$s = rmt_card_spec('trip', '1');
ok($s !== null && $s['title'] === 'Lisbon, slowly', 'trip spec has the title');
ok($s !== null && str_starts_with($s['meta'], '@ana' . $dot . 'Lisbon' . $dot), 'trip meta carries author and city');
ok($s !== null && $s['kicker'] === 'Going', 'an upcoming trip says Going');
ok($s !== null && in_array('1 other traveler there', $s['pills'], true), 'overlapping public trip is counted');
ok($s !== null && in_array('1 photo', $s['pills'], true), 'photos are counted');
ok(rmt_card_spec('trip', '3') === null, 'a private trip has no card');
ok(rmt_card_spec('trip', '6') === null, 'a followers-only trip has no card');
ok(rmt_card_spec('trip', '4') === null, 'a draft trip has no card');
ok(rmt_card_spec('trip', '999') === null, 'unknown trip has no card');
$s = rmt_card_spec('trip', '5');
ok($s !== null && $s['kicker'] === 'Trip' && $s['meta'] === '@ana' . $dot . 'Lisbon', 'an undated trip says Trip and shows no dates');
ok($s !== null && $s['pills'] === [], 'an undated trip claims no company');
ok(getimagesizefromstring(rmt_card_render(rmt_card_spec('trip', '1'))) !== false, 'trip card renders');

/* Date ranges read as a person would write them, and never with a dash. */
ok(rmt_card_date_range('2026-03-12', '2026-03-19') === '12 to 19 Mar 2026', 'same month range');
ok(rmt_card_date_range('2026-03-28', '2026-04-02') === '28 Mar to 2 Apr 2026', 'range across months');
ok(rmt_card_date_range('2026-12-28', '2027-01-03') === '28 Dec 2026 to 3 Jan 2027', 'range across years');
ok(rmt_card_date_range('2026-03-12', '2026-03-12') === '12 Mar 2026', 'a single day is one date');
ok(rmt_card_date_range('', '') === '', 'no dates is empty');

// wrapping
$font = rmt_card_font(true);
$lines = rmt_card_wrap(str_repeat('word ', 80), $font, 60, 1056, 4);
ok(count($lines) === 4 && str_ends_with($lines[3], '…'), 'long text capped at 4 lines with ellipsis');
$lines = rmt_card_wrap('https://example.com/' . str_repeat('a', 120), $font, 60, 1056, 4);
ok(count($lines) >= 2 && rmt_card_text_width($lines[0], $font, 60) <= 1056, 'runaway word is broken, nothing overflows');
ok(rmt_card_wrap('   ', $font, 60, 1056, 4) === [], 'blank wraps to nothing');

// render
$png = rmt_card_render(rmt_card_spec('review', '1'));
ok(substr($png, 0, 8) === "\x89PNG\r\n\x1a\n", 'output is a PNG');
$info = getimagesizefromstring($png);
ok($info !== false && $info[0] === 1200 && $info[1] === 630, 'card is 1200x630');
ok(strlen($png) < 200000, 'card is small enough for scrapers (' . strlen($png) . ' bytes)');
$png2 = rmt_card_render(['title' => 'Ünïcödé — “quotes” and #hashtags ★', 'meta' => '@x']);
ok(getimagesizefromstring($png2) !== false, 'unicode title renders');

/* Plans. The thing somebody actually pastes into a group chat is not the trip, it is the Friday
   night, so it carries its own card, and the same refusal. */
$s = rmt_card_spec('activity', '1');
ok($s !== null && $s['title'] === 'Benfica vs Porto', 'a plan has a card');
ok($s !== null && $s['kicker'] === 'You can join this', 'and it says whether the reader can come');
ok($s !== null && in_array('1 person going', $s['pills'], true), 'one person going is counted as one');
ok($s !== null && str_contains($s['meta'], '@ana'), 'the card names the traveler whose plan it is');
ok(rmt_card_spec('activity', '2') === null, 'a plan on a private trip has no card');
ok(rmt_card_spec('activity', '3') === null, 'a plan marked private has no card');
ok(rmt_card_spec('activity', '999') === null, 'an unknown plan has no card');
ok(getimagesizefromstring(rmt_card_render(rmt_card_spec('activity', '1'))) !== false, 'plan card renders');

echo "share_card_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
