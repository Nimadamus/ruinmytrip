<?php
/**
 * The 2026-10-30 approved SEO pass: page descriptions and two destination summary facts.
 *
 * What must hold:
 *   - /terms, /privacy, /guidelines, /affiliate, /safety and the buddies type pages each carry
 *     their own meta description, 120 to 170 characters, no dashes, none equal to the site default
 *     or to each other; /buddies keeps the hub text it had.
 *   - migration 115 moves the two old summaries to the new ones, only when the old text is still
 *     there, and agrees with database/editorial/cities_20261030.json, which the boot publish uses.
 *
 *   php tests/seo_meta_20261030_test.php   (copies database/dev.sqlite, writes only to the copy)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

/* ---------------- summaries: migration and editorial JSON agree ---------------- */
$sql = (string) file_get_contents($root . '/database/migrations/115_city_summary_facts.sql');
$old046 = (string) file_get_contents($root . '/database/migrations/046_destinations_batch20.sql');
$ed = json_decode((string) file_get_contents($root . '/database/editorial/cities_20261030.json'), true);
$edSum = array_column($ed['destinations'], 'summary', 'slug');
preg_match_all("/UPDATE destinations SET summary = '((?:[^']|'')*)'\s+WHERE slug = '([a-z\-]+)' AND summary = '((?:[^']|'')*)';/", $sql, $m, PREG_SET_ORDER);
ok('migration 115 has two guarded updates', count($m) === 2, (string) count($m));
foreach ($m as [$all, $new, $slug, $old]) {
    $new = str_replace("''", "'", $new); $old = str_replace("''", "'", $old);
    ok("$slug: guard text is the summary migration 046 wrote", str_contains($old046, $old));
    ok("$slug: new text equals the editorial JSON", ($edSum[$slug] ?? null) === $new);
    ok("$slug: new text has no dashes", !preg_match('/[\x{2012}-\x{2015}]| - /u', $new));
}
ok('San Francisco cites the 24.4 million forecast', str_contains($edSum['san-francisco-usa'] ?? '', '24.4 million visitors forecast for 2026'));
ok('the San Francisco source is recorded', str_contains($ed['_note'], 'sftravel.com/media/press-release/san-francisco-travel-forecasts-third-straight-year-growth-2027') && str_contains($sql, '2026-08-26'));
ok('Buzios quotes no fare', !preg_match('/dollar|US\$|R\$|\d/u', $edSum['buzios-brazil'] ?? '1'));

$db = new PDO('sqlite::memory:');
$db->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, summary TEXT)");
foreach ($m as [$all, $new, $slug, $old]) {
    $db->prepare("INSERT INTO destinations (slug, summary) VALUES (?, ?)")->execute([$slug, str_replace("''", "'", $old)]);
}
$db->prepare("INSERT INTO destinations (slug, summary) VALUES ('buzios-brazil', 'A summary somebody edited by hand.')")->execute();
$db->exec($sql);
$db->exec($sql);   // a rerun changes nothing
$got = $db->query("SELECT slug, summary FROM destinations ORDER BY id")->fetchAll(PDO::FETCH_NUM);
ok('applied: San Francisco updated', $got[0][1] === $edSum['san-francisco-usa']);
ok('applied: Buzios updated', $got[1][1] === $edSum['buzios-brazil']);
ok('applied: an edited summary is never overwritten', $got[2][1] === 'A summary somebody edited by hand.');

/* ---------------- descriptions, rendered ---------------- */
$src = $root . '/database/dev.sqlite';
if (!is_file($src) || filesize($src) < 1024) {
    echo "SKIP  rendered checks: no dev database\n";
    exit($fails ? 1 : 0);
}
$port = 8191;
$base = 'http://127.0.0.1:' . $port;
$copy = sys_get_temp_dir() . '/rmt_seo_meta_' . getmypid() . '.sqlite';
copy($src, $copy);
$ini = is_file($root . '/php.local.ini') ? ['-c', $root . '/php.local.ini'] : [];
$cmd = array_merge([PHP_BINARY], $ini, ['-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php']);
$log = $root . '/tests/.render_server.log';
$env = array_merge(getenv(), ['RMT_SQLITE' => $copy, 'RMT_LOCAL_URL' => $base, 'RMT_APP_URL' => $base]);
unset($env['DATABASE_URL']);
$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, $env);
$up = false;
for ($i = 0; is_resource($proc) && $i < 40; $i++) {
    if (@file_get_contents($base . '/healthz', false, stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]])) !== false) { $up = true; break; }
    usleep(250000);
}
if (!$up) {
    if (is_resource($proc)) proc_terminate($proc);
    @unlink($copy);
    echo "SKIP  server did not come up on $port\n";
    exit($fails ? 1 : 0);
}
$desc = static function (string $path) use ($base): ?string {
    $html = @file_get_contents($base . $path, false, stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]));
    return preg_match('#<meta name="description" content="([^"]*)"#', (string) $html, $mm) ? html_entity_decode($mm[1], ENT_QUOTES) : null;
};
$default = 'RuinMyTrip: a trustworthy travel community for real trips, honest reviews, and safe meetups.';
$hub = 'Going somewhere? Find people heading the same way. Meet travelers on your dates, locals open to meeting, and people on the same cruise. Join free at 16. Posting a trip is 18+, and nobody can message you until you say yes.';
ok('/buddies keeps its hub description', $desc('/buddies') === $hub);
$seen = [];
foreach (['/terms', '/privacy', '/guidelines', '/affiliate', '/safety', '/buddies/cruise', '/buddies/backpacking', '/buddies/road-trip'] as $p) {
    $d = $desc($p);
    $len = mb_strlen((string) $d);
    ok("$p has its own description", $d !== null && $d !== $default && $d !== $hub && !isset($seen[$d]), (string) $d);
    ok("$p description is 120 to 170 characters", $len >= 120 && $len <= 170, (string) $len);
    ok("$p description has no dashes", $d !== null && !preg_match('/[-\x{2012}-\x{2015}]/u', $d), (string) $d);
    $seen[(string) $d] = true;
}

proc_terminate($proc);
proc_close($proc);
@unlink($copy);
echo $fails ? "\nseo_meta_20261030_test: $fails FAILED\n" : "\nseo_meta_20261030_test: all passed\n";
exit($fails ? 1 : 0);
