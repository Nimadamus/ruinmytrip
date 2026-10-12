<?php
/**
 * The per-channel report (rmt_acq_report) with the proof module loaded: a session counts as a
 * person only when /admin/proof calls its browser a verified human, so the two pages agree.
 *
 * The case that made this necessary (production, 2026-10-11): 2,568 "human" direct sessions on the
 * old rule against 39 verified humans, because crawlers send human_interaction too, and 6,705
 * "review form starts" from crawlers that fetch /review/new.
 *
 *   php tests/acquisition_proof_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/contribution_events.php';
require BASE_PATH . '/app/growth_scorecard.php';
require BASE_PATH . '/app/kpi.php';
require BASE_PATH . '/app/proof.php';
require BASE_PATH . '/app/acquisition.php';

$fails = 0;
function ok(string $name, $got, $expect): void {
    global $fails;
    if ($got === $expect) { echo "PASS  $name\n"; return; }
    $fails++;
    echo "FAIL  $name -- expected " . var_export($expect, true) . ' got ' . var_export($got, true) . "\n";
}

$pdo = db();
foreach (['052_contribution_events', '090_event_visitor', '093_event_cookied', '094_acquisition_source', '102_plan_first'] as $m) {
    $pdo->exec(file_get_contents(BASE_PATH . "/database/migrations/$m.sqlite.sql"));
}
$st = $pdo->prepare("INSERT INTO contribution_events (event, journey, visitor, acq_source, acq_campaign, acq_content, detail, path, cookied, is_authed, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,0,?)");
$at = static fn(int $ago): string => gmdate('Y-m-d H:i:s', time() - $ago);
$add = static function (string $j, string $v, string $ev, int $ago, ?string $src = 'friends', ?string $detail = null, ?string $path = null, ?string $content = 'invite')
    use ($st, $at): void {
    $st->execute([$ev, $j, $v, $src, $src === null ? null : 'launch-review', $src === null ? null : $content, $detail, $path, 1, $at($ago)]);
};

// A friend: lands, taps (human_verified), opens the review form, publishes. Comes back three days later directly.
$add('a1', 'vA', 'landing_view', 4 * 86400 + 60, 'friends', null, '/map');
$add('a1', 'vA', 'human_verified', 4 * 86400 + 50);
$add('a1', 'vA', 'review_form_start', 4 * 86400 + 40, 'friends', null, '/review/new');
$add('a1', 'vA', 'review_publish_success', 4 * 86400);
$add('a2', 'vA', 'landing_view', 86400, null);
$add('a2', 'vA', 'human_verified', 86400 - 5, null);
// Another friend: lands on the map, taps, shares the map.
$add('b1', 'vB', 'map_view', 3600, 'friends', null, '/map');
$add('b1', 'vB', 'human_verified', 3590);
$add('b1', 'vB', 'cta_click', 3580, 'friends', 'map_share');
// A crawler with the invite link that sends human_interaction and opens the review form first.
$add('c1', 'vC', 'review_form_start', 7200, 'friends', null, '/review/new');
$add('c1', 'vC', 'human_interaction', 7190);
// Somebody who opened the invite and left: one page, nothing else.
$add('d1', 'vD', 'landing_view', 5000, 'friends', null, '/map');
// Our own check through the invite link: kept apart, on the old rule.
$add('e1', 'vE', 'landing_view', 4000, 'friends', null, '/map', 'selfcheck');
$add('e1', 'vE', 'human_interaction', 3990, 'friends', null, null, 'selfcheck');

$v = rmt_acq_verdicts(gmdate("Y-m-d H:i:s", time() - 30 * 86400));
ok('the proof verdicts are used when the module is loaded', is_array($v), true);
ok('the tapping friend is a verified human', $v['vA'] ?? null, 'human');
ok('the form-first crawler is a suspected bot', $v['vC'] ?? null, 'bot');
ok('one page and gone is unverified', $v['vD'] ?? null, 'unverified');

$fr = null; $self = null;
foreach (rmt_acq_report(30) as $r) {
    if ($r['source'] === 'friends' && $r['content'] === 'invite') $fr = $r;
    if ($r['source'] === 'friends' && $r['content'] === 'selfcheck') $self = $r;
}
ok('four invite sessions', $fr['sessions'] ?? null, 4);
ok('two of them people', $fr['human'] ?? null, 2);
ok('one suspected bot', $fr['suspected_bots'] ?? null, 1);
ok('one unverified', $fr['unverified'] ?? null, 1);
ok('people, bots and unverified add up to sessions', ($fr['human'] + $fr['suspected_bots'] + $fr['unverified']), $fr['sessions']);
ok('the crawler opening the form is not a review start', $fr['review_started'] ?? null, 1);
ok('the published review is counted', $fr['reviews'] ?? null, 1);
ok('the map share is counted', $fr['map_actions'] ?? null, 1);
ok('the map view is counted for the person', $fr['map_views'] ?? null, 1);
ok('the friend who came back days later is returning', $fr['returning'] ?? null, 1);
ok('two human browsers came by the invite', $fr['human_visitors'] ?? null, 2);
ok('review rate is a share of people', $fr['visit_to_review_pct'] ?? null, 50.0);
ok('our own check is still counted apart', $self['selfcheck_human'] ?? null, 1);

$proof = rmt_proof(7);
ok('the proof page counts the same two people (plus nothing else)', $proof['totals']['verified_humans'], 2);

echo $fails ? "\n$fails FAILED\n" : "\nALL ACQUISITION PROOF TESTS PASS\n";
exit($fails ? 1 : 0);
