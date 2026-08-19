<?php
/**
 * Regression tests for what a block actually stops.
 *
 * rmt_is_blocked() is symmetric -- it does not matter who blocked whom, the two of them have
 * stopped interacting -- and following, complimenting and messaging all honoured that. Commenting,
 * liking, saving, voting and RSVPing did not. So a block stopped somebody sending you a message
 * and left them free to turn up in the comments under your review, or at your meetup, which is the
 * one place on this site where a block has to hold physically.
 *
 * Also pinned here: the owner column. Meetups call theirs `host_id`, and the code that read an
 * owner assumed `user_id` everywhere, so `SELECT user_id FROM meetups` threw a PDOException -- a
 * 500 page -- on every attempt to comment on or like a meetup. Confirmed before the fix:
 *
 *     rmt_can_interact('meetup', 1, $user)
 *     -> PDOException: SQLSTATE[HY000]: General error: 1 no such column: user_id
 *
 * Runs against a throwaway in-memory SQLite DB. No network, no fixtures on disk.
 *
 *   php tests/block_interactions_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';

$src = file_get_contents(BASE_PATH . '/app/controllers.php');
/** Lift one function (or const) out of controllers.php, which cannot be required on its own. */
function lift(string $src, string $name, string $kind = 'function'): string {
    $start = strpos($src, "{$kind} {$name}" . ($kind === 'function' ? '(' : ' '));
    if ($start === false) return '';
    if ($kind === 'const') {
        $end = strpos($src, ';', $start);
        return $end === false ? '' : substr($src, $start, $end - $start + 1);
    }
    $depth = 0; $i = strpos($src, '{', $start);
    for (; $i < strlen($src); $i++) {
        if ($src[$i] === '{') $depth++;
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) return substr($src, $start, $i - $start + 1); }
    }
    return '';
}
eval(implode("\n", [
    lift($src, 'RMT_INTERACT_TARGETS', 'const'),
    lift($src, 'RMT_INTERACT_OWNER_COLUMN', 'const'),
    lift($src, 'rmt_interact_owner_column'),
    lift($src, 'rmt_content_owner_id'),
    lift($src, 'rmt_blocked_from'),
    lift($src, 'rmt_can_interact'),
]));
// The real one lives in app/messages.php, which pulls in the whole conversation machinery.
require BASE_PATH . '/app/messages.php';

$pdo = db();
$pdo->exec('CREATE TABLE blocks (blocker_id INT NOT NULL, blocked_id INT NOT NULL, PRIMARY KEY (blocker_id, blocked_id))');
foreach (['trips', 'reviews', 'guides', 'blog_posts', 'collections'] as $t) {
    $pdo->exec("CREATE TABLE {$t} (id INTEGER PRIMARY KEY, user_id INT, status TEXT)");
    $pdo->exec("INSERT INTO {$t} (id,user_id,status) VALUES (1,10,'published'), (2,10,'draft')");
}
// The one that is different, and the reason this file exists.
$pdo->exec('CREATE TABLE meetups (id INTEGER PRIMARY KEY, host_id INT, status TEXT)');
$pdo->exec("INSERT INTO meetups (id,host_id,status) VALUES (1,10,'published'), (2,10,'cancelled')");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if ($cond) { echo "  PASS  $name\n"; return; }
    $fails++;
    echo "  FAIL  $name" . ($detail !== '' ? "  ($detail)" : '') . "\n";
}

$owner   = ['id' => 10, 'role' => 'user'];
$blocked = ['id' => 11, 'role' => 'user'];
$other   = ['id' => 12, 'role' => 'user'];

echo "block interactions\n";

// --- The owner column -------------------------------------------------------------------------
ok('a meetup owner is read from host_id', rmt_interact_owner_column('meetup') === 'host_id');
ok('everything else is read from user_id', rmt_interact_owner_column('review') === 'user_id');
ok('an unknown type falls back rather than erroring', rmt_interact_owner_column('spaceship') === 'user_id');

