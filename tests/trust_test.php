<?php
/**
 * Trust signals: facts, never a score, never about the person.
 *
 * Strangers arrange to meet through this site now, so the page where they decide shows what is
 * publicly true about an account. The rules this pins down are mostly rules about restraint:
 *
 *   - every line is a count of rows the viewer could go and count by hand
 *   - a private trip is real and is nobody else's evidence of anything
 *   - "email not confirmed" is never a badge, because it is a scarlet letter on somebody who has
 *     not clicked a link yet
 *   - a brand new account with nothing on it draws no box at all, rather than a box saying it is
 *     new, which reads as an accusation
 *   - nothing is returned for an account that is gone
 *
 *   php tests/trust_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/trust.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, created_at TEXT,
              email_verified_at TEXT, status TEXT DEFAULT 'active')");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, status TEXT DEFAULT 'published',
              visibility TEXT DEFAULT 'public')");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, status TEXT DEFAULT 'published')");
$pdo->exec("CREATE TABLE trip_activities (id INTEGER PRIMARY KEY, user_id INT, status TEXT DEFAULT 'published')");
$pdo->exec("CREATE TABLE activity_joins (activity_id INT, user_id INT, state TEXT)");
$pdo->exec("CREATE TABLE follows (followee_id INT, follower_id INT)");

$old = date('Y-m-d H:i:s', strtotime('-900 days'));
$new = date('Y-m-d H:i:s', strtotime('-2 days'));
$pdo->exec("INSERT INTO users (id,username,created_at,email_verified_at) VALUES
  (1,'ana','$old','$old'), (2,'ben','$new',NULL), (3,'cara','$old',NULL), (4,'gone','$old','$old')");
$pdo->exec("UPDATE users SET status='deleted' WHERE id=4");

$pdo->exec("INSERT INTO trips (id,user_id,visibility) VALUES (1,1,'public'),(2,1,'public'),
  (3,1,'private'),(4,1,'followers')");
$pdo->exec("INSERT INTO reviews (id,user_id) VALUES (1,1)");
$pdo->exec("INSERT INTO trip_activities (id,user_id) VALUES (1,1)");
$pdo->exec("INSERT INTO activity_joins VALUES (1,2,'going'),(1,3,'going'),(1,1,'going'),(1,9,'requested')");

$keys = static fn(array $sigs): array => array_column($sigs, 'key');
$labelOf = static function (array $sigs, string $key): string {
    foreach ($sigs as $s) if ($s['key'] === $key) return (string) $s['label'];
    return '';
};

$ana = rmt_trust_signals(1);
ok(in_array('age', $keys($ana), true), 'how long the account has been here is a signal');
ok(str_contains($labelOf($ana, 'age'), 'years'), 'and an old one is said in years');
ok($labelOf($ana, 'trips') === '2 trips posted', 'only public trips count, because only those can be checked');
ok($labelOf($ana, 'reviews') === '1 review written', 'one review is one review, said in the singular');
ok($labelOf($ana, 'hosted') === '2 travelers have joined their plans',
   'people who turned up are counted, and never the host themselves');
ok(!str_contains(implode('|', $keys($ana)), 'requested'), 'somebody who only asked has not turned up');

$ben = rmt_trust_signals(2);
ok(!in_array('email', $keys($ben), true), 'an unconfirmed address produces no line at all');
ok(str_contains($labelOf($ben, 'age'), 'week'), 'a new account says it is new, plainly');
ok(!rmt_trust_worth_showing($ben), 'and one line on its own is not worth a box');
ok(rmt_trust_worth_showing($ana), 'an account with a history is');

// Mutual connections: about the two people, not about one of them.
$pdo->exec("INSERT INTO follows VALUES (7,1),(7,3),(8,1),(8,3),(9,1)");
$mutual = rmt_trust_signals(3, ['id' => 1]);
ok($labelOf($mutual, 'mutual') === '2 travelers you both follow', 'people you both follow are counted');
ok(!in_array('mutual', $keys(rmt_trust_signals(3)), true), 'with nobody asking, there is no mutual line');
ok(!in_array('mutual', $keys(rmt_trust_signals(1, ['id' => 1])), true),
   'and never on your own account, where the question is meaningless');

ok(rmt_trust_signals(4) === [], 'an account that is gone says nothing');
ok(rmt_trust_signals(0) === [], 'and neither does no account at all');

/* The line this must never cross. Every signal is a count of public rows, so nothing here may be
   about a person rather than about their activity. This is checked as a list of forbidden keys
   rather than as a comment, so adding one fails the build. */
$forbidden = ['gender', 'age_years', 'attractive', 'rating', 'score', 'reliability', 'risk',
              'ethnicity', 'religion', 'politics', 'health', 'orientation'];
$leaked = array_intersect($forbidden, $keys(rmt_trust_signals(1, ['id' => 3])));
ok($leaked === [], 'no signal is a judgement about the person' . ($leaked ? ': ' . implode(', ', $leaked) : ''));

echo "trust_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
