<?php
declare(strict_types=1);

/**
 * Affiliate foundation — built, disclosed, and switched off.
 *
 * The site's entire value is that it tells you what is wrong with a place. The moment a
 * recommendation is quietly paid for, that is gone. So the rules are structural, not editorial
 * good intentions:
 *
 *   1. NOTHING IS LIVE WITHOUT A REAL PARTNER. `active` defaults to 0 in the schema. There are no
 *      seeded links and no placeholder URLs; a link appears only when the owner adds a real one
 *      in the admin.
 *   2. ONE RENDER PATH. Every outbound partner link goes through rmt_affiliate_block(), which
 *      always emits the disclosure and always sets rel="sponsored nofollow noopener". A page
 *      cannot forget the disclosure, because the page never writes the markup.
 *   3. CONTEXT ONLY. Links are attached to a destination or a category and render in the section
 *      they belong to. There is no global ad slot and no interstitial.
 *   4. NEVER PAYWALL A WARNING. Warnings, risk sections and FAQs are free for everyone; there is
 *      no gate anywhere in this module.
 */

/** Kinds the owner can create. Labels are shown in the admin and on the disclosure line. */
const RMT_AFFILIATE_KINDS = [
    'hotel'     => 'Hotels & stays',
    'flight'    => 'Flights',
    'insurance' => 'Travel insurance',
    'tour'      => 'Tours & attractions',
    'transport' => 'Transfers & transport',
    'esim'      => 'Data & connectivity',
    'other'     => 'Other',
];

/** The sentence shown wherever a paid link appears. One string, one meaning, site-wide. */
function rmt_affiliate_disclosure(): string {
    return 'Some links below are affiliate links. If you book through one, RuinMyTrip may earn a '
         . 'commission at no extra cost to you. It never changes what a warning says, and no partner '
         . 'can pay to have a warning removed.';
}

/**
 * Active links for a destination (its own first, then generic ones for the same kind).
 * @return array<int,array>
 */
function rmt_affiliate_links(?int $destId = null, ?string $kind = null, int $limit = 4): array {
    $where = ['active = 1'];
    $args  = [];
    if ($destId) { $where[] = '(destination_id = ? OR destination_id IS NULL)'; $args[] = $destId; }
    else         { $where[] = 'destination_id IS NULL'; }
    if ($kind)   { $where[] = 'kind = ?'; $args[] = $kind; }
    $sql = 'SELECT * FROM affiliate_links WHERE ' . implode(' AND ', $where)
         . ' ORDER BY (destination_id IS NULL), sort, id LIMIT ' . max(1, min(12, $limit));
    try { return q_all($sql, $args); } catch (Throwable $e) { return []; }
}

/**
 * Render a contextual affiliate block, or '' when there is nothing real to show.
 *
 * Returning '' rather than an empty styled box matters: a monetization shell with no partners is
 * exactly the kind of "looks unfinished" element this site does not ship.
 */
function rmt_affiliate_block(?int $destId = null, ?string $kind = null, string $heading = 'Booking options'): string {
    $links = rmt_affiliate_links($destId, $kind);
    if (!$links) return '';
    $out  = '<aside class="aff-block" aria-label="Affiliate booking options">';
    $out .= '<h3 class="aff-head">' . e($heading) . ' <span class="chip chip-muted">Paid links</span></h3>';
    $out .= '<ul class="aff-list">';
    foreach ($links as $l) {
        $out .= '<li><a class="aff-link" rel="sponsored nofollow noopener" target="_blank" href="'
              . e(url('go/' . $l['slug'])) . '">' . e((string) $l['label']) . '</a>';
        if (!empty($l['blurb'])) $out .= '<span class="muted"> — ' . e((string) $l['blurb']) . '</span>';
        $out .= '</li>';
    }
    $out .= '</ul><p class="aff-disclosure muted">' . e(rmt_affiliate_disclosure())
          . ' <a href="' . e(url('affiliate')) . '">Full disclosure</a>.</p></aside>';
    return $out;
}

function rmt_affiliate_by_slug(string $slug): ?array {
    try { return q_one('SELECT * FROM affiliate_links WHERE slug = ? AND active = 1', [$slug]); }
    catch (Throwable $e) { return null; }
}

/** Count a click. Derived counter kept for the admin list; the event table is the real record. */
function rmt_affiliate_record_click(array $link): void {
    try {
        q_exec('UPDATE affiliate_links SET click_count = click_count + 1 WHERE id = ?', [(int) $link['id']]);
    } catch (Throwable $e) {
        // never block the redirect
    }
    rmt_track('affiliate_click', [
        'destination_id' => $link['destination_id'] ?? null,
        'target_type'    => 'affiliate_link',
        'target_id'      => (int) $link['id'],
        'meta'           => ['provider' => $link['provider'] ?? '', 'kind' => $link['kind'] ?? ''],
    ]);
}
