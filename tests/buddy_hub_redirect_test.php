<?php
// The bare buddy paths must 301 to the hub, and must be matched before the /{slug} landing routes.
$root = dirname(__DIR__);
$index = file_get_contents($root . '/public/index.php');
$fail = 0;
$check = function (bool $ok, string $m) use (&$fail) { echo ($ok ? 'ok   ' : 'FAIL ') . $m . "\n"; if (!$ok) $fail++; };
preg_match("/'(#\^\/\(\?:travel-buddies[^']+)', 'buddy_landing_hub_redirect'/", $index, $m);
$check(isset($m[1]), 'hub redirect route exists');
foreach (['/travel-buddies', '/travel-buddies/', '/travel-buddy', '/find-a-travel-buddy'] as $p) {
    $check((bool) preg_match($m[1], $p), "$p redirects");
}
$check(!preg_match($m[1], '/travel-buddies/greece'), 'country landing untouched');
$check(str_contains($index, "'#^/cruise-buddies/?$#'"), 'cruise hub redirect route exists');
$land = file_get_contents($root . '/app/buddy_landing.php');
$check(str_contains($land, "function buddy_landing_hub_redirect"), 'handler defined');
$check(str_contains($land, "function buddy_landing_cruise_hub_redirect"), 'cruise handler defined');
exit($fail ? 1 : 0);
