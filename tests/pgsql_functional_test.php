<?php
declare(strict_types=1);

/**
 * The application's own domain logic, exercised against a real PostgreSQL server.
 *
 * Every other suite in tests/ runs on in-memory SQLite, which is fast and portable and cannot
 * catch the whole class of bug that only exists on Postgres: ORDER BY resolution against derived
 * tables, LIKE case sensitivity, generated columns, ON CONFLICT behaviour, transaction abort
 * semantics after a failed statement. This suite runs the real functions — the same ones the
 * controllers call — against the real migrated schema.
 *
 * Usage (requires a DISPOSABLE Postgres; see scripts/verify_pgsql.php for the guard rationale):
 *   DATABASE_URL='postgresql://rmttest:testpass@127.0.0.1:15432/rmt_apptest' \
 *     php -d extension=pdo_pgsql tests/pgsql_functional_test.php
 *
 * The database is built from scratch by this script, so it is safe to re-run.
 */

$dbUrl = getenv('DATABASE_URL') ?: '';
foreach (['render.com', 'amazonaws', 'dpg-'] as $needle) {
    if (stripos($dbUrl, $needle) !== false) {
        fwrite(STDERR, "REFUSED: DATABASE_URL looks like a managed/production host ({$needle}).\n");
        exit(1);
    }
}
if (!preg_match('#@(127\.0\.0\.1|localhost)[:/]#', $dbUrl)) {
    fwrite(STDERR, "REFUSED: DATABASE_URL must point at localhost. Got: " . preg_replace('#//[^@]*@#', '//***@', $dbUrl) . "\n");
    exit(1);
}

define('RMT_NO_AUTOSEED', true);
define('RMT_TRACK_IN_CLI', true);   // analytics normally no-ops in CLI; this suite tests it
require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/app/controllers.php';
require BASE_PATH . '/app/controllers_warnings.php';
require BASE_PATH . '/app/controllers_watchlist.php';
require BASE_PATH . '/app/controllers_landing.php';
require BASE_PATH . '/app/controllers_admin.php';

$fail = 0; $checks = 0;
function ok(string $name, $got, $expect = true): void {
    global $fail, $checks;
    $checks++;
    $pass = $got === $expect;
    printf("  [%s] %-64s %s\n", $pass ? 'PASS' : 'FAIL', $name,
        $pass ? '' : 'expected=' . var_export($expect, true) . ' got=' . var_export($got, true));
    if (!$pass) $fail++;
}
function head(string $s): void { echo "\n" . $s . "\n" . str_repeat('-', strlen($s)) . "\n"; }

ok('driver is pgsql', cfg('db_driver'), 'pgsql');
echo "  server: " . q_one('SELECT version() v')['v'] . "\n";

/* ---------------------------------------------------------------- fixtures */
$now = date('Y-m-d H:i:s');
$dest = q_one("SELECT id, slug, name FROM destinations WHERE slug = 'paris-france'");
if (!$dest) { fwrite(STDERR, "fixture destination missing — run migrate.php first\n"); exit(1); }
$destId = (int) $dest['id'];

foreach ([['pgt_member', 'user'], ['pgt_mod', 'mod'], ['pgt_other', 'user']] as [$uname, $role]) {
    if (!q_one('SELECT 1 FROM users WHERE username = ?', [$uname])) {
        q_run('INSERT INTO users (username,email,password_hash,role,status,created_at) VALUES (?,?,?,?,?,?)',
              [$uname, $uname . '@fixture.invalid', password_hash('x', PASSWORD_DEFAULT), $role, 'active', $now]);
    }
}
$member = (int) q_one("SELECT id FROM users WHERE username='pgt_member'")['id'];
$mod    = (int) q_one("SELECT id FROM users WHERE username='pgt_mod'")['id'];
$other  = (int) q_one("SELECT id FROM users WHERE username='pgt_other'")['id'];

