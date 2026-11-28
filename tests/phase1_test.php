<?php
/**
 * Phase 1, the single player utility (app/phase1.php, 2026-11-27).
 *
 * Guest votes count once per browser and are rate limited; a save held before signup lands on the
 * new member's checklist; a guest trap report waits in the editor queue and shows nowhere; the
 * social links hide with Phase 1 on and come back everywhere or city by city.
 *
 * Builds its own throwaway database (bootstrap auto migrates and seeds a missing file).
 *   php tests/phase1_test.php
 */
putenv('DATABASE_URL');
putenv('RESEND_API_KEY');
$tmp = sys_get_temp_dir() . '/rmt_p1_test_' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('RMT_SQLITE=' . $tmp);
putenv('RMT_PHASE1');
putenv('RMT_SOCIAL_ON');
putenv('RMT_SOCIAL_CITY_ON');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';
ini_set('display_errors', '0');
if (session_status() !== PHP_SESSION_ACTIVE) $_SESSION = [];
$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }

// A city with published warnings, whatever the seed holds.
$city = null;
foreach (q_all('SELECT slug FROM destinations ORDER BY id') as $r) {
    if (count(rmt_p1_warnings((string) $r['slug'])) >= 2) { $city = (string) $r['slug']; break; }
}
ok($city !== null, 'a seeded city has published warnings');
if ($city === null) { @unlink($tmp); exit(1); }
$w = rmt_p1_warnings($city);
$k0 = (string) $w[0]['key']; $k1 = (string) $w[1]['key'];

echo "\nEvery warning is real data, with honest badges:\n";
ok(count(array_filter($w, fn($x) => $x['source'] === '')) === 0, 'every warning names its source');
$badFig = array_filter($w, fn($x) => $x['cost'] !== null && !preg_match('/\d/', (string) $x['cost']));
ok(!$badFig, 'a cost badge always carries a figure');
ok(rmt_p1_cost_from('A nice walk in the old town') === null, 'no figure in the text, no badge');

echo "\nGuest votes:\n";
$r1 = rmt_p1_vote($city, $k0, 'helpful', 'c:browserA', '10.0.0.1');
$r2 = rmt_p1_vote($city, $k0, 'helpful', 'c:browserA', '10.0.0.1');
ok($r1['ok'] && $r1['counted'], 'the first vote counts');
ok($r2['ok'] && !$r2['counted'], 'the same browser voting again does not');
rmt_p1_vote($city, $k0, 'helpful', 'c:browserB', '10.0.0.2');
rmt_p1_vote($city, $k0, 'saved', 'c:browserA', '10.0.0.1');
$c = rmt_p1_vote_counts($city);
ok(($c[$k0]['helpful'] ?? 0) === 2 && ($c[$k0]['saved'] ?? 0) === 1, 'counts are stored per warning and kind');
ok(isset(rmt_p1_my_votes($city, 'c:browserA')[$k0 . '|saved']), 'a browser sees its own votes');
ok(!rmt_p1_vote($city, 'no-such-key', 'helpful', 'c:browserC', '10.0.0.3')['ok'], 'an unknown warning is refused');
ok(!rmt_p1_vote($city, $k0, 'love', 'c:browserC', '10.0.0.3')['ok'], 'an unknown kind is refused');
$limited = false;
for ($i = 0; $i < 70; $i++) {
    $r = rmt_p1_vote($city, $k1, 'helpful', 'c:flood' . $i, '10.9.9.9');
    if (!$r['ok'] && ($r['error'] ?? '') === 'rate') { $limited = true; break; }
}
ok($limited, 'one address voting a flood is rate limited');

echo "\nA save held before signup lands on the checklist:\n";
$_SESSION = [];
rmt_p1_hold_save($city, $k1);
ok(isset($_SESSION[RMT_P1_PENDING_KEY]), 'a guest save waits in the session');
$reg = @rmt_register_quick('p1.saver@example.invalid', 'longenough1', true);
ok($reg['ok'], 'the guest joins');
$uid = (int) q_one('SELECT id FROM users WHERE email = ?', ['p1.saver@example.invalid'])['id'];
ok(rmt_p1_claim_pending($uid) === $city, 'the held save is claimed for the new member');
ok(!isset($_SESSION[RMT_P1_PENDING_KEY]), 'and leaves the session');
ok(rmt_p1_claim_pending($uid) === null, 'claiming twice writes nothing more');
$cl = rmt_p1_checklist($uid);
ok(count($cl) === 1 && $cl[0]['dest']['slug'] === $city && array_column($cl[0]['items'], 'key') === [$k1], 'the dashboard checklist shows that warning');
ok(rmt_p1_save($uid, $city, '*'), 'a full city save works');
$cl = rmt_p1_checklist($uid);
ok($cl[0]['full'] && count($cl[0]['items']) === count($w), 'and lists every warning for the city');
ok(!rmt_p1_save($uid, $city, 'no-such-key'), 'an unknown warning is not saved');

