<?php
declare(strict_types=1);
/**
 * One-time backfill: resolve every existing review's free-text `subject_name` into a `places` row
 * (migration 040) and point `reviews.place_id` at it.
 *
 * Safe to re-run: rmt_place_resolve() is find-or-create on (destination_id, name_key), so a second
 * pass re-finds the same rows and rewrites the same ids. Nothing is deleted and `subject_name` is
 * never touched — the author's own words stay exactly as written.
 *
 * What is deliberately SKIPPED:
 *  - subject_type 'destination'. The destination is the container, not a place inside it; creating
 *    a "Kyoto" place inside Kyoto would double-count the same reviews on two pages.
 *  - reviews with no destination, or a blank subject name. There is nothing to resolve.
 *  - status 'removed'. Moderated-away content must not conjure a page.
 *
 * Editorial reviews ARE included. A place is an entity, not an opinion — and rmt_place_stats()
 * excludes editorial from every average by role, so an editorial review can start a place's page
 * without ever contributing to its rating.
 *
 * --apply runs as ONE transaction, so a failure part-way writes nothing and the script can simply
 * be re-run. The consequence on Postgres is that a concurrent writer publishing the first review of
 * a place mid-run can lose the unique-index race, abort this transaction, and exit 1 with nothing
 * committed — re-running picks up the row the other writer created. Nothing is corrupted either
 * way; it just means the run is cheapest against a quiet site.
 *
 * Usage: php scripts/backfill_places.php [--apply]
 *        Without --apply it reports what it would do and writes nothing.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/app/controllers.php';   // dest_by_id(), authors_fill()

$apply = in_array('--apply', $argv, true);

$rows = q_all("SELECT id, user_id, destination_id, subject_type, subject_name, place_id
                 FROM reviews
                WHERE status <> 'removed' AND destination_id IS NOT NULL
                ORDER BY id");

$resolved = $skipped = $alreadySet = 0;
$byPlace = [];

if ($apply) db()->beginTransaction();
try {
    foreach ($rows as $r) {
        $name = trim((string) $r['subject_name']);
        $type = (string) $r['subject_type'];
        if ($name === '' || !in_array($type, RMT_PLACE_TYPES, true)) { $skipped++; continue; }

        if (!$apply) {
            // Dry run: report the grouping without creating anything, so the merge can be eyeballed
            // before any row is written.
            $key = $r['destination_id'] . '|' . rmt_place_name_key($name);
            $byPlace[$key][] = $name;
            $resolved++;
            continue;
        }

        $placeId = rmt_place_resolve((int) $r['destination_id'], $type, $name, (int) $r['user_id']);
        if ($placeId === null) { $skipped++; continue; }
        if ((int) $r['place_id'] === $placeId) { $alreadySet++; continue; }
        db()->prepare('UPDATE reviews SET place_id = ? WHERE id = ?')->execute([$placeId, (int) $r['id']]);
        $resolved++;
    }
    if ($apply) db()->commit();
} catch (Throwable $e) {
    if ($apply && db()->inTransaction()) db()->rollBack();
    fwrite(STDERR, "FAILED: " . $e->getMessage() . "\n");
    exit(1);
}

if (!$apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";
    echo "Reviews that would resolve to a place: $resolved\n";
    echo "Reviews skipped (destination-level or unnamed): $skipped\n";
    echo "Distinct places that would exist: " . count($byPlace) . "\n";
    // Anything that merges two different spellings is the one thing worth a human look.
    foreach ($byPlace as $key => $names) {
        $uniq = array_unique($names);
        if (count($uniq) > 1) echo "  MERGES: " . implode('  |  ', $uniq) . "\n";
    }
    exit(0);
}

$places = (int) q_one('SELECT COUNT(*) n FROM places')['n'];
$linked = (int) q_one('SELECT COUNT(*) n FROM reviews WHERE place_id IS NOT NULL')['n'];
echo "Linked $resolved reviews ($alreadySet already correct, $skipped skipped)\n";
echo "places: $places rows; reviews with a place: $linked\n";
