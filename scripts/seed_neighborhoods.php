<?php
/**
 * Apply the curated neighborhood identity file, then attach places that match a known alias.
 *
 * Dry run by default, like every other data tool here. Nothing about it is destructive: it creates
 * or updates canonical areas, adds alias spellings that are missing, and fills places.neighborhood_id
 * ONLY where it is currently null and the raw text matches an alias exactly. Raw text is never
 * touched, a place already assigned is never reassigned, and a place whose area we do not
 * recognise is left alone and reported.
 *
 *   php scripts/seed_neighborhoods.php                 # show what would happen
 *   php scripts/seed_neighborhoods.php --apply         # do it
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';
require_once BASE_PATH . '/app/search_suggest.php';
require_once BASE_PATH . '/app/neighborhoods.php';

$apply = in_array('--apply', $argv, true);
$file  = BASE_PATH . '/database/neighborhoods.json';
if (!is_file($file)) { fwrite(STDERR, "no seed file at $file\n"); exit(1); }

$doc = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
$created = 0; $updated = 0; $aliases = 0; $missingDest = [];

foreach (($doc['destinations'] ?? []) as $destSlug => $areas) {
    $d = q_one("SELECT id, name FROM destinations WHERE slug = ?", [$destSlug]);
    if (!$d) { $missingDest[] = $destSlug; continue; }
    foreach ($areas as $a) {
        $name = (string) ($a['name'] ?? '');
        if ($name === '') continue;
        if (!$apply) {
            // A dry run still has to prove the alias table would accept the file: a spelling that
            // already means a different area is a curation mistake, and finding it at apply time
            // in production is finding it too late.
            $clash = null;
            foreach (array_merge([$name], [(string) ($a['local_name'] ?? '')], (array) ($a['aliases'] ?? [])) as $al) {
                $al = trim((string) $al);
                if ($al === '') continue;
                $key = rmt_nb_key($al, (string) $d['name']);
                $own = $key === '' ? null : q_one(
                    "SELECT n.canonical_name FROM neighborhood_aliases x JOIN neighborhoods n ON n.id = x.neighborhood_id
                      WHERE x.destination_id = ? AND x.alias_key = ? AND n.slug <> ?",
                    [(int) $d['id'], $key, rmt_nb_slug($name)]);
                if ($own) $clash = $al . ' -> ' . $own['canonical_name'];
            }
            printf("  %-24s %-34s %s%s\n", $destSlug, $name,
                   (string) ($a['kind'] ?? 'neighborhood'),
                   $clash ? '   CLASH: ' . $clash : '');
            continue;
        }
        $r = rmt_nb_upsert((int) $d['id'], $name, (array) ($a['aliases'] ?? []), [
            'kind'       => (string) ($a['kind'] ?? 'neighborhood'),
            'local_name' => $a['local_name'] ?? null,
            'blurb'      => $a['blurb'] ?? null,
        ]);
        $r['created'] ? $created++ : $updated++;
        $aliases += $r['aliases_added'];
    }
}

if ($missingDest) echo "destinations in the file that do not exist here: " . implode(', ', $missingDest) . "\n";

$att = rmt_nb_attach_places($apply);
printf("\n%s: %d created, %d updated, %d aliases added; %d places attached, %d unmatched\n",
       $apply ? 'applied' : 'dry run', $created, $updated, $aliases, $att['matched'], $att['unmatched']);

if ($att['unresolved']) {
    echo "\nraw values nobody has mapped yet (this being non-empty is normal):\n";
    foreach (array_slice($att['unresolved'], 0, 25, true) as $raw => $n) {
        printf("  %-40s %d\n", mb_strimwidth($raw, 0, 40, '...'), $n);
    }
}
