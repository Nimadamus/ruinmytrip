<?php
/**
 * Regression test for a production-only search bug: `LIKE` is case-insensitive on SQLite (local
 * dev) but case-SENSITIVE on Postgres (production) -- see app/controllers.php explore()/search().
 * A search for "kyoto" silently returned zero results in production against a "Kyoto" row, while
 * working fine in every local test, because SQLite can't reproduce the bug at all.
 *
 * Verified directly against production Postgres during the fix (not reproducible here):
 *   SELECT name FROM destinations WHERE name LIKE '%kyoto%'  -> []
 *   SELECT name FROM destinations WHERE name LIKE '%Kyoto%'  -> [('Kyoto',)]
 *
 * Because the bug is driver-specific and this suite only runs against SQLite, a runtime query
 * can't tell "fixed" apart from "broken" -- SQLite's LIKE masks it either way. The real guard is
 * static: every user-facing search column must be wrapped in LOWER() on both sides so the
 * comparison is case-insensitive regardless of which engine runs it.
 *
 *   php tests/search_case_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

$src = file_get_contents(dirname(__DIR__) . '/app/controllers.php');

function extract_function(string $src, string $name): string {
    $start = strpos($src, "function {$name}(");
    if ($start === false) return '';
    $depth = 0; $i = strpos($src, '{', $start); $bodyStart = $i;
    for (; $i < strlen($src); $i++) {
        if ($src[$i] === '{') $depth++;
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) return substr($src, $bodyStart, $i - $bodyStart + 1); }
    }
    return '';
}

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) {
    printf("  [%s] %s\n", $ok ? 'PASS' : 'FAIL', $name);
    if (!$ok) $fail++;
};

$explore = extract_function($src, 'explore');
$search = extract_function($src, 'search');

$check('explore() found in controllers.php', $explore !== '');
$check('search() found in controllers.php', $search !== '');

// Every column compared against user search input must be LOWER()-wrapped. A bare "column LIKE"
// on one of these specific columns means the case-sensitivity bug is back.
$mustBeLowered = [
    'explore()' => ['d.name', 'd.country', 'd.summary'],
    'search()'  => ['name', 'country', 'summary', 't.title', 't.body', 'title', 'summary'],
];

foreach (['explore()' => $explore, 'search()' => $search] as $label => $body) {
    foreach ($mustBeLowered[$label] as $col) {
        $wrapped = preg_match('/LOWER\(\s*' . preg_quote($col, '/') . '\s*\)\s*LIKE/i', $body) === 1;
        $check("{$label}: {$col} is LOWER()-wrapped before LIKE", $wrapped);
    }
    // The needle itself must be lowercased in PHP too -- LOWER(col) LIKE '%Kyoto%' would still
    // fail to match "kyoto" the column lowercased but the needle not.
    $needleLowered = preg_match('/mb_strtolower\(\$qs\)/', $body) === 1;
    $check("{$label}: search term is lowercased before binding", $needleLowered);
}

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL SEARCH CASE-SENSITIVITY TESTS PASS\n";