echo "\nA guest trap report is held for an editor:\n";
$_SESSION = [];
$before = count(rmt_p1_warnings($city));
$msg = rmt_p1_report_message('fee', 'The hotel added a resort fee at checkout that was never shown when booking. Ask before you pay.', '$45 a night');
$r = rmt_feedback_submit('trip_story', null, $msg, null, '', 'warnings:' . $city);
ok($r['ok'] && $r['id'] > 0, 'the report is accepted with no account');
$row = q_one('SELECT * FROM feedback WHERE id = ?', [$r['id']]);
ok($row['status'] === 'pending' && $row['kind'] === 'trip_story' && $row['topic'] === 'warnings:' . $city, 'it waits in the editor queue');
ok((int) q_one("SELECT COUNT(*) n FROM traveler_stories WHERE feedback_id = ?", [$r['id']])['n'] === 0, 'nothing is published');
$fresh = (function (string $s) { return q_all("SELECT id FROM traveler_stories WHERE status = 'live' AND topic = ?", ['warnings:' . $s]); })($city);
ok(!$fresh, 'and the city shows no traveler warning from it');
$_SESSION[RMT_P1_REPORT_KEY] = (int) $r['id'];
rmt_p1_claim_pending($uid);
ok((int) q_one('SELECT reported_by FROM feedback WHERE id = ?', [$r['id']])['reported_by'] === $uid, 'joining afterwards puts their name on it');
ok(q_one('SELECT status FROM feedback WHERE id = ?', [$r['id']])['status'] === 'pending', 'and it is still held');
$src = (string) file_get_contents(BASE_PATH . '/app/phase1.php');
ok(str_contains($src, "rmt_feedback_submit('trip_story'"), 'the report controller uses the trip story queue');

echo "\nFeature flags:\n";
$foot = (string) file_get_contents(BASE_PATH . '/views/layout/footer.php');
foreach (['buddies', 'events', 'communities', 'leaderboard', 'travelers'] as $lnk) {
    ok(str_contains($foot, "): ?><a href=\"<?= e(url('$lnk')) ?>\"") && !str_contains($foot, "      <a href=\"<?= e(url('$lnk')) ?>\""), "footer /$lnk sits behind a flag");
}
ok(rmt_phase1_on(), 'Phase 1 is on by default');
foreach (RMT_P1_SOCIAL as $f) ok(rmt_social_on($f, $city), "SEO preserve: $f links stay by default");
ok(!rmt_p1_trim_links(), 'link trimming is off unless approved');
putenv('RMT_P1_TRIM_LINKS=1');
ok(rmt_p1_trim_links(), 'RMT_P1_TRIM_LINKS=1 switches trimming on');
foreach (RMT_P1_SOCIAL as $f) ok(!rmt_social_on($f, $city), "trimmed: $f links are hidden");
putenv('RMT_SOCIAL_CITY_ON=' . $city . '=talk|going');
ok(rmt_social_on('talk', $city) && rmt_social_on('going', $city), 'switched back on for one city');
ok(!rmt_social_on('meetups', $city), 'only the features named');
ok(!rmt_social_on('talk', 'some-other-city') && !rmt_social_on('talk'), 'and only in that city');
putenv('RMT_SOCIAL_CITY_ON');
putenv('RMT_SOCIAL_ON=meetups');
ok(rmt_social_on('meetups') && rmt_social_on('meetups', $city) && !rmt_social_on('talk'), 'switched back on everywhere');
putenv('RMT_SOCIAL_ON=all');
ok(rmt_social_on('collections') && rmt_social_on('talk'), 'all brings every one back');
putenv('RMT_SOCIAL_ON');
putenv('RMT_P1_TRIM_LINKS');
$hubCity = null;
foreach (q_all('SELECT slug FROM destinations ORDER BY id') as $r3) {
    if (count(rmt_city_scams((string) $r3['slug'])['items'] ?? []) >= RMT_HUB_MIN_WARNINGS) { $hubCity = (string) $r3['slug']; break; }
}
if ($hubCity !== null) {
    $sc = array_values(array_filter(rmt_p1_warnings($hubCity), static fn(array $x): bool => str_starts_with($x['key'], 'scam:') || str_starts_with($x['key'], 'byb:')));
    ok($sc && !array_filter($sc, static fn(array $x): bool => !str_ends_with($x['url'], 'warnings/' . $hubCity)), 'SEO dedupe: a city with its own warnings page only names its scams and points there');
    ok(!array_filter($sc, static fn(array $x): bool => $x['cost'] !== null), 'and repeats no figures from that page');
}
putenv('RMT_PHASE1=0');
ok(!rmt_phase1_on() && rmt_social_on('talk') && rmt_social_on('going', $city), 'Phase 1 off shows everything as before');
putenv('RMT_PHASE1');

