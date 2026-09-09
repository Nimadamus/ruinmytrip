<?php
/**
 * How somebody travels (migration 074, RMT_TRAVEL_STYLES).
 *
 * "Solo travelers" was the one discovery question this site could not answer. It knew who was going
 * where and when and nothing about who they would arrive with, so a person travelling alone and a
 * family of four were shown the same list and left to guess.
 *
 * The rules that matter:
 *   - it is optional, and "not said" is never displayed as an answer.
 *   - it is a closed list, because the site groups people by it and free text is a filter nobody
 *     can build. Anything else submitted is treated as not said rather than stored.
 *   - it is self-declared, so the profile says "travels solo" as a preference, never as a fact
 *     established about somebody.
 *
 *   php tests/travel_style_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/profiles.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

ok('the list is closed and short', array_keys(RMT_TRAVEL_STYLES) === ['solo', 'couple', 'family', 'friends'],
   implode(',', array_keys(RMT_TRAVEL_STYLES)));

$v = rmt_profile_validate(['travel_style' => 'solo']);
ok('a listed style is kept', ($v['data']['travel_style'] ?? null) === 'solo');
$v = rmt_profile_validate(['travel_style' => 'family']);
ok('so is another', ($v['data']['travel_style'] ?? null) === 'family');

foreach (['', 'nomad', 'SOLO', '<script>', '0'] as $bad) {
    $v = rmt_profile_validate(['travel_style' => $bad]);
    // ?? would swallow the null this is looking for, which is how the first version of this test
    // reported six failures against code that was doing exactly the right thing.
    ok('not on the list means not said: ' . var_export($bad, true),
       array_key_exists('travel_style', $v['data']) && $v['data']['travel_style'] === null);
}
$v = rmt_profile_validate([]);
ok('leaving it out is not said',
   array_key_exists('travel_style', $v['data']) && $v['data']['travel_style'] === null);
ok('it never blocks a profile save', $v['ok']);

// Both drivers get the column, and neither migration touches anything else on the table.
foreach (['pgsql', 'sqlite'] as $driver) {
    $sql = (string) file_get_contents(BASE_PATH . "/database/migrations/074_travel_style.$driver.sql");
    ok("$driver migration adds travel_style", str_contains($sql, 'ADD COLUMN') && str_contains($sql, 'travel_style'));
    ok("$driver migration drops nothing", !preg_match('/\bDROP\b/i', $sql));
}

// The wiring: the form offers it, both save paths write it, and the city page counts it.
$edit = (string) file_get_contents(BASE_PATH . '/views/profile_edit.php');
ok('the form offers the list', str_contains($edit, 'name="travel_style"') && str_contains($edit, 'RMT_TRAVEL_STYLES'));
ok('the form allows saying nothing', str_contains($edit, 'Rather not say'));
$controllers = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('both save paths write it', substr_count($controllers, 'travel_style=?') === 2);
$profile = (string) file_get_contents(BASE_PATH . '/views/profile.php');
ok('the profile shows it as a preference', str_contains($profile, 'Travels <?= e(strtolower(RMT_TRAVEL_STYLES'));
$hub = (string) file_get_contents(BASE_PATH . '/app/travelers_hub.php');
ok('the city page counts who travels solo', str_contains($hub, "=== 'solo'"));
$plans = (string) file_get_contents(BASE_PATH . '/app/plans.php');
ok('the who-is-going query carries it', str_contains($plans, 'p.travel_style'));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
