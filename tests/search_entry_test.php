<?php
/**
 * The pages search actually lands on, and the one thing they have to do.
 *
 * Every page that earns an impression on this site answers a question about a building, a price or
 * a city, and then has to offer the reader the thing the other ten results cannot: who is going
 * there, and when. There is exactly ONE component for that, and this test exists because for a few
 * hours there were two, both on the same page, and neither was the one being maintained.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));

$pass = 0; $fail = 0;
function ok(string $what, $got, $want = true): void {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  [PASS] $what\n"; }
    else { $fail++; echo "  [FAIL] $what  expected=" . var_export($want, true)
                      . " got=" . var_export($got, true) . "\n"; }
}

$views = ['blog_show', 'guide_show', 'place_show', 'review_show', 'trip_show', 'post_show'];
$src = [];
foreach ($views as $v) $src[$v] = (string) file_get_contents(BASE_PATH . "/views/$v.php");

echo "\n-- one component, on every page search lands on --\n";
foreach ($views as $v) {
    $uses = substr_count($src[$v], '_meet_travelers.php') + substr_count($src[$v], '_dest_social_cta.php');
    ok("$v offers it exactly once", $uses, 1);
}

echo "\n-- and it is offered before the reader has decided --\n";
/* A phone screen is about 800px and these pages run to 4,000 and 8,000. The offer sitting in the
   last third is the same as not making it, which is what the guides were doing at 57% of the page. */
foreach (['guide_show' => 'summary', 'place_show' => '<h1'] as $v => $anchor) {
    $at  = strpos($src[$v], '_meet_travelers.php');
    $top = strpos($src[$v], $anchor);
    ok("$v offers it near the top", $at !== false && $top !== false && $at - $top < 1500, true);
}

echo "\n-- what the component may and may not say --\n";
$cta = (string) file_get_contents(BASE_PATH . '/views/_dest_social_cta.php');
ok('it asks about the city',      str_contains($cta, 'Going to'), true);
ok('it offers the trip form',     str_contains($cta, 'trip/new'), true);
ok('it offers the travelers hub', str_contains($cta, '/travelers'), true);
ok('it carries a campaign window when one is running', str_contains($cta, 'rmt_acq_window_near'), true);
ok('it says who can see your dates', str_contains($cta, 'Nobody sees your dates until you post them'), true);
/* The honest number on most cities is zero, so it states none at all. */
ok('it claims no traveler count', (bool) preg_match('/\d+\s*(travelers|people|members)/i', $cta), false);
ok('and nothing is called trending', (bool) preg_match('/trending/i', $cta), false);
ok('it renders nothing without a city',
   str_contains($cta, "if (\$dsSlug === '' || \$dsName === '') return;"), true);

echo "\n-- the adapter keeps the old call signature --\n";
$adapter = (string) file_get_contents(BASE_PATH . '/views/_meet_travelers.php');
ok('callers still pass destSlug',  str_contains($adapter, '$destSlug'), true);
ok('callers still pass destName',  str_contains($adapter, '$destName'), true);
ok('and it delegates rather than duplicating', str_contains($adapter, '_dest_social_cta.php'), true);
ok('the old text strip is gone',   str_contains($adapter, 'class="callout"'), false);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL SEARCH ENTRY TESTS PASS ({$pass})\n";
