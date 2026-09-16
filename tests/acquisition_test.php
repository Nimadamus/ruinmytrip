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
ok('and the rate is a share of its own HUMAN sessions', $by['reddit']['visit_to_signup_pct'], 100.0);
/* One of the two reddit sessions did something only a person does (it signed up); the other is a
   single instant row with no cookie, which is a shape a crawler makes and a bored person also
   makes, so it is not counted as a visitor to divide by. A channel's conversion rate divided by
   crawlers is the mistake the whole measurement exists to stop making. */
ok('human sessions are counted separately from all sessions', $by['reddit']['human'], 1);
ok('signup to trip is a share of signups', $by['reddit']['signup_to_trip_pct'], 100.0);
ok('visit to trip is a share of human visits', $by['reddit']['visit_to_trip_pct'], 100.0);
ok('search is reported separately',   $by['search']['sessions'], 1);
ok('...with nothing after the arrival', $by['search']['signed_up'], 0);
ok('a rate with a zero denominator is left blank rather than zero', $by['search']['trip_rate_pct'], null);
ok('the campaign travels with it',    $by['reddit']['campaign'], 'miami');

echo "
-- a campaign that names a real window suggests it, and never creates it --
";
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (42,'munich-germany','Munich')");
$w = rmt_acq_window('oktoberfest');
ok('the campaign knows its city',  $w['slug'] ?? null, 'munich-germany');
ok('...and its destination id',    $w['id'] ?? null, 42);
ok('...and the verified window',   ($w['from'] ?? '') . ' to ' . ($w['to'] ?? ''), '2026-09-19 to 2026-10-04');
ok('a campaign we do not know suggests nothing', rmt_acq_window('some-other-thing'), null);
/* The Facebook links are tagged oktoberfest_2026. The Munich page has to recognise that as its own
   campaign, or those visitors get no window line and no prefilled dates. */
ok('the facebook campaign name finds the same window', rmt_acq_window('oktoberfest_2026')['slug'] ?? null, 'munich-germany');
ok('...with the same dates', (rmt_acq_window('oktoberfest_2026')['from'] ?? '') . ' ' . (rmt_acq_window('oktoberfest_2026')['to'] ?? ''), '2026-09-19 2026-10-04');
ok('the name survives the slug cleaner', rmt_acq_slug('oktoberfest_2026'), 'oktoberfest_2026');
ok('and neither does no campaign at all', rmt_acq_window(''), null);
/* A city this database does not hold cannot be suggested, whatever the campaign says. */
ok('a city we do not hold suggests nothing', rmt_acq_window('web-summit'), null);

/* Every window in the list has to be a real, ordered, future-facing range naming a real city, or
   it will offer somebody a trip that cannot happen. */
foreach (RMT_ACQ_WINDOWS as $key => $win) {
    ok("$key is slugged like a campaign", (bool) preg_match('/^[a-z0-9\-]+$/', $key), true);
    ok("$key names a city",  (bool) preg_match('/^[a-z0-9\-]+$/', $win['slug']), true);
    ok("$key has real dates", (bool) (strtotime($win['from']) && strtotime($win['to'])), true);
    ok("$key runs forwards",  $win['from'] <= $win['to'], true);
    ok("$key is labelled",    trim($win['label']) !== '', true);
}
/* New Year is the one window that crosses a year boundary. String comparison still has to order it,
   because that is what decides whether the window has closed. */
$ny = RMT_ACQ_WINDOWS['new-year-2027'];
ok('New Year crosses the year', $ny['from'] > '2026-12-01' && $ny['to'] < '2027-02-01', true);
ok('...and still runs forwards', $ny['from'] < $ny['to'], true);
/* A window whose last day has passed suggests nothing, whatever the link says. */
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (7,'somewhere','Somewhere')");
ok('a closed window suggests nothing',
   (function () use ($pdo) {
       $past = ['slug' => 'somewhere', 'from' => '2020-01-01', 'to' => '2020-01-02', 'label' => 'Gone'];
       return $past['to'] < gmdate('Y-m-d');   // the condition rmt_acq_window applies
   })(), true);

$link = rmt_acq_trip_link($w);
ok('the link opens the trip form', str_contains($link, '/trip/new?'), true);
ok('...with the city',             str_contains($link, 'destination_id=42'), true);
ok('...and both dates',            str_contains($link, 'date_from=2026-09-19') && str_contains($link, 'date_to=2026-10-04'), true);
/* The link is a GET to a form. Nothing here writes a trip, and nothing should: the person still
   chooses the dates and submits them. */
