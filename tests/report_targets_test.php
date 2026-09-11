<?php
/**
 * Every reportable thing must actually be reportable, and moderatable.
 *
 * The report route is generic: it takes a target type, looks up a table, checks the row exists,
 * checks it is not yours, and files it. That generality is what makes it cheap to add a new type,
 * and it is also what makes it possible to add one that 500s the moment somebody uses it. Reporting
 * a message did exactly that in the first version of this feature: `SELECT status FROM messages`
 * against a table with no status column.
 *
 * So this is a build gate rather than a test of one path. For every key in RMT_REPORT_TARGETS:
 *
 *   - the table exists
 *   - it has an id
 *   - the column the code reads for the author exists, under whichever name that type uses
 *   - it either has a status column, or it is on the short list of types moderated through the
 *     account instead, with that exclusion written down in the code and checked here
 *
 * Add a target without one of those and this goes red rather than the site going down.
 *
 *   php tests/report_targets_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => BASE_PATH . '/database/dev.sqlite',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';

/* Only the two constants are needed, and controllers.php pulls in the world. They are read out of
   the file so that the thing under test is the thing that ships. */
$src = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
foreach (['RMT_REPORT_TARGETS', 'RMT_REPORT_OWNER_COLUMN'] as $constName) {
    $at = strpos($src, 'const ' . $constName . ' = [');
    if ($at === false) { echo "FAIL: $constName not found in controllers.php\n"; exit(1); }
    $end = strpos($src, '];', $at);
    eval(substr($src, $at, $end - $at + 2));
}
$modSrc = (string) file_get_contents(BASE_PATH . '/app/moderation.php');

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

/* Types moderated through the account that owns them rather than by moving a status. The list is
   read out of moderation.php rather than repeated here, so the two cannot drift apart. */
preg_match("/in_array\(\\\$targetType, \[([^\]]*)\], true\)/", $modSrc, $m);
$noStatus = [];
if (!empty($m[1])) {
    foreach (explode(',', $m[1]) as $bit) {
        $bit = trim($bit, " '\"\t\n");
        if ($bit !== '') $noStatus[] = $bit;
    }
}
ok($noStatus !== [], 'moderation.php names the types that are handled through the account');

$pdo = db();
$tables = array_column($pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_ASSOC), 'name');

foreach (RMT_REPORT_TARGETS as $type => $table) {
    ok(in_array($table, $tables, true), "$type: the table $table exists");
    if (!in_array($table, $tables, true)) continue;

    $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    ok(in_array('id', $cols, true), "$type: has an id to report");

    if ($type !== 'user') {
        $owner = RMT_REPORT_OWNER_COLUMN[$type] ?? 'user_id';
        ok(in_array($owner, $cols, true),
           "$type: the author column $owner exists, so reporting your own is refused");
    }

    if (in_array($type, $noStatus, true)) {
        ok(true, "$type: moderated through the account, which is why it needs no status");
    } else {
        ok(in_array('status', $cols, true),
           "$type: has a status, so a moderator can hide or remove it");
    }
}

echo "report_targets_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
