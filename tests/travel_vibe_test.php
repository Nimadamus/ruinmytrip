<?php
/**
 * Travel vibe: budget and pace on a profile and on a buddy post, and searching by them
 * (migration 124, app/vibe.php), plus the guidelines and safety words that describe the features.
 *
 * Optional and self-declared: unset means not said, anything off the list is stored as not said,
 * and nothing is displayed for a member who left them blank.
 *
 *   php tests/travel_vibe_test.php
 */
putenv('DATABASE_URL');
putenv('RESEND_API_KEY');
putenv('RMT_TRUST_MODE');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }

$pdo = db(); $pdo->beginTransaction();
$now = date('Y-m-d H:i:s');
$dest = q_one("SELECT * FROM destinations ORDER BY id DESC LIMIT 1");
$did = (int) $dest['id'];
foreach (['vbme', 'vbshoe', 'vblux', 'vbslow', 'vbblank'] as $u) {
    q_run("INSERT INTO users (username,email,password_hash,role,status,birthdate,email_verified_at,created_at) VALUES (?,?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', 'member', 'active', '1990-01-01', $now, $now]);
    $uid = (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];
    if (!q_one('SELECT 1 FROM profiles WHERE user_id=?', [$uid])) q_run('INSERT INTO profiles (user_id, display_name) VALUES (?,?)', [$uid, ucfirst($u)]);
}
$id = fn(string $u): int => (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];

/* ---- a member's own answers ---- */
ok(rmt_vibe_for($id('vbme')) === ['budget_level' => null, 'travel_pace' => null], 'unset means not said');
rmt_vibe_save($id('vbme'), ['budget_level' => 'budget', 'travel_pace' => 'slow']);
ok(rmt_vibe_for($id('vbme')) === ['budget_level' => 'budget', 'travel_pace' => 'slow'], 'a budget and a pace are saved');
rmt_vibe_save($id('vblux'), ['budget_level' => 'billionaire', 'travel_pace' => '<script>']);
ok(rmt_vibe_for($id('vblux')) === ['budget_level' => null, 'travel_pace' => null], 'anything off the list is stored as not said');
rmt_vibe_save($id('vbme'), ['budget_level' => '', 'travel_pace' => 'slow']);
ok(rmt_vibe_for($id('vbme'))['budget_level'] === null, 'an answer can be taken back');
rmt_vibe_save($id('vbme'), ['budget_level' => 'budget', 'travel_pace' => 'slow']);
ok(rmt_vibe_labels('budget', 'slow') === ['Shoestring budget', 'Slow and easy'] && rmt_vibe_labels('any', null) === [] && rmt_vibe_labels(null, null) === [],
   'the words are said only for what was said; "any budget" is not a statement');
ok(rmt_vibe_shared(rmt_vibe_for($id('vbme')), 'budget', 'packed') === ['Same budget as you']
   && rmt_vibe_shared(['budget_level' => null, 'travel_pace' => null], 'budget', 'slow') === [], 'what two travelers share is said only when both said it');

/* ---- on a buddy post ---- */
$y = (int) date('Y') + 1;
$mk = static function (int $uid, array $over) use ($did, $y): int {
    $v = rmt_buddy_validate($over + ['trip_type' => 'trip', 'title' => 'Vibe test trip', 'where_text' => '', 'destination_id' => (string) $did,
         'date_from' => "$y-06-01", 'date_to' => "$y-06-08", 'spots' => '1', 'budget' => 'any',
         'description' => 'Would love someone to explore museums and cafes with.', 'safety_ack' => '1']);
    if (!$v['ok']) throw new RuntimeException(implode(' ', $v['errors']));
    return rmt_buddy_insert($uid, $v['data']);
};
$shoe = $mk($id('vbshoe'), ['budget' => 'budget', 'travel_pace' => 'packed']);
$lux  = $mk($id('vblux'),  ['budget' => 'luxury', 'travel_pace' => 'slow']);
$slow = $mk($id('vbslow'), ['budget' => 'any', 'travel_pace' => '']);
$bad  = $mk($id('vbblank'), ['budget' => 'any', 'travel_pace' => 'warp speed']);
rmt_vibe_save($id('vbslow'), ['budget_level' => 'mid', 'travel_pace' => 'slow']);
ok(q_one('SELECT travel_pace FROM buddy_posts WHERE id=?', [$shoe])['travel_pace'] === 'packed', 'a post carries its pace');
ok(q_one('SELECT travel_pace FROM buddy_posts WHERE id=?', [$bad])['travel_pace'] === null, 'a pace off the list is not stored');

$find = static function (array $g) use ($did, $dest, $id): array {
    $f = rmt_buddy_filters($g + ['dest' => (string) $dest['slug']]);
    $ids = [];
    foreach (rmt_buddy_search($f, ['id' => $id('vbme')])['cards'] as $c) if ($c['kind'] === 'post' && $c['title'] === 'Vibe test trip') $ids[] = (int) $c['id'];
    sort($ids); return $ids;
};
$all = [$shoe, $lux, $slow, $bad]; sort($all);
ok($find([]) === $all, 'with no vibe filter every post shows');
ok($find(['budget' => 'budget']) === [$shoe], 'a budget search finds the shoestring trip');
ok($find(['budget' => 'luxury']) === [$lux], 'and the luxury one');
ok($find(['budget' => 'mid']) === [$slow], 'a post that said "any budget" is matched on what its author usually spends');
$sl = [$lux, $slow]; sort($sl);
ok($find(['pace' => 'slow']) === $sl, 'a pace search matches the post, or the author when the post did not say');
ok($find(['pace' => 'packed']) === [$shoe] && $find(['budget' => 'luxury', 'pace' => 'packed']) === [], 'filters combine');
ok($find(['budget' => 'nonsense', 'pace' => 'x']) === $all, 'a filter value off the list is ignored, not an error');
$f = rmt_buddy_filters(['budget' => 'mid', 'pace' => 'slow']);
ok(rmt_buddy_filters_active($f) && str_contains(rmt_buddy_query($f), 'budget=mid') && str_contains(rmt_buddy_query($f), 'pace=slow'), 'the filters survive in links');
$card = array_values(array_filter(rmt_buddy_search(rmt_buddy_filters(['dest' => (string) $dest['slug']]), ['id' => $id('vbme')])['cards'], static fn(array $c): bool => (int) $c['id'] === $shoe && $c['kind'] === 'post'))[0];
ok($card['budget'] === 'budget' && $card['pace'] === 'packed', 'a card carries the trip\'s budget and pace');

/* ---- the pages ---- */
ini_set('display_errors', '0');
$_SESSION['uid'] = $id('vbme'); $_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['dest' => (string) $dest['slug']];
ob_start(); buddies_index([]); $h = (string) ob_get_clean();
ok(str_contains($h, 'name="budget"') && str_contains($h, 'name="pace"') && str_contains($h, 'Slow and easy'), 'the search form offers budget and pace');
ok(str_contains($h, 'Shoestring budget, Packed days') && str_contains($h, 'same budget as you'), 'a card says the trip\'s vibe and what it shares with the reader');
$_GET = [];
ob_start(); buddy_show(['id' => $lux]); $h = (string) ob_get_clean();
ok(str_contains($h, 'Luxury') && str_contains($h, 'Slow and easy'), 'the trip page shows budget and pace');
ob_start(); buddy_new_form([]); $h = (string) ob_get_clean();
ok(str_contains($h, 'name="travel_pace"'), 'the post form asks for a pace');
$view = static function (string $name): string {
    ob_start(); include dirname(__DIR__) . '/views/' . $name . '.php'; return (string) ob_get_clean();
};
$pe = (string) file_get_contents(dirname(__DIR__) . '/views/profile_edit.php');
ok(str_contains($pe, 'name="budget_level"') && str_contains($pe, 'name="travel_pace"') && str_contains($pe, 'Rather not say'), 'the profile form asks for both, optionally');
ok(str_contains((string) file_get_contents(dirname(__DIR__) . '/views/profile.php'), 'rmt_vibe_labels('), 'the profile shows them when set');

/* ---- the words that describe the features ---- */
$GLOBALS['rmt_trust_mode'] = 'off';
$g = $view('legal/guidelines');
ok(str_contains($g, 'Travel partners, check-ins and city rooms') && str_contains($g, 'Never ask another traveler for money'), 'the guidelines cover travel partners and city rooms');
ok(!str_contains($g, 'Vouches, flags and restrictions'), 'and say nothing about vouching while it is off for members');
$GLOBALS['rmt_trust_mode'] = 'monitor';
$g = $view('legal/guidelines');
ok(str_contains($g, 'Vouches, flags and restrictions') && str_contains($g, 'Flags never restrict an account by themselves') && str_contains($g, 'you can appeal'), 'once it is on they explain vouches, flags, review and appeal');
$s = $view('legal/safety');
ok(str_contains($s, 'Check-ins and city rooms') && str_contains($s, 'Never your hotel'), 'the safety page covers check-ins');
foreach (['legal/guidelines' => $g, 'legal/safety' => $s] as $n => $html) {
    $added = substr($html, (int) strpos($html, $n === 'legal/safety' ? 'id="check-ins"' : 'id="travel-partners"'), 2600);
    ok(!preg_match('/[\x{2013}\x{2014}]| - /u', strip_tags($added)), "the new words on $n have no dashes");
}

$pdo->rollBack();
echo $fails ? "\ntravel_vibe_test: $fails FAILED\n" : "\ntravel_vibe_test: all passed\n";
exit($fails ? 1 : 0);
