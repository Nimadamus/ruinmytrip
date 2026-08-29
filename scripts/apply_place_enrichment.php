<?php
declare(strict_types=1);

/**
 * Apply a proposal file from scripts/enrich_places.py to the places table.
 *
 * Dry run by default. Nothing is written unless --apply is passed, and even then the rules below
 * hold, because a bulk write against real rows is the one operation where "mostly right" is not
 * good enough:
 *
 *   1. IT NEVER CREATES A PLACE. A slug in the proposal that does not exist is reported and
 *      skipped. Matching an external spelling to a new row is exactly how a directory ends up with
 *      two Bellagios, and the whole point of the id being identity is that it is never minted by a
 *      lookup.
 *   2. IT NEVER OVERWRITES SOMETHING WE ALREADY HOLD. A field with a current value is reported as
 *      a conflict, with both values shown, and left alone unless --overwrite is given. Empty
 *      fields are the target; disagreeing with an editor is not.
 *   3. IT REFUSES A WEAK MATCH. Below --min-confidence (default 0.80) the place is skipped
 *      entirely. A 0.6 name similarity means the lookup found something else.
 *   4. IT VALIDATES LIKE EVERYTHING ELSE. Every field goes through rmt_place_update_attributes()
 *      and every interval through rmt_place_set_hours(), so an import is held to the same standard
 *      as a person typing into the admin editor: no (0,0), no javascript: URL, no 25:00.
 *   5. IT RECORDS WHERE THE VALUE CAME FROM. data_source and data_source_url are set from the
 *      proposal, and data_checked_at is stamped by the update itself.
 *
 * Usage:
 *   php scripts/apply_place_enrichment.php --file database/enrichment/proposal.json
 *   php scripts/apply_place_enrichment.php --file ... --apply
 *   php scripts/apply_place_enrichment.php --file ... --apply --overwrite --min-confidence 0.9
 *   php scripts/apply_place_enrichment.php --file ... --apply --log .work/enrichment.log
 */

$args = array_slice($argv, 1);
$opt = static function (string $name, ?string $default = null) use ($args): ?string {
    $i = array_search('--' . $name, $args, true);
    return ($i !== false && isset($args[$i + 1])) ? $args[$i + 1] : $default;
};
$flag = static fn(string $name): bool => in_array('--' . $name, $args, true);

$file = $opt('file');
if (!$file || !is_file($file)) {
    fwrite(STDERR, "usage: php scripts/apply_place_enrichment.php --file <proposal.json> [--apply] [--overwrite] [--min-confidence 0.8] [--log FILE]\n");
    exit(1);
}
$apply     = $flag('apply');
$overwrite = $flag('overwrite');
$minConf   = (float) ($opt('min-confidence', '0.80'));
$logFile   = $opt('log');

require dirname(__DIR__) . '/app/bootstrap.php';

$doc = json_decode((string) file_get_contents($file), true);
if (!is_array($doc) || !isset($doc['places'])) {
    fwrite(STDERR, "not a proposal file: {$file}\n");
    exit(1);
}

/** Fields this tool is allowed to touch. Name, category and price are human judgements. */
const ENRICHABLE = ['street_address', 'neighborhood', 'postal_code', 'lat', 'lng',
                    'phone', 'website_url', 'timezone'];

$lines = [];
$say = static function (string $s) use (&$lines, $logFile): void {
    echo $s, PHP_EOL;
    if ($logFile) $lines[] = $s;
};

$say(sprintf('%s  source=%s  generated=%s',
    $apply ? 'APPLYING' : 'DRY RUN (nothing will be written)',
    (string) ($doc['source'] ?? '?'), (string) ($doc['generated_at'] ?? '?')));
$say('min-confidence=' . $minConf . ($overwrite ? '  overwrite=YES' : '  overwrite=no'));
$say(str_repeat('-', 78));

$stats = ['places' => 0, 'skipped_missing' => 0, 'skipped_conf' => 0,
          'fields_set' => 0, 'conflicts' => 0, 'hours_set' => 0, 'hours_kept' => 0, 'errors' => 0];

