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
