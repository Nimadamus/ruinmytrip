<?php
declare(strict_types=1);

/**
 * The tourist tax calculator as a free embed (2026-11-17, plan weeks 6 to 9: "offer the calculator
 * as a free embed with a credit link for travel bloggers and city guides").
 *
 * A script, not an iframe. The whole site sends frame-ancestors 'self' and X-Frame-Options from
 * both PHP and Apache, and opening that up for one path would mean an Apache exception that every
 * later header change has to remember. A script tag needs none of it: GET /embed/tourist-tax.js
 * returns the rates from rmt_tt_cities() and a small renderer, so the embed can never disagree with
 * the calculator, and the host page needs only:
 *
 *   <div data-rmt-tax-calc></div>
 *   <script src="https://ruinmytrip.com/embed/tourist-tax.js" async></script>
 *
 * The widget writes plain DOM with textContent (no innerHTML of data), styles itself under one
 * class so it cannot leak into the host page, and ends with a visible credit link back to the
 * calculator. It sets no cookies and calls nothing after it loads.
 */

/** The rates the widget needs, nothing else. */
function rmt_tt_embed_data(): array {
    $out = [];
    foreach (rmt_tt_cities() as $k => $c) {
        $tiers = [];
        foreach ($c['tiers'] as $tk => $t) {
            $tiers[] = ['key' => (string) $tk, 'label' => (string) ($t['label'] ?? '')]
                + array_intersect_key($t, array_flip(['pp', 'pct', 'room', 'bands']));
        }
        $out[] = ['key' => (string) $k, 'name' => $c['name'], 'cur' => $c['cur'], 'cap' => $c['cap'] ?? null,
                  'rate' => $c['rate'], 'tip' => $c['tip'], 'tiers' => $tiers,
                  'url' => url('tourist-tax-calculator?city=' . $k . '&utm_source=embed&utm_medium=widget')];
    }
    return $out;
}

/** GET /embed/tourist-tax.js */
function tourist_tax_embed_js(array $a): void {
    $data = json_encode(['cities' => rmt_tt_embed_data(), 'home' => url('tourist-tax-calculator?utm_source=embed&utm_medium=widget'),
                         'year' => '2026'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('Access-Control-Allow-Origin: *');
    header('X-Robots-Tag: noindex');
    $js = (string) file_get_contents(BASE_PATH . '/public/assets/js/tourist-tax-embed.src.js');
    echo str_replace('/*RMT_DATA*/null', (string) $data, $js);
}

/** GET /tourist-tax-calculator/embed  the copy and paste page for bloggers. */
function tourist_tax_embed_page(array $a): void {
    $snippet = "<div data-rmt-tax-calc></div>\n<script src=\"" . url('embed/tourist-tax.js') . "\" async></script>";
    $citySnippet = "<div data-rmt-tax-calc data-city=\"amsterdam\"></div>\n<script src=\"" . url('embed/tourist-tax.js') . "\" async></script>";
    view('tourist_tax_embed', ['snippet' => $snippet, 'citySnippet' => $citySnippet, 'cities' => rmt_tt_cities()], [
        'title' => 'Free tourist tax calculator for your website | RuinMyTrip',
        'description' => 'Add the RuinMyTrip tourist tax calculator to your travel blog or city guide. Free, two lines of code, ' . count(rmt_tt_cities()) . ' cities, sourced rates, no cookies.',
        'canonical' => url('tourist-tax-calculator/embed'),
    ]);
}