foreach ($doc['places'] as $prop) {
    $slug = (string) ($prop['slug'] ?? '');
    $conf = (float) ($prop['confidence'] ?? 0);
    $place = q_one('SELECT * FROM places WHERE slug = ?', [$slug]);

    if (!$place) {
        // Reported, never created. See rule 1.
        $say(sprintf('SKIP  %-40s no such place (this tool never creates one)', $slug));
        $stats['skipped_missing']++;
        continue;
    }
    if ($conf < $minConf) {
        $say(sprintf('SKIP  %-40s confidence %.2f below %.2f — matched "%s"',
            $slug, $conf, $minConf, (string) ($prop['osm']['display_name'] ?? '?')));
        $stats['skipped_conf']++;
        continue;
    }

    // Source precedence. A value confirmed by the business or taken off its own site outranks a
    // map, and a re-run must never quietly demote it -- not even with --overwrite, which exists to
    // correct a bad import, not to overrule a better source. Filling EMPTY fields is still allowed:
    // an owner who gave us hours did not thereby forbid us knowing the postcode.
    $trusted = ['owner', 'official_site'];
    $incoming = (string) ($prop['source'] ?? 'osm');
    $holdsBetter = in_array((string) ($place['data_source'] ?? ''), $trusted, true)
                   && !in_array($incoming, $trusted, true);

    $stats['places']++;
    $say(sprintf('PLACE %-40s conf=%.2f  %s', $slug, $conf, (string) ($prop['source_url'] ?? '')));
    if ($holdsBetter) {
        $say(sprintf('      · already sourced from %s; %s may fill blanks but not overwrite',
             (string) $place['data_source'], $incoming));
    }

    $write = [];
    foreach (ENRICHABLE as $f) {
        if (!array_key_exists($f, (array) ($prop['fields'] ?? []))) continue;
        $new = (string) $prop['fields'][$f];
        $cur = $place[$f] ?? null;
        $curStr = $cur === null ? '' : (string) $cur;

        // Coordinates are stored rounded to six decimals (about 11cm). OSM hands back seven, so a
        // straight string compare called every single re-run a conflict and would have made the
        // whole tool look non-idempotent. Round the incoming value the same way it will be stored
        // before comparing anything.
        if (($f === 'lat' || $f === 'lng') && is_numeric($new)) {
            $new = rtrim(rtrim(number_format((float) $new, 6, '.', ''), '0'), '.');
            if ($curStr !== '' && is_numeric($curStr)) {
                $curStr = rtrim(rtrim(number_format((float) $curStr, 6, '.', ''), '0'), '.');
            }
        }

        if ($curStr === '') {
            $say(sprintf('      + %-16s %s', $f, $new));
            $write[$f] = $new;
            $stats['fields_set']++;
        } elseif ($curStr === $new) {
            $say(sprintf('      = %-16s unchanged', $f));
        } else {
            $stats['conflicts']++;
            if ($overwrite && $holdsBetter) {
                $say(sprintf('      ! %-16s kept: %s outranks %s', $f, (string) $place['data_source'], $incoming));
            } elseif ($overwrite) {
                $say(sprintf('      ! %-16s OVERWRITE  %s  ->  %s', $f, $curStr, $new));
                $write[$f] = $new;
                $stats['fields_set']++;
            } else {
                $say(sprintf('      ! %-16s conflict, keeping  %s   (proposed %s)', $f, $curStr, $new));
            }
        }
    }

    // lat and lng move together or not at all; the validator rejects a lone half anyway.
    if (isset($write['lat']) !== isset($write['lng'])) {
        $say('      ! coordinates incomplete, dropping both');
        unset($write['lat'], $write['lng']);
    }

    // Only restamp provenance when this run is what filled something, and never downgrade a
    // better source's label just because a map agreed with it.
    if ($write && !empty($prop['source_url']) && !$holdsBetter) {
        $write['data_source'] = $incoming;
        $write['data_source_url'] = (string) $prop['source_url'];
    }

    $hours = (array) ($prop['hours'] ?? []);
    $existingHours = rmt_place_hours((int) $place['id']);
    $doHours = $hours && (!$existingHours || ($overwrite && !$holdsBetter));
    if ($hours && $existingHours && !$overwrite) {
        // Counted separately from a field conflict. Declining to overwrite hours we already hold
        // is the normal state of every re-run, and folding it into "conflicts" made a clean
        // idempotent pass look like it had found 62 disagreements.
        $say('      · hours already set, keeping them (' . count($existingHours) . ' intervals)');
        $stats['hours_kept']++;
    } elseif ($doHours) {
        $say(sprintf('      + %-16s %d intervals  (%s)', 'hours', count($hours),
             (string) ($prop['hours_raw'] ?? '')));
    }

    foreach ((array) ($prop['notes'] ?? []) as $n) $say('      note: ' . $n);

    if (!$apply) continue;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($write) {
            $errs = rmt_place_update_attributes((int) $place['id'], $write);
            if ($errs) {
                foreach ($errs as $f => $msg) $say('      ERROR ' . $f . ': ' . $msg);
                $stats['errors']++;
                $pdo->rollBack();
                continue;
            }
        }
        if ($doHours) {
            $errs = rmt_place_set_hours((int) $place['id'], $hours);
            if ($errs) {
                foreach ($errs as $msg) $say('      ERROR hours: ' . $msg);
                $stats['errors']++;
                $pdo->rollBack();
                continue;
            }
            $stats['hours_set']++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $say('      ERROR ' . $e->getMessage());
        $stats['errors']++;
    }
}

$say(str_repeat('-', 78));
$say(sprintf('%d places processed, %d fields %s, %d hour sets (%d already had hours), %d field conflicts kept, %d skipped (missing %d, low confidence %d), %d errors',
    $stats['places'], $stats['fields_set'], $apply ? 'written' : 'proposed', $stats['hours_set'],
    $stats['hours_kept'], $stats['conflicts'], $stats['skipped_missing'] + $stats['skipped_conf'],
    $stats['skipped_missing'], $stats['skipped_conf'], $stats['errors']));
if (!$apply) $say('Nothing was written. Re-run with --apply once the diff above looks right.');

if ($logFile) {
    @mkdir(dirname($logFile), 0777, true);
    file_put_contents($logFile, date('c') . "\n" . implode("\n", $lines) . "\n\n", FILE_APPEND);
    echo 'logged to ' . $logFile . PHP_EOL;
}
exit($stats['errors'] ? 1 : 0);
