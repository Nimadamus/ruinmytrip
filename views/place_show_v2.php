<?php
/**
 * Place page, design v2 (2026-10-11), behind RMT_PLACE_V2 (app/place_v2.php). Same data, same
 * title, description, canonical, robots and JSON-LD as views/place_show.php: the controller is
 * shared and only the template changes, so turning the flag off is a full rollback.
 *
 * What changed, and why (docs/AUDIT_20261011.md):
 *   - The first screen answers what a searcher came for: what it is, where, open now, today's
 *     hours, and one tap to directions, the website or a call. Every place has some of that; none
 *     has photos, and almost none has reviews.
 *   - The page is useful without reviews. Ratings show when they exist; when they do not, one line
 *     says so and the stars to start the first review sit right there.
 *   - "Who is going" moved below the facts and only takes space when the city has real travelers.
 *   - The repeated blocks (meet travelers, ask, review CTA three times) are one each.
 *   - Mobile first: a two column layout on desktop, one column on a phone, no horizontal scroll.
 *
 * Nothing is invented: no stock photo of the city passed off as the place, no placeholder ratings,
 * no "popular" claims. Editorial content keeps its label.
 *
 * @var array $p @var array $stats @var array $breakdown @var array $aspectAverages @var array $reviews
 * @var array $editorial @var array $photos @var int $photoCount @var ?array $me @var string $typeLabel
 * @var ?array $ed @var array $nearby @var array $nearbyGeo @var array $similar @var array $myLists
 * @var ?array $placeArea @var array $inGuides @var bool $saved @var int $saveCount @var array $hours
 * @var array $hoursByDay @var ?bool $openNow @var array $address @var ?array $coords @var ?array $category
 * @var ?string $priceLabel @var ?string $cover @var array $talk @var array $network
 */
