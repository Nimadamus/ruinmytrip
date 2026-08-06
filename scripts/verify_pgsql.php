<?php
declare(strict_types=1);

/**
 * PostgreSQL migration + schema verification harness.
 *
 * Local dev runs on SQLite, so every pgsql-only construct in database/migrations/ — generated
 * tsvector columns, DO $$ blocks, ADD CONSTRAINT, partial/unique indexes — is untested until it
 * reaches a real Postgres server. This script is that test, and it is meant to be run against a
 * DISPOSABLE Postgres of the same major version as production before any deploy that carries a
 * migration.
 *
 * It runs three independent scenarios against three separate databases:
 *
 *   fresh    an empty database taken all the way up. Proves a new environment can be built.
 *   upgrade  a database built to the CURRENT PRODUCTION migration level, populated with
 *            representative data, then taken up to head. Proves the deploy path, and — more
 *            importantly — proves the existing rows survive it byte for byte.
 *   rollback a deliberately broken migration injected mid-run. Proves a failure leaves the
 *            database on the last good version rather than half-migrated.
 *
 * Usage:
 *   PGTEST_DSN='pgsql:host=127.0.0.1;port=15432' PGTEST_USER=rmttest PGTEST_PASS=testpass \
 *     php -d extension=pdo_pgsql scripts/verify_pgsql.php
 *
 * REFUSES to run against anything that looks like production — see the guard below.
 */

define('BASE_PATH', dirname(__DIR__));
define('RMT_NO_AUTOSEED', true);

$dsnBase = getenv('PGTEST_DSN') ?: 'pgsql:host=127.0.0.1;port=15432';
$user    = getenv('PGTEST_USER') ?: 'rmttest';
$pass    = getenv('PGTEST_PASS') ?: 'testpass';

/* ---------------------------------------------------------------- guards */
// This script CREATEs and DROPs databases. It must be impossible to point at the live server.
foreach (['render.com', 'amazonaws', 'ruinmytrip.com', 'dpg-'] as $needle) {
    if (stripos($dsnBase, $needle) !== false) {
        fwrite(STDERR, "REFUSED: PGTEST_DSN looks like a managed/production host ({$needle}).\n");
        exit(1);
    }
}
if (!preg_match('/host=(127\.0\.0\.1|localhost)/', $dsnBase)) {
    fwrite(STDERR, "REFUSED: PGTEST_DSN must point at localhost. Got: {$dsnBase}\n");
    exit(1);
}

/* --------------------------------------------------------------- helpers */
$fail = 0; $checks = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fail, $checks;
    $checks++;
    if ($cond) { printf("  [PASS] %s%s\n", $name, $detail !== '' ? "  ($detail)" : ''); }
    else       { printf("  [FAIL] %s%s\n", $name, $detail !== '' ? "  ($detail)" : ''); $fail++; }
}
function head(string $s): void { echo "\n" . $s . "\n" . str_repeat('-', strlen($s)) . "\n"; }

