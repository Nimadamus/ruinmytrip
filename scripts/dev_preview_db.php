<?php
declare(strict_types=1);

/**
 * Build a local SQLite database that MIRRORS PRODUCTION: the 8 real destination rows and nothing
 * else. No demo members, no invented reviews, no fake trips.
 *
 * The normal local bootstrap calls rmt_seed_data(), which fabricates travelers so the UI has
 * something to render. That is fine for poking at layout, but it is useless for previewing what
 * the live site will actually look like, and it is actively misleading when the whole point of a
 * change is how the site behaves with ZERO community content. Use this instead.
 *
 *   php scripts/dev_preview_db.php            rebuild database/preview.sqlite
 *   RMT_SQLITE=... php scripts/dev_preview_db.php   write somewhere else
 */

define('RMT_NO_AUTOSEED', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$path = getenv('RMT_SQLITE') ?: BASE_PATH . '/database/preview.sqlite';
if (($GLOBALS['config']['app_env'] ?? '') === 'production' || getenv('DATABASE_URL')) {
    fwrite(STDERR, "refusing to run against production\n");
    exit(1);
}
if (file_exists($path)) unlink($path);

$pdo = new PDO('sqlite:' . $path);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

require_once BASE_PATH . '/database/seed.php';
require_once BASE_PATH . '/app/migrator.php';
rmt_apply_schema($pdo, 'sqlite');
rmt_migrate($pdo, 'sqlite', static fn(string $m) => print($m . PHP_EOL));

// The 8 live destinations, matching production exactly (slug, name, country, region, lat, lng,
// category). Summary and hero image are placeholders here because publish_editorial.php
// overwrites both with researched copy and a credited, licensed photograph.
$dests = [
    ['kyoto-japan','Kyoto','Japan','Kansai',35.0116,135.7681,'culture'],
    ['lisbon-portugal','Lisbon','Portugal','Lisboa',38.7223,-9.1393,'city'],
    ['queenstown-nz','Queenstown','New Zealand','Otago',-45.0312,168.6626,'adventure'],
    ['marrakech-morocco','Marrakech','Morocco','Marrakesh-Safi',31.6295,-7.9811,'culture'],
    ['banff-canada','Banff','Canada','Alberta',51.1784,-115.5708,'nature'],
    ['oaxaca-mexico','Oaxaca','Mexico','Oaxaca',17.0732,-96.7266,'food'],
    ['reykjavik-iceland','Reykjavik','Iceland','Capital Region',64.1466,-21.9426,'nature'],
    ['hoi-an-vietnam','Hoi An','Vietnam','Quang Nam',15.8801,108.3380,'culture'],
];
$ins = $pdo->prepare('INSERT INTO destinations (slug,name,country,region,lat,lng,summary,hero_url,category)
                      VALUES (?,?,?,?,?,?,?,?,?)');
foreach ($dests as $d) {
    $ins->execute([$d[0], $d[1], $d[2], $d[3], $d[4], $d[5], '', '', $d[6]]);
}

echo 'built ' . $path . ' with ' . count($dests) . " destinations, 0 users, 0 reviews\n";
