<?php
/**
 * Create place rows from a curated candidate list, so the enricher has something to enrich.
 *
 * This is not a new ingestion system. Places have always been created by rmt_place_resolve(), the
 * find-or-create the editorial publisher and the backfill both call; all this does is call it from
 * a reviewed list of real venues instead of from an editorial essay. Attributes are not its job --
 * scripts/enrich_places.py and scripts/apply_place_enrichment.php already do that, and they refuse
 * a match they cannot verify.
 *
 * Idempotent by construction: rmt_place_resolve() matches on (destination_id, normalised name), so
 * a second run re-finds the same rows and creates nothing. A name that differs only in accents,
 * punctuation or "The" resolves to the SAME row, which is what stops a curated list from quietly
 * duplicating inventory that arrived by another route.
 *
 *   php scripts/add_places.php                        # dry run
 *   php scripts/add_places.php --apply
 *   php scripts/add_places.php --only=athens-greece   # one destination at a time
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$only  = '';
foreach ($argv as $a) if (str_starts_with($a, '--only=')) $only = substr($a, 7);

$file = BASE_PATH . '/database/enrichment/new_places.json';
if (!is_file($file)) { fwrite(STDERR, "no candidate file at $file\n"); exit(1); }
$doc = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

$editorial = q_one("SELECT id FROM users WHERE role = ?", [RMT_EDITORIAL_ROLE]);
$uid = $editorial ? (int) $editorial['id'] : null;

$created = 0; $existing = 0; $skipped = [];
foreach (($doc['places'] ?? []) as $c) {
    $destSlug = (string) ($c['destination_slug'] ?? '');
    $name = trim((string) ($c['name'] ?? ''));
    $type = (string) ($c['type'] ?? '');
    if ($only !== '' && $destSlug !== $only) continue;

    $d = q_one("SELECT id, name FROM destinations WHERE slug = ?", [$destSlug]);
    if (!$d) { $skipped[] = "$destSlug/$name: no such destination"; continue; }
    if (!in_array($type, RMT_PLACE_TYPES, true)) { $skipped[] = "$destSlug/$name: bad type $type"; continue; }

    // Dedupe against what is already there, on the same key the table is unique on.
    $key = rmt_place_name_key($name);
    $have = q_one("SELECT id, slug, status FROM places WHERE destination_id = ? AND name_key = ?",
                  [(int) $d['id'], $key]);
    if ($have) {
        $existing++;
        printf("  = %-14s %-42s already present (/p/%s)\n", $destSlug, $name, $have['slug']);
        continue;
    }
    if (!$apply) {
        printf("  + %-14s %-42s %s\n", $destSlug, $name, $type);
        $created++;
        continue;
    }
    $id = rmt_place_resolve((int) $d['id'], $type, $name, $uid);
    if (!$id) { $skipped[] = "$destSlug/$name: resolve refused it"; continue; }
    $slug = (string) q_one("SELECT slug FROM places WHERE id = ?", [$id])['slug'];
    printf("  + %-14s %-42s created /p/%s\n", $destSlug, $name, $slug);
    $created++;
}

printf("\n%s: %d %s, %d already present, %d skipped\n",
       $apply ? 'applied' : 'dry run', $created, $apply ? 'created' : 'would be created',
       $existing, count($skipped));
foreach ($skipped as $s) echo "  skip: $s\n";
