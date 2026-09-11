<?php
/**
 * Reading OpenStreetMap opening hours, and refusing to guess.
 *
 * A wrong opening time is the worst kind of wrong fact this site can publish: it sends somebody
 * across a city to a locked door, and the page that did it looked authoritative. So the parser
 * takes the common unambiguous forms and refuses everything else WHOLE, rather than keeping the
 * part it understood and quietly dropping "except in August".
 *
 * Half the assertions here are refusals. That is the point of it.
 *
 *   php tests/osm_hours_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/osm_hours.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

/** A compact view of a parse: "day opens-closes" per row, or "day closed". */
function shape(?array $rows): ?string {
    if ($rows === null) return null;
    $out = [];
    foreach ($rows as $r) {
        $out[] = $r['closed'] ? $r['day_of_week'] . ' closed'
                              : $r['day_of_week'] . ' ' . $r['opens'] . '-' . $r['closes'];
    }
    return implode('|', $out);
}

// --- the forms we take ----------------------------------------------------------------------------
ok(shape(rmt_osm_hours_parse('Mo-Fr 09:00-17:00'))
   === '0 09:00-17:00|1 09:00-17:00|2 09:00-17:00|3 09:00-17:00|4 09:00-17:00',
   'a weekday range opens five days');
ok(shape(rmt_osm_hours_parse('Sa 10:00-14:00')) === '5 10:00-14:00', 'one day is one day');
ok(shape(rmt_osm_hours_parse('Mo,We,Fr 10:00-14:00')) === '0 10:00-14:00|2 10:00-14:00|4 10:00-14:00',
   'a list of days is a list of days');
ok(shape(rmt_osm_hours_parse('Tu 10:00-14:00,16:00-20:00')) === '1 10:00-14:00|1 16:00-20:00',
   'a lunch break is two spans on one day');
ok(shape(rmt_osm_hours_parse('Su off')) === '6 closed', 'off is closed, not absent');
ok(shape(rmt_osm_hours_parse('Mo-Fr 09:00-17:00; Sa 10:00-13:00; Su off'))
   === '0 09:00-17:00|1 09:00-17:00|2 09:00-17:00|3 09:00-17:00|4 09:00-17:00|5 10:00-13:00|6 closed',
   'a full week reads as a full week');
ok(shape(rmt_osm_hours_parse('Sa-Su 11:00-18:00')) === '5 11:00-18:00|6 11:00-18:00',
   'a weekend range wraps the end of the week');
$always = rmt_osm_hours_parse('24/7');
ok($always !== null && count($always) === 7, 'around the clock is seven open days');
ok(shape(rmt_osm_hours_parse('mo-fr 9:00-17:00')) === shape(rmt_osm_hours_parse('Mo-Fr 09:00-17:00')),
   'case and a missing leading zero do not change the answer');

// --- the forms we refuse, whole -------------------------------------------------------------------
foreach ([
    'Mo-Fr 09:00-17:00; PH off'            => 'public holidays',
    'Mo-Su 10:00-18:00; Dec 25 off'        => 'a date exception',
    'Jan-Mar 10:00-16:00'                  => 'a month range',
    'Mo-Fr sunset-24:00'                   => 'sunset',
    'week 1-20 Mo-Fr 09:00-17:00'          => 'week numbers',
    'Mo-Fr 09:00+'                         => 'an open ended time',
    'Mo-Fr 09:00-17:00 "by appointment"'   => 'a comment',
    '2024-2025 Mo-Fr 09:00-17:00'          => 'a year range',
    'Mo-Fr 25:00-99:00'                    => 'times that are not times',
    'Xx-Yy 09:00-17:00'                    => 'days that are not days',
    'open'                                 => 'a word with no hours in it',
    ''                                     => 'nothing at all',
] as $raw => $why) {
    ok(rmt_osm_hours_parse((string) $raw) === null, "refused: $why");
}

// --- the small hours ------------------------------------------------------------------------------
/* Half the bars in any city close after midnight. "11:30 to 02:00" IS "11:30 to midnight, then
   midnight to 02:00 the next day", so it is stored that way: the same fact in the shape the table
   holds, not a guess about anything. */
ok(shape(rmt_osm_hours_parse('Fr-Sa 22:00-04:00'))
   === '4 22:00-23:59|5 22:00-23:59|5 00:00-04:00|6 00:00-04:00',
   'a night that ends after midnight lands on both days');
ok(shape(rmt_osm_hours_parse('Mo-Sa 20:00-02:00; Su off'))
   === '0 20:00-23:59|1 20:00-23:59|1 00:00-02:00|2 20:00-23:59|2 00:00-02:00|3 20:00-23:59|3 00:00-02:00'
     . '|4 20:00-23:59|4 00:00-02:00|5 20:00-23:59|5 00:00-02:00|6 closed',
   'and a day that is explicitly shut stays shut rather than opening for two hours');
