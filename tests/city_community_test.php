<?php
/**
 * The city community block: views/_city_community.php on /d/{slug}.
 *
 * A city page used to open with our own rating, our own editorial review and a wall of places, and
 * put the conversation four screens down. The community is the product, so it is now the first
 * thing under the hero. Three things have to stay true, and each of them is a thing that has gone
 * wrong on a social page before:
 *
 *   1. ORDER. The community sits above everything this site wrote about the city. Nothing stops a
 *      later edit from dropping a new editorial panel above it, so the order is asserted, not
 *      trusted.
 *   2. HONESTY. Every number on the strip comes from a query. There is no hardcoded count, no
 *      rounded up follower figure, and the empty state carries no digits at all: a city with
 *      nobody in it says so. A fabricated crowd would make the whole site worthless, and it is
 *      the single easiest thing to add by accident while making a page look alive.
 *   3. COUNTS MATCH ROWS. The heading count is counted, not taken from the length of the teaser
 *      list, which is capped at six. The reviews count on this same page made exactly that
 *      mistake once.
 *
 *   php tests/city_community_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; printf("  [PASS] %s\n", $what); }
    else    { $fail++; printf("  [FAIL] %s\n", $what); }
}

/* ---------------------------------------------------------------- 1. the count is a count */

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT)");
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INT, destination_id INT,
              body TEXT, status TEXT, created_at TEXT)");
$pdo->exec("INSERT INTO users (id, username, status) VALUES (1,'ana','active'),(2,'leo','active'),(3,'gone','suspended')");
$rows = [
    [1, 1, 7, 'published'],   // counts
    [2, 2, 7, 'published'],   // counts
    [3, 1, 7, 'removed'],     // moderated away, not a question any more
    [4, 3, 7, 'published'],   // suspended author, not shown so not counted
    [5, 1, 9, 'published'],   // another city
];
foreach ($rows as [$id, $uid, $dest, $status]) {
    $pdo->exec("INSERT INTO posts (id, user_id, destination_id, body, status, created_at)
                VALUES ($id, $uid, $dest, 'x', '$status', '2026-09-15 00:00:00')");
}
require BASE_PATH . '/app/posts.php';

ok(rmt_posts_count_for_destination(7) === 2, 'the count excludes removed posts and suspended authors');
ok(rmt_posts_count_for_destination(9) === 1, 'the count is scoped to one city');
ok(rmt_posts_count_for_destination(999) === 0, 'a city nobody has posted about counts zero');

/* ---------------------------------------------------------------- 2. the view contract */

$partial = (string) file_get_contents(BASE_PATH . '/views/_city_community.php');
$page    = (string) file_get_contents(BASE_PATH . '/views/destination.php');

ok(str_contains($page, "include __DIR__ . '/_city_community.php'"), 'the city page includes the community block');

$posCommunity = strpos($page, '_city_community.php');
$posRating    = strpos($page, 'Community rating');
$posEditorial = strpos($page, 'ed-panel');
ok($posCommunity !== false && $posRating !== false && $posCommunity < $posRating,
   'the community is above our own rating card');
ok($posCommunity !== false && $posEditorial !== false && $posCommunity < $posEditorial,
   'the community is above the editorial panel');

ok(str_contains($partial, "action=\"<?= e(url('post/new')) ?>\""),
   'the composer posts through the existing post route, not a new one');
ok(str_contains($partial, 'csrf_field()') && str_contains($partial, 'rmt_submit_token'),
   'the composer carries a CSRF token and a one-use submit token');
ok(str_contains($partial, "url('u/' . \$tp['username'])"), 'every post links to its author profile');
ok(str_contains($partial, 'ago((string) $tp[\'created_at\'])'), 'every post carries a timestamp');

/* No fabricated social proof. Every number in the strip has to come from a variable; a literal
   digit inside the stats list would be a count somebody typed rather than counted. */
if (preg_match('/\$stats = array_values\(array_filter\(\[(.*?)\]\)\);/s', $partial, $m)) {
    ok(!preg_match("/'n'\s*=>\s*\d/", $m[1]), 'no stat is a hardcoded number');
} else {
    ok(false, 'the stats list was not found where the test expects it');
}
if (preg_match('/<div class="cc-empty">(.*?)<\/div>/s', $partial, $m)) {
    /* php first, then markup: a style attribute is full of digits and none of them are a count. */
    $text = preg_replace('/<\?.*?\?>/s', '', $m[1]) ?? '';
    $text = trim(strip_tags($text));
    ok(!preg_match('/\d/', $text), 'the empty state contains no numbers at all');
    ok(stripos($text, 'yet') !== false, 'the empty state says the city is empty rather than implying a crowd');
} else {
    ok(false, 'the empty state was not found');
}

/* ---------------------------------------------------------------- 3. the phone */

$css = (string) file_get_contents(BASE_PATH . '/public/assets/css/app.css');
$rule = static function (string $sel) use ($css): string {
    $pos = strpos($css, $sel . '{');
    if ($pos === false) return '';
    $end = strpos($css, '}', $pos);
    return $end === false ? '' : substr($css, $pos, $end - $pos + 1);
};
ok($rule('.cc-stats') !== '' && str_contains($rule('.cc-stats'), 'flex-wrap:wrap'),
   'the stat row wraps rather than pushing the page sideways');
ok(str_contains($rule('.cc-actions'), 'flex-wrap:wrap'), 'the action row wraps');
ok(str_contains($rule('.cc-actions .btn,.cc-ask-row .btn'), 'min-height:44px'),
   'every control in the block clears a 44px tap target');
ok(str_contains($rule('.cc-ask-box textarea'), 'box-sizing:border-box'),
   'the composer cannot overflow its column');
ok(str_contains($rule('.cc-post-body'), 'overflow-wrap:anywhere'),
   'a long unbroken word in a post cannot scroll the page');
ok(str_contains($rule('.cc-head'), 'flex-direction:column'),
   'the block stacks in one column by default, and only widens when there is room');

/* The four first moves a stranger has, all in the actions row at the top of the block rather than
   thousands of pixels down the page. On a phone the travelers hub used to sit about seven screens
   below the fold, and the only trip control was inside the collapsed menu, so a signed in visitor
   on a phone could not see one at all. */
$ccSrc = (string) file_get_contents(dirname(__DIR__) . '/views/_city_community.php');
$acts  = substr($ccSrc, (int) strpos($ccSrc, 'cc-actions'), 2600);
ok(str_contains($acts, 'cc-follow'),      'follow is in the actions row');
ok(str_contains($acts, 'cc-ask'),         'ask is in the actions row');
ok(str_contains($acts, 'cc-travelers'),   'see who is going is in the actions row');
ok(str_contains($acts, 'cc-post-dates'),  'post your dates is in the actions row');
ok(str_contains($acts, '/travelers'),     'the travelers control opens the hub');
ok(str_contains($acts, 'trip/new?destination_id='), 'the trip control carries the city');
ok(!preg_match('/\d+\s+travelers going/i', $acts), 'neither control claims anybody is there');

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL CITY COMMUNITY TESTS PASS ({$pass})\n";
