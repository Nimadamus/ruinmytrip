<?php
/**
 * A place's life: renamed, moved, shut for a while, shut for good, and open again.
 *
 * Everything here is about one claim: the row id is the identity, and nothing that happens to a
 * business in the world changes it. A rebrand, a move across town and a closure all leave the same
 * row, carrying the same reviews, photos, saves and list entries, reachable from every URL it has
 * ever had. The tests are mostly about what SURVIVES.
 *
 *   php tests/place_lifecycle_test.php
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
require BASE_PATH . '/app/feedback.php';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-60s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, region TEXT, hero_url TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT, slug TEXT UNIQUE,
            name TEXT, name_key TEXT, type TEXT, status TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, place_id INT,
            destination_id INT, rating INT, title TEXT, body TEXT, slug TEXT, status TEXT, created_at TEXT,
            safety_rating INT, value_rating INT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY, review_id INT, url TEXT, storage_key TEXT, caption TEXT, sort INT, created_at TEXT)");
$pdo->exec("CREATE TABLE saves (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, target_type TEXT, target_id INT, created_at TEXT)");
$pdo->exec("CREATE TABLE collections (id INTEGER PRIMARY KEY, user_id INT, slug TEXT, title TEXT, summary TEXT, status TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE collection_items (id INTEGER PRIMARY KEY AUTOINCREMENT, collection_id INT, destination_id INT, place_id INT, note TEXT, sort INT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/047_place_attributes.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/058_feedback.sqlite.sql'));

$pdo->exec("INSERT INTO users (id,username,role,status) VALUES (1,'ada','user','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'paris-france','Paris','France')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,created_at,street_address,lat,lng,neighborhood,postal_code)
            VALUES (1,1,'hotel-abc-paris','Hotel ABC','hotel abc','hotel','active','2026-01-01','1 Rue Ancienne',48.8566,2.3522,'1st Arrondissement','75001')");
$pdo->exec("INSERT INTO reviews (id,user_id,place_id,destination_id,rating,title,body,slug,status,created_at)
            VALUES (1,1,1,1,4,'Fine','It was fine, and the lift was slow.','fine','published','2026-02-01')");
$pdo->exec("INSERT INTO review_photos (id,review_id,url,sort,created_at) VALUES (1,1,'/x.jpg',0,'2026-02-01')");
$pdo->exec("INSERT INTO saves (user_id,target_type,target_id,created_at) VALUES (1,'place',1,'2026-02-02')");
$pdo->exec("INSERT INTO collections (id,user_id,slug,title,summary,status,created_at) VALUES (1,1,'trip','My Trip','Why','published','2026-02-02')");
$pdo->exec("INSERT INTO collection_items (collection_id,place_id,sort) VALUES (1,1,0)");
$pdo->exec("INSERT INTO place_hours (place_id,day_of_week,opens,closes,closed) VALUES
            (1,0,'09:00','17:00',0),(1,1,'09:00','17:00',0)");

/** Everything attached to place 1, as one comparable snapshot. */
function attachments(): array {
    return [
        'reviews' => (int) q_one("SELECT COUNT(*) c FROM reviews WHERE place_id = 1")['c'],
        'photos'  => (int) q_one("SELECT COUNT(*) c FROM review_photos rp JOIN reviews r ON r.id = rp.review_id WHERE r.place_id = 1")['c'],
        'saves'   => (int) q_one("SELECT COUNT(*) c FROM saves WHERE target_type = 'place' AND target_id = 1")['c'],
        'lists'   => (int) q_one("SELECT COUNT(*) c FROM collection_items WHERE place_id = 1")['c'],
        'hours'   => (int) q_one("SELECT COUNT(*) c FROM place_hours WHERE place_id = 1")['c'],
        'dest'    => (int) q_one("SELECT destination_id FROM places WHERE id = 1")['destination_id'],
    ];
}
$before = attachments();

/* ==================================================================== renamed */

echo "\nHotel ABC becomes Hotel XYZ:\n";
rmt_place_rename(1, 'Hotel XYZ');
$p = q_one("SELECT * FROM places WHERE id = 1");
check('the row id never changed', (int) $p['id'], 1);
check('the name changed', (string) $p['name'], 'Hotel XYZ');
check('and so did the slug', (string) $p['slug'], 'hotel-xyz-paris');
check('everything attached survived', attachments(), $before);

$old = rmt_place_for_retired_slug('hotel-abc-paris');
check('the old slug still resolves', $old !== null, true);
check('...to the CURRENT url', (string) $old['slug'], 'hotel-xyz-paris');
check('the current slug is not in history',
      rmt_place_for_retired_slug('hotel-xyz-paris'), null);

// Rename again. The old-old URL must still land in ONE hop, not two.
rmt_place_rename(1, 'Hotel Zed');
check('two renames later, the first slug still resolves',
      (string) rmt_place_for_retired_slug('hotel-abc-paris')['slug'], 'hotel-zed-paris');
check('and so does the second', (string) rmt_place_for_retired_slug('hotel-xyz-paris')['slug'], 'hotel-zed-paris');
// This is the anti-chain assertion: history maps slug -> id, and the target is read from the place,
// so the answer for every retired slug is the same current URL rather than the next one along.
check('no chain: both retired slugs point at the same place',
      rmt_place_for_retired_slug('hotel-abc-paris')['slug'] === rmt_place_for_retired_slug('hotel-xyz-paris')['slug'], true);
