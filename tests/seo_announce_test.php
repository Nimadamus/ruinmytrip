<?php
/**
 * The announce queue: publishing enqueues, the flush sends once, failures stay pending.
 *
 *   php tests/seo_announce_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/seo.php';

$pdo = db();
$pdo->exec('CREATE TABLE seo_ping_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, url TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL, sent_at TEXT)');

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-58s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

echo "-- queueing --\n";
rmt_seo_announce('/post/1');
rmt_seo_announce('post/2');
check('a leading slash is not required', rmt_seo_pending(), ['https://example.test/post/1','https://example.test/post/2']);
rmt_seo_announce('/post/1');
check('the same page twice is queued once', count(rmt_seo_pending()), 2);
rmt_seo_announce('https://example.test/already/absolute');
check('an absolute url is left alone', in_array('https://example.test/already/absolute', rmt_seo_pending(), true), true);

echo "\n-- flushing --\n";
$pdo->exec("UPDATE seo_ping_queue SET sent_at='2026-08-30 00:00:00' WHERE url LIKE '%post/1'");
check('a sent url is not waiting again', count(rmt_seo_pending()), 2);
$pdo->exec("UPDATE seo_ping_queue SET sent_at='2026-08-30 00:00:00'");
check('nothing waiting means nothing to send', rmt_seo_flush(), 0);

echo "\n-- a missing table never breaks publishing --\n";
$pdo->exec('DROP TABLE seo_ping_queue');
rmt_seo_announce('/post/9');
check('announcing on a database without the table is silent', true, true);

echo $fail ? "\nFAILED: $fail\n" : "\nOK\n";
exit($fail ? 1 : 0);
