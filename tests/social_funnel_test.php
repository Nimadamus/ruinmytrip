<?php
/**
 * The social funnel: the events added 2026-09-15 and the numbers built on them.
 *
 * The review funnel has its own suite. This one guards the loop the product is actually built
 * around, and in particular the four ways a funnel like this goes quietly wrong:
 *
 *   1. IT BREAKS THE PRODUCT. A telemetry write must never reach the person. The last case in
 *      this file drops the table out from under the tracker and asserts that posting still works,
 *      because "analytics fails gracefully" is a claim worth proving rather than asserting.
 *   2. IT COUNTS THE WRONG UNIT. Three presses in one session are one attempt. Two different
 *      cities in one session are two views, not one, which is the bug rmt_track_once_for exists
 *      to avoid: keyed on the event alone, the first city anybody opened would be the only city
 *      with any traffic at all.
 *   3. IT LEARNS TOO MUCH. There is still no user id, no address, no agent and no content in this
 *      table. The one identifier added is random bytes recognising a browser, and the test asserts
 *      what it is made of.
 *   4. IT ATTRIBUTES BY GUESSING. A signup is credited to the city in the same session, read from
 *      the token the table already keeps. Nothing follows anybody anywhere.
 *
 *   php tests/social_funnel_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
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

$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/605.1';
$GLOBALS['test_authed'] = false;
function is_logged_in(): bool { return (bool) ($GLOBALS['test_authed'] ?? false); }

$fail = 0; $pass = 0;
function check(string $name, $got, $expect): void {
    global $fail, $pass;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-58s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/052_contribution_events.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/090_event_visitor.sqlite.sql'));
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
$pdo->exec("INSERT INTO destinations (id,name,slug) VALUES (7,'Bangkok','bangkok-thailand'),(9,'Lisbon','lisbon-portugal')");

@session_start();
$journey = static function (string $j): void {
    $_SESSION['_journey'] = $j;
    $_SESSION['_tracked'] = [];
    $_SESSION['_tracked_keyed'] = [];
};

echo "-- every event the product promised to measure exists --\n";
/* A name in this list and nowhere else is a counter that reads zero forever, which is worse than
   a missing counter because it looks like an answer. */
foreach (['destination_page_view', 'destination_follow_click', 'destination_follow_success',
          'ask_question_click', 'question_posted', 'join_submit', 'join_created', 'login_completed',
          'profile_viewed', 'profile_edit_started', 'profile_completed', 'trip_create_started',
          'trip_created', 'traveler_profile_clicked', 'overlapping_traveler_viewed', 'post_created',
          'comment_created', 'reaction_created', 'message_started', 'destination_return_visit'] as $e) {
    check("'$e' is a known event", in_array($e, RMT_CONTRIB_EVENTS, true), true);
}

echo "\n-- the unit is an attempt, and a city is not an event --\n";
$journey('j-dedupe');
rmt_track_once_for('destination_page_view', '7', ['destination_id' => 7]);
rmt_track_once_for('destination_page_view', '7', ['destination_id' => 7]);
rmt_track_once_for('destination_page_view', '7', ['destination_id' => 7]);
check('three views of one city in one session are one row',
      (int) $pdo->query("SELECT COUNT(*) FROM contribution_events WHERE event='destination_page_view'")->fetchColumn(), 1);
rmt_track_once_for('destination_page_view', '9', ['destination_id' => 9]);
check('a second city in the same session is its own row',
      (int) $pdo->query("SELECT COUNT(*) FROM contribution_events WHERE event='destination_page_view'")->fetchColumn(), 2);

echo "\n-- a dropped row never spends the session's one slot --\n";
/* The bug this exists for, found by instrumenting three pages correctly and getting nothing back:
   a headless browser had opened them earlier in the same session, rmt_track_once() marked them as
   recorded before rmt_track() refused the row as a crawler, and every real visit after that was
   silently ignored for the rest of that session. */
$count = static fn(string $e): int => (int) db()->query("SELECT COUNT(*) FROM contribution_events WHERE event='$e'")->fetchColumn();
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; SomeBot/1.0; +http://example.invalid/bot)';
$journey('j-crawler');
rmt_track_once('trip_create_started', ['source' => 'trip']);
check('a crawler writes nothing', $count('trip_create_started'), 0);
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/605.1';
rmt_track_once('trip_create_started', ['source' => 'trip']);
check('...and the traveler behind it is still counted', $count('trip_create_started'), 1);
rmt_track_once('trip_create_started', ['source' => 'trip']);
check('...but only once', $count('trip_create_started'), 1);

echo "\n-- a second page is not a second visit --\n";
/* The other half of the same mistake: the cookie is written on the first page of a first visit,
   so by the second page it is there, and a naive reading of it calls somebody who has not left
   yet a returning visitor. */
$newBrowser  = static function (): void { unset($GLOBALS['_rmt_visitor_id'], $_SESSION['_v_returning']); $_COOKIE = []; };
$nextRequest = static function (): void { unset($GLOBALS['_rmt_visitor_id']); };

