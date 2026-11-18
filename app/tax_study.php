<?php
declare(strict_types=1);

/**
 * "Every European tourist tax change for 2027, ranked" (2026-11-17, plan weeks 6 to 9).
 *
 * The dated data study for press. Data: database/tax2027/changes_2027.json, one row per city, and
 * the same evidence rule as the scam lists: a row is shown only with an official https source and
 * a quote copied from it (rmt_city_scam_url_ok). A city with only news coverage is left out, never
 * shown with a guess.
 *
 * The ranking prices one trip everywhere: 2 adults, 1 room, 4 nights at 200 a night in the local
 * currency before VAT, four star where a city charges by category. Cities are ranked by how much
 * more that trip pays in tax in 2027 than in 2026, as a percentage, so euro, pound and krone cities
 * sit in one list without an exchange rate. A brand new tax (nothing in 2026) ranks first.
 *
 * For a city in the calculator, the 2026 figure comes from rmt_tt_cities(), so the study, the
 * calculator and the comparison table can never disagree. The CSV at /tourist-tax-2027.csv is the
 * same rows.
 */

const RMT_TT27_FILE = BASE_PATH . '/database/tax2027/changes_2027.json';
const RMT_TT27_TRIP = ['nights' => 4, 'people' => 2];
/** The night price in each currency: 200 euros or pounds; in Norway 2,000 kroner, since 200 kroner is not a hotel night. */
const RMT_TT27_PRICE = ['EUR' => 200.0, 'GBP' => 200.0, 'NOK' => 2000.0];
const RMT_TT27_STATUS = [
    'approved' => 'Approved', 'scheduled_in_law' => 'In law', 'proposed' => 'Proposed',
];

/** @return list<array> shown rows, ranked */
function rmt_tt27_rows(): array {
    static $rows = null;
    if ($rows !== null) return $rows;
    $rows = [];
    if (!is_file(RMT_TT27_FILE)) return $rows;
    $raw = json_decode((string) file_get_contents(RMT_TT27_FILE), true);
    if (!is_array($raw)) return $rows;
    $cities = rmt_tt_cities();
    foreach ((array) ($raw['changes'] ?? []) as $r) {
        if (!is_array($r) || !isset(RMT_TT27_STATUS[$r['status'] ?? ''])) continue;
        $sources = [];
        foreach ((array) ($r['sources'] ?? []) as $s) {
            $url = trim((string) ($s['url'] ?? ''));
            if (!rmt_city_scam_url_ok($url) || trim((string) ($s['quote'] ?? '')) === '' || trim((string) ($s['publisher'] ?? '')) === '') continue;
            $sources[] = ['url' => $url, 'publisher' => trim((string) $s['publisher']), 'quote' => trim((string) $s['quote']),
                          'quote_en' => trim((string) ($s['quote_en'] ?? ''))];
        }
        if (!$sources || !is_array($r['calc_2027'] ?? null)) continue;
        $key = (string) ($r['key'] ?? '');
        $cal = $cities[$key] ?? null;
        $cap27 = $r['calc_2027']['cap_nights'] ?? ($cal['cap'] ?? null);
        $t26 = $r['calc_2026'] ?? null;
        $cap26 = is_array($t26) ? ($t26['cap_nights'] ?? null) : null;
        if ($cal && !is_array($t26)) {
            $tk = RMT_TT_COMPARE_TIER[$key] ?? (string) array_key_first($cal['tiers']);
            $t26 = $cal['tiers'][$tk] ?? null;
            $cap26 = $cal['cap'] ?? null;
        }
        $cur = (string) ($r['currency'] ?? 'EUR');
        if (!isset(RMT_TT27_PRICE[$cur])) continue;
        $trip = static function (?array $t, $cap) use ($cur): float {
            if (!$t) return 0.0;
            $c = $cap !== null ? ['cap' => (int) $cap] : [];
            return (float) rmt_tt_estimate($c, $t, RMT_TT27_TRIP['nights'], RMT_TT27_TRIP['people'], 1, RMT_TT27_PRICE[$cur])['total'];
        };
        $now = $trip($t26, $cap26);
        $next = $trip($r['calc_2027'], $cap27);
        $rows[] = [
            'key' => $key, 'city' => (string) $r['city'], 'country' => (string) ($r['country'] ?? ''),
            'cur' => $cur, 'status' => (string) $r['status'],
            'date' => (string) ($r['effective_date'] ?? ''), 'rate_2026' => (string) ($r['rate_2026'] ?? ''),
            'rate_2027' => (string) ($r['rate_2027'] ?? ''), 'notes' => (string) ($r['who_pays_notes'] ?? ''),
            'now' => $now, 'next' => $next,
            'rise_pct' => $now > 0 ? round(($next - $now) / $now * 100, 1) : null,
            'new' => $now <= 0 && $next > 0,
            'in_calc' => $cal !== null, 'note' => $cal['note'] ?? (isset($r['note']) && preg_match('/^[a-z0-9-]+$/', (string) $r['note']) ? (string) $r['note'] : null), 'sources' => $sources,
        ];
    }
    usort($rows, static function (array $a, array $b): int {
        if ($a['new'] !== $b['new']) return $a['new'] ? -1 : 1;
        if ($a['new']) return strcmp($a['date'] !== '' ? $a['date'] : '9999', $b['date'] !== '' ? $b['date'] : '9999') ?: strcmp($a['city'], $b['city']);
        return ($b['rise_pct'] ?? 0) <=> ($a['rise_pct'] ?? 0) ?: strcmp($a['city'], $b['city']);
    });
    return $rows;
}

