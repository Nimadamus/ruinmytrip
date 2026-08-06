<?php
declare(strict_types=1);

/**
 * Editorial landing pages — the search-visible surface of the risk reports.
 *
 * These are the pages people actually search for: "what can ruin a trip to Paris", "Barcelona
 * hidden costs", "worst time to visit Bangkok". They exist as ROWS, not as a URL pattern that
 * renders something for any slug anyone types. That distinction is the whole anti-thin-content
 * policy in one design decision:
 *
 *   * a page exists only because a person wrote and reviewed it — there is no generator that
 *     turns 80 destinations x 8 templates into 640 pages of restated database fields
 *   * an unpublished slug 404s; it never renders an empty stub for a crawler to index
 *   * every page carries its own reviewed date and its own sources, both rendered
 *   * every page is required to link back into the destination's live warnings, so the reader
 *     lands on something maintained rather than a frozen article
 */

/** The eight page shapes the editorial system supports. */
function rmt_landing_templates(): array {
    return [
        'risk_guide' => [
            'label' => 'Destination risk guide',
            'pattern' => 'what-can-ruin-a-trip-to-{destination}',
            'h1' => 'What can ruin a trip to {name}?',
            'category' => null,
        ],
        'scam_guide' => [
            'label' => 'Scam guide',
            'pattern' => '{destination}-tourist-scams',
            'h1' => '{name} tourist scams to avoid',
            'category' => 'scams',
        ],
        'cost_guide' => [
            'label' => 'Hidden cost guide',
            'pattern' => 'hidden-costs-in-{destination}',
            'h1' => 'Hidden costs in {name}',
            'category' => 'hidden-costs',
        ],
        'neighborhood_guide' => [
            'label' => 'Neighborhood guide',
            'pattern' => 'is-{area}-safe-for-tourists',
            'h1' => 'Is {area} safe for tourists?',
            'category' => 'neighborhoods',
        ],
        'seasonal_guide' => [
            'label' => 'Seasonal warning guide',
            'pattern' => 'worst-time-to-visit-{destination}',
            'h1' => 'The worst time to visit {name}',
            'category' => 'weather',
        ],
        'airport_guide' => [
            'label' => 'Airport warning guide',
            'pattern' => 'airport-scams-in-{destination}',
            'h1' => 'Airport scams and problems in {name}',
            'category' => 'scams',
        ],
        'transport_guide' => [
            'label' => 'Transportation warning guide',
            'pattern' => '{destination}-transportation-mistakes',
            'h1' => '{name} transportation mistakes',
            'category' => 'transportation',
        ],
        'attraction_guide' => [
            'label' => 'Attraction warning guide',
            'pattern' => 'common-mistakes-tourists-make-in-{destination}',
            'h1' => 'Common mistakes tourists make in {name}',
            'category' => 'crowds',
        ],
    ];
}

function rmt_landing_template_label(string $t): string {
    return rmt_landing_templates()[$t]['label'] ?? $t;
}