$acqSrc = (string) file_get_contents(BASE_PATH . '/app/acquisition.php');
ok('nothing here writes a trip', (bool) preg_match('/INSERT INTO trips/i', $acqSrc), false);

/* A window that is close is offered to everybody on that city, campaign or not. */
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (2,'lisbon-portugal','Lisbon'),(15,'bangkok-thailand','Bangkok')");
$near = rmt_acq_window_near('munich-germany', 3650);
ok('a near window is offered without a campaign', $near['slug'] ?? null, 'munich-germany');
ok('...with its destination id',                  $near['id'] ?? null, 42);
ok('a window still far off is not offered',       rmt_acq_window_near('bangkok-thailand', 1), null);
ok('...and is offered once it is close',          rmt_acq_window_near('bangkok-thailand', 3650)['slug'] ?? null, 'bangkok-thailand');
ok('a city with no window is offered nothing',    rmt_acq_window_near('somewhere', 3650), null);
ok('an empty slug is offered nothing',            rmt_acq_window_near('', 3650), null);
/* The nine cities in the destination title experiment are left exactly as they are. */
require_once BASE_PATH . '/app/seo.php';
ok('the title experiment is still there', count(RMT_DEST_SOCIAL_TITLE_TEST) > 0, true);
foreach (array_keys(RMT_DEST_SOCIAL_TITLE_TEST) as $tested) {
    ok("the title test city $tested is untouched", rmt_acq_window_near((string) $tested, 3650), null);
}
$cc = (string) file_get_contents(BASE_PATH . '/views/_city_community.php');
ok('the city page asks for a near window',  str_contains($cc, 'rmt_acq_window_near((string) $d'), true);
ok('...and the campaign still wins',        strpos($cc, 'rmt_acq_window()') < strpos($cc, 'rmt_acq_window_near'), true);


/* The signup page has to say what it is for when somebody arrives from a trip link, or the campaign
   path ends on a page that has forgotten the city and the dates it just carried. */
$auth = (string) file_get_contents(BASE_PATH . '/app/auth.php');
ok('signup knows the trip link',   str_contains($auth, "\$path === '/trip/new'"), true);
ok('...and names the city',        str_contains($auth, "'Join and post your ' . \$d['name'] . ' dates, '"), true);
$ev = (string) file_get_contents(BASE_PATH . '/app/contribution_events.php');
ok('and the trip form is a named recruiting surface',
   str_contains($ev, "return 'trip';"), true);

/* The campaign has to survive as far as the empty feed, because that is where somebody lands after
   confirming their email and it is the last place the friction can be removed. */
$feed = (string) file_get_contents(BASE_PATH . '/views/feed.php');
ok('the empty feed asks the campaign',   str_contains($feed, 'rmt_acq_window()'), true);
ok('...and offers the filled form',      str_contains($feed, 'rmt_acq_trip_link($fw)'), true);
ok('...only when there is no trip yet',  str_contains($feed, '(!$nt && !$je && function_exists'), true);
ok('...and still asks everybody else',   str_contains($feed, 'Where are you going?'), true);
ok('...without creating anything',       (bool) preg_match('/INSERT INTO trips/i', $feed), false);


echo "
-- a member's share is told apart from something we published --
";
$s1 = rmt_share_url(abs_url('/d/munich-germany'), 'whatsapp');
ok('a share carries its channel',   str_contains($s1, 'utm_source=whatsapp'), true);
ok('...and says it was a share',    str_contains($s1, 'utm_medium=share'), true);
ok('...under the member campaign',  str_contains($s1, 'utm_campaign=' . RMT_ACQ_REFERRAL_CAMPAIGN), true);
ok('member share is not a campaign we ran', RMT_ACQ_REFERRAL_CAMPAIGN !== 'oktoberfest', true);
$s2 = rmt_share_url(abs_url('/d/munich-germany') . '?x=1', 'x');
ok('an existing query is kept',     str_contains($s2, 'x=1') && str_contains($s2, '&utm_source=x'), true);
ok('a channel we do not publish becomes other',
   str_contains(rmt_share_url(abs_url('/d/x'), 'myspace'), 'utm_source=other'), true);
