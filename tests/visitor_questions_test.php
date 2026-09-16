<?php
/**
 * One question, asked once.
 *
 * The rules locked down here are the ones that would make the answers worthless or the site worse:
 * only our questions, only their answers, never twice to the same person, and the words somebody
 * typed never reaching the event stream.
 *
 * It is also deliberately named nothing like `feedback`, which on this site already means
 * corrections to a place and has its own table. Two tables one word apart is how a later query
 * silently reads the wrong one.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec((string) file_get_contents(BASE_PATH . '/database/migrations/095_visitor_answers.sqlite.sql'));
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT)');
$pdo->exec("INSERT INTO users (id,username) VALUES (1,'a'),(2,'b'),(3,'c'),(4,'d')");

require_once BASE_PATH . '/app/visitor_questions.php';

$pass = 0; $fail = 0;
function ok(string $what, $got, $want = true): void {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  [PASS] $what\n"; }
    else { $fail++; echo "  [FAIL] $what  expected=" . var_export($want, true)
                      . " got=" . var_export($got, true) . "\n"; }
}

echo "\n-- it does not collide with the corrections queue --\n";
ok('the table is its own',   str_contains((string) file_get_contents(BASE_PATH . '/app/visitor_questions.php'),
                                          'visitor_answers'), true);
ok('and nothing here is called feedback',
   (bool) preg_match('/feedback/i', (string) file_get_contents(BASE_PATH . '/app/visitor_questions.php')), false);

echo "\n-- only our questions, only their answers --\n";
ok('a question we ask is valid',       rmt_vq_valid('no_match_hoped_for', 'locals'), true);
ok('an answer we do not offer is not', rmt_vq_valid('no_match_hoped_for', 'anything_else'), false);
ok('a question we do not ask is not',  rmt_vq_valid('made_up', 'locals'), false);
ok('and neither writes a row',         rmt_vq_record(1, 'made_up', 'locals'), false);
ok('...nor an invented answer',        rmt_vq_record(1, 'no_match_hoped_for', 'whatever'), false);
ok('nothing was written at all',       (int) $pdo->query('SELECT COUNT(*) FROM visitor_answers')->fetchColumn(), 0);

echo "\n-- asked once --\n";
ok('a real answer is recorded',  rmt_vq_record(1, 'no_match_hoped_for', 'locals', 'was hoping for locals'), true);
ok('the same person is not asked again', rmt_vq_record(1, 'no_match_hoped_for', 'just_looking'), false);
ok('somebody else still can',    rmt_vq_record(2, 'no_match_hoped_for', 'travelers_same_dates'), true);
ok('two rows, not three',        (int) $pdo->query('SELECT COUNT(*) FROM visitor_answers')->fetchColumn(), 2);
ok('the page knows not to ask again', rmt_vq_answered(1, 'no_match_hoped_for'), true);

echo "\n-- their words are theirs --\n";
rmt_vq_record(3, 'no_match_hoped_for', 'locals', str_repeat('x', 500));
ok('a long note is capped, not refused',
   mb_strlen((string) $pdo->query('SELECT note FROM visitor_answers WHERE user_id=3')->fetchColumn()),
   RMT_VQ_NOTE_MAX);
rmt_vq_record(4, 'no_match_hoped_for', 'locals', '   ');
ok('an empty note is stored as nothing',
   $pdo->query('SELECT note FROM visitor_answers WHERE user_id=4')->fetchColumn(), null);
$src = (string) file_get_contents(BASE_PATH . '/app/visitor_questions.php');
ok('the note never reaches telemetry', (bool) preg_match('/rmt_track\(/', $src), false);
$ctrl = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('and the controller does not put it there either',
   (bool) preg_match("/rmt_track\([^)]*input\('note'\)/", $ctrl), false);

echo "\n-- asked only at moments worth asking at --\n";
ok('there are two questions, not a survey', count(RMT_VQ_QUESTIONS), 2);
ok('one is for an empty match list',  isset(RMT_VQ_QUESTIONS['no_match_hoped_for']), true);
ok('one is for a first trip',         isset(RMT_VQ_QUESTIONS['after_first_trip']), true);
foreach (RMT_VQ_QUESTIONS as $k => $qq) {
    ok("$k actually asks something", str_contains((string) $qq['question'], '?'), true);
    ok("$k offers between three and six answers",
       count($qq['answers']) >= 3 && count($qq['answers']) <= 6, true);
}
/* Neither is a popup. Both are a disclosure the reader opens, on a page they were already on. */
$partial = (string) file_get_contents(BASE_PATH . '/views/_visitor_question.php');
ok('it is a disclosure, not a modal', str_contains($partial, '<details'), true);
ok('nothing about it is a dialog',    (bool) preg_match('/<dialog|role="dialog"|modal/i', $partial), false);
$matchesView = (string) file_get_contents(BASE_PATH . '/views/matches.php');
$tripView    = (string) file_get_contents(BASE_PATH . '/views/trip_show.php');
ok('the empty match list asks the first', str_contains($matchesView, "'no_match_hoped_for'"), true);
ok('your own trip page asks the second',  str_contains($tripView, "'after_first_trip'"), true);

echo "\n-- where it sends somebody afterwards --\n";
$ctrl2 = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
$fn = substr($ctrl2, (int) strpos($ctrl2, 'function visitor_answer_submit'), 900);
ok('an off site return is refused',      str_contains($fn, "str_contains(\$back, '://')"), true);
ok('a protocol relative one too',        str_contains($fn, "str_starts_with(\$back, '//')"), true);
ok('and the fallback is a page of ours', str_contains($fn, "'/matches'"), true);


echo "\n-- the summary reads what is there --\n";
$sum = rmt_vq_summary('no_match_hoped_for');
ok('it counts every answer', $sum['total'], 4);
ok('grouped by answer',      $sum['answers']['locals'], 3);
ok('and carries the notes',  count($sum['notes']), 2);

echo "\n-- it cannot break a page --\n";
/* The table is dropped rather than a second database opened, because db() holds one connection for
   the process. Same check either way: a database that has not run this migration. */
$pdo->exec('DROP TABLE visitor_answers');
ok('a summary against a missing table returns nothing', rmt_vq_summary('no_match_hoped_for')['total'], 0);
ok('the prompt stays quiet rather than throwing',       rmt_vq_answered(1, 'no_match_hoped_for'), true);
ok('and recording fails rather than throwing',          rmt_vq_record(2, 'no_match_hoped_for', 'locals'), false);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL VISITOR QUESTION TESTS PASS ({$pass})\n";
