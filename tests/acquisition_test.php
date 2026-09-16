<?php
/**
 * Where a visit came from, and what this is not allowed to learn while finding out.
 *
 * Two things are being guarded, and the second matters more than the first.
 *
 * THE MEASUREMENT. First touch wins: somebody who lands from a Reddit post, reads three pages and
 * signs up on the fourth is a Reddit signup, not a direct one, and a later arrival never steals the
 * credit. A channel is one word from a list this code owns, so nothing a stranger appends to a URL
 * can invent a category or arrive in a report as somebody's sentence.
 *
 * THE PROMISE. This table has never held an address, an agent or a referrer, and adding attribution
 * is exactly the change that would quietly break that: a referrer IS a URL, carrying a path, a
 * query and sometimes a person's own words. So the referring HOST is read for the length of one
 * comparison, mapped to a word, and dropped. The tests below assert that no referrer string can
 * reach the database and that the campaign fields cannot carry anything but a label.
 *
 *   php tests/acquisition_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
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
require BASE_PATH . '/app/acquisition.php';

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-52s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}
function is_logged_in(): bool { return false; }

$pdo = db();
foreach (['052_contribution_events', '090_event_visitor', '093_event_cookied', '094_acquisition_source'] as $m) {
    $pdo->exec(file_get_contents(BASE_PATH . "/database/migrations/$m.sqlite.sql"));
}

/** A fresh request: no held channel anywhere, and whatever this one carries. */
$request = static function (array $get = [], ?string $referer = null): array {
    unset($GLOBALS['_rmt_acq_resolved']);
    $_GET = $get; $_POST = []; $_REQUEST = $get;
    $_SERVER['HTTP_REFERER'] = $referer ?? '';
    $_SESSION = []; $_COOKIE = [];
    return rmt_acq_from_request();
};

echo "-- a channel is named by the link --\n";
ok('utm_source reddit',     $request(['utm_source' => 'reddit'])['source'], 'reddit');
ok('the short ref= form',   $request(['ref' => 'facebook'])['source'], 'facebook');
ok('case and spacing do not matter', $request(['utm_source' => '  Reddit '])['source'], 'reddit');
ok('a channel we do not publish is kept as other', $request(['utm_source' => 'some-newsletter-xyz'])['source'], 'other');
ok('nothing at all is nothing', $request([])['source'], null);

echo "\n-- or by the referring host, which is read and never kept --\n";
ok('a reddit link',    $request([], 'https://www.reddit.com/r/solotravel/comments/abc/def/')['source'], 'reddit');
ok('an old twitter host counts as x', $request([], 'https://twitter.com/someone/status/123')['source'], 'x');
ok('a t.co shortener too', $request([], 'https://t.co/abc123')['source'], 'x');
ok('google is search',  $request([], 'https://www.google.com/search?q=bangkok+travel')['source'], 'search');
ok('a site we cannot name is referral', $request([], 'https://some-travel-blog.example/post')['source'], 'referral');
ok('our own pages are not a channel', $request([], 'https://ruinmytrip.com/d/bangkok-thailand')['source'], null);

echo "\n-- the campaign is a label, not a sentence --\n";
$r = $request(['utm_source' => 'reddit', 'utm_medium' => 'comment',
               'utm_campaign' => 'Miami Art Week 2026!', 'utm_content' => 'variant_a']);
ok('the medium comes from the closed list', $r['medium'], 'comment');
ok('a campaign is slugged',  $r['campaign'], 'miami-art-week-2026');
ok('the content label survives', $r['content'], 'variant_a');
$r2 = $request(['utm_source' => 'x', 'utm_medium' => 'whatever-this-is']);
ok('a medium we do not publish is other', $r2['medium'], 'other');
$long = $request(['utm_source' => 'reddit', 'utm_campaign' => str_repeat('a', 200)]);
ok('a campaign cannot be an essay', mb_strlen((string) $long['campaign']), 40);
$nasty = $request(['utm_source' => 'reddit', 'utm_campaign' => "<script>alert('x')</script>"]);
ok('markup cannot survive the slug', (bool) preg_match('/^[a-z0-9_\-]+$/', (string) $nasty['campaign']), true);

