<?php
/**
 * Regression test: DbSessionHandler must serialize concurrent access to the same session id.
 *
 * Reproduced live in production: a submit token rendered into a form's HTML was completely
 * absent from that session's row in Postgres moments later. Root cause -- read()/write() had no
 * locking at all, unlike PHP's default file-based session handler (which uses flock() so
 * concurrent requests for the same session serialize automatically). Two requests for the same
 * session that overlap even slightly -- confirmed trigger: the browser's own automatic
 * GET /favicon.ico firing alongside the real page request, both racing against a session with no
 * row yet -- each read the same starting snapshot and write back their own full copy;
 * whichever write lands last silently erases the other's changes.
 *
 * This is a static/source-level test (mirrors tests/duplicate_click_race_test.php's approach):
 * true concurrent-request races can't be reproduced deterministically in a single-process test,
 * so it verifies the locking calls are actually present and correctly paired instead.
 *
 *   php tests/session_lock_test.php   -> PASS/FAIL per case, exits non-zero on failure.
 */
declare(strict_types=1);

$src = file_get_contents(dirname(__DIR__) . '/app/session.php');

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) {
    printf("  [%s] %s\n", $ok ? 'PASS' : 'FAIL', $name);
    if (!$ok) $fail++;
};

$check('read() acquires a lock before touching the sessions table',
    (bool) preg_match('/function read\(string \$id\).*?acquireLock\(\$id\).*?SELECT data FROM sessions/s', $src));
$check('close() releases the lock', (bool) preg_match('/function close\(\).*?releaseLock\(\)/s', $src));
$check('pg_advisory_lock used for Postgres (matches the id being locked)',
    strpos($src, 'pg_advisory_lock(hashtext(?))') !== false);
$check('pg_advisory_unlock used to release it', strpos($src, 'pg_advisory_unlock(hashtext(?))') !== false);
$check('MySQL GET_LOCK/RELEASE_LOCK covered too (schema supports mysql)',
    strpos($src, 'GET_LOCK(') !== false && strpos($src, 'RELEASE_LOCK(') !== false);
// The lock must NOT be held via a transaction on the shared db() connection -- that would put
// every unrelated query the request makes inside it. Confirms no beginTransaction/commit pairing
// was introduced instead of the advisory-lock approach.
$check('locking does not use a held transaction on the shared connection',
    strpos($src, 'beginTransaction') === false);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL SESSION LOCK TESTS PASS\n";
