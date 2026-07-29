<?php
/**
 * Regression test for a production-only search bug: `LIKE` is case-insensitive on SQLite (local
 * dev) but case-SENSITIVE on Postgres (production) -- see app/controllers.php explore().
 * A search for "kyoto" silently returned zero results in production against a "Kyoto" row, while
 * working fine in every local test, because SQLite can't reproduce the bug at all.
 *
 * Verified directly against production Postgres during the fix (not reproducible here):
 *   SELECT name FROM destinations WHERE name LIKE '%kyoto%'  -> []
 *   SELECT name FROM destinations WHERE name LIKE '%Kyoto%'  -> [('Kyoto',)]
 *
 * Because the bug is driver-specific and this suite only runs against SQLite, a runtime query
 * can't tell "fixed" apart from "broken" -- SQLite's LIKE masks it either way. The real guard is
 * static: every user-facing search column in explore() must be wrapped in LOWER() on both sides
 * so the comparison is case-insensitive regardless of which engine runs it.
 *
 * search() itself was rewritten in migration 015 to use real full-text search (Postgres
 * tsvector/ts_rank, SQLite FTS5) instead of LIKE for destinations/trips/reviews/guides -- both
 * engines' text-search normalizes case by construction, so the LOWER()-wrapping requirement no
 * longer applies there. It still asserts driver-branching + FTS usage so a regression back to
 * bare LIKE (which would reintroduce the case bug) fails loudly. People search stays LIKE-based
 * on purpose (substring matching on short usernames), so it's still checked here.
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

// explore() still does bare LIKE search: every column compared against user input must be
// LOWER()-wrapped, or the case-sensitivity bug is back.
foreach (['d.name', 'd.country', 'd.summary'] as $col) {
    $wrapped = preg_match('/LOWER\(\s*' . preg_quote($col, '/') . '\s*\)\s*LIKE/i', $explore) === 1;
    $check("explore(): {$col} is LOWER()-wrapped before LIKE", $wrapped);
}
$check('explore(): search term is lowercased before binding', preg_match('/mb_strtolower\(\$qs\)/', $explore) === 1);

// search() must still branch per driver and use each engine's real full-text mechanism -- a
// regression to bare "LIKE ?" against these content columns would silently reintroduce the bug.
$check('search(): branches on db_driver', preg_match('/db_driver.*pgsql/s', $search) === 1);
$check('search(): pgsql path uses tsvector/ts_rank', preg_match('/search_vector\s*@@|ts_rank/', $search) === 1);
$check('search(): sqlite path uses FTS5 MATCH', preg_match('/_fts\s+MATCH/', $search) === 1);
$check('search(): people search term is lowercased before LIKE binding', preg_match('/mb_strtolower\(\$qs\)/', $search) === 1);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL SEARCH CASE-SENSITIVITY TESTS PASS\n";
