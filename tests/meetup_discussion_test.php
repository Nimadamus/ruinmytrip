<?php
/**
 * Regression tests for the meetup discussion (app/meetups.php, comment_action, meetup_show).
 *
 * Comments on a meetup were accepted by the interaction endpoints from the day meetups shipped and
 * the page never rendered them, so the discussion existed and nobody could read it. Rendering it is
 * only half: a meetup is a plan other people arranged their day around, so a new line on it is news
 * to everyone going, not only to the host.
 *
 * What must hold:
 *   - everybody going hears about a new comment, and nobody hears twice about one sentence:
 *     the author never, and the host never here (the ordinary comment notification reached them).
 *   - a host commenting on their own meetup still reaches the people going.
 *   - the notification type is one the notifications page and the push line can actually render;
 *     an unknown type is dropped rather than written as "meetup_whatever from @someone".
 *
 *   php tests/meetup_discussion_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/meetups.php';

$pdo = db();
$pdo->exec('CREATE TABLE meetup_rsvps (meetup_id INT NOT NULL, user_id INT NOT NULL,
              status TEXT NOT NULL DEFAULT \'going\', PRIMARY KEY (meetup_id, user_id))');
$pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL,
              type TEXT NOT NULL, actor_id INT, target_type TEXT, target_id INT, created_at TEXT NOT NULL)');

// Meetup 1: host 10, going 11, 12, 13 (13 is also the host of nothing; just another attendee).
foreach ([11, 12, 13] as $uid) {
    $pdo->exec("INSERT INTO meetup_rsvps (meetup_id,user_id,status) VALUES (1,$uid,'going')");
}
// One person who RSVPed and withdrew is stored as anything but 'going' nowhere -- rows are deleted --
// so a 'maybe' row stands in for any future status that is not a commitment.
$pdo->exec("INSERT INTO meetup_rsvps (meetup_id,user_id,status) VALUES (1,14,'maybe')");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}
function recipients_of(string $type, int $meetupId): array {
    $rows = q_all('SELECT user_id FROM notifications WHERE type=? AND target_id=? ORDER BY user_id', [$type, $meetupId]);
    return array_map(static fn(array $r) => (int) $r['user_id'], $rows);
}

// An attendee comments: the other attendees hear, the host does not (comment_action told them), the
// author does not, and somebody who is not going does not.
$r = rmt_meetup_discussion_recipients(1, 12, 10);
sort($r);
ok('attendee comment reaches the other attendees only', $r === [11, 13], 'got ' . json_encode($r));

// The host comments on their own meetup: everybody going hears, including nobody twice.
$r = rmt_meetup_discussion_recipients(1, 10, 10);
sort($r);
ok('host comment reaches everyone going', $r === [11, 12, 13], 'got ' . json_encode($r));

// A stranger comments: the whole going list hears, host excluded (already notified as owner).
$r = rmt_meetup_discussion_recipients(1, 99, 10);
sort($r);
ok('stranger comment reaches the going list without the host', $r === [11, 12, 13], 'got ' . json_encode($r));

// A meetup nobody has RSVPed to has nobody to tell.
ok('empty meetup notifies nobody', rmt_meetup_discussion_recipients(2, 12, 10) === []);

// The write side: rows land, one per recipient, and the actor is never one of them.
$sent = rmt_meetup_notify(rmt_meetup_discussion_recipients(1, 12, 10), 'meetup_comment', 12, 1);
ok('two notifications written', $sent === 2, "sent=$sent");
ok('rows are the right people', recipients_of('meetup_comment', 1) === [11, 13]);
ok('actor got nothing', !in_array(12, recipients_of('meetup_comment', 1), true));

// A type the notifications page cannot render is refused rather than written.
ok('unknown type writes nothing', rmt_meetup_notify([11], 'meetup_whatever', 12, 1) === 0);
ok('meetup_comment is a known type', in_array('meetup_comment', RMT_MEETUP_NOTIFY_TYPES, true));

// Both renderers know the type. Checked as source text because pulling the notifications view or
// push.php in needs the whole app; a type rendered in neither is the bug this guards.
$view = (string) file_get_contents(BASE_PATH . '/views/notifications.php');
$push = (string) file_get_contents(BASE_PATH . '/app/push.php');
ok('notifications page renders meetup_comment', str_contains($view, "'meetup_comment'"));
ok('push renders meetup_comment', str_contains($push, "'meetup_comment'") && str_contains($push, "case 'meetup_comment'"));

// The meetup page has to actually render the discussion, which is the half that was missing.
$page = (string) file_get_contents(BASE_PATH . '/views/meetup_show.php');
ok('meetup page includes the engagement partial', str_contains($page, "_engagement.php"));
ok('meetup page comments target the meetup', str_contains($page, "\$targetType = 'meetup'"));

// A saved meetup has somewhere to go from /saved.
ok('saved path for a meetup', rmt_saved_path('meetup', 7, '') === '/meetup/7');

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