echo "\n-- first touch wins, and is held --\n";
/* The whole point: the post that did the work keeps the credit, even though the signup happens
   three pages later with no parameters on the URL at all. */
unset($GLOBALS['_rmt_acq_resolved']);
$_SESSION = ['_acq' => ['source' => 'reddit', 'medium' => 'comment', 'campaign' => 'miami-art-week', 'content' => null]];
$_GET = []; $_SERVER['HTTP_REFERER'] = '';
$held = rmt_acq_current();
ok('the held channel is used when the URL says nothing', $held['source'], 'reddit');
ok('...with its campaign', $held['campaign'], 'miami-art-week');

echo "\n-- the report counts sessions, and the funnel between them --\n";
$pdo->exec('DELETE FROM contribution_events');
$plant = static function (string $j, string $event, string $src, ?string $camp = null) use ($pdo): void {
    $st = $pdo->prepare("INSERT INTO contribution_events (event, journey, visitor, acq_source, acq_campaign, is_authed, created_at)
                         VALUES (?,?,?,?,?,0,?)");
    $st->execute([$event, $j, 'v' . substr(md5($j), 0, 15), $src, $camp, date('Y-m-d H:i:s')]);
};
// Reddit: two arrivals, one of which signed up, confirmed and made a trip.
$plant('r1', 'landing_view', 'reddit', 'miami');
$plant('r2', 'landing_view', 'reddit', 'miami');
$plant('r2', 'join_submit', 'reddit', 'miami');
$plant('r2', 'join_created', 'reddit', 'miami');
$plant('r2', 'join_confirmed', 'reddit', 'miami');
$plant('r2', 'trip_created', 'reddit', 'miami');
// Search: one arrival, nothing after it.
$plant('s1', 'landing_view', 'search');

$rep = rmt_acq_report(0);
$by = [];
foreach ($rep as $row) $by[$row['source']] = $row;
ok('reddit is reported',              isset($by['reddit']), true);
ok('two reddit sessions',             $by['reddit']['sessions'], 2);
ok('one of them signed up',           $by['reddit']['signed_up'], 1);
ok('one confirmed',                   $by['reddit']['confirmed'], 1);
ok('one made a trip',                 $by['reddit']['trips'], 1);
ok('and the rate is a share of its own sessions', $by['reddit']['signup_rate_pct'], 50.0);
ok('search is reported separately',   $by['search']['sessions'], 1);
ok('...with nothing after the arrival', $by['search']['signed_up'], 0);
ok('a rate with a zero denominator is left blank rather than zero', $by['search']['trip_rate_pct'], null);
ok('the campaign travels with it',    $by['reddit']['campaign'], 'miami');

echo "\n-- what attribution is not allowed to store --\n";
$src = (string) file_get_contents(BASE_PATH . '/app/acquisition.php');
$events = (string) file_get_contents(BASE_PATH . '/app/contribution_events.php');
preg_match('/INSERT INTO contribution_events(.*?)VALUES/s', $events, $m);
$insert = strtolower($m[1] ?? '');
ok('the insert statement is where it was', $insert !== '', true);
foreach (['referer', 'referrer', 'user_agent', 'ip', 'url', 'query'] as $forbidden) {
    ok("no $forbidden column is written", str_contains($insert, $forbidden), false);
}
ok('the referrer is only ever parsed for its host',
   str_contains($src, "parse_url(\$ref, PHP_URL_HOST)"), true);
ok('and the full referrer is never handed to the tracker',
   (bool) preg_match('/rmt_track\([^)]*HTTP_REFERER/', $src . $events), false);
ok('every stored source comes from the closed list',
   str_contains($src, 'in_array($src, RMT_ACQ_SOURCES, true)'), true);
$cols = array_column($pdo->query('PRAGMA table_info(contribution_events)')->fetchAll(), 'name');
foreach (['referer', 'referrer', 'user_agent', 'ip'] as $forbidden) {
    ok("there is still no $forbidden column at all", in_array($forbidden, $cols, true), false);
}

echo "\n-- and measuring still cannot break the site --\n";
$pdo->exec('DROP TABLE contribution_events');
$threw = false;
try { rmt_track('landing_view'); } catch (Throwable $e) { $threw = true; }
ok('a write against a missing table does not throw', $threw, false);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL ACQUISITION TESTS PASS ({$pass})\n";
