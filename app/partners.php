<?php
declare(strict_types=1);

/**
 * Partner (affiliate and referral) links, ready and OFF (2026-11-07).
 *
 * The brand promise is "nobody can buy a rating", so these rules are the module, not a convention:
 *   1. A partner block renders only where it is useful on its own terms: the claims page (a claim
 *      service, for someone who would rather not chase an airline) and ticket notes (booking the
 *      attraction the note is about). Never on a review, a rating, a city's ratings, a member's
 *      content or a search result, and never ordered by commission.
 *   2. It renders only when the owner has joined the program and set its URL on the server
 *      (environment variable, see RMT_PARTNERS). Unset means nothing is drawn: no placeholder,
 *      no "coming soon", no dead link.
 *   3. Every block says "Paid link" next to the link, links /affiliate, and the link carries
 *      rel="sponsored noopener" (Google's rule for paid links; the FTC's for disclosure next to it).
 *   4. The copy says what the reader can do for free first. A claim service is offered as a
 *      convenience, after the free route, not instead of it.
 *
 * tests/partners_test.php holds all four.
 */

/** The programs. 'env' holds the full tracked URL; a '{q}' in it is replaced by the page's subject. */
const RMT_PARTNERS = [
    'airhelp' => [
        'env'   => 'PARTNER_AIRHELP_URL',
        'name'  => 'AirHelp',
        'title' => 'Rather not chase the airline yourself?',
        'text'  => 'Claim services such as AirHelp file the claim and take a share of what they recover; their fee is on their site. Everything above can be done yourself for free.',
        'cta'   => 'See what AirHelp charges',
    ],
    'tickets' => [
        'env'   => 'PARTNER_TICKETS_URL',
        'name'  => 'our ticket partner',
        'title' => 'Booking',
        'text'  => 'The official site above is the place to book and usually the cheapest. If it is sold out, a reseller may still have tickets at a higher price.',
        'cta'   => 'Check reseller availability',
    ],
];

/** Which partner, if any, belongs on a blog post. Ticket notes are recognised by their slug. */
function rmt_partner_for_post(string $slug): ?array {
    if ($slug === 'trip-went-wrong-what-you-can-claim') return ['key' => 'airhelp', 'q' => ''];
    if (preg_match('/^(.+?)-(tickets|ticket-price)-2026$/', $slug, $m)) {
        return ['key' => 'tickets', 'q' => ucwords(str_replace('-', ' ', $m[1]))];
    }
    return null;
}

/** The configured URL for a partner, or null when the owner has not set it (the default). */
function rmt_partner_url(string $key, string $q = ''): ?string {
    $p = RMT_PARTNERS[$key] ?? null;
    if (!$p) return null;
    $url = trim((string) (getenv($p['env']) ?: ''));
    if (!preg_match('#^https://[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $url)) return null;
    return str_replace('{q}', rawurlencode($q), $url);
}

/** The block for a blog post, or '' (the usual case until a program is joined). */
function rmt_partner_block_for_post(string $slug): string {
    $m = rmt_partner_for_post($slug);
    if (!$m) return '';
    $url = rmt_partner_url($m['key'], $m['q']);
    if ($url === null) return '';
    $p = RMT_PARTNERS[$m['key']];
    return '<aside class="card partner-block" aria-label="Paid link" style="margin:22px 0"><div class="card-body">'
         . '<p style="margin:0 0 6px"><b>' . e($p['title']) . '</b></p>'
         . '<p class="muted" style="margin:0 0 10px">' . e($p['text']) . '</p>'
         . '<p style="margin:0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">'
         . '<a class="btn btn-ghost btn-sm" href="' . e($url) . '" rel="sponsored noopener" target="_blank" data-cta="partner_' . e($m['key']) . '">' . e($p['cta']) . '</a>'
         . '<span class="hint">Paid link: we may earn a commission. It never affects a rating. <a href="' . e(url('affiliate')) . '">How this works</a></span>'
         . '</p></div></aside>';
}
