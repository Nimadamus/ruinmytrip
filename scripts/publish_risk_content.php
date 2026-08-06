<?php
declare(strict_types=1);

/**
 * Publish destination risk reports from database/editorial/risk_content.json.
 *
 * This is the counterpart to scripts/publish_editorial.php (which publishes the older
 * review/guide editorial layer). It writes:
 *   destinations.risk_level / risk_summary / worth_visiting / best_months / worst_months /
 *                last_reviewed_at
 *   destination_risk_sections   (one row per written section, upserted by section_key)
 *   destination_faqs            (replaced wholesale per destination — see below)
 *   seo_landing_pages           (upserted by slug)
 *
 * Deliberate design decisions:
 *
 *  - IT NEVER CREATES A DESTINATION ROW. A missing slug is reported and skipped, exactly like
 *    publish_editorial.php. Base rows belong in a numbered migration so the schema history shows
 *    when a destination appeared.
 *  - FAQs ARE REPLACED, SECTIONS ARE UPSERTED. A section has a stable key so it can be updated in
 *    place and keep its id (staleness reports point at that id). FAQs have no stable key, so
 *    re-publishing replaces the set for that destination rather than accumulating duplicates.
 *  - ONE TRANSACTION. Either the whole file lands or none of it does. A half-published risk report
 *    is worse than an unpublished one, because the missing sections look like a considered
 *    editorial decision rather than an interrupted script.
 *  - UPDATEs USE q_exec(), NOT q_run(). q_run() calls lastInsertId(), which on Postgres is
 *    lastval() and raises a real server-side error when nothing has touched a sequence yet in the
 *    session — which aborts the surrounding transaction. This is the same bug that broke every
 *    run of publish_editorial.php until it was fixed there.
 *
 * Usage:
 *   php scripts/publish_risk_content.php --check    validate + report, write nothing
 *   php scripts/publish_risk_content.php --apply    write (single transaction)
 *   php scripts/publish_risk_content.php --apply --only=paris-france,rome-italy
 */

define('RMT_NO_AUTOSEED', true);
require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/app/controllers.php';
require BASE_PATH . '/app/controllers_landing.php';
require BASE_PATH . '/app/controllers_admin.php';

$args  = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$only  = [];
foreach ($args as $a) {
    if (preg_match('/^--only=(.+)$/', $a, $m)) $only = array_map('trim', explode(',', $m[1]));
}
if (!$apply && !in_array('--check', $args, true)) {
    fwrite(STDERR, "Usage: publish_risk_content.php --check | --apply [--only=slug,slug]\n");
    exit(1);
}

$path = BASE_PATH . '/database/editorial/risk_content.json';
if (!is_file($path)) { fwrite(STDERR, "missing {$path}\n"); exit(1); }
$data = json_decode((string) file_get_contents($path), true);
if (!is_array($data) || !isset($data['destinations'])) { fwrite(STDERR, "risk_content.json is not valid\n"); exit(1); }

function out(string $s): void { echo $s . PHP_EOL; }

$defs      = rmt_risk_section_defs();
$templates = rmt_landing_templates();
$reserved  = rmt_reserved_slugs();
$errors    = [];
$plan      = [];

