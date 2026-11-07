<?php
/**
 * "Read by N travelers" (2026-11-07): distinct browsers that sent review_read for the review's page,
 * our own selfcheck traffic out, a different review's reads not counted, the bare /review/{id}
 * path counted too. The browser side (trusted interaction, no automation flag, not the writer) is
 * in public/assets/js/contribute-track.js and views/review_show.php.
 *   php tests/review_reads_test.php
 */
putenv('DATABASE_URL');
require dirname(__DIR__) . '/app/bootstrap.php';
$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }
$pdo = db(); $pdo->beginTransaction();
$now = date('Y-m-d H:i:s');
$ins = static fn(string $visitor, string $path, ?string $content = null) => q_run(
    "INSERT INTO contribution_events (event, source, journey, created_at, visitor, path, acq_content) VALUES ('review_read', 'review', ?, ?, ?, ?, ?)",
    ['j' . $visitor, $now, $visitor, $path, $content]);
$ins('v1', '/review/9001/a-slug');
$ins('v1', '/review/9001/a-slug');            // same browser again
$ins('v2', '/review/9001/a-renamed-slug');    // slug changed since
$ins('v3', '/review/9001');                   // bare path
$ins('v4', '/review/9001/a-slug', 'selfcheck');
$ins('v5', '/review/90012/other');            // a different review whose id starts the same
ok(rmt_review_read_count(9001) === 3, 'three distinct readers: repeats once, renamed slug and bare path count, selfcheck and other reviews do not');
ok(rmt_review_read_count(90012) === 1, 'the other review keeps its own reader');
ok(rmt_review_read_count(0) === 0, 'no id, no count');
ok(in_array('review_read', RMT_CONTRIB_EVENTS, true), 'the beacon event is accepted');
$js = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/contribute-track.js');
ok(str_contains($js, "isTrusted !== true") && str_contains($js, 'navigator.webdriver') && str_contains($js, "send('review_read'"), 'the browser side needs a trusted interaction and no automation flag');
$view = (string) file_get_contents(dirname(__DIR__) . '/views/review_show.php');
ok(str_contains($view, '$rmtMemberReview && !$isOwn): ?><span data-review-read hidden>'), 'the writer\'s own visits are never asked for');
$pdo->rollBack();
echo $fails ? "review_reads_test: $fails FAILED\n" : "review_reads_test: all passed\n";
exit($fails ? 1 : 0);
