<?php
/**
 * Vouches, flags, the visible trust score and the restricted state (migration 122,
 * app/trust_reviews.php).
 *
 * Three promises are tested here.
 *
 * 1. Only people who really crossed paths can review each other, once, inside the window: a buddy
 *    trip both were on that is over, or two check-ins to the same city that overlapped for an hour
 *    with both of them writing in the room.
 * 2. The numbers are plain arithmetic the member can check, and the member is shown them.
 * 3. Nothing happens to a member silently. Crossing the line pauses their messaging, and the same
 *    step writes the event, the notification, and a notice on every page. No shadowban: a
 *    restricted member's posts and profile are untouched. A moderator who sets the flags aside
 *    lifts it by the same arithmetic.
 *
 *   php tests/trust_reviews_test.php
 */
putenv('DATABASE_URL');
putenv('RESEND_API_KEY');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }

$pdo = db(); $pdo->beginTransaction();
$t0 = time();
$at = static fn(int $minutes): string => date('Y-m-d H:i:s', $t0 + $minutes * 60);
$now = $at(0);
$dest = q_one('SELECT * FROM destinations ORDER BY id LIMIT 1');
$did = (int) $dest['id'];
$names = ['tvsam', 'tvana', 'tvleo', 'tvkim', 'tvjun', 'tvout', 'tvmod'];
foreach ($names as $u) {
    q_run("INSERT INTO users (username,email,password_hash,role,status,birthdate,email_verified_at,created_at) VALUES (?,?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', $u === 'tvmod' ? 'mod' : 'member', 'active', '1990-01-01', $now, $now]);
    $uid = (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];
    if (!q_one('SELECT 1 FROM profiles WHERE user_id=?', [$uid])) q_run('INSERT INTO profiles (user_id, display_name) VALUES (?,?)', [$uid, ucfirst($u)]);
}
$id = fn(string $u): int => (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];
[$sam, $ana, $leo, $kim, $jun, $out, $mod] = array_map($id, $names);
$keys = static fn(int $uid, ?string $when = null): array => array_map(
    static fn(array $r): string => $r['subject_id'] . '|' . $r['context_key'], rmt_trust_reviewable($uid, $when));

/* ================= who may review whom ================= */
/* A buddy trip: sam posted, ana and leo were accepted, kim only asked, out was declined. */
q_run("INSERT INTO buddy_posts (user_id,trip_type,title,where_text,destination_id,date_from,date_to,spots,description,status,created_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)", [$sam, 'trip', 'Sam goes hiking', '', $did, date('Y-m-d', $t0 - 9 * 86400), date('Y-m-d', $t0 - 2 * 86400), 3, 'Come along.', 'closed', $at(-20000)]);
$post = (int) q_one('SELECT id FROM buddy_posts WHERE user_id=? ORDER BY id DESC LIMIT 1', [$sam])['id'];
foreach ([[$ana, 'accepted'], [$leo, 'accepted'], [$kim, 'interested'], [$out, 'declined']] as [$u, $st]) {
    q_run('INSERT INTO buddy_interest (post_id,user_id,state,created_at) VALUES (?,?,?,?)', [$post, $u, $st, $at(-20000)]);
}
ok($keys($ana) === [] && $keys($sam) === [], 'nobody can review while the trip is not marked over');
ok(rmt_buddy_complete_past($now) >= 1, 'the trip is stamped complete once its last day has passed');
$bk = 'bounty:' . $post;
ok($keys($sam) == ["$ana|$bk", "$leo|$bk"] || $keys($sam) == ["$leo|$bk", "$ana|$bk"], 'the poster can review everybody they accepted');
$ka = $keys($ana); sort($ka); $want = ["$sam|$bk", "$leo|$bk"]; sort($want);
ok($ka === $want, 'an accepted traveler can review the poster and the other accepted traveler');
ok($keys($kim) === [] && $keys($out) === [], 'somebody who only asked, or was declined, cannot review anybody');
ok(!rmt_trust_review_submit($kim, $sam, $bk, false)['ok'], 'and a forged flag from them is refused');
ok(!rmt_trust_review_submit($ana, $kim, $bk, true)['ok'], 'nobody can review somebody who was not on the trip');
ok(!rmt_trust_review_submit($ana, $ana, $bk, true)['ok'], 'nobody can vouch for themselves');
ok($keys($ana, $at((RMT_TRUST_REVIEW_DAYS + 1) * 1440)) === [], 'the window closes after ' . RMT_TRUST_REVIEW_DAYS . ' days');

/* Check-ins: ana and jun overlap two hours and both wrote; kim overlapped but never wrote; out
   overlapped ana for ten minutes only. */
$ck = static function (int $uid, int $fromMin, int $toMin, bool $wrote) use ($did, $at): int {
    q_run('INSERT INTO active_checkins (user_id,destination_id,created_at,expires_at,ended_at,end_reason) VALUES (?,?,?,?,?,?)',
          [$uid, $did, $at($fromMin), $at($toMin), $at($toMin), 'expired']);
    $cid = (int) q_one('SELECT MAX(id) id FROM active_checkins WHERE user_id=?', [$uid])['id'];
    if ($wrote) {
        $room = rmt_city_room_id($did, $at($fromMin));
        q_run('INSERT INTO city_room_messages (room_id,user_id,checkin_id,body,created_at) VALUES (?,?,?,?,?)', [$room, $uid, $cid, 'hello room', $at($fromMin + 1)]);
    }
    return $cid;
};
$cAna = $ck($ana, -600, -300, true);
$cJun = $ck($jun, -480, -360, true);
$cKim = $ck($kim, -600, -300, false);
$cOut = $ck($out, -310, -200, true);
$ckKey = 'checkin:' . min($cAna, $cJun) . ':' . max($cAna, $cJun);
ok(in_array("$jun|$ckKey", $keys($ana), true) && in_array("$ana|$ckKey", $keys($jun), true), 'two travelers who shared a room for two hours and both wrote can review each other');
ok(!in_array($kim, array_column(rmt_trust_reviewable($ana), 'subject_id'), true), 'somebody who never wrote in the room is not offered');
ok(!in_array($out, array_column(rmt_trust_reviewable($ana), 'subject_id'), true), 'an overlap of ten minutes is not a meeting');
$live = $ck($sam, -120, 600, true);
q_run('UPDATE active_checkins SET ended_at = NULL, end_reason = NULL WHERE id = ?', [$live]);
$live2 = $ck($leo, -120, -10, true);
ok(!in_array("$sam|checkin:" . min($live, $live2) . ':' . max($live, $live2), $keys($leo), true), 'a check-in that is still running opens no review');

/* ================= the arithmetic ================= */
$sum = static fn(int $uid): array => rmt_trust_summary($uid);
ok(rmt_trust_review_submit($ana, $sam, $bk, true, 'Great company')['ok'], 'a vouch is taken');
ok(!rmt_trust_review_submit($ana, $sam, $bk, false)['ok'], 'and cannot be given twice or changed into a flag');
ok(!in_array("$sam|$bk", $keys($ana), true), 'once answered it is no longer offered');
ok(rmt_trust_review_submit($leo, $sam, $bk, true)['ok'] && $sum($sam)['vouch_count'] === 2 && $sum($sam)['trust_score'] === 2 && $sum($sam)['trust_state'] === 'ok',
   'two travelers vouching is a score of 2');
ok(rmt_trust_review_submit($jun, $ana, $ckKey, false, 'Asked me for money')['ok'], 'a flag is taken');
$s = $sum($ana);
ok($s['flag_count'] === 1 && $s['vouch_count'] === 0 && $s['trust_score'] === -RMT_TRUST_FLAG_WEIGHT && $s['trust_state'] === 'ok',
   'one flag takes away ' . RMT_TRUST_FLAG_WEIGHT . ' and does not restrict anybody on its own');
ok((int) q_one('SELECT COUNT(*) n FROM trust_events WHERE user_id=?', [$ana])['n'] === 0 && (int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=? AND type LIKE 'trust_%'", [$ana])['n'] === 0,
   'no event and no notification while the state has not changed');

/* ================= crossing the line ================= */
$flag = static function (int $by, int $about) use ($at): void {
    q_run('INSERT INTO user_reviews (reviewer_id,subject_id,context_type,context_key,is_vouch,note,status,created_at) VALUES (?,?,?,?,?,?,?,?)',
          [$by, $about, 'bounty', 'bounty:test-' . $by, 0, 'felt unsafe', 'counted', $at(0)]);
    rmt_trust_recompute($about, $at(0));
};
$flag($sam, $ana);
ok($sum($ana)['trust_state'] === 'ok' && $sum($ana)['flag_count'] === 2, 'two travelers flagging is still not a restriction');
q_run('INSERT INTO user_reviews (reviewer_id,subject_id,context_type,context_key,is_vouch,status,created_at) VALUES (?,?,?,?,?,?,?)', [$sam, $ana, 'bounty', 'bounty:again', 0, 'counted', $now]);
rmt_trust_recompute($ana);
ok($sum($ana)['flag_count'] === 2, 'the same traveler flagging twice counts once');
ok(rmt_message_allowed($ana, $sam)['reason'] !== 'restricted', 'messaging is untouched below the line');
$flag($leo, $ana);
$s = $sum($ana);
ok($s['trust_state'] === 'restricted' && $s['flag_count'] === 3 && $s['trust_score'] === -6, RMT_TRUST_RESTRICT_FLAGS . ' travelers flagging with a score below zero restricts the account');
ok(rmt_trust_restricted($ana), 'and the account reads as restricted at once');
$ev = q_one('SELECT * FROM trust_events WHERE user_id=? ORDER BY id DESC LIMIT 1', [$ana]);
ok($ev && $ev['from_state'] === 'ok' && $ev['to_state'] === 'restricted' && $ev['reason'] === 'flag_threshold' && (int) $ev['flag_count'] === 3 && $ev['actor_id'] === null,
   'the change is written down with the numbers that caused it');
ok((int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=? AND type='trust_restricted'", [$ana])['n'] === 1, 'the member is notified the same moment');
rmt_trust_recompute($ana);
ok((int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=? AND type='trust_restricted'", [$ana])['n'] === 1
   && (int) q_one('SELECT COUNT(*) n FROM trust_events WHERE user_id=?', [$ana])['n'] === 1, 'counting again does not notify again');

/* A member with vouches is not restricted by the same three flags unless the score is below zero. */
foreach ([$ana, $leo, $kim, $jun, $out, $mod, $sam] as $i => $by) {
    if ($by === $sam) continue;
    q_run('INSERT INTO user_reviews (reviewer_id,subject_id,context_type,context_key,is_vouch,status,created_at) VALUES (?,?,?,?,?,?,?)',
          [$by, $sam, 'bounty', 'bounty:v' . $by, $i < 3 ? 0 : 1, 'counted', $now]);
}
rmt_trust_recompute($sam);
$s = $sum($sam);
ok($s['flag_count'] === 3 && $s['trust_score'] < 0 ? $s['trust_state'] === 'restricted' : $s['trust_state'] === 'ok', 'the rule is the stated one: flags at the threshold AND a score below zero');
q_run('DELETE FROM user_reviews WHERE subject_id = ? AND context_key LIKE ?', [$sam, 'bounty:v%']); rmt_trust_recompute($sam);
q_run("DELETE FROM notifications WHERE user_id = ? AND type LIKE 'trust_%'", [$sam]);

/* ================= what restricted means ================= */
ok(rmt_message_allowed($ana, $sam) === ['ok' => false, 'reason' => 'restricted'], 'a restricted member cannot send private messages, even to somebody who accepted them');
ok(rmt_message_allowed($sam, $ana)['ok'], 'other members can still write to them');
rmt_checkin_start($ana, $did, 3);
$r = rmt_city_room_post($ana, $did, 'hello?');
ok(!$r['ok'] && str_contains($r['error'], 'paused'), 'nor post in a city room, and is told why');
ok(rmt_checkin_live($ana) !== null && rmt_city_room_count($did) >= 1, 'but is still checked in, still counted, still visible: no shadowban');
ok((string) q_one('SELECT status FROM users WHERE id=?', [$ana])['status'] === 'active'
   && (int) q_one("SELECT COUNT(*) n FROM buddy_interest WHERE user_id=? AND state='accepted'", [$ana])['n'] === 1, 'the account and everything it posted are untouched');

/* ================= what the member is shown ================= */
ini_set('display_errors', '0');
$_SESSION['uid'] = $ana;
ob_start(); trust_page([]); $h = (string) ob_get_clean();
ok(str_contains($h, 'Your account is restricted') && str_contains($h, '3 travelers you met have flagged your account'), 'the trust page says the account is restricted and why');
ok(preg_match('/id="trust-score">-6</', $h) === 1 && preg_match('/id="trust-vouches">0</', $h) === 1 && preg_match('/id="trust-flags">3</', $h) === 1, 'it shows the score, the vouches and the flags');
ok(str_contains($h, '0 minus 2 times 3 is -6'), 'and the arithmetic, with their own numbers in it');
ok(str_contains($h, 'Travelers you met flagged your account') && str_contains($h, 'Messaging was paused'), 'and the dated history of the change');
ok(!str_contains($h, 'Asked me for money') && !str_contains($h, 'felt unsafe'), 'never what was written about them');
$card = substr($h, (int) strpos($h, 'trust-restricted'), 1200) . substr($h, (int) strpos($h, '<h2>History</h2>'));
ok(!str_contains($card, 'tvjun') && !str_contains($card, 'tvleo') && !str_contains($card, 'tvsam'), 'and never who flagged them');
ok(substr_count($h, 'class="wrap trust-notice"') === 1 && str_contains($h, 'See why and what happens next'), 'every page carries the notice for a restricted member');
ok(str_contains($h, 'noindex'), 'the page is not indexed');
ob_start(); notifications([]); $nh = (string) ob_get_clean();
ok(str_contains($nh, 'Your account is restricted.') && str_contains($nh, e(url('trust'))), 'the notification says so in words and links to the page');
ok(str_contains((string) file_get_contents(dirname(__DIR__) . '/views/messages_thread.php'), "\$gate['reason'] === 'restricted'"), 'a message thread explains the pause instead of just failing');

/* ================= a moderator looks ================= */
$q = rmt_trust_restricted_queue();
$row = array_values(array_filter($q, static fn(array $r): bool => (int) $r['id'] === $ana))[0] ?? null;
ok($row && count($row['flags']) === 4 && in_array('Asked me for money', array_column($row['flags'], 'note'), true)
   && in_array('tvjun', array_column($row['flags'], 'reviewer'), true), 'moderators see the restricted member, each flag, its note and who left it');
ok(!rmt_trust_lift($mod, $sam)['ok'], 'an account that is not restricted cannot be lifted');
$lift = rmt_trust_lift($mod, $ana, 'Flags came from one group after an argument');
$s = $sum($ana);
ok($lift['ok'] && $s['trust_state'] === 'ok' && $s['flag_count'] === 0 && $s['trust_score'] === 0, 'setting the flags aside lifts the restriction by the same arithmetic');
ok((int) q_one("SELECT COUNT(*) n FROM user_reviews WHERE subject_id=? AND status='discounted'", [$ana])['n'] === 4, 'the flags are kept as a record, not deleted');
$ev = q_one('SELECT * FROM trust_events WHERE user_id=? ORDER BY id DESC LIMIT 1', [$ana]);
ok($ev['to_state'] === 'ok' && $ev['reason'] === 'moderator' && (int) $ev['actor_id'] === $mod, 'the lift is written down with who did it');
ok((int) q_one("SELECT COUNT(*) n FROM moderation_log WHERE target_type='user' AND target_id=? AND action='lift_restriction'", [$ana])['n'] === 1, 'and in the moderation log');
ok((int) q_one("SELECT COUNT(*) n FROM notifications WHERE user_id=? AND type='trust_restored'", [$ana])['n'] === 1, 'the member is told it was lifted');
ok(!rmt_trust_restricted($ana) && rmt_message_allowed($ana, $sam)['ok'] && rmt_city_room_post($ana, $did, 'back')['ok'], 'and can message again at once');

/* ================= reviews from accounts that are gone ================= */
$flag($sam, $kim); $flag($leo, $kim); $flag($jun, $kim);
ok($sum($kim)['trust_state'] === 'restricted', 'three flags restrict another member');
q_run("UPDATE users SET status='suspended' WHERE id=?", [$jun]);
rmt_trust_recompute($kim);
ok($sum($kim)['trust_state'] === 'ok' && $sum($kim)['flag_count'] === 2, 'a flag from an account that is no longer active stops counting, and the restriction lifts by itself');
ok(q_one('SELECT reason FROM trust_events WHERE user_id=? ORDER BY id DESC LIMIT 1', [$kim])['reason'] === 'flags_cleared', 'recorded as cleared, not as a moderator');

/* ================= wiring ================= */
$routes = (string) file_get_contents(dirname(__DIR__) . '/public/index.php');
foreach (['trust_page', 'trust_review', 'admin_trust_lift'] as $fn) ok(str_contains($routes, "'$fn'") && function_exists($fn), "route handler $fn is wired and exists");
ok(str_contains((string) file_get_contents(dirname(__DIR__) . '/views/layout/nav_v2.php'), "url('trust')"), 'the menu links to the trust page');
ok(str_contains((string) file_get_contents(dirname(__DIR__) . '/views/admin_moderation.php'), 'admin/trust/lift'), 'the moderation page has the lift form');
$src = (string) file_get_contents(dirname(__DIR__) . '/app/trust_reviews.php') . (string) file_get_contents(dirname(__DIR__) . '/app/buddies.php');
ok(!preg_match('/shadow/i', (string) file_get_contents(dirname(__DIR__) . '/app/buddies.php')) && !preg_match('/ORDER BY[^;]*trust_score/i', $src), 'no listing is ordered by the score and nothing is shadowbanned');

$pdo->rollBack();
echo $fails ? "\ntrust_reviews_test: $fails FAILED\n" : "\ntrust_reviews_test: all passed\n";
exit($fails ? 1 : 0);
