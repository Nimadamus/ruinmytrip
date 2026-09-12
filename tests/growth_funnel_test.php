<?php
/**
 * What the analytics layer is allowed to know.
 *
 * Two kinds of assertion, and the first kind matters more than the second.
 *
 * The privacy assertions are a build gate on a promise: this site measures a funnel without keeping
 * a record of any individual's behaviour. That promise is one careless column away from being false
 * at any time, and nothing about the product would visibly break on the day it stopped being true,
 * so it is asserted here rather than trusted. The event table may not learn a user id, an address,
 * an IP, a user agent or a referrer, and the derived funnel may not return a name, an id or a row
 * per person. It returns counts.
 *
 * The correctness assertions cover the part that is easy to get quietly wrong: every rate is a
 * share of a stated denominator, "came back" means a later calendar day rather than a later second,
 * and editorial accounts are never counted as converted visitors.
 *
 *   php tests/growth_funnel_test.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$growthSrc = (string) file_get_contents($root . '/app/growth_funnel.php');
$eventsSrc = (string) file_get_contents($root . '/app/contribution_events.php');

echo "-- the event table cannot identify anybody --\n";
/* The whole defence is that the INSERT has nowhere to put a person. Asserting on the statement
   itself rather than on the schema, because a column nothing writes to is harmless and a column
   something writes to is the problem. */
preg_match('/INSERT INTO contribution_events(.*?)VALUES/s', $eventsSrc, $m);
$insert = strtolower($m[1] ?? '');
ok($insert !== '', 'the insert statement is where it was');
foreach (['user_id', 'email', 'ip', 'user_agent', 'referrer', 'referer', 'username'] as $forbidden) {
    ok(!str_contains($insert, $forbidden), "no $forbidden is written with an event");
}
ok(str_contains($eventsSrc, 'is_authed'),
   'whether somebody was signed in is kept as a flag, which is not an identity');

echo "\n-- the derived funnel returns counts, never people --\n";
$f = rmt_growth_funnel(0);
$flat = [];
array_walk_recursive($f, static function ($v, $k) use (&$flat) { $flat[] = [$k, $v]; });
$leaky = array_filter($flat, static fn(array $kv) => is_string($kv[1])
    && !in_array($kv[0], ['label'], true));
ok($leaky === [], 'nothing but labels comes back as text, so no name can ride along');
foreach (['user', 'username', 'email', 'id', 'ids'] as $key) {
    ok(!array_key_exists($key, $f), "the result has no $key key");
}
ok(!preg_match('/SELECT\s+u\.(id|username|email)/i', $growthSrc),
   'and no query selects a member column, only COUNT over them');
ok(substr_count(strtolower($growthSrc), 'select count') >= 8,
   'every step is a count');

echo "\n-- the shape of the report --\n";
ok(isset($f['spine'], $f['firsts'], $f['members'], $f['visits']), 'the four parts are present');
ok(count($f['spine']) === 6, 'the spine is the six steps that matter');
foreach ($f['spine'] as $row) {
    ok(isset($row['label'], $row['n']) && is_int($row['n']), "{$row['label']}: an integer count");
}
/* Each rate is a share of the line above it. A funnel whose percentages are all shares of the top
   reads as if the last step lost everybody, which is the opposite of what it should tell you. */
$spine = $f['spine'];
ok($spine[0]['of'] === null, 'the first line has no rate, because there is nothing above it');
$members = (int) $f['members'];
if ($members > 0) {
    $confirmed = $spine[2];
    ok(abs((float) $confirmed['of'] - round($confirmed['n'] * 100 / $members, 1)) < 0.05,
       'confirmation is a share of members, not of arrivals');
}
foreach ($f['firsts'] as $row) {
    ok((int) $row['n'] <= $members, "{$row['label']} cannot exceed the member count");
}

echo "\n-- coming back means a different day --\n";
/* A second action ninety seconds after signing up is the same visit. Counting it as a return is
   how a retention number gets to be 90% while nobody has ever come back. */
ok(str_contains($growthSrc, 'SUBSTR(CAST($t.created_at AS TEXT),1,10) > SUBSTR(CAST(u.created_at AS TEXT),1,10)'),
   'the comparison is calendar day against calendar day');
ok(!preg_match('/created_at\s*>\s*u\.created_at/', $growthSrc),
   'and not a bare timestamp comparison');

echo "\n-- we are not counted as members --\n";
ok(substr_count($growthSrc, 'RMT_EDITORIAL_ROLE') >= 1 && str_contains($growthSrc, 'u.role <> ?'),
   'the editorial account is excluded from the cohort');
ok(str_contains($growthSrc, "u.status = 'active'"),
   'and so is a removed account, which would otherwise sit in the funnel forever');

echo "\n-- arrivals are recorded once, and honestly --\n";
ok(in_array('landing_view', RMT_CONTRIB_EVENTS, true), 'the arrival event exists');
ok(function_exists('rmt_track_once'), 'and there is a once-per-session way to record it');
$controllers = (string) file_get_contents($root . '/app/controllers.php');
ok(str_contains($controllers, "rmt_track_once('landing_view'"),
   'the front page records an arrival once rather than per request');
ok(!str_contains($controllers, "rmt_track('landing_view'"),
   'and never the unguarded version, which would count reloads as visitors');
/* It is fired below the signed-in branch. A member opening their own feed is not an arrival, and
   counting them as one would make the signup rate look worse every time the site was used. */
$home = substr($controllers, (int) strpos($controllers, 'function home(array $a)'), 1200);
ok(strpos($home, 'if (current_user()) { feed($a); return; }') < strpos($home, 'landing_view'),
   'and only for somebody who is signed out');

echo "\n-- the inventory says what is actually here --\n";
$inv = rmt_growth_inventory();
foreach (['cities', 'places', 'public_trips', 'upcoming_trips', 'open_plans', 'cities_with_overlap'] as $k) {
    ok(isset($inv[$k]) && is_int($inv[$k]), "$k is a number");
}
ok($inv['cities_with_overlap'] <= $inv['upcoming_trips'],
   'a city with two travelers needs at least two upcoming trips');
ok($inv['public_trips'] <= (int) (q_one("SELECT COUNT(*) c FROM trips WHERE status = 'published'")['c'] ?? 0),
   'public trips are a subset of published ones, so a private trip is never counted as inventory');

echo "\ngrowth_funnel_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
