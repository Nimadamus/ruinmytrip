<?php
/**
 * Photos: who may see one, what is next to one, and whose photos appear on a city wall.
 *
 * A photograph now has a page of its own and can be liked and replied to, which makes it a new
 * surface for the oldest mistake in this codebase: showing something because it exists rather than
 * because the reader is allowed to see it. The city photo wall had exactly that bug before this
 * file existed, selecting every trip photo in a city with no visibility clause at all, so a trip
 * marked "only you" had its photographs on a public page.
 *
 * What must hold:
 *   - a photo is exactly as private as the thing it belongs to
 *   - a draft parent has no photo page at all
 *   - the city wall and the profile wall show only what the viewer may see
 *   - walking an album with prev/next stays inside one parent and stops at both ends
 *
 *   php tests/photos_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/plans.php';
require BASE_PATH . '/app/photos.php';

function dest_by_id(int $id): ?array { return q_one('SELECT * FROM destinations WHERE id=?', [$id]); }
function rmt_review_path(array $r): string { return '/review/' . (int) $r['parent_id']; }
function author(int $id): array {
    $u = q_one('SELECT username FROM users WHERE id=?', [$id]);
    return ['username' => (string) ($u['username'] ?? ''), 'display_name' => null, 'avatar_url' => null];
}
function authors_fill(array &$rows): void {
    foreach ($rows as $i => $r) $rows[$i]['author'] = author((int) ($r['user_id'] ?? 0));
}

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active', role TEXT DEFAULT 'user')");
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT)');
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, body TEXT, status TEXT, visibility TEXT, date_from TEXT, date_to TEXT)");
$pdo->exec("CREATE TABLE trip_photos (id INTEGER PRIMARY KEY, trip_id INT, url TEXT, caption TEXT,
              sort INT, width INT, height INT, storage_key TEXT, created_at TEXT, user_id INT,
              status TEXT DEFAULT 'published')");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, subject_name TEXT, status TEXT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY, review_id INT, url TEXT, caption TEXT,
              sort INT, width INT, height INT, storage_key TEXT, created_at TEXT, user_id INT,
              status TEXT DEFAULT 'published')");
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, body TEXT,
              image_url TEXT, status TEXT, created_at TEXT)");

$pdo->exec("INSERT INTO destinations VALUES (7,'lisbon-portugal','Lisbon')");
$pdo->exec("INSERT INTO users (id,username) VALUES (1,'ana'),(2,'ben'),(3,'cleo')");
$pdo->exec("INSERT INTO follows VALUES (3,1)");   // cleo follows ana

$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,body,status,visibility) VALUES
  (1,1,7,'Public trip','pt','','published','public'),
  (2,1,7,'Followers trip','ft','','published','followers'),
  (3,1,7,'Private trip','vt','','published','private'),
  (4,1,7,'Draft trip','dt','','draft','public')");
$pdo->exec("INSERT INTO trip_photos (id,trip_id,url,caption,sort,created_at,user_id) VALUES
  (1,1,'/media/a.jpg','First light',0,'2026-05-01 10:00:00',1),
  (2,1,'/media/b.jpg','The hill',1,'2026-05-01 11:00:00',1),
  (3,1,'/media/c.jpg','',2,'2026-05-01 12:00:00',1),
  (4,2,'/media/d.jpg','Followers only',0,'2026-05-02 10:00:00',1),
  (5,3,'/media/e.jpg','Only me',0,'2026-05-03 10:00:00',1),
  (6,4,'/media/f.jpg','Draft',0,'2026-05-04 10:00:00',1)");
$pdo->exec("INSERT INTO reviews (id,user_id,destination_id,title,slug,subject_name,status) VALUES
  (1,2,7,'A hotel','a-hotel','Hotel Foo','published')");
$pdo->exec("INSERT INTO review_photos (id,review_id,url,caption,sort,created_at,user_id) VALUES
  (1,1,'/media/g.jpg','The lobby',0,'2026-05-05 10:00:00',2)");
$pdo->exec("INSERT INTO posts (id,user_id,destination_id,body,image_url,status,created_at) VALUES
  (1,2,7,'Tram queue at nine','/media/h.jpg','published','2026-05-06 10:00:00')");

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $m\n"; } }

$owner = ['id' => 1, 'role' => 'user'];
$follower = ['id' => 3, 'role' => 'user'];
$stranger = ['id' => 2, 'role' => 'user'];

// --- one photo, and who may see it -------------------------------------------------------
$pub = rmt_photo_get('trip', 1);
ok($pub !== null && $pub['caption'] === 'First light', 'a public trip photo loads with its caption');
ok($pub !== null && $pub['parent_title'] === 'Public trip', 'it knows what it belongs to');
ok($pub !== null && $pub['dest_name'] === 'Lisbon', 'and where it was taken');
ok(rmt_photo_visible_to($pub, null), 'a public photo is visible to a stranger who is not signed in');

$fol = rmt_photo_get('trip', 4);
ok(!rmt_photo_visible_to($fol, null), 'a followers-only photo is not public');
ok(!rmt_photo_visible_to($fol, $stranger), 'a followers-only photo is hidden from a non-follower');
ok(rmt_photo_visible_to($fol, $follower), 'a follower sees it');
ok(rmt_photo_visible_to($fol, $owner), 'the owner sees their own');

$priv = rmt_photo_get('trip', 5);
ok(!rmt_photo_visible_to($priv, null) && !rmt_photo_visible_to($priv, $follower)
   && !rmt_photo_visible_to($priv, $stranger), 'a private photo is visible to nobody but its owner');
ok(rmt_photo_visible_to($priv, $owner), 'the owner still sees it');
ok(rmt_photo_visible_to($priv, ['id' => 9, 'role' => 'admin']), 'a moderator can see it, for moderation');

ok(rmt_photo_get('trip', 6) === null, 'a draft trip has no photo page');
ok(rmt_photo_get('trip', 999) === null, 'an unknown photo is nothing');
ok(rmt_photo_get('nonsense', 1) === null, 'an unknown kind is nothing');

// --- walking an album --------------------------------------------------------------------
$sib = rmt_photo_siblings(rmt_photo_get('trip', 2));
ok($sib['prev'] === 1 && $sib['next'] === 3, 'the middle photo has one either side');
ok($sib['index'] === 2 && $sib['total'] === 3, 'and knows where it is in the album');
$first = rmt_photo_siblings(rmt_photo_get('trip', 1));
ok($first['prev'] === null && $first['next'] === 2, 'the first photo has nothing before it');
$last = rmt_photo_siblings(rmt_photo_get('trip', 3));
ok($last['next'] === null, 'the last photo has nothing after it');
$alone = rmt_photo_siblings(rmt_photo_get('trip', 4));
ok($alone['prev'] === null && $alone['next'] === null && $alone['total'] === 1,
   'an album of one does not borrow from another trip');

// --- the city wall -----------------------------------------------------------------------
$ids = static fn(array $rows): array => array_map(static fn(array $r) => $r['kind'] . ':' . (int) $r['id'], $rows);

$wall = $ids(rmt_city_photos(7, null));
ok(in_array('trip:1', $wall, true), 'a public trip photo is on the city wall');
ok(in_array('review:1', $wall, true) && in_array('post:1', $wall, true),
   'review and post photos are on it too');
ok(!in_array('trip:4', $wall, true), 'a followers-only photo is not shown to a stranger');
ok(!in_array('trip:5', $wall, true), 'a private photo is never on a public wall');
ok(!in_array('trip:6', $wall, true), 'a draft trip is not on it either');

ok(in_array('trip:4', $ids(rmt_city_photos(7, $follower)), true), 'a follower sees it on the wall');
ok(!in_array('trip:5', $ids(rmt_city_photos(7, $follower)), true), 'a follower still does not see a private one');
ok(in_array('trip:5', $ids(rmt_city_photos(7, $owner)), true), 'the owner sees their own private photo there');

// --- the profile wall --------------------------------------------------------------------
$mine = $ids(rmt_member_photos(1, null));
ok(in_array('trip:1', $mine, true) && !in_array('trip:4', $mine, true) && !in_array('trip:5', $mine, true),
   'a profile wall shows a stranger only the public photos');
ok(in_array('trip:4', $ids(rmt_member_photos(1, $follower)), true), 'a follower sees the followers-only ones');
ok(in_array('trip:5', $ids(rmt_member_photos(1, $owner)), true), 'and the owner sees everything of theirs');

// --- captions ----------------------------------------------------------------------------
ok(rmt_photo_trim('A short one', 40) === 'A short one', 'a short caption is left alone');
ok(str_ends_with(rmt_photo_trim(str_repeat('word ', 40), 30), '...'), 'a long one is cut with an ellipsis');
ok(!str_contains(rmt_photo_trim('<b>bold</b> and more', 40), '<'), 'markup never survives a caption');

echo "photos_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
