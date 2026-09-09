<?php
/**
 * Regression test for views/_share.php clobbering the page it is included in.
 *
 * The partial assigned two single-letter locals, $t and $u. It is included in the middle of pages
 * that already hold their own: on a trip story $t IS the trip, so the include replaced it with a
 * URL-encoded string and the next line that read $t['id'] threw "Cannot access offset of type
 * string on string". Every trip page on the site served its header and then died, losing the
 * comments and everything below them, while still returning 200 so nothing looked wrong from
 * outside.
 *
 * An included template shares the caller's scope. These tests hold that line.
 *
 *   php tests/share_partial_scope_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$src = (string) file_get_contents(dirname(__DIR__) . '/views/_share.php');

// No bare single or double letter assignment survives in a shared-scope template.
preg_match_all('/^\s*\$([A-Za-z_][A-Za-z0-9_]*)\s*(?:\?\?)?=/m', $src, $m);
$assigned = array_values(array_unique($m[1]));
$short = array_values(array_filter($assigned, static fn(string $v) => strlen($v) <= 2));
ok('no one or two letter locals are assigned', $short === [], implode(',', $short));

$allowed = ['shareUrl', 'shareText'];
$leaked = array_values(array_filter($assigned, static fn(string $v) =>
    !in_array($v, $allowed, true) && !str_starts_with($v, 'rmt_')));
ok('everything else it defines is namespaced', $leaked === [], implode(',', $leaked));
ok('it still defines the two it documents',
   in_array('shareUrl', $assigned, true) && in_array('shareText', $assigned, true));

// The links must still be built from encoded values, not from raw text.
ok('the share links use the encoded values',
   substr_count($src, '$rmt_share_t') >= 3 && substr_count($src, '$rmt_share_u') >= 3);
ok('nothing still prints a bare $t or $u', !preg_match('/<\?=\s*\$[tu]\s*\?>/', $src));

// The page that this actually broke: the trip story reads $t after including the partial.
$trip = (string) file_get_contents(dirname(__DIR__) . '/views/trip_show.php');
$at = strpos($trip, "_share.php");
ok('trip_show still reads $t after the include', $at !== false && str_contains(substr($trip, $at), "\$t['id']"));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
