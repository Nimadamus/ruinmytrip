<?php
/**
 * Languages on a traveler profile: a closed list, optional, and incapable of taking a page down.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://ruinmytrip.com',
                      'app_name' => 'RuinMyTrip', 'db_driver' => 'sqlite', 'sqlite_path' => ':memory:'];
require BASE_PATH . '/app/db.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, display_name TEXT)');
$pdo->exec((string) file_get_contents(BASE_PATH . '/database/migrations/096_profile_languages.sqlite.sql'));
$pdo->exec("INSERT INTO profiles (user_id, display_name) VALUES (1,'a'),(2,'b')");
require_once BASE_PATH . '/app/helpers.php';
require_once BASE_PATH . '/app/profiles.php';

$pass = 0; $fail = 0;
function ok(string $what, $got, $want = true): void {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  [PASS] $what\n"; }
    else { $fail++; echo "  [FAIL] $what  expected=" . var_export($want, true) . " got=" . var_export($got, true) . "\n"; }
}

echo "\n-- only the list, only once, in order --\n";
ok('codes outside the list are dropped', rmt_languages_clean(['en', 'klingon', 'es']), ['en', 'es']);
ok('duplicates collapse',                rmt_languages_clean(['fr', 'fr', 'fr']), ['fr']);
ok('order follows the list, not the form', rmt_languages_clean(['ja', 'en']), ['en', 'ja']);
ok('capped at eight', count(rmt_languages_clean(array_keys(RMT_LANGUAGES))), 8);
ok('markup cannot get in', rmt_languages_clean(['<script>', 'de']), ['de']);

echo "\n-- saved and read back --\n";
rmt_languages_save(1, ['es', 'en', 'nope']);
ok('what was stored is the cleaned list', (string) $pdo->query('SELECT languages FROM profiles WHERE user_id=1')->fetchColumn(), 'en,es');
ok('read back as codes', rmt_languages_for_user(1), ['en', 'es']);
ok('and shown as names', rmt_language_labels(rmt_languages_for_user(1)), ['English', 'Spanish']);
rmt_languages_save(1, []);
ok('clearing every box stores nothing', $pdo->query('SELECT languages FROM profiles WHERE user_id=1')->fetchColumn(), null);
ok('a member who never chose has no languages', rmt_languages_for_user(2), []);

echo "\n-- it cannot take a profile down --\n";
$pdo->exec('CREATE TABLE p2 AS SELECT user_id, display_name FROM profiles');
$pdo->exec('DROP TABLE profiles');
$pdo->exec('ALTER TABLE p2 RENAME TO profiles');
ok('a database without the column reads nothing rather than throwing', rmt_languages_for_user(1), []);
rmt_languages_save(1, ['en']);
ok('and saving does not throw either', true, true);

echo "\n-- wired into the page and the form --\n";
$view = (string) file_get_contents(BASE_PATH . '/views/profile.php');
$edit = (string) file_get_contents(BASE_PATH . '/views/profile_edit.php');
$ctrl = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('the profile shows them', str_contains($view, 'rmt_languages_for_user((int) $u[\'id\'])'), true);
ok('...only when there are some', str_contains($view, '<?php if ($rmtLangs): ?>'), true);
ok('the form offers the fixed list', str_contains($edit, 'name="languages[]"'), true);
ok('the save goes through the cleaner', str_contains($ctrl, "rmt_languages_save((int) \$me['id']"), true);
ok('the profile query was not widened', str_contains($ctrl, 'p.travel_style,p.languages'), false);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL PROFILE LANGUAGE TESTS PASS ({$pass})\n";
