<?php
/**
 * Contribution milestones: earned by counting real work, never by a stored number.
 *
 * A reputation system's failure mode is saying something that is not true — a "10 Reviews" badge
 * on somebody with three because a counter drifted, or a helpful total somebody voted up
 * themselves. Every rule here recounts from the rows, and these are the cases that prove it.
 *
 *   php tests/contribution_badges_test.php
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
require BASE_PATH . '/app/profiles.php';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-58s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT, invited_by INT)");
$pdo->exec("CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, avatar_url TEXT, bio TEXT, home_city TEXT, credibility_score INT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT, place_id INT, status TEXT, rating INT, created_at TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT, status TEXT)");
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, status TEXT)");
$pdo->exec("CREATE TABLE review_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, review_id INT)");
$pdo->exec("CREATE TABLE trip_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, trip_id INT)");
$pdo->exec("CREATE TABLE review_votes (id INTEGER PRIMARY KEY AUTOINCREMENT, review_id INT, user_id INT, vote_type TEXT)");
$pdo->exec("CREATE TABLE follows (follower_id INT, followee_id INT)");
$pdo->exec("CREATE TABLE compliments (id INTEGER PRIMARY KEY AUTOINCREMENT, to_user_id INT, from_user_id INT, kind TEXT)");
$pdo->exec("CREATE TABLE badges (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT, name TEXT, description TEXT, icon TEXT)");
$pdo->exec("CREATE TABLE user_badges (user_id INT, badge_id INT, awarded_at TEXT)");
// The two older badges come from an earlier migration this test does not load; the rule map
// covers them too, so the "every rule has a row" check needs them present.
$pdo->exec("INSERT INTO badges (slug,name,description,icon) VALUES
    ('founding-traveler','Founding Traveler','Early member who reviewed.','F'),
    ('elite-traveler','Elite Traveler','Sustained, useful contribution.','E')");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/053_contribution_badges.sql'));

$pdo->exec("INSERT INTO users (id,username,role,status) VALUES (1,'writer','user','active'),(2,'other','user','active')");

function add_reviews(int $uid, int $n, string $status = 'published'): void {
    for ($i = 0; $i < $n; $i++) {
        db()->prepare("INSERT INTO reviews (user_id,destination_id,place_id,status,rating,created_at)
                       VALUES (?,?,?,?,4,'2026-08-01')")->execute([$uid, 1, 1, $status]);
    }
}

echo "-- nothing is awarded for nothing --\n";
check('a new account has earned no milestone', rmt_award_badges(1), []);
check('...and holds none', (int) $pdo->query("SELECT COUNT(*) FROM user_badges")->fetchColumn(), 0);

echo "\n-- the first review --\n";
add_reviews(1, 1);
// This account is also early enough for the older Founding Traveler rule, so one review earns
// both. The point under test is that the milestone is among them and that a second pass adds
// nothing, not that it is the only badge in existence.
$first = rmt_award_badges(1);
check('one review earns the first milestone', in_array('first-review', $first, true), true);
check('awarding twice does not award twice', rmt_award_badges(1), []);
check('...and every award left exactly one row',
      (int) $pdo->query("SELECT COUNT(*) FROM user_badges WHERE user_id=1")->fetchColumn(), count($first));
check('five is not yet earned', rmt_qualifies_reviewer_5(1), false);

echo "\n-- thresholds are thresholds --\n";
add_reviews(1, 3);                                  // four total
check('four reviews is still not five', rmt_qualifies_reviewer_5(1), false);
add_reviews(1, 1);                                  // five
check('five is five', rmt_award_badges(1), ['reviewer-5']);
check('ten is not', rmt_qualifies_reviewer_10(1), false);
add_reviews(1, 5);
check('ten is ten', rmt_award_badges(1), ['reviewer-10']);
check('the count is real', rmt_user_review_count(1), 10);

echo "\n-- a milestone cannot stand on work that is gone --\n";
$pdo->exec("UPDATE reviews SET status='removed' WHERE user_id=1 AND id > 4");
check('removed reviews stop counting', rmt_user_review_count(1), 4);
check('...so the rule no longer qualifies', rmt_qualifies_reviewer_10(1), false);
check('...and nothing new is awarded on a recount', rmt_award_badges(1), []);
// The badge already granted is history and stays; what matters is that the RULE recounts, so a
// drifting stored total can never invent one that was never earned.
$pdo->exec("UPDATE reviews SET status='published' WHERE user_id=1");

echo "\n-- drafts and other people's work never count --\n";
add_reviews(2, 30);                                  // a different user
check('another account does not lift this one', rmt_user_review_count(1), 10);
add_reviews(1, 5, 'draft');
check('drafts do not count', rmt_user_review_count(1), 10);

echo "\n-- photographs --\n";
check('no photos, no badge', rmt_qualifies_photo_contributor(1), false);
for ($i = 1; $i <= 5; $i++) $pdo->exec("INSERT INTO review_photos (review_id) VALUES ($i)");
check('five photos earn it', rmt_qualifies_photo_contributor(1), true);
check('the count is real', rmt_user_photo_count(1), 5);

echo "\n-- helpful votes --\n";
check('none yet', rmt_user_helpful_count(1), 0);
for ($i = 1; $i <= 12; $i++) $pdo->exec("INSERT INTO review_votes (review_id, user_id, vote_type) VALUES (1, 2, 'useful')");
check('other travelers voting counts', rmt_user_helpful_count(1), 12);
check('...and earns the milestone', rmt_qualifies_helpful_reviewer(1), true);

$pdo->exec("DELETE FROM review_votes");
for ($i = 1; $i <= 20; $i++) $pdo->exec("INSERT INTO review_votes (review_id, user_id, vote_type) VALUES (1, 1, 'useful')");
check('a reputation you award yourself is not one', rmt_user_helpful_count(1), 0);
check('...and earns nothing', rmt_qualifies_helpful_reviewer(1), false);

$pdo->exec("DELETE FROM review_votes");
$pdo->exec("INSERT INTO review_votes (review_id, user_id, vote_type) VALUES (1, 2, 'funny')");
check('a funny vote is not a helpful one', rmt_user_helpful_count(1), 0);

echo "\n-- the rules live in one place --\n";
check('every rule has a badge row',
      array_values(array_filter(array_keys(RMT_BADGE_RULES), static fn($slug) =>
          !q_one('SELECT id FROM badges WHERE slug = ?', [$slug]))), []);
check('every rule is a function that exists',
      array_values(array_filter(RMT_BADGE_RULES, static fn($fn) => !function_exists($fn))), []);
check('the list is short on purpose', count(RMT_BADGE_RULES) <= 10, true);

echo "\n-- profile counts are real counts --\n";
$stats = rmt_profile_stats(1);
check('reviews',  $stats['reviews'], 10);
check('photos',   $stats['photos'], 5);
check('helpful',  $stats['helpful'], 0);
check('a profile with nothing reports nothing', rmt_profile_stats(999)['reviews'], 0);

/* ---------------------------------------------------- follower counts vs lists

   The count and the list are two answers to one question, and they were computed differently:
   the list required an active user, the count did not. A profile whose follower deactivated read
   "2 followers" above a list of one, and nothing on the page said which number to believe. */

