<?php
/**
 * Telling a person from a fetcher, and refusing to pretend the line is sharp.
 *
 * The correction this guards. The dashboard's top line was every signed out session that reached
 * an indexable page, and on this site that was overwhelmingly automated: 8,066 of 8,272 sessions
 * lasted zero seconds, 118 of 118 browsers never came back, and Search Console recorded zero
 * clicks across the same window. Every rate on the page was therefore divided by crawlers, which
 * reports a catastrophe every week and teaches whoever reads it to stop looking.
 *
 * The rules are asserted here rather than trusted because a bot filter has two opposite ways to be
 * wrong and both are quiet:
 *
 *   TOO EAGER, and a real person disappears from the numbers. So a single page view with nothing
 *   after it stays UNCERTAIN, never automated: a bored human and a polite crawler are the same row
 *   and there is no honest way to split them.
 *
 *   TOO TIMID, and the correction does nothing. So a session that made several requests and never
 *   once returned the cookie we set IS automated, because no browser behaves that way.
 *
 * And a third failure that is worse than either: a filter that hides what it discarded. Every
 * bucket is reported, the raw count is kept beside the classified one, and nothing is deleted.
 *
 *   php tests/traffic_shape_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/contribution_events.php';
require BASE_PATH . '/app/traffic_shape.php';

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-54s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}
function is_logged_in(): bool { return false; }

$pdo = db();
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/052_contribution_events.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/090_event_visitor.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/093_event_cookied.sqlite.sql'));
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
$pdo->exec("INSERT INTO destinations (id,name,slug) VALUES (7,'Bangkok','bangkok-thailand')");

$at = static fn(int $sec): string => date('Y-m-d H:i:s', strtotime('-1 hour') + $sec);
$plant = static function (string $journey, string $event, int $sec, ?int $cookied, ?int $dest = null) use ($pdo, $at): void {
    $st = $pdo->prepare('INSERT INTO contribution_events (event, journey, visitor, cookied, destination_id, is_authed, created_at)
                         VALUES (?,?,?,?,?,0,?)');
    $st->execute([$event, $journey, 'v' . substr(md5($journey), 0, 15), $cookied, $dest, $at($sec)]);
};

echo "-- a person is recognised --\n";
// Somebody who read three pages over two minutes, returning the cookie after the first one.
$plant('j-reader', 'landing_view', 0, 0);
$plant('j-reader', 'destination_page_view', 40, 1, 7);
$plant('j-reader', 'join_view', 120, 1);
// Somebody who did something only a person does.
$plant('j-actor', 'landing_view', 0, 0);
$plant('j-actor', 'join_submit', 90, 1);
// Somebody who signed up.
$plant('j-signup', 'join_view', 0, 0);
$plant('j-signup', 'join_submit', 45, 1);
$plant('j-signup', 'join_created', 46, 1);

$s = rmt_traffic_shape(0);
ok('a reader with several pages over human time is human', $s['sessions']['likely_human'] >= 3, true);
ok('...and the reason is recorded', isset($s['why']['human']['returned_the_cookie_we_set'])
    || isset($s['why']['human']['did_something_only_a_person_does']), true);

echo "\n-- a fetcher is recognised, and only on evidence --\n";
$pdo->exec('DELETE FROM contribution_events');
// Six requests, instantly, never returning a cookie: the shape that was being counted as arrivals.
for ($i = 0; $i < 6; $i++) $plant('j-crawl', 'landing_view', 0, 0);
// Two requests, a second apart, no cookie ever: a link follower.
$plant('j-follow', 'landing_view', 0, 0);
$plant('j-follow', 'review_signup_required', 1, 0);
// The join form, instantly, and nothing else.
$plant('j-join', 'join_view', 0, 0);

$s = rmt_traffic_shape(0);
ok('a burst of requests with no cookie is automated', $s['sessions']['likely_automated'] >= 2, true);
ok('and the evidence is named',
   isset($s['why']['automated']['several_requests_never_returned_a_cookie'])
   || isset($s['why']['automated']['several_pages_same_second']), true);
ok('none of them was called human', $s['sessions']['likely_human'], 0);

echo "\n-- one page and nothing after it stays uncertain --\n";
/* The case the correction must NOT get wrong. A person who reads one page and leaves has exactly
   the same row as a crawler that fetches one page, and calling it automated would delete real
   people from the numbers to make a chart look better. */
