<?php
/**
 * "Happening on RuinMyTrip" (app/live_activity.php), the buddy "looking for" filter, the named
 * reply emails and the title experiment guard.
 *
 * What must hold:
 *   - real rows come first; our own content only tops the block up, and every such card is marked
 *     editorial and bylined as RuinMyTrip, never as a member.
 *   - the block never shows the viewer their own rows.
 *   - "a travel companion" returns only buddy posts; "meet up casually" only trips and locals.
 *   - a reply email names the thing it is about.
 *   - the nine title test cities do not get the new city strip before the read on 2026-09-29.
 *
 *   php tests/live_activity_test.php   (runs against database/dev.sqlite, read only)
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

$items = rmt_live_activity(null, 8);
ok(count($items) > 0 && count($items) <= 8, 'the block has something and respects its limit');
$seenOurs = false; $orderOk = true;
foreach ($items as $it) {
    foreach (['kind', 'label', 'title', 'href', 'who', 'editorial'] as $k) if (!array_key_exists($k, $it)) $orderOk = false;
    if ($it['editorial']) $seenOurs = true;
    elseif ($seenOurs) $orderOk = false;                       // a real row after an editorial one
}
ok($orderOk, 'every card is complete, and real rows always come before ours');
$ours = array_filter($items, static fn($i) => $i['editorial']);
ok(!$ours || !array_filter($ours, static fn($i) => !str_starts_with((string) $i['who'], 'RuinMyTrip')),
   'everything we wrote is bylined RuinMyTrip');
ok(!array_filter($ours, static fn($i) => !in_array($i['kind'], ['warning', 'prompt'], true)),
   'our content is only research lines and team questions, never a trip, buddy post or review');

$someone = q_one("SELECT t.user_id FROM trips t WHERE t.status = 'published' AND COALESCE(t.visibility,'public') = 'public'
                   AND t.date_to >= ? ORDER BY t.date_from LIMIT 1", [date('Y-m-d')]);
if ($someone) {
    $mine = q_all("SELECT id FROM trips WHERE user_id = ?", [(int) $someone['user_id']]);
    $ids = array_map(static fn($r) => url('trip/' . (int) $r['id']), $mine);
    $leak = array_filter(rmt_live_activity((int) $someone['user_id'], 12),
        static fn($i) => $i['kind'] === 'trip' && array_filter($ids, static fn($p) => str_starts_with((string) $i['href'], $p . '/')));
    ok(!$leak, 'the viewer is not shown their own trips');
}

$lis = q_one('SELECT id FROM destinations ORDER BY id LIMIT 1');
$city = rmt_live_activity(null, 6, (int) $lis['id']);
ok(count($city) <= 6, 'a city scoped block respects its limit');

// Interleave: a busy lane cannot bury the others.
$lanes = ['trip' => array_fill(0, 5, ['k' => 't']), 'question' => [['k' => 'q']], 'buddy' => [['k' => 'b']]];
$mix = rmt_live_interleave($lanes, 4);
ok(array_column($mix, 'k') === ['t', 'q', 'b', 't'], 'lanes take turns');

// Buddy "looking for".
$f = rmt_buddy_filters(['want' => 'companion']);
ok($f['want'] === 'companion' && rmt_buddy_filters_active($f), 'companion is a filter');
ok(rmt_buddy_filters(['want' => 'nonsense'])['want'] === '', 'unknown values are ignored');
foreach (['companion' => ['post'], 'meet' => ['trip', 'local']] as $w => $kinds) {
    $r = rmt_buddy_search(rmt_buddy_filters(['want' => $w]), null, 60);
    ok(!array_filter($r['cards'], static fn($c) => !in_array($c['kind'], $kinds, true)), "want=$w returns only " . implode('/', $kinds));
}

// Named reply emails.
[$s, $l] = rmt_comment_email_words('review', 1, 'ana');
ok($s === 'Somebody commented on your review' && str_contains($l, '@ana'), 'a review comment email says review');
$post = q_one('SELECT p.id, d.name FROM posts p JOIN destinations d ON d.id = p.destination_id LIMIT 1');
if ($post) {
    [$s] = rmt_comment_email_words('post', (int) $post['id'], 'ana');
    ok($s === 'Somebody answered your question about ' . $post['name'], 'an answer to a city question names the city');
}
[$s] = rmt_comment_email_words('photo', 1, 'ana');
ok($s === 'Somebody replied to you on RuinMyTrip', 'anything else keeps the plain wording');

// The title experiment.
ok(rmt_in_title_test('milan-italy') === (date('Y-m-d') <= '2026-09-29'), 'a title test city is guarded until the read');
ok(!rmt_in_title_test('bangkok-thailand'), 'other cities are not');
ok(str_contains((string) file_get_contents(BASE_PATH . '/views/_city_community.php'), '!rmt_in_title_test('), 'the city strip checks the guard');

// The by channel funnel always lists the channels we are working on, even at zero.
$bs = rmt_source_funnel(0);
$srcs = array_column($bs, 'source');
ok(!array_diff(['search', 'facebook', 'instagram', 'tiktok', 'reddit', 'direct', 'referral'], $srcs), 'every working channel has a row');
ok(!array_filter($bs, static fn($r) => $r['signup_completed'] > $r['landed'] + $r['signup_started'] + 1000), 'rows are counts');
ok(isset(rmt_growth_scorecard(7)['by_source']), 'the scorecard carries the by channel table');

// Social landing (app/social_landing.php) and the content bank it reads.
$bank = rmt_social_bank();
ok(!empty($bank['posts']) && !empty($bank['calendar']['days']), 'the content bank and its calendar load');
ok(rmt_social_post_of_day('2026-09-28')['id'] === $bank['calendar']['days'][0], 'day one of the calendar is the first post');
ok(rmt_social_post_of_day('2026-09-27') === null, 'nothing before the calendar starts');
ok(rmt_social_post_of_day('2027-01-01')['id'] === end($bank['calendar']['days']), 'after it ends, the last post');
ok(str_ends_with(rmt_social_answer_url(['link_path' => '/ruined']), '/ruined#ruined-text'), 'a trap post is answered on /ruined');
ok(str_ends_with(rmt_social_answer_url(['link_path' => '/d/bangkok-thailand']), '/d/bangkok-thailand#city-ask'), 'a city post is answered in the city');
ok(str_contains(rmt_social_answer_url(['link_path' => '/travel-buddies/japan']), 'plan?buddy=1'), 'a buddy post opens the buddy form');
ok(rmt_social_question(['facebook' => 'Be honest: which city? And which one?']) === 'Be honest: which city?', 'the banner shows the first question');
$bad = [];
foreach ($bank['posts'] as $p) {
    $all = implode(' ', array_merge([$p['facebook'], $p['caption']], $p['slides']));
    if (preg_match('/[\x{2013}\x{2014}]| - /u', $all)) $bad[] = $p['id'];
}
ok(!$bad, 'no dashes anywhere in the social copy' . ($bad ? ': ' . implode(',', $bad) : ''));
ok(count(array_unique($bank['calendar']['days'])) === count($bank['calendar']['days']), 'no post repeats in the calendar');

echo "\nlive_activity_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