$pdo->exec("INSERT INTO users (id,username,role,status) VALUES
    (81,'popular','user','active'), (82,'live_fan','user','active'), (83,'gone_fan','user','disabled')");
$pdo->exec("INSERT INTO follows (follower_id,followee_id) VALUES (82,81),(83,81)");

$st = rmt_profile_stats(81);
check('followers counts only active accounts', (int) $st['followers'], 1);

// And the other direction: a user who followed somebody since deactivated.
$pdo->exec("INSERT INTO follows (follower_id,followee_id) VALUES (81,82),(81,83)");
check('following counts only active accounts', (int) rmt_profile_stats(81)['following'], 1);

/* ------------------------------------------- editorial does not earn traveler badges

   rmt_user_review_count() counts published reviews, and 185 of them belong to the editorial
   account. Without this the staff account qualifies for First Review, 5, 10, 25 and Helpful
   Reviewer -- five statements that somebody went places and wrote about them, on an account whose
   own policy says it never claims to have gone. */

$pdo->exec("INSERT INTO users (id,username,role,status) VALUES (91,'the_editorial','" . RMT_EDITORIAL_ROLE . "','active')");
for ($i = 0; $i < 30; $i++) {
    q_run("INSERT INTO reviews (user_id,destination_id,place_id,rating,status,created_at)
           VALUES (91,1,1,5,'published','2026-08-01')");
}
check('the editorial account has plenty of published reviews', rmt_user_review_count(91) >= 25, true);
check('and would qualify on the raw rule', rmt_qualifies_reviewer_25(91), true);
// ...and is still awarded nothing, because badges are traveler reputation.
check('but it is awarded no badges at all', rmt_award_badges(91), []);
check('and holds none', count(rmt_user_badges(91)), 0);
// A badge granted before the rule existed must stop SHOWING, not merely stop being granted: the
// editorial account was awarded Founding Traveler long before this and it was still on the page.
$b = q_one("SELECT id FROM badges LIMIT 1");
if ($b) {
    q_run("INSERT INTO user_badges (user_id, badge_id, awarded_at) VALUES (?,?,?)",
          [91, (int) $b['id'], '2026-01-01 00:00:00']);
    check('a badge granted historically is still stored',
          (int) q_one("SELECT COUNT(*) c FROM user_badges WHERE user_id = 91")['c'], 1);
    check('but is not shown on an editorial profile', count(rmt_user_badges(91)), 0);
}

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
