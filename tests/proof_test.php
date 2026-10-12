<?php
/**
 * Proof metrics (app/proof.php): every browser lands in one bucket, for a reason, and the daily
 * numbers are counts over real rows.
 *
 * The cases are the traffic actually seen in production:
 *   - 2026-10-09 00:15 PDT: fifteen browsers open /review/new within two minutes and "interact".
 *   - a headless browser that sets navigator.webdriver.
 *   - a crawler that never returns our cookie.
 *   - a person who taps (human_verified), a person who submits a review, a person from before
 *     human_verified existed who scrolled and stayed, and somebody who read one page and left.
 *
 *   php tests/proof_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/growth_scorecard.php';
require BASE_PATH . '/app/kpi.php';
require BASE_PATH . '/app/proof.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$rows = [];
$add = static function (string $v, string $event, string $at, ?string $path = null, ?int $cookied = 1, string $src = '') use (&$rows): void {
    $rows[] = ['visitor' => $v, 'event' => $event, 'path' => $path, 'created_at' => $at, 'cookied' => $cookied, 'acq_source' => $src];
};

// The 2026-10-09 burst: 15 browsers, form page first, then a scroll "interaction" a few seconds later.
for ($i = 0; $i < 15; $i++) {
    $t = strtotime('2026-10-09 07:15:30') + $i * 7;
    $add("burst$i", 'review_signup_required', date('Y-m-d H:i:s', $t), null, 0, 'direct');
    $add("burst$i", 'review_form_start', date('Y-m-d H:i:s', $t), null, 0, 'direct');
    $add("burst$i", 'human_interaction', date('Y-m-d H:i:s', $t + 5), '/review/new', 0, 'direct');
}
// Six landings on one blog page inside a minute, each scrolling for 12 s: a burst on a normal page.
for ($i = 0; $i < 6; $i++) {
    $t = strtotime('2026-10-09 09:00:00') + $i * 8;
    $add("land$i", 'landing_view', date('Y-m-d H:i:s', $t), '/blog/x', 1, 'direct');
    $add("land$i", 'human_interaction', date('Y-m-d H:i:s', $t + 12), '/blog/x', 1, 'direct');
}
$add('wd', 'landing_view', '2026-10-09 10:00:00', '/p/a', 1, 'search');
$add('wd', 'automation_detected', '2026-10-09 10:00:01', '/p/a', 1, 'search');
$add('wd', 'human_verified', '2026-10-09 10:00:05', '/p/a', 1, 'search');
$add('nocookie', 'landing_view', '2026-10-09 11:00:00', '/p/a', 0, 'direct');
$add('nocookie', 'landing_view', '2026-10-09 11:00:09', '/p/b', 0, 'direct');
$add('tapper', 'landing_view', '2026-10-09 12:00:00', '/p/zeughauskeller-zurich', 1, 'search');
$add('tapper', 'human_verified', '2026-10-09 12:00:09', '/p/zeughauskeller-zurich', 1, 'search');
$add('tapper', 'review_form_start', '2026-10-09 12:00:26', null, 1, 'search');
$add('tapper', 'landing_view', '2026-10-10 12:00:00', '/p/zeughauskeller-zurich', 1, 'search');
$add('writer', 'landing_view', '2026-10-09 13:00:00', '/p/x', 1, 'facebook');
$add('writer', 'review_submit_attempt', '2026-10-09 13:03:00', null, 1, 'facebook');
$add('legacy', 'landing_view', '2026-10-09 14:00:00', '/d/lisbon', 1, 'direct');
$add('legacy', 'human_interaction', '2026-10-09 14:00:15', '/d/lisbon', 1, 'direct');
$add('quick', 'landing_view', '2026-10-09 15:00:00', '/d/rome', 1, 'direct');
$add('quick', 'human_interaction', '2026-10-09 15:00:02', '/d/rome', 1, 'direct');
$add('reader', 'landing_view', '2026-10-09 16:00:00', '/p/q', null, 'search');
usort($rows, static fn($a, $b) => strcmp($a['created_at'], $b['created_at']));

$c = rmt_proof_classify($rows);
$cls = static fn(string $v) => $c[$v]['class'] . ':' . $c[$v]['reason'];

ok('all fifteen form first browsers are bots', count(array_filter(range(0, 14), static fn($i) => $c["burst$i"]['class'] === 'bot')) === 15);
ok('and the reason is form_first', $cls('burst0') === 'bot:form_first', $cls('burst0'));
ok('six landings on one page inside a minute are a burst', $cls('land3') === 'bot:burst', $cls('land3'));
ok('a webdriver browser is a bot even after a trusted tap', $cls('wd') === 'bot:webdriver', $cls('wd'));
ok('several requests and never our cookie back is a bot', $cls('nocookie') === 'bot:no_cookie', $cls('nocookie'));
ok('a trusted tap is a verified human', $cls('tapper') === 'human:trusted_input', $cls('tapper'));
ok('pressing publish is a verified human', $cls('writer') === 'human:submitted_a_form', $cls('writer'));
ok('an old style interaction that stayed 15 s is a human', $cls('legacy') === 'human:interacted_and_stayed', $cls('legacy'));
ok('an old style interaction that left after 2 s is unverified', $c['quick']['class'] === 'unverified');
ok('one page and nothing else is unverified, not a bot', $c['reader']['class'] === 'unverified');
ok('a human seen on two days keeps both days', count($c['tapper']['days']) === 2);

// The same rows through the database, as /cron/proof reads them.
$pdo = db();
$pdo->exec("CREATE TABLE contribution_events (id INTEGER PRIMARY KEY AUTOINCREMENT, event TEXT, source TEXT, journey TEXT,
            visitor TEXT, cookied INTEGER, place_id INTEGER, destination_id INTEGER, is_authed INTEGER, reason TEXT,
            acq_source TEXT, acq_medium TEXT, acq_campaign TEXT, acq_content TEXT, path TEXT, detail TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT, created_at TEXT, email_verified_at TEXT, email TEXT)");
$pdo->exec("CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, status TEXT, created_at TEXT)");
foreach (['trips' => 'user_id', 'buddy_posts' => 'user_id'] as $tb => $col) $pdo->exec("CREATE TABLE $tb (id INTEGER PRIMARY KEY, $col INT, created_at TEXT)");
$pdo->exec("CREATE TABLE messages (id INTEGER PRIMARY KEY, sender_id INT, created_at TEXT)");
$pdo->exec("CREATE TABLE match_alerts (id INTEGER PRIMARY KEY, confirmed_at TEXT)");
$ins = $pdo->prepare('INSERT INTO contribution_events (visitor, event, path, created_at, cookied, acq_source, acq_content) VALUES (?,?,?,?,?,?,?)');
foreach ($rows as $r) $ins->execute([$r['visitor'], $r['event'], $r['path'], $r['created_at'], $r['cookied'], $r['acq_source'], '']);
$ins->execute(['ourcheck', 'human_verified', '/p/a', '2026-10-09 17:00:00', 1, 'search', 'selfcheck']);
$pdo->exec("INSERT INTO users VALUES (1,'writer1','user','active','2026-10-09 13:04:00','2026-10-09 13:10:00','w@example.com'),
                                     (2,'team_house','user','active','2026-10-09 13:05:00',NULL,'h@example.com'),
                                     (3,'unconfirmed','user','active','2026-10-09 18:00:00',NULL,'u@example.com'),
                                     (4,'qa_member','user','active','2026-10-09 19:00:00','2026-10-09 19:01:00','qa+rmtqa1@example.com')");
$pdo->exec("INSERT INTO reviews VALUES (1,1,'published','2026-10-09 13:04:30'), (2,2,'published','2026-10-09 13:06:00')");

$p = rmt_proof(3, '2026-10-10 20:00:00');
$day = []; foreach ($p['days'] as $d) $day[$d['day']] = $d;
$d9 = $day['2026-10-09'];
ok('our own checks are left out', !isset(rmt_proof_classify([['visitor' => 'x', 'event' => 'landing_view', 'path' => '/', 'created_at' => '2026-10-09 00:00:00', 'cookied' => 1, 'acq_source' => '']])['ourcheck'])
   && $p['totals']['verified_humans'] === 3, (string) $p['totals']['verified_humans']);
ok('Oct 9 has three verified humans', $d9['verified_humans'] === 3, (string) $d9['verified_humans']);
ok('Oct 9 has 23 suspected bots', $d9['suspected_bots'] === 23, (string) $d9['suspected_bots']);
ok('the review opened by bots is not counted, the human one is', $d9['review_opened'] === 1, (string) $d9['review_opened']);
ok('a submitted review is counted once', $d9['review_submitted'] === 1);
ok('the house account is not a signup', $d9['signups'] === 2 && $d9['confirmed'] === 1, json_encode([$d9['signups'], $d9['confirmed']]));
ok('the house review is not a published review', $d9['reviews_published'] === 1);
ok('the returning human is counted on Oct 10', $day['2026-10-10']['returning_humans'] === 1);
ok('search and referral humans are split', $d9['search_humans'] === 1 && $d9['referral_humans'] === 1,
   json_encode([$d9['search_humans'], $d9['referral_humans']]));
ok('the signup rate is over verified humans', $p['rates']['signup_rate_pct'] === round(100 * 2 / 3, 1), (string) $p['rates']['signup_rate_pct']);
ok('every metric has a definition', !array_diff(array_keys($p['totals']), array_keys(RMT_PROOF_DEFINITIONS)));
ok('reasons are reported', ($p['reasons']['bot']['form_first'] ?? 0) === 15 && ($p['reasons']['human']['trusted_input'] ?? 0) === 1);

echo $fails ? "\nproof_test: $fails FAILED\n" : "\nproof_test: all passed\n";
exit($fails ? 1 : 0);
