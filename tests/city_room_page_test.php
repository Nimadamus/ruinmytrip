<?php
/**
 * The check-in card and the city room page (views/_checkin_card.php, views/city_room.php).
 *
 * The room is private to travelers checked in to one city. So the page must show a member the
 * roster, the lines, the composer and a way to report; and must show everybody else the way in and
 * NOT ONE WORD of what is being said. A moderator must be able to read a reported line.
 *
 *   php tests/city_room_page_test.php
 */
putenv('DATABASE_URL');
putenv('RESEND_API_KEY');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }

$pdo = db(); $pdo->beginTransaction();
$now = date('Y-m-d H:i:s');
$dests = q_all('SELECT * FROM destinations ORDER BY id LIMIT 2');
[$city, $other] = [$dests[0], $dests[1]];
$cid = (int) $city['id'];
foreach (['crana', 'crleo', 'crout'] as $u) {
    q_run("INSERT INTO users (username,email,password_hash,role,status,birthdate,email_verified_at,created_at) VALUES (?,?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', 'member', 'active', '1990-01-01', $now, $now]);
    $uid = (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];
    if (!q_one('SELECT 1 FROM profiles WHERE user_id=?', [$uid])) q_run('INSERT INTO profiles (user_id, display_name) VALUES (?,?)', [$uid, ucfirst($u)]);
}
$id = fn(string $u): int => (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];

ini_set('display_errors', '0');
$page = static function (int $uid, int $dest): string {
    $_SESSION['uid'] = $uid; $_GET = [];
    ob_start(); city_room(['id' => $dest]); return (string) ob_get_clean();
};
$card = static function (int $uid, ?array $ckDest, array $dests = []): string {
    $_SESSION['uid'] = $uid; $ckBack = '/buddies';
    ob_start(); include dirname(__DIR__) . '/views/_checkin_card.php'; return (string) ob_get_clean();
};

/* ---- before anybody checks in ---- */
$h = $page($id('crana'), $cid);
ok(str_contains($h, 'Nobody is checked in to') && str_contains($h, 'action="' . e(url('checkin')) . '"'), 'an empty room shows the way in');
ok(!str_contains($h, 'msg-composer'), 'and no composer');
ok(str_contains($h, 'noindex'), 'the room page is never indexed');
$c = $card($id('crana'), null, $dests);
ok(str_contains($c, 'In town right now?') && str_contains($c, 'name="destination_id"') && str_contains($c, 'name="hours"'), 'the card offers a city and a length');
ok(substr_count($c, '<option') === count($dests) + 1 + count(RMT_CHECKIN_CHOICES), 'with every length on offer');
$c = $card($id('crana'), $city);
ok(str_contains($c, 'In ' . e($city['name']) . ' right now?') && str_contains($c, 'type="hidden" name="destination_id" value="' . $cid . '"'), 'on a city page the card is for that city');

/* ---- two travelers in the room, one outside ---- */
rmt_checkin_start($id('crana'), $cid, 24);
rmt_checkin_start($id('crleo'), $cid, 3);
rmt_checkin_start($id('crout'), (int) $other['id'], 24);
rmt_city_room_post($id('crana'), $cid, 'Ramen at eight, who is in?');
$leoLine = rmt_city_room_post($id('crleo'), $cid, 'Leo here <b>bold</b> & hungry')['id'];

$h = $page($id('crana'), $cid);
ok(str_contains($h, 'Ramen at eight') && str_contains($h, 'Leo here'), 'a member reads the room');
ok(str_contains($h, '&lt;b&gt;bold&lt;/b&gt;') && !str_contains($h, '<b>bold</b>'), 'a line is escaped');
ok(str_contains($h, 'msg-composer') && str_contains($h, 'city-room/' . $cid . '/message'), 'and gets the composer');
ok(str_contains($h, 'data-city-room="' . e(url('city-room/' . $cid . '/feed')) . '"'), 'the page knows where to poll');
ok(str_contains($h, '@crleo') && str_contains($h, '@crana'), 'the roster shows who is here');
ok(str_contains($h, 'target_type=room_message&amp;target_id=' . $leoLine), 'somebody else\'s line can be reported');
ok(substr_count($h, 'target_type=room_message') === 1, 'your own cannot');
ok(str_contains($h, '>2</span> checked in'), 'the head counts the room');
ok(str_contains($h, 'Check out of'), 'and there is a way out');

$c = $card($id('crana'), null, $dests);
ok(str_contains($c, 'You are checked in to ' . e($city['name'])) && str_contains($c, 'city-room/' . $cid) && str_contains($c, 'checkin/end'),
   'checked in, the card links to the room and offers check out');

/* ---- the same reader, now checked in to another city. current_user() is cached for the life of
        a request, so this file changes what is true about one reader rather than switching readers. ---- */
rmt_checkin_start($id('crana'), (int) $other['id'], 24);
$h = $page($id('crana'), $cid);
ok(!str_contains($h, 'Leo here') && !str_contains($h, '@crleo') && !str_contains($h, 'msg-composer'), 'a traveler checked in elsewhere reads nothing and sees nobody');
ok(str_contains($h, '1 traveler is checked in'), 'but is told how many are there');
ok(str_contains($h, 'Move my check-in to ' . e($city['name'])), 'and can move their check-in here');

rmt_checkin_start($id('crana'), $cid, 24);
$h = $page($id('crana'), $cid);
ok(str_contains($h, 'Leo here') && !str_contains($h, 'Ramen at eight'), 'coming back opens the room again, without the lines from the earlier check-in');

/* ---- expiry reaches the page ---- */
$expire = static function (int $uid): void {
    q_run('UPDATE active_checkins SET created_at = ? WHERE user_id = ? AND ended_at IS NULL', [date('Y-m-d H:i:s', time() - 7200), $uid]);
    q_run('UPDATE active_checkins SET expires_at = ? WHERE user_id = ? AND ended_at IS NULL', [date('Y-m-d H:i:s', time() - 60), $uid]);
};
$expire($id('crleo'));
$h = $page($id('crana'), $cid);
ok(!str_contains($h, 'Leo here') && !str_contains($h, '@crleo'), 'an expired traveler is gone from the room, lines and all, with no sweep');
ok(str_contains($h, '>1</span> checked in'), 'the count follows');
$expire($id('crana'));
$h = $page($id('crana'), $cid);
ok(!str_contains($h, 'msg-composer') && str_contains($h, 'Nobody is checked in to'), 'and an expired reader is shown the way in, not the room');

/* ---- words ---- */
ok(rmt_checkin_left('2026-01-01 05:00:00', '2026-01-01 00:00:00') === '5 hours'
   && rmt_checkin_left('2026-01-01 01:00:00', '2026-01-01 00:00:00') === '60 minutes'
   && rmt_checkin_left('2026-01-01 00:40:00', '2026-01-01 00:00:00') === '40 minutes'
   && rmt_checkin_left('2026-01-04 00:00:00', '2026-01-01 00:00:00') === '3 days'
   && rmt_checkin_left('2026-01-02 00:00:00', '2026-01-01 00:00:00') === '24 hours'
   && rmt_checkin_left('2025-12-31 00:00:00', '2026-01-01 00:00:00') === 'a minute', 'time left is said in plain words');

/* ---- reports ---- */
ok((RMT_REPORT_TARGETS['room_message'] ?? '') === 'city_room_messages', 'a room line is a reportable thing');
$ctx = rmt_moderation_context('room_message', (int) $leoLine);
ok(str_contains((string) ($ctx['title'] ?? ''), '@crleo') && str_contains((string) ($ctx['excerpt'] ?? ''), 'Leo here'), 'a moderator can read a reported line');
ok(rmt_moderation_account_for('room_message', (int) $leoLine) === $id('crleo'), 'and reach the account that wrote it');

/* ---- one way to say "I am here" ---- */
$bi = (string) file_get_contents(dirname(__DIR__) . '/views/buddies_index.php');
ok(!str_contains($bi, 'Already there?') && !str_contains($bi, "url('buddies/here')"), 'the older "Already there?" card is gone from the buddies page');
$pub = rmt_here_now_publish($id('crana'), (int) $other['id'], date('Y-m-d', strtotime('+2 days')));
$plan = q_one('SELECT * FROM trips WHERE user_id = ? AND destination_id = ? ORDER BY id DESC LIMIT 1', [$id('crana'), (int) $other['id']]);
ok($pub['ok'] && $plan && (string) $plan['date_from'] === date('Y-m-d') && (string) $plan['date_to'] === date('Y-m-d', strtotime('+2 days'))
   && (int) $plan['open_to_meeting'] === 1 && (string) $plan['visibility'] === 'public', 'checking in lists the member as in town for travelers searching the city');
$src = (string) file_get_contents(dirname(__DIR__) . '/app/checkins.php');
$old = (string) file_get_contents(dirname(__DIR__) . '/app/buddies.php');
ok(str_contains($src, 'rmt_here_now_publish(') && preg_match('/function buddies_here_now.*?rmt_checkin_start\(/s', $old) === 1, 'the check-in does it, and the old form now checks in');

/* ---- wiring ---- */
$routes = (string) file_get_contents(dirname(__DIR__) . '/public/index.php');
ok(str_contains($routes, "'city_room'],") && function_exists('city_room'), 'the room page is routed');
ok(str_contains((string) file_get_contents(dirname(__DIR__) . '/views/buddies_index.php'), '_checkin_card.php')
   && str_contains((string) file_get_contents(dirname(__DIR__) . '/views/destination_travelers.php'), '_checkin_card.php'), 'the card is on the buddies page and on each city\'s travelers page');
ok(str_contains((string) file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js'), "getElementById('city-room-list')"), 'the polling script ships');
$js = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js');
ok(str_contains($js, 'if (!keep[row.id]) row.remove();') && str_contains($src, "'ids' =>"), 'an open room drops the lines of a traveler who has left');
ok(str_contains($js, 'm.report') && str_contains($src, "'report' =>"), 'a line that arrives live can be reported too');

$pdo->rollBack();
echo $fails ? "\ncity_room_page_test: $fails FAILED\n" : "\ncity_room_page_test: all passed\n";
exit($fails ? 1 : 0);