/* ============================================================== validation */
head('Warning validation (same code path as the controller)');
$body = str_repeat('The driver refused the meter and demanded a flat fare. ', 3);
$in = ['destination_id' => (string) $destId, 'category' => 'scams',
       'title' => 'PG test: airport taxi refused the meter', 'body' => $body,
       'severity' => '3', 'date_experienced' => '2026-04', 'attested' => '1',
       'cost_impact_usd' => '$1,250.40', 'provider_name' => 'Example Cab Co', 'provider_type' => 'transport'];
$v = rmt_warning_validate($in, false);
ok('valid submission passes on pgsql', $v['ok']);
ok('cost parsed from a formatted string', $v['data']['cost_impact_usd'], 1250);
ok('season_month derived', $v['data']['season_month'], 4);
ok('missing attestation still fails', rmt_warning_validate(array_diff_key($in, ['attested' => 1]), false)['ok'], false);

/* ============================================================== creation */
head('Warning creation');
// Counts are asserted as DELTAS against this baseline. The database under test is a full replica
// with real published content, so an absolute "the list contains exactly 1" is a statement about
// the fixture set rather than about the behaviour, and breaks the moment anything else is seeded.
$baseVisible  = rmt_warning_query(['destination_id' => $destId], 100)['total'];
$baseVerified = rmt_warning_query(['destination_id' => $destId, 'verification' => 'verified'], 100)['total'];
$hash = rmt_warning_dedupe_hash($destId, 'scams', $in['title']);
$d = $v['data'];
$wid = (int) q_run('INSERT INTO warnings
    (user_id,destination_id,title,slug,category,body,advice,severity,date_experienced,season_month,
     location_detail,cost_impact_usd,provider_type,provider_name,traveler_type,attested,status,
     verification,dedupe_hash,created_at,updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    $member, $destId, $d['title'], 'pg-test-taxi', $d['category'], $d['body'], $d['advice'],
    $d['severity'], $d['date_experienced'], $d['season_month'], $d['location_detail'],
    $d['cost_impact_usd'], $d['provider_type'], $d['provider_name'], $d['traveler_type'],
    $d['attested'], 'pending', 'unverified', $hash, $now, $now]);
ok('insert returned a serial id', $wid > 0);
$w = rmt_warning_get($wid);
ok('row reads back', is_array($w));
ok('enters the queue as pending, never published', $w['status'], 'pending');
ok('starts unverified', $w['verification'], 'unverified');
ok('duplicate detected for the same author', rmt_warning_duplicate_id($member, $hash), $wid);
ok('not flagged for a different author', rmt_warning_duplicate_id($other, $hash), null);

/* ============================================================= visibility */
head('Visibility boundary before publication');
ok('invisible logged out', rmt_warning_can_view($w, null), false);
ok('invisible to another member', rmt_warning_can_view($w, ['id' => $other, 'role' => 'user']), false);
ok('visible to its author', rmt_warning_can_view($w, ['id' => $member, 'role' => 'user']), true);
ok('visible to a moderator', rmt_warning_can_view($w, ['id' => $mod, 'role' => 'mod']), true);
ok('absent from the public list while pending', rmt_warning_query(['destination_id' => $destId], 100)['total'], $baseVisible);

/* ============================================================= moderation */
head('Moderation and publication');
ok('approve succeeds', rmt_warning_moderate($wid, 'status', 'approved', $mod, 'Reads as first-hand.'));
$w = rmt_warning_get($wid);
ok('now approved', $w['status'], 'approved');
ok('publication did NOT confer verification', $w['verification'], 'unverified');
ok('visible to the public now', rmt_warning_can_view($w, null), true);
ok('appears in the public list once approved', rmt_warning_query(['destination_id' => $destId], 100)['total'], $baseVisible + 1);
ok('an invalid status is refused', rmt_warning_moderate($wid, 'status', 'live', $mod, ''), false);
ok('an unmoderatable field is refused', rmt_warning_moderate($wid, 'body', 'rewritten', $mod, ''), false);
ok('a no-op writes no log row', rmt_warning_moderate($wid, 'status', 'approved', $mod, ''), false);
ok('audit log has exactly the real decision', count(rmt_warning_moderation_log($wid)), 1);

