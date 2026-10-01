<?php
/**
 * The acquisition loop (2026-10-01): signed out match alerts (app/match_alerts.php), "I'm going"
 * cards (app/going_cards.php), founding recognitions (app/recognitions.php), and the quality bar
 * that lets the three ecosystem guides be indexed before anybody is on them (app/occasions.php).
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:', 'security_salt' => 'test-salt',
];
const RMT_EDITORIAL_ROLE = 'editorial';
putenv('RESEND_API_KEY');
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/mail.php';
require BASE_PATH . '/app/growth_scorecard.php';
require BASE_PATH . '/app/occasions.php';
require BASE_PATH . '/app/match_alerts.php';
require BASE_PATH . '/app/going_cards.php';
require BASE_PATH . '/app/recognitions.php';
if (session_status() !== PHP_SESSION_ACTIVE) { $_SESSION = []; }

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-56s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, status TEXT DEFAULT 'active', role TEXT DEFAULT 'user', email_verified_at TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, hero_url TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, status TEXT, visibility TEXT, date_from TEXT, date_to TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE buddy_posts (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, status TEXT, date_from TEXT, date_to TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, status TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INT, status TEXT, created_at TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/105_match_alerts.sqlite.sql'));
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'chiang-mai-thailand','Chiang Mai','Thailand'),(2,'lisbon-portugal','Lisbon','Portugal')");
$old = date('Y-m-d H:i:s', time() - 72 * 3600);
$pdo->exec("INSERT INTO users (id,username,email,email_verified_at) VALUES (1,'ana','ana@example.com','$old'),(2,'team_x','team@example.com','$old'),
            (3,'ben','ben@example.com','$old'),(4,'cat','cat@example.com',NULL)");

echo "\nValidation:\n";
$today = '2026-10-01';
$good = ['email' => ' Me@Example.com ', 'destination_id' => 1, 'occasion' => 'yi-peng-chiang-mai', 'date_from' => '2026-11-22', 'date_to' => '2026-11-27', 'flex_days' => 3];
$v = rmt_alert_validate($good, $today);
ok('a good alert passes', $v['ok'], true);
ok('the address is trimmed and lowercased', $v['data']['email'], 'me@example.com');
ok('an occasion in this city is kept', $v['data']['occasion'], 'yi-peng-chiang-mai');
ok('an occasion in another city is dropped', rmt_alert_validate(['occasion' => 'web-summit-lisbon'] + $good, $today)['data']['occasion'], '');
ok('a bad address is refused', rmt_alert_validate(['email' => 'nope'] + $good, $today)['ok'], false);
ok('past dates are refused', rmt_alert_validate(['date_from' => '2026-09-01', 'date_to' => '2026-09-05'] + $good, $today)['ok'], false);
ok('leaving before arriving is refused', rmt_alert_validate(['date_from' => '2026-11-27', 'date_to' => '2026-11-22'] + $good, $today)['ok'], false);
ok('a window over six months is refused', rmt_alert_validate(['date_to' => '2027-07-01'] + $good, $today)['ok'], false);
ok('an impossible date is refused', rmt_alert_validate(['date_to' => '2026-11-31'] + $good, $today)['ok'], false);
ok('an unknown flexibility becomes exact', rmt_alert_validate(['flex_days' => 99] + $good, $today)['data']['flex_days'], 0);
ok('an unknown city is refused', rmt_alert_validate(['destination_id' => 99] + $good, $today)['ok'], false);

echo "\nStoring and the off switch:\n";
$s1 = rmt_alert_save($v['data']);
$a1 = rmt_alert_by_id($s1['id']);
ok('stored as pending', $a1['status'], 'pending');
ok('only the hash of the token is stored', $a1['token_hash'] === hash('sha256', $s1['token']) && !str_contains(json_encode($a1), $s1['token']), true);
ok('the token finds it', (int) rmt_alert_by_token($s1['token'])['id'], $s1['id']);
$s2 = rmt_alert_save(['date_to' => '2026-11-28'] + $v['data']);
ok('the same address and city is one alert', $s2['id'], $s1['id']);
ok('the old link stops working', rmt_alert_by_token($s1['token']), null);
ok('the dates were updated', rmt_alert_by_id($s1['id'])['date_to'], '2026-11-28');
ok('a malformed token finds nothing', rmt_alert_by_token("x' OR 1=1"), null);
ok('flexibility widens the window', rmt_alert_window(['date_from' => '2026-11-22', 'date_to' => '2026-11-28', 'flex_days' => 3]), ['2026-11-19', '2026-12-01']);
ok('the off signature checks out', hash_equals(rmt_alert_off_sig($s1['id'], 'me@example.com'), rmt_alert_off_sig($s1['id'], 'me@example.com')), true);
ok('another address does not', rmt_alert_off_sig($s1['id'], 'me@example.com') === rmt_alert_off_sig($s1['id'], 'you@example.com'), false);
ok('the label names the occasion and dates', rmt_alert_label(rmt_alert_by_id($s1['id'])), 'Chiang Mai for Yi Peng, 22 to 28 November 2026');

echo "\nOverlaps:\n";
$a = rmt_alert_by_id($s1['id']);
ok('nobody yet', rmt_alert_overlaps($a)['total'], 0);
$pdo->exec("INSERT INTO trips VALUES (1,2,1,'published','public','2026-11-20','2026-11-25','$old')");
ok('a house account does not count', rmt_alert_overlaps($a)['total'], 0);
$pdo->exec("INSERT INTO trips VALUES (2,1,1,'published','private','2026-11-20','2026-11-25','$old')");
ok('a private trip does not count', rmt_alert_overlaps($a)['total'], 0);
$pdo->exec("INSERT INTO trips VALUES (3,1,1,'published','public','2026-11-30','2026-12-03','$old')");
ok('a trip inside the flexible days counts', rmt_alert_overlaps($a)['travelers'], 1);
$pdo->exec("INSERT INTO users (id,username,email,email_verified_at) VALUES (5,'me','me@example.com','$old')");
$pdo->exec("INSERT INTO trips VALUES (4,5,1,'published','public','2026-11-22','2026-11-28','$old')");
ok('the alert holder\'s own trip does not count', rmt_alert_overlaps($a)['travelers'], 1);
$s3 = rmt_alert_save(['email' => 'zoe@example.com', 'destination_id' => 1, 'occasion' => '', 'date_from' => '2026-11-26', 'date_to' => '2026-11-30', 'flex_days' => 0]);
ok('an unconfirmed alert is nobody', count(rmt_alert_crossing($a)), 0);
$pdo->exec("UPDATE match_alerts SET status = 'active' WHERE id = " . (int) $s3['id']);
ok('a confirmed alert on crossing days is somebody', count(rmt_alert_crossing($a)), 1);
ok('...and counts once', rmt_alert_overlaps($a)['total'], 2);
ok('a pending alert is never emailed', rmt_alert_mail_overlap($a, 'x'), false);
ok('mail disabled: nothing is marked sent', (int) rmt_alert_by_id($s3['id'])['notify_count'], 0);
ok('a private trip alerts nobody', rmt_alerts_on_trip(1, 1, '2026-11-26', '2026-11-30', 'private'), 0);

echo "\nI'm going cards:\n";
$code = rmt_going_card_make(1, null, 1, 'yi-peng-chiang-mai', '2026-11-22', '2026-11-27', false);
ok('a code is made', (bool) preg_match('/^[a-z0-9]{10}$/', $code), true);
ok('the same card twice is one link', rmt_going_card_make(1, null, 1, 'yi-peng-chiang-mai', '2026-11-22', '2026-11-27', false), $code);
$c = rmt_going_card($code);
ok('no name unless ticked', $c['who'], '');
ok('the line says where, why and when', rmt_going_line($c), "I'm going to Chiang Mai for Yi Peng, 22 to 27 November 2026.");
$named = rmt_going_card(rmt_going_card_make(1, null, 1, '', '2026-11-22', '2026-11-27', true));
ok('the name when ticked', $named['who'], '@ana');
$links = rmt_going_share_links($c);
ok('whatsapp carries the line and link', str_starts_with($links['whatsapp'], 'https://wa.me/?text=') && str_contains($links['whatsapp'], rawurlencode($code)), true);
ok('every channel is offered', array_keys($links), ['whatsapp', 'facebook', 'x', 'telegram', 'email', 'copy']);
$pdo->exec("UPDATE users SET status = 'suspended' WHERE id = 3");
$benCode = rmt_going_card_make(3, null, 2, '', '2026-11-09', '2026-11-12', true);
ok('a suspended member\'s card is gone', rmt_going_card($benCode), null);
$pdo->exec("UPDATE users SET status = 'active' WHERE id = 3");
ok('a code with odd characters is nothing', rmt_going_card('../etc'), null);
ok('the occasion is found from a trip window', rmt_going_occasion_for('lisbon-portugal', '2026-11-10', '2026-11-15'), 'web-summit-lisbon');
ok('...and not outside it', rmt_going_occasion_for('lisbon-portugal', '2026-12-10', '2026-12-15'), '');

echo "\nRecognitions:\n";
$fresh = date('Y-m-d H:i:s', time() - 3600);
$pdo->exec("INSERT INTO trips VALUES (10,3,2,'published','public','2026-11-09','2026-11-12','$fresh')");
$pdo->exec("INSERT INTO trips VALUES (11,4,2,'published','public','2026-11-09','2026-11-12','$old')");
rmt_recognitions_sweep();
$kinds = static fn(int $uid): array => array_column(q_all('SELECT kind FROM recognitions WHERE user_id = ? ORDER BY kind', [$uid]), 'kind');
ok('first traveler for the first real member', $kinds(1), ['first_traveler']);
ok('a house account gets nothing', $kinds(2), []);
ok('under 48 hours gets nothing yet', $kinds(3), []);
ok('an unconfirmed email gets nothing', $kinds(4), []);
$pdo->exec("UPDATE trips SET created_at = '$old' WHERE id = 10");
rmt_recognitions_sweep();
rmt_recognitions_sweep();
ok('once old enough, Lisbon goes to its first traveler', $kinds(3), ['first_traveler']);
ok('a second sweep adds nothing', (int) q_one('SELECT COUNT(*) c FROM recognitions')['c'], 2);
ok('one first per city', (int) q_one("SELECT COUNT(*) c FROM recognitions WHERE kind='first_traveler' AND destination_id=1")['c'], 1);
ok('the label', rmt_recognitions_for(1), ['First traveler to Chiang Mai']);
$pdo->exec("UPDATE trips SET status = 'removed' WHERE id = 10");
rmt_recognitions_sweep();
ok('moderation takes a first back', rmt_recognitions_for(3), []);

echo "\nThe ecosystem guides:\n";
foreach (['yi-peng-chiang-mai', 'day-of-the-dead-oaxaca', 'web-summit-lisbon'] as $slug) {
    $q = rmt_occasion_quality(RMT_OCCASIONS[$slug] + ['to' => '2099-01-01']);
    ok("$slug clears the bar ({$q['words']} words)", $q['ok'], true);
    $o = RMT_OCCASIONS[$slug];
    $txt = $o['name'] . $o['lede'] . ($o['when'] ?? '') . implode('', $o['facts']) . implode('', $o['warnings']);
    foreach ($o['guide'] as [$h, $ps]) $txt .= $h . implode('', $ps);
    foreach ($o['faq'] as [$fq, $fa]) $txt .= $fq . $fa;
    ok("$slug has no dashes", (bool) preg_match('/\s-\s|[\x{2013}\x{2014}]/u', $txt), false);
    ok("$slug slug carries no year", (bool) preg_match('/\d{4}/', $slug), false);
}
ok('a page with only a lede does not', rmt_occasion_quality(RMT_OCCASIONS['new-year-bangkok'])['ok'], false);
foreach (RMT_OCCASION_OLD_SLUGS as $from => $to) ok("$from moves to a live slug", isset(RMT_OCCASIONS[$to]), true);

printf("\n%s: %d passed, %d failed\n", basename(__FILE__, '.php'), $pass, $fail);
exit($fail ? 1 : 0);
