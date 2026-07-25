<?php
/**
 * Regression tests for guide authoring: field validation, the ownership boundary, unique-slug
 * collision handling, and the stored-XSS risk in guide_show.php (traveler-submitted body must
 * never be trusted as raw HTML the way editorial/seed content is).
 *
 * Runs against a throwaway in-memory SQLite DB. No network, no fixtures on disk.
 *
 *   php tests/guide_edit_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
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
$pdo->exec("INSERT INTO destinations (id, slug, hero_url) VALUES (1, 'kyoto-japan', '/media/abc123.jpg')");
$pdo->exec('CREATE TABLE guides (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, slug TEXT UNIQUE,
                                  title TEXT, summary TEXT, body TEXT, cover_url TEXT, premium INT DEFAULT 0,
                                  status TEXT DEFAULT "published", created_at TEXT, updated_at TEXT)');

$fail = 0;
$check = function (string $name, $got, $expect) use (&$fail) {
    $ok = $got === $expect;
    printf("  [%s] %-55s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
        var_export($expect, true), var_export($got, true));
    if (!$ok) $fail++;
};

$longBody = str_repeat('This is a real guide with enough detail to be useful. ', 3); // > 100 chars

echo "-- rmt_guide_validate(): field rules --\n";
$v = rmt_guide_validate(['title' => 'A Real Guide Title', 'summary' => 'A useful one-line summary.', 'body' => $longBody, 'destination_id' => '1']);
$check('valid input passes', $v['ok'], true);

$v = rmt_guide_validate(['title' => 'Hi', 'summary' => 'A useful one-line summary.', 'body' => $longBody]);
$check('title under 5 chars fails', $v['ok'], false);

$v = rmt_guide_validate(['title' => 'A Real Guide Title', 'summary' => 'short', 'body' => $longBody]);
$check('summary under 10 chars fails', $v['ok'], false);

$v = rmt_guide_validate(['title' => 'A Real Guide Title', 'summary' => 'A useful one-line summary.', 'body' => 'too short']);
$check('body under 100 chars fails', $v['ok'], false);

$v = rmt_guide_validate(['title' => 'A Real Guide Title', 'summary' => 'A useful one-line summary.', 'body' => $longBody, 'cover_url' => '/media/abc123.jpg']);
$check('relative cover_url rejected', $v['ok'], false);

$v = rmt_guide_validate(['title' => 'A Real Guide Title', 'summary' => 'A useful one-line summary.', 'body' => $longBody, 'destination_id' => '999']);
$check('nonexistent destination_id fails', $v['ok'], false);

// The exact scenario a stored-XSS attempt would take: HTML/script in the body must survive
// validation as plain text (rejection is not the control here -- escaped rendering is; see
// guide_show.php's $isEd ? raw : nl2br(e(...)) split, which this test can't render but the
// validator must at least not choke on or strip differently than any other body).
$xssBody = '<script>alert(1)</script> ' . $longBody;
$v = rmt_guide_validate(['title' => 'A Real Guide Title', 'summary' => 'A useful one-line summary.', 'body' => $xssBody]);
$check('body with markup still just text data (no special-casing)', $v['ok'] && $v['data']['body'] === $xssBody, true);

echo "\n-- rmt_guide_can_edit(): ownership boundary --\n";
$guide = ['user_id' => 5];
$check('owner can edit', rmt_guide_can_edit($guide, ['id' => 5]), true);
$check('a different user cannot edit', rmt_guide_can_edit($guide, ['id' => 6]), false);
$check('logged-out user cannot edit', rmt_guide_can_edit($guide, null), false);

echo "\n-- rmt_guide_unique_slug(): collision handling --\n";
$pdo->exec("INSERT INTO guides (id, user_id, slug, title, status, created_at) VALUES (1, 1, 'kyoto-in-four-days', 'Kyoto in Four Days', 'published', '2026-01-01')");
$check('fresh title -> base slug', rmt_guide_unique_slug('A Brand New Guide'), 'a-brand-new-guide');
$check('colliding title -> -2 suffix', rmt_guide_unique_slug('Kyoto in Four Days'), 'kyoto-in-four-days-2');
$check('editing the original row itself keeps its own slug', rmt_guide_unique_slug('Kyoto in Four Days', 1), 'kyoto-in-four-days');

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL GUIDE EDIT TESTS PASS\n";