$pdo->exec('DELETE FROM contribution_events');
$plant('j-bounce', 'landing_view', 0, 0);
$s = rmt_traffic_shape(0);
ok('a single first hit is not called automated', $s['sessions']['likely_automated'], 0);
ok('...nor claimed as human', $s['sessions']['likely_human'], 0);
ok('...it is uncertain, and visible', $s['sessions']['uncertain'], 1);

echo "\n-- history is not reinterpreted --\n";
/* Rows written before the cookie bit existed carry NULL. They must not be swept into the automated
   pile on the strength of a signal that was never recorded for them. */
$pdo->exec('DELETE FROM contribution_events');
$plant('j-old', 'landing_view', 0, null);
$plant('j-old', 'review_signup_required', 2, null);
$s = rmt_traffic_shape(0);
ok('two old rows with no bit are not called automated', $s['sessions']['likely_automated'], 0);
ok('...they stay uncertain', $s['sessions']['uncertain'], 1);

echo "\n-- nothing is thrown away --\n";
$pdo->exec('DELETE FROM contribution_events');
for ($i = 0; $i < 4; $i++) $plant('j-a', 'landing_view', 0, 0);
$plant('j-b', 'landing_view', 0, 0);
$plant('j-c', 'landing_view', 0, 0);
$plant('j-c', 'join_submit', 30, 1);
$s = rmt_traffic_shape(0);
$total = $s['sessions']['likely_human'] + $s['sessions']['likely_automated'] + $s['sessions']['uncertain'];
ok('every session lands in exactly one bucket', $total, $s['sessions']['total']);
ok('the rows are all still there',
   (int) $pdo->query('SELECT COUNT(*) FROM contribution_events')->fetchColumn(), 7);
ok('the shape evidence is reported, not just the verdict',
   isset($s['shape']['session_duration'], $s['shape']['events_per_session'], $s['overcounting']), true);

echo "\n-- the bit itself --\n";
/* It records whether the CLIENT sent a token back, which is why a token minted during this request
   does not count: a real person's first page has no cookie either. */
$src = (string) file_get_contents(BASE_PATH . '/app/contribution_events.php');
ok('the tracker writes the bit', str_contains($src, 'rmt_visitor_presented_cookie() ? 1 : 0'), true);
ok('a token minted in this request does not count as returned',
   str_contains($src, "!(\$GLOBALS['_rmt_visitor_minted'] ?? false)"), true);
ok('and it is still the case that no agent is stored',
   (bool) preg_match('/INSERT INTO contribution_events.*user_agent/s', $src), false);

echo "\n-- the headline is people, and the raw number survives --\n";
$growth = (string) file_get_contents(BASE_PATH . '/app/growth_funnel.php');
ok('the funnel divides by the human count', str_contains($growth, '$covers ? $pct($members, $visitsHuman) : null'), true);
ok('the raw count is still published', str_contains($growth, "'visits_raw' => \$visits"), true);
ok('so are the other two buckets',
   str_contains($growth, "'visits_automated'") && str_contains($growth, "'visits_uncertain'"), true);
ok('and the spine says which one it is showing', str_contains($growth, 'Arrived (likely human)'), true);

echo "\n-- measuring must never break the site --\n";
$pdo->exec('DROP TABLE contribution_events');
$threw = false;
try { rmt_track('landing_view'); } catch (Throwable $e) { $threw = true; }
ok('a write against a missing table still does not throw', $threw, false);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL TRAFFIC SHAPE TESTS PASS ({$pass})\n";
