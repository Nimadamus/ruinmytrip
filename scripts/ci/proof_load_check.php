<?php
/**
 * Load check for /cron/proof before it ships (2026-10-11). Builds a throwaway SQLite database with
 * N synthetic events spread over 31 days, runs rmt_proof(31) under production's memory_limit, and
 * prints peak memory and wall time. Production measured 2026-10-11: 29,999 sessions and roughly
 * 52,000 events in 31 days, so the default N of 160,000 is three times the real volume.
 *
 *   php -d memory_limit=256M scripts/ci/proof_load_check.php [events] [visitors]
 *
 * Writes only to a temp file it deletes. Exits non-zero if the run fails or uses more than half of
 * memory_limit.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
$tmp = tempnam(sys_get_temp_dir(), 'proofload') . '.sqlite';
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
                      'db_driver' => 'sqlite', 'sqlite_path' => $tmp];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/growth_scorecard.php';
require BASE_PATH . '/app/kpi.php';
require BASE_PATH . '/app/proof.php';

$n = (int) ($argv[1] ?? 160000);
$visitors = (int) ($argv[2] ?? 90000);
$pdo = db();
$pdo->exec("CREATE TABLE contribution_events (id INTEGER PRIMARY KEY AUTOINCREMENT, event TEXT, source TEXT, journey TEXT,
            visitor TEXT, cookied INTEGER, place_id INTEGER, destination_id INTEGER, is_authed INTEGER, reason TEXT,
            acq_source TEXT, acq_medium TEXT, acq_campaign TEXT, acq_content TEXT, path TEXT, detail TEXT, created_at TEXT)");
$pdo->exec("CREATE INDEX ix ON contribution_events (created_at)");
foreach (['users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, status TEXT, created_at TEXT, email_verified_at TEXT)',
          'reviews (id INTEGER PRIMARY KEY, user_id INT, status TEXT, created_at TEXT)',
          'trips (id INTEGER PRIMARY KEY, user_id INT, created_at TEXT)', 'buddy_posts (id INTEGER PRIMARY KEY, user_id INT, created_at TEXT)',
          'messages (id INTEGER PRIMARY KEY, sender_id INT, created_at TEXT)', 'match_alerts (id INTEGER PRIMARY KEY, confirmed_at TEXT)'] as $t) {
    $pdo->exec("CREATE TABLE $t");
}
$events = ['landing_view', 'landing_view', 'landing_view', 'human_interaction', 'review_form_start', 'review_signup_required',
           'join_view', 'destination_page_view', 'human_verified', 'review_submit_attempt', 'cta_click', 'map_view'];
$paths = ['/p/a', '/p/b', '/d/lisbon-portugal', '/blog/x', '/travel-buddies/iceland', '/review/new', '/', '/map'];
$pdo->beginTransaction();
$ins = $pdo->prepare('INSERT INTO contribution_events (event, journey, visitor, cookied, path, acq_source, created_at) VALUES (?,?,?,?,?,?,?)');
$start = time() - 31 * 86400;
mt_srand(7);
for ($i = 0; $i < $n; $i++) {
    $v = 'v' . mt_rand(1, $visitors);
    $ins->execute([$events[mt_rand(0, count($events) - 1)], 'j' . $v, $v, mt_rand(0, 1), $paths[mt_rand(0, count($paths) - 1)],
                   ['direct', 'search', 'facebook', 'reddit'][mt_rand(0, 3)], date('Y-m-d H:i:s', $start + mt_rand(0, 31 * 86400 - 60))]);
}
$pdo->commit();

$t0 = microtime(true);
$base = memory_get_usage(true);
$p = rmt_proof(31);
$secs = microtime(true) - $t0;
$peak = memory_get_peak_usage(true);
$limit = (int) ini_get('memory_limit') * 1024 * 1024;
printf("events=%d browsers=%d rows_read=%d peak=%.1fMB (limit %s) time=%.2fs humans=%d bots=%d unverified=%d\n",
       $n, $visitors, $p['events_read'], $peak / 1048576, ini_get('memory_limit'), $secs,
       $p['totals']['verified_humans'], $p['totals']['suspected_bots'], $p['totals']['unverified']);
@unlink($tmp);
exit(($limit > 0 && $peak > $limit / 2) ? 1 : 0);