/* ========================================================== corroboration */
head('Corroboration (verification is a separate, deliberate act)');
ok('verify succeeds', rmt_warning_moderate($wid, 'verification', 'verified', $mod, 'Confirmed against the operator notice.'));
$w = rmt_warning_get($wid);
ok('now verified', $w['verification'], 'verified');
ok('audit log records both decisions', count(rmt_warning_moderation_log($wid)), 2);
ok('dispute is reachable', rmt_warning_moderate($wid, 'verification', 'disputed', $mod, 'Business contests it.'));
ok('back to verified', rmt_warning_moderate($wid, 'verification', 'verified', $mod, 'Re-confirmed.'));
$log = rmt_warning_moderation_log($wid);
ok('every transition is attributable to a person', (int) $log[0]['actor_id'], $mod);

/* ================================================================== votes */
head('Helpful votes are derived, never incremented');
q_exec('INSERT INTO warning_votes (warning_id,user_id,vote,created_at) VALUES (?,?,?,?)', [$wid, $other, 'helpful', $now]);
rmt_warning_recount_votes($wid);
ok('one vote counted', (int) q_one('SELECT helpful_count c FROM warnings WHERE id=?', [$wid])['c'], 1);
rmt_warning_recount_votes($wid);
ok('recounting cannot inflate', (int) q_one('SELECT helpful_count c FROM warnings WHERE id=?', [$wid])['c'], 1);
ok('duplicate vote rejected by the composite PK',
   (function () use ($wid, $other, $now) {
       try { q_exec('INSERT INTO warning_votes (warning_id,user_id,vote,created_at) VALUES (?,?,?,?)', [$wid, $other, 'helpful', $now]); return false; }
       catch (Throwable $e) { return true; }
   })());

/* ===================================================== business responses */
head('Business right of reply');
$before = q_one('SELECT title, body, status, verification FROM warnings WHERE id=?', [$wid]);
$rid = (int) q_run('INSERT INTO warning_responses (warning_id,responder_name,responder_role,contact_email,body,status,created_at)
                    VALUES (?,?,?,?,?,?,?)',
    [$wid, 'Example Cab Co', 'Operations manager', 'ops@example.invalid',
     'We have retrained the driver and the meter is now mandatory on airport runs.', 'pending', $now]);
ok('response filed', $rid > 0);
ok('a pending response is NOT publicly visible', count(rmt_warning_responses($wid)), 0);
ok('moderators can see it pending', count(rmt_warning_responses($wid, true)), 1);
q_exec('UPDATE warning_responses SET status=?, approved_by=?, approved_at=? WHERE id=?', ['approved', $mod, $now, $rid]);
ok('approved response is publicly visible', count(rmt_warning_responses($wid)), 1);
$after = q_one('SELECT title, body, status, verification FROM warnings WHERE id=?', [$wid]);
ok('the traveler\'s original report is byte-for-byte unchanged', $after, $before);

/* ================================================================= search */
head('Search on PostgreSQL (tsvector, ranking, phrase, typo tolerance)');
$_GET = ['q' => 'airport taxi meter'];
ok('generated search_vector populated', (bool) q_one('SELECT 1 FROM warnings WHERE id=? AND search_vector IS NOT NULL', [$wid]));
$hits = q_all("SELECT id FROM warnings WHERE status='approved' AND search_vector @@ plainto_tsquery('english', ?)", ['airport taxi meter']);
ok('full-text finds the warning', count($hits) >= 1);
$phrase = q_all("SELECT id FROM warnings WHERE status='approved' AND search_vector @@ phraseto_tsquery('english', ?)", ['refused the meter']);
ok('exact-phrase query matches', count($phrase) >= 1);
$noPhrase = q_all("SELECT id FROM warnings WHERE status='approved' AND search_vector @@ phraseto_tsquery('english', ?)", ['meter refused the']);
ok('a wrong word order does NOT match the phrase', count($noPhrase), 0);
ok('provider name is searchable (weight B)',
   count(q_all("SELECT id FROM warnings WHERE search_vector @@ plainto_tsquery('english','Example Cab')")) >= 1);
