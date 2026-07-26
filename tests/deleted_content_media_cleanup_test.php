<?php
/**
 * Regression test: deleting a trip or review must clean up its uploaded photo blobs, not just
 * soft-delete the parent row.
 *
 * Found live: trip_delete()/review_delete() only ever set status='removed' on the parent row --
 * neither touched trip_photos/review_photos or the underlying media table at all. Photo bytes
 * are stored directly as blobs in the media table (storage_key), so leaving that row behind
 * meant the exact same image stayed fully downloadable at its direct /media/{key} URL forever,
 * even after the owner "deleted" the trip or review that showed it. Confirmed live: uploaded a
 * real photo via /trip/new, its storage_key row existed in media, and nothing in trip_delete()
 * would ever have removed it.
 *
 * trip_delete()/review_delete() both end in redirect(), which calls exit() -- they can't be
 * invoked directly in a test process. This is a static/source-level test (mirrors
 * tests/search_case_test.php's approach): verifies the cleanup loop is actually present, keyed
 * off the right table, and calls rmt_storage_delete() with the row's storage_key.
 *
 *   php tests/deleted_content_media_cleanup_test.php   -> PASS/FAIL per case, exits non-zero on failure.
 */
declare(strict_types=1);

$src = file_get_contents(dirname(__DIR__) . '/app/controllers.php');

function extract_fn(string $src, string $name): string {
    $start = strpos($src, "function {$name}(");
    if ($start === false) return '';
    $depth = 0; $i = strpos($src, '{', $start); $bodyStart = $i;
    for (; $i < strlen($src); $i++) {
        if ($src[$i] === '{') $depth++;
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) return substr($src, $bodyStart, $i - $bodyStart + 1); }
    }
    return '';
}

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) {
    printf("  [%s] %s\n", $ok ? 'PASS' : 'FAIL', $name);
    if (!$ok) $fail++;
};

$cases = [
    'trip_delete'   => 'trip_photos',
    'review_delete' => 'review_photos',
];

foreach ($cases as $fn => $table) {
    $body = extract_fn($src, $fn);
    $check("{$fn}() found in controllers.php", $body !== '');
    $check("{$fn}() queries {$table} for storage keys before/after soft-deleting the row",
        (bool) preg_match('/SELECT storage_key FROM ' . preg_quote($table, '/') . '/', $body));
    $check("{$fn}() calls rmt_storage_delete() on each photo's storage_key",
        (bool) preg_match('/rmt_storage_delete\(\(string\)\s*\$ph\[.storage_key.\]\)/', $body));
    $check("{$fn}() guards against an empty/null storage_key before deleting",
        (bool) preg_match('/!empty\(\$ph\[.storage_key.\]\)/', $body));
}

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL DELETED-CONTENT MEDIA CLEANUP TESTS PASS\n";
