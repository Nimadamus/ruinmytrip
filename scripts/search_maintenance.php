<?php
declare(strict_types=1);

/**
 * Keep the search index in step. Idempotent, cheap, safe to run on every boot.
 *
 * Two jobs:
 *
 *   1. Fill in name_norm wherever it is missing or no longer matches what the current normaliser
 *      produces. That second half matters: the normaliser will gain characters over time, and a
 *      row normalised by an older version has to be redone or it quietly stops matching.
 *   2. Seed destination aliases from database/search/aliases.json — Wien, Praha, NYC. Adding an
 *      alias that already exists is a no-op, and an alias for a destination that does not exist is
 *      skipped rather than treated as an error, because the file is edited by hand and a typo in
 *      it should not fail a deploy.
 *
 * Neither job invents anything: a normalised name is derived from a name we already have, and an
 * alias is a name a person wrote down in a reviewed file.
 *
 * Usage:  php scripts/search_maintenance.php [--quiet]
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$quiet = in_array('--quiet', array_slice($argv, 1), true);
$say = static function (string $m) use ($quiet): void { if (!$quiet) echo $m, PHP_EOL; };

$n = rmt_search_backfill_norm();
$say(sprintf('search: normalised %d destinations, %d places, %d aliases',
     $n['destinations'], $n['places'], $n['aliases']));

$file = BASE_PATH . '/database/search/aliases.json';
$added = 0;
$missing = [];
if (is_file($file)) {
    $doc = json_decode((string) file_get_contents($file), true) ?: [];
    foreach ($doc as $destName => $aliases) {
        if ($destName === '' || $destName[0] === '_' || !is_array($aliases)) continue;
        $d = q_one('SELECT id FROM destinations WHERE name = ?', [$destName]);
        if (!$d) { $missing[] = $destName; continue; }
        foreach ($aliases as $alias) {
            if (!is_string($alias)) continue;
            if (rmt_search_add_alias('destination', (int) $d['id'], $alias, 'local_name')) $added++;
        }
    }
}
$say(sprintf('search: %d aliases added%s', $added,
     $missing ? ' (' . count($missing) . ' alias keys match no destination: ' . implode(', ', array_slice($missing, 0, 6)) . ')' : ''));

// A place whose name was just written by enrichment needs its normalised form too; the backfill
// above already covered it, so this is only a report.
$un = (int) (q_one("SELECT COUNT(*) c FROM places WHERE name_norm IS NULL OR name_norm = ''")['c'] ?? 0);
if ($un > 0) $say('search: WARNING ' . $un . ' places still have no normalised name');
