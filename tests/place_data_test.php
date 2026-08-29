<?php
/**
 * Migration 047: place attributes, slug history, hours, photos, structured data.
 *
 * The thing being defended here is not "the columns exist" — it is that an unknown value stays
 * unknown. Every rejection case below is a case where a lazier implementation would have written
 * something plausible and wrong: a coordinate of (0,0), a phone number with no digits, a price
 * level of 9, a "Closed" for a day nobody told us about.
 *
 *   php tests/place_data_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/editorial.php';
require BASE_PATH . '/app/places.php';
require BASE_PATH . '/app/place_data.php';
require BASE_PATH . '/app/reviews.php';
require BASE_PATH . '/app/storage.php';
require BASE_PATH . '/app/seo.php';

// authors_fill() lives in controllers.php, which a unit test does not load. Same stub as
// tests/places_test.php: the gallery's shape is what is under test here, not author hydration.
function authors_fill(array &$rows, string $idField = 'user_id'): void {}

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-60s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

/* ---------------- schema, built by the real migration ---------------- */

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT)");
$pdo->exec("CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, avatar_url TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, region TEXT, hero_url TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT, slug TEXT UNIQUE,
            name TEXT, name_key TEXT, type TEXT, created_by INT, status TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, place_id INT,
            slug TEXT, title TEXT, body TEXT, rating INT, subject_name TEXT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, review_id INT, url TEXT,
            storage_key TEXT, caption TEXT, sort INT, created_at TEXT)");

// The migration itself is the schema under test: a hand-written CREATE TABLE here would let the
// migration drift from what the tests prove.
$sql = file_get_contents(BASE_PATH . '/database/migrations/047_place_attributes.sqlite.sql');
$pdo->exec($sql);