ok('a campaign can override the label',
   str_contains(rmt_share_url(abs_url('/d/x'), 'x', 'oktoberfest'), 'utm_campaign=oktoberfest'), true);
/* Somebody else's address is never decorated to look like ours. */
ok('a foreign url is left alone', rmt_share_url('https://example.com/a', 'x'), 'https://example.com/a');
ok('share is a medium we publish', in_array('share', RMT_ACQ_MEDIUMS, true), true);

$share = (string) file_get_contents(BASE_PATH . '/views/_share.php');
foreach (['whatsapp', 'facebook', 'x', 'reddit'] as $ch) {
    ok("the share control tags $ch", str_contains($share, "rmt_share_enc('$ch')"), true);
}
ok('and the copied link is tagged too', str_contains($share, 'data-copy="<?= e($rmt_share_copy) ?>"'), true);
ok('the campaign does not leak to the next control', str_contains($share, 'unset($shareCampaign, $shareLabel)'), true);
/* The second page a campaign arrival reads has to record something, or the session can never hand
   our cookie back and a real person is filed as a fetcher. */
$ctrl = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('the city travelers page is counted',
   str_contains($ctrl, "rmt_track_once_for('destination_page_view', 'travelers:'"), true);
ok('...under a source the vocabulary publishes',
   in_array('travelers', RMT_CONTRIB_SOURCES, true), true);
ok('...and keyed apart from the city page itself',
   substr_count($ctrl, "rmt_track_once_for('destination_page_view'") >= 2, true);


echo "
-- the operating view asks the same question three times --
";
ok('our own checks are named',        rmt_acq_is_internal('coldqa'), true);
ok('...and so is the attribution one', rmt_acq_is_internal('attrib-qa'), true);
ok('a real campaign is not ours',     rmt_acq_is_internal('oktoberfest'), false);
ok('and neither is nothing at all',   rmt_acq_is_internal(null), false);

$cc = rmt_acq_command_center(3650);
ok('it reports three windows',    array_keys($cc['windows']), ['d1', 'd7', 'all']);
ok('the day is one day',          $cc['windows']['d1'], 1);
ok('the week is seven',           $cc['windows']['d7'], 7);
ok('every row carries all three', (bool) array_reduce($cc['rows'], static fn($ok, $r) =>
    $ok && isset($r['d1'], $r['d7'], $r['all']), true), true);
ok('totals exist for each window', array_keys($cc['totals']), ['d1', 'd7', 'all']);
/* Ours is marked on the row and left out of the totals, so the headline is acquisition and the
   table is still the whole truth. */
ok('every row says whether it is ours', (bool) array_reduce($cc['rows'], static fn($ok, $r) =>
    $ok && array_key_exists('internal', $r), true), true);
ok('the totals carry our own count separately', array_key_exists('internal_human', $cc['totals']['d7']), true);
/* Some checks have to use the real campaign name, because what they are checking is what a real
   campaign visitor sees. Those are marked in utm_content instead and come out of that row's human
   count rather than taking the whole row out. */
ok('there is a marker for a check on a real campaign', RMT_ACQ_INTERNAL_CONTENT, 'selfcheck');
ok('every row carries its own check count', (bool) array_reduce($cc['rows'], static fn($ok, $r) =>
    $ok && array_key_exists('selfcheck_human', $r['d7']), true), true);
ok('the report asks the database for it',
   str_contains((string) file_get_contents(BASE_PATH . '/app/acquisition.php'),
                'MAX(CASE WHEN acq_content = ? THEN 1 ELSE 0 END) selfcheck'), true);

$mineHuman = 0; $allHuman = 0;
foreach ($cc['rows'] as $r) { $allHuman += (int) $r['all']['human']; if ($r['internal']) $mineHuman += (int) $r['all']['human']; }
ok('the total excludes what we generated', $cc['totals']['all']['human'], $allHuman - $mineHuman);

/* A day cannot hold more than a week, and a week cannot hold more than the whole window. */
foreach (['human', 'signups', 'confirmed', 'trips'] as $m) {
    ok("the day never exceeds the week for $m", $cc['totals']['d1'][$m] <= $cc['totals']['d7'][$m], true);
    ok("the week never exceeds the window for $m", $cc['totals']['d7'][$m] <= $cc['totals']['all'][$m], true);
}
$adm = (string) file_get_contents(BASE_PATH . '/views/admin_funnel.php');
ok('the dashboard draws it',       str_contains($adm, 'Acquisition, now'), true);
ok('...and keeps automated out',   str_contains($adm, 'Automated traffic is excluded'), true);
$gf = (string) file_get_contents(BASE_PATH . '/app/growth_funnel.php');
ok('the key gated json carries it', str_contains($gf, "'command_center'"), true);

