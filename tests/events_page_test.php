<?php
/**
 * The events page, and the one rule it has to keep: a date lives in exactly one place.
 *
 * The page is built from RMT_ACQ_WINDOWS, the same list the campaign links are built from, so the
 * page cannot drift from the campaign. This test exists mostly to stop somebody adding a second
 * list later, and to stop the page turning into a directory of invented events.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
/* Source level only: this asserts the shape of the page and the list behind it, which needs no
   database and therefore cannot be made to pass by a fixture that does not match production. */
require BASE_PATH . '/app/acquisition.php';

$pass = 0; $fail = 0;
function ok(bool $cond, string $what): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  [PASS] $what\n"; }
    else       { $fail++; echo "  [FAIL] $what\n"; }
}

$view = (string) file_get_contents(BASE_PATH . '/views/events.php');
$acq  = (string) file_get_contents(BASE_PATH . '/app/acquisition.php');
$ctrl = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
$idx  = (string) file_get_contents(BASE_PATH . '/public/index.php');

echo "\n-- one list, one page --\n";
ok(str_contains($acq, 'function rmt_acq_upcoming_events'), 'the page has a source of events');
ok(str_contains($acq, 'rmt_acq_window($campaign)'),
   'and it reads the campaign windows rather than a second list');
ok(!preg_match('/const RMT_EVENTS|\$events = \[\s*\[/', $view),
   'the view does not hold a list of its own');
ok(str_contains($ctrl, 'function events_index'), 'there is a controller');
ok(str_contains($idx, "'#^/events\$#',                    'events_index'"), 'and exactly one route');
ok(substr_count($idx, 'events_index') === 1, 'one route, not a page per event');

echo "\n-- it cannot invent anything --\n";
ok(!preg_match('/\d+\s+(travelers|people)\s+going/i', $view), 'no count of travelers is asserted');
ok(str_contains($view, 'Nothing here counts anybody'), 'and it says so');
ok(!str_contains($view, 'Trending'), 'nothing is called trending on a site this quiet');

echo "\n-- every window it can show is real --\n";
foreach (RMT_ACQ_WINDOWS as $campaign => $w) {
    ok(isset(RMT_ACQ_WINDOW_WHY[$campaign]) && trim(RMT_ACQ_WINDOW_WHY[$campaign]) !== '',
       "$campaign says why matching matters there");
    ok($w['from'] <= $w['to'], "$campaign runs forwards");
}

echo "\n-- it is reachable --\n";
foreach (['views/layout/header.php' => 'the header', 'views/layout/footer.php' => 'the footer',
          'views/explore.php' => 'explore'] as $file => $where) {
    ok(str_contains((string) file_get_contents(BASE_PATH . '/' . $file), "url('events')"),
       "linked from $where");
}
ok(str_contains((string) file_get_contents(BASE_PATH . '/app/sitemap.php'), "'/events'"),
   'and listed in the sitemap');

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL EVENTS PAGE TESTS PASS ({$pass})\n";
