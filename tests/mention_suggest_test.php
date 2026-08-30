<?php
/**
 * The @mention box: prefix matches first, blocked people never, and never the person typing.
 *
 *   php tests/mention_suggest_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
                      'db_driver' => 'sqlite', 'sqlite_path' => ':memory:'];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT)");
$pdo->exec("CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT)");
$pdo->exec("CREATE TABLE blocks (blocker_id INT, blocked_id INT)");
$pdo->exec("INSERT INTO users (id,username,status) VALUES
    (1,'me','active'),(2,'maya','active'),(3,'normanmartin','active'),(4,'gone','deleted'),(5,'mask','active')");
$pdo->exec("INSERT INTO profiles (user_id,display_name) VALUES (3,'Norman Martin')");
$pdo->exec("INSERT INTO blocks (blocker_id,blocked_id) VALUES (5,1)");

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-54s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

/** The controller's query, verbatim in shape, so the ordering rule is what is under test. */
function suggest(int $meId, string $q): array {
    $like = mb_strtolower($q) . '%';
    $anywhere = '%' . mb_strtolower($q) . '%';
    $rows = q_all("SELECT u.username FROM users u LEFT JOIN profiles p ON p.user_id = u.id
                    WHERE u.status='active' AND u.id <> ?
                      AND (LOWER(u.username) LIKE ? OR LOWER(COALESCE(p.display_name,'')) LIKE ?)
                      AND NOT EXISTS (SELECT 1 FROM blocks b
                                       WHERE (b.blocker_id = ? AND b.blocked_id = u.id)
                                          OR (b.blocker_id = u.id AND b.blocked_id = ?))
                 ORDER BY CASE WHEN LOWER(u.username) LIKE ? THEN 0 ELSE 1 END, u.username
                    LIMIT 8", [$meId, $anywhere, $anywhere, $meId, $meId, $like]);
    return array_map(static fn(array $r): string => (string) $r['username'], $rows);
}

echo "-- suggestions --\n";
check('a prefix match comes first', suggest(1, 'ma'), ['maya', 'normanmartin']);
check('a display name still matches', suggest(1, 'norman'), ['normanmartin']);
check('never me', in_array('me', suggest(1, 'm'), true), false);
check('never a deleted account', in_array('gone', suggest(1, 'gone'), true), false);
check('never somebody who blocked me', in_array('mask', suggest(1, 'mask'), true), false);
check('and they can not see me either', in_array('me', suggest(5, 'me'), true), false);
check('no match is an empty list', suggest(1, 'zzz'), []);

echo $fail ? "\nFAILED: $fail\n" : "\nOK\n";
exit($fail ? 1 : 0);
