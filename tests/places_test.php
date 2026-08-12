<?php
/**
 * Regression tests for the places layer (migration 040 / app/places.php).
 *
 * Places are what makes RuinMyTrip a review site rather than a collection of essays, so the
 * properties that make an aggregate rating trustworthy get tests:
 *
 *   - the same thing, written differently, resolves to ONE place (or the average splits silently)
 *   - two genuinely different things never collapse into one (the failure a reader cannot see)
 *   - editorial reviews never move a place's number, exactly as for destinations
 *   - a destination-level review never invents a place inside its own destination
 *   - an edit re-points the review, so a renamed subject stops counting against the old place
 *
 * Runs against a throwaway in-memory SQLite DB. No network, no fixtures on disk.
 *
 *   php tests/places_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
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

// Minimal stand-ins for the two controller helpers places.php calls. The real ones do the same
// thing against the same tables; duplicating them here keeps the test free of the whole app.
function dest_by_id(int $id): ?array { return q_one('SELECT * FROM destinations WHERE id = ?', [$id]); }
function authors_fill(array &$rows, string $idField = 'user_id'): void {}

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT)');
$pdo->exec('CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT NOT NULL,
              slug TEXT UNIQUE NOT NULL, name TEXT NOT NULL, name_key TEXT NOT NULL,
              type TEXT NOT NULL DEFAULT \'attraction\', created_by INT, status TEXT NOT NULL DEFAULT \'active\',
              created_at TEXT NOT NULL, updated_at TEXT)');
$pdo->exec('CREATE UNIQUE INDEX idx_places_dest_namekey ON places(destination_id, name_key)');
$pdo->exec('CREATE TABLE reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
              place_id INT, subject_type TEXT, subject_name TEXT, rating INT, safety_rating INT,
              value_rating INT, slug TEXT, title TEXT, status TEXT)');
$pdo->exec('CREATE TABLE review_votes (id INTEGER PRIMARY KEY, review_id INT, vote_type TEXT)');
$pdo->exec('CREATE TABLE review_photos (id INTEGER PRIMARY KEY, review_id INT, url TEXT, caption TEXT, created_at TEXT)');

$pdo->exec("INSERT INTO users (id,username,role,status) VALUES
              (1,'ruinmytrip','editorial','active'), (2,'traveler_a','user','active'), (3,'traveler_b','user','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES
              (1,'barcelona-spain','Barcelona','Spain'), (2,'lisbon-portugal','Lisbon','Portugal')");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if ($cond) { echo "  PASS  $name\n"; return; }
    $fails++;
    echo "  FAIL  $name" . ($detail !== '' ? "  ($detail)" : '') . "\n";
}

/** Publish a review the way review_create does: resolve the place first, then insert. */
function write_review(int $uid, int $destId, string $type, string $name, int $rating, string $status = 'published'): array {
    $placeId = rmt_place_resolve($destId, $type, $name, $uid);
    $id = (int) q_run('INSERT INTO reviews (user_id,destination_id,place_id,subject_type,subject_name,rating,status)
                       VALUES (?,?,?,?,?,?,?)', [$uid, $destId, $placeId, $type, $name, $rating, $status]);
    return ['id' => $id, 'place_id' => $placeId];
}

echo "places\n";

// --- Resolution: one place per real-world thing -----------------------------------------------
$a = write_review(2, 1, 'hotel', 'Hotel Arts', 4);
$b = write_review(3, 1, 'hotel', '  the hotel arts.  ', 2);
ok('spelling/case/article variants resolve to one place',
   $a['place_id'] !== null && $a['place_id'] === $b['place_id'],
   'got ' . var_export($a['place_id'], true) . ' vs ' . var_export($b['place_id'], true));

$c = write_review(2, 1, 'restaurant', 'Hotel Arts Bistro', 5);
ok('a different name is a different place', $c['place_id'] !== $a['place_id']);

$d = write_review(2, 2, 'hotel', 'Hotel Arts', 5);
ok('the same name in another destination is another place', $d['place_id'] !== $a['place_id']);

ok('type does not split the identity',
   (int) q_one('SELECT COUNT(*) n FROM places WHERE destination_id=1 AND name_key=?',
               [rmt_place_name_key('Hotel Arts')])['n'] === 1);

// Accents are NOT folded on purpose: a hand-rolled fold without ext/intl would be wrong for some
// language, and a wrong fold merges two real places invisibly.
ok('accented and unaccented spellings stay separate',
   rmt_place_name_key('Café Central') !== rmt_place_name_key('Cafe Central'));

// --- What must never become a place ------------------------------------------------------------
$e = write_review(2, 1, 'destination', 'Barcelona', 4);
ok('a destination-level review creates no place', $e['place_id'] === null);
ok('a blank subject creates no place', rmt_place_resolve(1, 'hotel', '   ', 2) === null);
ok('a name that is only punctuation creates no place', rmt_place_resolve(1, 'hotel', '!!! ???', 2) === null);
ok('an unknown destination creates no place', rmt_place_resolve(999, 'hotel', 'Ghost Inn', 2) === null);
ok('an over-long name creates no place', rmt_place_resolve(1, 'hotel', str_repeat('x', 201), 2) === null);

// --- Slugs --------------------------------------------------------------------------------------
$p1 = q_one('SELECT * FROM places WHERE id = ?', [$a['place_id']]);
ok('slug folds in the destination', $p1['slug'] === 'hotel-arts-barcelona', 'got ' . $p1['slug']);
$p2 = q_one('SELECT * FROM places WHERE id = ?', [$d['place_id']]);
ok('same name elsewhere gets its own slug', $p2['slug'] === 'hotel-arts-lisbon', 'got ' . $p2['slug']);
ok('slugs are unique',
   (int) q_one('SELECT COUNT(DISTINCT slug) n FROM places')['n'] === (int) q_one('SELECT COUNT(*) n FROM places')['n']);

// --- Aggregates ---------------------------------------------------------------------------------
$stats = rmt_place_stats((int) $a['place_id']);
ok('average is over community reviews only', $stats['c'] === 2 && (float) $stats['a'] === 3.0,
   'c=' . $stats['c'] . ' a=' . var_export($stats['a'], true));

// The site's own review must never move the number it presents as traveler consensus.
write_review(1, 1, 'hotel', 'Hotel Arts', 5);
$after = rmt_place_stats((int) $a['place_id']);
ok('an editorial review does not change the community average',
   $after['c'] === 2 && (float) $after['a'] === 3.0, 'c=' . $after['c'] . ' a=' . var_export($after['a'], true));
ok('the editorial review is still attached to the place',
   (int) q_one('SELECT COUNT(*) n FROM reviews WHERE place_id=?', [$a['place_id']])['n'] === 3);

// Unpublished reviews are not opinions yet.
write_review(3, 1, 'hotel', 'Hotel Arts', 1, 'draft');
$afterDraft = rmt_place_stats((int) $a['place_id']);
ok('a draft does not count toward the average', $afterDraft['c'] === 2 && (float) $afterDraft['a'] === 3.0);

$bd = rmt_place_rating_breakdown((int) $a['place_id']);
ok('breakdown counts only published community ratings',
   $bd[4] === 1 && $bd[2] === 1 && $bd[5] === 0 && $bd[1] === 0);

// A place nobody has published about reports nothing rather than a zero.
$emptyId = rmt_place_resolve(2, 'attraction', 'Unreviewed Viewpoint', 2);
$emptyStats = rmt_place_stats((int) $emptyId);
ok('a place with no published reviews reports no rating', $emptyStats['c'] === 0 && $emptyStats['a'] === null);

// --- Editing re-points the review ----------------------------------------------------------------
$moved = rmt_place_resolve(1, 'hotel', 'Hotel Somewhere Else', 3);
db()->prepare('UPDATE reviews SET place_id=?, subject_name=? WHERE id=?')
    ->execute([$moved, 'Hotel Somewhere Else', $b['id']]);
$reStats = rmt_place_stats((int) $a['place_id']);
ok('editing a review off a place drops it from that average', $reStats['c'] === 1 && (float) $reStats['a'] === 4.0,
   'c=' . $reStats['c'] . ' a=' . var_export($reStats['a'], true));

// --- Listing --------------------------------------------------------------------------------------
$list = rmt_places_for_destination(1);
ok('destination listing returns its own places only',
   $list && count(array_filter($list, fn($p) => (int) $p['destination_id'] !== 1)) === 0);
$rated = array_values(array_filter($list, fn($p) => (int) $p['review_count'] > 0));
$unrated = array_values(array_filter($list, fn($p) => (int) $p['review_count'] === 0));
$firstUnratedAt = null;
foreach ($list as $i => $p) { if ((int) $p['review_count'] === 0) { $firstUnratedAt = $i; break; } }
ok('rated places sort above unrated ones',
   $firstUnratedAt === null || $firstUnratedAt >= count($rated));
$filtered = rmt_places_for_destination(1, 'restaurant');
ok('the type filter filters', $filtered && count(array_filter($filtered, fn($p) => $p['type'] !== 'restaurant')) === 0);

$counts = rmt_place_type_counts(1);
ok('type counts match the rows',
   array_sum($counts) === (int) q_one("SELECT COUNT(*) n FROM places WHERE destination_id=1 AND status='active'")['n']);

echo $fails ? "\n$fails FAILED\n" : "\nAll places tests passed.\n";
exit($fails ? 1 : 0);