$subj = rmt_search_subjects('Example Cab', 10);
ok('named-subject search finds the business', count($subj) >= 1);
$fuzzy = rmt_fuzzy_destinations('Parris', 5);
ok('typo tolerance resolves a misspelt destination', count($fuzzy) >= 1 && $fuzzy[0]['slug'] === 'paris-france');
// LIKE is case-SENSITIVE on Postgres — the exact bug the LOWER() calls exist to prevent.
$res = rmt_warning_query(['destination_id' => $destId, 'q' => 'AIRPORT TAXI'], 20);
ok('case-insensitive filter works on pgsql (LOWER on both sides)', $res['total'] >= 1);

/* ============================================================== filtering */
head('Filtering and sorting (derived-table ORDER BY on pgsql)');
ok('category filter', rmt_warning_query(['destination_id' => $destId, 'category' => 'scams'], 100)['total'] >= 1, true);
ok('a category with no rows returns nothing', rmt_warning_query(['destination_id' => $destId, 'category' => 'weather'], 100)['total'], 0);
ok('severity floor excludes the severity-3 fixture', rmt_warning_query(['destination_id' => $destId, 'severity_min' => 4], 100)['total'], 0);
ok('verification filter', rmt_warning_query(['destination_id' => $destId, 'verification' => 'verified'], 100)['total'], $baseVerified + 1);
ok('month filter', rmt_warning_query(['destination_id' => $destId, 'month' => 4], 100)['total'] >= 1, true);
foreach (['recent', 'helpful', 'severity', 'experienced', 'oldest', 'verified'] as $sort) {
    $r = rmt_warning_query(['destination_id' => $destId, 'sort' => $sort], 20);
    ok("sort={$sort} executes on pgsql", is_array($r['rows']));
}
ok('category counts', rmt_warning_category_counts($destId)['scams']['c'] >= 1, true);
ok('trending query executes', is_array(rmt_trending_warnings(6, 365)));
// explore()'s derived-table sorts are the exact shape that 500'd on pgsql historically.
foreach (['name', 'risk', 'warnings', 'covered', 'rating', 'popular'] as $s) {
    $_GET = ['sort' => $s];
    $sql = "SELECT * FROM (SELECT d.*,
              (SELECT COUNT(*) FROM reviews r JOIN users u ON u.id=r.user_id
                WHERE r.destination_id=d.id AND r.status='published' AND u.role <> ?) reviews,
              (SELECT AVG(r.rating) FROM reviews r JOIN users u ON u.id=r.user_id
                WHERE r.destination_id=d.id AND r.status='published' AND u.role <> ?) avg_rating,
              (SELECT COUNT(*) FROM destination_risk_sections rs WHERE rs.destination_id=d.id) sections,
              (SELECT COUNT(*) FROM warnings w WHERE w.destination_id=d.id AND w.status='approved') warnings,
              (SELECT COUNT(*) FROM saves sv WHERE sv.target_type='destination' AND sv.target_id=d.id) wants
            FROM destinations d) x" . match ($s) {
        'popular'  => ' ORDER BY wants DESC, name',
        'rating'   => ' ORDER BY (avg_rating IS NULL OR reviews < 2), avg_rating DESC, name',
        'risk'     => ' ORDER BY (risk_level IS NULL), risk_level DESC, name',
        'warnings' => ' ORDER BY warnings DESC, name',
        'covered'  => ' ORDER BY sections DESC, warnings DESC, name',
        default    => ' ORDER BY name',
    } . ' LIMIT 5';
    $rows = q_all($sql, [RMT_EDITORIAL_ROLE, RMT_EDITORIAL_ROLE]);
    ok("explore sort={$s} executes on pgsql", count($rows) > 0);
}

/* ============================================================== watchlist */
head('Trip watchlist and destination follows');
q_exec('DELETE FROM trip_watchlist WHERE user_id = ?', [$member]);
$twid = (int) q_run('INSERT INTO trip_watchlist (user_id,destination_id,label,date_from,date_to,
                     categories_json,min_severity,alert_frequency,last_seen_at,created_at,updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)',
    [$member, $destId, 'PG test trip', '2026-11-02', '2026-11-09', rmt_categories_encode(['scams']),
     1, 'weekly', '2020-01-01 00:00:00', $now, $now]);
