<?php
/**
 * Regression tests for locals (app/locals.php, migration 070).
 *
 * The site knew who was VISITING a city, because they said so with dates, and never who lived
 * there, even though the answer was sitting in profiles as free text: "Lisbon, PT", "lisbon",
 * "Lisboa". A traveler asking where to actually eat wants a local more than another tourist.
 *
 * The rule that matters is that the resolver is CONSERVATIVE. Listing somebody as a resident of a
 * city they merely mentioned is worse than listing nobody, because the whole value of the section
 * is that these people actually live there.
 *
 *   php tests/locals_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
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
require BASE_PATH . '/app/locals.php';

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT, role TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INT, home_city TEXT, home_destination_id INT, avatar_url TEXT, display_name TEXT)');
$pdo->exec('CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, status TEXT)');
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (1,'lisbon-portugal','Lisbon'),(2,'porto-portugal','Porto')");
$pdo->exec("INSERT INTO users (id,username,status,role) VALUES
              (1,'ana','active','user'), (2,'leo','active','user'), (3,'house','active','editorial'),
              (4,'gone','disabled','user'), (5,'nomad','active','user')");
$pdo->exec("INSERT INTO profiles (user_id,home_city,home_destination_id) VALUES
              (1,'Lisbon, PT',1), (2,'lisbon',1), (3,'Lisbon',1), (4,'Lisbon',1), (5,'Denver, US',NULL)");
$pdo->exec("INSERT INTO reviews (id,user_id,destination_id,status) VALUES (1,2,1,'published'),(2,2,1,'published'),(3,1,1,'draft')");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

// The resolver: exact, or the part before the comma. Nothing looser.
ok('an exact name resolves', rmt_resolve_home_destination('Lisbon') === 1);
ok('case does not matter', rmt_resolve_home_destination('lisbon') === 1);
ok('a country suffix is ignored', rmt_resolve_home_destination('Lisbon, PT') === 1);
ok('whitespace is ignored', rmt_resolve_home_destination('  Lisbon , Portugal ') === 1);
ok('a different city is a different city', rmt_resolve_home_destination('Porto') === 2);
ok('a city we do not have stays unresolved', rmt_resolve_home_destination('Denver, US') === null);
ok('a name that merely contains ours does not match', rmt_resolve_home_destination('Lisbon Valley') === null);
ok('empty is null', rmt_resolve_home_destination('') === null && rmt_resolve_home_destination(null) === null);
ok('a trailing comma does not resolve to everything', rmt_resolve_home_destination(', PT') === null);

$locals = rmt_city_locals(1);
$names = array_column($locals, 'username');
sort($names);
ok('locals are the members who live there', $names === ['ana', 'leo'], json_encode($names));
ok('the house account is never a local', !in_array('house', $names, true));
ok('a disabled account is not a local', !in_array('gone', $names, true));
ok('somebody living elsewhere is not a local', !in_array('nomad', $names, true));
ok('the most active local leads', $locals[0]['username'] === 'leo', json_encode(array_column($locals, 'username')));
ok('only published reviews are counted', $locals[0]['reviews'] === 2 && $locals[1]['reviews'] === 0,
   json_encode(array_column($locals, 'reviews')));
ok('the count matches the list', rmt_city_local_count(1) === 2);
ok('a city with no residents has none', rmt_city_locals(2) === [] && rmt_city_local_count(2) === 0);

// The migration must exist for both drivers, and must not lose what people typed.
foreach (['pgsql', 'sqlite'] as $driver) {
    $sql = (string) file_get_contents(BASE_PATH . "/database/migrations/070_profile_home_destination.$driver.sql");
    ok("$driver migration adds the column", str_contains($sql, 'home_destination_id'));
    ok("$driver migration backfills from what people typed", str_contains($sql, 'UPDATE profiles'));
    ok("$driver migration never drops home_city", !preg_match('/DROP COLUMN\s+home_city/i', $sql));
}

$profiles = (string) file_get_contents(BASE_PATH . '/app/profiles.php');
ok('saving a profile resolves the city', str_contains($profiles, 'rmt_resolve_home_destination'));
$controllers = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('both save paths write the column', substr_count($controllers, 'home_destination_id=?') === 2);

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
