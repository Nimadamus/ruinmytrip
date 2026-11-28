<?php
declare(strict_types=1);

/**
 * "New trap reported" emails for members who saved a city to their trip checklist
 * (app/trap_alerts.php). Safe to run as often as wanted: each story is mailed to a member once.
 *
 * Usage:
 *   php scripts/send_trap_alerts.php --dry-run    count who would get what, send nothing
 *   php scripts/send_trap_alerts.php              send for real
 */

define('RMT_NO_AUTOSEED', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$dryRun = in_array('--dry-run', array_slice($argv, 1), true);
$r = rmt_trap_alerts_run($dryRun);
echo ($dryRun ? '[dry run] ' : '') . "members {$r['members']}, emails {$r['emails']}, sent {$r['sent']}, skipped {$r['skipped']}" . PHP_EOL;
