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

/* One layout rule that is worth a gate rather than a memory.

   A grid item defaults to min-width:auto, so it refuses to be narrower than its widest indivisible
   content: one map, one long word, one wide row, and the whole column blows past the viewport. A
   city page at 390px was laying its content out at 1174px and letting the screen clip it, which
   looks like a map with no margin rather than like a bug, so nobody reports it. */
$css = (string) file_get_contents($root . '/public/assets/css/app.css');
ok(preg_match('/\.grid\s*>\s*\*\s*\{[^}]*min-width\s*:\s*0/', $css) === 1,
   'grid children are allowed to shrink');

/* Every control that uploads photographs takes more than one at a time, and says so. The trip
   one auto submits on choosing a file, so it is the one place where a silent control looks like a
   broken one: choosing three pictures on a phone showed nothing at all until they had arrived. */
foreach (['trip_show.php', 'activity_show.php', 'review_new.php', 'review_edit.php',
          'trip_new.php', 'trip_edit.php'] as $v) {
    $src = (string) file_get_contents($root . '/views/' . $v);
    if (!str_contains($src, 'type="file"')) continue;
    ok(str_contains($src, 'name="photos[]"') && str_contains($src, 'multiple'),
       "$v takes more than one photograph at a time");
    ok(!preg_match('/>\s*Add a photo\s*</', $src), "$v does not call a multiple control singular");
}
$tripSrc = (string) file_get_contents($root . '/views/trip_show.php');
ok(str_contains($tripSrc, 'data-photo-label') && str_contains($tripSrc, 'Adding '),
   'and the one that submits by itself says what it is doing while it does it');
ok(str_contains($tripSrc, 'Six photos is the most'),
   'a trip at the cap says so rather than offering a control that will refuse');

echo "vocabulary_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