/** Changes on the way whose rates are not final: listed with their source, never priced. */
function rmt_tt27_pending(): array {
    $raw = is_file(RMT_TT27_FILE) ? json_decode((string) file_get_contents(RMT_TT27_FILE), true) : null;
    $out = [];
    foreach ((array) ($raw['pending'] ?? []) as $r) {
        if (!is_array($r)) continue;
        $src = array_values(array_filter((array) ($r['sources'] ?? []), static fn($s) => is_array($s) && rmt_city_scam_url_ok((string) ($s['url'] ?? '')) && trim((string) ($s['quote'] ?? '')) !== ''));
        if ($src) $out[] = ['city' => (string) $r['city'], 'country' => (string) ($r['country'] ?? ''), 'date' => (string) ($r['effective_date'] ?? ''),
                            'rate' => (string) ($r['rate_2027'] ?? ''), 'notes' => (string) ($r['who_pays_notes'] ?? ''), 'sources' => $src];
    }
    return $out;
}

function rmt_tt27_checked(): string {
    $raw = is_file(RMT_TT27_FILE) ? json_decode((string) file_get_contents(RMT_TT27_FILE), true) : null;
    return is_array($raw) ? (string) ($raw['checked'] ?? '') : '';
}

/** GET /tourist-tax-2027 */
function tourist_tax_2027_page(array $a): void {
    $rows = rmt_tt27_rows();
    $checked = rmt_tt27_checked();
    $pending = rmt_tt27_pending();
    view('tourist_tax_2027', compact('rows', 'checked', 'pending'), [
        'title' => 'Every European tourist tax change for 2027, ranked | RuinMyTrip',
        'description' => count($rows) . ' European cities change their tourist tax in 2027. The same trip priced in each, ranked by how much more it pays, with the official source for every rate.',
        'canonical' => url('tourist-tax-2027'),
        'jsonld' => jsonld(['@context' => 'https://schema.org', '@type' => 'Dataset',
            'name' => 'European tourist tax changes for 2027', 'url' => url('tourist-tax-2027'),
            'description' => 'Tourist and accommodation tax changes taking effect in 2027 in European cities, each with the official source, and the tax on one standard trip in 2026 and 2027.',
            'dateModified' => $checked, 'isAccessibleForFree' => true,
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
            'creator' => ['@type' => 'Organization', 'name' => 'RuinMyTrip', 'url' => url('')],
            'distribution' => [['@type' => 'DataDownload', 'encodingFormat' => 'text/csv', 'contentUrl' => url('tourist-tax-2027.csv')]]]),
    ]);
}

/** GET /tourist-tax-2027.csv */
function tourist_tax_2027_csv(array $a): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ruinmytrip-tourist-tax-changes-2027.csv"');
    $f = fopen('php://output', 'w');
    fputcsv($f, ['rank', 'city', 'country', 'status', 'effective_date', 'rate_2026', 'rate_2027', 'currency',
                 'trip_tax_2026', 'trip_tax_2027', 'rise_percent', 'source_publisher', 'source_url', 'source_quote', 'checked'], ',', '"', '');
    $checked = rmt_tt27_checked();
    foreach (rmt_tt27_rows() as $i => $r) {
        fputcsv($f, [$i + 1, $r['city'], $r['country'], RMT_TT27_STATUS[$r['status']], $r['date'], $r['rate_2026'], $r['rate_2027'],
                     $r['cur'], number_format($r['now'], 2, '.', ''), number_format($r['next'], 2, '.', ''),
                     $r['new'] ? 'new tax' : (string) $r['rise_pct'], $r['sources'][0]['publisher'], $r['sources'][0]['url'],
                     $r['sources'][0]['quote'], $checked], ',', '"', '');
    }
    fclose($f);
}
