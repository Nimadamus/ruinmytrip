<?php
/**
 * Import real places for one city from a provider.
 *
 * Usage:
 *   php scripts/import_places.php --city=lisbon-portugal --type=restaurant --limit=40 --dry-run
 *   php scripts/import_places.php --city=lisbon-portugal --type=all --limit=30
 *
 * Options:
 *   --city    destination slug (required)
 *   --type    restaurant | hotel | attraction | experience | all   (default: all)
 *   --limit   how many per type                                    (default: 40)
 *   --km      radius around the city point                         (default: 12)
 *   --provider                                                     (default: openstreetmap)
 *   --dry-run report what would happen and write nothing
 *
 * It is safe to run twice. The second run matches on the provider's own record id and updates
 * rather than duplicating, which is the whole point of storing source_ref.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';


$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) continue;
    $bit = substr($arg, 2);
    [$k, $v] = str_contains($bit, '=') ? explode('=', $bit, 2) : [$bit, '1'];
    $opts[$k] = $v;
}

$citySlug = (string) ($opts['city'] ?? '');
$type     = (string) ($opts['type'] ?? 'all');
$limit    = max(1, (int) ($opts['limit'] ?? 40));
$km       = (float) ($opts['km'] ?? 12);
$provider = (string) ($opts['provider'] ?? 'openstreetmap');
$dry      = isset($opts['dry-run']);

if ($citySlug === '') { fwrite(STDERR, "--city is required\n"); exit(2); }
if (!isset(rmt_place_providers()[$provider])) { fwrite(STDERR, "unknown provider: $provider\n"); exit(2); }

$dest = q_one('SELECT * FROM destinations WHERE slug = ?', [$citySlug]);
if (!$dest) { fwrite(STDERR, "no such city: $citySlug\n"); exit(2); }

$types = $type === 'all' ? RMT_PLACE_TYPES : [$type];
foreach ($types as $t) {
    if (!in_array($t, RMT_PLACE_TYPES, true)) { fwrite(STDERR, "unknown type: $t\n"); exit(2); }
}

printf("%s, %s%s\n", $dest['name'], rmt_place_providers()[$provider]['name'], $dry ? ' (dry run)' : '');

$totalCreated = 0; $totalUpdated = 0; $totalSkipped = 0;
foreach ($types as $t) {
    $pull = rmt_osm_places_for_destination($dest, $t, $limit, $km);
    if (!$pull['ok']) {
        printf("  %-12s FAILED: %s\n", $t, (string) $pull['error']);
        continue;
    }
    $res = rmt_place_import_batch((int) $dest['id'], $pull['rows'], $dry);

    // Aliases only once the place exists, and only for rows that actually landed.
    if (!$dry) {
        foreach ($res['details'] as $i => $d) {
            $ref = (string) ($pull['rows'][$i]['source_ref'] ?? '');
            if (!$d['place_id'] || $ref === '') continue;
            foreach ($pull['aliases'][$ref] ?? [] as $alias) {
                rmt_place_alias_add((int) $d['place_id'], $alias, $provider);
            }
        }
    }

    $totalCreated += $res['created'];
    $totalUpdated += $res['updated'];
    $totalSkipped += $res['skipped'];
    printf("  %-12s %d from the provider, %d new, %d updated, %d skipped\n",
           $t, count($pull['rows']), $res['created'], $res['updated'], $res['skipped']);
    foreach (array_slice(array_unique($res['errors']), 0, 3) as $e) printf("       ! %s\n", $e);
}

printf("total: %d new, %d updated, %d skipped\n", $totalCreated, $totalUpdated, $totalSkipped);
