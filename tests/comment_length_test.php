<?php
/**
 * Regression test: an over-limit comment must be rejected with a clear error, not silently
 * truncated. comment_action used to insert mb_substr($body, 0, 2000) unconditionally -- anyone
 * who wrote a comment longer than 2000 characters had the tail silently discarded with no
 * indication anything was cut, unlike every other body-length limit in the app (trip/guide/
 * review validators), which reject over-limit input with a visible error instead.
 *
 *   php tests/comment_length_test.php   -> PASS/FAIL per case, exits non-zero on failure.
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

$body = extract_fn($src, 'comment_action');

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) {
    printf("  [%s] %s\n", $ok ? 'PASS' : 'FAIL', $name);
    if (!$ok) $fail++;
};

$check('comment_action() found in controllers.php', $body !== '');
$check('rejects a body over 2000 chars before inserting',
    (bool) preg_match('/mb_strlen\(\$body\)\s*>\s*2000/', $body));
$check('the INSERT no longer silently truncates via mb_substr',
    strpos($body, 'mb_substr($body') === false);
$check('the INSERT binds the full, untruncated $body',
    (bool) preg_match('/INSERT INTO comments.*VALUES.*\$body/s', $body));

// The comment textarea lives in the shared _engagement.php partial (trip/review/guide pages all
// include it) rather than being duplicated per content type.
$viewSrc = file_get_contents(dirname(__DIR__) . '/views/_engagement.php');
$check('the comment textarea has a matching client-side maxlength="2000"',
    (bool) preg_match('/<textarea name="body"[^>]*maxlength="2000"/', $viewSrc));

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed\n"; exit(1); }
echo "ALL COMMENT LENGTH TESTS PASS\n";
