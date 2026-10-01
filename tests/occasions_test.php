<?php
/**
 * Occasions (app/occasions.php): who is going is read live from real members' public trips and
 * buddy posts that overlap the window, house accounts never count, and a page is indexable only
 * once somebody real is on it. Every registry entry names a known kind, a city and a valid window.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
const RMT_EDITORIAL_ROLE = 'editorial';
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/occasions.php';

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-56s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

echo "\nThe registry:\n";
foreach (RMT_OCCASIONS as $slug => $o) {
    ok("$slug: known kind", isset(RMT_OCCASION_KINDS[$o['kind']]), true);
    ok("$slug: a window that runs forwards", $o['from'] <= $o['to'], true);
    ok("$slug: a lede and at least one fact", $o['lede'] !== '' && count($o['facts']) > 0, true);
    ok("$slug: slug is url safe", (bool) preg_match('/^[a-z0-9\-]+$/', $slug), true);
    $txt = $o['name'] . $o['lede'] . implode('', $o['facts']);
    ok("$slug: no dashes in the copy", (bool) preg_match('/\s[\x{2013}\x{2014}-]\s|[\x{2013}\x{2014}]/u', $txt), false);
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active', role TEXT DEFAULT 'user')");
$pdo->exec("CREATE TABLE profiles (user_id INT, avatar_url TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, hero_url TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, status TEXT, visibility TEXT, date_from TEXT, date_to TEXT)");
$pdo->exec("CREATE TABLE buddy_posts (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, status TEXT, date_from TEXT, date_to TEXT)");
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'chiang-mai-thailand','Chiang Mai','Thailand')");
$pdo->exec("INSERT INTO users (id,username) VALUES (1,'ana_real'),(2,'team_ana'),(3,'gone')");
$pdo->exec("UPDATE users SET status='suspended' WHERE id=3");

$o = rmt_occasion('yi-peng-chiang-mai');
ok('the occasion resolves to its city', (int) $o['d']['id'], 1);
ok('a written guide is indexable with nobody on it yet', rmt_occasion_indexable($o), true);
ok('nobody yet', count(rmt_occasion_people($o)), 0);
$pdo->exec("INSERT INTO trips VALUES (1,2,1,'published','public','2026-11-22','2026-11-27')");
ok('a team account does not count', count(rmt_occasion_people($o)), 0);
$pdo->exec("INSERT INTO trips VALUES (2,3,1,'published','public','2026-11-22','2026-11-27')");
ok('a suspended account does not count', count(rmt_occasion_people($o)), 0);
$pdo->exec("INSERT INTO trips VALUES (3,1,1,'published','private','2026-11-22','2026-11-27')");
ok('a private trip does not count', count(rmt_occasion_people($o)), 0);
$pdo->exec("INSERT INTO trips VALUES (4,1,1,'published','public','2026-12-01','2026-12-05')");
ok('dates outside the window do not count', count(rmt_occasion_people($o)), 0);
$pdo->exec("INSERT INTO trips VALUES (5,1,1,'published','public','2026-11-25','2026-11-30')");
ok('an overlapping public trip by a member counts', count(rmt_occasion_people($o)) > 0, true);
ok('and is listed once', count(rmt_occasion_people($o)), 1);
$pdo->exec("INSERT INTO buddy_posts VALUES (1,1,1,'open','2026-11-20','2026-11-24')");
ok('an overlapping buddy post is listed too', count(rmt_occasion_people($o)), 2);
ok('links prefill the window', str_contains(rmt_occasion_links($o)['trip'], 'from=2026-11-22'), true);
ok('an unknown slug is nothing', rmt_occasion('nope'), null);

printf("\n%s: %d passed, %d failed\n", basename(__FILE__, '.php'), $pass, $fail);
exit($fail ? 1 : 0);
