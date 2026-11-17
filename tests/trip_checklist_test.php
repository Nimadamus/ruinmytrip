<?php
/**
 * Before you go checklist on a trip (app/trip_checklist.php, migration 128).
 *
 * What must hold: a city with sourced data gets items built only from that data, each with a link;
 * keys are unique and stable; a city with no data gets nothing; ticks are stored per trip and read back.
 *
 *   php tests/trip_checklist_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:', 'security_salt' => 'test-salt',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/tourist_tax.php';
require BASE_PATH . '/app/city_scams.php';
require BASE_PATH . '/app/tax_study.php';
require BASE_PATH . '/app/before_you_book.php';
require BASE_PATH . '/app/trip_checklist.php';

$fail = 0;
$ok = static function (bool $c, string $m) use (&$fail): void { if (!$c) { $fail++; echo "FAIL: $m\n"; } };

$ams = rmt_trip_checklist_items('amsterdam-netherlands');
$keys = array_column($ams, 'key');
$ok(count($ams) >= 15, 'Amsterdam has at least 15 items, got ' . count($ams));
$ok(count($keys) === count(array_unique($keys)), 'keys are unique');
$ok(in_array('tax', $keys, true) && in_array('tax2027', $keys, true), 'Amsterdam has the tax and the 2027 change');
foreach ($ams as $i) {
    $ok($i['title'] !== '' && $i['url'] !== '' && $i['source'] !== '', 'item complete: ' . $i['key']);
    $ok(str_starts_with($i['url'], 'https://'), 'item links over https: ' . $i['key']);
}
$ok(rmt_trip_checklist_items('amsterdam-netherlands') === $ams, 'items are stable between calls');
$ok(rmt_trip_checklist_items('nowhere-at-all') === [], 'a city with no data has no checklist');
$scams = array_filter($ams, static fn($i) => $i['group'] === 'Scams to know');
$ok(count($scams) <= RMT_TCL_MAX_SCAMS, 'scams capped');

$pdo = db();
$pdo->exec('CREATE TABLE trip_checklist (trip_id INTEGER NOT NULL, item_key TEXT NOT NULL, user_id INTEGER, done_at TEXT NOT NULL, PRIMARY KEY (trip_id, item_key))');
q_run('INSERT INTO trip_checklist (trip_id, item_key, user_id, done_at) VALUES (?, ?, ?, ?)', [7, 'tax', 1, '2026-11-17 10:00:00']);
q_run('INSERT INTO trip_checklist (trip_id, item_key, user_id, done_at) VALUES (?, ?, ?, ?)', [8, 'tax2027', 1, '2026-11-17 10:00:00']);
$ok(rmt_trip_checklist_done(7) === ['tax' => true], 'ticks are read per trip');

echo $fail ? "$fail failed\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