ok('the owner of a meetup resolves', rmt_content_owner_id('meetup', 1) === 10);
ok('the owner of a review resolves', rmt_content_owner_id('review', 1) === 10);
ok('a row that does not exist has no owner', rmt_content_owner_id('review', 999) === 0);
ok('a type that does not exist has no owner', rmt_content_owner_id('spaceship', 1) === 0);
ok('id 0 has no owner', rmt_content_owner_id('review', 0) === 0);

// This is the case that used to be a 500. It must return a boolean, not throw.
$threw = false;
try { $canMeetup = rmt_can_interact('meetup', 1, $other); } catch (\Throwable $e) { $threw = true; $canMeetup = null; }
ok('interacting with a meetup no longer throws', !$threw);
ok('a published meetup is interactable', $canMeetup === true);
ok('a cancelled meetup is not interactable by a stranger', rmt_can_interact('meetup', 2, $other) === false);
ok('the host can still interact with their own cancelled meetup', rmt_can_interact('meetup', 2, $owner) === true);

// --- What a block stops -----------------------------------------------------------------------
ok('with no block, nothing is blocked', !rmt_blocked_from(11, 'review', 1));

q_run('INSERT INTO blocks (blocker_id, blocked_id) VALUES (?,?)', [10, 11]);
// Symmetric: it does not matter which way round the block was made.
foreach (['review', 'trip', 'guide', 'blog_post', 'collection', 'meetup'] as $tt) {
    ok("a blocked member cannot add an interaction to a {$tt}", rmt_blocked_from(11, $tt, 1));
}
ok('somebody uninvolved is unaffected', !rmt_blocked_from(12, 'review', 1));
ok('the owner is never blocked from their own content', !rmt_blocked_from(10, 'review', 1));

q_run('DELETE FROM blocks WHERE blocker_id=? AND blocked_id=?', [10, 11]);
q_run('INSERT INTO blocks (blocker_id, blocked_id) VALUES (?,?)', [11, 10]);
ok('a block made the other way round stops it just the same', rmt_blocked_from(11, 'review', 1));
ok('and still does for a meetup', rmt_blocked_from(11, 'meetup', 1));

// Content with no owner (a deleted row, an unknown type) must not be treated as blocked -- that
// would turn a missing row into a confusing refusal instead of the ordinary no-op.
ok('a row with no owner is not "blocked"', !rmt_blocked_from(11, 'review', 999));
ok('an unknown type is not "blocked"', !rmt_blocked_from(11, 'spaceship', 1));

// --- Every add path is gated, and no removal path is ------------------------------------------
$body = static function (string $name) use ($src): string {
    $start = strpos($src, "function {$name}(");
    if ($start === false) return '';
    $end = strpos($src, "\nfunction ", $start + 1);
    return substr($src, $start, ($end ?: strlen($src)) - $start);
};
foreach (['react_action', 'comment_action', 'review_vote_action', 'meetup_rsvp'] as $fn) {
    ok("{$fn}() checks the block", strpos($body($fn), 'rmt_blocked_from') !== false);
}
// The gate must sit after the "already has one" branch, so unliking and withdrawing stay open.
// Being stuck holding an interaction you cannot take back is the wrong way for this to fail.
$react = $body('react_action');
ok('react_action(): the block is checked only on the add branch',
   strpos($react, 'rmt_blocked_from') > strpos($react, 'DELETE FROM $tbl'));
$rsvp = $body('meetup_rsvp');
ok('meetup_rsvp(): the block is checked only on the join branch',
   strpos($rsvp, 'rmt_blocked_from') > strpos($rsvp, 'DELETE FROM meetup_rsvps'));
$vote = $body('review_vote_action');
ok('review_vote_action(): the block is checked before any row is written',
   strpos($vote, 'rmt_blocked_from') < strpos($vote, 'INSERT INTO review_votes'));

// The old hand-rolled owner lookup is gone, so meetup comments cannot regress to a 500.
ok('nothing reads an owner with a hand-written user_id query any more',
   !preg_match("/SELECT user_id FROM ' \\. RMT_INTERACT_TARGETS/", $src));

echo $fails ? "\n$fails FAILED\n" : "\nAll block interaction tests passed.\n";
exit($fails ? 1 : 0);
