<?php
/**
 * A first review that was held for an unconfirmed email (rmt_reviews_release_held).
 *
 * The publish path already refuses to throw the work away: a review written minutes after signing
 * up is saved as a draft and the member is told to confirm their address. Nothing then released it.
 * They confirmed, landed on /welcome, and the review stayed a draft behind /reviews?mine=1 -- the
 * site kept the one contribution it had just asked a new member for, and the "reviews by distinct
 * travelers" number never moved.
 *
 * What must hold:
 *   - a review held for verification goes live when the address is confirmed.
 *   - a deliberate draft never does, no matter how new the account is.
 *   - somebody else's held review is not touched.
 *   - it cannot fire twice: the flag is cleared, so a second confirmation publishes nothing.
 *
 *   php tests/held_review_release_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/reviews.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$pdo = db();
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, slug TEXT,
              status TEXT NOT NULL DEFAULT 'draft', held_for_verification INTEGER NOT NULL DEFAULT 0,
              updated_at TEXT)");
$pdo->exec("INSERT INTO reviews (id,user_id,slug,status,held_for_verification) VALUES
              (1, 7, 'held-one',      'draft',     1),
              (2, 7, 'held-two',      'draft',     1),
              (3, 7, 'their-draft',   'draft',     0),
              (4, 7, 'already-live',  'published', 0),
              (5, 9, 'someone-else',  'draft',     1)");

$n = rmt_reviews_release_held(7);
ok('both held reviews go live', $n === 2, "released $n");

$status = static fn(int $id): string =>
    (string) (q_one('SELECT status FROM reviews WHERE id = ?', [$id])['status'] ?? '');
ok('the first held review is published', $status(1) === 'published');
ok('the second held review is published', $status(2) === 'published');
ok('a deliberate draft stays a draft', $status(3) === 'draft');
ok('a published review is left alone', $status(4) === 'published');
ok("another member's held review is untouched", $status(5) === 'draft');

$flag = (int) (q_one('SELECT held_for_verification f FROM reviews WHERE id = 1', [])['f'] ?? -1);
ok('the flag is cleared as it publishes', $flag === 0);
ok('confirming a second time publishes nothing', rmt_reviews_release_held(7) === 0);
ok('a bad user id does nothing', rmt_reviews_release_held(0) === 0);

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
