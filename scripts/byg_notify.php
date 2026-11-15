<?php
/**
 * Before you go updates (app/before_you_go.php): compare each city's tourist tax and official
 * warnings with the last run and email the confirmed signups about what changed. Both live in the
 * repo, so they only change on a deploy: docker/entrypoint.sh runs this on every start. A restart
 * with nothing changed sends nothing.
 *
 *   php scripts/byg_notify.php           record and send
 *   php scripts/byg_notify.php --dry     show what changed since the last run, write and send nothing
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

$dry = in_array('--dry', $argv, true);
$r = rmt_byg_notify(!$dry);
$n = 0;
foreach ($r['changed'] as $topic => $cities) { $n += count($cities); echo "changed $topic: ", implode(', ', $cities), "\n"; }
echo ($dry ? 'dry run: ' : ''), "$n changed, {$r['sent']} emails sent\n";
