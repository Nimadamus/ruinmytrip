<?php
/**
 * The blog and guides sitemaps (app/sitemap.php groups 'blog' and 'guides'), and the talk rule.
 *
 * What must hold:
 *   - every URL either sitemap emits renders 200 on a real local server, says index in its robots
 *     meta, and has a canonical equal to the sitemap <loc> byte for byte (scheme, host, slash).
 *   - nothing thin, premium, placeholder, roundup or duplicate is emitted, and lastmod is the row's
 *     own date, never today's.
 *   - both children are listed in the sitemap index and served as valid XML.
 *   - a talk post with no answer from anybody else and under the word floor is not submitted, and
 *     one that drew an answer is, so the open questions the site asks stay out until answered.
 *
 *   php tests/sitemap_articles_test.php   (copies database/dev.sqlite, writes only to the copy)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$src = $root . '/database/dev.sqlite';
if (!is_file($src) || filesize($src) < 1024) { echo "SKIP  no dev database\n"; exit(0); }

$port = 8191;
$base = 'http://127.0.0.1:' . $port;
$copy = sys_get_temp_dir() . '/rmt_sitemap_articles_' . getmypid() . '.sqlite';
copy($src, $copy);
foreach (['RMT_SQLITE' => $copy, 'RMT_LOCAL_URL' => $base, 'RMT_APP_URL' => $base] as $k => $v) { putenv("$k=$v"); $_ENV[$k] = $v; }
putenv('DATABASE_URL');
require $root . '/app/bootstrap.php';

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

/* ---------------- the rules, on the rows ---------------- */
$today = date('Y-m-d');
foreach (['blog' => 'blog_posts', 'guides' => 'guides'] as $group => $table) {
    $rows = rmt_sitemap_group($group);
    ok("$group: emits pages", count($rows) > 0, (string) count($rows));
    $byLoc = [];
    foreach (q_all("SELECT slug, title, body, " . ($table === 'guides' ? 'premium' : '0 premium')
                   . ", status, created_at, updated_at FROM $table") as $r) {
        $byLoc[url(($group === 'blog' ? 'blog/' : 'g/') . $r['slug'])] = $r;
    }
    $titles = [];
    foreach ($rows as $row) {
        $r = $byLoc[$row['loc']] ?? null;
        ok("$group: {$row['loc']} is a real row", $r !== null);
        if (!$r) continue;
        $w = rmt_sitemap_word_count((string) $r['body']);
        $bad = [];
        if ($r['status'] !== 'published') $bad[] = 'unpublished';
        if ($group === 'guides' && (int) $r['premium'] === 1) $bad[] = 'premium';
        if ($w < RMT_SITEMAP_ARTICLE_MIN_WORDS) $bad[] = "$w words";
        if (preg_match_all('#/d/([a-z0-9\-]+)#', (string) $r['body'], $m) && count(array_unique($m[1])) > RMT_SITEMAP_ROUNDUP_MAX_CITIES) $bad[] = 'roundup';
        $tk = mb_strtolower(trim((string) $r['title']));
        if (isset($titles[$tk])) $bad[] = 'duplicate title';
        $titles[$tk] = true;
        $want = rmt_sitemap_day(($r['updated_at'] ?? null) ?: $r['created_at']);
        if ($row['lastmod'] !== $want) $bad[] = "lastmod {$row['lastmod']} != $want";
        if (!$bad) continue;
        ok("$group: {$row['loc']} meets every rule", false, implode(', ', $bad));
    }
    $ex = rmt_sitemap_articles($group)['exclude'];
    ok("$group: every exclusion has a reason", !array_filter($ex, static fn($e) => $e['reason'] === ''));
}

