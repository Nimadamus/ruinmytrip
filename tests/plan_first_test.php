<?php
/**
 * Trip first (app/plan_first.php): a visitor states the trip before being asked for an account.
 *
 * What must hold:
 *   - the form's rules are the trip validator's and the buddy validator's, not a third copy.
 *   - a trip here is always a plan still ahead: a city and both dates, not over.
 *   - a buddy request is validated only when it was asked for, and still needs its note and safety terms.
 *   - nothing is written for a signed out visitor (the draft is session only).
 *   - every "post your dates" door sends a signed out reader here instead of a sign in wall.
 *   - the measurement is closed: unknown CTA labels are dropped, a stored path never carries a query.
 *
 *   php tests/plan_first_test.php   (runs against database/dev.sqlite, read only)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/database/dev.sqlite')) { echo "SKIP  no dev database\n"; exit(0); }
require $root . '/app/bootstrap.php';
require BASE_PATH . '/app/controllers.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$dest = q_one('SELECT id, name FROM destinations ORDER BY id LIMIT 1');
$today = '2026-09-25';
$base = ['destination_id' => (string) $dest['id'], 'date_from' => '2026-10-10', 'date_to' => '2026-10-15',
         'party' => 'solo', 'interests' => ['food', 'nonsense'], 'meet' => 'yes'];

$v = rmt_plan_first_validate($base, $today);
ok($v['ok'], 'a city, two dates ahead and nothing else is a valid trip');
ok($v['inputs']['trip']['travel_style'] === 'solo', 'solo carries over as the trip travel style');
ok($v['inputs']['trip']['open_to_meeting'] === '1', 'meet=yes is open to meeting');
ok($v['inputs']['interests'] === ['food'], 'unknown interests are dropped');
ok($v['inputs']['buddy'] === null, 'no buddy post unless asked for');

ok(rmt_plan_first_inputs($base + ['party' => 'group'])['trip']['travel_style'] === 'solo'
   && rmt_plan_first_inputs(['party' => 'group'] + $base)['trip']['travel_style'] === '',
   'a group is not guessed into one of the four travel styles');
ok(rmt_plan_first_inputs(['meet' => 'maybe'] + $base)['trip']['open_to_meeting'] === '', 'maybe stays unstated');
ok(rmt_plan_first_inputs(['meet' => 'no'] + $base)['trip']['open_to_meeting'] === '0', 'no is a no');

ok(!rmt_plan_first_validate(['destination_id' => ''] + $base, $today)['ok'], 'a city is required');
ok(!rmt_plan_first_validate(['date_to' => ''] + $base, $today)['ok'], 'both dates are required');
ok(!rmt_plan_first_validate(['date_from' => '2026-10-15', 'date_to' => '2026-10-10'] + $base, $today)['ok'], 'backwards dates are refused');
ok(!rmt_plan_first_validate(['date_from' => '2026-08-01', 'date_to' => '2026-08-05'] + $base, $today)['ok'], 'a trip that is over is refused');
ok(!rmt_plan_first_validate(['date_from' => '2029-01-01', 'date_to' => '2029-01-05'] + $base, $today)['ok'], 'more than two years out is refused');

$b = rmt_plan_first_validate($base + ['want_buddy' => '1'], $today);
ok(!$b['ok'], 'a buddy request without a note or safety terms is refused');
ok((bool) array_filter($b['errors'], static fn($e) => str_contains($e, 'who you hope to travel with')), 'and it says what is missing in plain words');
$b = rmt_plan_first_validate($base + ['want_buddy' => '1', 'safety_ack' => '1',
    'buddy_note' => 'Someone to share a few dinners and a day trip with.'], $today);
ok($b['ok'] && $b['inputs']['buddy'] !== null, 'a complete buddy request validates');
ok($b['inputs']['buddy']['interests'] === ['food'], 'the buddy post carries the same interests');

$sum = rmt_plan_first_summary($b['inputs']);
ok(str_contains($sum['lines'][0], (string) $dest['name']) && in_array('Looking for a travel buddy', $sum['lines'], true),
   'the summary names the city and the buddy request');

$ov = rmt_plan_first_overlap((int) $dest['id'], '2026-10-10', '2026-10-15');
ok(isset($ov['travelers'], $ov['buddies'], $ov['locals']) && min($ov) >= 0, 'overlap is three counts, never negative');

// Team prompts are ours, labelled, and only ever put words in the composer.
$pr = rmt_city_prompts('Lisbon', 3);
ok(count($pr) === 3 && !array_diff(array_keys($pr), array_keys(RMT_CITY_PROMPTS)), 'three prompts from the closed list');
ok(rmt_city_prompt_start('avoid', 'Lisbon') === 'Tourists in Lisbon should avoid ', 'a prompt key gives its starter');
ok(rmt_city_prompt_start('<script>', 'Lisbon') === '', 'an unknown prompt key gives nothing');

// Measurement is closed.
ok(rmt_track_path('/p/le-rapido-paris?utm_source=x#top') === '/p/le-rapido-paris', 'a stored path never carries a query or fragment');
ok(rmt_track_path('https://evil.example/x') === null, 'a full URL is not a path');
ok(rmt_track_path('') === null, 'no path, no column');
ok(in_array('cta_dates', RMT_CTA_KEYS, true) && !in_array('<script>', RMT_CTA_KEYS, true), 'CTA labels are a closed list');
foreach (['plan_view', 'plan_started', 'plan_submitted', 'plan_signup_view', 'cta_click', 'human_interaction', 'buddy_post_created'] as $e) {
    ok(in_array($e, RMT_CONTRIB_EVENTS, true), "event is recordable: $e");
}

// The doors.
$src = static fn(string $f): string => (string) file_get_contents(BASE_PATH . '/' . $f);
ok(str_contains($src('app/controllers.php'), "redirect('/plan?' . http_build_query(\$q));"), 'signed out /trip/new goes to /plan');
ok(str_contains($src('app/buddies.php'), "redirect('/plan?' . http_build_query(\$q));"), 'signed out /buddies/new goes to /plan');
ok(str_contains($src('views/_city_community.php'), "url('plan/ask')"), 'the signed out city composer writes first, joins after');
ok(str_contains($src('views/_dest_social_cta.php'), "action=\"<?= e(url('plan')) ?>\""), 'the content page module has the dates form');
ok(str_contains($src('views/home.php'), "url('plan?cta=home_plan')"), 'the front page leads with the trip');
ok(!str_contains($src('app/plan_first.php'), 'INSERT INTO trips'), 'plan_first never writes a trip itself; the one row writer does');
ok(str_contains($src('app/controllers.php'), "WHERE u.id = ? AND u.status = 'active'\", [(int) \$row['user_id']]);"),
   'confirming reads the account by id, so a confirm from another browser applies the held work');

echo "\nplan_first_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
