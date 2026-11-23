<?php
/**
 * Traveler stories (app/traveler_stories.php, migration 134).
 *
 * What must hold: only a queued trip_story tied to a city page can be published; the editor's text
 * is what shows, never the raw message; markup is stripped; it publishes once; publishing closes
 * the queue item; a taken down story stops showing; a city shows its own stories and no others.
 *
 *   php tests/traveler_stories_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:', 'security_salt' => 'test-salt',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/tourist_tax.php';
require BASE_PATH . '/app/feedback.php';
require BASE_PATH . '/app/traveler_stories.php';

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; return; }
    $fail++;
    printf("  [FAIL] %-60s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
$pdo->exec("CREATE TABLE feedback (id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT, place_id INTEGER, message TEXT, contact_email TEXT,
             reported_by INTEGER, status TEXT DEFAULT 'pending', resolved_by INTEGER, resolved_at TEXT, resolution_note TEXT, created_at TEXT, topic TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/134_traveler_stories.sqlite.sql'));
$pdo->exec("INSERT INTO users (id) VALUES (1)");
$pdo->exec("INSERT INTO feedback (id, kind, message, topic, created_at) VALUES
  (1, 'trip_story', 'When: May 2026 | The taxi from the airport charged us 80 euros for a 30 euro ride.', 'warnings:amsterdam-netherlands', '2026-11-23'),
  (2, 'trip_story', 'No city on this one, it came from the home page.', NULL, '2026-11-23'),
  (3, 'wrong_info', 'The museum closes at 5.', 'warnings:amsterdam-netherlands', '2026-11-23'),
  (4, 'trip_story', 'The hotel added the city tax twice and refused to fix it at checkout.', 'tax:rome', '2026-11-23')");

ok('the When prefix is split off', rmt_story_split('When: May 2026 | Something happened here'), ['when' => 'May 2026', 'body' => 'Something happened here']);
ok('no prefix, all body', rmt_story_split('Just this'), ['when' => '', 'body' => 'Just this']);
ok('a story with no city cannot be published', rmt_story_publish(2, 1, 'No city on this one, it came from the home page.', '') !== '', true);
ok('a correction is not a story', rmt_story_publish(3, 1, 'The museum closes at 5 every day.', '') !== '', true);
ok('a missing row is refused', rmt_story_publish(99, 1, 'Something long enough to publish here.', '') !== '', true);
ok('too short is refused', rmt_story_publish(1, 1, 'Taxi bad', '') !== '', true);
ok('the editor version publishes', rmt_story_publish(1, 1, '<b>The airport taxi</b> charged 80 euros for a ride that should cost about 30.', 'May 2026'), '');
ok('publishing twice is refused', rmt_story_publish(1, 1, 'The airport taxi charged 80 euros for a ride that should cost about 30.', ''), 'Already published.');
$live = rmt_stories_for(['warnings:amsterdam-netherlands']);
ok('it shows on its city', count($live), 1);
ok('the editor text shows, markup stripped', (string) $live[0]['body'], 'The airport taxi charged 80 euros for a ride that should cost about 30.');
ok('the when shows', (string) $live[0]['when_text'], 'May 2026');
ok('the queue item is closed', (string) q_one('SELECT status FROM feedback WHERE id = 1')['status'], 'resolved');
ok('another city shows nothing from it', rmt_stories_for(['warnings:rome-italy', 'tax:rome']), []);
rmt_story_publish(4, 1, 'The hotel added the city tax twice and refused to fix it at checkout.', '');
ok('a tax story shows where its tax topic is asked for', count(rmt_stories_for(['warnings:rome-italy', 'tax:rome'])), 1);
ok('a bad topic is ignored, not queried', rmt_stories_for(["x' OR 1=1 --"]), []);
ok('Amsterdam pages share both topics', rmt_story_topics_for_dest('amsterdam-netherlands'), ['warnings:amsterdam-netherlands', 'tax:amsterdam']);
ok('take it down', rmt_story_take_down((int) $live[0]['id']), true);
ok('...and it stops showing', rmt_stories_for(['warnings:amsterdam-netherlands']), []);
ok('...but stays on record', (string) q_one('SELECT status FROM traveler_stories WHERE id = ?', [(int) $live[0]['id']])['status'], 'down');
ok('taking it down twice is refused', rmt_story_take_down((int) $live[0]['id']), false);

echo $fail ? "\n$fail FAIL(S), $pass pass\n" : "\nALL PASS ($pass)\n";
exit($fail ? 1 : 0);