function rmt_landing_by_slug(string $slug): ?array {
    return q_one('SELECT p.*, d.name dest_name, d.slug dest_slug, d.country dest_country, d.hero_url,
                         d.hero_credit, d.hero_license, d.hero_source_url, d.risk_level, d.summary dest_summary
                  FROM seo_landing_pages p LEFT JOIN destinations d ON d.id = p.destination_id
                  WHERE p.slug = ?', [$slug]);
}

/**
 * GET /{slug} — the catch-all resolver, registered LAST in the route table.
 *
 * It can only ever match a slug that is a published row, so it cannot shadow a future route and
 * cannot be used to probe for internal paths.
 */
function landing_page(array $a): void {
    $p = rmt_landing_by_slug((string) $a['slug']);
    if (!$p || $p['status'] !== 'published') {
        $me = current_user();
        // Editors get a preview of their own drafts at the real URL; everyone else gets a 404.
        if (!$p || !rmt_is_moderator($me)) not_found();
    }

    $destId = $p['destination_id'] ? (int) $p['destination_id'] : 0;
    $tpl = rmt_landing_templates()[$p['template']] ?? null;
    $category = $p['category'] ?: ($tpl['category'] ?? null);

    // The live layer: whatever travelers have actually reported on this theme, right now. An
    // article that never changes is exactly the stale travel content this site exists to replace.
    $warnings = [];
    if ($destId) {
        $filters = ['destination_id' => $destId, 'sort' => 'helpful'];
        if ($category) $filters['category'] = $category;
        $warnings = rmt_warning_query($filters, 8)['rows'];
    }

    $related = [];
    if ($destId) {
        $related = q_all("SELECT slug, h1, template FROM seo_landing_pages
                          WHERE destination_id = ? AND status = 'published' AND id <> ?
                          ORDER BY id LIMIT 6", [$destId, (int) $p['id']]);
    }
    $sources = rmt_sources($p['sources_json'] ?? null);
    try { q_exec('UPDATE seo_landing_pages SET view_count = view_count + 1 WHERE id = ?', [(int) $p['id']]); }
    catch (Throwable $e) {}

    $crumbs = [['name' => 'Home', 'url' => url()]];
    if ($destId) {
        $crumbs[] = ['name' => 'Destinations', 'url' => url('explore')];
        $crumbs[] = ['name' => (string) $p['dest_name'], 'url' => url('d/' . $p['dest_slug'])];
    }
    $crumbs[] = ['name' => (string) $p['h1'], 'url' => url($p['slug'])];

    // Article markup, with the reviewed date exposed. No aggregateRating and no FAQ markup here —
    // both would be claims this page does not actually make.
    $ld = [
        '@context' => 'https://schema.org', '@type' => 'Article',
        'headline' => $p['h1'],
        'description' => $p['meta_description'],
        'datePublished' => substr((string) $p['created_at'], 0, 10),
        'dateModified' => substr((string) ($p['last_reviewed_at'] ?: $p['updated_at'] ?: $p['created_at']), 0, 10),
        'author' => ['@type' => 'Organization', 'name' => 'RuinMyTrip Editorial'],
        'publisher' => ['@type' => 'Organization', 'name' => 'RuinMyTrip', 'url' => cfg('app_url')],
        'mainEntityOfPage' => url($p['slug']),
        'about' => $destId ? ['@type' => 'TouristDestination', 'name' => $p['dest_name'] . ', ' . $p['dest_country'],
                              'url' => url('d/' . $p['dest_slug'])] : null,
    ];

    view('landing_page', compact('p', 'warnings', 'related', 'sources', 'category', 'tpl'), [
        'title' => $p['title_tag'],
        'description' => $p['meta_description'],
        'canonical' => url($p['slug']),
        'og_image' => abs_url($p['hero_url'] ?? ''),
        'jsonld' => jsonld($ld),
        'breadcrumbs' => $crumbs,
    ]);
}

/** GET /guides/warnings — the index of every published editorial landing page. */
function landing_index(array $a): void {
    $tplFilter = (string) ($_GET['type'] ?? '');
    $where = ["status = 'published'"];
    $args = [];
    if ($tplFilter !== '' && isset(rmt_landing_templates()[$tplFilter])) {
        $where[] = 'template = ?'; $args[] = $tplFilter;
    }
    $pages = q_all('SELECT p.*, d.name dest_name, d.slug dest_slug FROM seo_landing_pages p
                    LEFT JOIN destinations d ON d.id = p.destination_id
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY p.template, d.name, p.id', $args);
    $byTemplate = [];
    foreach ($pages as $row) $byTemplate[(string) $row['template']][] = $row;

    view('landing_index', ['byTemplate' => $byTemplate, 'tplFilter' => $tplFilter], [
        'title' => 'Travel warning guides by destination — RuinMyTrip',
        'description' => 'Researched guides to the scams, hidden costs, seasonal problems, airport traps and '
                       . 'transport mistakes that ruin trips — organised by destination.',
        'breadcrumbs' => [['name' => 'Home', 'url' => url()],
                          ['name' => 'Warning guides', 'url' => url('warning-guides')]],
    ]);
}
