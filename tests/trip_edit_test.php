<?php
/**
 * Regression tests for trip edit/delete: ownership boundaries, field validation, and the
 * type="url" pre-fill bug (a relative-path cover URL -- copied from a destination's fallback
 * photo -- silently blocked the whole edit form because the browser's native URL constraint
 * validation rejects non-absolute values, with no visible error).
 *
 * Runs against a throwaway in-memory SQLite DB. No network, no fixtures on disk.
 *
 *   php tests/trip_edit_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/controllers.php';

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, hero_url TEXT)');
$pdo->exec("INSERT INTO destinations (id, slug, hero_url) VALUES (1, 'oaxaca-mexico', '/media/abc123.jpg')");

$fail = 0;
$check = function (string $name, $got, $expect) use (&$fail) {
    $ok = $got === $expect;
    printf("  [%s] %-55s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
        var_export($expect, true), var_export($got, true));
    if (!$ok) $fail++;
};

echo "-- editable_url_value(): only ever pre-fill a value the user could type into type=\"url\" --\n";
$check('absolute https:// URL kept', editable_url_value('https://example.com/x.jpg'), 'https://example.com/x.jpg');
$check('relative /media/ path (destination fallback) -> blank', editable_url_value('/media/abc123.jpg'), '');
$check('bare http:// rejected (form requires https)', editable_url_value('http://example.com/x.jpg'), '');
$check('null -> blank', editable_url_value(null), '');
$check('empty string -> blank', editable_url_value(''), '');

echo "\n-- rmt_trip_validate(): field rules --\n";
$v = rmt_trip_validate(['title' => 'Valid Title', 'body' => str_repeat('a', 25), 'destination_id' => '1', 'cover_url' => '', 'visited_on' => '']);
$check('valid input passes', $v['ok'], true);

$v = rmt_trip_validate(['title' => 'Hi', 'body' => str_repeat('a', 25)]);
$check('title under 5 chars fails', $v['ok'], false);

$v = rmt_trip_validate(['title' => 'Valid Title', 'body' => 'too short']);
$check('body under 20 chars fails', $v['ok'], false);

$v = rmt_trip_validate(['title' => 'Valid Title', 'body' => str_repeat('a', 25), 'cover_url' => '/media/abc123.jpg']);
$check('relative cover_url rejected server-side too', $v['ok'], false);

$v = rmt_trip_validate(['title' => 'Valid Title', 'body' => str_repeat('a', 25), 'cover_url' => 'https://example.com/x.jpg']);
$check('absolute https:// cover_url accepted', $v['ok'], true);

$v = rmt_trip_validate(['title' => 'Valid Title', 'body' => str_repeat('a', 25), 'destination_id' => '999']);
$check('nonexistent destination_id fails', $v['ok'], false);

echo "\n-- rmt_trip_can_edit(): ownership boundary --\n";
$trip = ['user_id' => 5];
$check('owner can edit', rmt_trip_can_edit($trip, ['id' => 5]), true);
$check('a different user cannot edit', rmt_trip_can_edit($trip, ['id' => 6]), false);
$check('logged-out user cannot edit', rmt_trip_can_edit($trip, null), false);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL TRIP EDIT TESTS PASS\n";