/* ---------------------------------------------------------------- validate */
foreach ($data['destinations'] as $i => $d) {
    $slug = (string) ($d['slug'] ?? '');
    if ($only && !in_array($slug, $only, true)) continue;

    $dest = dest_by_slug($slug);
    if (!$dest) { out("  no such destination row: SKIP  {$slug}"); continue; }

    $entry = ['dest' => $dest, 'sections' => [], 'faqs' => [], 'pages' => [], 'fields' => []];

    $risk = (int) ($d['risk_level'] ?? 0);
    if ($risk && ($risk < 1 || $risk > 4)) $errors[] = "{$slug}: risk_level must be 1-4";
    $entry['fields'] = [
        'risk_level'     => $risk ?: null,
        'risk_summary'   => trim((string) ($d['risk_summary'] ?? '')) ?: null,
        'worth_visiting' => trim((string) ($d['worth_visiting'] ?? '')) ?: null,
        'best_months'    => trim((string) ($d['best_months'] ?? '')) ?: null,
        'worst_months'   => trim((string) ($d['worst_months'] ?? '')) ?: null,
    ];

    foreach ((array) ($d['sections'] ?? []) as $key => $sec) {
        if (!isset($defs[$key])) { $errors[] = "{$slug}: unknown section key '{$key}'"; continue; }
        $body = trim((string) ($sec['body'] ?? ''));
        if ($body === '') { $errors[] = "{$slug}/{$key}: empty body"; continue; }
        // A section that only restates the destination name is worse than no section at all.
        if (mb_strlen($body) < 120) $errors[] = "{$slug}/{$key}: body is only " . mb_strlen($body) . " chars (min 120)";
        $type = (string) ($sec['type'] ?? $defs[$key]['type']);
        if (!in_array($type, ['fact', 'editorial', 'alert'], true)) $errors[] = "{$slug}/{$key}: bad type '{$type}'";
        $sev = (int) ($sec['severity'] ?? 0);
        if ($sev && ($sev < 1 || $sev > 4)) $errors[] = "{$slug}/{$key}: severity must be 1-4";
        $entry['sections'][$key] = [
            'body' => $body, 'type' => $type, 'severity' => $sev ?: null,
            'heading' => trim((string) ($sec['heading'] ?? '')) ?: $defs[$key]['heading'],
            'sources' => rmt_sources_json_from($sec['sources'] ?? []),
        ];
    }

    foreach ((array) ($d['faqs'] ?? []) as $f) {
        $q = trim((string) ($f['q'] ?? ''));
        $a2 = trim((string) ($f['a'] ?? ''));
        if ($q === '' || $a2 === '') { $errors[] = "{$slug}: FAQ needs both q and a"; continue; }
        $entry['faqs'][] = ['q' => $q, 'a' => $a2];
    }

    foreach ((array) ($d['pages'] ?? []) as $p) {
        $ps = slugify((string) ($p['slug'] ?? ''));
        if ($ps === '' || $ps === 'item') { $errors[] = "{$slug}: page needs a slug"; continue; }
        if (in_array($ps, $reserved, true)) { $errors[] = "{$slug}: page slug '{$ps}' collides with a route"; continue; }
        $tpl = (string) ($p['template'] ?? '');
        if (!isset($templates[$tpl])) { $errors[] = "{$slug}/{$ps}: unknown template '{$tpl}'"; continue; }
        $body = trim((string) ($p['body'] ?? ''));
        $status = ($p['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
        if ($status === 'published' && mb_strlen($body) < 600) {
            $errors[] = "{$slug}/{$ps}: published page body is " . mb_strlen($body) . " chars (min 600)";
        }
        foreach (['h1', 'title_tag', 'meta_description'] as $req) {
            if (trim((string) ($p[$req] ?? '')) === '') $errors[] = "{$slug}/{$ps}: missing {$req}";
        }
        if (mb_strlen((string) ($p['title_tag'] ?? '')) > 70) $errors[] = "{$slug}/{$ps}: title_tag over 70 chars";
        if (mb_strlen((string) ($p['meta_description'] ?? '')) > 170) $errors[] = "{$slug}/{$ps}: meta_description over 170 chars";
        $entry['pages'][] = [
            'slug' => $ps, 'template' => $tpl, 'category' => $p['category'] ?? ($templates[$tpl]['category'] ?? null),
            'h1' => (string) ($p['h1'] ?? ''), 'title_tag' => (string) ($p['title_tag'] ?? ''),
            'meta_description' => (string) ($p['meta_description'] ?? ''),
            'intro' => trim((string) ($p['intro'] ?? '')) ?: null, 'body' => $body,
            'sources' => rmt_sources_json_from($p['sources'] ?? []), 'status' => $status,
        ];
    }
    $plan[$slug] = $entry;
}

/** Accept sources as [["Title","url"], ...] or [{"title":..,"url":..}, ...]. */
function rmt_sources_json_from($raw): ?string {
    $out = [];
    foreach ((array) $raw as $s) {
        if (is_array($s) && isset($s[0])) $s = ['title' => $s[0], 'url' => $s[1] ?? ''];
        $t = trim((string) ($s['title'] ?? ''));
        $u = trim((string) ($s['url'] ?? ''));
        if ($u !== '' && !preg_match('#^https?://#i', $u)) $u = '';
        if ($t === '' && $u === '') continue;
        $out[] = ['title' => mb_substr($t, 0, 200), 'url' => mb_substr($u, 0, 500)];
    }
    return $out ? json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
}

/* ------------------------------------------------------------------ report */
out('');
foreach ($plan as $slug => $e) {
    out(sprintf('  %-28s risk=%s  sections=%2d  faqs=%d  pages=%d', $slug,
        $e['fields']['risk_level'] ?? '-', count($e['sections']), count($e['faqs']), count($e['pages'])));
}
out('');
out(count($plan) . ' destination(s) planned, ' . count($errors) . ' problem(s)');
foreach ($errors as $er) out('  ! ' . $er);

if ($errors) { out("\nRefusing to write while there are problems."); exit(1); }
if (!$apply) { out("\nCHECK ONLY — nothing written. Re-run with --apply."); exit(0); }

/* ------------------------------------------------------------------- apply */
$now  = date('Y-m-d H:i:s');
$today = date('Y-m-d');
$editorialId = rmt_editorial_user()['id'] ?? null;

db()->beginTransaction();
try {
    foreach ($plan as $slug => $e) {
        $did = (int) $e['dest']['id'];
        $f = $e['fields'];
        q_exec('UPDATE destinations SET risk_level=?, risk_summary=?, worth_visiting=?, best_months=?,
                       worst_months=?, last_reviewed_at=? WHERE id=?',
               [$f['risk_level'], $f['risk_summary'], $f['worth_visiting'], $f['best_months'],
                $f['worst_months'], $today, $did]);

        $sort = 0;
        foreach ($defs as $key => $def) {
            if (!isset($e['sections'][$key])) { $sort++; continue; }
            $s = $e['sections'][$key];
            $existing = q_one('SELECT id FROM destination_risk_sections WHERE destination_id=? AND section_key=?',
                              [$did, $key]);
            if ($existing) {
                q_exec('UPDATE destination_risk_sections SET heading=?, body=?, content_type=?, severity=?,
                               sources_json=?, sort=?, last_reviewed_at=?, updated_at=? WHERE id=?',
                       [$s['heading'], $s['body'], $s['type'], $s['severity'], $s['sources'], $sort,
                        $today, $now, (int) $existing['id']]);
            } else {
                q_exec('INSERT INTO destination_risk_sections
                        (destination_id, section_key, heading, body, content_type, severity, sources_json,
                         sort, last_reviewed_at, created_at, updated_at)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                       [$did, $key, $s['heading'], $s['body'], $s['type'], $s['severity'], $s['sources'],
                        $sort, $today, $now, $now]);
            }
            $sort++;
        }

        if ($e['faqs']) {
            q_exec('DELETE FROM destination_faqs WHERE destination_id = ?', [$did]);
            foreach ($e['faqs'] as $n => $fq) {
                q_exec('INSERT INTO destination_faqs (destination_id, question, answer, sort, last_reviewed_at, created_at)
                        VALUES (?,?,?,?,?,?)', [$did, $fq['q'], $fq['a'], $n, $today, $now]);
            }
        }

        foreach ($e['pages'] as $p) {
            $ex = q_one('SELECT id FROM seo_landing_pages WHERE slug = ?', [$p['slug']]);
            if ($ex) {
                q_exec('UPDATE seo_landing_pages SET template=?, destination_id=?, category=?, h1=?, title_tag=?,
                               meta_description=?, intro=?, body=?, sources_json=?, status=?, last_reviewed_at=?,
                               updated_at=? WHERE id=?',
                       [$p['template'], $did, $p['category'], $p['h1'], $p['title_tag'], $p['meta_description'],
                        $p['intro'], $p['body'], $p['sources'], $p['status'], $today, $now, (int) $ex['id']]);
            } else {
                q_exec('INSERT INTO seo_landing_pages
                        (slug, template, destination_id, category, h1, title_tag, meta_description, intro, body,
                         sources_json, status, author_id, last_reviewed_at, created_at, updated_at)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                       [$p['slug'], $p['template'], $did, $p['category'], $p['h1'], $p['title_tag'],
                        $p['meta_description'], $p['intro'], $p['body'], $p['sources'], $p['status'],
                        $editorialId, $today, $now, $now]);
            }
        }
        out("  published  {$slug}");
    }
    db()->commit();
    out("\nCOMMITTED.");
} catch (Throwable $ex) {
    if (db()->inTransaction()) db()->rollBack();
    fwrite(STDERR, "FAILED, rolled back: " . $ex->getMessage() . "\n");
    exit(1);
}
