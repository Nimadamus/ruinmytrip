<?php
/**
 * One word per idea, in the words a reader sees.
 *
 * The architecture calls an itinerary line an "activity", because that is what the table is called
 * and renaming a table is a migration with no user visible benefit. The product calls it a PLAN,
 * and the moment a page says "activity" the reader is holding two words for one thing and has to
 * work out whether they mean the same. They always do, which is exactly why it is confusing.
 *
 * The vocabulary, decided once:
 *
 *   trip      a city and two dates, belonging to one traveler, possibly planned with others
 *   plan      one line on a trip's itinerary. NEVER "activity" in reader-facing copy
 *   meetup    a public gathering posted on its own, with no trip behind it
 *   place     a venue this site holds a record for
 *   traveler  a person. NEVER "user" in reader-facing copy
 *
 * Admin screens and legal pages are exempt: an admin is reading about the system, and a terms page
 * has to use the words a lawyer would.
 *
 *   php tests/vocabulary_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; } else { $fail++; echo "FAIL: $what\n"; }
}

/** The words a reader actually sees: php blocks, echo tags and markup removed. */
function rmt_view_text(string $file): string {
    $s = (string) file_get_contents($file);
    $s = (string) preg_replace('/<\?php.*?\?>/s', ' ', $s);
    $s = (string) preg_replace('/<\?=.*?\?>/s', ' ', $s);
    $s = (string) preg_replace('/<\?php.*$/s', ' ', $s);       // an unclosed trailing block
    $s = (string) preg_replace('/<[^>]+>/', ' ', $s);
    return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$exempt = static fn(string $rel): bool =>
    str_starts_with($rel, 'admin') || str_starts_with($rel, 'legal/') || str_contains($rel, 'unsubscribe');

$banned = [
    '/\bactivit(y|ies)\b/i' => 'calls a plan an "activity"',
];

$offenders = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/views'));
$seen = 0;
foreach ($files as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') continue;
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($f->getPathname(), strlen($root . '/views/')));
    if ($exempt($rel)) continue;
    $seen++;
    $text = rmt_view_text($f->getPathname());
    foreach ($banned as $re => $why) {
        if (preg_match($re, $text, $m)) {
            $offenders[] = $rel . ': ' . $why . ' (' . trim($m[0]) . ')';
        }
    }
}

ok($seen > 40, "the audit actually read the views ($seen files)");
ok($offenders === [], 'no page shows a reader a word the product does not use'
   . ($offenders ? "\n    " . implode("\n    ", $offenders) : ''));

/* The other half: the words the product DOES use have to appear, or the vocabulary above is a
   description of a site that no longer exists. */
$all = '';
foreach (['_trip_plan.php', 'activity_show.php', 'meetups_index.php'] as $v) {
    if (is_file($root . '/views/' . $v)) $all .= rmt_view_text($root . '/views/' . $v);
}
ok(preg_match('/\bplans?\b/i', $all) === 1 || str_contains(strtolower($all), 'plan'),
   'the itinerary calls its lines plans');
ok(str_contains(strtolower($all), 'meetup'), 'and a meetup is still called a meetup');

echo "vocabulary_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
