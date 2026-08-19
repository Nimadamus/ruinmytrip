<?php
/**
 * Regression tests for saving a place (migration 042 / app/places.php / place_save_action).
 *
 * A save is two claims at once: a private one ("this is on my list") and a public one ("N
 * travelers saved this"). Both have to survive the ordinary ways they get broken:
 *
 *   - a double-tap must not add a second row, or the public count inflates for one person
 *   - the count must be a live COUNT, never a stored number that can drift from the rows
 *   - a hidden place must not stay on somebody's list as a link to a page that 404s
 *   - a logged-out visitor has no saves at all, and asking must not error
 *   - the newest save sorts first, and rows saved before created_at existed sort last
 *     rather than into a random position
 *
 * Runs against a throwaway in-memory SQLite DB. No network, no fixtures on disk.
 *
 *   php tests/place_saves_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
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

function dest_by_id(int $id): ?array { return q_one('SELECT * FROM destinations WHERE id = ?', [$id]); }
function authors_fill(array &$rows, string $idField = 'user_id'): void {}

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, hero_url TEXT)');
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT NOT NULL,
              slug TEXT UNIQUE NOT NULL, name TEXT NOT NULL, name_key TEXT NOT NULL,
              type TEXT NOT NULL DEFAULT 'attraction', created_by INT, status TEXT NOT NULL DEFAULT 'active',
              created_at TEXT NOT NULL, updated_at TEXT)");
$pdo->exec('CREATE UNIQUE INDEX idx_places_dest_namekey ON places(destination_id, name_key)');
// Exactly the production shape: the primary key is what makes a duplicate save impossible.
$pdo->exec('CREATE TABLE saves (user_id INTEGER NOT NULL, target_type TEXT NOT NULL, target_id INTEGER NOT NULL,
              created_at TEXT, PRIMARY KEY (user_id, target_type, target_id))');

$pdo->exec("INSERT INTO users (id,username,role,status) VALUES
              (1,'traveler_a','user','active'), (2,'traveler_b','user','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES
              (1,'barcelona-spain','Barcelona','Spain'), (2,'lisbon-portugal','Lisbon','Portugal')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,created_at) VALUES
              (1,1,'hotel-arts-barcelona','Hotel Arts','hotel arts','hotel','active','2026-01-01 00:00:00'),
              (2,1,'sagrada-familia-barcelona','Sagrada Familia','sagrada familia','attraction','active','2026-01-02 00:00:00'),
              (3,2,'time-out-market-lisbon','Time Out Market','time out market','restaurant','active','2026-01-03 00:00:00'),
              (4,1,'closed-bar-barcelona','Closed Bar','closed bar','restaurant','hidden','2026-01-04 00:00:00')");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if ($cond) { echo "  PASS  $name\n"; return; }
    $fails++;
    echo "  FAIL  $name" . ($detail !== '' ? "  ($detail)" : '') . "\n";
}

/** Save the way place_save_action does: guarded INSERT that swallows only a duplicate-key clash. */
function save_place(int $uid, int $placeId, string $at): bool {
    try {
        q_run('INSERT INTO saves (user_id,target_type,target_id,created_at) VALUES (?,?,?,?)',
              [$uid, RMT_SAVE_PLACE, $placeId, $at]);
        return true;
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23505' && $e->getCode() !== '23000') throw $e;
        return false;
    }
}

echo "place saves\n";

// --- The count is live, and starts at nothing -------------------------------------------------
ok('a place nobody saved counts zero', rmt_place_save_count(1) === 0);
ok('a logged-out visitor has not saved anything', rmt_place_is_saved(1, null) === false);
ok('an unsaved place reads as unsaved for a real user', rmt_place_is_saved(1, 1) === false);

// --- Saving, and the double-tap ---------------------------------------------------------------
save_place(1, 1, '2026-03-01 10:00:00');
ok('saving is visible to the user who saved', rmt_place_is_saved(1, 1) === true);
ok('saving is not visible to anyone else', rmt_place_is_saved(1, 2) === false);
ok('one save counts one', rmt_place_save_count(1) === 1);

$second = save_place(1, 1, '2026-03-01 10:00:01');
ok('a double-tap inserts no second row', $second === false);
ok('a double-tap does not inflate the count', rmt_place_save_count(1) === 1,
   'count=' . rmt_place_save_count(1));

// --- Two people, one place --------------------------------------------------------------------
save_place(2, 1, '2026-03-02 09:00:00');
ok('two travelers saving the same place count two', rmt_place_save_count(1) === 2);
ok('their saves are independent', rmt_place_is_saved(1, 2) === true);

