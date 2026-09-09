<?php
/**
 * Guard against the gap that took production down twice on 2026-09-09.
 *
 * Development is SQLite and production is Postgres. SQLite has no real types, so it accepts
 * expressions Postgres refuses outright, which means a whole green test run locally proves nothing
 * about the two statements that matter. Both outages came from the date columns migration 071
 * added to trips:
 *
 *   1. `UPDATE trips SET date_from = visited_on` -- DATE assigned from TEXT. The migration rolled
 *      back, the columns never appeared, and every page reading a trip 500'd.
 *   2. `ORDER BY COALESCE(t.date_from, t.visited_on, t.created_at)` -- COALESCE over DATE and TEXT.
 *      Profiles 500'd while every local test passed.
 *
 * So this test reads the source the way Postgres would: any expression that mixes one of the real
 * DATE columns with one of the TEXT ones has to carry an explicit cast. It is a lint, not a
 * simulation, and it is deliberately narrow -- it knows the specific columns rather than trying to
 * parse SQL.
 *
 *   php tests/driver_sql_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? "\n      $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

/** Columns Postgres holds as DATE, and the ones it holds as TEXT, on the same tables. */
$DATE_COLS = ['date_from', 'date_to'];
$TEXT_COLS = ['visited_on', 'created_at', 'updated_at'];

$sources = array_merge(
    glob($root . '/app/*.php') ?: [],
    glob($root . '/database/migrations/*.pgsql.sql') ?: []
);

$bad = [];
foreach ($sources as $file) {
    $src = (string) file_get_contents($file);
    // Every COALESCE(...) in the file, however it is spelled.
    if (preg_match_all('/COALESCE\s*\(([^()]*)\)/i', $src, $m)) {
        foreach ($m[1] as $args) {
            $hasDate = false; $hasText = false;
            foreach ($DATE_COLS as $c) if (preg_match('/\b' . $c . '\b/i', $args)) $hasDate = true;
            foreach ($TEXT_COLS as $c) if (preg_match('/\b' . $c . '\b/i', $args)) $hasText = true;
            if ($hasDate && $hasText && !preg_match('/CAST\s*\(|::\s*(date|text)/i', $args)) {
                $bad[] = basename($file) . ': COALESCE(' . trim(preg_replace('/\s+/', ' ', $args)) . ')';
            }
        }
    }
}
ok('no COALESCE mixes a DATE column with a TEXT one uncast', $bad === [], implode("\n      ", $bad));

// A migration that fills a DATE column from a TEXT one has to say so, and has to filter first:
// one row of free text aborts the whole migration and takes every other statement in it down.
$assign = [];
foreach (glob($root . '/database/migrations/*.pgsql.sql') ?: [] as $file) {
    $src = (string) file_get_contents($file);
    if (preg_match_all('/\b(date_from|date_to)\s*=\s*([A-Za-z_][A-Za-z0-9_.]*)/i', $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $rhs = $hit[2];
            if (in_array(strtolower($rhs), ['null'], true)) continue;
            $isTextCol = false;
            foreach ($TEXT_COLS as $c) if (stripos($rhs, $c) !== false) $isTextCol = true;
            if (!$isTextCol) continue;
            // The cast has to be right there on the value being assigned.
            if (!preg_match('/' . preg_quote($hit[0], '/') . '\s*::\s*date/i', $src)) {
                $assign[] = basename($file) . ': ' . $hit[0] . ' (no ::date)';
            }
        }
    }
}
ok('every DATE column filled from a TEXT column is cast', $assign === [], implode("\n      ", $assign));

// The two statements that actually broke, pinned by name so a future edit cannot quietly undo them.
$controllers = (string) file_get_contents($root . '/app/controllers.php');
ok('the profile trip order still casts', str_contains($controllers, 'COALESCE(CAST(t.date_from AS TEXT)'));
$mig = (string) file_get_contents($root . '/database/migrations/071_trips_are_plans.pgsql.sql');
ok('migration 071 casts visited_on', str_contains($mig, 'visited_on::date'));
ok('migration 071 filters before casting', str_contains($mig, "visited_on ~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}'"));
ok('migration 071 casts the going dates it moves', str_contains($mig, 'g.date_from::date'));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