/* Fixtures through the same function: thin, placeholder, roundup, duplicate, near copy. */
$uid = (int) q_one("SELECT id FROM users ORDER BY id LIMIT 1")['id'];
$long = static function (string $seed): string {
    $w = [];
    for ($i = 0; $i < 320; $i++) $w[] = $seed . 'word' . ($i * 7 % 101) . ' ' . (['harbour', 'ticket', 'evening', 'market', 'ferry'][$i % 5]);
    return '<p>' . implode(' ', $w) . '.</p>';
};
$ins = static function (string $slug, string $title, string $body, string $created) use ($uid): void {
    q_run("INSERT INTO blog_posts (user_id, slug, title, summary, body, category, status, created_at)
           VALUES (?,?,?,?,?,'tips','published',?)", [$uid, $slug, $title, 'summary', $body, $created]);
};
$ins('zz-fixture-thin', 'Fixture thin', '<p>Only a few words here.</p>', '2030-01-01 00:00:00');
$ins('zz-fixture-placeholder', 'Coming soon', $long('a'), '2030-01-02 00:00:00');
$ins('zz-fixture-roundup', 'Fixture roundup', $long('b') . '<a href="/d/a-x">a</a><a href="/d/b-x">b</a><a href="/d/c-x">c</a>', '2030-01-03 00:00:00');
$ins('zz-fixture-original', 'Fixture original', $long('c'), '2030-01-04 00:00:00');
$ins('zz-fixture-same-title', 'Fixture  ORIGINAL', $long('d'), '2030-01-05 00:00:00');
$ins('zz-fixture-near-copy', 'Fixture near copy', $long('c') . '<p>One extra closing line.</p>', '2030-01-06 00:00:00');
$res = rmt_sitemap_articles('blog');
$inc = array_column($res['include'], 'slug');
$why = array_column($res['exclude'], 'reason', 'slug');
ok('fixture: thin is excluded', str_starts_with($why['zz-fixture-thin'] ?? '', 'thin'), $why['zz-fixture-thin'] ?? 'included');
ok('fixture: placeholder is excluded', ($why['zz-fixture-placeholder'] ?? '') === 'placeholder text');
ok('fixture: roundup is excluded', str_starts_with($why['zz-fixture-roundup'] ?? '', 'roundup'));
ok('fixture: the original is included', in_array('zz-fixture-original', $inc, true));
ok('fixture: same title is excluded', str_starts_with($why['zz-fixture-same-title'] ?? '', 'duplicate title'));
ok('fixture: near copy is excluded', str_starts_with($why['zz-fixture-near-copy'] ?? '', 'near duplicate'));
q_run("DELETE FROM blog_posts WHERE slug LIKE 'zz-fixture-%'");

$pub = (string) file_get_contents($root . '/scripts/publish_editorial.php');
ok('the editorial publisher leaves an unchanged guide alone, so its lastmod holds', str_contains($pub, 'guide unchanged') && str_contains($pub, "(string) \$have['body'] === \$body"));

/* ---------------- talk ---------------- */
$q = 'RuinMyTrip asks: which days of Oktoberfest would you tell a first timer to avoid entirely? Everything written about this says weekday mornings and then stops.';
ok('talk: an unanswered short question is not submitted', !rmt_sitemap_talk_ok(['body' => $q, 'other_reply_count' => 0]));
ok('talk: an answer from the author alone does not count', !rmt_sitemap_talk_ok(['body' => $q, 'other_reply_count' => 0, 'reply_count' => 3]));
ok('talk: the same question once somebody answers is submitted', rmt_sitemap_talk_ok(['body' => $q, 'other_reply_count' => 1]));
ok('talk: a long write up stands on its own', rmt_sitemap_talk_ok(['body' => str_repeat('We took the night train and it was worth it. ', 20), 'other_reply_count' => 0]));
$talk = array_column(rmt_sitemap_group('talk'), 'loc');
foreach (q_all("SELECT p.id, p.body, (SELECT COUNT(*) FROM comments c WHERE c.target_type='post' AND c.target_id=p.id
                       AND c.status='published' AND c.user_id <> p.user_id) other_reply_count
                  FROM posts p WHERE p.status='published'") as $p) {
    if (rmt_sitemap_talk_ok($p)) continue;
    ok("talk: post {$p['id']} below the rule is not submitted", !in_array(url('post/' . $p['id']), $talk, true));
}

/* ---------------- rendered: 200, index, canonical equal ---------------- */
rmt_sitemap_generate();
$emit = [];
foreach (['blog', 'guides'] as $g) foreach (rmt_sitemap_group($g) as $r) $emit[] = $r['loc'];

$php = PHP_BINARY;
$ini = is_file($root . '/php.local.ini') ? ['-c', $root . '/php.local.ini'] : [];
$cmd = array_merge([$php], $ini, ['-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php']);
$log = $root . '/tests/.render_server.log';
$env = array_merge(getenv(), ['RMT_SQLITE' => $copy, 'RMT_LOCAL_URL' => $base, 'RMT_APP_URL' => $base]);
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
$get = static function (string $u): array {
    $ctx = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true, 'follow_location' => 0]]);
    $body = @file_get_contents($u, false, $ctx);
    $code = 0; $xrobots = '';
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $code = (int) $m[1];
        if (stripos($h, 'X-Robots-Tag:') === 0) $xrobots .= $h;
    }
    return [$code, (string) $body, $xrobots];
};
$bad = [];
foreach ($emit as $loc) {
    [$code, $html, $xr] = $get($loc);
    $canon = preg_match('#<link rel="canonical" href="([^"]*)"#', $html, $m) ? html_entity_decode($m[1]) : null;
    $robots = preg_match('#<meta name="robots" content="([^"]*)"#', $html, $m) ? $m[1] : '';
    $why = [];
    if ($code !== 200) $why[] = "status $code";
    if ($canon !== $loc) $why[] = 'canonical ' . var_export($canon, true);
    if (stripos($robots, 'noindex') !== false || stripos($xr, 'noindex') !== false) $why[] = "robots $robots $xr";
    if ($why) $bad[] = $loc . ': ' . implode(', ', $why);
}
ok('every emitted URL renders 200, indexable, canonical equal (' . count($emit) . ' checked)', !$bad, implode(' | ', array_slice($bad, 0, 5)));

[$code, $idx] = $get($base . '/sitemap.xml');
ok('index lists the blog sitemap', str_contains($idx, '<loc>' . $base . '/sitemap-blog.xml</loc>'));
ok('index lists the guides sitemap', str_contains($idx, '<loc>' . $base . '/sitemap-guides.xml</loc>'));
foreach (['blog', 'guides'] as $g) {
    [$code, $x] = $get($base . '/sitemap-' . $g . '.xml');
    $prev = libxml_use_internal_errors(true);
    $sx = simplexml_load_string($x);
    libxml_use_internal_errors($prev);
    ok("sitemap-$g.xml is 200 and valid XML", $code === 200 && $sx !== false);
    ok("sitemap-$g.xml holds exactly the generated rows", $sx !== false && count($sx->url) === count(rmt_sitemap_group($g)));
    ok("sitemap-$g.xml never stamps today on every row", !str_contains($x, '<lastmod>' . $today . '</lastmod>') || substr_count($x, '<lastmod>' . $today) < count($sx->url));
}

proc_terminate($proc);
proc_close($proc);
@unlink($copy);
echo $fails ? "\nsitemap_articles_test: $fails FAILED\n" : "\nsitemap_articles_test: all passed\n";
exit($fails ? 1 : 0);
