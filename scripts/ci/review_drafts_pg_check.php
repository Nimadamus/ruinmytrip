<?php
/**
 * Migration 107 and the server side review draft against a real Postgres (2026-10-11).
 *
 *   DATABASE_URL=postgres://... php scripts/ci/review_drafts_pg_check.php
 *
 * Run against a THROWAWAY database only (it writes and drops review_drafts). It refuses a host that
 * is not 127.0.0.1 or localhost, so it can never be pointed at production by accident.
 *
 * What it proves:
 *   - the table migration 107 made accepts, returns and deletes a draft on Postgres;
 *   - the token is stored only as a hash; another token reads nothing; an expired row reads nothing;
 *   - the rollback file (database/rollback/107_down.sql) removes the table and the ledger row in one go;
 *   - with the table gone, every draft call degrades to "no draft" and nothing throws, which is what
 *     the previous release relies on after a rollback;
 *   - the migrator recreates the table after the rollback.
 */
declare(strict_types=1);

$url = (string) getenv('DATABASE_URL');
$host = (string) parse_url($url, PHP_URL_HOST);
if (!in_array($host, ['127.0.0.1', 'localhost'], true)) { fwrite(STDERR, "refusing: DATABASE_URL host is not local ($host)\n"); exit(2); }

require dirname(__DIR__, 2) . '/app/bootstrap.php';
$fails = 0;
function ok(string $n, bool $c, string $d = ''): void { global $fails; if (!$c) { $fails++; echo "FAIL  $n" . ($d ? " -- $d" : '') . "\n"; } else echo "PASS  $n\n"; }

ok('driver is pgsql', ($GLOBALS['config']['db_driver'] ?? '') === 'pgsql');
$ledger = q_one("SELECT version FROM schema_migrations WHERE version LIKE '107%'");
ok('migration 107 is in the ledger', (bool) $ledger, json_encode($ledger));

unset($_COOKIE[RMT_REVIEW_DRAFT_COOKIE]);
$post = ['_csrf' => 'x', 'place_id' => '39', 'destination_id' => '78', 'subject_type' => 'restaurant',
         'subject_name' => 'A Brasileira', 'rating' => '5', 'body' => 'Great coffee, the terrace is a trap at noon.'];
ok('put works on Postgres', rmt_review_draft_put($post));
$tok = rmt_review_draft_token();
$row = q_one('SELECT token_hash, payload FROM review_drafts WHERE token_hash = ?', [hash('sha256', $tok)]);
ok('only the hash is stored', $row && !str_contains(json_encode($row), $tok));
ok('no CSRF token in the payload', $row && !str_contains((string) $row['payload'], '_csrf'));
ok('get returns it', (rmt_review_draft_get()['body'] ?? '') === $post['body']);
$_COOKIE[RMT_REVIEW_DRAFT_COOKIE] = str_repeat('b', 48);
ok('another token reads nothing', rmt_review_draft_get() === null);
$_COOKIE[RMT_REVIEW_DRAFT_COOKIE] = $tok;
q_run('UPDATE review_drafts SET expires_at = ? WHERE token_hash = ?', ['2000-01-01 00:00:00', hash('sha256', $tok)]);
ok('an expired draft reads nothing', rmt_review_draft_get() === null);
q_run('UPDATE review_drafts SET expires_at = ? WHERE token_hash = ?', ['2099-01-01 00:00:00', hash('sha256', $tok)]);
rmt_review_draft_clear();
ok('clear deletes the row', (int) q_one('SELECT COUNT(*) c FROM review_drafts')['c'] === 0);

// Rollback, exactly as written in the rollback file.
$sql = (string) file_get_contents(BASE_PATH . '/database/rollback/107_down.sql');
db()->exec($sql);
$exists = (int) (q_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_name = 'review_drafts'")['c'] ?? 0);
ok('rollback drops the table', $exists === 0);
ok('rollback removes the ledger row', !q_one("SELECT version FROM schema_migrations WHERE version LIKE '107%'"));
unset($_COOKIE[RMT_REVIEW_DRAFT_COOKIE]);
$threw = false; $p = null; $g = 'unset';
try { $p = rmt_review_draft_put($post); $g = rmt_review_draft_get(); rmt_review_draft_clear(); } catch (Throwable $e) { $threw = true; }
/* PGlite, the local stand-in, leaves its one backend inside the failed statement's transaction;
   real Postgres in autocommit does not. A ROLLBACK clears it and is a no-op on real Postgres. */
try { db()->exec('ROLLBACK'); } catch (Throwable $e) { }
ok('with the table gone nothing throws', !$threw);
ok('...put reports failure', $p === false);
ok('...get reads no draft', $g === null);
/* PGlite (the local stand-in) cannot run another statement on a connection after an error; real
   Postgres can. So the recreate step runs on a fresh connection, as it does on a real deploy. */
$GLOBALS['__rmt_pdo'] = null;
foreach (['__rmt_db', 'rmt_pdo', 'pdo'] as $k) unset($GLOBALS[$k]);
if (function_exists('db_reset')) db_reset();
$fresh = new PDO('pgsql:host=127.0.0.1;port=' . (parse_url($url, PHP_URL_PORT) ?: 5432) . ';dbname=' . ltrim((string) parse_url($url, PHP_URL_PATH), '/'),
                 (string) parse_url($url, PHP_URL_USER), (string) parse_url($url, PHP_URL_PASS), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// The migrator brings it back.
require_once BASE_PATH . '/app/migrator.php';
$res = rmt_migrate($fresh, 'pgsql', static fn(string $m) => null);
ok('the migrator recreates it after a rollback', in_array('107_review_drafts', $res['applied'], true), json_encode($res['applied']));
$n = (int) $fresh->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'review_drafts'")->fetchColumn();
ok('and the table is back', $n === 1);

echo $fails ? "\nreview_drafts_pg_check: $fails FAILED\n" : "\nreview_drafts_pg_check: all passed\n";
exit($fails ? 1 : 0);
