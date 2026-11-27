<?php
/**
 * Signed-out report links go to the login page. The report route still requires a session.
 *   php tests/report_href_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip'];
$_SESSION = [];

require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/auth.php';

$pass = 0;
$fail = 0;
function ok(string $name, bool $good, string $detail = ''): void {
    global $pass, $fail;
    if ($good) { $pass++; echo "  [PASS] $name\n"; return; }
    $fail++;
    echo "  [FAIL] $name $detail\n";
}

$bare = rmt_report_href();
ok('guest bare report uses login', str_contains($bare, '/login?return='), $bare);
ok('guest bare return is /report', str_contains($bare, rawurlencode('/report')), $bare);
ok('guest bare href is not /report itself', !str_ends_with(parse_url($bare, PHP_URL_PATH) ?: '', '/report'), $bare);

$meetup = rmt_report_href('target_type=meetup&target_id=4');
ok('guest meetup return keeps the target', str_contains($meetup, rawurlencode('/report?target_type=meetup&target_id=4')), $meetup);
ok('report form still requires login', str_contains(file_get_contents(BASE_PATH . '/app/controllers.php'), "function report_form(array \$a): void {\n    require_login();"));

echo ($fail === 0 ? "ALL PASSED ($pass)\n" : "FAILED $fail\n");
exit($fail === 0 ? 0 : 1);
