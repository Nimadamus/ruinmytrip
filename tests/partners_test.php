<?php
/**
 * Partner links (app/partners.php): off until configured, then only where useful, always disclosed
 * next to the link, always rel="sponsored", never on a review or a member's words.
 *   php tests/partners_test.php
 */
putenv('DATABASE_URL');
require dirname(__DIR__) . '/app/bootstrap.php';
$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }

putenv('PARTNER_AIRHELP_URL'); putenv('PARTNER_TICKETS_URL');
ok(rmt_partner_block_for_post('trip-went-wrong-what-you-can-claim') === '', 'nothing is drawn until a program is joined and configured');
ok(rmt_partner_block_for_post('sagrada-familia-tickets-2026') === '', 'not on ticket notes either');

putenv('PARTNER_AIRHELP_URL=https://www.airhelp.com/en/?a=TEST123');
putenv('PARTNER_TICKETS_URL=https://tickets.example.com/search?q={q}&partner=TEST');
$claims = rmt_partner_block_for_post('trip-went-wrong-what-you-can-claim');
ok($claims !== '' && str_contains($claims, 'rel="sponsored noopener"'), 'the claims page gets the claim service, marked sponsored');
ok(str_contains($claims, 'Paid link') && str_contains($claims, '/affiliate'), 'the disclosure sits next to the link, with the policy');
ok(str_contains($claims, 'for free'), 'the free route is named alongside the paid one');
$tix = rmt_partner_block_for_post('sagrada-familia-tickets-2026');
ok(str_contains($tix, 'q=Sagrada%20Familia') && str_contains($tix, 'official site'), 'a ticket note gets a reseller search for its own attraction, after the official site');
ok(rmt_partner_block_for_post('barcelona-tourist-tax-2026') === '' && rmt_partner_block_for_post('how-to-find-a-travel-buddy-safely') === '', 'no partner on posts it does not fit');
putenv('PARTNER_AIRHELP_URL=http://insecure.example.com/x');
ok(rmt_partner_block_for_post('trip-went-wrong-what-you-can-claim') === '', 'a non-https link is never printed');
putenv('PARTNER_AIRHELP_URL=javascript:alert(1)');
ok(rmt_partner_block_for_post('trip-went-wrong-what-you-can-claim') === '', 'nor a script URL');

// Only the blog view calls it, and only for editorial posts; no review, place or city view does.
$callers = array_filter(array_merge(glob(dirname(__DIR__) . '/views/*.php'), glob(dirname(__DIR__) . '/views/*/*.php')),
    static fn($f) => str_contains((string) file_get_contents($f), 'rmt_partner_'));
ok(array_map('basename', array_values($callers)) === ['blog_show.php'], 'only the blog note view draws partner links');
ok(str_contains((string) file_get_contents(dirname(__DIR__) . '/views/blog_show.php'), 'if ($isEd) echo rmt_partner_block_for_post'), 'and only on our own editorial notes');

putenv('PARTNER_AIRHELP_URL'); putenv('PARTNER_TICKETS_URL');
echo $fails ? "partners_test: $fails FAILED\n" : "partners_test: all passed\n";
exit($fails ? 1 : 0);