check('and nothing detached', attachments(), $before);

// Renamed back to a name it used before: the slug must stop redirecting to itself.
rmt_place_rename(1, 'Hotel ABC');
check('renaming back restores the slug', (string) q_one("SELECT slug FROM places WHERE id = 1")['slug'], 'hotel-abc-paris');
check('and that slug is no longer a redirect', rmt_place_for_retired_slug('hotel-abc-paris'), null);
check('while the intermediate one still redirects here',
      (string) rmt_place_for_retired_slug('hotel-xyz-paris')['slug'], 'hotel-abc-paris');

/* ====================================================================== moved */

echo "\nIt moves across the river:\n";
$errs = rmt_place_update_attributes(1, [
    'street_address' => '9 Rue Nouvelle', 'lat' => '48.8600', 'lng' => '2.3400',
    'neighborhood' => '6th Arrondissement', 'postal_code' => '75006',
]);
check('the move is accepted', $errs, []);
$p = q_one("SELECT * FROM places WHERE id = 1");
check('same id after moving', (int) $p['id'], 1);
check('new address', (string) $p['street_address'], '9 Rue Nouvelle');
check('new neighborhood', (string) $p['neighborhood'], '6th Arrondissement');
check('the slug did NOT change', (string) $p['slug'], 'hotel-abc-paris');
check('no second place was created', (int) q_one("SELECT COUNT(*) c FROM places")['c'], 1);
check('everything attached survived the move', attachments(), $before);

/* ======================================================== temporarily closed */

echo "\nIt shuts for refurbishment:\n";
q_run("UPDATE places SET status = 'temporarily_closed' WHERE id = 1");
check('the page is still public', rmt_place_is_public('temporarily_closed'), true);
check('but it is not trading', rmt_place_is_trading('temporarily_closed'), false);
check('and it says so', rmt_place_status_label('temporarily_closed'), 'Temporarily closed');
check('the hours were NOT destroyed', (int) q_one("SELECT COUNT(*) c FROM place_hours WHERE place_id = 1")['c'], 2);
// The reason the trading flag exists: hours for Monday still exist, so anything reading them
// without checking status would cheerfully announce "Open now" about a locked door.
check('the page still loads by slug', rmt_place_by_slug('hotel-abc-paris') !== null, true);
check('reviews are still attached', attachments()['reviews'], $before['reviews']);

echo "\nAnd reopens:\n";
q_run("UPDATE places SET status = 'active' WHERE id = 1");
check('trading again', rmt_place_is_trading('active'), true);
check('nothing to announce', rmt_place_status_label('active'), null);
check('same id', (int) q_one("SELECT id FROM places WHERE slug = 'hotel-abc-paris'")['id'], 1);
check('same slug', (string) q_one("SELECT slug FROM places WHERE id = 1")['slug'], 'hotel-abc-paris');
check('hours came back with it', attachments()['hours'], $before['hours']);
check('and so did everything else', attachments(), $before);

/* ======================================================== permanently closed */

echo "\nIt shuts for good:\n";
q_run("UPDATE places SET status = 'permanently_closed' WHERE id = 1");
check('still public', rmt_place_is_public('permanently_closed'), true);
check('not trading', rmt_place_is_trading('permanently_closed'), false);
check('and it says so', rmt_place_status_label('permanently_closed'), 'Permanently closed');
check('the page still loads', rmt_place_by_slug('hotel-abc-paris') !== null, true);
check('the old URL still redirects to it',
      (string) rmt_place_for_retired_slug('hotel-xyz-paris')['slug'], 'hotel-abc-paris');
// The whole argument for keeping a closed page: this is what somebody wrote, and it is still true
// about the time it describes.
check('nothing was deleted', attachments(), $before);
check('the place row is still there', (int) q_one("SELECT COUNT(*) c FROM places WHERE id = 1")['c'], 1);

check('the legacy value means the same thing', rmt_place_status('closed'), 'permanently_closed');
check('and is public too', rmt_place_is_public('closed'), true);
check('hidden is not public', rmt_place_is_public('hidden'), false);
check('an unknown status is treated as open rather than vanishing',
      rmt_place_status('somethingelse'), 'active');

/* =========================================== a report is still only a report */

echo "\nSomebody reports it reopened. Nothing happens until a person acts:\n";
q_run("UPDATE places SET status = 'permanently_closed' WHERE id = 1");
$snapshot = q_one("SELECT * FROM places WHERE id = 1");
$r = rmt_feedback_submit('closed_temporarily', 1, 'I walked past and it is open again.', 1);
check('the report is accepted', $r['ok'], true);
check('the place is byte-identical', q_one("SELECT * FROM places WHERE id = 1"), $snapshot);
check('it is still permanently closed', (string) q_one("SELECT status FROM places WHERE id = 1")['status'], 'permanently_closed');
check('the report is waiting for a person',
      (string) q_one("SELECT status FROM feedback WHERE id = ?", [$r['id']])['status'], 'pending');

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
