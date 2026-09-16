<?php
/**
 * A block has to mean the other person disappears, everywhere a list of people is drawn.
 *
 * It was enforced where a query had been written with it in mind (messaging, overlap matching, the
 * discover page) and nowhere else, so somebody you blocked still appeared among a city's travelers,
 * in its questions and in the home feed. The rule now lives in one helper, and this asserts both
 * that the helper works in both directions and that every people list goes through it.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://ruinmytrip.com',
                      'app_name' => 'RuinMyTrip', 'db_driver' => 'sqlite', 'sqlite_path' => ':memory:'];

require BASE_PATH . '/app/db.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE blocks (blocker_id INTEGER, blocked_id INTEGER)');
$pdo->exec('INSERT INTO blocks VALUES (1, 2)');   // 1 blocked 2
$pdo->exec('INSERT INTO blocks VALUES (3, 1)');   // 3 blocked 1

require_once BASE_PATH . '/app/helpers.php';
require_once BASE_PATH . '/app/messages.php';

$pass = 0; $fail = 0;
function ok(string $what, $got, $want = true): void {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  [PASS] $what\n"; }
    else { $fail++; echo "  [FAIL] $what  expected=" . var_export($want, true)
                      . " got=" . var_export($got, true) . "\n"; }
}

echo "\n-- both directions --\n";
$ids = rmt_blocked_ids(1);
ok('somebody I blocked is in my set',       isset($ids[2]), true);
ok('somebody who blocked me is in my set',   isset($ids[3]), true);
ok('an unrelated member is not',             isset($ids[4]), false);
ok('nobody signed in blocks nobody',         rmt_blocked_ids(null), []);

echo "\n-- the filter --\n";
$rows = [['user_id' => 2, 'x' => 'a'], ['user_id' => 3, 'x' => 'b'], ['user_id' => 4, 'x' => 'c']];
ok('both blocked authors are dropped', array_column(rmt_without_blocked($rows, 1), 'x'), ['c']);
ok('a signed out reader sees everything', count(rmt_without_blocked($rows, null)), 3);
ok('it reads whichever column the list uses',
   array_column(rmt_without_blocked([['actor' => 2], ['actor' => 9]], 1, 'actor'), 'actor'), [9]);
/* Member 4 has no blocks, so nothing is filtered for them. */
ok('a member with no blocks loses nothing', count(rmt_without_blocked($rows, 4)), 3);

echo "\n-- and every people list goes through it --\n";
$hub   = (string) file_get_contents(BASE_PATH . '/app/travelers_hub.php');
$posts = (string) file_get_contents(BASE_PATH . '/app/posts.php');
$ctrl  = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
foreach (['going', 'talk', 'people', 'locals', 'reviews'] as $list) {
    ok("the city hub filters $list",
       (bool) preg_match('/\$' . $list . '\s*=\s*rmt_without_blocked\(\$' . $list . ', \$vid\)/', $hub), true);
}
ok('every recent posts list is filtered', str_contains($posts, 'rmt_without_blocked($rows, (int) $viewer'), true);
ok('the home feed is filtered, Everyone scope included', str_contains($ctrl, '$items = rmt_without_blocked($items, $uid)'), true);
/* The places that already enforced it must keep doing so. */
ok('messaging still checks blocks', str_contains((string) file_get_contents(BASE_PATH . '/app/messages.php'), 'function rmt_is_blocked'), true);

echo "\n-- notifications --\n";
$pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INT, type TEXT, actor_id INT, read_at TEXT)');
$pdo->exec("INSERT INTO notifications (user_id,type,actor_id) VALUES (1,'follow',2),(1,'like',3),(1,'like',4),(1,'save',NULL)");
ok('the badge ignores blocked actors and keeps actorless rows', rmt_unread_notification_count(1), 2);
ok('a member with no blocks counts everything', rmt_unread_notification_count(4), 0);
ok('the notifications page filters actors', str_contains($ctrl, "rmt_without_blocked(\$items, (int) \$me['id'], 'actor_id')"), true);
ok('a blocked member cannot trigger a like notification', str_contains($ctrl, 'if (rmt_is_blocked($owner, $actorId)) return;'), true);
ok('matching still excludes blocks', str_contains((string) file_get_contents(BASE_PATH . '/app/matching.php'), 'function rmt_match_block_sql'), true);
ok('discovery still excludes blocks', str_contains((string) file_get_contents(BASE_PATH . '/app/discovery.php'), 'rmt_discover_blocks('), true);

echo "\n-- it cannot take a page down --\n";
$pdo->exec('DROP TABLE blocks');
unset($GLOBALS['_rmt_blocked_ids_7']);
ok('a missing blocks table filters nothing rather than throwing', rmt_blocked_ids(7), []);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL BLOCK SUPPRESSION TESTS PASS ({$pass})\n";
