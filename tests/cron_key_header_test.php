<?php
/**
 * The cron key can travel in the X-Cron-Key header instead of the URL (2026-10-11), and every cron
 * route reads it through rmt_cron_key_given(), so none still reads ?key= directly.
 *
 *   php tests/cron_key_header_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
                      'db_driver' => 'sqlite', 'sqlite_path' => ':memory:'];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';

$fails = 0;
function ok(string $n, bool $c): void { global $fails; if (!$c) { $fails++; echo "FAIL  $n\n"; } else echo "PASS  $n\n"; }

$_GET = []; $_POST = []; unset($_SERVER['HTTP_X_CRON_KEY']);
ok('no key given is empty', rmt_cron_key_given() === '');
$_GET['key'] = 'from-url';
ok('?key= still works', rmt_cron_key_given() === 'from-url');
$_SERVER['HTTP_X_CRON_KEY'] = 'from-header';
ok('the header wins over the URL', rmt_cron_key_given() === 'from-header');
$_SERVER['HTTP_X_CRON_KEY'] = '   ';
ok('a blank header falls back to the URL', rmt_cron_key_given() === 'from-url');

$direct = [];
foreach (glob(BASE_PATH . '/app/*.php') as $f) {
    if (preg_match("/\\\$given\\s*=\\s*\\(string\\)\\s*input\\('key'\\)/", (string) file_get_contents($f))) $direct[] = basename($f);
}
ok('no cron route reads ?key= directly', !$direct);
$n = 0;
foreach (glob(BASE_PATH . '/app/*.php') as $f) $n += substr_count((string) file_get_contents($f), '$given = rmt_cron_key_given();');
ok('all eight cron routes use the helper', $n === 8);

echo $fails ? "\ncron_key_header_test: $fails FAILED\n" : "\ncron_key_header_test: all passed\n";
exit($fails ? 1 : 0);
