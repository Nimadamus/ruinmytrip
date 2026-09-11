<?php
/**
 * A place URL: readable, unique, and safe in any script.
 *
 * 43 places on this site have a name written only in Japanese or Thai, and OpenStreetMap records
 * no Latin name for a single one of them. They used to live at /p/item-tokyo-31, which is stable
 * and honest and is not a link anybody sends to a friend. The URL now carries the real name.
 *
 * What this pins down is the whole of that decision, because it is the kind of change that breaks
 * quietly: the slug, the collision rule, what gets escaped on the wire, and above all what the
 * route still refuses, since widening a path pattern is how a router starts accepting "..".
 *
 *   php tests/place_url_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/places.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$pdo = db();
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, name TEXT)");

// --- the slug -------------------------------------------------------------------------------------
ok(rmt_place_slug_unicode('あみやき亭') === 'あみやき亭', 'a Japanese name is its own slug');
ok(rmt_place_slug_unicode('วัดโพธิ์') === 'วัดโพธิ์', 'and a Thai one, marks and all');
ok(rmt_place_slug_unicode('東京  国立  博物館') === '東京-国立-博物館', 'spaces become hyphens, runs become one');
ok(rmt_place_slug_unicode('  ...  ') === 'item', 'punctuation alone is still nothing to work with');
ok(rmt_place_slug_unicode('') === 'item', 'and so is nothing at all');
/* A zero width joiner is invisible. Turning it into a hyphen would put a word boundary in a URL
   where the reader sees none, and two names that look identical would get different slugs. */
ok(rmt_place_slug_unicode("東京\u{200D}都") === '東京都', 'an invisible character is dropped, not hyphenated');
$long = rmt_place_slug_unicode(str_repeat('東', 40));
ok(strlen($long) <= 60, 'the cap is in bytes, because that is what a URL and an index hold');
ok(mb_check_encoding($long, 'UTF-8'), 'and it never cuts a character in half');
ok(!str_ends_with($long, '-'), 'nor leaves a trailing hyphen behind');

// --- ASCII names are untouched --------------------------------------------------------------------
ok(rmt_place_unique_slug('Museu Geologico', 'Lisbon') === 'museu-geologico-lisbon',
   'a Latin name still slugifies exactly as it did');
ok(rmt_place_unique_slug('M+', 'Hong Kong') === 'm-plus-hong-kong',
   'and a name that is mostly a symbol still gets spoken first');
/* The alias route comes before the unicode one: a real English name somebody recorded beats the
   original script, because more readers can type it. */
ok(rmt_place_unique_slug('アーティゾン美術館', 'Tokyo', 0, ['Artizon Museum']) === 'artizon-museum-tokyo',
   'a real English name recorded by the provider wins');
ok(rmt_place_unique_slug('あみやき亭', 'Tokyo') === 'あみやき亭-tokyo',
   'and with no other name the URL carries the one it has');

// --- collisions ---------------------------------------------------------------------------------
$pdo->exec("INSERT INTO places (slug, name) VALUES ('あみやき亭-tokyo', 'あみやき亭')");
ok(rmt_place_unique_slug('あみやき亭', 'Tokyo') === 'あみやき亭-tokyo-2',
   'a second place of the same name in the same city is numbered, not collided');
$id = (int) $pdo->query("SELECT id FROM places WHERE slug = 'あみやき亭-tokyo'")->fetchColumn();
ok(rmt_place_unique_slug('あみやき亭', 'Tokyo', $id) === 'あみやき亭-tokyo',
   'and a place keeps its own slug when it is the one being renamed');

// --- what goes on the wire ------------------------------------------------------------------------
ok(url('p/あみやき亭-tokyo') === 'https://ruinmytrip.com/p/%E3%81%82%E3%81%BF%E3%82%84%E3%81%8D%E4%BA%AD-tokyo',
   'the wire sees percent encoded UTF-8, which is what a Location header and a sitemap require');
ok(url('search?q=a&b=c') === 'https://ruinmytrip.com/search?q=a&b=c',
   'and an ASCII path with a query string is left exactly alone');
ok(rmt_url_escape('/d/lisbon-portugal/places') === '/d/lisbon-portugal/places', 'slashes survive');

// --- what the route still refuses -----------------------------------------------------------------
/* Widening a path pattern is how a router starts accepting "..", so the pattern itself is read out
   of the route table and tried against the things that must never match. */
$src = (string) file_get_contents(BASE_PATH . '/public/index.php');
preg_match("#'(\#\^/p/\(\?<slug>[^']+)'#", $src, $m);
$route = $m[1] ?? '';
ok($route !== '', 'the place route was found in the table');
ok((bool) preg_match($route, '/p/あみやき亭-tokyo'), 'a Japanese slug is routed');
ok((bool) preg_match($route, '/p/museu-geologico-lisbon'), 'and so is an ordinary one');
foreach (['/p/../../etc/passwd' => 'a traversal',
          '/p/a/b'              => 'a second path segment',
          '/p/a.php'            => 'a file extension',
          '/p/'                 => 'an empty slug',
          "/p/a\nb"             => 'a newline',
          '/p/a b'              => 'a space'] as $bad => $why) {
    ok(!preg_match($route, $bad), "refused: $why");
}

echo "place_url_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
