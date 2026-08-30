<?php
declare(strict_types=1);

/**
 * Announce everything publishing has queued since the last run.
 *
 * Runs on a schedule (see .github/workflows/indexnow.yml). Publishing writes a row; this does the
 * talking, so no member ever waits on api.indexnow.org and no new page waits a day to be found.
 *
 *   php scripts/indexnow_flush.php            send what is waiting
 *   php scripts/indexnow_flush.php --dry-run  show it, send nothing
 */
define('RMT_NO_AUTOSEED', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$dry = in_array('--dry-run', array_slice($argv, 1), true);
$pending = rmt_seo_pending(500);

if (!$pending) { echo "nothing waiting\n"; exit(0); }
echo count($pending) . " URL(s) waiting\n";
foreach (array_slice($pending, 0, 20) as $u) echo '  ' . $u . "\n";
if (count($pending) > 20) echo '  ... and ' . (count($pending) - 20) . " more\n";

if ($dry) { echo "dry run, nothing sent\n"; exit(0); }

$n = rmt_seo_flush(500);
echo $n > 0 ? "submitted {$n}\n" : "submit failed, rows left pending for the next run\n";
exit($n > 0 ? 0 : 1);
