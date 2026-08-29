<?php
/**
 * Moderation: what a report does, what it must never do, and what happens to the numbers.
 *
 * Two failures are worse than any bug in here. One is a review site that quietly removes
 * criticism — the reviews it exists to publish. The other is a hidden review that keeps counting
 * toward a rating, so a place's score is built partly on content nobody can read.
 *
 *   php tests/moderation_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/editorial.php';
require BASE_PATH . '/app/places.php';
require BASE_PATH . '/app/place_data.php';
require BASE_PATH . '/app/reviews.php';
require BASE_PATH . '/app/review_aspects.php';
require BASE_PATH . '/app/destination_modules.php';
require BASE_PATH . '/app/profiles.php';

// The report/moderation vocabulary lives in controllers.php, which a unit test does not load.
const RMT_REPORT_TARGETS = ['review' => 'reviews', 'trip' => 'trips', 'comment' => 'comments', 'user' => 'users'];
require BASE_PATH . '/app/moderation.php';

function authors_fill(array &$rows, string $idField = 'user_id'): void {}

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-60s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT)");
$pdo->exec("CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, avatar_url TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, region TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT, slug TEXT UNIQUE,
            name TEXT, name_key TEXT, type TEXT, status TEXT, created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
            place_id INT, rating INT, title TEXT, body TEXT, slug TEXT, subject_name TEXT,
            safety_rating INT, value_rating INT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, status TEXT)");
$pdo->exec("CREATE TABLE comments (id INTEGER PRIMARY KEY, user_id INT, body TEXT, status TEXT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, review_id INT)");
$pdo->exec("CREATE TABLE trip_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, trip_id INT)");
$pdo->exec("CREATE TABLE review_votes (id INTEGER PRIMARY KEY AUTOINCREMENT, review_id INT, user_id INT, vote_type TEXT)");
$pdo->exec("CREATE TABLE follows (follower_id INT, followee_id INT)");
$pdo->exec("CREATE TABLE compliments (id INTEGER PRIMARY KEY, to_user_id INT, from_user_id INT, kind TEXT)");
$pdo->exec("CREATE TABLE badges (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT, name TEXT, description TEXT, icon TEXT)");
$pdo->exec("CREATE TABLE user_badges (user_id INT, badge_id INT, awarded_at TEXT)");
$pdo->exec("CREATE TABLE reports (id INTEGER PRIMARY KEY AUTOINCREMENT, reporter_id INT, target_type TEXT,
            target_id INT, reason TEXT, details TEXT, status TEXT, created_at TEXT, resolved_by INT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/047_place_attributes.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/049_review_aspects.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/054_moderation_log.sqlite.sql'));

$pdo->exec("INSERT INTO users (id,username,role,status) VALUES
    (1,'harsh','user','active'),(2,'spammer','user','active'),(3,'reader','user','active'),
    (4,'mod','mod','active'),(9,'ruinmytrip','" . RMT_EDITORIAL_ROLE . "','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'lisbon-portugal','Lisbon','Portugal')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,name_key,type,status,created_at)
            VALUES (1,1,'the-cafe','The Cafe','the cafe','restaurant','active','2026-08-01')");

/** A published review by $uid with $rating. */
function mk(int $id, int $uid, int $rating, string $title, string $body): void {
    db()->prepare("INSERT INTO reviews (id,user_id,destination_id,place_id,rating,title,body,slug,subject_name,status,created_at)
                   VALUES (?,?,1,1,?,?,?,?, 'The Cafe','published','2026-08-02')")
        ->execute([$id, $uid, $rating, $title, $body, 'r' . $id]);
}

mk(1, 1, 1, 'Terrible service', 'Waited an hour and would not return. The staff were rude and the bill was wrong.');
mk(2, 3, 5, 'Lovely morning',   'Good coffee, quick service, nothing to complain about at all.');
mk(3, 3, 4, 'Fine',             'Perfectly decent, would go again if I were nearby.');
mk(4, 2, 5, 'BEST DEALS CLICK', 'Visit my site for cheap tickets cheap tickets cheap tickets.');

echo "-- a report changes nothing --\n";
$pdo->exec("INSERT INTO reports (reporter_id,target_type,target_id,reason,status,created_at)
            VALUES (3,'review',1,'abuse','open','2026-08-03')");
check('the reported review is still published',
      (string) q_one('SELECT status FROM reviews WHERE id=1')['status'], 'published');
$pdo->exec("INSERT INTO reports (reporter_id,target_type,target_id,reason,status,created_at)
            VALUES (2,'review',1,'off_topic','open','2026-08-03')");
$pdo->exec("INSERT INTO reports (reporter_id,target_type,target_id,reason,status,created_at)
            VALUES (4,'review',1,'spam','open','2026-08-03')");
check('three reports still change nothing',
      (string) q_one('SELECT status FROM reviews WHERE id=1')['status'], 'published');
check('nothing was logged, because nothing was decided',
      (int) $pdo->query('SELECT COUNT(*) FROM moderation_log')->fetchColumn(), 0);

echo "\n-- the queue groups reports and carries the content --\n";
$queue = rmt_moderation_queue();
check('three reports about one review are one item', count($queue), 1);
check('...with the count shown', $queue[0]['reports'], 3);
check('...and every distinct reason', count($queue[0]['reasons']), 3);
check('the moderator sees the actual text',
      str_contains((string) $queue[0]['context']['excerpt'], 'Waited an hour'), true);
check('...and who wrote it', $queue[0]['context']['author'], 'harsh');
check('...and what it currently is', $queue[0]['context']['status'], 'published');

echo "\n-- criticism is not a violation --\n";
// The one-star review above is exactly the kind a business would report. Nothing in the moderation
// path may act on its rating, and dismissing leaves it exactly as it was.
$res = rmt_moderate(4, 'review', 1, 'dismiss', (int) $queue[0]['first_report_id'], 'Negative but legitimate.');
check('dismissing succeeds', $res['ok'], true);
check('the harsh review is untouched',
      (string) q_one('SELECT status FROM reviews WHERE id=1')['status'], 'published');
check('the dismissal is recorded',
      (string) q_one("SELECT action FROM moderation_log ORDER BY id DESC LIMIT 1")['action'], 'dismiss');
check('a dismissal moves no status',
      q_one("SELECT to_status FROM moderation_log ORDER BY id DESC LIMIT 1")['to_status'], null);
check('the reason is kept with it',
      str_contains((string) q_one("SELECT note FROM moderation_log ORDER BY id DESC LIMIT 1")['note'], 'legitimate'), true);
check('no rule anywhere reads a rating',
      str_contains(file_get_contents(BASE_PATH . '/app/moderation.php'), '$row[\'rating\'] >'), false);

echo "\n-- hiding the spam --\n";
$before = rmt_place_stats(1);
check('four reviews counted before', $before['c'], 4);
$res = rmt_moderate(4, 'review', 4, 'hide', null, 'Advertising.');
check('the action reports what it moved', [$res['from'], $res['to']], ['published', 'hidden']);
check('the review is hidden', (string) q_one('SELECT status FROM reviews WHERE id=4')['status'], 'hidden');

echo "\n-- the numbers follow --\n";
$after = rmt_place_stats(1);
check('the hidden review stops counting', $after['c'], 3);
check('...and stops moving the average', $after['a'] !== $before['a'], true);
check('it is gone from the reviews shown',
      in_array(4, array_map(static fn($r) => (int) $r['id'], rmt_place_reviews(1)), true), false);
check('it is gone from recent activity',
      in_array(4, array_map(static fn($r) => (int) $r['id'], rmt_destination_recent_reviews(1, 10)), true), false);
check('it stops counting toward the author contribution total', rmt_user_review_count(2), 0);
check('the rating breakdown drops it',
      array_sum(rmt_place_rating_breakdown(1)), 3);

echo "\n-- ranking eligibility follows too --\n";
$rank = rmt_destination_rankings(1);
check('the place has three qualifying reviews', $rank['qualified'], 1);
rmt_moderate(4, 'review', 3, 'hide', null, 'test');
check('down to two, it no longer qualifies as top', rmt_destination_rankings(1)['qualified'], 0);
check('...and is absent from every top row', rmt_destination_rankings(1)['top'], []);

echo "\n-- restoring puts it all back --\n";
$res = rmt_moderate(4, 'review', 3, 'restore', null, 'Wrong call.');
check('the restore reports the move', [$res['from'], $res['to']], ['hidden', 'published']);
check('the count comes back', rmt_place_stats(1)['c'], 3);
check('...and so does ranking eligibility', rmt_destination_rankings(1)['qualified'], 1);
check('both decisions are in the log',
      (int) $pdo->query("SELECT COUNT(*) FROM moderation_log WHERE target_id=3")->fetchColumn(), 2);

echo "\n-- helpful votes do not survive hiding --\n";
$pdo->exec("INSERT INTO review_votes (review_id,user_id,vote_type) VALUES (2,1,'useful'),(2,4,'useful')");
check('votes on a published review count', rmt_user_helpful_count(3), 2);
rmt_moderate(4, 'review', 2, 'hide', null, 'test');
check('votes on a hidden review do not', rmt_user_helpful_count(3), 0);
rmt_moderate(4, 'review', 2, 'restore', null, 'test');
check('and come back with it', rmt_user_helpful_count(3), 2);

echo "\n-- removal is not deletion --\n";
rmt_moderate(4, 'review', 4, 'remove', null, 'Persistent advertising.');
check('the row is still there', (int) $pdo->query('SELECT COUNT(*) FROM reviews WHERE id=4')->fetchColumn(), 1);
check('...marked removed', (string) q_one('SELECT status FROM reviews WHERE id=4')['status'], 'removed');
check('...and counted nowhere', rmt_place_stats(1)['c'], 3);
check('the history survives the decision',
      (int) $pdo->query("SELECT COUNT(*) FROM moderation_log WHERE target_id=4")->fetchColumn(), 2);

echo "\n-- what cannot be moderated this way --\n";
check('an unknown action is refused', rmt_moderate(4, 'review', 1, 'delete_forever')['ok'], false);
check('an unknown target type is refused', rmt_moderate(4, 'spaceship', 1, 'hide')['ok'], false);
check('content that does not exist is refused', rmt_moderate(4, 'review', 9999, 'hide')['ok'], false);
$pdo->exec("DELETE FROM moderation_log WHERE target_type='user'");
$r = rmt_moderate(4, 'user', 2, 'hide', null, 'not how accounts work');
check('an account is not hidden by pressing hide on a report',
      (string) q_one('SELECT status FROM users WHERE id=2')['status'], 'active');
check('...though the attempt is still recorded',
      (int) $pdo->query("SELECT COUNT(*) FROM moderation_log WHERE target_type='user'")->fetchColumn(), 1);

echo "\n-- the audit trail reads --\n";
$hist = rmt_moderation_history(50);
check('every decision is there', count($hist) >= 8, true);
check('newest first', $hist[0]['target_type'], 'user');
check('each one names who made it', (int) $hist[0]['actor_id'], 4);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
