<?php
/**
 * Seed a batch of cities (app/hubs.php, migration 125).
 *
 *   php scripts/seed_hub_cities.php database/hub_seed/batch_001.json            dry run: says what it would do
 *   php scripts/seed_hub_cities.php database/hub_seed/batch_001.json --apply    writes
 *   php scripts/seed_hub_cities.php database/hub_seed/batch_001.json --undo     removes this batch's cities that are still untouched
 *
 * A seeded city gets a name, a country and coordinates, and seeded_at. Nothing is written about
 * it. It is noindex and left out of public lists until it meets the content threshold, and it is
 * there to be picked when somebody posts a trip or checks in.
 *
 * Safe to run twice: a slug that already exists is skipped, never updated. --undo deletes only
 * cities that this file seeded AND that nobody has used: no trip, buddy post, check-in, review,
 * place or follower. Anything a member has touched stays.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('RMT_NO_AUTOSEED', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$file = $argv[1] ?? '';
$mode = in_array('--apply', $argv, true) ? 'apply' : (in_array('--undo', $argv, true) ? 'undo' : 'dry');
$path = is_file($file) ? $file : BASE_PATH . '/' . ltrim($file, '/');
if ($file === '' || !is_file($path)) { fwrite(STDERR, "usage: php scripts/seed_hub_cities.php <batch.json> [--apply|--undo]\n"); exit(2); }
$data = json_decode((string) file_get_contents($path), true);
$cities = is_array($data['cities'] ?? null) ? $data['cities'] : [];
if (!$cities) { fwrite(STDERR, "no cities in $file\n"); exit(2); }
if (count($cities) > 50) { fwrite(STDERR, "a batch is 50 cities at most; this one has " . count($cities) . "\n"); exit(2); }

$now = date('Y-m-d H:i:s');
$hasNorm = true;
try { q_one('SELECT name_norm FROM destinations LIMIT 1'); } catch (\PDOException $e) { $hasNorm = false; }
$counts = ['new' => 0, 'exists' => 0, 'invalid' => 0, 'removed' => 0, 'kept' => 0];

foreach ($cities as $c) {
    $slug = (string) ($c['slug'] ?? ''); $name = trim((string) ($c['name'] ?? '')); $country = trim((string) ($c['country'] ?? ''));
    $lat = $c['lat'] ?? null; $lng = $c['lng'] ?? null;
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || $name === '' || $country === ''
        || !is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
        $counts['invalid']++; echo "INVALID  $slug\n"; continue;
    }
    $row = q_one('SELECT id, seeded_at FROM destinations WHERE slug = ?', [$slug]);

    if ($mode === 'undo') {
        if (!$row || empty($row['seeded_at'])) { continue; }
        $id = (int) $row['id'];
        $used = 0;
        foreach (['trips' => 'destination_id', 'buddy_posts' => 'destination_id', 'active_checkins' => 'destination_id',
                  'reviews' => 'destination_id', 'places' => 'destination_id'] as $t => $col) {
            $used += (int) (q_one("SELECT COUNT(*) n FROM $t WHERE $col = ?", [$id])['n'] ?? 0);
        }
        if ($used > 0) { $counts['kept']++; echo "KEPT     $slug (in use)\n"; continue; }
        q_run('DELETE FROM destinations WHERE id = ? AND seeded_at IS NOT NULL', [$id]);
        $counts['removed']++; echo "REMOVED  $slug\n";
        continue;
    }

    if ($row) { $counts['exists']++; echo "EXISTS   $slug\n"; continue; }
    $counts['new']++;
    if ($mode === 'dry') { echo "WOULD ADD $slug  ($name, $country)\n"; continue; }
    $cols = ['slug', 'name', 'country', 'lat', 'lng', 'category', 'seeded_at'];
    $args = [$slug, $name, $country, (float) $lat, (float) $lng, 'city', $now];
    if ($hasNorm && function_exists('rmt_search_norm')) { $cols[] = 'name_norm'; $args[] = rmt_search_norm($name); }
    q_run('INSERT INTO destinations (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', $args);
    echo "ADDED    $slug\n";
}

echo "\n$mode: " . json_encode($counts) . "\n";
if ($mode === 'dry') echo "Nothing was written. Run again with --apply.\n";
