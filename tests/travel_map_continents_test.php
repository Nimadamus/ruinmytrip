<?php
// Every pickable country on the map carries a continent, and the FAQ list stays usable by page and schema.
define('BASE_PATH', dirname(__DIR__));
$d = json_decode(file_get_contents(BASE_PATH . '/public/assets/data/world-map.json'), true);
$fail = 0; $seen = [];
foreach ($d['countries'] as $c) {
    if ($c['id'] === null) continue;
    if (!in_array($c['ct'] ?? '', ['AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA'], true)) { echo "FAIL no continent: {$c['name']}\n"; $fail++; }
    $seen[$c['name']] = $c['ct'] ?? '';
}
foreach (['France' => 'EU', 'Japan' => 'AS', 'Brazil' => 'SA', 'Kenya' => 'AF', 'Mexico' => 'NA', 'Australia' => 'OC', 'Kosovo' => 'EU', 'Taiwan' => 'AS'] as $n => $ct) {
    if (($seen[$n] ?? '') !== $ct) { echo "FAIL $n\n"; $fail++; }
}
$src = file_get_contents(BASE_PATH . '/app/travel_map.php');
if (!str_contains($src, 'function rmt_map_faq')) { echo "FAIL faq\n"; $fail++; }
echo $fail ? "FAILED\n" : "ok\n";
exit($fail ? 1 : 0);
