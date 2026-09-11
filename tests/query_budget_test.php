<?php
/**
 * How many database queries each main page runs, as a number rather than a suspicion.
 *
 * Every page here is loaded against the development database with the counter on, and held to a
 * budget. The budgets are not aspirations: they are set a little above what the page does today, so
 * that the test passes now and fails the day somebody adds a query inside a loop. That is the only
 * kind of performance test worth having on a site this size. It is not measuring speed, which
 * depends on the machine; it is measuring shape, which does not.
 *
 * It also reports any statement run three or more times on one page, which is what an N+1 looks
 * like from the outside: the same normalised SQL, over and over, once per row.
 *
 * If a budget here is too tight after a legitimate change, raise it in the same commit that makes
 * the page do more, and say why. Raising it in a separate commit is how a budget stops meaning
 * anything.
 *
 *   php tests/query_budget_test.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/app/controllers.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

/** Run one page's controller with the counter on, swallowing its output. */
function rmt_measure(callable $fn): array {
    rmt_q_start();
    ob_start();
    /* A controller ends by rendering and exiting, or by redirecting. Either is fine: the count is
       taken from what it did before it stopped. An Error, though, means the page did not run at
       all, and a budget met by crashing is not a budget met. */
    $err = null;
    try { $fn(); } catch (Error $e) { $err = $e->getMessage(); } catch (Throwable $e) { }
    $GLOBALS['rmt_measure_error'] = $err;
    ob_end_clean();
    return rmt_q_report();
}

$dest = q_one("SELECT * FROM destinations WHERE slug = 'lisbon-portugal'")
     ?: q_one('SELECT * FROM destinations ORDER BY id LIMIT 1');
$trip = q_one("SELECT * FROM trips WHERE status = 'published' ORDER BY id LIMIT 1");
$act  = q_one("SELECT * FROM trip_activities WHERE status = 'published' ORDER BY id LIMIT 1");
$place = q_one("SELECT * FROM places WHERE status = 'active' ORDER BY id LIMIT 1");
$user = q_one("SELECT * FROM users WHERE status = 'active' ORDER BY id LIMIT 1");

if (!$dest || !$user) { echo "FAIL: the development database has no destinations or users\n"; exit(1); }

/* Each case: a name, the work, and the budget. The budget counts EVERY statement, including the
   ones the layout runs for the navigation. */
$cases = [];

$cases[] = ['the city page', 60, static function () use ($dest) {
    destination(['slug' => (string) $dest['slug']]);
}];

$cases[] = ['the travelers page', 26, static function () use ($dest) {
    destination_travelers(['slug' => (string) $dest['slug']]);
}];

if ($trip) {
    $cases[] = ['a trip page', 28, static function () use ($trip) {
        trip_show(['id' => (string) $trip['id']]);
    }];
}
if ($act) {
    $cases[] = ['a plan page', 16, static function () use ($act) {
        activity_show(['id' => (string) $act['id']]);
    }];
}
if ($place) {
    $cases[] = ['a place page', 32, static function () use ($place) {
        place_show(['slug' => (string) $place['slug']]);
    }];
}
$cases[] = ['the meetups page', 12, static function () { meetups_index([]); }];

/* The signed-in feed, which is the page that grows with a member rather than with the site: it
   reads their follows, their saves, their trips and then ranks. It is measured last because it
   needs a session, and current_user() caches, so nothing before it may have asked. */
$_SESSION['uid'] = (int) $user['id'];
$cases[] = ['the signed-in feed', 60, static function () { feed([]); }];
$cases[] = ['search for a city', 20, static function () {
    $_GET['q'] = 'lisbon';
    search([]);
    unset($_GET['q']);
}];

$worst = [];
foreach ($cases as [$name, $budget, $fn]) {
    $r = rmt_measure($fn);
    $err = $GLOBALS['rmt_measure_error'] ?? null;
    if ($err !== null) { ok(false, sprintf('%s did not run: %s', $name, $err)); continue; }
    ok($r['total'] > 0 && $r['total'] <= $budget,
       sprintf('%s runs %d queries, budget %d', $name, $r['total'], $budget));
    if ($r['repeats']) {
        $top = $r['repeats'][0];
        $worst[] = sprintf('%s: %d x %s', $name, $top['n'], mb_strimwidth($top['sql'], 0, 90, '...'));
    }
}

/* Repeated statements are reported rather than failed. Three lookups of the same destination row
   on a page that shows three cities is fine; forty is an N+1. The number is here so a person can
   tell the difference, and so a new one shows up in the diff of a test run. */
if ($worst) {
    echo "\n  repeated statements, worth a look:\n";
    foreach (array_slice($worst, 0, 8) as $w) echo "    $w\n";
}

echo "\nquery_budget_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