$newBrowser();
rmt_visitor_id();
check('a browser we have never seen is not returning', rmt_visitor_is_returning(), false);
$nextRequest();
check('...and is still not returning on its second page', rmt_visitor_is_returning(), false);
/* Same cookie, new session. This is what coming back actually looks like. The cookie is set here
   by hand because the CLI has already sent its output, so setcookie() cannot echo it back the way
   a real first response does. */
unset($_SESSION['_v_returning'], $GLOBALS['_rmt_visitor_id']);
$_COOKIE[RMT_VISITOR_COOKIE] = 'abc123def4567890';
check('a browser that arrives holding the cookie is returning', rmt_visitor_is_returning(), true);
check('and it is that browser, not a new one', rmt_visitor_id(), 'abc123def4567890');

echo "\n-- the visitor token, and what it is made of --\n";
$v = rmt_visitor_id();
check('sixteen hex characters', (bool) preg_match('/^[a-f0-9]{16}$/', $v), true);
check('stable within a request', rmt_visitor_id(), $v);
/* Not derived from the person: the same request with a different address and a different agent
   still produces the same token, because the token has nothing to do with either. */
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Safari/605.1';
check('not derived from address or agent', rmt_visitor_id(), $v);
rmt_track('reaction_created');
check('it is on the row it wrote',
      (string) $pdo->query("SELECT visitor FROM contribution_events ORDER BY id DESC LIMIT 1")->fetchColumn(), $v);

$cols = array_column($pdo->query("PRAGMA table_info(contribution_events)")->fetchAll(), 'name');
foreach (['user_id', 'ip', 'user_agent', 'referrer', 'body', 'email', 'username'] as $forbidden) {
    check("there is still no $forbidden column", in_array($forbidden, $cols, true), false);
}

echo "\n-- the funnel counts sessions, in order --\n";
$pdo->exec('DELETE FROM contribution_events');
$now = date('Y-m-d H:i:s');
$plant = static function (string $journey, string $event, ?int $dest = null) use ($pdo, $now): void {
    $st = $pdo->prepare('INSERT INTO contribution_events (event, journey, visitor, destination_id, is_authed, created_at)
                         VALUES (?,?,?,?,0,?)');
    $st->execute([$event, $journey, 'v' . substr(md5($journey), 0, 15), $dest, $now]);
};
// One session that went all the way, one that landed and left, one that landed and followed.
foreach (['destination_page_view', 'destination_follow_click', 'join_submit', 'join_created',
          'destination_follow_success', 'trip_created', 'question_posted', 'destination_return_visit'] as $e) {
    $plant('j-full', $e, 7);
}
$plant('j-bounced', 'destination_page_view', 9);
$plant('j-follower', 'destination_page_view', 9);
$plant('j-follower', 'destination_follow_click', 9);
$plant('j-follower', 'destination_follow_success', 9);

$steps = [];
foreach (rmt_social_funnel(30) as $s) $steps[$s['key']] = $s['count'];
check('three sessions landed',            $steps['landed'], 3);
check('two of them touched the page',     $steps['interacted'], 2);
check('one started signing up',           $steps['signup_started'], 1);
check('one finished',                     $steps['signup_done'], 1);
check('two followed a destination',       $steps['followed'], 2);
check('one created a trip',               $steps['tripped'], 1);
check('one posted',                       $steps['posted'], 1);
check('one came back',                    $steps['returned'], 1);

echo "\n-- browsers, stated as the floor they are --\n";
$vc = rmt_visitor_counts(30);
check('three distinct browsers', $vc['unique'], 3);
check('one of them had been here before', $vc['returning'], 1);

echo "\n-- which communities are busy, and which recruited --\n";
$top = rmt_top_communities(30);
check('the busiest city leads on actions, not views', $top[0]['slug'], 'bangkok-thailand');
check('Bangkok counted its follow',   $top[0]['follows'], 1);
check('Bangkok counted its question', $top[0]['questions'], 1);
check('Lisbon is still listed',       $top[1]['slug'], 'lisbon-portugal');
check('Lisbon saw two sessions',      $top[1]['views'], 2);

$at = rmt_signup_attribution(30);
check('the signup is credited to the city in the same session', $at[0]['slug'], 'bangkok-thailand');
check('...once', $at[0]['signups'], 1);
check('and to nowhere else', count($at), 1);

echo "\n-- a telemetry failure never reaches the traveler --\n";
/* The table is gone. Every one of these would throw if the tracker did not swallow it, and a
   throw here is a 500 on a page somebody was reading. */
$pdo->exec('DROP TABLE contribution_events');
$threw = false;
try {
    rmt_track('destination_page_view', ['destination_id' => 7]);
    rmt_track_once_for('destination_page_view', '7', ['destination_id' => 7]);
    rmt_track_once('trip_created');
} catch (Throwable $e) {
    $threw = true;
}
check('writing an event against a missing table does not throw', $threw, false);
/* And reading is the same promise from the other side: a dashboard is not worth a 500 either,
   but it IS allowed to fail, because nobody is mid-journey on it. What must not happen is the
   tracker taking a page down, which is what the case above proves. */

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL SOCIAL FUNNEL TESTS PASS ({$pass})\n";
