<?php
/**
 * The contribution funnel: what it counts, what it refuses to store, and what it must not get wrong.
 *
 * A funnel that miscounts is worse than none, because it will be used to decide what to change.
 * Most of what follows is about the ways a plausible implementation still returns a number and the
 * number is a lie.
 *
 *   php tests/contribution_events_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/contribution_events.php';

// The tracker asks whether somebody is signed in; in a unit test that is whatever we say it is.
$GLOBALS['test_authed'] = false;
function is_logged_in(): bool { return (bool) ($GLOBALS['test_authed'] ?? false); }

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-60s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/052_contribution_events.sqlite.sql'));

// The journey token lives in the session, so the test drives it through the session rather than
// redefining the function -- PHP hoists a test file's declarations before the require runs, so a
// stub here would collide with the real one rather than replace it.
@session_start();
$setJourney = static function (string $j): void { $_SESSION['_journey'] = $j; };

echo "-- what it refuses to store --\n";
rmt_track('not_a_real_event', ['source' => 'place']);
check('an event outside the list is dropped',
      (int) $pdo->query("SELECT COUNT(*) FROM contribution_events")->fetchColumn(), 0);

rmt_track('review_cta_click', ['source' => 'from-a-hostile-client', 'reason' => 'whatever']);
$row = $pdo->query("SELECT source, reason FROM contribution_events")->fetch();
check('an unknown source is stored as nothing', $row['source'], null);
check('an unknown reason is stored as nothing', $row['reason'], null);

$cols = array_column($pdo->query("PRAGMA table_info(contribution_events)")->fetchAll(), 'name');
check('there is no user column to fill in',   in_array('user_id', $cols, true), false);
check('there is no address column',           in_array('ip', $cols, true), false);
check('there is no user agent column',        in_array('user_agent', $cols, true), false);
check('there is nowhere to put review text',  in_array('body', $cols, true), false);
check('what it does hold', $cols,
      ['id','event','source','journey','place_id','destination_id','is_authed','reason','created_at']);

$pdo->exec('DELETE FROM contribution_events');

echo "\n-- an attempt is a journey, not a click --\n";
$setJourney('j-1');
rmt_track('review_cta_click', ['source' => 'place', 'place_id' => 7]);
rmt_track('review_cta_click', ['source' => 'place', 'place_id' => 7]);   // impatient double click
rmt_track('review_cta_click', ['source' => 'place', 'place_id' => 7]);
$c = rmt_funnel_counts(30);
check('three clicks in one attempt count once', $c['review_cta_click'], 1);
check('rows are still all there',
      (int) $pdo->query("SELECT COUNT(*) FROM contribution_events")->fetchColumn(), 3);

echo "\n-- a complete anonymous journey --\n";
rmt_track('review_signup_required', ['source' => 'place', 'place_id' => 7]);
$GLOBALS['test_authed'] = true;
rmt_track('review_signup_completed');
rmt_track('review_return_after_auth');
rmt_track('review_form_start', ['source' => 'place', 'place_id' => 7]);
rmt_track('review_submit_attempt', ['source' => 'place']);
rmt_track('review_publish_success', ['place_id' => 7]);

$steps = array_column(rmt_funnel_steps(30), 'count', 'key');
check('the click is counted',       $steps['review_cta_click'], 1);
check('the form is counted',        $steps['review_form_start'], 1);
check('the signup step is counted', $steps['review_signup_required'], 1);
check('the return is counted',      $steps['review_return_after_auth'], 1);
check('the submit is counted',      $steps['review_submit_attempt'], 1);
check('the publish is counted',     $steps['review_publish_success'], 1);

$auth = rmt_funnel_by_auth(30);
check('the attempt is counted as anonymous, because that is how it started',
      $auth['anonymous']['started'], 1);
check('...and as published', $auth['anonymous']['published'], 1);
check('nothing is double counted under signed-in', $auth['authed']['started'], 0);

// The publish event carries no source. A query that filtered the journey down to rows WITH a
// source threw the publish away and reported every successful attempt as unpublished.
$src = array_column(rmt_funnel_by_source(30), null, 'source');
check('the attempt is attributed to where it started', array_keys($src), ['place']);
check('...and its publish is counted despite the publish row having no source',
      $src['place']['published'], 1);
check('...against one attempt', $src['place']['attempts'], 1);

echo "\n-- a second attempt is a second attempt --\n";
rmt_journey_rotate();      // the real one: a publish ends an attempt
check('rotating starts a different attempt', $_SESSION['_journey'] === 'j-1', false);
rmt_track('review_cta_click', ['source' => 'browse', 'place_id' => 9]);
rmt_track('review_submit_attempt', ['source' => 'browse']);
rmt_track('review_publish_failure', ['reason' => 'validation']);
$c = rmt_funnel_counts(30);
check('two attempts clicked', $c['review_cta_click'], 2);
check('one of them failed',   $c['review_publish_failure'], 1);
$auth = rmt_funnel_by_auth(30);
check('the signed-in attempt is counted separately', $auth['authed']['started'], 1);
check('...and did not publish', $auth['authed']['published'], 0);

$f = array_column(rmt_funnel_failures(30), 'n', 'reason');
check('the failure reason is grouped', (int) ($f['validation'] ?? 0), 1);
check('and it is a reason, not content',
      in_array('validation', RMT_CONTRIB_REASONS, true), true);

$bySource = array_column(rmt_funnel_by_source(30), null, 'source');
check('both surfaces are reported', count($bySource), 2);
check('the failing one shows no publishes', $bySource['browse']['published'], 0);

echo "\n-- windows --\n";
$pdo->exec("UPDATE contribution_events SET created_at = '2020-01-01 00:00:00'");
check('an old attempt is outside 30 days', rmt_funnel_counts(30)['review_cta_click'], 0);
check('...and inside all time',            rmt_funnel_counts(0)['review_cta_click'], 2);
check('all time reaches back far enough',  rmt_funnel_since(0) < '2020-01-01 00:00:00', true);

echo "\n-- the step list reads in journey order --\n";
$order = array_column(rmt_funnel_steps(30), 'key');
check('ordered as the journey happens', $order, [
    'review_cta_click', 'review_form_start', 'review_signup_required',
    'review_return_after_auth', 'review_submit_attempt',
    'review_verification_required', 'review_publish_success',
]);
$branch = array_column(rmt_funnel_steps(30), 'branch', 'key');
check('the steps only some attempts take are marked as such',
      [$branch['review_signup_required'], $branch['review_verification_required'], $branch['review_submit_attempt']],
      [true, true, false]);

echo "\n-- no fabricated community data --\n";
// A guard, not a formality: this table must never become a place where review-like rows live.
check('the event vocabulary contains nothing that looks like a review',
      array_values(array_filter(RMT_CONTRIB_EVENTS,
          static fn($e) => str_contains($e, 'rating') || str_contains($e, 'text'))), []);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
