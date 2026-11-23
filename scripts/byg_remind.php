<?php
/**
 * Before you go reminders (app/before_you_go.php): a confirmed signup with a trip date gets one
 * email 14 days and one 3 days before it. Each is stamped once, so running this twice a day sends
 * nothing twice. Host cron runs it daily inside the container.
 *
 *   php scripts/byg_remind.php         send what is due today
 *   php scripts/byg_remind.php --dry   count what is due, send nothing
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

$dry = in_array('--dry', $argv, true);
$r = rmt_byg_remind(!$dry);
echo date('Y-m-d H:i:s'), ' ', ($dry ? 'dry run: ' : ''), "{$r['due']} due, {$r['sent']} sent\n";
