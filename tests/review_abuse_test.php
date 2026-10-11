<?php
/**
 * Abuse guards on the review path (2026-10-11).
 *
 *   - the same member reviewing the same place twice within a day gets the first review back,
 *     not a second row (double tap, retried request, account step handing it over again);
 *   - a later review of a different place is a new row;
 *   - more than two links in a review is refused in plain words;
 *   - the rate limit key is Cloudflare's client address, not the spoofable first forwarded address.
 *
 *   php tests/review_abuse_test.php   (writes to database/dev.sqlite inside a transaction it rolls back)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/database/dev.sqlite')) { echo "SKIP  no dev database\n"; exit(0); }
putenv('DATABASE_URL');
require $root . '/app/bootstrap.php';
require_once BASE_PATH . '/app/controllers.php';

$fails = 0;
function ok(string $n, bool $c, string $d = ''): void { global $fails; if (!$c) { $fails++; echo "FAIL  $n" . ($d ? " -- $d" : '') . "\n"; } else echo "PASS  $n\n"; }

$me = q_one("SELECT * FROM users WHERE email_verified_at IS NOT NULL AND status = 'active' ORDER BY id LIMIT 1");
$places = q_all("SELECT p.*, d.name dest_name, d.country dest_country, d.slug dest_slug FROM places p JOIN destinations d ON d.id = p.destination_id
                  WHERE p.status = 'active' ORDER BY p.id LIMIT 2");
if (!$me || count($places) < 2) { echo "SKIP  dev data missing\n"; exit(0); }

db()->beginTransaction();
try {
    $post = static fn(array $p, string $body) => ['place_id' => (string) $p['id'], 'destination_id' => (string) $p['destination_id'],
        'subject_type' => (string) $p['type'], 'subject_name' => (string) $p['name'], 'rating' => '4', 'body' => $body];
    $body = 'The coffee was good and the terrace was a trap at noon, go at nine instead.';
    $a = rmt_review_save($me, $post($places[0], $body), rmt_place_by_id((int) $places[0]['id']), false);
    $b = rmt_review_save($me, $post($places[0], $body), rmt_place_by_id((int) $places[0]['id']), false);
    ok('the first review saves', $a['ok'] && $a['id'] > 0);
    ok('the same review again within a day returns the first one', $b['ok'] && $b['id'] === $a['id'] && !empty($b['duplicate']),
       json_encode([$a['id'], $b['id']]));
    $n = (int) q_one('SELECT COUNT(*) c FROM reviews WHERE user_id = ? AND place_id = ?', [(int) $me['id'], (int) $places[0]['id']])['c'];
    $c = rmt_review_save($me, $post($places[1], $body), rmt_place_by_id((int) $places[1]['id']), false);
    ok('a different place is a new review', $c['ok'] && $c['id'] !== $a['id']);
    $d = rmt_review_save($me, $post($places[0], 'A draft about the place, only for me, with enough words.'), rmt_place_by_id((int) $places[0]['id']), true);
    ok('a deliberate draft is never swallowed as a duplicate', $d['ok'] && $d['id'] !== $a['id']);
} finally {
    db()->rollBack();
}

$v = rmt_review_validate(['destination_id' => '1', 'subject_type' => 'restaurant', 'subject_name' => 'X', 'rating' => '5',
    'body' => 'Great place http://a.example http://b.example http://c.example lots of words here'], false);
ok('three links are refused', !$v['ok'] && (bool) array_filter($v['errors'], static fn($e) => str_contains($e, 'at most 2 links')));
$v = rmt_review_validate(['destination_id' => '1', 'subject_type' => 'restaurant', 'subject_name' => 'X', 'rating' => '5',
    'body' => 'Book at https://their.example and the menu is at www.menu.example, both worth a look.'], false);
ok('two links are fine', !array_filter($v['errors'], static fn($e) => str_contains($e, 'links')));

$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 162.158.0.1';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.9';
ok('the rate limit key is the Cloudflare client address', rmt_client_ip() === '203.0.113.9');
unset($_SERVER['HTTP_CF_CONNECTING_IP']);
ok('without Cloudflare it falls back as before', rmt_client_ip() === '1.2.3.4');
$_SERVER['HTTP_CF_CONNECTING_IP'] = 'not-an-ip';
ok('a junk Cloudflare header is ignored', rmt_client_ip() === '1.2.3.4');

$form = (string) file_get_contents(BASE_PATH . '/views/review_new.php');
ok('the guest form carries the hidden field', str_contains($form, 'name="hp_note"') && str_contains($form, 'tabindex="-1"'));

echo $fails ? "\nreview_abuse_test: $fails FAILED\n" : "\nreview_abuse_test: all passed\n";
exit($fails ? 1 : 0);
