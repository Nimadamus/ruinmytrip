<?php
/**
 * Corrections and feedback: what a submission may say, and what it must never do.
 *
 * One assertion matters more than the rest and it is the negative one: submitting a correction
 * changes nothing. An "is this closed?" form that could close a business on one anonymous click is
 * a way to damage a business, not a way to fix a page, and the tests below pin that the place row
 * is byte-identical before and after somebody reports it permanently closed.
 *
 *   php tests/feedback_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/places.php';
require BASE_PATH . '/app/feedback.php';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-58s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY, destination_id INT, slug TEXT, name TEXT,
            type TEXT, status TEXT, street_address TEXT, phone TEXT, website_url TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/058_feedback.sqlite.sql'));

$pdo->exec("INSERT INTO users (id,username,status) VALUES (1,'ada','active'), (2,'mod','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (1,'paris-france','Paris')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,type,status,street_address,phone,website_url)
            VALUES (1,1,'le-procope','Le Procope','restaurant','active','13 Rue de l''Ancienne Comedie','+33 1','https://x.test'),
                   (2,1,'shut','Shut Place','hotel','permanently_closed','1 Rue X',NULL,NULL),
                   (3,1,'hidden-one','Hidden Place','hotel','hidden','1 Rue Y',NULL,NULL)");

/* ------------------------------------------------------- nothing is changed */

$before = q_one("SELECT * FROM places WHERE id = 1");
$r = rmt_feedback_submit('closed_permanently', 1, 'Saw a notice on the door last week, it has shut.', 1);
check('a correction is accepted', $r['ok'], true);
$after = q_one("SELECT * FROM places WHERE id = 1");
// The whole point. Somebody just reported this restaurant permanently closed.
check('the place row is untouched', $after, $before);
check('the place is still active', (string) $after['status'], 'active');
check('and it is a queue row waiting for a person',
      (string) q_one("SELECT status FROM feedback WHERE id = ?", [$r['id']])['status'], 'pending');

// Ten more reports of the same thing change nothing either. There is no threshold, for the same
// reason there is none in review moderation.
for ($i = 0; $i < 10; $i++) rmt_feedback_submit('closed_permanently', 1, 'Closed, I checked again.', null);
check('ten reports still change nothing', q_one("SELECT * FROM places WHERE id = 1"), $before);
check('they are all just queue rows', rmt_feedback_open_for_place(1), 11);

/* ------------------------------------------------------------- what it takes */

check('no account needed',
      rmt_feedback_submit('wrong_hours', 1, 'It closes at four on Sundays now.', null)['ok'], true);
check('no email needed',
      rmt_feedback_submit('wrong_hours', 1, 'Still closes at four.', null, '')['ok'], true);

$bad = rmt_feedback_submit('wrong_hours', 1, 'no', null);
check('an empty message is refused', $bad['ok'], false);
check('and says what to do', str_contains((string) $bad['error'], 'sentence'), true);
check('an unknown kind is refused', rmt_feedback_submit('delete_it', 1, 'A real message here.')['ok'], false);
check('a place correction needs a place',
      rmt_feedback_submit('wrong_hours', null, 'A real message here.')['ok'], false);
// A closed place is exactly where corrections matter most -- "it has reopened", "this page still
// lists the old number" -- so refusing them would silence the reports most likely to be right.
check('a closed place IS correctable',
      rmt_feedback_submit('closed_temporarily', 2, 'Walked past, it is open again.')['ok'], true);
check('a place we have hidden is not',
      rmt_feedback_submit('wrong_hours', 3, 'A real message here.')['ok'], false);
check('a place that does not exist is refused',
      rmt_feedback_submit('wrong_hours', 999, 'A real message here.')['ok'], false);
check('an over-long message is refused',
      rmt_feedback_submit('wrong_hours', 1, str_repeat('x', RMT_FEEDBACK_MAX + 1))['ok'], false);

// A site problem is not about a place, and a place id posted alongside one is ignored rather than
// stored: otherwise "the search box is broken" ends up filed against a restaurant.
$site = rmt_feedback_submit('site_problem', 1, 'The search box does nothing on my phone.', null);
check('a site problem is accepted', $site['ok'], true);
check('and is not filed against a place',
      q_one("SELECT place_id FROM feedback WHERE id = ?", [$site['id']])['place_id'], null);

// A mistyped address costs us the reply, not the correction.
$typo = rmt_feedback_submit('wrong_hours', 1, 'Opens at nine now.', null, 'not-an-email');
check('a broken email does not lose the correction', $typo['ok'], true);
check('the broken email is dropped',
      q_one("SELECT contact_email FROM feedback WHERE id = ?", [$typo['id']])['contact_email'], null);
$good = rmt_feedback_submit('wrong_hours', 1, 'Opens at nine.', null, 'traveler@example.test');
check('a valid email is kept',
      (string) q_one("SELECT contact_email FROM feedback WHERE id = ?", [$good['id']])['contact_email'],
      'traveler@example.test');

/* ------------------------------------------------------------------ resolving */

$open = rmt_feedback_pending_count();
check('the queue knows how many are waiting', $open > 0, true);
check('resolving works', rmt_feedback_resolve($r['id'], 2, 'resolved', 'Checked the site, still open.'), true);
$row = q_one("SELECT * FROM feedback WHERE id = ?", [$r['id']]);
check('status recorded', (string) $row['status'], 'resolved');
check('who decided is recorded', (int) $row['resolved_by'], 2);
check('and what they did', (string) $row['resolution_note'], 'Checked the site, still open.');
check('the pending count drops', rmt_feedback_pending_count(), $open - 1);
// Resolving is a note about the queue, not an edit of the site.
check('resolving STILL does not touch the place', q_one("SELECT * FROM places WHERE id = 1"), $before);

check('an invented status is refused', rmt_feedback_resolve($good['id'], 2, 'deleted'), false);
check('resolving something that is not there is refused', rmt_feedback_resolve(99999, 2, 'resolved'), false);

/* --------------------------------------------------------------- the queue */

$pending = rmt_feedback_queue('pending');
check('the queue returns pending items', count($pending) > 0, true);
check('each row carries the place it is about',
      (string) $pending[0]['place_slug'] !== '' || $pending[0]['place_id'] === null, true);
check('resolved items are not in the pending queue',
      count(array_filter($pending, static fn(array $x) => (int) $x['id'] === (int) $r['id'])), 0);
check('but can be listed', count(rmt_feedback_queue('resolved')), 1);
check('an unknown status falls back to pending rather than erroring',
      count(rmt_feedback_queue('nonsense')) === count(rmt_feedback_queue('pending')), true);

/* ----------------------------------------------------------------- wording */

check('every kind has wording', count(array_filter(RMT_FEEDBACK_KINDS)), count(RMT_FEEDBACK_KINDS));
foreach (RMT_FEEDBACK_PLACE_KINDS as $k) {
    if (!isset(RMT_FEEDBACK_KINDS[$k])) { check('place kind is a known kind: ' . $k, false, true); }
}
check('place kinds are all known kinds',
      count(array_diff(RMT_FEEDBACK_PLACE_KINDS, array_keys(RMT_FEEDBACK_KINDS))), 0);
check('site kinds are not offered as place corrections',
      in_array('site_problem', RMT_FEEDBACK_PLACE_KINDS, true), false);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
