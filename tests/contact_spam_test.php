<?php
/**
 * The two forms open to signed out visitors (/contact and /p/{slug}/correct) carry a honeypot,
 * and every connection-keyed rate limit uses the visitor's real address.
 *
 * Behind Cloudflare and Render, REMOTE_ADDR is the proxy, not the visitor. A limit keyed on it is
 * one bucket shared by everybody: twenty signed out messages an hour for the whole site on the
 * contact form, and one busy minute of search typing locking the suggest box for every visitor.
 *
 *   php tests/contact_spam_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/feedback.php';
require BASE_PATH . '/app/ratelimit.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if ($cond) { echo "  PASS  $name\n"; return; }
    $fails++;
    echo "  FAIL  $name" . ($detail !== '' ? "  ($detail)" : '') . "\n";
}

$_POST = [];
ok('no honeypot field: a person', rmt_honeypot_tripped() === false);
$_POST = ['hp_note' => '   '];
ok('whitespace only: a person (autofill can leave a space)', rmt_honeypot_tripped() === false);
$_POST = ['hp_note' => 'http://spam.example'];
ok('filled: a bot', rmt_honeypot_tripped() === true);

foreach (['views/legal/contact.php', 'views/place_correct.php'] as $v) {
    $src = file_get_contents(BASE_PATH . '/' . $v);
    ok("$v renders the hidden field", str_contains($src, 'name="hp_note"') && str_contains($src, 'class="hp-field"')
        && str_contains($src, 'tabindex="-1"'));
}

$ctl = file_get_contents(BASE_PATH . '/app/controllers.php');
function body_of(string $src, string $fn): string {
    $s = strpos($src, "function $fn(");
    return $s === false ? '' : substr($src, $s, strpos($src, "\nfunction ", $s + 10) - $s);
}
foreach (['page_contact_submit', 'place_correct_submit'] as $fn) {
    $b = body_of($ctl, $fn);
    ok("$fn checks the honeypot before storing anything",
       str_contains($b, 'rmt_honeypot_tripped()') && strpos($b, 'rmt_honeypot_tripped()') < strpos($b, 'rmt_feedback_submit('));
    ok("$fn keys its limit on the client address", str_contains($b, 'rmt_client_ip()'));
}
ok('no controller keys a rate limit on REMOTE_ADDR', !str_contains($ctl, "\$_SERVER['REMOTE_ADDR']"));

// The address used is the visitor's, not the proxy's.
$_SERVER = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_CF_CONNECTING_IP' => '203.0.113.9'];
ok('the Cloudflare visitor address wins over the proxy', rmt_client_ip() === '203.0.113.9');

echo $fails ? "\n$fails FAILED\n" : "\nAll contact spam tests passed.\n";
exit($fails ? 1 : 0);
