<?php
/**
 * Titles and descriptions as a searcher sees them: short enough to survive, never cut mid-word.
 *
 *   php tests/meta_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
                      'db_driver' => 'sqlite', 'sqlite_path' => ':memory:'];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/seo.php';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-56s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

echo "-- titles --\n";
check('a short title keeps the brand', rmt_meta_title('Lisbon in August'), 'Lisbon in August | RuinMyTrip');
check('the brand goes on while the whole thing still fits',
      str_ends_with(rmt_meta_title(str_repeat('ab ', 14)), '| RuinMyTrip'), true);
check('and comes off as soon as it would not',
      str_ends_with(rmt_meta_title(str_repeat('abcd ', 12)), '| RuinMyTrip'), false);
$long = 'Queenstown Is Worth Seeing And Expensive To Do: NZ$199 Jet Boats, NZ$320 Bungy, NZ$100 Levy';
$t = rmt_meta_title($long);
check('a long one drops the brand rather than the subject', str_contains($t, 'RuinMyTrip'), false);
check('and stays inside the budget', mb_strlen($t) <= 61, true);
check('never mid-word', (bool) preg_match('/(\s|…)$|[a-z]…$/u', $t), true);
check('no dangling punctuation before the ellipsis', str_contains($t, ',…'), false);
check('empty falls back to the brand', rmt_meta_title(''), 'RuinMyTrip');
check('markup is not a title', rmt_meta_title('<b>Rome</b>'), 'Rome | RuinMyTrip');

echo "\n-- descriptions --\n";
check('short text is left alone', rmt_meta_description('Two sentences. Both short.'), 'Two sentences. Both short.');
$body = 'Reykjavik is a capital of modest size doing a very large job. It is the arrival point, the transport hub and the accommodation base for most trips to Iceland, which is a lot to ask of one small city.';
$d = rmt_meta_description($body);
check('ends on a sentence', str_ends_with($d, '.'), true);
check('and is the first one here', $d, 'Reykjavik is a capital of modest size doing a very large job.');
check('within budget', mb_strlen($d) <= 155, true);
$noStop = str_repeat('a long clause with no full stop anywhere in it ', 6);
$d2 = rmt_meta_description($noStop);
check('no sentence to find means a word boundary', str_ends_with($d2, '…'), true);
check('still within budget', mb_strlen($d2) <= 156, true);
check('empty stays empty', rmt_meta_description(''), '');
$early = 'Yes. ' . str_repeat('And then a much longer second sentence that runs on and on ', 4);
check('a two-word first sentence does not become the description',
      str_ends_with(rmt_meta_description($early), '…'), true);

echo "\n-- place titles --\n";
$fits = static fn(array $p): bool => mb_strlen(rmt_place_page_title($p)) <= 60;
$anne = ['name' => 'Anne Frank House', 'dest_name' => 'Amsterdam', 'type' => 'attraction'];
check('the city and the year fit here', str_contains(rmt_place_page_title($anne), 'Amsterdam ' . date('Y')), true);
check('and it says what the page answers', str_contains(rmt_place_page_title($anne), 'tickets & prices'), true);
check('inside the budget', $fits($anne), true);

$long = ['name' => 'Book of Kells Experience at Trinity College', 'dest_name' => 'Dublin', 'type' => 'attraction'];
check('a long name loses the city, not the question',
      str_contains(rmt_place_page_title($long), 'tickets & prices'), true);
check('still inside the budget', $fits($long), true);
check('and the name is trimmed rather than dropped',
      str_starts_with(rmt_place_page_title($long), 'Book of Kells'), true);

check('a hotel is asked a hotel question',
      str_contains(rmt_place_page_title(['name' => 'Hotel Danieli', 'dest_name' => 'Venice', 'type' => 'hotel']), 'prices & fees'), true);
check('a restaurant too',
      str_contains(rmt_place_page_title(['name' => 'Chez Janou', 'dest_name' => 'Paris', 'type' => 'restaurant']), 'prices & hours'), true);
check('no city is not a stray comma',
      str_contains(rmt_place_page_title(['name' => 'Somewhere', 'dest_name' => '', 'type' => 'attraction']), ', '), false);

echo $fail ? "\nFAILED: $fail\n" : "\nOK\n";
exit($fail ? 1 : 0);