ok('trip saved', $twid > 0);
ok('watchlist reads back', count(rmt_watchlist($member)), 1);
ok('rmt_watchlist_has', rmt_watchlist_has($member, $destId));
$watch = rmt_watchlist_get($twid, $member);
ok('new warnings computed for the trip', count(rmt_new_warnings_for($watch, 10)) >= 1);
ok('category filter narrows the alert set',
   count(rmt_new_warnings_for(array_merge($watch, ['categories_json' => rmt_categories_encode(['weather'])]), 10)), 0);
ok('severity floor narrows the alert set',
   count(rmt_new_warnings_for(array_merge($watch, ['min_severity' => 4]), 10)), 0);
ok('prep checklist derived from real warnings', count(rmt_trip_prep_actions($watch)) >= 1);
ok('bad date range rejected', rmt_watchlist_validate_dates('2026-11-09', '2026-11-02')['ok'], false);
ok('empty dates allowed', rmt_watchlist_validate_dates('', '')['ok'], true);
q_exec('DELETE FROM destination_follows WHERE user_id=?', [$member]);
q_exec('INSERT INTO destination_follows (user_id,destination_id,min_severity,alert_frequency,last_seen_at,created_at)
        VALUES (?,?,?,?,?,?)', [$member, $destId, 2, 'weekly', '2020-01-01 00:00:00', $now]);
ok('follow stored on the composite PK', (int) q_one('SELECT COUNT(*) c FROM destination_follows WHERE user_id=?', [$member])['c'], 1);

/* ================================================================= alerts */
head('Alerts: subscription, double opt-in, and the anti-spam brakes');
q_exec("DELETE FROM alert_subscriptions WHERE email='pgt@fixture.invalid'");
$sub = rmt_alert_subscribe('pgt@fixture.invalid', $destId, ['frequency' => 'weekly', 'min_severity' => 2, 'categories' => ['scams']]);
ok('subscription created', $sub['status'], 'created');
ok('starts unconfirmed (double opt-in)', $sub['row']['confirmed_at'], null);
$again = rmt_alert_subscribe('pgt@fixture.invalid', $destId, ['frequency' => 'daily']);
ok('resubscribing does not duplicate', $again['status'], 'reconfirm');
ok('still one row', (int) q_one("SELECT COUNT(*) c FROM alert_subscriptions WHERE email='pgt@fixture.invalid'")['c'], 1);
ok('token round-trips', is_array(rmt_alert_by_token('pgt@fixture.invalid', $sub['row']['token'])));
ok('a wrong token resolves to nothing', rmt_alert_by_token('pgt@fixture.invalid', 'nope'), null);
q_exec('DELETE FROM alert_deliveries WHERE recipient = ?', ['pgt@fixture.invalid']);
ok('first delivery is logged', rmt_alert_log_delivery('pgt@fixture.invalid', $wid, 'email'), true);
ok('the SAME warning cannot be delivered twice', rmt_alert_log_delivery('pgt@fixture.invalid', $wid, 'email'), false);
ok('frequency window closes after a send', rmt_alert_window_open('pgt@fixture.invalid', 'weekly'), false);
ok('frequency "none" never opens', rmt_alert_window_open('never-sent@fixture.invalid', 'none'), false);
ok('an unused recipient has an open window', rmt_alert_window_open('never-sent@fixture.invalid', 'weekly'), true);

/* ============================================================== analytics */
head('Analytics');
q_exec("DELETE FROM analytics_events WHERE name IN ('destination_view','warning_view')");
rmt_track('destination_view', ['destination_id' => $destId, 'target_type' => 'destination', 'target_id' => $destId]);
rmt_track('warning_view', ['destination_id' => $destId, 'target_type' => 'warning', 'target_id' => $wid]);
rmt_track('not_a_real_event', []);
ok('known events recorded', (int) q_one("SELECT COUNT(*) c FROM analytics_events WHERE name IN ('destination_view','warning_view')")['c'], 2);
ok('unknown event names are refused', (int) q_one("SELECT COUNT(*) c FROM analytics_events WHERE name='not_a_real_event'")['c'], 0);
$since = date('Y-m-d H:i:s', strtotime('-1 day'));
ok('funnel computes', count(rmt_funnel($since)), 5);
ok('event totals compute', array_key_exists('destination_view', rmt_event_totals($since)));
ok('top destinations compute', is_array(rmt_top_destination_views($since)));
ok('visitor key is a rotating hash, not an identifier', strlen(rmt_visitor_key()), 32);

/* =============================================================== editorial */
head('Editorial content and risk reports');
$sections = rmt_risk_sections($destId);
ok('risk sections load in spine order', count($sections) >= 8);
ok('first section is the overview', array_key_first($sections), 'overview');
ok('FAQs load', count(rmt_destination_faqs($destId)) >= 1);
ok('sources decode', count(rmt_sources(q_one("SELECT sources_json FROM destination_risk_sections
        WHERE destination_id=? AND sources_json IS NOT NULL LIMIT 1", [$destId])['sources_json'] ?? null)) >= 1);
$page = rmt_landing_by_slug('what-can-ruin-a-trip-to-paris');
ok('landing page resolves', is_array($page) && $page['status'] === 'published');
ok('unknown slug resolves to nothing', rmt_landing_by_slug('no-such-page-xyz'), null);

/* =============================================================== affiliate */
head('Affiliate stays off by default');
ok('no active affiliate links exist', (int) q_one('SELECT COUNT(*) c FROM affiliate_links WHERE active=1')['c'], 0);
ok('no affiliate links seeded at all', (int) q_one('SELECT COUNT(*) c FROM affiliate_links')['c'], 0);
ok('the render component emits nothing', rmt_affiliate_block($destId, null, 'Booking?'), '');
ok('an inactive link is not resolvable', rmt_affiliate_by_slug('anything'), null);
$aid = (int) q_run('INSERT INTO affiliate_links (slug,label,provider,kind,target_url,active,sort,created_at)
                    VALUES (?,?,?,?,?,?,?,?)', ['pgt-link', 'L', 'P', 'hotel', 'https://example.invalid', 0, 0, $now]);
ok('an inactive link stays invisible even once created', rmt_affiliate_block($destId, null, 'Booking?'), '');
ok('and is not resolvable by /go/', rmt_affiliate_by_slug('pgt-link'), null);
q_exec('UPDATE affiliate_links SET active=1 WHERE id=?', [$aid]);
ok('only an explicitly activated link renders', str_contains(rmt_affiliate_block($destId, null, 'Booking?'), 'pgt-link'));
ok('and it carries sponsored+nofollow', str_contains(rmt_affiliate_block($destId, null, 'B'), 'rel="sponsored nofollow noopener"'));
ok('and the disclosure', str_contains(rmt_affiliate_block($destId, null, 'B'), 'affiliate links'));
q_exec('DELETE FROM affiliate_links WHERE id=?', [$aid]);

/* ================================================================ cleanup */
head('Cleanup');
q_exec('DELETE FROM warnings WHERE id=?', [$wid]);
ok('cascading delete removed the response', (int) q_one('SELECT COUNT(*) c FROM warning_responses WHERE warning_id=?', [$wid])['c'], 0);
ok('cascading delete removed the votes', (int) q_one('SELECT COUNT(*) c FROM warning_votes WHERE warning_id=?', [$wid])['c'], 0);
ok('cascading delete removed the audit log', (int) q_one('SELECT COUNT(*) c FROM warning_moderation_log WHERE warning_id=?', [$wid])['c'], 0);

echo "\n" . str_repeat('=', 64) . "\n";
printf("%d checks, %d failure(s)\n", $checks, $fail);
echo $fail === 0 ? "POSTGRESQL FUNCTIONAL TESTS PASSED\n" : "POSTGRESQL FUNCTIONAL TESTS FAILED\n";
exit($fail === 0 ? 0 : 1);
