<?php
/**
 * Regression tests for the warnings domain.
 *
 * The rules under test are the ones that keep a warnings site honest rather than merely working:
 * validation floors, the separation of moderation state from evidence state, the audit trail,
 * duplicate detection, vote counters that cannot be inflated by a double submit, and the
 * visibility boundary that stops an unapproved warning leaking.
 *
 * Runs against a throwaway in-memory SQLite DB. No network, no fixtures on disk.
 *
 *   php tests/warnings_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:', 'security_salt' => 'test-salt',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/warnings.php';
require BASE_PATH . '/app/richtext.php';

/* Minimal stand-in for the controller helper the validator calls. */
function dest_by_id(int $id): ?array {
    return q_one('SELECT * FROM destinations WHERE id = ?', [$id]);
}

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT)');
$pdo->exec("INSERT INTO destinations (id, slug, name, country) VALUES (1,'paris-france','Paris','France')");
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT DEFAULT "user", status TEXT DEFAULT "active")');
$pdo->exec("INSERT INTO users (id, username, role) VALUES (1,'traveler','user'), (2,'mod','mod'), (3,'other','user')");
$pdo->exec('CREATE TABLE warnings (
  id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT, title TEXT, slug TEXT,
  category TEXT, body TEXT, advice TEXT, severity INT DEFAULT 2, date_experienced TEXT, season_month INT,
  location_detail TEXT, cost_impact_usd INT, provider_type TEXT, provider_name TEXT, traveler_type TEXT,
  attested INT DEFAULT 0, status TEXT DEFAULT "pending", verification TEXT DEFAULT "unverified",
  moderation_note TEXT, moderated_by INT, moderated_at TEXT, helpful_count INT DEFAULT 0,
  not_helpful_count INT DEFAULT 0, view_count INT DEFAULT 0, featured INT DEFAULT 0, source_url TEXT,
  dedupe_hash TEXT, last_reviewed_at TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE warning_votes (warning_id INT, user_id INT, vote TEXT, created_at TEXT,
            PRIMARY KEY (warning_id, user_id))');
$pdo->exec('CREATE TABLE warning_moderation_log (id INTEGER PRIMARY KEY AUTOINCREMENT, warning_id INT,
            actor_id INT, field TEXT, from_value TEXT, to_value TEXT, note TEXT, created_at TEXT)');
$pdo->exec('CREATE TABLE warning_responses (id INTEGER PRIMARY KEY AUTOINCREMENT, warning_id INT,
            responder_name TEXT, responder_role TEXT, contact_email TEXT, body TEXT,
            status TEXT DEFAULT "pending", approved_by INT, created_at TEXT, approved_at TEXT)');
$pdo->exec('CREATE TABLE destination_risk_sections (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT,
            section_key TEXT, heading TEXT, body TEXT, content_type TEXT, severity INT, sources_json TEXT,
            sort INT, last_reviewed_at TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE destination_faqs (id INTEGER PRIMARY KEY AUTOINCREMENT, destination_id INT,
            question TEXT, answer TEXT, sort INT, last_reviewed_at TEXT, created_at TEXT)');

$fail = 0;
$check = function (string $name, $got, $expect) use (&$fail) {
    $ok = $got === $expect;
    printf("  [%s] %-62s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
        var_export($expect, true), var_export($got, true));
    if (!$ok) $fail++;
};

$goodBody = str_repeat('The driver refused the meter and quoted a flat fare. ', 3); // > 80 chars
$base = [
    'destination_id' => '1', 'category' => 'scams', 'title' => 'Airport taxi refused the meter',
    'body' => $goodBody, 'severity' => '3', 'date_experienced' => '2026-04', 'attested' => '1',
];

echo "-- rmt_warning_validate(): publishing bar --\n";
$v = rmt_warning_validate($base, false);
$check('complete submission passes', $v['ok'], true);
$check('season_month derived from date', $v['data']['season_month'], 4);

$v = rmt_warning_validate(array_diff_key($base, ['attested' => 1]), false);
$check('missing genuine-experience attestation fails', $v['ok'], false);

$v = rmt_warning_validate(array_merge($base, ['date_experienced' => '']), false);
$check('missing date fails (dates are load-bearing)', $v['ok'], false);

$v = rmt_warning_validate(array_merge($base, ['date_experienced' => date('Y-m', strtotime('+2 months'))]), false);
$check('future date fails', $v['ok'], false);

$v = rmt_warning_validate(array_merge($base, ['body' => 'too short']), false);
$check('body under the minimum fails', $v['ok'], false);

$v = rmt_warning_validate(array_merge($base, ['severity' => '0']), false);
$check('missing severity fails', $v['ok'], false);

$v = rmt_warning_validate(array_merge($base, ['category' => 'not-a-category']), false);
$check('unknown category fails', $v['ok'], false);

$v = rmt_warning_validate(array_merge($base, ['destination_id' => '999']), false);
$check('nonexistent destination fails', $v['ok'], false);

echo "\n-- drafts are held to a lower bar --\n";
$v = rmt_warning_validate(['destination_id' => '1', 'category' => 'scams', 'title' => 'Half an idea'], true);
$check('incomplete draft passes', $v['ok'], true);

echo "\n-- cost parsing --\n";
$v = rmt_warning_validate(array_merge($base, ['cost_impact_usd' => '$1,250.40']), false);
$check('currency formatting is stripped and rounded', $v['data']['cost_impact_usd'], 1250);
$v = rmt_warning_validate(array_merge($base, ['cost_impact_usd' => 'a lot']), false);
$check('non-numeric cost fails', $v['ok'], false);

echo "\n-- unknown enum values are dropped, not stored --\n";
$v = rmt_warning_validate(array_merge($base, ['provider_type' => 'spaceship', 'traveler_type' => 'wizard']), false);
$check('bogus provider_type becomes null', $v['data']['provider_type'], null);
$check('bogus traveler_type becomes null', $v['data']['traveler_type'], null);

echo "\n-- duplicate detection --\n";
$h1 = rmt_warning_dedupe_hash(1, 'scams', 'Airport taxi refused the meter!!!');
$h2 = rmt_warning_dedupe_hash(1, 'scams', 'the airport TAXI refused, the meter');
$check('punctuation/case/stop-words do not change the fingerprint', $h1, $h2);
$h3 = rmt_warning_dedupe_hash(1, 'hidden-costs', 'Airport taxi refused the meter');
$check('a different category is a different fingerprint', $h1 === $h3, false);
$h4 = rmt_warning_dedupe_hash(1, 'scams', 'Museum closed on a Tuesday');
$check('different wording is NOT a duplicate (corroboration is wanted)', $h1 === $h4, false);

$now = date('Y-m-d H:i:s');
$wid = (int) q_run('INSERT INTO warnings (user_id,destination_id,title,slug,category,body,severity,
        status,verification,dedupe_hash,created_at) VALUES (1,1,?,?,?,?,3,?,?,?,?)',
    ['Airport taxi refused the meter', 'airport-taxi', 'scams', $goodBody, 'pending', 'unverified', $h1, $now]);
$check('same author + same fingerprint is flagged', rmt_warning_duplicate_id(1, $h1), $wid);
$check('a different author is not flagged', rmt_warning_duplicate_id(3, $h1), null);
$check('excluding the row itself avoids a self-duplicate on edit', rmt_warning_duplicate_id(1, $h1, $wid), null);

echo "\n-- visibility boundary --\n";
$pending = ['status' => 'pending', 'user_id' => 1];
$check('pending is invisible logged out', rmt_warning_can_view($pending, null), false);
$check('pending is invisible to another user', rmt_warning_can_view($pending, ['id' => 3, 'role' => 'user']), false);
$check('pending is visible to its author', rmt_warning_can_view($pending, ['id' => 1, 'role' => 'user']), true);
$check('pending is visible to a moderator', rmt_warning_can_view($pending, ['id' => 2, 'role' => 'mod']), true);
$check('approved is visible logged out', rmt_warning_can_view(['status' => 'approved', 'user_id' => 1], null), true);
$check('only the author may edit', rmt_warning_can_edit($pending, ['id' => 3, 'role' => 'admin']), false);
$check('a moderator may not rewrite someone else\'s words', rmt_warning_can_edit($pending, ['id' => 2, 'role' => 'mod']), false);

echo "\n-- moderation is audited and state-separated --\n";
$check('approve changes status', rmt_warning_moderate($wid, 'status', 'approved', 2, 'Looks good'), true);
$row = q_one('SELECT * FROM warnings WHERE id = ?', [$wid]);
$check('status is approved', $row['status'], 'approved');
$check('approving does NOT verify (evidence is separate)', $row['verification'], 'unverified');
$check('a no-op change is not logged', rmt_warning_moderate($wid, 'status', 'approved', 2, ''), false);
$check('one log entry exists', (int) q_one('SELECT COUNT(*) c FROM warning_moderation_log')['c'], 1);
$check('an invalid status is rejected', rmt_warning_moderate($wid, 'status', 'banana', 2, ''), false);
$check('an invalid verification is rejected', rmt_warning_moderate($wid, 'verification', 'true-ish', 2, ''), false);
$check('an unmoderatable field is rejected', rmt_warning_moderate($wid, 'body', 'rewritten', 2, ''), false);
$check('verify is a separate transition', rmt_warning_moderate($wid, 'verification', 'verified', 2, 'Confirmed via city notice'), true);
$log = rmt_warning_moderation_log($wid);
$check('both decisions are in the log', count($log), 2);
$check('the log records who acted', (int) $log[0]['actor_id'], 2);

echo "\n-- vote counters are derived, never incremented --\n";
q_exec('INSERT INTO warning_votes (warning_id,user_id,vote,created_at) VALUES (?,?,?,?)', [$wid, 3, 'helpful', $now]);
rmt_warning_recount_votes($wid);
$check('one helpful vote counted', (int) q_one('SELECT helpful_count c FROM warnings WHERE id=?', [$wid])['c'], 1);
rmt_warning_recount_votes($wid);
$check('recounting twice cannot inflate the total', (int) q_one('SELECT helpful_count c FROM warnings WHERE id=?', [$wid])['c'], 1);
q_exec('DELETE FROM warning_votes WHERE warning_id=? AND user_id=?', [$wid, 3]);
rmt_warning_recount_votes($wid);
$check('removing the vote removes the count', (int) q_one('SELECT helpful_count c FROM warnings WHERE id=?', [$wid])['c'], 0);
$check('my vote is null when absent', rmt_warning_my_vote($wid, 3), null);
$check('my vote is null for a logged-out reader', rmt_warning_my_vote($wid, null), null);

echo "\n-- querying --\n";
$res = rmt_warning_query([], 20);
$check('public query returns only approved rows', count($res['rows']), 1);
q_exec("INSERT INTO warnings (user_id,destination_id,title,slug,category,body,severity,status,verification,created_at)
        VALUES (1,1,'Pending thing','p','scams',?,2,'pending','unverified',?)", [$goodBody, $now]);
$res = rmt_warning_query([], 20);
$check('a pending row stays out of the public list', count($res['rows']), 1);
$res = rmt_warning_query(['status' => 'any'], 20);
$check('status=any is opt-in only', $res['total'], 2);
$res = rmt_warning_query(['category' => 'hidden-costs'], 20);
$check('category filter applies', $res['total'], 0);
$res = rmt_warning_query(['severity_min' => 4], 20);
$check('severity floor applies', $res['total'], 0);
$counts = rmt_warning_category_counts(1);
$check('category counts see only approved rows', $counts['scams']['c'], 1);

echo "\n-- staleness --\n";
$check('a fresh row is not stale', rmt_warning_is_stale(['created_at' => $now]), false);
$check('a two-year-old row is stale', rmt_warning_is_stale(['created_at' => date('Y-m-d', strtotime('-2 years'))]), true);
$check('a recent review resets staleness',
    rmt_warning_is_stale(['created_at' => date('Y-m-d', strtotime('-2 years')), 'last_reviewed_at' => $now]), false);

echo "\n-- labels degrade safely on unknown input --\n";
$check('unknown category label', rmt_warning_category_label('nope'), 'Other');
$check('unknown severity label', rmt_severity_label(99), 'Moderate');
$check('severity class is clamped', rmt_severity_class(99), 'sev-4');
$check('severity class floors at 1', rmt_severity_class(0), 'sev-1');
$check('unrated risk level', rmt_risk_level_label(null), 'Not yet rated');

echo "\n-- experienced-date formatting --\n";
$check('YYYY-MM renders as month + year', rmt_experienced_label('2026-04'), 'April 2026');
$check('YYYY-MM-DD renders as a full date', rmt_experienced_label('2026-04-09'), 'Apr 9, 2026');
$check('empty renders empty', rmt_experienced_label(''), '');
$check('garbage renders empty', rmt_experienced_label('not-a-date'), '');

echo "\n-- risk sections --\n";
q_exec("INSERT INTO destination_risk_sections (destination_id,section_key,heading,body,content_type,sort,created_at)
        VALUES (1,'scams','Common scams','Body text here.','fact',3,?)", [$now]);
q_exec("INSERT INTO destination_risk_sections (destination_id,section_key,heading,body,content_type,sort,created_at)
        VALUES (1,'overview','Overview','Overview text.','editorial',0,?)", [$now]);
q_exec("INSERT INTO destination_risk_sections (destination_id,section_key,heading,body,content_type,sort,created_at)
        VALUES (1,'weather','Weather','   ','fact',8,?)", [$now]);
$sections = rmt_risk_sections(1);
$check('sections come back in spine order, not insert order', array_keys($sections), ['overview', 'scams']);
$check('a whitespace-only section is treated as unwritten', isset($sections['weather']), false);
$check('section maps to its warning category', rmt_section_to_category('hidden_costs'), 'hidden-costs');
$check('airport section maps to transportation', rmt_section_to_category('airport'), 'transportation');
$check('a section with no category maps to null', rmt_section_to_category('worth_visiting'), null);

echo "\n-- rmt_rich(): editorial prose is escaped, then structured --\n";
$check('script tags cannot survive', strpos(rmt_rich('<script>alert(1)</script>'), '<script>'), false);
$check('an img onerror payload is escaped', strpos(rmt_rich('<img src=x onerror=alert(1)>'), '<img'), false);
$check('paragraphs are built from blank lines', rmt_rich("One.\n\nTwo."), '<p>One.</p><p>Two.</p>');
$check('dash lines become a list', rmt_rich("- a\n- b"), '<ul><li>a</li><li>b</li></ul>');
$check('bold works', rmt_rich('**hi**'), '<p><strong>hi</strong></p>');
$check('external links get nofollow', strpos(rmt_rich('[x](https://example.com)'), 'rel="nofollow noopener"') !== false, true);
$check('javascript: URLs are refused', strpos(rmt_rich('[x](javascript:alert(1))'), 'href') , false);
$check('excerpt strips markup', rmt_rich_excerpt("- alpha\n- beta"), 'alpha beta');

echo "\n-- contributor history --\n";
$stats = rmt_contributor_stats(1);
$check('approved count excludes pending', $stats['approved'], 1);
$check('a user with no warnings reports zero', rmt_contributor_stats(3)['approved'], 0);

echo "\n" . ($fail === 0 ? "ALL PASS\n" : "{$fail} FAILURE(S)\n");
exit($fail === 0 ? 0 : 1);
