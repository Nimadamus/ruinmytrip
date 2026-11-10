<?php
/**
 * The blog index canonical (app/controllers.php rmt_blog_index_canonical).
 *
 * What must hold:
 *   - page 1 names /blog, as it always has.
 *   - page 2 and later of the unfiltered index name themselves, never page 1.
 *   - a category view names /blog on every page (unchanged behaviour).
 *   - an unknown category is treated as unfiltered, the same way blog_index() treats it.
 *
 *   php tests/blog_pagination_canonical_test.php   (reads database/dev.sqlite, writes nothing)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/database/dev.sqlite')) { echo "SKIP  no dev database\n"; exit(0); }
putenv('DATABASE_URL');
require $root . '/app/bootstrap.php';
require_once $root . '/app/controllers.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$base = url('blog');
ok('page 1 names /blog', rmt_blog_index_canonical(1, '') === $base, rmt_blog_index_canonical(1, ''));
ok('page 2 names itself', rmt_blog_index_canonical(2, '') === $base . '?page=2', rmt_blog_index_canonical(2, ''));
ok('page 3 names itself', rmt_blog_index_canonical(3, '') === $base . '?page=3', rmt_blog_index_canonical(3, ''));
ok('category page 1 names /blog', rmt_blog_index_canonical(1, 'tips') === $base);
ok('category page 2 names /blog', rmt_blog_index_canonical(2, 'tips') === $base);
ok('unknown category counts as unfiltered', rmt_blog_index_canonical(2, 'nonsense') === $base . '?page=2');
ok('no query string other than page', !str_contains(rmt_blog_index_canonical(2, ''), '&'));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
