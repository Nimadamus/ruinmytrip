<?php
/**
 * A guest review kept on the server (app/review_drafts.php, migration 107).
 *
 * What must hold:
 *   - a kept review comes back for the same browser token, and for no other.
 *   - the database holds only sha256 of the token, never the token itself.
 *   - only the form's text fields are kept: no CSRF token, no submit token, no action.
 *   - an expired row reads as nothing, and the next write sweeps it.
 *   - clearing deletes the row and the cookie, so a saved review cannot surface again.
 *   - a second write from the same browser replaces the first instead of adding a row.
 *   - a broken or foreign cookie value is ignored.
 *   - the localStorage key matches the one views/review_new.php gives the form.
 *
 *   php tests/review_drafts_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
const RMT_PLAN_DRAFT_KEY = 'plan_draft';
require BASE_PATH . '/app/review_drafts.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

db()->exec((string) file_get_contents(BASE_PATH . '/database/migrations/107_review_drafts.sqlite.sql'));

$post = ['_csrf' => 'secret-token', '_submit' => 'abc', 'action' => 'publish', 'place_id' => '39',
         'destination_id' => '78', 'subject_type' => 'restaurant', 'subject_name' => 'A Brasileira',
         'rating' => '5', 'body' => 'Great coffee, but the terrace is a tourist trap at noon.',
         'aspect' => ['food' => '5', 'value' => '2']];

ok('nothing is kept before anything is written', rmt_review_draft_get() === null);
ok('the write succeeds', rmt_review_draft_put($post));
$token = rmt_review_draft_token();
ok('the browser now holds a 48 hex character token', (bool) preg_match('/^[a-f0-9]{48}$/', $token));

$row = q_one('SELECT * FROM review_drafts');
ok('the database holds the token hash, not the token', $row && $row['token_hash'] === hash('sha256', $token)
   && !str_contains((string) json_encode($row), $token));
ok('the place is recorded for the row', (int) $row['place_id'] === 39);

$got = rmt_review_draft_get();
ok('the same browser gets its review back', $got !== null && $got['body'] === $post['body']);
ok('the CSRF token is never kept', !isset($got['_csrf']) && !isset($got['_submit']) && !isset($got['action']));
ok('sub ratings ride along', ($got['aspect']['food'] ?? '') === '5');

$_COOKIE[RMT_REVIEW_DRAFT_COOKIE] = str_repeat('a', 48);
ok('another token sees nothing', rmt_review_draft_get() === null);
$_COOKIE[RMT_REVIEW_DRAFT_COOKIE] = '../../etc/passwd';
ok('a malformed cookie is ignored', rmt_review_draft_token() === '' && rmt_review_draft_get() === null);
$_COOKIE[RMT_REVIEW_DRAFT_COOKIE] = $token;

rmt_review_draft_put(['body' => 'Changed my mind about the terrace, it was fine at five.'] + $post);
ok('a second write replaces the first', (int) q_one('SELECT COUNT(*) c FROM review_drafts')['c'] === 1
   && str_starts_with((string) rmt_review_draft_get()['body'], 'Changed my mind'));
ok('and keeps the same browser token', rmt_review_draft_token() === $token);

q_run('UPDATE review_drafts SET expires_at = ?', ['2000-01-01 00:00:00']);
ok('an expired review reads as nothing', rmt_review_draft_get() === null);
unset($_COOKIE[RMT_REVIEW_DRAFT_COOKIE]);
rmt_review_draft_put($post);
ok('the next write sweeps expired rows', (int) q_one('SELECT COUNT(*) c FROM review_drafts')['c'] === 1
   && rmt_review_draft_get() !== null);

rmt_review_draft_clear();
ok('clearing deletes the row', (int) q_one('SELECT COUNT(*) c FROM review_drafts')['c'] === 0);
ok('and forgets the cookie', rmt_review_draft_token() === '' && rmt_review_draft_get() === null);

ok('the localStorage key matches the form', rmt_review_draft_js_key($post) === 'new-39-78');
ok('an unbound review has a zero place in its key', rmt_review_draft_js_key(['destination_id' => '5']) === 'new-0-5');

$long = rmt_review_draft_clean(['body' => str_repeat('x', 11000)]);
ok('a long review is kept whole up to the validator limit', mb_strlen($long['body']) === 11000);
ok('and capped past it', mb_strlen(rmt_review_draft_clean(['body' => str_repeat('x', 13000)])['body']) === 12000);
$junk = [];
for ($i = 0; $i < 500; $i++) $junk['junk' . $i] = str_repeat('z', 6000);
$big = rmt_review_draft_clean($junk + ['body' => 'Real words here.', 'aspect' => array_combine(
    array_map(static fn($i) => 'a' . chr(97 + $i % 26) . 'x', range(0, 89)), array_fill(0, 90, '5'))]);
ok('fields the form does not have are dropped', array_keys($big) === ['body', 'aspect']);
ok('sub ratings are capped at 20', count($big['aspect']) <= 20);
ok('a junk flood stays small', strlen((string) json_encode($big)) < 2000);
ok('a bad aspect name is dropped', !isset(rmt_review_draft_clean(['aspect' => ['../x' => '5']])['aspect']));

echo $fails ? "\nreview_drafts_test: $fails FAILED\n" : "\nreview_drafts_test: all passed\n";
exit($fails ? 1 : 0);
