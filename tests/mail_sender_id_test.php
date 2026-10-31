<?php
/**
 * Sender identification (2026-10-30): the postal address in every footer once configured, the
 * unsubscribe header mail clients turn into a button, and no promotional mail without an address.
 *
 *   php tests/mail_sender_id_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/mail.php';

$fails = 0;
function ok(bool $cond, string $name): void {
    global $fails;
    if (!$cond) $fails++;
    echo ($cond ? 'PASS  ' : 'FAIL  ') . $name . "\n";
}

putenv('MAIL_POSTAL_ADDRESS');
putenv('RESEND_API_KEY=test');
ok(!str_contains(rmt_mail_layout('Hi', '<p>x</p>'), 'RuinMyTrip, '), 'no address line until one is configured');
ok(!rmt_mail_marketing_ready(), 'no promotional mail without a postal address');

putenv('MAIL_POSTAL_ADDRESS=100 Test St, Springfield, CA 90000');
$html = rmt_mail_layout('Hi', '<p>x</p>');
ok(str_contains($html, 'RuinMyTrip, 100 Test St, Springfield, CA 90000'), 'the footer carries the postal address');
ok(rmt_mail_marketing_ready(), 'ready once mail works and the address is set');

putenv('RESEND_API_KEY');
ok(!rmt_mail_marketing_ready(), 'never ready while mail is switched off');

ok(rmt_mail_unsub_headers('https://ruinmytrip.com/unsubscribe?t=abc') === ['List-Unsubscribe' => '<https://ruinmytrip.com/unsubscribe?t=abc>'],
   'the unsubscribe link becomes a List-Unsubscribe header');
ok(rmt_mail_unsub_headers('') === [], 'no header without a link');

echo $fails ? "\nmail_sender_id_test: $fails FAILED\n" : "\nmail_sender_id_test: all passed\n";
exit($fails ? 1 : 0);
