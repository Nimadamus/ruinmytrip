<?php
/**
 * Three people liking the same review is one line, not three.
 *
 * Notifications are the thing a member turns off, and they turn them off when the page stops
 * being news and starts being a log. Repeats of the same event on the same object collapse; the
 * ones addressed to the reader personally never do.
 *
 * What this pins down:
 *   - likes on one object roll into one row, with the newest kept
 *   - likes on different objects stay apart, and so do different types
 *   - an ask to join, a reply and a message are one row each, always
 *   - the order of the page is not disturbed by rolling
 *
 *   php tests/notification_rollup_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';

/* The function under test lives in controllers.php, which pulls the whole application in. It is a
   pure function over an array, so it is copied in by reading just its own text: the alternative is
   booting the world to test twenty lines of grouping. */
$src = file_get_contents(BASE_PATH . '/app/controllers.php');
$start = strpos($src, 'function rmt_notifications_rollup(array $items): array {');
if ($start === false) { echo "FAIL: rmt_notifications_rollup not found\n"; exit(1); }
$end = strpos($src, "\n}\n", $start);
eval(substr($src, $start, $end - $start + 3));

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$row = static fn(int $id, string $type, string $tt, int $tid, ?string $actor): array =>
    ['id' => $id, 'type' => $type, 'target_type' => $tt, 'target_id' => $tid, 'actor' => $actor];

$items = [
    $row(9, 'like', 'review', 5, 'cara'),
    $row(8, 'like', 'review', 5, 'ben'),
    $row(7, 'like', 'review', 5, 'ana'),
    $row(6, 'like', 'review', 4, 'ana'),
    $row(5, 'activity_request', 'activity', 2, 'ben'),
    $row(4, 'activity_request', 'activity', 2, 'cara'),
];
$out = rmt_notifications_rollup($items);

ok(count($out) === 4, 'six rows become four');
ok((int) $out[0]['id'] === 9, 'the newest of a group is the one kept');
ok((int) $out[0]['others'] === 2, 'and it knows two other people did the same thing');
ok($out[0]['also'] === ['ben'], 'one of them is named, because a name beats a number');
ok((int) $out[1]['target_id'] === 4 && (int) $out[1]['others'] === 0,
   'a like on a different review is its own row');
ok((int) $out[2]['id'] === 5 && (int) $out[3]['id'] === 4,
   'two people asking to join are two rows, because each is addressed to the reader');

/* Order is the page. A roll that reorders is a page that jumps under somebody's thumb. */
$ids = array_map(static fn(array $r) => (int) $r['id'], $out);
ok($ids === [9, 6, 5, 4], 'the page stays in the order it came in');

// A type nobody rolls, and an empty page, both come back unchanged.
ok(rmt_notifications_rollup([]) === [], 'an empty page is an empty page');
$one = [$row(1, 'follow', 'user', 3, 'ana')];
ok(rmt_notifications_rollup($one) === $one, 'a follow is never touched');

echo "notification_rollup_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
