<?php
/**
 * Reading OpenStreetMap opening hours, conservatively.
 *
 * The `opening_hours` syntax is a small language. It can express "Mo-Fr 09:00-17:00" and it can
 * also express "Mo-Sa 10:00-20:00; Tu off; PH off; Jan 01 off; sunset-24:00", and a parser that
 * half understands the second kind writes a wrong fact onto a page that looks authoritative. A
 * traveler standing outside a locked door at nine on a Tuesday because this site said it was open
 * is a worse outcome than a page with a gap in it.
 *
 * So this reads the common, unambiguous forms and REFUSES everything else. What it takes:
 *
 *     24/7
 *     Mo-Fr 09:00-17:00
 *     Mo,We,Fr 10:00-14:00
 *     Mo-Sa 10:00-14:00,16:00-20:00
 *     Mo-Fr 09:00-17:00; Sa 10:00-13:00; Su off
 *
 * What it refuses, by returning null for the whole string rather than guessing at the part it
 * understood: public holidays, month or date ranges, week numbers, "sunset" and "dawn", open ended
 * ranges, comments in quotes, and anything with a token it does not recognise.
 *
 * Returns rows shaped for the place_hours table: day_of_week 0..6 with 0 as Monday, the same
 * convention rmt_place_hours() already reads.
 */
declare(strict_types=1);

/** Monday first, matching what the rest of the site stores. */
const RMT_OSM_DAYS = ['mo' => 0, 'tu' => 1, 'we' => 2, 'th' => 3, 'fr' => 4, 'sa' => 5, 'su' => 6];

/**
 * Parse an `opening_hours` value into rows, or null when it is not one of the forms we trust.
 *
 * @return list<array{day_of_week:int,opens:?string,closes:?string,closed:int}>|null
 */
function rmt_osm_hours_parse(string $raw): ?array {
    $s = trim(mb_strtolower($raw));
    if ($s === '') return null;

    /* Anything that carries a concept this parser does not model. Refused whole: taking the part we
       understood and dropping "except in August" is how a page ends up confidently wrong. */
    foreach (['ph', 'sh', 'easter', 'sunset', 'sunrise', 'dawn', 'dusk', 'week ', '"', 'open"',
              'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
              '=>', '||'] as $bad) {
        if (str_contains($s, $bad)) return null;
    }
    if (preg_match('/\d{4}\s*-\s*\d{4}/', $s)) return null;   // a year range
    if (str_contains($s, 'off') && !preg_match('/\b(mo|tu|we|th|fr|sa|su)[^;]*\boff\b/', $s)) return null;

    if ($s === '24/7') {
        $out = [];
        foreach (range(0, 6) as $d) $out[] = ['day_of_week' => $d, 'opens' => '00:00',
                                              'closes' => '23:59', 'closed' => 0];
        return $out;
    }

    $byDay = [];
    foreach (explode(';', $s) as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') continue;

        // "mo-fr 09:00-17:00" or "su off"
        if (!preg_match('/^([a-z,\-]+)\s+(.+)$/', $chunk, $m)) return null;
        $days = rmt_osm_hours_days($m[1]);
        if ($days === null) return null;

        $spec = trim($m[2]);
        if ($spec === 'off' || $spec === 'closed') {
            foreach ($days as $d) $byDay[$d] = [['day_of_week' => $d, 'opens' => null,
                                                 'closes' => null, 'closed' => 1]];
            continue;
        }

        $spans = [];
        foreach (explode(',', $spec) as $span) {
            $span = trim($span);
            if (!preg_match('/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', $span, $t)) return null;
            $from = sprintf('%02d:%02d', (int) $t[1], (int) $t[2]);
            $to   = sprintf('%02d:%02d', (int) $t[3], (int) $t[4]);
            if ((int) $t[1] > 24 || (int) $t[3] > 24 || (int) $t[2] > 59 || (int) $t[4] > 59) return null;
            // A closing time before an opening one means it runs past midnight, which this does not
            // model. Refused rather than stored backwards.
            if ($to <= $from) return null;
            $spans[] = [$from, $to];
        }
        if (!$spans) return null;

        foreach ($days as $d) {
            $byDay[$d] = [];
            foreach ($spans as [$from, $to]) {
                $byDay[$d][] = ['day_of_week' => $d, 'opens' => $from, 'closes' => $to, 'closed' => 0];
            }
        }
    }

    if (!$byDay) return null;
    ksort($byDay);
    $out = [];
    foreach ($byDay as $rows) foreach ($rows as $r) $out[] = $r;
    return $out;
}

/**
 * "mo-fr" or "mo,we,fr" or "sa" to day numbers, or null when it is not days at all.
 *
 * @return list<int>|null
 */
function rmt_osm_hours_days(string $spec): ?array {
    $days = [];
    foreach (explode(',', $spec) as $part) {
        $part = trim($part);
        if ($part === '') return null;
        if (str_contains($part, '-')) {
            [$a, $b] = array_map('trim', explode('-', $part, 2));
            if (!isset(RMT_OSM_DAYS[$a], RMT_OSM_DAYS[$b])) return null;
            $from = RMT_OSM_DAYS[$a];
            $to = RMT_OSM_DAYS[$b];
            // "sa-su" wraps around the end of the week and is perfectly normal.
            for ($d = $from; ; $d = ($d + 1) % 7) {
                $days[] = $d;
                if ($d === $to) break;
                if (count($days) > 7) return null;
            }
            continue;
        }
        if (!isset(RMT_OSM_DAYS[$part])) return null;
        $days[] = RMT_OSM_DAYS[$part];
    }
    return $days ? array_values(array_unique($days)) : null;
}

/**
 * Store parsed hours for a place, replacing what the same provider put there before.
 *
 * Only ever touches rows this importer wrote: a person who typed opening hours by hand is not
 * overruled by a provider, which is the same rule the field merge follows.
 *
 * @return int rows written, or 0 when the value was not one we trust
 */
function rmt_osm_hours_store(int $placeId, string $raw, string $source = 'openstreetmap'): int {
    if ($placeId < 1) return 0;
    $rows = rmt_osm_hours_parse($raw);
    if ($rows === null) return 0;

    $existing = q_one('SELECT COUNT(*) c FROM place_hours WHERE place_id = ?', [$placeId]);
    $had = (int) ($existing['c'] ?? 0);
    if ($had > 0) {
        $mine = q_one('SELECT COUNT(*) c FROM place_hours WHERE place_id = ? AND source = ?',
                      [$placeId, $source]);
        // Somebody else's hours. Left alone, and reported by returning nothing written.
        if ((int) ($mine['c'] ?? 0) !== $had) return 0;
        q_run('DELETE FROM place_hours WHERE place_id = ? AND source = ?', [$placeId, $source]);
    }

    $now = date('Y-m-d H:i:s');
    $n = 0;
    foreach ($rows as $i => $r) {
        q_run('INSERT INTO place_hours (place_id, day_of_week, opens, closes, closed, sort, source, created_at)
               VALUES (?,?,?,?,?,?,?,?)',
              [$placeId, $r['day_of_week'], $r['opens'], $r['closes'], $r['closed'], $i, $source, $now]);
        $n++;
    }
    return $n;
}
