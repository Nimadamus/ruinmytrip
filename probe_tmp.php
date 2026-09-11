<?php
require __DIR__ . '/app/bootstrap.php';
$refs = [];
foreach (['/tmp/serials_tokyo.json','/tmp/serials_bkk.json'] as $f) {
    $d = json_decode((string) file_get_contents(sys_get_temp_dir() . '/' . basename($f)), true);
    foreach ($d['rows'] ?? [] as $r) $refs[] = $r['source_ref'];
}
echo count($refs), " refs\n";
$res = rmt_osm_fetch(rmt_osm_query_refs($refs), 45);
if ($res['error']) { echo "err: {$res['error']}\n"; exit(1); }
$tagCount = [];
foreach ($res['elements'] as $el) {
    foreach (($el['tags'] ?? []) as $k => $v) {
        if (str_starts_with($k, 'name') || str_starts_with($k, 'int_name')
            || str_starts_with($k, 'alt_name') || str_starts_with($k, 'official_name')) {
            $tagCount[$k] = ($tagCount[$k] ?? 0) + 1;
        }
    }
}
arsort($tagCount);
print_r($tagCount);