echo "\nNew trap alerts reach members who saved the city, once:\n";
$other = null;
foreach (q_all('SELECT slug FROM destinations WHERE slug <> ? ORDER BY id LIMIT 1', [$city]) as $r2) $other = (string) $r2['slug'];
$nowS = date('Y-m-d H:i:s');
q_run("INSERT INTO traveler_stories (feedback_id, topic, body, when_text, published_by, published_at, status) VALUES (NULL,?,?,NULL,?,?,'live')",
      ['warnings:' . $city, 'Taxi at the airport added a 30 euro fee nobody mentioned.', $uid, $nowS]);
$sid = (int) q_one("SELECT MAX(id) m FROM traveler_stories")['m'];
q_run("INSERT INTO traveler_stories (feedback_id, topic, body, when_text, published_by, published_at, status) VALUES (NULL,?,?,NULL,?,?,'live')",
      ['warnings:' . $city, 'An old report from long ago, already past the window.', $uid, date('Y-m-d H:i:s', time() - 30 * 86400)]);
if ($other !== null) q_run("INSERT INTO traveler_stories (feedback_id, topic, body, when_text, published_by, published_at, status) VALUES (NULL,?,?,NULL,?,?,'live')",
      ['warnings:' . $other, 'A trap in a city this member never saved.', $uid, $nowS]);
$plan = rmt_trap_alert_plan();
ok(isset($plan[$uid][$city]) && $plan[$uid][$city] === [$sid], 'the member is planned one alert for the new story in the saved city only');
ok(count($plan[$uid] ?? []) === 1, 'no alert for a city they did not save');
$dry = rmt_trap_alerts_run(true);
ok($dry['emails'] >= 1 && $dry['sent'] === 0, 'a dry run counts and sends nothing');
$real = rmt_trap_alerts_run(false);
ok($real['sent'] === 0 && $real['skipped'] >= 1, 'with mail disabled nothing is sent');
ok((int) q_one('SELECT COUNT(*) n FROM trap_alerts_sent')['n'] === 0, 'and nothing is marked sent, so it goes out once mail works');
q_run('INSERT INTO trap_alerts_sent (user_id, story_id, sent_at) VALUES (?,?,?)', [$uid, $sid, $nowS]);
ok(!isset(rmt_trap_alert_plan()[$uid][$city]), 'a story already mailed is never planned again');


echo "
A day with no new reports sends nothing:
";
q_run("UPDATE traveler_stories SET status = 'down'");
$zero = rmt_trap_alerts_run(false);
ok($zero === ['members' => 0, 'emails' => 0, 'sent' => 0, 'skipped' => 0], 'zero reports: zero emails, clean exit');

echo "
An editor published trap report never grants Founding Traveler:
";
ok(rmt_story_publish((int) $r['id'], $uid, 'The taxi rank at the station added a fee nobody had mentioned to us.', '') === '', 'the editor publishes the guest report');
ok((int) q_one('SELECT reported_by FROM feedback WHERE id = ?', [$r['id']])['reported_by'] === $uid, 'it is credited to the member');
// Test on a member who meets every other part of the rule (an early account, active), so only the
// review requirement stands between them and the badge.
$early = q_one("SELECT u.id FROM users u WHERE u.id <= ? AND u.status = 'active'
                  AND NOT EXISTS (SELECT 1 FROM reviews v WHERE v.user_id = u.id AND v.status = 'published')
                  AND NOT EXISTS (SELECT 1 FROM user_badges ub JOIN badges b ON b.id = ub.badge_id WHERE ub.user_id = u.id AND b.slug = 'founding-traveler')
                ORDER BY u.id LIMIT 1", [RMT_FOUNDING_TRAVELER_CUTOFF]);
ok($early !== null, 'an early member without a review exists to test with');
$eid = (int) ($early['id'] ?? 0);
q_run('UPDATE feedback SET reported_by = ? WHERE id = ?', [$eid, $r['id']]);
rmt_award_badges($eid);
ok(!rmt_qualifies_founding_traveler($eid), 'an early member does not qualify from a published trap report');
ok(!q_one("SELECT 1 x FROM user_badges ub JOIN badges b ON b.id = ub.badge_id WHERE ub.user_id = ? AND b.slug = 'founding-traveler'", [$eid]), 'and no badge was awarded');

$pdo = null;
@unlink($tmp);
echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