$status    = rmt_place_status((string) $p['status']);
$closed    = $status === 'permanently_closed';
$trading   = rmt_place_is_trading((string) $p['status']);
$placePath = rmt_place_path($p);
$catName   = (string) ($category['name'] ?? $typeLabel);
$network   = $network ?? ['planned' => [], 'recommended' => [], 'planned_n' => 0, 'recommended_n' => 0, 'overlapping' => 0];
$todayName = (new DateTimeImmutable('now', new DateTimeZone((string) (($p['timezone'] ?? '') ?: 'UTC'))))->format('l');
$today     = null;
foreach ($hoursByDay as $d) if (strcasecmp((string) $d['day'], $todayName) === 0) $today = $d;
$reviewHref = url('review/new?place=' . (int) $p['id'] . '&src=place');
$mapHref    = $coords ? rmt_place_map_url($coords[0], $coords[1]) : null;
$icon = static function (string $name): string {
    $paths = [
        'restaurant' => '<path d="M7 2v9a3 3 0 0 0 3 3v8M7 2v6M10 2v6M4 2v6a3 3 0 0 0 3 3M17 22V2c-2.5 1-4 4-4 8v2h4"/>',
        'hotel'      => '<path d="M3 21V7M3 12h18v9M21 21v-6M7 12V9h5v3"/>',
        'attraction' => '<path d="M3 21h18M5 21V10M19 21V10M9 21v-7M15 21v-7M2 10l10-7 10 7"/>',
        'experience' => '<path d="M12 2l3 6.5 7 1-5 5 1.2 7L12 18l-6.2 3.5L7 14.5l-5-5 7-1z"/>',
        'directions' => '<path d="M12 2l9 9-9 9-9-9z"/><path d="M9 13v-2h5V9l3 3-3 3v-2"/>',
        'web'        => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 3 2.5 15 0 18M12 3c-2.5 3-2.5 15 0 18"/>',
        'phone'      => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'save'       => '<path d="M6 3h12v18l-6-4-6 4z"/>',
        'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'pin'        => '<path d="M12 22s7-6.5 7-12a7 7 0 0 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
    ];
    return '<svg class="pv2-ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
         . ($paths[$name] ?? $paths['pin']) . '</svg>';
};
$typeIcon = in_array((string) $p['type'], ['restaurant', 'hotel', 'attraction', 'experience'], true) ? (string) $p['type'] : 'attraction';
$statusLabel = rmt_place_status_label((string) $p['status']);
?>
<div class="pv2">
  <header class="pv2-hero<?= $cover ? ' has-cover' : '' ?>">
    <?php /* The place's own photo, or a designed band. Never the city's photo standing in for it. */ ?>
    <?php if ($cover): ?>
      <img class="pv2-cover" src="<?= e(abs_url($cover)) ?>" alt="<?= e($p['name']) ?>" fetchpriority="high">
    <?php endif; ?>
    <div class="wrap pv2-hero-in">
      <nav class="pv2-crumbs" aria-label="Breadcrumb">
        <a href="<?= e(url()) ?>">Home</a><span>/</span><a href="<?= e(url('explore')) ?>">Explore</a><span>/</span>
        <a href="<?= e(url('d/' . $p['dest_slug'])) ?>"><?= e($p['dest_name']) ?></a><span>/</span>
        <a href="<?= e(url('d/' . $p['dest_slug'] . '/places')) ?>">Places</a>
      </nav>
      <div class="pv2-title-row">
        <span class="pv2-badge" aria-hidden="true"><?= $icon($typeIcon) ?></span>
        <div>
          <p class="pv2-eyebrow"><?= e($catName) ?> &middot; <?= e(trim($p['dest_name'] . ', ' . $p['dest_country'], ', ')) ?></p>
          <h1 class="pv2-h1"><?= e($p['name']) ?></h1>
        </div>
      </div>
      <ul class="pv2-chips" aria-label="At a glance">
        <?php if ($openNow !== null): ?>
          <li class="pv2-chip <?= $openNow ? 'is-open' : 'is-closed' ?>"><?= $openNow ? 'Open now' : 'Closed now' ?></li>
        <?php endif; ?>
        <?php if ($trading && $today): ?>
          <li class="pv2-chip"><?= $icon('clock') ?> Today <?= $today['closed'] ? 'closed' : e(implode(', ', $today['intervals'])) ?></li>
        <?php endif; ?>
        <?php if ($priceLabel): ?><li class="pv2-chip"><?= e($priceLabel) ?></li><?php endif; ?>
        <?php if ($placeArea): ?>
          <li class="pv2-chip"><?= $icon('pin') ?> <a href="<?= e(url('d/' . $placeArea['dest_slug'] . '/n/' . $placeArea['slug'])) ?>"><?= e((string) $placeArea['canonical_name']) ?></a></li>
        <?php elseif (!empty($p['neighborhood'])): ?>
          <li class="pv2-chip"><?= $icon('pin') ?> <?= e((string) $p['neighborhood']) ?></li>
        <?php endif; ?>
        <?php if ($stats['c'] > 0): ?>
          <li class="pv2-chip is-rating"><span class="stars"><?= stars((int) round((float) $stats['a'])) ?></span>
            <b><?= e((string) $stats['a']) ?></b> &middot; <?= (int) $stats['c'] ?> traveler <?= $stats['c'] === 1 ? 'review' : 'reviews' ?></li>
        <?php endif; ?>
      </ul>
    </div>
  </header>

  <div class="wrap">
    <?php if ($statusLabel !== null): ?>
      <div class="callout pv2-status"><b><?= e($statusLabel) ?>.</b>
        <?= $closed ? 'Everything below is kept for the record: what travelers wrote, and what the place was like while it was open.'
                    : 'It is expected to reopen. The hours below are the ones it kept before, not a promise about this week.' ?>
      </div>
    <?php endif; ?>

    <?php /* The action bar: what somebody standing on the street needs, each only when we hold it. */ ?>
    <div class="pv2-actions">
      <?php if ($mapHref): ?>
        <a class="pv2-act" href="<?= e($mapHref) ?>" rel="nofollow noopener" target="_blank"><?= $icon('directions') ?><span>Directions</span></a>
      <?php endif; ?>
      <?php if (!empty($p['website_url'])): ?>
        <a class="pv2-act" href="<?= e((string) $p['website_url']) ?>" rel="nofollow noopener" target="_blank"><?= $icon('web') ?><span>Website</span></a>
      <?php endif; ?>
      <?php if (!empty($p['phone'])): ?>
        <a class="pv2-act" href="tel:<?= e(rmt_place_tel_href((string) $p['phone'])) ?>"><?= $icon('phone') ?><span>Call</span></a>
      <?php endif; ?>
      <?php if ($me): ?>
        <form method="post" action="<?= e(url('place/save')) ?>" class="pv2-act-form">
          <?= csrf_field() ?>
          <input type="hidden" name="place_id" value="<?= (int) $p['id'] ?>">
          <input type="hidden" name="return" value="<?= e($placePath) ?>">
          <input type="hidden" name="want" value="<?= $saved ? 'off' : 'on' ?>">
          <button class="pv2-act<?= $saved ? ' is-on' : '' ?>" aria-pressed="<?= $saved ? 'true' : 'false' ?>"><?= $icon('save') ?><span><?= $saved ? 'Saved' : 'Save' ?></span></button>
        </form>
      <?php else: ?>
        <a class="pv2-act" rel="nofollow" href="<?= e(url('login?return=' . rawurlencode($placePath))) ?>"><?= $icon('save') ?><span>Save</span></a>
      <?php endif; ?>
    </div>

    <div class="pv2-grid">
      <main class="pv2-main">
        <?php if (!$closed): ?>
          <section class="pv2-card pv2-rate">
            <?php include __DIR__ . '/_rate_place.php'; ?>
            <?php if ($stats['c'] === 0): ?>
              <p class="hint" style="margin:8px 0 0">No traveler has reviewed it yet. Yours would be the first on this page, and the bad parts are the useful parts.</p>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if (!empty($inGuides)): ?>
          <p class="hint" style="margin:0 0 16px">Mentioned in
            <?php foreach ($inGuides as $i => $gd): ?><?= $i > 0 ? ', ' : '' ?><a href="<?= e(url('g/' . $gd['slug'])) ?>"><?= e((string) $gd['title']) ?></a><?php endforeach; ?>.</p>
        <?php endif; ?>

        <?php if ($stats['c'] > 0): ?>
          <section class="pv2-card">
            <h2 class="pv2-h2">What travelers rated it</h2>
            <div class="pv2-score">
              <div class="pv2-score-big"><b><?= e((string) $stats['a']) ?></b><span class="stars"><?= stars((int) round((float) $stats['a'])) ?></span>
                <span class="hint"><?= (int) $stats['c'] ?> <?= $stats['c'] === 1 ? 'review' : 'reviews' ?></span></div>
              <div class="pv2-bars">
                <?php foreach ([5, 4, 3, 2, 1] as $n): $c = (int) $breakdown[$n]; $pct = $stats['c'] > 0 ? round($c * 100 / $stats['c']) : 0; ?>
                  <div class="pv2-bar"><span><?= $n ?> ★</span><i><em style="width:<?= $pct ?>%"></em></i><span><?= $c ?></span></div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php if (!empty($aspectAverages)): ?>
              <div class="pv2-aspects">
                <?php foreach ($aspectAverages as $a): ?>
                  <div class="pv2-bar"><span><?= e($a['label']) ?></span><i><em style="width:<?= round($a['avg'] * 20) ?>%"></em></i><span><?= e(number_format($a['avg'], 1)) ?></span></div>
                <?php endforeach; ?>
                <p class="hint">Shown once at least <?= RMT_ASPECT_MIN_SAMPLE ?> travelers have rated it.</p>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($network['planned_n'] > 0 || $network['recommended_n'] > 0): ?>
          <section class="pv2-card place-network">
            <?php if ($network['planned_n'] > 0): ?>
              <p class="place-net-line">
                <?php foreach ($network['planned'] as $pl): ?>
                  <a href="<?= e(url('u/' . $pl['username'])) ?>" title="@<?= e((string) $pl['username']) ?>"><img class="avatar" src="<?= e(avatar_url($pl['avatar_url'] ?? null)) ?>" alt=""></a>
                <?php endforeach; ?>
                <span><b><?= (int) $network['planned_n'] ?> <?= (int) $network['planned_n'] === 1 ? 'traveler has' : 'travelers have' ?> this planned</b></span>
              </p>
            <?php endif; ?>
            <?php if ($network['recommended_n'] > 0): ?>
              <p class="place-net-line"><span><b><?= (int) $network['recommended_n'] ?> <?= (int) $network['recommended_n'] === 1 ? 'traveler went' : 'travelers went' ?> and would go again</b></span></p>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($ed): ?>
          <section class="pv2-card">
            <div class="pv2-head"><h2 class="pv2-h2">RuinMyTrip guide to <?= e($p['name']) ?></h2><?= rmt_editorial_badge('review') ?></div>
            <p class="hint" style="margin:0 0 12px"><?= rmt_editorial_disclosure() ?></p>
            <?php foreach (rmt_place_editorial_sections((string) $p['type']) as $col => $heading): ?>
              <?php $val = trim((string) ($ed[$col] ?? '')); if ($val === '') continue; ?>
              <h3 class="pv2-h3"><?= e($heading) ?></h3>
              <?php foreach (preg_split('/\n\s*\n/', $val) ?: [] as $para): $para = trim($para); if ($para === '') continue; ?>
                <p style="margin:0 0 .6rem"><?= e($para) ?></p>
              <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if (!empty($ed['sources'])): ?>
              <details class="pv2-sources"><summary>Sources</summary>
                <ul class="list-plain"><?php foreach ($ed['sources'] as $s): ?>
                  <li><?= e((string) ($s['fact'] ?? '')) ?><?php if (!empty($s['url'])): ?> <a href="<?= e((string) $s['url']) ?>" rel="nofollow noopener" target="_blank">source</a><?php endif; ?></li>
                <?php endforeach; ?></ul>
              </details>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($photos): ?>
          <section class="pv2-card">
            <h2 class="pv2-h2"><?= count($photos) === 1 ? 'Photo' : 'Photos' ?></h2>
            <div class="pv2-photos">
              <?php foreach ($photos as $ph):
                $alt = $ph['alt_text'] ?: ($ph['caption'] ?: ($ph['kind'] === 'review' ? $p['name'] . ' photo by @' . ($ph['author']['username'] ?? '') : $p['name'])); ?>
                <?php if ($ph['kind'] === 'review' && $ph['parent_id']): ?>
                  <a href="<?= e(url('review/' . $ph['parent_id'] . ($ph['parent_slug'] ? '/' . $ph['parent_slug'] : ''))) ?>"><img loading="lazy" src="<?= e(abs_url($ph['url'])) ?>" alt="<?= e($alt) ?>"></a>
                <?php else: ?>
                  <img loading="lazy" src="<?= e(abs_url($ph['url'])) ?>" alt="<?= e($alt) ?>">
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
            <?php if ($photoCount > count($photos)): ?><p class="hint"><?= (int) $photoCount ?> photos in total.</p><?php endif; ?>
          </section>
        <?php endif; ?>

        <section class="pv2-card" id="reviews">
          <div class="pv2-head">
            <h2 class="pv2-h2"><?= $stats['c'] > 0 ? 'What travelers said' : 'Traveler reviews' ?></h2>
            <?php if (!$closed && $reviews): ?><a class="btn btn-ghost btn-sm" rel="nofollow" data-review-cta="place" data-place-id="<?= (int) $p['id'] ?>" href="<?= e($reviewHref) ?>">Write a review</a><?php endif; ?>
          </div>
          <?php if (!$reviews): ?>
            <div class="pv2-empty">
              <p class="pv2-empty-t">No traveler reviews yet</p>
              <p class="muted" style="margin:0 0 14px">Say what it actually cost and what you wish you had known. Stars and a few sentences, no account needed to start.</p>
              <?php if (!$closed): ?><a class="btn btn-accent" rel="nofollow" data-review-cta="place" data-place-id="<?= (int) $p['id'] ?>" href="<?= e($reviewHref) ?>">Write the first review</a><?php endif; ?>
            </div>
          <?php endif; ?>
          <?php foreach ($reviews as $r): $href = url('review/' . (int) $r['id'] . '/' . ($r['slug'] ?: rmt_review_slug($r))); ?>
            <article class="pv2-review">
              <div class="pv2-head"><span class="stars"><?= stars((int) $r['rating']) ?></span><?php if (show_verified($r)): ?><span class="verified">Verified</span><?php endif; ?></div>
              <h3 class="pv2-h3"><a href="<?= e($href) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a></h3>
              <p style="margin:.3rem 0 .4rem"><?= e(mb_strimwidth((string) $r['body'], 0, 260, '…')) ?></p>
              <p class="hint" style="margin:0">
                <?php if (!empty($r['author']['username'])): ?><a href="<?= e(url('u/' . $r['author']['username'])) ?>">@<?= e((string) $r['author']['username']) ?></a><?php else: ?>@traveler<?php endif; ?>
                &middot; <?= e(ago((string) $r['created_at'])) ?>
                <?php if (!empty($r['useful_count'])): ?> &middot; <?= (int) $r['useful_count'] ?> found this useful<?php endif; ?>
                <?php if (rmt_review_is_stale($r)): ?> &middot; may be outdated<?php endif; ?>
                <?php if (rmt_review_can_edit($r, $me)): ?> &middot; <a href="<?= e(url('review/' . (int) $r['id'] . '/edit')) ?>">Edit</a>
                <?php elseif ($me): ?> &middot; <a href="<?= e(url('report?target_type=review&target_id=' . (int) $r['id'])) ?>">Report</a><?php endif; ?>
              </p>
            </article>
          <?php endforeach; ?>
        </section>

        <?php if ($editorial): ?>
          <section class="pv2-card">
            <div class="pv2-head"><h2 class="pv2-h2">From the RuinMyTrip team</h2><?= rmt_editorial_badge('review') ?></div>
            <p class="hint" style="margin:0 0 10px"><?= rmt_editorial_disclosure() ?></p>
            <?php foreach ($editorial as $r): $href = url('review/' . (int) $r['id'] . '/' . ($r['slug'] ?: rmt_review_slug($r))); ?>
              <article class="pv2-review">
                <span class="stars"><?= stars((int) $r['rating']) ?></span>
                <h3 class="pv2-h3"><a href="<?= e($href) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a></h3>
                <p style="margin:.3rem 0 0;line-height:1.55"><?= e(excerpt((string) $r['body'], 420)) ?></p>
              </article>
            <?php endforeach; ?>
          </section>
        <?php endif; ?>

        <section class="pv2-card" id="questions">
          <h2 class="pv2-h2">Questions about <?= e($p['name']) ?></h2>
          <?php foreach ($talk as $tp): ?>
            <div class="pv2-q">
              <b><a href="<?= e(url('u/' . $tp['username'])) ?>">@<?= e((string) $tp['username']) ?></a></b> <span class="hint">&middot; <?= e(ago((string) $tp['created_at'])) ?></span>
              <p style="margin:.3rem 0;white-space:pre-wrap"><?= nl2br(e(mb_strimwidth((string) $tp['body'], 0, 260, '…'))) ?></p>
              <?php $rn = (int) ($tp['reply_count'] ?? 0); ?>
              <a class="hint" href="<?= e(url('post/' . (int) $tp['id'])) ?>"><?= $rn ? $rn . ' ' . ($rn === 1 ? 'reply' : 'replies') : 'Answer it' ?></a>
            </div>
          <?php endforeach; ?>
          <?php $asked = trim((string) input('ask')); ?>
          <?php if ($me): ?>
            <form method="post" action="<?= e(url('post/new')) ?>">
              <?= csrf_field() ?><input type="hidden" name="_submit" value="<?= e(rmt_submit_token('post_new')) ?>">
              <input type="hidden" name="place_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="return" value="<?= e(url('p/' . $p['slug'])) ?>">
              <label class="sr-only" for="place_question">Ask about <?= e($p['name']) ?></label>
              <textarea id="place_question" name="body" rows="2" required maxlength="<?= RMT_POST_MAX ?>" placeholder="Ask about <?= e($p['name']) ?>: tickets, queues, whether it is worth it."><?= e(mb_substr($asked, 0, RMT_POST_MAX)) ?></textarea>
              <p style="margin:8px 0 0"><button class="btn btn-ghost btn-sm">Ask</button></p>
            </form>
          <?php elseif ($asked !== ''): ?>
            <?php $back = 'p/' . $p['slug'] . '?ask=' . rawurlencode(mb_substr($asked, 0, RMT_POST_MAX)); ?>
            <p class="hint" style="margin:0 0 6px">Your question</p>
            <p style="margin:0 0 12px;white-space:pre-wrap"><b><?= e(mb_strimwidth($asked, 0, 300, '…')) ?></b></p>
            <p style="margin:0"><a class="btn btn-primary btn-sm" href="<?= e(url('register?return=' . rawurlencode('/' . $back))) ?>">Join free and post it</a>
              <a class="hint" style="margin-left:10px" href="<?= e(url('login?return=' . rawurlencode('/' . $back))) ?>">or sign in</a></p>
          <?php else: ?>
            <form method="get" action="<?= e(url('p/' . $p['slug'])) ?>">
              <label class="sr-only" for="place_question">Ask about <?= e($p['name']) ?></label>
              <textarea id="place_question" name="ask" rows="2" required maxlength="<?= RMT_POST_MAX ?>" placeholder="Ask about <?= e($p['name']) ?>: tickets, queues, whether it is worth it."></textarea>
              <p style="margin:8px 0 0"><button class="btn btn-ghost btn-sm">Ask</button> <span class="hint" style="margin-left:6px">Free account, one minute. Travelers who have been answer it.</span></p>
            </form>
          <?php endif; ?>
        </section>
      </main>

      <aside class="pv2-side">
        <?php $hasFacts = rmt_place_has_address($p) || !empty($p['phone']) || !empty($p['website_url']) || $coords || $hoursByDay; ?>
        <?php if ($hasFacts): ?>
          <section class="pv2-card">
            <h2 class="pv2-h2">The basics</h2>
            <dl class="pv2-facts">
              <?php if (rmt_place_has_address($p)): ?><dt>Address</dt><dd><?= e(implode(', ', $address['lines'])) ?></dd><?php endif; ?>
              <?php if ($placeArea): ?><dt>Area</dt><dd><a href="<?= e(url('d/' . $placeArea['dest_slug'] . '/n/' . $placeArea['slug'])) ?>"><?= e((string) $placeArea['canonical_name']) ?></a></dd>
              <?php elseif (!empty($p['neighborhood'])): ?><dt>Area</dt><dd><?= e((string) $p['neighborhood']) ?></dd><?php endif; ?>
              <?php if (!empty($p['phone'])): ?><dt>Phone</dt><dd><a href="tel:<?= e(rmt_place_tel_href((string) $p['phone'])) ?>"><?= e((string) $p['phone']) ?></a></dd><?php endif; ?>
              <?php if (!empty($p['website_url'])): ?><dt>Website</dt><dd><a href="<?= e((string) $p['website_url']) ?>" rel="nofollow noopener" target="_blank"><?= e(parse_url((string) $p['website_url'], PHP_URL_HOST) ?: (string) $p['website_url']) ?></a></dd><?php endif; ?>
              <?php if ($priceLabel): ?><dt>Price</dt><dd><?= e($priceLabel) ?> <span class="hint"><?= e((string) rmt_place_price_title((int) $p['price_level'])) ?></span></dd><?php endif; ?>
            </dl>
            <?php if ($hoursByDay): ?>
              <h3 class="pv2-h3"><?= $trading ? 'Opening hours' : 'Hours it kept' ?></h3>
              <dl class="pv2-hours">
                <?php foreach ($hoursByDay as $d): $isToday = strcasecmp((string) $d['day'], $todayName) === 0; ?>
                  <dt<?= $isToday ? ' class="is-today"' : '' ?>><?= e($d['day']) ?></dt><dd<?= $isToday ? ' class="is-today"' : '' ?>><?= $d['closed'] ? 'Closed' : e(implode(', ', $d['intervals'])) ?></dd>
                <?php endforeach; ?>
              </dl>
            <?php endif; ?>
            <?php $rmt_prov = rmt_place_providers()[(string) ($p['data_source'] ?? '')] ?? null; ?>
            <?php if ($rmt_prov): ?>
              <p class="hint pv2-credit"><?= e((string) $rmt_prov['attribution']) ?>
                <?php if (!empty($p['data_source_url'])): ?><a href="<?= e((string) $p['data_source_url']) ?>" rel="nofollow noopener" target="_blank">This record</a>.<?php endif; ?>
                <a href="<?= e((string) $rmt_prov['url']) ?>" rel="nofollow noopener" target="_blank">Licence</a>.</p>
            <?php elseif ($src = rmt_place_source_line($p)): ?>
              <p class="hint pv2-credit"><?php if ($src['url']): ?><a href="<?= e($src['url']) ?>" rel="nofollow noopener" target="_blank"><?= e($src['text']) ?></a><?php else: ?><?= e($src['text']) ?><?php endif; ?></p>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($coords): ?>
          <section class="pv2-card pv2-map">
            <?php $mapPoints = [['lat' => (float) $coords[0], 'lng' => (float) $coords[1], 'label' => (string) $p['name'], 'href' => null,
                                 'meta' => $catName, 'group' => 'place']];
                  $mapId = 'place-map'; $mapTitle = null;
                  include __DIR__ . '/_map.php'; ?>
          </section>
        <?php endif; ?>

        <p class="hint pv2-correct"><a href="<?= e(url(ltrim($placePath, '/') . '/correct')) ?>">Suggest a correction</a>: closed, moved, wrong hours, anything. A person checks it.</p>

        <?php if ($me && in_array($me['role'], ['admin', 'mod'], true)): ?>
          <p class="hint"><a href="<?= e(url('admin/place/' . (int) $p['id'])) ?>">Edit this place</a></p>
        <?php endif; ?>
        <?php if ($me && $myLists): ?>
          <form method="post" action="<?= e(url('list/add')) ?>" class="pv2-card pv2-list">
            <?= csrf_field() ?><input type="hidden" name="place_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="return" value="<?= e($placePath) ?>">
            <label for="list_id">Add to one of your lists</label>
            <select id="list_id" name="list_id"><?php foreach ($myLists as $l): ?><option value="<?= (int) $l['id'] ?>"<?= (int) $l['has'] ? ' disabled' : '' ?>><?= e((string) $l['title']) ?><?= (int) $l['has'] ? ' (already on it)' : '' ?></option><?php endforeach; ?></select>
            <button class="btn btn-ghost btn-sm" style="margin-top:8px">Add to list</button>
          </form>
        <?php endif; ?>
      </aside>
    </div>

    <?php /* Planning the city: one block, after the facts. The traveler strip only takes room when it
             has something real to show; the travel map works for one person with nobody else here. */ ?>
    <section class="pv2-city">
      <div>
        <h2 class="pv2-h2">Planning <?= e($p['dest_name']) ?>?</h2>
        <p class="muted" style="margin:0 0 12px">Everything we hold on the city, the places travelers rate there, and who is going on your dates.</p>
        <div class="pv2-city-links">
          <a class="btn btn-primary btn-sm" href="<?= e(url('d/' . $p['dest_slug'])) ?>"><?= e($p['dest_name']) ?> guide</a>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('d/' . $p['dest_slug'] . '/places')) ?>">More places in <?= e($p['dest_name']) ?></a>
          <a class="btn btn-ghost btn-sm" data-cta="map_make" href="<?= e(url('map')) ?>">Color in your travel map</a>
        </div>
      </div>
      <?php if (!empty($p['dest_slug'])): ?>
        <div class="pv2-city-meet">
          <?php $destSlug = (string) $p['dest_slug']; $destName = (string) $p['dest_name']; $dsCompact = true;
                include __DIR__ . '/_meet_travelers.php'; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($nearby): ?>
      <section class="pv2-rail">
        <h2 class="pv2-h2"><?= $nearbyGeo ? 'Nearby' : 'Also in ' . e($p['dest_name']) ?></h2>
        <div class="pv2-rail-row">
          <?php foreach ($nearby as $n): ?>
            <a class="pv2-mini" href="<?= e(url('p/' . $n['slug'])) ?>">
              <span class="pv2-mini-t"><?= e(rmt_place_type_label((string) $n['type'])) ?><?php if (isset($n['distance_m'])): ?> &middot; <?= e(rmt_distance_label((int) $n['distance_m'])) ?><?php endif; ?></span>
              <b><?= e($n['name']) ?></b>
              <?php if (!empty($n['meta_description'])): ?><span class="pv2-mini-d"><?= e(mb_strimwidth((string) $n['meta_description'], 0, 90, '…')) ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($similar): ?>
      <section class="pv2-rail">
        <h2 class="pv2-h2"><?= e(rmt_similar_heading((string) $p['type'])) ?></h2>
        <div class="pv2-rail-row">
          <?php foreach ($similar as $sp):
            $why = [];
            if (!empty($sp['neighborhood'])) $why[] = (string) $sp['neighborhood'];
            if (($lbl = rmt_place_price_label(isset($sp['price_level']) && $sp['price_level'] !== null ? (int) $sp['price_level'] : null))) $why[] = $lbl;
            if (isset($sp['distance_m']) && $sp['distance_m'] !== null) $why[] = rmt_distance_label((int) $sp['distance_m']) . ' away'; ?>
            <a class="pv2-mini" href="<?= e(url('p/' . $sp['slug'])) ?>">
              <span class="pv2-mini-t"><?= e(rmt_place_type_label((string) $sp['type'])) ?></span>
              <b><?= e((string) $sp['name']) ?></b>
              <?php if ($why): ?><span class="pv2-mini-d"><?= e(implode(' · ', $why)) ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
    <p style="margin:0 0 50px"><a href="<?= e(url('d/' . $p['dest_slug'] . '/places')) ?>">All places in <?= e($p['dest_name']) ?> →</a></p>
  </div>
</div>
