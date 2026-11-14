<?php
/**
 * Vouches, flags, the private trust score, moderator review, restriction and appeal
 * (migrations 122 and 123, app/trust_reviews.php).
 *
 * The promises tested here:
 *
 * 1. Only people who really crossed paths can review each other, once, inside the window.
 * 2. The numbers are plain arithmetic the member can check, shown to that member and to
 *    moderators and to nobody else.
 * 3. NOTHING IS AUTOMATIC. No number of flags and no score restricts anybody. Flags put an account
 *    in front of a moderator, with warnings about the patterns a coordinated pile-on leaves.
 * 4. A restriction needs a moderator, a written note, a reason the member is shown, and 'active'
 *    mode. It is announced, appealable, and every step is in the audit trail.
 * 5. The staged rollout holds: off = staff only, monitor = no restrictions possible.
 *
 *   php tests/trust_reviews_test.php
 */
putenv('DATABASE_URL');
putenv('RESEND_API_KEY');
putenv('RMT_TRUST_MODE');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }
$mode = static function (string $m): void { $GLOBALS['rmt_trust_mode'] = $m; };

$pdo = db(); $pdo->beginTransaction();
$t0 = time();
$at = static fn(int $minutes): string => date('Y-m-d H:i:s', $t0 + $minutes * 60);
$now = $at(0);
$old = $at(-200 * 1440);
$dest = q_one('SELECT * FROM destinations ORDER BY id LIMIT 1');
$did = (int) $dest['id'];
$names = ['tvsam', 'tvana', 'tvleo', 'tvkim', 'tvjun', 'tvout', 'tvmod', 'tvmod2', 'tvnew'];
foreach ($names as $u) {
    q_run("INSERT INTO users (username,email,password_hash,role,status,birthdate,email_verified_at,created_at) VALUES (?,?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', str_starts_with($u, 'tvmod') ? 'mod' : 'member', 'active', '1990-01-01', $old, $u === 'tvnew' ? $at(-1440) : $old]);
    $uid = (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];
    if (!q_one('SELECT 1 FROM profiles WHERE user_id=?', [$uid])) q_run('INSERT INTO profiles (user_id, display_name) VALUES (?,?)', [$uid, ucfirst($u)]);
}
$id = fn(string $u): int => (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];
[$sam, $ana, $leo, $kim, $jun, $out, $mod, $mod2, $new] = array_map($id, $names);
$keys = static fn(int $uid, ?string $when = null): array => array_map(
    static fn(array $r): string => $r['subject_id'] . '|' . $r['context_key'], rmt_trust_reviewable($uid, $when));
$sum = static fn(int $uid): array => rmt_trust_summary($uid);
$notes = static fn(int $uid, string $type): int => (int) q_one('SELECT COUNT(*) n FROM notifications WHERE user_id=? AND type=?', [$uid, $type])['n'];
$state = static fn(int $uid): string => (string) q_one('SELECT trust_state FROM profiles WHERE user_id=?', [$uid])['trust_state'];

/* A buddy trip: sam posted, ana and leo were accepted, kim only asked, out was declined. */
q_run("INSERT INTO buddy_posts (user_id,trip_type,title,where_text,destination_id,date_from,date_to,spots,description,status,created_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)", [$sam, 'trip', 'Sam goes hiking', '', $did, date('Y-m-d', $t0 - 9 * 86400), date('Y-m-d', $t0 - 2 * 86400), 3, 'Come along.', 'closed', $at(-20000)]);
$post = (int) q_one('SELECT id FROM buddy_posts WHERE user_id=? ORDER BY id DESC LIMIT 1', [$sam])['id'];
foreach ([[$ana, 'accepted'], [$leo, 'accepted'], [$kim, 'interested'], [$out, 'declined']] as [$u, $st]) {
    q_run('INSERT INTO buddy_interest (post_id,user_id,state,created_at) VALUES (?,?,?,?)', [$post, $u, $st, $at(-20000)]);
}
$bk = 'bounty:' . $post;

/* ================= the staged rollout: off ================= */
ok(rmt_trust_mode() === 'off', 'the feature is off unless the environment says otherwise');
$GLOBALS['rmt_trust_mode'] = 'nonsense';
ok(rmt_trust_mode() === 'off', 'an unrecognised mode is off');
$mode('off');
rmt_buddy_complete_past($now);
ok($keys($sam) === [] && $keys($ana) === [], '[off] members are offered nobody to review');
ok(!rmt_trust_review_submit($ana, $sam, $bk, true)['ok'], '[off] and a forged vouch from a member is refused');
ok(!rmt_trust_open_for(['id' => $ana, 'role' => 'member']) && rmt_trust_open_for(['id' => $mod, 'role' => 'mod']), '[off] only staff accounts have the feature');

/* ================= who may review whom (monitor) ================= */
$mode('monitor');
$ks = $keys($sam); sort($ks); $want = ["$ana|$bk", "$leo|$bk"]; sort($want);
ok($ks === $want, 'the poster can review everybody they accepted');
$ka = $keys($ana); sort($ka); $want = ["$sam|$bk", "$leo|$bk"]; sort($want);
ok($ka === $want, 'an accepted traveler can review the poster and the other accepted traveler');
ok($keys($kim) === [] && $keys($out) === [], 'somebody who only asked, or was declined, cannot review anybody');
ok(!rmt_trust_review_submit($kim, $sam, $bk, false)['ok'], 'and a forged flag from them is refused');
ok(!rmt_trust_review_submit($ana, $kim, $bk, true)['ok'], 'nobody can review somebody who was not on the trip');
ok(!rmt_trust_review_submit($ana, $ana, $bk, true)['ok'], 'nobody can vouch for themselves');
ok($keys($ana, $at((RMT_TRUST_REVIEW_DAYS + 1) * 1440)) === [], 'the window closes after ' . RMT_TRUST_REVIEW_DAYS . ' days');

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
$ck($kim, -600, -300, false);
$ck($out, -310, -200, true);
$ckKey = 'checkin:' . min($cAna, $cJun) . ':' . max($cAna, $cJun);
ok(in_array("$jun|$ckKey", $keys($ana), true) && in_array("$ana|$ckKey", $keys($jun), true), 'two travelers who shared a room for two hours and both wrote can review each other');
ok(!in_array($kim, array_column(rmt_trust_reviewable($ana), 'subject_id'), true), 'somebody who never wrote in the room is not offered');
ok(!in_array($out, array_column(rmt_trust_reviewable($ana), 'subject_id'), true), 'an overlap of ten minutes is not a meeting');

/* ================= the arithmetic, and duplicates ================= */
ok(rmt_trust_review_submit($ana, $sam, $bk, true, 'Great company')['ok'], 'a vouch is taken');
ok(!rmt_trust_review_submit($ana, $sam, $bk, true)['ok'], 'the same vouch cannot be given twice');
ok(!rmt_trust_review_submit($ana, $sam, $bk, false)['ok'], 'nor turned into a flag afterwards');
ok((int) q_one('SELECT COUNT(*) n FROM user_reviews WHERE reviewer_id=? AND subject_id=?', [$ana, $sam])['n'] === 1, 'one row for one meeting');
$dupe = false;
$pdo->exec('SAVEPOINT dupe');
try { q_run('INSERT INTO user_reviews (reviewer_id,subject_id,context_type,context_key,is_vouch,status,created_at) VALUES (?,?,?,?,?,?,?)', [$ana, $sam, 'bounty', $bk, 0, 'counted', $now]); }
catch (\PDOException $e) { $dupe = true; }
$pdo->exec('ROLLBACK TO dupe');
ok($dupe, 'and the database itself refuses a second one');
ok(!in_array("$sam|$bk", $keys($ana), true), 'once answered it is no longer offered');
ok(rmt_trust_review_submit($leo, $sam, $bk, true)['ok'] && $sum($sam)['vouch_count'] === 2 && $sum($sam)['trust_score'] === 2, 'two travelers vouching is a score of 2');
ok(rmt_trust_review_submit($sam, $ana, $bk, true)['ok'] && $sum($ana)['vouch_count'] === 1, 'vouching is mutual: the poster vouches back');
ok(rmt_trust_review_submit($jun, $ana, $ckKey, false, 'Asked me for money')['ok'], 'a flag is taken');
$s = $sum($ana);
ok($s['flag_count'] === 1 && $s['trust_score'] === 1 - RMT_TRUST_FLAG_WEIGHT, 'one vouch and one flag is 1 minus ' . RMT_TRUST_FLAG_WEIGHT);

/* ================= nothing is automatic ================= */
$flag = static function (int $by, int $about, string $key, string $when) : void {
    q_run('INSERT INTO user_reviews (reviewer_id,subject_id,context_type,context_key,is_vouch,note,status,created_at) VALUES (?,?,?,?,?,?,?,?)',
          [$by, $about, 'bounty', $key, 0, 'felt unsafe', 'counted', $when]);
    rmt_trust_recompute($about);
};
$q = static fn(): array => rmt_trust_mod_queue();
$inQ = static fn(string $which, int $uid): ?array => array_values(array_filter(rmt_trust_mod_queue()[$which], static fn(array $r): bool => (int) ($which === 'appeals' ? $r['user_id'] : $r['id']) === $uid))[0] ?? null;
ok($inQ('review', $ana) === null, 'one flag does not reach the review queue');
$flag($leo, $ana, 'bounty:x1', $at(-50)); $flag($kim, $ana, 'bounty:x2', $at(-40)); $flag($out, $ana, 'bounty:x3', $at(-30)); $flag($new, $ana, 'bounty:x4', $at(-20));
$s = $sum($ana);
foreach (['monitor', 'active'] as $m) {
    $mode($m); rmt_trust_recompute($ana);
    ok($state($ana) === 'ok' && !rmt_trust_restricted($ana, true), "[$m] five travelers flagging and a score of {$s['trust_score']} restricts nobody");
}
ok($s['flag_count'] === 5 && $s['trust_score'] === 1 - 5 * RMT_TRUST_FLAG_WEIGHT, 'the numbers are still counted exactly');
ok(rmt_message_allowed($ana, $sam)['ok'] && $notes($ana, 'trust_restricted') === 0
   && (int) q_one('SELECT COUNT(*) n FROM trust_events WHERE user_id=?', [$ana])['n'] === 0, 'no pause, no notification, no event: flags alone change nothing for the member');
q_run('INSERT INTO user_reviews (reviewer_id,subject_id,context_type,context_key,is_vouch,status,created_at) VALUES (?,?,?,?,?,?,?)', [$leo, $ana, 'bounty', 'bounty:again', 0, 'counted', $now]);
ok(rmt_trust_recompute($ana)['flag_count'] === 5, 'the same traveler flagging twice counts once');

/* ================= the review queue, and the warnings ================= */
$row = $inQ('review', $ana);
ok($row !== null && count($row['flags']) === 6, 'enough flags put the account in front of a moderator, with every flag');
ok(in_array('Asked me for money', array_column($row['flags'], 'note'), true) && in_array('tvjun', array_column($row['flags'], 'reviewer'), true), 'moderators see each note and who left it');
$sig = implode(' | ', $row['signals']);
ok(str_contains($sig, 'within 24 hours'), 'a burst of flags inside a day is called out');
ok(str_contains($sig, 'less than two weeks old'), 'flags from brand new accounts are called out');
q_run('INSERT INTO user_reviews (reviewer_id,subject_id,context_type,context_key,is_vouch,status,created_at) VALUES (?,?,?,?,?,?,?)', [$ana, $kim, 'bounty', 'bounty:r1', 0, 'counted', $at(-900)]);
q_run('INSERT INTO follows (follower_id, followee_id, created_at) VALUES (?,?,?)', [$leo, $out, $now]);
$sig = implode(' | ', rmt_trust_flag_signals($ana));
ok(str_contains($sig, 'had flagged 1 of the travelers who flagged them'), 'an argument between two members is called out');
ok(str_contains($sig, 'follow each other'), 'flaggers who know each other are called out');
foreach ([$kim, $out, $new] as $j => $by) q_run('UPDATE user_reviews SET context_key = ? WHERE reviewer_id = ? AND subject_id = ?', ['bounty:same', $by, $ana]);
q_run("UPDATE user_reviews SET context_key = 'bounty:same' WHERE reviewer_id = ? AND subject_id = ? AND context_key = 'bounty:x1'", [$leo, $ana]);
ok(str_contains(implode(' | ', rmt_trust_flag_signals($ana)), 'come from one trip or one room'), 'flags that all come from one trip are called out as one group');
ok(rmt_trust_flag_signals($sam) === [], 'an account with no flags has no warnings');

/* One account cannot flag its way through a room. */
foreach (range(1, RMT_TRUST_FLAGS_PER_30D) as $i) q_run('INSERT INTO user_reviews (reviewer_id,subject_id,context_type,context_key,is_vouch,status,created_at) VALUES (?,?,?,?,?,?,?)', [$jun, $out, 'bounty', 'bounty:cap' . $i, 0, 'counted', $at(-$i)]);
q_run("DELETE FROM user_reviews WHERE reviewer_id = ? AND subject_id = ?", [$jun, $ana]);
$r = rmt_trust_review_submit($jun, $ana, $ckKey, false);
ok(!$r['ok'] && str_contains($r['error'], 'limit on flags'), 'a member is capped at ' . RMT_TRUST_FLAGS_PER_30D . ' flags in 30 days');
ok(rmt_trust_review_submit($jun, $ana, $ckKey, true)['ok'], 'the cap is on flags, not on vouches');
q_run("DELETE FROM user_reviews WHERE reviewer_id = ? AND (subject_id = ? OR subject_id = ?)", [$jun, $out, $ana]);
$flag($jun, $ana, $ckKey, $at(-60));

/* ================= a moderator decides ================= */
$note = 'Read all flags and the room log; pattern is consistent.';
ok(!rmt_trust_mod_action($sam, $ana, 'no_action', $note)['ok'], 'a member cannot act as a moderator');
ok(!rmt_trust_mod_action($mod, $ana, 'no_action', 'ok')['ok'], 'no action without a real note');
ok(!rmt_trust_mod_action($mod, $mod, 'no_action', $note)['ok'], 'a moderator cannot review their own account');
$mode('monitor');
$r = rmt_trust_mod_action($mod, $ana, 'restrict', $note, 'Several travelers reported requests for money.');
ok(!$r['ok'] && str_contains($r['error'], 'switched off') && $state($ana) === 'ok', '[monitor] a moderator cannot restrict anybody');
$mode('off');
ok(!rmt_trust_mod_action($mod, $ana, 'restrict', $note, 'Several travelers reported requests for money.')['ok'] && $state($ana) === 'ok', '[off] nor while the feature is off');
$mode('active');
ok(!rmt_trust_mod_action($mod, $ana, 'restrict', $note, '')['ok'] && $state($ana) === 'ok', '[active] not without a reason the member will be shown');
ok(!rmt_trust_mod_action($mod, $sam, 'restrict', $note, 'There is a reason here.')['ok'] && $state($sam) === 'ok', '[active] not an account with no counted flags');
ok(!rmt_trust_mod_action($mod, $mod2, 'restrict', $note, 'There is a reason here.')['ok'], '[active] not a staff account');

ok(rmt_trust_mod_action($mod, $ana, 'no_action', $note)['ok'] && $state($ana) === 'ok', 'reviewed with no action: nothing changes');
ok($inQ('review', $ana) === null, 'and the account leaves the queue');
ok($notes($ana, 'trust_restricted') + $notes($ana, 'trust_restored') === 0, 'with no notification to the member');
$flag($mod2, $ana, 'bounty:late', $at(5));
ok($inQ('review', $ana) !== null, 'a new flag after the review brings it back');

$r = rmt_trust_mod_action($mod, $ana, 'restrict', $note, 'Several travelers reported requests for money.', $at(6));
ok($r['ok'] && $state($ana) === 'restricted' && rmt_trust_restricted($ana), '[active] a moderator restricts after a documented review');
$ev = q_one('SELECT * FROM trust_events WHERE user_id=? ORDER BY id DESC LIMIT 1', [$ana]);
ok($ev['from_state'] === 'ok' && $ev['to_state'] === 'restricted' && $ev['reason'] === 'restricted' && (int) $ev['actor_id'] === $mod
   && $ev['note'] === 'Several travelers reported requests for money.', 'the audit trail has who, when, the numbers and the reason given');
$ml = q_one("SELECT * FROM moderation_log WHERE target_type='user' AND target_id=? AND action='trust_restrict' ORDER BY id DESC LIMIT 1", [$ana]);
ok($ml && (int) $ml['actor_id'] === $mod && $ml['note'] === $note, 'and the moderation log has the internal note');
ok($notes($ana, 'trust_restricted') === 1, 'the member is notified, once');
ok(!rmt_trust_mod_action($mod, $ana, 'restrict', $note, 'Several travelers reported requests for money.')['ok'] && $notes($ana, 'trust_restricted') === 1, 'restricting twice is refused and does not notify twice');

/* ================= what restricted means ================= */
ok(rmt_message_allowed($ana, $sam) === ['ok' => false, 'reason' => 'restricted'], 'a restricted member cannot send private messages');
ok(rmt_message_allowed($sam, $ana)['ok'], 'other members can still write to them');
rmt_checkin_start($ana, $did, 3);
$r = rmt_city_room_post($ana, $did, 'hello?');
ok(!$r['ok'] && str_contains($r['error'], 'paused'), 'nor post in a city room, and is told why');
ok(rmt_checkin_live($ana) !== null && (string) q_one('SELECT status FROM users WHERE id=?', [$ana])['status'] === 'active'
   && (int) q_one("SELECT COUNT(*) n FROM buddy_interest WHERE user_id=? AND state='accepted'", [$ana])['n'] === 1, 'but nothing is hidden or removed: no shadowban');

/* ================= what the member is shown ================= */
ini_set('display_errors', '0');
$_SESSION['uid'] = $ana;
$render = static function (): string { ob_start(); trust_page([]); return (string) ob_get_clean(); };
$h = $render();
ok(str_contains($h, 'Your account is restricted') && str_contains($h, 'The reason given:') && str_contains($h, 'Several travelers reported requests for money.'), 'the trust page says restricted, by a moderator, and the reason');
ok(preg_match('/id="trust-score">-11</', $h) === 1 && preg_match('/id="trust-vouches">1</', $h) === 1 && preg_match('/id="trust-flags">6</', $h) === 1, 'it shows the score, the vouches and the flags');
ok(str_contains($h, '1 minus 2 times 6 is -11'), 'and the arithmetic, with their own numbers in it');
ok(str_contains($h, 'Flags never restrict an account by themselves'), 'and says plainly that nothing is automatic');
ok(!str_contains($h, 'Asked me for money') && !str_contains($h, 'felt unsafe') && !str_contains($h, 'Read all flags'), 'never what was written about them, nor the internal note');
$card = substr($h, (int) strpos($h, 'trust-restricted'), 1500) . substr($h, (int) strpos($h, '<h2>History</h2>'));
ok(!str_contains($card, 'tvjun') && !str_contains($card, 'tvleo') && !str_contains($card, 'tvmod'), 'never who flagged them or which moderator acted');
ok(str_contains($h, 'action="' . e(url('trust/appeal')) . '"'), 'and offers the appeal');
ok(substr_count($h, 'class="wrap trust-notice"') === 1 && str_contains($h, 'See the reason and how to appeal'), 'every page carries the notice for a restricted member');
ok(str_contains($h, 'noindex'), 'the page is not indexed');
ob_start(); notifications([]); $nh = (string) ob_get_clean();
ok(str_contains($nh, 'Your account is restricted.') && str_contains($nh, 'A moderator paused your messaging'), 'the notification says what happened, accurately');

/* Nothing about it is public. */
foreach (['profile.php', '_buddy_card.php', 'buddy_show.php', '_person_card.php', 'city_room.php', '_trust.php'] as $v) {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/views/' . $v);
    ok(!preg_match('/trust_score|flag_count|vouch_count|trust_state/', $src), "views/$v shows no score, count or state");
}
$all = (string) file_get_contents(dirname(__DIR__) . '/app/buddies.php') . (string) file_get_contents(dirname(__DIR__) . '/app/discovery.php');
ok(!preg_match('/trust_score|flag_count|trust_state/', $all), 'no listing reads the score, so nothing is ranked or hidden by it');

/* ================= appeal ================= */
ok(!rmt_trust_appeal_submit($sam, 'I would like to appeal something please.')['ok'], 'an account that is not restricted has nothing to appeal');
ok(!rmt_trust_appeal_submit($ana, 'too short')['ok'], 'an appeal has to say something');
ok(rmt_trust_appeal_submit($ana, 'These came from one group after an argument about a hostel bill.', $at(10))['ok'], 'a restricted member appeals');
ok(!rmt_trust_appeal_submit($ana, 'Sending the same appeal a second time to be sure.', $at(11))['ok'], 'one open appeal at a time');
$ap = $inQ('appeals', $ana);
ok($ap !== null && str_contains((string) $ap['body'], 'hostel bill'), 'moderators see the appeal');
ok(str_contains($render(), 'Your appeal is with a moderator'), 'the member sees it is being looked at');
ok(!rmt_trust_appeal_decide($sam, (int) $ap['id'], 'grant', $note)['ok'], 'a member cannot answer an appeal');
ok(!rmt_trust_appeal_decide($mod2, (int) $ap['id'], 'uphold', $note, '')['ok'], 'upholding needs an answer the member is shown');
ok(rmt_trust_appeal_decide($mod2, (int) $ap['id'], 'uphold', $note, 'Three of the flags are from separate trips.', $at(12))['ok'] && $state($ana) === 'restricted', 'a moderator upholds: the restriction stays');
ok($notes($ana, 'trust_appeal_upheld') === 1, 'and the member is told');
$h = $render();
ok(str_contains($h, 'Three of the flags are from separate trips.') && !str_contains($h, 'action="' . e(url('trust/appeal')) . '"'), 'they see the answer, and cannot appeal again straight away');
ok(!rmt_trust_appeal_submit($ana, 'Appealing again the very next day, please look again.', $at(1440))['ok'], 'not the next day');
$later = $at((RMT_TRUST_APPEAL_AGAIN_DAYS + 1) * 1440);
ok(rmt_trust_appeal_submit($ana, 'Two of those accounts have since been suspended for fraud.', $later)['ok'], 'they can appeal again after ' . RMT_TRUST_APPEAL_AGAIN_DAYS . ' days');
$ap2 = $inQ('appeals', $ana);
ok(rmt_trust_appeal_decide($mod, (int) $ap2['id'], 'grant', $note, 'You were right. Sorry for the trouble.', $later)['ok'] && $state($ana) === 'ok', 'a granted appeal lifts the restriction');
ok(!rmt_trust_restricted($ana) && rmt_message_allowed($ana, $sam)['ok'] && rmt_city_room_post($ana, $did, 'back')['ok'], 'and messaging works again at once');
ok($notes($ana, 'trust_restored') === 1 && $notes($ana, 'trust_restricted') === 1 && $notes($ana, 'trust_appeal_upheld') === 1, 'one notification per decision, none repeated');
$reasons = array_column(q_all('SELECT reason FROM trust_events WHERE user_id=? ORDER BY id', [$ana]), 'reason');
ok($reasons === ['reviewed_no_action', 'restricted', 'appeal_upheld', 'appeal_granted'], 'the audit trail holds every step in order');
ok((int) q_one("SELECT COUNT(*) n FROM moderation_log WHERE target_type='user' AND target_id=? AND action LIKE 'trust_%'", [$ana])['n'] === 4, 'and so does the moderation log');
$h = $render();
ok(str_contains($h, 'In good standing') && str_contains($h, 'Your appeal was granted') && substr_count($h, 'class="wrap trust-notice"') === 0, 'the page and the notice follow');

/* ================= set aside, and lift ================= */
$mode('active');
ok(rmt_trust_mod_action($mod, $ana, 'set_aside', $note)['ok'] && $sum($ana)['flag_count'] === 0 && $sum($ana)['trust_score'] === 1, 'setting the flags aside takes them out of the count');
ok((int) q_one("SELECT COUNT(*) n FROM user_reviews WHERE subject_id=? AND status='discounted'", [$ana])['n'] >= 6, 'and keeps them as a record');
$flag($sam, $kim, 'bounty:k1', $now); $flag($leo, $kim, 'bounty:k2', $now); $flag($out, $kim, 'bounty:k3', $now);
rmt_trust_mod_action($mod, $kim, 'restrict', $note, 'Travelers reported aggressive behaviour.');
ok(rmt_trust_mod_action($mod2, $kim, 'lift', $note)['ok'] && $state($kim) === 'ok' && $sum($kim)['flag_count'] === 4, 'a moderator can lift a restriction and leave the flags counted');
ok($notes($kim, 'trust_restored') === 1, 'and the member is told');
ok(!rmt_trust_mod_action($mod2, $kim, 'lift', $note)['ok'], 'lifting what is not restricted is refused');

/* A flag from an account that is gone stops counting. */
q_run("UPDATE users SET status='suspended' WHERE id=?", [$out]);
ok(rmt_trust_recompute($kim)['flag_count'] === 3, 'a flag from an account that is no longer active stops counting');

/* A restricted member always reaches their page, whatever the mode. */
rmt_trust_mod_action($mod, $kim, 'restrict', $note, 'Travelers reported aggressive behaviour.');
$mode('off');
ok(rmt_trust_restricted($kim, true) && !rmt_message_allowed($kim, $sam)['ok'], 'turning the feature off does not silently lift a moderator\'s decision');

/* ================= moderators' page ================= */
$mode('active');
ob_start(); $trust = rmt_trust_mod_queue(); include dirname(__DIR__) . '/views/_admin_trust.php'; $ah = (string) ob_get_clean();
ok(str_contains($ah, 'Mode: <b>active</b>') && str_contains($ah, '@tvkim') && str_contains($ah, 'value="lift"') && str_contains($ah, 'admin/trust/act'), 'the moderators\' page lists the restricted account with its actions');
ok(str_contains($ah, 'required minlength="' . RMT_TRUST_MOD_NOTE_MIN . '"'), 'and will not submit without a note');
$mode('monitor');
ob_start(); $trust = rmt_trust_mod_queue(); include dirname(__DIR__) . '/views/_admin_trust.php'; $ah = (string) ob_get_clean();
ok(!str_contains($ah, 'value="restrict"') && str_contains($ah, 'No account can be restricted'), '[monitor] the restrict button is not there at all');

/* ================= wiring ================= */
$routes = (string) file_get_contents(dirname(__DIR__) . '/public/index.php');
foreach (['trust_page', 'trust_review', 'trust_appeal', 'admin_trust_act', 'admin_trust_appeal'] as $fn) ok(str_contains($routes, "'$fn'") && function_exists($fn), "route handler $fn is wired and exists");
$src = (string) file_get_contents(dirname(__DIR__) . '/app/trust_reviews.php');
ok(preg_match_all("/SET trust_state = 'restricted'/", $src) === 1 && strpos($src, "SET trust_state = 'restricted'") > strpos($src, 'function rmt_trust_mod_action'), 'exactly one statement can restrict an account, and it is inside the moderator action');
ok(str_contains((string) file_get_contents(dirname(__DIR__) . '/views/admin_moderation.php'), '_admin_trust.php'), 'the moderation page includes the trust section');

$pdo->rollBack();
echo $fails ? "\ntrust_reviews_test: $fails FAILED\n" : "\ntrust_reviews_test: all passed\n";
exit($fails ? 1 : 0);