$pdo->exec("INSERT INTO users (id,username,role,status) VALUES (1,'traveler','user','active'),(2,'ruinmytrip','editorial','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country,region) VALUES (1,'lisbon-portugal','Lisbon','Portugal','Lisboa')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,created_at)
            VALUES (1,1,'cervejaria-ramiro-lisbon','Cervejaria Ramiro','cervejaria ramiro','restaurant','active','2026-08-01')");

echo "-- migration shape --\n";
$cols = array_column($pdo->query("PRAGMA table_info(places)")->fetchAll(), 'name');
foreach (['category_id','street_address','neighborhood','region','postal_code','lat','lng','phone',
          'website_url','price_level','timezone','data_source','data_source_url','data_checked_at'] as $c) {
    check("places.$c exists", in_array($c, $cols, true), true);
}
check('taxonomy seeded', (int) $pdo->query('SELECT COUNT(*) FROM place_categories')->fetchColumn() > 40, true);
check('every category belongs to a real place type',
      (int) $pdo->query("SELECT COUNT(*) FROM place_categories WHERE bucket NOT IN ('hotel','restaurant','attraction','experience')")->fetchColumn(), 0);
check('no place was given attributes by the migration',
      (int) $pdo->query('SELECT COUNT(*) FROM places WHERE lat IS NOT NULL OR phone IS NOT NULL OR price_level IS NOT NULL')->fetchColumn(), 0);
check('new tables start empty',
      (int) $pdo->query('SELECT (SELECT COUNT(*) FROM place_photos)+(SELECT COUNT(*) FROM place_hours)+(SELECT COUNT(*) FROM place_slug_history)')->fetchColumn(), 0);

echo "\n-- website validation --\n";
check('bare domain gets https',  rmt_place_normalize_website('ramiro.pt'), 'https://ramiro.pt');
check('http is kept',            rmt_place_normalize_website('http://ramiro.pt/menu'), 'http://ramiro.pt/menu');
check('host is lowercased',      rmt_place_normalize_website('HTTPS://Ramiro.PT'), 'https://ramiro.pt');
check('query survives',          rmt_place_normalize_website('https://a.com/x?y=1'), 'https://a.com/x?y=1');
check('javascript: rejected',    rmt_place_normalize_website('javascript:alert(1)'), null);
check('data: rejected',          rmt_place_normalize_website('data:text/html,x'), null);
check('mailto rejected',         rmt_place_normalize_website('mailto:a@b.com'), null);
check('dotless host rejected',   rmt_place_normalize_website('localhost'), null);
check('empty is null',           rmt_place_normalize_website(''), null);

echo "\n-- phone validation --\n";
check('punctuation kept',        rmt_place_normalize_phone('+351 21 886 2184'), '+351 21 886 2184');
check('letters stripped',        rmt_place_normalize_phone('call 555 123 4567'), '555 123 4567');
check('too few digits rejected', rmt_place_normalize_phone('12345'), null);
check('no digits rejected',      rmt_place_normalize_phone('call us!'), null);

echo "\n-- price level --\n";
check('2 is 2',        rmt_place_normalize_price_level('2'), 2);
check('0 rejected',    rmt_place_normalize_price_level(0), null);
check('9 rejected',    rmt_place_normalize_price_level(9), null);
check('word rejected', rmt_place_normalize_price_level('cheap'), null);
check('label',         rmt_place_price_label(3), '$$$');
check('null label',    rmt_place_price_label(null), null);

echo "\n-- coordinates --\n";
check('valid pair',       rmt_place_normalize_coords('38.7139', '-9.1394'), [38.7139, -9.1394]);
check('null island out',  rmt_place_normalize_coords(0, 0), null);
check('lat only out',     rmt_place_normalize_coords('38.7', null), null);
check('out of range out', rmt_place_normalize_coords(91, 10), null);
check('junk out',         rmt_place_normalize_coords('north', 'west'), null);

echo "\n-- writing attributes --\n";
$errs = rmt_place_update_attributes(1, [
    'street_address' => 'Av. Almirante Reis 1', 'postal_code' => '1150-007',
    'lat' => '38.7205', 'lng' => '-9.1350', 'phone' => '+351 21 885 1024',
    'website_url' => 'cervejariaramiro.com', 'price_level' => '3', 'timezone' => 'Europe/Lisbon',
]);
check('valid write has no errors', $errs, []);
$p = rmt_place_by_slug('cervejaria-ramiro-lisbon');
check('street stored',   $p['street_address'], 'Av. Almirante Reis 1');
check('website normalised', $p['website_url'], 'https://cervejariaramiro.com');
check('price stored',    (int) $p['price_level'], 3);
check('lat stored',      round((float) $p['lat'], 4), 38.7205);
check('checked_at set',  is_string($p['data_checked_at']) && $p['data_checked_at'] !== '', true);

$bad = rmt_place_update_attributes(1, ['phone' => 'nope', 'website_url' => 'javascript:x']);
check('bad values are reported', count($bad), 2);
$p2 = rmt_place_by_slug('cervejaria-ramiro-lisbon');
check('a rejected write does not overwrite a good value', $p2['phone'], '+351 21 885 1024');

$catRow = $pdo->query("SELECT id FROM place_categories WHERE slug='seafood'")->fetch();
check('matching category accepted', rmt_place_update_attributes(1, ['category_id' => (int) $catRow['id']]), []);
$hotelCat = $pdo->query("SELECT id FROM place_categories WHERE slug='hostel'")->fetch();
check('category from another bucket rejected',
      array_key_first(rmt_place_update_attributes(1, ['category_id' => (int) $hotelCat['id']])), 'category_id');

echo "\n-- address assembly --\n";
$addr = rmt_place_address(rmt_place_by_slug('cervejaria-ramiro-lisbon'));
check('locality comes from the destination', $addr['locality'], 'Lisbon');
check('region falls back to the destination', $addr['region'], 'Lisboa');
check('country comes from the destination', $addr['country'], 'Portugal');
check('lines are assembled in order', $addr['lines'], ['Av. Almirante Reis 1', 'Lisbon Lisboa 1150-007', 'Portugal']);
// Prague's region is also called Prague. "Prague Prague 110 00" is not an address.
db()->exec("UPDATE destinations SET name='Prague', region='Prague' WHERE id=1");
$dupe = rmt_place_address(rmt_place_by_slug('cervejaria-ramiro-lisbon'));
check('a region identical to the city is not printed twice', $dupe['lines'][1] ?? null, 'Prague 1150-007');
db()->exec("UPDATE destinations SET name='Lisbon', region='Lisboa' WHERE id=1");

echo "\n-- slug history and 301 --\n";
$r = rmt_place_rename(1, 'Ramiro');
check('slug changed', $r['slug'], 'ramiro-lisbon');
check('id is unchanged', (int) $pdo->query("SELECT id FROM places WHERE slug='ramiro-lisbon'")->fetchColumn(), 1);
$old = rmt_place_for_retired_slug('cervejaria-ramiro-lisbon');
check('old slug resolves', $old['slug'] ?? null, 'ramiro-lisbon');
check('current slug is not in history', rmt_place_for_retired_slug('ramiro-lisbon'), null);

rmt_place_rename(1, 'Ramiro Beer House');
check('two renames still resolve to the newest slug in ONE hop',
      rmt_place_for_retired_slug('cervejaria-ramiro-lisbon')['slug'] ?? null, 'ramiro-beer-house-lisbon');
check('the intermediate slug also resolves to the newest',
      rmt_place_for_retired_slug('ramiro-lisbon')['slug'] ?? null, 'ramiro-beer-house-lisbon');
rmt_place_rename(1, 'Ramiro');
check('renaming back does not leave a self-redirect', rmt_place_for_retired_slug('ramiro-lisbon'), null);
check('attributes survived every rename', rmt_place_by_slug('ramiro-lisbon')['phone'], '+351 21 885 1024');

echo "\n-- hours --\n";
check('set hours', rmt_place_set_hours(1, [
    ['day_of_week' => 0, 'opens' => '12:00', 'closes' => '15:00'],
    ['day_of_week' => 0, 'opens' => '19:00', 'closes' => '23:30'],
    ['day_of_week' => 1, 'closed' => true],
    ['day_of_week' => 4, 'opens' => '21:00', 'closes' => '02:00'],
]), []);
$hours = rmt_place_hours(1);
check('four intervals stored', count($hours), 4);
$byDay = rmt_place_hours_by_day($hours);
check('three days known', count($byDay), 3);
check('two intervals on Monday', $byDay[0]['intervals'], ['12:00-15:00', '19:00-23:30']);
check('Tuesday is explicitly closed', $byDay[1]['closed'], true);
check('a day we know nothing about is absent', in_array(6, array_column($byDay, 'dow'), true), false);
check('bad time rejected', array_key_first(rmt_place_set_hours(1, [['day_of_week' => 0, 'opens' => '25:00', 'closes' => '9']])), 'hours');
check('bad day rejected', array_key_first(rmt_place_set_hours(1, [['day_of_week' => 9, 'closed' => true]])), 'hours');

echo "\n-- open now --\n";
$tz = 'Europe/Lisbon';
$at = static fn(string $s) => new DateTimeImmutable($s, new DateTimeZone($tz));
check('Monday 13:00 is open',        rmt_place_open_now($hours, $tz, $at('2026-08-31 13:00')), true);
check('Monday 17:00 is closed',      rmt_place_open_now($hours, $tz, $at('2026-08-31 17:00')), false);
check('Tuesday is closed',           rmt_place_open_now($hours, $tz, $at('2026-09-01 13:00')), false);
check('Friday 23:00 is open',        rmt_place_open_now($hours, $tz, $at('2026-09-04 23:00')), true);
check('Saturday 01:00 is still open (overnight)', rmt_place_open_now($hours, $tz, $at('2026-09-05 01:00')), true);
check('Saturday 03:00 is closed',    rmt_place_open_now($hours, $tz, $at('2026-09-05 03:00')), false);
check('no timezone means we do not know', rmt_place_open_now($hours, null, $at('2026-08-31 13:00')), null);
check('no hours means we do not know',    rmt_place_open_now([], $tz, $at('2026-08-31 13:00')), null);
// Wednesday and Sunday have no rows at all. "Closed now" there would be an assertion nobody made.
check('a day with no rows is unknown, not closed', rmt_place_open_now($hours, $tz, $at('2026-09-02 13:00')), null);
check('Sunday is unknown, not closed',             rmt_place_open_now($hours, $tz, $at('2026-09-06 13:00')), null);
// Saturday itself has no rows, but Friday's interval runs into it, so Saturday IS answerable.
check('Saturday 01:00 is answerable via Friday night', rmt_place_open_now($hours, $tz, $at('2026-09-05 01:00')), true);
check('Saturday 03:00 is a real closed, not unknown',  rmt_place_open_now($hours, $tz, $at('2026-09-05 03:00')), false);

echo "\n-- photos --\n";
$pdo->exec("INSERT INTO reviews (id,user_id,destination_id,place_id,slug,title,body,rating,subject_name,status,created_at)
            VALUES (10,1,1,1,'ramiro-is-worth-it','Worth it','Body',5,'Ramiro','published','2026-08-02')");
$pdo->exec("INSERT INTO review_photos (id,review_id,url,caption,sort,created_at) VALUES (100,10,'/media/aaa.jpg','Prawns',0,'2026-08-02')");
$coverId = rmt_place_photo_add(1, ['storage_key' => 'bbb.jpg', 'alt_text' => 'The counter', 'is_cover' => true, 'uploaded_by' => 1]);
check('cover added', $coverId > 0, true);
$gal = rmt_place_gallery(1, 12);
check('gallery merges both sources', count($gal), 2);
check('cover leads the gallery', $gal[0]['kind'], 'place');
check('place photo has no review to link to', $gal[0]['parent_id'], null);
check('review photo keeps its parent', $gal[1]['parent_id'], 10);
check('storage key becomes a media url', $gal[0]['url'], rmt_media_url('bbb.jpg'));
check('photo count spans both sources', rmt_place_photo_count(1), 2);
check('cover url is the cover', rmt_place_cover_url(1), rmt_media_url('bbb.jpg'));

$second = rmt_place_photo_add(1, ['storage_key' => 'ccc.jpg', 'is_cover' => true]);
check('setting a new cover clears the old one',
      (int) $pdo->query('SELECT COUNT(*) FROM place_photos WHERE place_id=1 AND is_cover=1')->fetchColumn(), 1);
check('the new cover is the one returned', rmt_place_cover_url(1), rmt_media_url('ccc.jpg'));

echo "\n-- structured data --\n";
$p = rmt_place_by_slug('ramiro-lisbon');
$ld = rmt_place_schema_attributes($p, $hours);
check('address emitted',      $ld['address']['streetAddress'] ?? null, 'Av. Almirante Reis 1');
check('locality emitted',     $ld['address']['addressLocality'] ?? null, 'Lisbon');
check('geo emitted',          $ld['geo']['latitude'] ?? null, 38.7205);
check('telephone emitted',    $ld['telephone'] ?? null, '+351 21 885 1024');
check('website is sameAs',    $ld['sameAs'] ?? null, ['https://cervejariaramiro.com']);
check('priceRange emitted',   $ld['priceRange'] ?? null, '$$$');
check('hours emitted',        count($ld['openingHoursSpecification'] ?? []), 4);
check('overnight kept as stored',
      ($ld['openingHoursSpecification'][3]['opens'] ?? null) === '21:00' &&
      ($ld['openingHoursSpecification'][3]['closes'] ?? null) === '02:00', true);
check('closed day is a zero-length interval',
      ($ld['openingHoursSpecification'][2]['opens'] ?? null) === '00:00' &&
      ($ld['openingHoursSpecification'][2]['closes'] ?? null) === '00:00', true);

$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,created_at)
            VALUES (2,1,'bare-place-lisbon','Bare Place','bare place','attraction','active','2026-08-01')");
$bare = rmt_place_schema_attributes(rmt_place_by_slug('bare-place-lisbon'), []);
check('a place we know nothing about emits no geo',   isset($bare['geo']), false);
check('...no telephone',   isset($bare['telephone']), false);
check('...no priceRange',  isset($bare['priceRange']), false);
check('...no hours',       isset($bare['openingHoursSpecification']), false);
check('...but still carries the city it is in', $bare['address']['addressLocality'] ?? null, 'Lisbon');
check('a bare place shows no facts panel',
      rmt_place_has_address(rmt_place_by_slug('bare-place-lisbon')), false);

echo "\n-- q_run inside a transaction --\n";
// pdo_pgsql implements lastInsertId() as lastval(), which RAISES when no sequence has been used
// and, inside a transaction, poisons every statement after it. q_run must therefore not ask after
// an UPDATE or a DELETE. SQLite will not reproduce the failure, so this pins the contract instead:
// a non-INSERT returns an empty string without consulting the driver at all.
check('an UPDATE returns no insert id',
      q_run('UPDATE places SET updated_at = ? WHERE id = ?', ['2026-01-01', 1]), '');
check('a DELETE returns no insert id',
      q_run('DELETE FROM place_hours WHERE place_id = ?', [-1]), '');
check('an INSERT still returns its id',
      q_run('INSERT INTO place_hours (place_id, day_of_week, opens, closes, closed, sort) VALUES (?,?,?,?,?,?)',
            [1, 0, '09:00', '17:00', 0, 0]) !== '', true);
db()->exec('DELETE FROM place_hours WHERE place_id = 1');

echo "\n-- provenance line --\n";
check('no source means no line',
      rmt_place_source_line(['data_source' => null, 'data_source_url' => null]), null);
$osm = rmt_place_source_line(['data_source' => 'osm',
    'data_source_url' => 'https://www.openstreetmap.org/way/1', 'data_checked_at' => '2026-08-29 10:00:00']);
check('OSM is credited by name, as ODbL requires',
      str_contains($osm['text'], 'OpenStreetMap contributors, ODbL'), true);
check('...with the date it was checked', str_contains($osm['text'], 'checked 2026-08-29'), true);
check('...and a link to the object', $osm['url'], 'https://www.openstreetmap.org/way/1');
check('a map source is never described as the venue', str_contains($osm['text'], 'the venue'), false);
$own = rmt_place_source_line(['data_source' => 'official_site', 'data_source_url' => 'https://x.com']);
check('an official site IS the venue', str_contains($own['text'], 'the venue'), true);

echo "\n-- driver parity --\n";
// The two migration files describe the same tables on two engines. When they disagree about a
// storage class the difference does not show up locally at all: it shows up as a 500 in
// production, which is exactly how 048 came to exist ("operator does not exist: boolean =
// integer"). These assertions fail on the machine, before the deploy.
$pg = file_get_contents(BASE_PATH . '/database/migrations/048_place_flags_integer.pgsql.sql');
check('postgres stores closed as an integer',   (bool) preg_match('/closed\s+SMALLINT/i', $pg), true);
check('postgres stores is_cover as an integer', (bool) preg_match('/is_cover\s+SMALLINT/i', $pg), true);
check('no boolean flag survives in postgres',   (bool) preg_match('/(closed|is_cover)\s+BOOLEAN/i', $pg), false);
check('the cover index compares against 1',     str_contains($pg, 'WHERE is_cover = 1'), true);
check('048 refuses to run on non-empty tables', str_contains($pg, 'RAISE EXCEPTION'), true);

// A flag read back as the string 'f' must not be true. (bool) 'f' is.
check("'f' reads as false", rmt_place_flag('f'), false);
check("'0' reads as false", rmt_place_flag('0'), false);
check("'t' reads as true",  rmt_place_flag('t'), true);
check("'1' reads as true",  rmt_place_flag('1'), true);
check('0 reads as false',   rmt_place_flag(0), false);
check('true reads as true', rmt_place_flag(true), true);

echo "\n-- helpers --\n";
check('tel href strips formatting', rmt_place_tel_href('+351 21 885-1024'), '+351218851024');
check('map url points at the pin',
      str_contains(rmt_place_map_url(38.7205, -9.135), 'mlat=38.7205'), true);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
