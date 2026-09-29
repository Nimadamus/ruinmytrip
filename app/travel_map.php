<?php
declare(strict_types=1);
/**
 * The travel map: colour in every country you have been to, share it, keep it on your profile.
 *
 * It is the one thing on this site a stranger can make in thirty seconds with no account and want
 * to post, and every copy that gets posted carries our address. It works signed out end to end:
 * the countries live in the URL (`/map?c=250-380-724`), so the link IS the map, and the share image
 * is drawn from the same key. An account only adds keeping it, on the profile, next to the trips.
 *
 * Geometry is public/assets/data/world-map.json, built once by scripts/build_world_map.mjs: rings
 * already projected to a 1000 x 520 grid, so the browser draws them as SVG and GD draws the very
 * same rings for the card.
 */

const RMT_MAP_MAX = 250;

/** @return array{w:int,h:int,countries:list<array{id:?string,name:string,rings:list<list<int>>,tiny:bool,c:array{0:int,1:int}}>} */
function rmt_world_map(): array {
    static $map = null;
    if ($map === null) {
        $raw = @file_get_contents(BASE_PATH . '/public/assets/data/world-map.json');
        $map = $raw ? (json_decode($raw, true) ?: []) : [];
        $map += ['w' => 1000, 'h' => 520, 'countries' => []];
    }
    return $map;
}

/** Every country that can be picked, keyed by its code. @return array<string,string> code => name */
function rmt_map_names(): array {
    static $names = null;
    if ($names === null) {
        $names = [];
        foreach (rmt_world_map()['countries'] as $c) if ($c['id'] !== null) $names[(string) $c['id']] = (string) $c['name'];
    }
    return $names;
}

/**
 * Read a list of countries from a URL key or a form: "250-380", "250,380" or an array. Anything
 * that is not a country on the map is dropped rather than refused, so a mangled link still shows
 * the countries it does name. Sorted, so one set of countries is one key and one cached card.
 *
 * @param string|array<int,mixed> $in
 * @return list<string>
 */
function rmt_map_codes($in): array {
    $parts = is_array($in) ? $in : preg_split('/[\s,\-]+/', (string) $in, -1, PREG_SPLIT_NO_EMPTY);
    $names = rmt_map_names();
    $out = [];
    foreach ((array) $parts as $p) {
        $p = strtolower(trim((string) $p));
        if ($p !== '' && isset($names[$p])) $out[$p] = true;
        if (count($out) >= RMT_MAP_MAX) break;
    }
    $codes = array_map('strval', array_keys($out));
    sort($codes, SORT_STRING);
    return $codes;
}

/** @param list<string> $codes */
function rmt_map_key(array $codes): string { return implode('-', $codes); }

/** @return list<string> */
function rmt_map_for_user(int $userId): array {
    if ($userId < 1) return [];
    try {
        return array_map(static fn($r) => (string) $r['country'],
            q_all('SELECT country FROM user_countries WHERE user_id = ? ORDER BY country', [$userId]));
    } catch (Throwable $e) {
        return [];   // before migration 103
    }
}

/**
 * Replace a member's map with this set. The map is one thing the member edits as a whole, so a
 * save is the set they are looking at, not a stream of toggles that can race each other.
 *
 * @param list<string> $codes already validated by rmt_map_codes()
 */
function rmt_map_save(int $userId, array $codes): void {
    if ($userId < 1) return;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q_run('DELETE FROM user_countries WHERE user_id = ?', [$userId]);
        $now = date('Y-m-d H:i:s');
        foreach ($codes as $c) q_run('INSERT INTO user_countries (user_id, country, created_at) VALUES (?,?,?)', [$userId, $c, $now]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** "1 country", "23 countries". */
function rmt_map_count_label(int $n): string { return $n . ($n === 1 ? ' country' : ' countries'); }

/** The line a share says, in the first person because the person sharing it is the one saying it. */
function rmt_map_headline(array $codes): string {
    $n = count($codes);
    return $n > 0 ? "I've been to " . rmt_map_count_label($n) : 'Where have you been?';
}

function rmt_map_card_url(array $codes): string {
    return abs_url('/card/map/' . ($codes ? rmt_map_key($codes) : 'none') . '.png');
}

/**
 * The share image: the map with the countries lit, and the count. 1200 x 630, the size every
 * network previews a link at.
 *
 * @param list<string> $codes
 */
function rmt_map_card_png(array $codes): string {
    $W = 1200; $H = 630;
    $im = imagecreatetruecolor($W, $H);
    imageantialias($im, true);
    $ink   = imagecolorallocate($im, 15, 27, 45);
    $land  = imagecolorallocate($im, 44, 62, 88);
    $edge  = imagecolorallocate($im, 30, 45, 68);
    $lit   = imagecolorallocate($im, 20, 184, 166);
    $white = imagecolorallocate($im, 255, 255, 255);
    $muted = imagecolorallocate($im, 170, 184, 200);
    $brand = $lit;
    imagefilledrectangle($im, 0, 0, $W, $H, $ink);
    imagefilledrectangle($im, 0, 0, 14, $H, $brand);

    $map = rmt_world_map();
    $have = array_flip($codes);
    // The map fills the lower part of the card; the words sit above it.
    $scale = 1080 / $map['w'];
    $ox = 60; $oy = 150;
    $dots = [];
    foreach ($map['countries'] as $c) {
        $on = $c['id'] !== null && isset($have[(string) $c['id']]);
        foreach ($c['rings'] as $ring) {
            $pts = [];
            for ($i = 0, $n = count($ring); $i + 1 < $n; $i += 2) {
                $pts[] = (int) round($ox + $ring[$i] * $scale);
                $pts[] = (int) round($oy + $ring[$i + 1] * $scale);
            }
            if (count($pts) < 6) continue;
            imagefilledpolygon($im, $pts, $on ? $lit : $land);
            imagepolygon($im, $pts, $on ? $lit : $edge);
        }
        if ($on && !empty($c['tiny'])) $dots[] = $c['c'];
    }
    // A visited island too small to see as a shape still shows, as a dot.
    foreach ($dots as [$x, $y]) {
        $cx = (int) round($ox + $x * $scale); $cy = (int) round($oy + $y * $scale);
        imagefilledellipse($im, $cx, $cy, 13, 13, $ink);
        imagefilledellipse($im, $cx, $cy, 9, 9, $lit);
    }

    $bold = rmt_card_font(true); $regular = rmt_card_font(false);
    imagettftext($im, 26, 0, 72, 70, $brand, $bold, 'RuinMyTrip');
    $x = 72 + rmt_card_text_width('RuinMyTrip', $bold, 26) + 26;
    imagettftext($im, 20, 0, $x, 68, $muted, $regular, 'TRAVEL MAP');
    imagettftext($im, 50, 0, 72, 140, $white, $bold, rmt_map_headline($codes));
    $dom = 'ruinmytrip.com/map';
    $dw = rmt_card_text_width($dom, $regular, 24);
    imagefilledrectangle($im, $W - 72 - $dw - 20, $H - 84, $W - 52, $H - 36, $ink);
    imagettftext($im, 24, 0, $W - 72 - $dw, $H - 50, $brand, $regular, $dom);

    ob_start();
    imagepng($im, null, 6);
    imagedestroy($im);
    return (string) ob_get_clean();
}
