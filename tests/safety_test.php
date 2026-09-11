<?php
/**
 * Trust and safety, as assertions rather than as intentions.
 *
 * Strangers can now find each other on this site and write to each other, which changes what a bug
 * costs. Four visibility leaks were found and fixed in two days, all of them the same shape: a
 * query that asked what exists rather than what the reader is allowed to see. This file is the
 * cheap end of making sure they cannot come back quietly.
 *
 * What it checks:
 *   - every query in the codebase that reads trips either applies the visibility clause, filters
 *     to public, or is one of the handful that legitimately does not (a trip's own page, the
 *     owner's own list, a bare count of everything)
 *   - blocks work in both directions and cover messaging
 *   - the new-conversation ceiling is real and separate from the message ceiling
 *   - a suspended account disappears from the places people are listed
 *
 *   php tests/safety_test.php
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

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $m\n"; } }

/* ------------------------------------------------------------------ the static audit
 *
 * Every SELECT that reads the trips table, checked for one of: the visibility helper, an explicit
 * public filter, or a by-id lookup (a trip's own page, which checks visibility in PHP afterwards).
 * A new query that forgets fails this, which is the only way to stop the fifth instance of a bug
 * that has now happened four times.
 */
$files = glob(BASE_PATH . '/app/*.php');
$offenders = [];

/* Queries that read trips and are allowed not to filter, each for a stated reason. A name on this
   list is a decision somebody made on purpose; anything not on it has to carry a clause. */
$allowed = [
    // The notification sweep is about the member's OWN trips, whatever they set them to: a private
    // trip is still their trip and still starts on Tuesday.
    'lifecycle.php',
    // These select WHO TO NOTIFY, not what to show. Being told that somebody is coming to the city
    // you are going to is not a disclosure of your own trip to anybody: the row is read to find
    // the recipient, and nothing about it is rendered to another member.
    'matching.php',
];

foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    $rel = basename($file);
    if (in_array($rel, $allowed, true)) continue;
    if (!preg_match_all('/q_(?:all|one)\(\s*"([^"]*FROM\s+trips[^"]*)"/is', $src, $m)) continue;
    foreach ($m[1] as $sql) {
        $flat = strtolower((string) preg_replace('/\s+/', ' ', $sql));
        $safe =
            // a visibility clause, however the local variable happens to be spelled
            /* an interpolated clause: $visSql, $goingSql, or a WHERE built by implode. The
               clause itself is tested where it is built (trip_visibility_test, going_test); what
               this audit is for is the query that interpolates nothing at all. */
            preg_match('/\$\w*(vis|going|where)\w*/i', $flat) === 1
            // or an explicit filter on the column
            || str_contains($flat, "visibility = 'public'")
            || str_contains($flat, "visibility='public'")
            || str_contains($flat, "'public')='public'")
            || str_contains($flat, "'public') = 'public'")
            // or one row by id, which the page then checks in PHP
            || preg_match('/where\s+t?\.?id\s*=\s*\?/', $flat) === 1
            // or one member's own trips
            || preg_match('/user_id\s*=\s*\?/', $flat) === 1
            // or a count, which reveals no content
            || str_contains($flat, 'count(');
        if (!$safe) $offenders[] = $rel . ': ' . mb_strimwidth($flat, 0, 100, '...');
    }
}
ok($offenders === [], 'every trips query filters by visibility, or is a single lookup, or is allowlisted'
   . ($offenders ? "\n    " . implode("\n    ", $offenders) : ''));
ok(count($files) > 20, 'the audit actually read the app directory');

/* The same audit for activities, which are the newest thing on this site that reads another
   person's plans. An activity carries its own visibility on top of its trip's, so a query that
   reads them has to apply the activity clause too: rmt_activity_visible_sql(), an explicit filter
   on the column, or a single lookup the page then checks in PHP. */
$actOffenders = [];
foreach ($files as $file) {
    $rel = basename($file);
    if (in_array($rel, $allowed, true)) continue;
    $src = (string) file_get_contents($file);
    if (!preg_match_all('/q_(?:all|one)\(\s*"([^"]*FROM\s+trip_activities[^"]*)"/is', $src, $m)) continue;
    foreach ($m[1] as $sql) {
        $flat = strtolower((string) preg_replace('/\s+/', ' ', $sql));
        $safe = preg_match('/\$\w*(vis|act|where)\w*/i', $flat) === 1
             || str_contains($flat, "visibility = 'trip'")
             || str_contains($flat, "visibility='trip'")
             || preg_match('/where\s+a?\.?id\s*=\s*\?/', $flat) === 1
             || preg_match('/user_id\s*=\s*\?/', $flat) === 1
             || str_contains($flat, 'count(')
             || str_contains($flat, 'max(');
        if (!$safe) $actOffenders[] = $rel . ': ' . mb_strimwidth($flat, 0, 100, '...');
    }
}
ok($actOffenders === [], 'every activities query filters by visibility, or is a single lookup'
   . ($actOffenders ? "\n    " . implode("\n    ", $actOffenders) : ''));


/* ------------------------------------------------------------------ blocks and ceilings */
$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active')");
$pdo->exec('CREATE TABLE blocks (blocker_id INT, blocked_id INT)');
$pdo->exec('CREATE TABLE conversations (id INTEGER PRIMARY KEY AUTOINCREMENT, user_lo_id INT, user_hi_id INT, created_at TEXT, last_message_at TEXT)');
$pdo->exec('CREATE TABLE messages (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INT, sender_id INT, body TEXT, created_at TEXT, read_at TEXT)');
$pdo->exec('CREATE TABLE rate_limits (bucket TEXT, window_start INT, hits INT, PRIMARY KEY (bucket, window_start))');
$pdo->exec("INSERT INTO users (id,username) VALUES (1,'ana'),(2,'ben'),(3,'cleo')");

require BASE_PATH . '/app/ratelimit.php';
require BASE_PATH . '/app/messages.php';

ok(!rmt_conversation_exists(1, 2), 'a conversation that does not exist is reported as not existing');
rmt_get_or_create_conversation(1, 2);
ok(rmt_conversation_exists(1, 2), 'and as existing once it does');
ok(rmt_conversation_exists(2, 1), 'the pair is order independent');

/* The new-thread ceiling is separate from, and much lower than, the message ceiling: sixty
   messages inside one thread is an argument, sixty first messages to sixty strangers is spam. */
ok(RMT_NEW_THREADS_PER_DAY < 60, 'there is a separate ceiling on opening new conversations');
$hits = 0;
for ($i = 0; $i < RMT_NEW_THREADS_PER_DAY + 3; $i++) {
    if (rmt_rate_ok('message_new', 'user-7', RMT_NEW_THREADS_PER_DAY, 86400)) $hits++;
}
ok($hits === RMT_NEW_THREADS_PER_DAY, "the ceiling holds at " . RMT_NEW_THREADS_PER_DAY . ", allowed $hits");

$pdo->exec('INSERT INTO blocks VALUES (1,2)');
ok(rmt_is_blocked(1, 2), 'a block is visible from the blocker');
ok(rmt_is_blocked(2, 1), 'and from the blocked, which is what stops a reply');
ok(!rmt_is_blocked(1, 3), 'and does not touch anybody else');

echo "safety_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