ok(rmt_osm_hours_parse('Mo-Fr 09:00-09:00') === null, 'a span of no length is still refused');

// --- the forms London writes ----------------------------------------------------------------------
/* Measured, not guessed: every value in this block was refused by the first version of this parser
   and every one of them was a real London pub, gallery or museum saying something unambiguous. */
ok(shape(rmt_osm_hours_parse('Mo-Th, Su 12:00-00:00'))
   === '0 12:00-23:59|1 12:00-23:59|2 12:00-23:59|3 12:00-23:59|6 12:00-23:59',
   'a day list written with spaces is the same day list');
ok(shape(rmt_osm_hours_parse('09:00 - 23:00'))
   === '0 09:00-23:00|1 09:00-23:00|2 09:00-23:00|3 09:00-23:00|4 09:00-23:00|5 09:00-23:00|6 09:00-23:00',
   'hours with no day at all are the same hours every day');
ok(shape(rmt_osm_hours_parse('Mo 12:00-00:00')) === '0 12:00-23:59',
   'midnight at the end of a day is the end of that day, not a nothing on the next one');
ok(shape(rmt_osm_hours_parse('Fr 12:00-01:00')) === '4 12:00-23:59|5 00:00-01:00',
   'and one in the morning is still the night before');
/* Still refused, and still for the right reasons. A public holiday rule is a fact about days this
   parser does not model, and dropping it is how a page tells somebody a museum is open on a day it
   is shut. */
ok(rmt_osm_hours_parse('Mo-We,Fr 09:30-18:00; Su,PH off') === null,
   'a week that hangs a public holiday rule off it is still refused whole');
ok(rmt_osm_hours_parse('"by appointment"') === null, 'and so is a sentence in quotation marks');

/* A closing time earlier than the opening one is not a fault, it is a night.
   An audit that read it as one flagged 66 correct rows across three cities, which is the shape
   every useless audit has: it cries wolf until nobody reads it. The parser splits such a span
   across two days; older rows on this site state it directly as 13:00 to 01:00, and schema.org
   expects exactly that. Both are right and neither is a defect. */
ok(rmt_osm_hours_parse('Mo 13:00-01:00') !== null, 'a night that runs past midnight parses');
ok(rmt_osm_hours_parse('Mo 13:00-13:00') === null, 'a span of no length does not');

// --- storing ---------------------------------------------------------------------------------------
$pdo = db();
$pdo->exec("CREATE TABLE place_hours (id INTEGER PRIMARY KEY AUTOINCREMENT, place_id INT,
              day_of_week INT, opens TEXT, closes TEXT, closed INT, valid_from TEXT,
              valid_through TEXT, sort INT, source TEXT, created_at TEXT)");

ok(rmt_osm_hours_store(1, 'Mo-Fr 09:00-17:00') === 5, 'five rows are written for five days');
ok(rmt_osm_hours_store(1, 'Mo-Fr 10:00-18:00') === 5, 'and a later run replaces its own rows');
$first = q_one('SELECT opens FROM place_hours WHERE place_id = 1 ORDER BY day_of_week LIMIT 1');
ok((string) $first['opens'] === '10:00', 'with the new value, not the old one');
ok((int) (q_one('SELECT COUNT(*) c FROM place_hours WHERE place_id = 1')['c'] ?? 0) === 5,
   'and not ten rows, which is what replacing badly looks like');

ok(rmt_osm_hours_store(2, 'Mo-Fr 09:00+') === 0, 'a value we do not trust writes nothing');
ok((int) (q_one('SELECT COUNT(*) c FROM place_hours WHERE place_id = 2')['c'] ?? 0) === 0,
   'and leaves no half written week behind');

/* Hours somebody typed by hand are not overruled by a provider, which is the same rule the field
   merge follows. The provider reports that it wrote nothing rather than winning the argument. */
$pdo->exec("INSERT INTO place_hours (place_id, day_of_week, opens, closes, closed, sort, source)
            VALUES (3, 0, '08:00', '12:00', 0, 0, NULL)");
ok(rmt_osm_hours_store(3, 'Mo-Fr 09:00-17:00') === 0, 'hours a person typed are left alone');
$kept = q_one('SELECT opens FROM place_hours WHERE place_id = 3');
ok((string) $kept['opens'] === '08:00', 'and they still say what the person said');

/* The flag has to survive both drivers. Postgres holds `closed` as a boolean and SQLite as an
   integer, and PDO will not cast an int to a bool for Postgres: it refuses the whole INSERT and the
   hours silently never arrive, which is exactly what happened to a city of imported museums. */
$src = (string) file_get_contents(BASE_PATH . '/app/osm_hours.php');
ok(str_contains($src, "\$r['closed'] ? '1' : '0'"),
   'the closed flag is sent as a string both drivers accept');

echo "osm_hours_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
