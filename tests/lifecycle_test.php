<?php
/**
 * The two notifications about your own trip: one before you go, one after you get back.
 *
 * These are the only things the site sends that are not a reaction to somebody else, which makes
 * them the only ones that work on a network that is not busy yet. They are also the easiest to get
 * wrong in the direction that matters: sending the same thing twice, or every time somebody loads
 * a page, is how a notification feature becomes a reason to turn notifications off.
 *
 * What must hold:
 *   - a trip starting within three days produces exactly one notification, ever
 *   - a trip that ended yesterday produces exactly one, ever
 *   - a trip further out, or long finished, produces none
 *   - running the sweep again changes nothing
 *   - the company count on the "starts soon" line is real, public trips only, and never counts
 *     the traveler themselves
 *
 *   php tests/lifecycle_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/lifecycle.php';

$pdo = db();
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, status TEXT, visibility TEXT, date_from TEXT, date_to TEXT)");
$pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT,
              actor_id INT, target_type TEXT, target_id INT, created_at TEXT, read_at TEXT)");

$d = static fn(string $rel): string => date('Y-m-d', strtotime($rel));
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,status,visibility,date_from,date_to) VALUES
  (1,1,7,'Starts in two days','a','published','public','{$d('+2 days')}','{$d('+9 days')}'),
  (2,1,7,'Starts in three weeks','b','published','public','{$d('+21 days')}','{$d('+28 days')}'),
  (3,1,7,'Ended yesterday','c','published','public','{$d('-8 days')}','{$d('-1 day')}'),
  (4,1,7,'Ended last year','d','published','public','2025-01-01','2025-01-08'),
  (5,2,7,'Somebody else, same week','e','published','public','{$d('+3 days')}','{$d('+8 days')}'),
  (6,3,7,'Private, same week','f','published','private','{$d('+3 days')}','{$d('+8 days')}'),
  (7,1,7,'A draft','g','draft','public','{$d('+2 days')}','{$d('+4 days')}'),
  (8,1,7,'No dates at all','h','published','public',NULL,NULL)");

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $m\n"; } }
$countOf = static function (string $type, int $tripId): int {
    return (int) (q_one('SELECT COUNT(*) n FROM notifications WHERE type = ? AND target_id = ?',
                        [$type, $tripId])['n'] ?? 0);
};

$made = rmt_lifecycle_run();
/* Four: three trips start within the window (one of them private, which is still its owner's own
   trip and still worth telling them about) and one ended yesterday. */
ok($made === 4, "the first sweep sends exactly four, sent $made");
ok($countOf('trip_soon', 6) === 1, 'a private trip still notifies its own owner');
$whose = q_one('SELECT user_id FROM notifications WHERE type = ? AND target_id = ?', ['trip_soon', 5]);
ok((int) ($whose['user_id'] ?? 0) === 2, 'each notification goes to the traveler whose trip it is');
ok($countOf('trip_soon', 1) === 1, 'a trip starting in two days is announced');
ok($countOf('trip_soon', 2) === 0, 'a trip three weeks out is not');
ok($countOf('trip_over', 3) === 1, 'a trip that ended yesterday is asked about');
ok($countOf('trip_over', 4) === 0, 'a trip that ended last year is left alone');
ok($countOf('trip_soon', 7) === 0 && $countOf('trip_over', 7) === 0, 'a draft is not a trip yet');
ok($countOf('trip_soon', 8) === 0, 'a trip with no dates has no moment to announce');

ok(rmt_lifecycle_run() === 0, 'running it again sends nothing');
ok($countOf('trip_soon', 1) === 1, 'and does not duplicate the first one');

/* The notification belongs to the traveler, so it carries no actor: there is nobody who did it. */
$row = q_one("SELECT actor_id FROM notifications WHERE type = 'trip_soon' AND target_id = 1");
ok($row !== null && $row['actor_id'] === null, 'a trip notification has no actor');

// --- the company count --------------------------------------------------------------------
ok(rmt_lifecycle_company(1) === 1, 'one other public trip overlaps, and it is counted');
ok(rmt_lifecycle_company(2) === 0, 'a trip nobody overlaps counts nobody');
$pdo->exec("UPDATE trips SET visibility = 'public' WHERE id = 6");
ok(rmt_lifecycle_company(1) === 2, 'a trip that becomes public joins the count');
$pdo->exec("UPDATE trips SET visibility = 'private' WHERE id = 6");
ok(rmt_lifecycle_company(1) === 1, 'and a private one drops out of it again');
ok(rmt_lifecycle_company(8) === 0, 'a trip with no dates has no company');
ok(rmt_lifecycle_company(999) === 0, 'an unknown trip has no company');

echo "lifecycle_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
