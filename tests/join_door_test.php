<?php
/**
 * Which door a logged-out visitor is shown (app/auth.php).
 *
 * Every protected route sent everybody to "Welcome back / Sign in to your RuinMyTrip account".
 * Confirmed live on 2026-09-09: the homepage box that asks what ruined your trip posts to
 * /review/new, so a stranger who typed a sentence into it was redirected to the SIGN IN page, with
 * their sentence surviving only inside the return URL where nothing displayed it. The site is short
 * of members, not of members who forgot their password, so a contribution route opens on Join.
 *
 *   php tests/join_door_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

/* auth.php's other functions reach for the database and the session; these two do not, so the file
   is loaded with just enough around it to be included. */
function q_one(string $sql, array $args = []): ?array { return null; }
require dirname(__DIR__) . '/app/auth.php';

/* Contribution: the visitor is here to give the site something. */
foreach (['/review/new', '/trip/new', '/going', '/contribute', '/matches', '/talk', '/meetups',
          '/d/lisbon-portugal/travelers', '/meetup/12'] as $p) {
    ok("join door for $p", rmt_return_is_join_intent($p));
}
ok('the query string does not change the door',
   rmt_return_is_join_intent('/review/new?src=ruined&ruined=Two+hour+taxi+queue'));

/* Member mail: a notification link or a bookmark belongs to somebody who already has an account. */
foreach (['/feed', '/notifications', '/messages', '/settings', '/u/nima', '/comment'] as $p) {
    ok("sign-in door for $p", !rmt_return_is_join_intent($p));
}
ok('an empty return is not treated as intent', !rmt_return_is_join_intent(''));
/* An absolute URL must not smuggle a path past the check; redirect safety is elsewhere, but this
   function must at least read the path it is given rather than the host. */
ok('an off-site url does not read as a contribution route',
   !rmt_return_is_join_intent('https://example.com/feed'));

/* The sentence, said back to them. */
$line = rmt_join_intent_line('/review/new?src=ruined&ruined=' . rawurlencode('The taxi queue took two hours'));
ok('the typed line is quoted on the join page', $line !== null && str_contains($line, 'taxi queue took two hours'), (string) $line);
ok('and it says what happens to it', $line !== null && str_contains($line, 'first review'));
$plain = rmt_join_intent_line('/review/new');
ok('a review route with no line still says why to join', $plain !== null && str_contains($plain, 'review'));
ok('a long line is trimmed rather than pasted whole',
   mb_strlen((string) rmt_join_intent_line('/review/new?ruined=' . rawurlencode(str_repeat('a', 400)))) < 260);
ok('a route with nothing to say returns null', rmt_join_intent_line('/feed') === null);

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