// --- Unsaving ---------------------------------------------------------------------------------
q_run('DELETE FROM saves WHERE user_id=? AND target_type=? AND target_id=?', [2, RMT_SAVE_PLACE, 1]);
ok('unsaving removes it from that user only', rmt_place_is_saved(1, 2) === false && rmt_place_is_saved(1, 1) === true);
ok('unsaving takes the count back down', rmt_place_save_count(1) === 1);

// --- The count is scoped to one place, and to places ------------------------------------------
q_run("INSERT INTO saves (user_id,target_type,target_id,created_at) VALUES (?, 'destination', ?, ?)",
      [1, 1, '2026-03-03 09:00:00']);
ok('a destination save never counts toward a place of the same id', rmt_place_save_count(1) === 1);
ok('a destination save is not a place save', rmt_place_is_saved(1, 1) === true && rmt_place_save_count(2) === 0);

// --- The personal list ------------------------------------------------------------------------
save_place(1, 3, '2026-03-05 08:00:00');
save_place(1, 2, '2026-03-04 08:00:00');
$list = rmt_saved_places(1);
ok('the list holds exactly what was saved', count($list) === 3, 'n=' . count($list));
ok('the newest save is first', (int) $list[0]['id'] === 3, 'first=' . ($list[0]['id'] ?? '?'));
ok('the oldest save is last', (int) $list[2]['id'] === 1, 'last=' . ($list[2]['id'] ?? '?'));
ok('each row carries its destination for the card', ($list[0]['dest_name'] ?? '') === 'Lisbon'
   && ($list[0]['dest_country'] ?? '') === 'Portugal');
ok('each row carries its type for the label', ($list[0]['type'] ?? '') === 'restaurant');
ok("one user's list is not another's", rmt_saved_places(2) === []);

// --- A hidden place ---------------------------------------------------------------------------
save_place(1, 4, '2026-03-06 08:00:00');
$listAfter = rmt_saved_places(1);
ok('a hidden place never appears on the list', count($listAfter) === 3
   && !in_array(4, array_map(static fn(array $r) => (int) $r['id'], $listAfter), true));
ok('the save row itself is kept, so restoring the place restores the save',
   (bool) q_one('SELECT 1 FROM saves WHERE user_id=1 AND target_type=? AND target_id=4', [RMT_SAVE_PLACE]));
q_run("UPDATE places SET status='active' WHERE id=4");
ok('restoring the place brings it back to the list', count(rmt_saved_places(1)) === 4);
q_run("UPDATE places SET status='hidden' WHERE id=4");

// --- Saves made before created_at existed -----------------------------------------------------
q_run("INSERT INTO saves (user_id,target_type,target_id,created_at) VALUES (2, ?, 1, NULL)", [RMT_SAVE_PLACE]);
save_place(2, 2, '2026-03-07 08:00:00');
$legacy = rmt_saved_places(2);
ok('an undated save still appears', count($legacy) === 2, 'n=' . count($legacy));
ok('an undated save sorts last, not randomly', (int) $legacy[0]['id'] === 2 && (int) $legacy[1]['id'] === 1,
   'order=' . implode(',', array_map(static fn(array $r) => (string) $r['id'], $legacy)));
ok('an undated save still counts', rmt_place_save_count(1) === 2);

// --- The controller keeps its guards ----------------------------------------------------------
$src = file_get_contents(BASE_PATH . '/app/controllers.php');
$start = strpos($src, 'function place_save_action(');
$body = $start === false ? '' : substr($src, $start, (strpos($src, "\nfunction ", $start + 1) ?: strlen($src)) - $start);
ok('place_save_action() exists', $body !== '');
ok('place_save_action(): requires a login', strpos($body, 'require_login()') !== false);
ok('place_save_action(): checks CSRF', strpos($body, 'csrf_check()') !== false);
ok('place_save_action(): resolves the place before trusting the id', strpos($body, 'rmt_place_by_id') !== false);
ok('place_save_action(): rate limits', strpos($body, 'rmt_rate_ok') !== false);
ok('place_save_action(): guards the racy INSERT', (bool) preg_match('/try\s*\{[^}]*INSERT INTO saves/s', $body));
ok('place_save_action(): swallows only duplicate-key codes', strpos($body, "'23000'") !== false
   && strpos($body, "'23505'") !== false && strpos($body, 'throw $e;') !== false);

ok('place_save_action(): normalises the return path instead of following it raw',
   strpos($body, 'rmt_safe_return_path') !== false);

// rmt_place_by_id is the whole status check: if it ever stopped filtering, a hidden place would
// become savable and the count on an unreachable page could be run up.
ok('rmt_place_by_id() refuses a hidden place', rmt_place_by_id(4) === null);

echo $fails ? "\n$fails FAILED\n" : "\nAll place save tests passed.\n";
exit($fails ? 1 : 0);
