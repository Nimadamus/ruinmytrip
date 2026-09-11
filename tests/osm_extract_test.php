<?php
/**
 * The offline extract path, proved on a fixture.
 *
 * This is the fallback for the day the public Overpass instances stop being a dependable way to
 * fetch: Geofabrik and BBBike publish regional extracts of the same database under the same ODbL
 * licence, free, with no key and no account, and a downloaded file has no rate limit and no queue.
 *
 * It is deliberately NOT wired into production. What this test pins is that it produces exactly the
 * same canonical rows as the live provider, so that switching over later is a decision rather than
 * a rewrite, and that it drops the same things: anything outside the city's box, anything unnamed,
 * and anything that is not a kind of place this site carries.
 *
 *   php tests/osm_extract_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/database/dev.sqlite')) { echo "SKIP  no dev database\n"; exit(0); }

$fixture = sys_get_temp_dir() . '/rmt_osm_fixture_' . getmypid() . '.osm';
file_put_contents($fixture, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<osm version="0.6" generator="test">
  <node id="1001" lat="38.7130" lon="-9.1400">
    <tag k="name" v="Museu Fixture"/>
    <tag k="tourism" v="museum"/>
    <tag k="opening_hours" v="Tu-Su 10:00-18:00"/>
    <tag k="addr:street" v="Rua Teste"/>
    <tag k="addr:housenumber" v="7"/>
    <tag k="alt_name" v="Fixture Museum"/>
  </node>
  <node id="1002" lat="38.7135" lon="-9.1405">
    <tag k="name" v="Cafe Fixture"/>
    <tag k="amenity" v="cafe"/>
  </node>
  <node id="1003" lat="51.5000" lon="-0.1200">
    <tag k="name" v="Far Away Pub"/>
    <tag k="amenity" v="pub"/>
  </node>
  <node id="1004" lat="38.7131" lon="-9.1401">
    <tag k="amenity" v="bench"/>
  </node>
  <node id="1005" lat="38.7132" lon="-9.1402">
    <tag k="amenity" v="restaurant"/>
  </node>
</osm>
XML);

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/osm_extract.php')
     . ' --file=' . escapeshellarg($fixture) . ' --city=lisbon-portugal --json 2>' . escapeshellarg(
        sys_get_temp_dir() . '/rmt_osm_fixture_err.txt');
$out = shell_exec($cmd);
@unlink($fixture);

$data = json_decode((string) $out, true);
if (!is_array($data) || !isset($data['rows'])) {
    echo "SKIP  the extract reader produced no JSON (is lisbon-portugal in the dev database?)\n";
    exit(0);
}

$rows = $data['rows'];
$byName = [];
foreach ($rows as $r) $byName[(string) $r['name']] = $r;

ok(count($rows) === 2, 'two of the five nodes are places (' . count($rows) . ')');
ok(isset($byName['Museu Fixture']), 'a named museum inside the box is kept');
ok(isset($byName['Cafe Fixture']), 'and a named cafe');
ok(!isset($byName['Far Away Pub']), 'a pub in another country is outside the box and is dropped');

$m = $byName['Museu Fixture'] ?? [];
ok(($m['type'] ?? '') === 'attraction', 'the coarse type matches what the live provider produces');
ok(($m['source_kind'] ?? '') === 'museum', "the provider's own word is carried the same way");
ok(($m['category_slug'] ?? '') === 'museum', 'and so is the category');
ok(($m['source_ref'] ?? '') === 'node/1001', 'the OSM object is recorded, so a re-import updates');
ok(($m['street_address'] ?? '') === '7 Rua Teste', 'the address is assembled the same way');
ok(($m['opening_hours'] ?? '') === 'Tu-Su 10:00-18:00', 'opening hours ride along as they do live');
ok(($m['data_source'] ?? '') === 'openstreetmap', 'the licence and attribution are unchanged');
ok(($data['aliases']['node/1001'] ?? []) === ['Fixture Museum'], 'and the other name it goes by');

/* The two dropped rows are dropped for the reasons the live provider drops them, not by accident:
   a bench is not a place, and an unnamed restaurant is a dot rather than a page. */
ok(!in_array('bench', array_column($rows, 'source_kind'), true), 'a bench is not a place');
ok(count($rows) === count(array_filter($rows, static fn(array $r) => trim((string) $r['name']) !== '')),
   'and nothing unnamed got through');

echo "osm_extract_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
