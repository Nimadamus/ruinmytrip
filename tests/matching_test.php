<?php
/**
 * Trip matching: overlap arithmetic, visibility, blocks, notification dedupe, wishlist tier.
 *
 *   php tests/matching_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/going.php';
require BASE_PATH . '/app/matching.php';

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT, home_city TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec('CREATE TABLE follows (follower_id INT, followee_id INT, PRIMARY KEY (follower_id, followee_id))');
$pdo->exec('CREATE TABLE blocks (blocker_id INT, blocked_id INT, PRIMARY KEY (blocker_id, blocked_id))');
$pdo->exec("CREATE TABLE saves (user_id INT, target_type TEXT, target_id INT, PRIMARY KEY (user_id,target_type,target_id))");
$pdo->exec("CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, type TEXT NOT NULL,
    actor_id INT, target_type TEXT, target_id INT, read_at TEXT, created_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE going (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, destination_id INT NOT NULL,
    date_from TEXT, date_to TEXT, visibility TEXT NOT NULL DEFAULT 'public', created_at TEXT NOT NULL)");
$pdo->exec('CREATE UNIQUE INDEX idx_going_user_dest ON going (user_id, destination_id)');

$pdo->exec("INSERT INTO users (id,username,status) VALUES
    (1,'alice','active'),(2,'bob','active'),(3,'cara','active'),(4,'dan','active'),(5,'gone','deleted')");
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES
    (10,'lisbon-portugal','Lisbon'),(11,'osaka-japan','Osaka'),(12,'quito-ecuador','Quito')");

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-64s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$Y = (int) gmdate('Y') + 2;                       // always in the future, whatever year it is run
$d = static fn(string $md): string => $Y . '-' . $md;

echo "-- overlap arithmetic --\n";
check('same day counts as one',      rmt_match_overlap_days('2099-06-08','2099-06-12','2099-06-12','2099-06-20'), 1);
check('no touch is zero',            rmt_match_overlap_days('2099-06-01','2099-06-05','2099-06-06','2099-06-09'), 0);
check('contained range',             rmt_match_overlap_days('2099-06-01','2099-06-30','2099-06-10','2099-06-14'), 5);
check('identical ranges',            rmt_match_overlap_days('2099-06-01','2099-06-03','2099-06-01','2099-06-03'), 3);
check('empty dates are not a match', rmt_match_overlap_days('', '', '2099-06-01','2099-06-03'), 0);
$w = rmt_match_overlap_window('2099-06-01','2099-06-10','2099-06-08','2099-06-20');
check('window is the intersection', ($w['from'] ?? '').'..'.($w['to'] ?? ''), '2099-06-08..2099-06-10');
check('window null when apart', rmt_match_overlap_window('2099-06-01','2099-06-02','2099-07-01','2099-07-02'), null);

echo "\n-- matches --\n";
rmt_going_upsert(1, ['destination_id'=>10,'date_from'=>$d('06-01'),'date_to'=>$d('06-10'),'visibility'=>'public']);
rmt_going_upsert(2, ['destination_id'=>10,'date_from'=>$d('06-08'),'date_to'=>$d('06-20'),'visibility'=>'public']);
rmt_going_upsert(3, ['destination_id'=>10,'date_from'=>$d('09-01'),'date_to'=>$d('09-05'),'visibility'=>'public']);
rmt_going_upsert(4, ['destination_id'=>11,'date_from'=>$d('06-01'),'date_to'=>$d('06-10'),'visibility'=>'public']);
$m = rmt_trip_matches(1);
check('only the overlapping traveler', count($m), 1);
check('it is bob', (string) ($m[0]['username'] ?? ''), 'bob');
check('overlap days computed', (int) ($m[0]['overlap_days'] ?? 0), 3);
check('overlap window', ($m[0]['overlap_from'] ?? '').'..'.($m[0]['overlap_to'] ?? ''), $d('06-08').'..'.$d('06-10'));
check('match is mutual', (string) (rmt_trip_matches(2)[0]['username'] ?? ''), 'alice');
check('a different city is not a match', rmt_trip_matches(4), []);
check('no plans means no matches', rmt_trip_matches(99), []);

echo "\n-- visibility --\n";
rmt_going_upsert(2, ['destination_id'=>10,'date_from'=>$d('06-08'),'date_to'=>$d('06-20'),'visibility'=>'private']);
check('private plan matches nobody', rmt_trip_matches(1), []);
rmt_going_upsert(2, ['destination_id'=>10,'date_from'=>$d('06-08'),'date_to'=>$d('06-20'),'visibility'=>'followers']);
check('followers plan hidden from non-follower', rmt_trip_matches(1), []);
$pdo->exec('INSERT INTO follows (follower_id,followee_id) VALUES (1,2)');
check('followers plan visible to follower', count(rmt_trip_matches(1)), 1);
rmt_going_upsert(2, ['destination_id'=>10,'date_from'=>$d('06-08'),'date_to'=>$d('06-20'),'visibility'=>'public']);

echo "\n-- blocks --\n";
$pdo->exec('INSERT INTO blocks (blocker_id,blocked_id) VALUES (1,2)');
check('blocked person is gone from my matches', rmt_trip_matches(1), []);
check('and I am gone from theirs', rmt_trip_matches(2), []);
$pdo->exec('DELETE FROM blocks');
check('unblocking restores the match', count(rmt_trip_matches(1)), 1);

echo "\n-- deleted accounts --\n";
rmt_going_upsert(5, ['destination_id'=>10,'date_from'=>$d('06-01'),'date_to'=>$d('06-10'),'visibility'=>'public']);
check('a deleted account never matches', count(rmt_trip_matches(1)), 1);

echo "\n-- notifications --\n";
$g = rmt_going_for_user_dest(2, 10);
check('public plan notifies the overlap',
      rmt_match_notify(2, (int) $g['id'], 10, (string) $g['date_from'], (string) $g['date_to'], 'public'), 1);
check('same plan never notifies twice',
      rmt_match_notify(2, (int) $g['id'], 10, (string) $g['date_from'], (string) $g['date_to'], 'public'), 0);
$n = q_one("SELECT * FROM notifications WHERE type='trip_match'");
check('addressed to the person already there', (int) $n['user_id'], 1);
check('points at the plan', (int) $n['target_id'] === (int) $g['id'] && $n['target_type'] === 'going', true);
$g3 = rmt_going_for_user_dest(3, 10);
check('a plan that overlaps nobody notifies nobody',
      rmt_match_notify(3, (int) $g3['id'], 10, (string) $g3['date_from'], (string) $g3['date_to'], 'public'), 0);
check('private plans stay quiet',
      rmt_match_notify(4, 999, 10, $d('06-01'), $d('06-10'), 'private'), 0);
check('followers plans stay quiet',
      rmt_match_notify(4, 998, 10, $d('06-01'), $d('06-10'), 'followers'), 0);

echo "\n-- wishlist tier --\n";
$pdo->exec("INSERT INTO saves (user_id,target_type,target_id) VALUES
    (1,'destination',10),(1,'destination',11),(1,'destination',12),
    (2,'destination',10),(2,'destination',11),
    (3,'destination',10),
    (4,'destination',10),(4,'destination',11),(4,'destination',12)");
$wl = rmt_wishlist_matches(1);
check('one shared city is not enough', count($wl), 2);
check('most in common comes first', (string) $wl[0]['username'], 'dan');
check('shared count is right', (int) $wl[0]['shared'], 3);
$sh = rmt_match_shared_destinations(1, array_column($wl, 'user_id'));
check('shared cities named for each person', count($sh[2] ?? []), 2);
check('and they are the right ones', implode(',', array_column($sh[2] ?? [], 'slug')), 'lisbon-portugal,osaka-japan');
$pdo->exec('INSERT INTO blocks (blocker_id,blocked_id) VALUES (4,1)');
check('a block hides the wishlist tier too', count(rmt_wishlist_matches(1)), 1);
$pdo->exec('DELETE FROM blocks');
check('no saves means no wishlist matches', rmt_wishlist_matches(99), []);

echo "\n-- meetups in the window --\n";
$pdo->exec("CREATE TABLE meetups (id INTEGER PRIMARY KEY AUTOINCREMENT, host_id INT, destination_id INT,
    title TEXT, date_start TEXT, status TEXT DEFAULT 'published', visibility TEXT DEFAULT 'public')");
$pdo->exec("CREATE TABLE meetup_rsvps (meetup_id INT, user_id INT, status TEXT)");
$pdo->prepare("INSERT INTO meetups (id,host_id,destination_id,title,date_start) VALUES
    (1,3,10,'Coffee in Lisbon',?),(2,3,10,'Way after they leave',?),(3,3,11,'Wrong city',?)")
    ->execute([$d('06-09') . ' 10:00:00', $d('12-01') . ' 10:00:00', $d('06-09') . ' 10:00:00']);
$pdo->exec("INSERT INTO meetup_rsvps (meetup_id,user_id,status) VALUES (1,3,'going')");
$win = rmt_meetups_in_window(10, $d('06-01'), $d('06-10'));
check('only the one inside the dates', count($win), 1);
check('and it is the right one', (string) $win[0]['title'], 'Coffee in Lisbon');
check('host count comes back', (int) $win[0]['going_count'], 1);
check('a city I am not in has none', rmt_meetups_in_window(12, $d('06-01'), $d('06-10')), []);
$pdo->exec("UPDATE meetups SET status='cancelled' WHERE id=1");
check('a cancelled meetup is not offered', rmt_meetups_in_window(10, $d('06-01'), $d('06-10')), []);
$pdo->exec("UPDATE meetups SET status='published' WHERE id=1");

echo "\n-- telling the people already in town --\n";
$sent = rmt_meetup_notify_travelers(1, 3, 10, $d('06-09') . ' 10:00:00');
check('travelers with dates covering it are told', $sent > 0, true);
check('and never twice', rmt_meetup_notify_travelers(1, 3, 10, $d('06-09') . ' 10:00:00'), 0);
$n = q_one("SELECT * FROM notifications WHERE type='meetup_nearby'");
check('points at the meetup', (int) $n['target_id'] === 1 && $n['target_type'] === 'meetup', true);
check('a date nobody is there for tells nobody',
      rmt_meetup_notify_travelers(2, 3, 10, $d('12-01') . ' 10:00:00'), 0);
$pdo->exec("DELETE FROM notifications WHERE type='meetup_nearby'");
$pdo->exec('INSERT INTO blocks (blocker_id,blocked_id) VALUES (1,3)');
$blocked = rmt_meetup_notify_travelers(1, 3, 10, $d('06-09') . ' 10:00:00');
check('a block keeps the host out of my notifications', $blocked === $sent - 1, true);
$pdo->exec('DELETE FROM blocks');

echo $fail ? "\nFAILED: $fail\n" : "\nOK\n";
exit($fail ? 1 : 0);