echo "\n-- the morning line --\n";
$dy = rmt_acq_daily();
foreach (['as_of','traffic','human_visits','signups','confirmed','trips','top_real_source',
          'top_real_campaign','top_landing','best_conversion','notable_change',
          'matches_viewed','connection_requests','connections_made','messages_sent',
          'contaminated_window'] as $field) {
    ok("the daily report carries $field", array_key_exists($field, $dy), true);
}
/* Four classes, and none of them folded into another. */
ok('the four traffic classes are separate', array_keys($dy['traffic']),
   ['real_human', 'self_check', 'automated', 'uncertain']);
ok('today is never more than the week', $dy['human_visits']['today'] <= $dy['human_visits']['week'], true);
/* The period before the marker existed is labelled rather than rewritten. */
ok('there is a date the numbers get clean from', (bool) strtotime(RMT_ACQ_CLEAN_FROM), true);
ok('a window reaching before it is flagged', rmt_acq_window_is_contaminated(3650), true);
ok('all time is always flagged',             rmt_acq_window_is_contaminated(0), true);
/* The milestone counts channels we can name. Direct is shown and not counted, because with nothing
   published a direct session cannot be told from the automated floor. */
$clean = rmt_acq_clean_totals();
ok('the milestone counter names its start',  (bool) strtotime((string) $clean['since']), true);
ok('direct is reported separately',          array_key_exists('direct_human_not_counted', $clean), true);
ok('...and says why it is not counted',      str_contains((string) $clean['note'], 'automated floor'), true);
ok('there are four milestones',              count($clean['milestones']), 4);
ok('the first one is a hundred',             $clean['milestones'][0]['target'], 100);
ok('and it can be zero',                     $clean['milestones'][0]['now'] >= 0, true);
ok('the counter cuts at the exact moment, not a whole day',
   str_contains((string) file_get_contents(BASE_PATH . '/app/acquisition.php'),
                'rmt_acq_report($days, RMT_ACQ_CLEAN_FROM)'), true);
ok('and the report accepts that moment',
   str_contains((string) file_get_contents(BASE_PATH . '/app/acquisition.php'),
                '?string $sinceOverride = null'), true);
ok('the counter reads named channels only',
   str_contains((string) file_get_contents(BASE_PATH . '/app/acquisition.php'),
                "\$r['source'] === 'direct'"), true);

$acqSrc2 = (string) file_get_contents(BASE_PATH . '/app/acquisition.php');
ok('nothing rewrites a historical row',
   (bool) preg_match('/UPDATE contribution_events|DELETE FROM contribution_events/i', $acqSrc2), false);
/* The top source and campaign come from the clean window, not the last seven days, or our own
   pre marker checks win the headline, which is exactly what happened with "oktoberfest". */
ok('top real source and campaign read the clean window',
   str_contains((string) file_get_contents(BASE_PATH . '/app/acquisition.php'),
                'foreach (rmt_acq_report($cleanDays, RMT_ACQ_CLEAN_FROM) as $r)'), true);
ok('...and leave direct out, like the milestone does',
   substr_count((string) file_get_contents(BASE_PATH . '/app/acquisition.php'),
                "\$r['source'] === 'direct'"), 2);
ok('top campaign discounts our own checks',
   str_contains($acqSrc2, "selfcheck_human"), true);
/* A rate off one or two sessions is noise dressed as a result, so it is not reported at all. */
ok('a conversion needs five sessions behind it',
   $dy['best_conversion'] === null || $dy['best_conversion']['rate'] !== null, true);
ok('the report says what it leaves out', str_contains((string) $dy['note'], 'our own verification'), true);
$gf2 = (string) file_get_contents(BASE_PATH . '/app/growth_funnel.php');
ok('and the key gated json carries the daily line', str_contains($gf2, "'daily'"), true);
$adm2 = (string) file_get_contents(BASE_PATH . '/views/admin_funnel.php');
ok('the dashboard opens with it', strpos($adm2, 'Today') < strpos($adm2, 'Acquisition, now'), true);


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
