<?php
/**
 * The controlled places sitemap (app/sitemap.php group 'places', database/sitemap_places.txt).
 *
 * What must hold:
 *   - exactly the listed slugs that rmt_indexable() still accepts, nothing else.
 *   - a listed place that the indexing rule says is thin is never submitted.
 *   - the list parser ignores comments, blanks and anything that is not a slug.
 *   - the reviewed list stays between 60 and 250 places (82 after the 2026-10-30 content check).
 *
 *   php tests/sitemap_places_test.php   (reads database/dev.sqlite, writes nothing)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/database/dev.sqlite')) { echo "SKIP  no dev database\n"; exit(0); }
putenv('DATABASE_URL');
require $root . '/app/bootstrap.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$tmp = tempnam(sys_get_temp_dir(), 'smp');
file_put_contents($tmp, "# a comment\n\nle-rapido-paris\n  vasa-museum-stockholm  \nNot A Slug\n../etc/passwd\n");
$set = rmt_sitemap_place_allowlist($tmp);
ok('comments, blanks and junk are ignored', array_keys($set) === ['le-rapido-paris', 'vasa-museum-stockholm'], json_encode(array_keys($set)));
ok('a missing file is an empty list', rmt_sitemap_place_allowlist($tmp . '.nope') === []);
unlink($tmp);

$list = rmt_sitemap_place_allowlist();
ok('the reviewed list holds 60 to 250 places', count($list) >= 60 && count($list) <= 250, (string) count($list));

$rows = rmt_sitemap_group('places');
$expect = [];
foreach (rmt_index_places() as $p) if (isset($list[(string) $p['slug']]) && $p['verdict']['ok']) $expect[] = url('p/' . $p['slug']);
$got = array_column($rows, 'loc');
sort($expect); sort($got);
ok('exactly the listed places the indexing rule accepts', $got === $expect, count($got) . ' vs ' . count($expect));
$thin = 0;
foreach (rmt_index_places() as $p) if (isset($list[(string) $p['slug']]) && !$p['verdict']['ok']) $thin++;
foreach ($rows as $r) {
    $slug = substr((string) $r['loc'], strrpos((string) $r['loc'], '/') + 1);
    ok("$slug is on the list", isset($list[$slug]));
}
ok('a listed place the rule calls thin is left out', count($got) === count($expect), "thin on list: $thin");

echo $fails ? "\nsitemap_places_test: $fails FAILED\n" : "\nsitemap_places_test: all passed\n";
exit($fails ? 1 : 0);
