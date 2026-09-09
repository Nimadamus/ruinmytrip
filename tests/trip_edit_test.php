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
/* The trip validator asks plans.php what a visibility may be, since a trip and a plan are the
   same object since migration 071. */
require BASE_PATH . '/app/plans.php';
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
/* Dates and visibility, which the form did not ask for until trips and plans became one object.
   A trip can be one you have not taken yet, so "when did you visit" was the wrong question. */
$v = rmt_trip_validate(['title' => 'Prague in October', 'body' => str_repeat('a', 40),
                        'date_from' => '2027-10-02', 'date_to' => '2027-10-09', 'visibility' => 'followers']);
$check('a future range is accepted', $v['ok'], true);
$check('the range is stored', ($v['data']['date_from'] ?? '') . '..' . ($v['data']['date_to'] ?? ''), '2027-10-02..2027-10-09');
$check('visibility is kept', $v['data']['visibility'] ?? '', 'followers');

$v = rmt_trip_validate(['title' => 'Half a range', 'body' => str_repeat('a', 40), 'date_from' => '2027-10-02']);
$check('half a range is refused', $v['ok'], false);

$v = rmt_trip_validate(['title' => 'Backwards', 'body' => str_repeat('a', 40),
                        'date_from' => '2027-10-09', 'date_to' => '2027-10-02']);
$check('leaving before arriving is refused', $v['ok'], false);

$v = rmt_trip_validate(['title' => 'A life change', 'body' => str_repeat('a', 40),
                        'date_from' => '2027-01-01', 'date_to' => '2029-01-01']);
$check('a range longer than a year is refused', $v['ok'], false);

$v = rmt_trip_validate(['title' => 'Old form', 'body' => str_repeat('a', 40), 'visited_on' => '2026-05-04']);
$check('an older form with only visited_on still works', $v['ok'], true);
$check('one day becomes a range of one day', ($v['data']['date_from'] ?? '') . '..' . ($v['data']['date_to'] ?? ''), '2026-05-04..2026-05-04');

$v = rmt_trip_validate(['title' => 'Made up privacy', 'body' => str_repeat('a', 40), 'visibility' => 'secret']);
$check('an unknown visibility falls back to public', $v['data']['visibility'] ?? '', 'public');

$new = (string) file_get_contents(BASE_PATH . '/views/trip_new.php');
$edit = (string) file_get_contents(BASE_PATH . '/views/trip_edit.php');
$check('the create form asks both dates', str_contains($new, 'name="date_from"') && str_contains($new, 'name="date_to"'), true);
$check('the edit form asks them too, so an edit cannot strip them', str_contains($edit, 'name="date_from"'), true);
$check('both forms ask who can see it', str_contains($new, 'name="visibility"') && str_contains($edit, 'name="visibility"'), true);

if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }

echo "ALL TRIP EDIT TESTS PASS\n";
