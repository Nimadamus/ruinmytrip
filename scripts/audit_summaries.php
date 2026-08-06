<?php
declare(strict_types=1);

/**
 * Apply the destination-summary audit from database/risk/summary_audit.json.
 *
 * Writes destinations.summary_reviewed_at and destinations.summary_sources for the destinations
 * a person has actually re-verified, and reports coverage for the rest.
 *
 * The important property is what it does NOT do: it never invents a review date. A destination
 * absent from the audit file keeps summary_reviewed_at = NULL and renders no date, which is the
 * honest representation of "nobody has checked this recently". Stamping every row with today's
 * date would make the freshness signal worthless, which is the whole failure mode the field
 * exists to prevent.
 *
 * It also re-runs the mechanical triage, so the report always states how many summaries carry a
 * checkable claim and how many of those have actually been verified.
 *
 * Usage:
 *   php scripts/audit_summaries.php --check
 *   php scripts/audit_summaries.php --apply
 */

define('RMT_NO_AUTOSEED', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$args  = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
if (!$apply && !in_array('--check', $args, true)) {
    fwrite(STDERR, "Usage: audit_summaries.php --check | --apply\n");
    exit(1);
}

$path = BASE_PATH . '/database/risk/summary_audit.json';
$data = json_decode((string) file_get_contents($path), true);
if (!is_array($data) || !isset($data['summaries'])) { fwrite(STDERR, "summary_audit.json is not valid\n"); exit(1); }

function out(string $s): void { echo $s . PHP_EOL; }

/**
 * The same mechanical triage used to scope the audit: does this summary assert anything a reader
 * could check and find wrong? Numbers, money, percentages, years, and the date-bound words that
 * quietly rot ("now", "new", "record").
 */
function rmt_summary_has_claim(string $s): bool {
    return (bool) preg_match(
        '/\b(\d[\d,\.]*\s*(per cent|percent|%|million|billion|euros?|dollars?|pounds?)|\d{4}\b|'
        . 'since \w+|as of|from \w+ \d{4}|new |now |just |recently|record\b|€\s?\d|\$\d|£\d)/i', $s);
}

$auditBySlug = [];
foreach ($data['summaries'] as $a) $auditBySlug[(string) $a['slug']] = $a;

$rows = q_all('SELECT id, slug, summary, summary_reviewed_at FROM destinations ORDER BY slug');
$withClaims = $verified = $unverified = 0;
$unverifiedSlugs = [];

foreach ($rows as $r) {
    $hasClaim = rmt_summary_has_claim((string) $r['summary']);
    if ($hasClaim) $withClaims++;
    if (isset($auditBySlug[$r['slug']])) {
        $verified++;
    } elseif ($hasClaim) {
        $unverified++;
        $unverifiedSlugs[] = $r['slug'];
    }
}

out('');
out('Destination summary audit');
out(str_repeat('-', 60));
out(sprintf('  destinations                     %d', count($rows)));
out(sprintf('  summaries with a checkable claim  %d', $withClaims));
out(sprintf('  verified in this audit            %d', $verified));
out(sprintf('  NOT yet re-verified               %d', $unverified));
out('');
foreach ($data['summaries'] as $a) {
    $exists = (bool) q_one('SELECT 1 FROM destinations WHERE slug = ?', [$a['slug']]);
    out(sprintf('  %-24s %-28s %s', $a['slug'], $a['verdict'], $exists ? '' : '(NO SUCH DESTINATION — skipped)'));
}
if ($unverifiedSlugs) {
    out('');
    out('  Carry a checkable claim and have NOT been re-verified — these render no review date:');
    foreach (array_chunk($unverifiedSlugs, 4) as $chunk) out('    ' . implode(', ', $chunk));
}

if (!$apply) { out("\nCHECK ONLY — nothing written."); exit(0); }

$now = date('Y-m-d H:i:s');
$reviewedOn = (string) ($data['_audited_on'] ?? date('Y-m-d'));
$written = 0;
db()->beginTransaction();
try {
    foreach ($data['summaries'] as $a) {
        $d = q_one('SELECT id FROM destinations WHERE slug = ?', [$a['slug']]);
        if (!$d) { out("  no such destination row: SKIP  {$a['slug']}"); continue; }
        $sources = [];
        foreach ((array) ($a['sources'] ?? []) as $s) {
            if (is_array($s) && isset($s[0])) $sources[] = ['title' => $s[0], 'url' => $s[1] ?? ''];
        }
        q_exec('UPDATE destinations SET summary_reviewed_at = ?, summary_sources = ? WHERE id = ?',
               [$reviewedOn, $sources ? json_encode($sources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                (int) $d['id']]);
        $written++;
    }
    db()->commit();
    out("\n  {$written} summaries stamped as reviewed on {$reviewedOn}.");
    out('COMMITTED.');
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    fwrite(STDERR, 'FAILED, rolled back: ' . $e->getMessage() . "\n");
    exit(1);
}