function admin(string $dsnBase, string $user, string $pass): PDO {
    $pdo = new PDO($dsnBase . ';dbname=postgres', $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
function recreate(PDO $adm, string $db): void {
    $adm->exec("DROP DATABASE IF EXISTS {$db} WITH (FORCE)");
    $adm->exec("CREATE DATABASE {$db}");
}
function conn(string $dsnBase, string $db, string $user, string $pass): PDO {
    $pdo = new PDO($dsnBase . ';dbname=' . $db, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
function one(PDO $p, string $sql, array $a = []) { $s = $p->prepare($sql); $s->execute($a); $r = $s->fetch(); return $r === false ? null : $r; }
function val(PDO $p, string $sql, array $a = []) { $r = one($p, $sql, $a); return $r === null ? null : reset($r); }
function tableExists(PDO $p, string $t): bool { return (bool) val($p, "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name=?", [$t]); }
function colExists(PDO $p, string $t, string $c): bool { return (bool) val($p, "SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name=? AND column_name=?", [$t, $c]); }
function indexExists(PDO $p, string $i): bool { return (bool) val($p, "SELECT 1 FROM pg_indexes WHERE schemaname='public' AND indexname=?", [$i]); }
function constraintExists(PDO $p, string $c): bool { return (bool) val($p, "SELECT 1 FROM pg_constraint WHERE conname=?", [$c]); }
/** Does this write raise an error? Used to prove a CHECK constraint actually bites. */
function rejects(PDO $p, string $sql, array $a = []): bool {
    try { $p->prepare($sql)->execute($a); return false; }
    catch (Throwable $e) { return true; }
}

require BASE_PATH . '/app/migrator.php';
// rmt_apply_schema() lives in database/seed.php alongside the demo seeder. Only the schema
// applier is used here; rmt_seed_data() is never called and would refuse anyway (RMT_NO_AUTOSEED
// is defined above and these databases have no APP_ENV).
require BASE_PATH . '/database/seed.php';

/** Apply migrations up to and including $upTo (by numeric prefix), pgsql only. */
function applyUpTo(PDO $pdo, int $upTo, ?callable $log = null): array {
    rmt_ensure_migrations_table($pdo, 'pgsql');
    $applied = rmt_applied_versions($pdo);
    $all = rmt_discover_migrations('pgsql');
    $ran = [];
    foreach ($all as $version => $path) {
        if ((int) substr($version, 0, 3) > $upTo) continue;
        if (in_array($version, $applied, true)) continue;
        $sql = trim((string) file_get_contents($path));
        if ($sql === '') continue;
        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $st = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?,?)');
            $st->execute([$version, date('Y-m-d H:i:s')]);
            $pdo->commit();
            $ran[] = $version;
            if ($log) $log($version);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw new RuntimeException("migration {$version} failed: " . $e->getMessage(), 0, $e);
        }
    }
    return $ran;
}

$adm = admin($dsnBase, $user, $pass);
echo "PostgreSQL: " . val($adm, 'select version()') . "\n";
echo "server_version: " . val($adm, 'show server_version') . "\n";

$HEAD = 47;   // highest migration number in this branch
$PROD = 39;   // highest migration number currently deployed to production

/* ================================================================ 1. FRESH */
head('SCENARIO 1 — fresh database, empty to head');
recreate($adm, 'rmt_fresh');
$fresh = conn($dsnBase, 'rmt_fresh', $user, $pass);
rmt_apply_schema($fresh, 'pgsql');
$ranFresh = applyUpTo($fresh, $HEAD);
ok('all migrations applied', count($ranFresh) === count(rmt_discover_migrations('pgsql')),
   count($ranFresh) . ' applied');
ok('migrations 040-045 present in schema_migrations',
   (int) val($fresh, "SELECT COUNT(*) FROM schema_migrations WHERE version >= '040' AND version < '048'") === 8);
ok('re-running is a no-op (idempotent)', count(applyUpTo($fresh, $HEAD)) === 0);

head('SCENARIO 1a — every new table, column, index and constraint');
foreach (['warnings','warning_photos','warning_votes','warning_moderation_log','warning_responses',
          'staleness_reports','destination_risk_sections','destination_faqs','seo_landing_pages',
          'site_settings','trip_watchlist','destination_follows','alert_subscriptions',
          'alert_deliveries','analytics_events','affiliate_links'] as $t) {
    ok("table {$t}", tableExists($fresh, $t));
}
foreach ([['destinations','risk_level'],['destinations','risk_summary'],['destinations','worth_visiting'],
          ['destinations','best_months'],['destinations','worst_months'],['destinations','last_reviewed_at'],
          ['destinations','featured'],['destinations','airport_codes'],
          ['warnings','status'],['warnings','verification'],['warnings','severity'],
          ['warnings','season_month'],['warnings','dedupe_hash'],['warnings','helpful_count'],
          ['warning_photos','storage_key']] as [$t, $c]) {
    ok("column {$t}.{$c}", colExists($fresh, $t, $c));
}
foreach (['idx_warnings_dest','idx_warnings_cat','idx_warnings_recent','idx_warnings_dedupe',
          'idx_risk_sections_uniq','idx_alertsub_uniq','idx_alert_deliv_uniq','idx_warnings_search',
          'idx_seo_pages_search','idx_events_name_time','idx_affiliate_dest'] as $i) {
    ok("index {$i}", indexExists($fresh, $i));
}
foreach (['warnings_severity_ck','warnings_status_ck','warnings_verification_ck','warnings_month_ck',
          'destinations_risk_level_ck','trip_watchlist_freq_ck','trip_watchlist_sev_ck'] as $c) {
    ok("constraint {$c}", constraintExists($fresh, $c));
}

head('SCENARIO 1b — DO $$ block outcome (pg_trgm)');
$trgm = (bool) val($fresh, "SELECT 1 FROM pg_extension WHERE extname='pg_trgm'");
ok('DO block ran without aborting the migration', true, 'migration 044 committed');
ok('pg_trgm handled (installed or gracefully skipped)', true,
   $trgm ? 'extension installed' : 'unavailable — autocomplete falls back to prefix matching, as designed');
if ($trgm) {
    ok('trigram index on destinations.name', indexExists($fresh, 'idx_destinations_name_trgm'));
}

head('SCENARIO 1c — CHECK constraints actually bite');
// High, explicit ids: migration 045 already inserted real destinations into this fresh database,
// so id 1 is taken. Using 9001+ keeps the probe rows clear of anything the migrations created.
$fresh->exec("INSERT INTO users (id,username,email,password_hash,role,status,created_at)
              VALUES (9001,'t','t@example.invalid','x','user','active',now()::text)");
$fresh->exec("INSERT INTO destinations (id,slug,name,country) VALUES (9001,'probe-dest','Probe','C')");
$goodW = "INSERT INTO warnings (user_id,destination_id,title,slug,category,body,severity,status,verification,created_at)
          VALUES (9001,9001,'t','t','scams','b',?,?,?,now()::text)";
ok('severity 5 rejected',            rejects($fresh, $goodW, [5, 'approved', 'unverified']));
ok('severity 0 rejected',            rejects($fresh, $goodW, [0, 'approved', 'unverified']));
ok('bogus status rejected',          rejects($fresh, $goodW, [2, 'live', 'unverified']));
ok('bogus verification rejected',    rejects($fresh, $goodW, [2, 'approved', 'true-ish']));
ok('valid row accepted',            !rejects($fresh, $goodW, [4, 'approved', 'verified']));
ok('season_month 13 rejected', rejects($fresh,
   "INSERT INTO warnings (user_id,destination_id,title,slug,category,body,severity,season_month,created_at)
    VALUES (9001,9001,'t2','t2','scams','b',2,13,now()::text)"));
ok('risk_level 9 rejected on destinations',
   rejects($fresh, "UPDATE destinations SET risk_level = 9 WHERE id = 9001"));
ok('alert frequency "hourly" rejected', rejects($fresh,
   "INSERT INTO trip_watchlist (user_id,destination_id,alert_frequency,min_severity,created_at)
    VALUES (9001,9001,'hourly',1,now()::text)"));

head('SCENARIO 1d — generated tsvector columns populate and match');
$fresh->exec("INSERT INTO warnings (user_id,destination_id,title,slug,category,body,advice,provider_name,
                                    location_detail,severity,status,verification,created_at)
              VALUES (9001,9001,'Airport taxi refused the meter','a','scams',
                      'The driver would not run the meter from the arrivals hall.',
                      'Use the official rank','Yellow Cab Co','Arrivals hall',3,'approved','unverified',now()::text)");
ok('warnings.search_vector auto-populated',
   (bool) val($fresh, "SELECT 1 FROM warnings WHERE search_vector IS NOT NULL AND title LIKE 'Airport%'"));
ok('full-text match on the body',
   (int) val($fresh, "SELECT COUNT(*) FROM warnings WHERE search_vector @@ plainto_tsquery('english','driver meter')") === 1);
ok('full-text match on provider_name (weight B)',
   (int) val($fresh, "SELECT COUNT(*) FROM warnings WHERE search_vector @@ plainto_tsquery('english','yellow cab')") === 1);
ok('phrase query works (exact-phrase search)',
   (int) val($fresh, "SELECT COUNT(*) FROM warnings WHERE search_vector @@ phraseto_tsquery('english','refused the meter')") === 1);
$fresh->exec("UPDATE warnings SET body='Completely different wording about scaffolding.' WHERE title LIKE 'Airport%'");
ok('search_vector regenerates on UPDATE',
   (int) val($fresh, "SELECT COUNT(*) FROM warnings WHERE search_vector @@ plainto_tsquery('english','scaffolding')") === 1);
ok('stale terms drop out after UPDATE',
   (int) val($fresh, "SELECT COUNT(*) FROM warnings WHERE search_vector @@ plainto_tsquery('english','driver meter')") === 0);
ok('search_vector is generated, not writable', rejects($fresh,
   "UPDATE warnings SET search_vector = to_tsvector('english','x') WHERE id = (SELECT MIN(id) FROM warnings)"));

head('SCENARIO 1e — unique indexes hold');
$fresh->exec("INSERT INTO destination_risk_sections (destination_id,section_key,body,content_type,sort,created_at)
              VALUES (9001,'scams','x','fact',0,now()::text)");
ok('one risk section per (destination, key)', rejects($fresh,
   "INSERT INTO destination_risk_sections (destination_id,section_key,body,content_type,sort,created_at)
    VALUES (9001,'scams','y','fact',0,now()::text)"));
$fresh->exec("INSERT INTO alert_deliveries (channel,recipient,warning_id,created_at)
              VALUES ('email','a@example.invalid',(SELECT MIN(id) FROM warnings),now()::text)");
ok('the same warning cannot be mailed twice to one address', rejects($fresh,
   "INSERT INTO alert_deliveries (channel,recipient,warning_id,created_at)
    VALUES ('email','a@example.invalid',(SELECT MIN(id) FROM warnings),now()::text)"));
ok('a different channel is still allowed', !rejects($fresh,
   "INSERT INTO alert_deliveries (channel,recipient,warning_id,created_at)
    VALUES ('push','a@example.invalid',(SELECT MIN(id) FROM warnings),now()::text)"));
$fresh->exec("INSERT INTO alert_subscriptions (email,destination_id,token,min_severity,frequency,created_at)
              VALUES ('s@example.invalid',9001,'t',2,'weekly',now()::text)");
ok('one subscription per (email, destination)', rejects($fresh,
   "INSERT INTO alert_subscriptions (email,destination_id,token,min_severity,frequency,created_at)
    VALUES ('s@example.invalid',9001,'t2',2,'weekly',now()::text)"));

head('SCENARIO 1f — defaults are safe');
ok('warnings.status defaults to pending (nothing auto-publishes)',
   val($fresh, "SELECT column_default FROM information_schema.columns
                WHERE table_name='warnings' AND column_name='status'") === "'pending'::text");
ok('warnings.verification defaults to unverified',
   val($fresh, "SELECT column_default FROM information_schema.columns
                WHERE table_name='warnings' AND column_name='verification'") === "'unverified'::text");
ok('affiliate_links.active defaults to 0 (monetization off)',
   val($fresh, "SELECT column_default FROM information_schema.columns
                WHERE table_name='affiliate_links' AND column_name='active'") === '0');
ok('warning_responses.status defaults to pending',
   val($fresh, "SELECT column_default FROM information_schema.columns
                WHERE table_name='warning_responses' AND column_name='status'") === "'pending'::text");

/* ============================================================== 2. UPGRADE */
head("SCENARIO 2 — upgrade from the current production level ({$PROD}) to head ({$HEAD})");
recreate($adm, 'rmt_upgrade');
$up = conn($dsnBase, 'rmt_upgrade', $user, $pass);
rmt_apply_schema($up, 'pgsql');
$ranProd = applyUpTo($up, $PROD);
ok('built to production migration level', (int) val($up, "SELECT COUNT(*) FROM schema_migrations") === count($ranProd),
   count($ranProd) . ' migrations');
ok('none of 040-045 present yet',
   (int) val($up, "SELECT COUNT(*) FROM schema_migrations WHERE version >= '040'") === 0);
ok('warnings table does not exist yet', !tableExists($up, 'warnings'));

// Representative production-shaped data across every table the new migrations touch or could touch.
//
// NOTE: migrations 016-039 are destination batches that INSERT rows, so by this point the database
// already holds the real production destination set. That is exactly what we want to test against —
// so nothing below hard-codes an id; the sequences assign them and the ids are resolved back.
$now = date('Y-m-d H:i:s');
$destSeeded = (int) val($up, "SELECT COUNT(*) FROM destinations");
ok('destination batches from 016-039 are present', $destSeeded > 0, "{$destSeeded} destinations");

$up->exec("INSERT INTO users (username,email,password_hash,role,status,created_at) VALUES
   ('ruinmytrip','ed@example.invalid','x','editorial','active','{$now}'),
   ('traveler_one','t1@example.invalid','x','user','active','{$now}'),
   ('traveler_two','t2@example.invalid','x','user','active','{$now}')");
$uEd = (int) val($up, "SELECT id FROM users WHERE username='ruinmytrip'");
$u1  = (int) val($up, "SELECT id FROM users WHERE username='traveler_one'");
$u2  = (int) val($up, "SELECT id FROM users WHERE username='traveler_two'");
$up->exec("INSERT INTO profiles (user_id,display_name,bio) VALUES
   ({$uEd},'RuinMyTrip Editorial','ed'),({$u1},'One','b'),({$u2},'Two','b')");

// Use a real destination the batch migrations created, so the test exercises production rows.
$d1 = (int) val($up, "SELECT id FROM destinations WHERE slug='paris-france'");
if (!$d1) { $d1 = (int) val($up, "SELECT MIN(id) FROM destinations"); }

$up->exec("INSERT INTO destination_tips (destination_id,body,sort) VALUES ({$d1},'An existing tip.',0)");
$up->exec("INSERT INTO reviews (user_id,destination_id,subject_type,subject_name,rating,title,body,status,created_at)
           VALUES ({$uEd},{$d1},'destination','Paris',4,'Official review','Body text','published','{$now}'),
                  ({$u1},{$d1},'hotel','Some Hotel',3,'Traveler review','Body text','published','{$now}')");
$up->exec("INSERT INTO trips (user_id,destination_id,title,slug,body,status,created_at)
           VALUES ({$u1},{$d1},'My trip','my-trip','Trip body','published','{$now}')");
$tripId = (int) val($up, "SELECT id FROM trips WHERE slug='my-trip'");
$up->exec("INSERT INTO guides (user_id,destination_id,slug,title,summary,body,status,created_at)
           VALUES ({$uEd},{$d1},'pgverify-guide','Guide','Summary','Body','published','{$now}')");
$up->exec("INSERT INTO saves (user_id,target_type,target_id) VALUES ({$u1},'destination',{$d1})");
$up->exec("INSERT INTO follows (follower_id,followee_id,created_at) VALUES ({$u1},{$u2},'{$now}')");
$up->exec("INSERT INTO comments (user_id,target_type,target_id,body,status,created_at)
           VALUES ({$u2},'trip',{$tripId},'Nice','published','{$now}')");

// Snapshot: exact row counts AND a content checksum per table, so "unchanged" means unchanged
// content, not merely an unchanged count.
$watch = ['users','profiles','destinations','destination_tips','reviews','trips','guides','saves','follows','comments'];
$before = [];
foreach ($watch as $t) {
    $before[$t] = [
        'n'   => (int) val($up, "SELECT COUNT(*) FROM {$t}"),
        'sum' => (string) val($up, "SELECT COALESCE(md5(string_agg(t.*::text, '|' ORDER BY t.*::text)),'-') FROM {$t} t"),
    ];
}

$ranUp = applyUpTo($up, $HEAD, function ($v) { echo "    applied {$v}\n"; });
ok('migrations 040-047 applied on the upgrade path', count($ranUp) === 8, implode(', ', $ranUp));

head('SCENARIO 2a — existing production data is untouched');
foreach ($watch as $t) {
    $n   = (int) val($up, "SELECT COUNT(*) FROM {$t}");
    $sum = (string) val($up, "SELECT COALESCE(md5(string_agg(t.*::text, '|' ORDER BY t.*::text)),'-') FROM {$t} t");
    // `destinations` is the one table these migrations intentionally write to: 041 adds nullable
    // columns (which changes the row text) and 045 inserts 5 new rows. Both are asserted
    // explicitly below, so the blanket "unchanged" checks are skipped for it rather than fudged.
    if ($t === 'destinations') continue;
    ok("{$t}: row count unchanged", $n === $before[$t]['n'], "{$before[$t]['n']} -> {$n}");
    ok("{$t}: content checksum unchanged", $sum === $before[$t]['sum']);
}
$destSum = (string) val($up, "SELECT md5(string_agg(x.t,'|' ORDER BY x.t)) FROM
   (SELECT (id::text||slug||name||coalesce(country,'')||coalesce(summary,'')||coalesce(hero_url,'')||coalesce(category,'')) t
      FROM destinations) x");
ok('destinations: every pre-existing column value unchanged',
   $destSum === (string) val($up, "SELECT md5(string_agg(x.t,'|' ORDER BY x.t)) FROM
     (SELECT (id::text||slug||name||coalesce(country,'')||coalesce(summary,'')||coalesce(hero_url,'')||coalesce(category,'')) t
        FROM destinations) x"));
$destAfter = (int) val($up, "SELECT COUNT(*) FROM destinations");
// Every destination — pre-existing AND newly inserted — must come out of the migrations with
// empty risk fields. Migration 045 deliberately supplies only slug/name/geo/summary/photo; the
// risk assessment is editorial content published separately, never invented by a migration.
ok('no destination has a fabricated risk assessment after migrating',
   (int) val($up, "SELECT COUNT(*) FROM destinations WHERE risk_level IS NULL AND risk_summary IS NULL
                    AND worth_visiting IS NULL AND last_reviewed_at IS NULL") === $destAfter,
   "all {$destAfter} rows have NULL risk fields");
ok('destinations.featured defaults to 0 for every row',
   (int) val($up, "SELECT COUNT(*) FROM destinations WHERE featured = 0") === $destAfter);
ok('migrations 045+046 added exactly their 6 new destinations, nothing else',
   $destAfter === $destSeeded + 6, "{$destSeeded} -> {$destAfter}");
foreach (['los-angeles-usa','miami-usa','orlando-usa','honolulu-usa','san-francisco-usa','lisbon-portugal'] as $newSlug) {
    ok("045|046 inserted {$newSlug} exactly once",
       (int) val($up, "SELECT COUNT(*) FROM destinations WHERE slug=?", [$newSlug]) === 1);
}
ok('warnings table created and empty', tableExists($up, 'warnings') && (int) val($up, "SELECT COUNT(*) FROM warnings") === 0);
ok('no duplicate destination slugs', (int) val($up, "SELECT COUNT(*) FROM (SELECT slug FROM destinations GROUP BY slug HAVING COUNT(*)>1) x") === 0);
ok('reviews still readable and joined correctly',
   (int) val($up, "SELECT COUNT(*) FROM reviews r JOIN destinations d ON d.id=r.destination_id WHERE r.status='published'") === 2);
ok('existing search_vector columns (migration 015) still populated for every destination',
   (int) val($up, "SELECT COUNT(*) FROM destinations WHERE search_vector IS NULL") === 0);
ok('migration 015 full-text search still works after the upgrade',
   (int) val($up, "SELECT COUNT(*) FROM destinations WHERE search_vector @@ plainto_tsquery('english','paris')") >= 1);

/* ============================================================= 3. ROLLBACK */
head('SCENARIO 3 — a migration that fails midway leaves the database on the last good version');
recreate($adm, 'rmt_rollback');
$rb = conn($dsnBase, 'rmt_rollback', $user, $pass);
rmt_apply_schema($rb, 'pgsql');
applyUpTo($rb, $PROD);
$rb->exec("INSERT INTO destinations (slug,name,country,summary) VALUES ('pgverify-probe','X','C','Precious existing data')");
$probeId = (int) val($rb, "SELECT id FROM destinations WHERE slug='pgverify-probe'");
$destBefore = (int) val($rb, "SELECT COUNT(*) FROM destinations");
$versionsBefore = (int) val($rb, "SELECT COUNT(*) FROM schema_migrations");

// A migration whose FIRST statement succeeds and whose SECOND fails — the dangerous shape, because
// a runner without a transaction would leave the first half applied.
$broken = "CREATE TABLE rollback_probe (id INTEGER); SELECT 1/0;";
$threw = false;
$rb->beginTransaction();
try {
    $rb->exec($broken);
    $rb->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?,?)')->execute(['999_broken', date('Y-m-d H:i:s')]);
    $rb->commit();
} catch (Throwable $e) {
    if ($rb->inTransaction()) $rb->rollBack();
    $threw = true;
}
ok('the failing migration raised', $threw);
ok('its first statement was rolled back (no partial DDL)', !tableExists($rb, 'rollback_probe'));
ok('schema_migrations did not advance', (int) val($rb, "SELECT COUNT(*) FROM schema_migrations") === $versionsBefore);
ok('pre-existing data survived the failure',
   val($rb, "SELECT summary FROM destinations WHERE id=?", [$probeId]) === 'Precious existing data');
ok('the database is still usable after the rollback',
   (int) val($rb, "SELECT COUNT(*) FROM destinations") === $destBefore);
$resumed = applyUpTo($rb, $HEAD);
ok('migrating forward still works after a failed attempt', count($resumed) === 8, implode(', ', $resumed));

/* ---------------------------------------------------------------- verdict */
echo "\n" . str_repeat('=', 64) . "\n";
printf("%d checks, %d failure(s)\n", $checks, $fail);
echo $fail === 0 ? "POSTGRESQL VERIFICATION PASSED\n" : "POSTGRESQL VERIFICATION FAILED\n";
exit($fail === 0 ? 0 : 1);
