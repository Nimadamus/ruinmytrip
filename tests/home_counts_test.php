<?php
/**
 * What the homepage promises with its numbers (views/home.php).
 *
 * The first screenful is the whole pitch to somebody who arrived from a link and has not decided
 * anything yet, and both of its numbers were working against it. The hero printed "0 Traveler
 * reviews" directly under the Join button, which is an advert for an empty room. The city chips
 * printed going + meetups + talk added together under the heading "Who is going, by city", so a
 * city with one question and nobody travelling read as one traveller going there: three different
 * things summed under the name of one of them.
 *
 * These are source assertions, the same shape as tests/mobile_tabbar_test.php, because the invariant
 * is about what the template is allowed to say rather than about a function's return value.
 *
 *   php tests/home_counts_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$home = (string) file_get_contents($root . '/views/home.php');

/* Hero stats: built into a list first, and a count only goes in when it is worth reading.

   Above zero was the original rule and it was not enough. "3 Travelers" sitting directly under
   the Join button is honest and is an advertisement for an empty room, which is the same failure
   the zero rule exists to prevent, one order of magnitude up. A small number is left out rather
   than rounded up, and it comes back the moment it is worth reading. */
ok('a hero stat is only added when it is worth reading',
   preg_match_all('/\$heroStats\[\] = \[/', $home) === 4
   && substr_count($home, '>= 10)') >= 2
   && substr_count($home, '> 0)') >= 2);
ok('and the row never grows past three',
   str_contains($home, 'array_slice($heroStats, 0, 3)'));
ok('the hero row disappears entirely rather than printing an empty strip',
   str_contains($home, 'if ($heroStats):'));
ok('nothing prints a raw stat outside that list',
   !preg_match('/<b><\?= \(int\)\$stat_/', $home));

/* City chips: one signal per chip, named, never a sum. */
ok('the chip badge is not going + meetups + talk added together',
   !str_contains($home, "(int)\$c['going_count'] + (int)\$c['meetup_count']"));
ok('a chip with travellers says going', str_contains($home, "' going'"));
ok('a chip with only meetups says meetup', str_contains($home, "' meetup'"));
ok('a chip with only talk says question, not a bare number', str_contains($home, "' question'"));
ok('the heading only claims travellers when somebody posted dates',
   str_contains($home, "!empty(\$goingSoon) ? 'Who is going, by city'"));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
