<?php
/**
 * Regression tests for the join funnel (rmt_join_source, rmt_signup_funnel).
 *
 * The contribution funnel has been measured since the day it was built. The JOIN funnel never was,
 * on a site whose stated problem is that it has no members, so every change to the front door was
 * an opinion with nothing behind it. This is the measurement, and it has the same two rules the
 * rest of that table has: a closed list of events, and no way to identify a person.
 *
 *   php tests/signup_funnel_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
session_start();

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/contribution_events.php';

/* A request with no user agent is a script, and rmt_track() declines to record one: a funnel
   counting robots is a funnel nobody believes. The CLI has no agent, so the harness supplies
   the one a traveler would arrive with. */
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/605.1';

function is_logged_in(): bool { return false; }

db()->exec('CREATE TABLE contribution_events (id INTEGER PRIMARY KEY AUTOINCREMENT, event TEXT,
              source TEXT, journey TEXT, place_id INT, destination_id INT, is_authed INT,
              reason TEXT, created_at TEXT)');

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

// Where a signup came from, read off the return path the form already carries.
$cases = [
    '/d/lisbon-portugal/travelers' => 'travelers',
    '/d/lisbon-portugal'           => 'destination',
    '/going'                       => 'going',
    '/matches'                     => 'matches',
    '/meetup/12'                   => 'meetups',
    '/meetups'                     => 'meetups',
    '/talk'                        => 'talk',
    '/post/8'                      => 'talk',
    '/blog/tourist-taxes-2026'     => 'blog',
    '/p/vasa-museum-stockholm'     => 'place',
    '/review/new?place=3'          => 'review',
    '/invite'                      => 'invite',
    '/'                            => 'home',
    '/travelers'                   => 'travelers',
    '/leaderboard'                 => 'other',
    ''                             => 'other',
];
$bad = [];
foreach ($cases as $path => $want) {
    if (rmt_join_source($path) !== $want) $bad[] = "$path => " . rmt_join_source($path) . " (want $want)";
}
ok('every join source maps to a known surface', $bad === [], implode('; ', $bad));
ok('every source it returns is in the closed list',
   count(array_diff(array_values($cases), RMT_CONTRIB_SOURCES)) === 0);

// The events are on the closed list, or rmt_track drops them silently and the funnel is a lie.
foreach (['join_view', 'join_submit', 'join_created', 'join_confirmed', 'join_first_action', 'join_failure'] as $e) {
    ok("$e is a recordable event", in_array($e, RMT_CONTRIB_EVENTS, true));
}

// One journey through the door.
rmt_track('join_view', ['source' => 'travelers']);
rmt_track('join_view', ['source' => 'travelers']);   // a reload is not a second person
rmt_track('join_submit', ['source' => 'travelers']);
rmt_track('join_created', ['source' => 'travelers']);
rmt_track('join_confirmed');
rmt_track('join_first_action');
$f = rmt_signup_funnel(30);
ok('a reload counts once', $f['steps']['join_view'] === 1, json_encode($f['steps']));
ok('the whole path is counted', $f['steps']['join_created'] === 1 && $f['steps']['join_confirmed'] === 1
    && $f['steps']['join_first_action'] === 1, json_encode($f['steps']));
ok('the recruiting page is attributed', ($f['by_source']['travelers']['created'] ?? 0) === 1,
   json_encode($f['by_source']));

// A second attempt, from a different page, that gives up at the form.
$_SESSION['_journey'] = 'second';
rmt_track('join_view', ['source' => 'blog']);
rmt_track('join_failure', ['source' => 'blog', 'reason' => 'rate_limit']);
$f = rmt_signup_funnel(30);
ok('two attempts, two views', $f['steps']['join_view'] === 2, json_encode($f['steps']));
ok('a refusal is counted', $f['steps']['join_failure'] === 1);
ok('the page that did not convert is still listed',
   ($f['by_source']['blog']['views'] ?? 0) === 1 && ($f['by_source']['blog']['created'] ?? 0) === 0,
   json_encode($f['by_source']));
ok('sources are ordered by who sends the most', array_key_first($f['by_source']) === 'travelers');

// The table still cannot name anybody.
$row = q_one("SELECT * FROM contribution_events WHERE event='join_created'");
ok('no user id is stored', !array_key_exists('user_id', $row));
ok('a made up event is dropped rather than stored',
   (function () { rmt_track('join_whatever'); return (int) q_one("SELECT COUNT(*) c FROM contribution_events WHERE event='join_whatever'")['c'] === 0; })());

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
