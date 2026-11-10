<?php
/**
 * One-off: put each guide's updated_at back to the day its words last changed.
 *
 * Why. guides.updated_at is the lastmod of every /g/ page in the sitemap (app/sitemap.php). Until
 * the publisher learned to leave an unchanged guide alone, every publish run stamped every guide,
 * and the last such run was 2026-10-31: on 2026-11-10 all 84 published guides said 2026-10-31,
 * while git history shows 79 of them last changed between 2026-07-24 and 2026-08-03. A sitemap
 * that says a page changed when it did not is a claim a crawler can check, and stops believing.
 *
 * What it does. For each slug in database/guide_content_dates.json (the day the guide's
 * title, summary or body last changed in the editorial files, read from git), it sets updated_at
 * to that day, but only when ALL of these hold:
 *   - the guide exists and is published;
 *   - its updated_at is on the restamp day (2026-10-31), so a guide edited since is never touched;
 *   - the real day is earlier than the stamped one, so a date only ever moves back to the truth;
 *   - the real day is not before the row was created (a guide cannot change before it exists;
 *     in that case created_at is used).
 * Nothing else is written: no body, no title, no status. Run it twice and the second run does nothing.
 *
 *   php scripts/fix_guide_dates.php            dry run: prints what would change, writes nothing
 *   php scripts/fix_guide_dates.php --apply    writes
 *   php scripts/fix_guide_dates.php --undo     puts 2026-10-31 back on the rows --apply changed
 *
 * After --apply the sitemap picks the dates up at its next rebuild (6 hours at most).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

const RMT_GUIDE_RESTAMP_DAY = '2026-10-31';

/**
 * The plan, as data, so the test can read it without writing anything.
 *
 * @param array<string,string> $dates slug => Y-m-d
 * @return list<array{id:int,slug:string,from:string,to:string}>
 */
function rmt_guide_date_plan(array $dates): array {
    $plan = [];
    foreach (q_all("SELECT id, slug, created_at, updated_at FROM guides WHERE status = 'published' ORDER BY id") as $g) {
        $slug = (string) $g['slug'];
        $real = $dates[$slug] ?? null;
        if ($real === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $real)) continue;
        $stamped = gmdate('Y-m-d', (int) strtotime((string) $g['updated_at']));
        if ($stamped !== RMT_GUIDE_RESTAMP_DAY) continue;
        $created = gmdate('Y-m-d', (int) strtotime((string) $g['created_at']));
        $to = max($real, $created);
        if ($to >= $stamped) continue;
        $plan[] = ['id' => (int) $g['id'], 'slug' => $slug, 'from' => $stamped, 'to' => $to];
    }
    return $plan;
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $apply = in_array('--apply', $argv, true);
    $undo  = in_array('--undo', $argv, true);
    $file  = dirname(__DIR__) . '/database/guide_content_dates.json';
    $dates = (array) (json_decode((string) file_get_contents($file), true)['guides'] ?? []);
    if (!$dates) { fwrite(STDERR, "no dates in $file\n"); exit(1); }

    if ($undo) {
        $n = 0;
        foreach (q_all("SELECT id, slug, updated_at FROM guides WHERE status = 'published' ORDER BY id") as $g) {
            $real = $dates[(string) $g['slug']] ?? null;
            if ($real === null) continue;
            $have = gmdate('Y-m-d', (int) strtotime((string) $g['updated_at']));
            if ($have >= RMT_GUIDE_RESTAMP_DAY) continue;
            q_run('UPDATE guides SET updated_at = ? WHERE id = ?', [RMT_GUIDE_RESTAMP_DAY . ' 00:00:00', (int) $g['id']]);
            $n++;
        }
        echo "undo: $n guides set back to " . RMT_GUIDE_RESTAMP_DAY . "\n";
        exit(0);
    }

    $plan = rmt_guide_date_plan($dates);
    foreach ($plan as $p) echo sprintf("%s  %s -> %s  /g/%s\n", $apply ? 'SET ' : 'WOULD', $p['from'], $p['to'], $p['slug']);
    if ($apply) {
        foreach ($plan as $p) q_run('UPDATE guides SET updated_at = ? WHERE id = ?', [$p['to'] . ' 00:00:00', $p['id']]);
    }
    echo ($apply ? 'changed ' : 'dry run, would change ') . count($plan) . " guides\n";
}
