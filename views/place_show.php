<?php /** @var array $p @var array $stats @var array $breakdown @var array $aspectAverages @var array $reviews @var array $editorial @var array $photos @var int $photoCount @var ?array $me @var string $typeLabel @var ?array $ed @var array $nearby @var bool $saved @var int $saveCount @var array $hours @var array $hoursByDay @var ?bool $openNow @var array $address @var ?array $coords @var ?array $category @var ?string $priceLabel @var ?string $cover */ ?>
<div class="wrap">
  <p class="crumbs">
    <a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> /
    <a href="<?= e(url('d/'.$p['dest_slug'])) ?>"><?= e($p['dest_name']) ?></a> /
    <a href="<?= e(url('d/'.$p['dest_slug'].'/places')) ?>">Places</a>
  </p>
  <?php /* The cover is the place's own picture or nothing. The destination hero is a photo of the
           city, and standing it in here would tell the reader it is a photo of this place. */ ?>
  <?php if ($cover): ?>
    <img class="card-media" src="<?= e(abs_url($cover)) ?>" alt="<?= e($p['name']) ?>"
         style="width:100%;aspect-ratio:16/7;object-fit:cover;border-radius:12px;margin:10px 0 14px">
  <?php endif; ?>

  <?php /* Every part of this line is dropped when we do not hold it, so a separator never ends up
           orphaned next to a blank. */ ?>
  <p class="eyebrow" style="margin-top:6px"><?= e(implode(' · ', array_filter([
        $category['name'] ?? $typeLabel,
        trim($p['dest_name'] . ', ' . $p['dest_country'], ', '),
        $priceLabel,
        $openNow === null ? null : ($openNow ? 'Open now' : 'Closed now'),
      ]))) ?></p>
  <h1 style="margin:.2rem 0 .5rem"><?= e($p['name']) ?></h1>

  <?php if ($stats['c'] > 0): ?>
    <div class="meta-row" style="gap:12px;align-items:center;margin-bottom:6px">
      <span class="stars" style="font-size:1.25rem"><?= stars((int) round((float)$stats['a'])) ?></span>
      <strong style="font-size:1.15rem"><?= e((string)$stats['a']) ?>/5</strong>
      <span class="muted">from <?= (int)$stats['c'] ?> traveler <?= $stats['c'] === 1 ? 'review' : 'reviews' ?></span>
    </div>
    <?php /* Safety and value used to be printed here as their own line. They are aspects now and
             appear in the breakdown below with every other one, under the same minimum-sample rule.
             Keeping both would have shown Value twice and shown Safety off two ratings in one place
             while the threshold correctly withheld it in the other. */ ?>

    <div style="max-width:420px;margin:0 0 22px">
      <?php foreach ([5,4,3,2,1] as $n): $c = (int)$breakdown[$n]; $pct = $stats['c'] > 0 ? round($c * 100 / $stats['c']) : 0; ?>
        <div style="display:flex;align-items:center;gap:10px;margin:3px 0">
          <span class="muted" style="width:2.4rem;font-size:.9rem"><?= $n ?> ★</span>
          <span style="flex:1;height:8px;background:#e9e9ee;border-radius:99px;overflow:hidden">
            <span style="display:block;height:100%;width:<?= $pct ?>%;background:var(--ink)"></span>
          </span>
          <span class="muted" style="width:2rem;text-align:right;font-size:.9rem"><?= $c ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="muted" style="margin:0 0 18px">
      No published traveler reviews yet, and we will not invent one to fill the space.
    </p>
  <?php endif; ?>

  <?php /* What travelers rated, aspect by aspect. Only aspects at least RMT_ASPECT_MIN_SAMPLE
           people scored appear: one person's "Service 5.0" is a person, not a consensus, and
           printing it as a community figure is the same borrowed credibility as inventing a
           review. Every rating is still stored; the threshold governs display only. */ ?>
  <?php if (!empty($aspectAverages)): ?>
    <div style="max-width:420px;margin:0 0 22px">
      <?php foreach ($aspectAverages as $a): $pct = round($a['avg'] * 20); ?>
        <div style="display:flex;align-items:center;gap:10px;margin:5px 0">
          <span class="muted" style="width:7.5rem;font-size:.9rem"><?= e($a['label']) ?></span>
          <span style="flex:1;height:8px;background:#e9e9ee;border-radius:99px;overflow:hidden">
            <span style="display:block;height:100%;width:<?= $pct ?>%;background:var(--ink)"></span>
          </span>
          <strong style="width:2.2rem;text-align:right;font-size:.9rem"><?= e(number_format($a['avg'], 1)) ?></strong>
        </div>
      <?php endforeach; ?>
      <p class="hint" style="margin:8px 0 0">Shown once at least <?= RMT_ASPECT_MIN_SAMPLE ?> travelers have rated it.</p>
    </div>
  <?php endif; ?>

  <?php /* Actions wrap on a narrow screen instead of overflowing, and the save control is a real
           form (a POST toggle), which cannot live inside a <p>. A logged-out visitor still sees the
           button: it links to sign-in carrying this page as the return, so the intent survives the
           detour instead of the control simply being missing. */ ?>
  <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 <?= $saveCount > 0 ? '8px' : '26px' ?>">
    <a class="btn btn-accent" href="<?= e(url('review/new?place='.(int)$p['id'])) ?>">Write a review</a>
    <?php if ($me): ?>
      <form method="post" action="<?= e(url('place/save')) ?>" style="margin:0">
        <?= csrf_field() ?>
        <input type="hidden" name="place_id" value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="return" value="<?= e(rmt_place_path($p)) ?>">
        <button class="btn <?= $saved ? 'btn-primary' : 'btn-ghost' ?>"
                aria-pressed="<?= $saved ? 'true' : 'false' ?>">
          <?= $saved ? '★ Saved' : '☆ Save' ?>
        </button>
      </form>
    <?php else: ?>
      <a class="btn btn-ghost" href="<?= e(url('login?return=' . rawurlencode(rmt_place_path($p)))) ?>">☆ Save</a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(url('d/'.$p['dest_slug'].'/places')) ?>">More in <?= e($p['dest_name']) ?></a>
    <?php /* Editors get a direct route into the place editor from the page they are looking at. */ ?>
    <?php if ($me && in_array($me['role'], ['admin','mod'], true)): ?>
      <a class="btn btn-ghost" href="<?= e(url('admin/place/'.(int)$p['id'])) ?>">Edit this place</a>
    <?php endif; ?>
  </div>
  <?php /* Zero is not announced. "0 travelers saved this" is a fact about nobody caring and it is
           the first thing a new page would say about itself. */ ?>
  <?php if ($saveCount > 0): ?>
    <p class="hint" style="margin:0 0 26px"><?= $saveCount ?> <?= $saveCount === 1 ? 'traveler has' : 'travelers have' ?> saved this</p>
  <?php endif; ?>

  <?php /* Practical detail. The whole card is skipped when we hold none of it, and each row is
           skipped on its own, so a place we only know the name of shows no empty scaffolding.
           Nothing here is inferred: an address we do not have is an address we do not print. */ ?>
  <?php $hasFacts = rmt_place_has_address($p) || !empty($p['phone']) || !empty($p['website_url']) || $coords || $hoursByDay; ?>
  <?php if ($hasFacts): ?>
    <section class="card" style="margin:0 0 26px"><div class="card-body">
      <h2 style="margin:0 0 10px;font-size:1.05rem">The basics</h2>
      <dl style="display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:0;font-size:.95rem">
        <?php if (rmt_place_has_address($p)): ?>
          <dt class="muted">Address</dt>
          <dd style="margin:0"><?= e(implode(', ', $address['lines'])) ?></dd>
        <?php endif; ?>
        <?php if (!empty($p['neighborhood'])): ?>
          <dt class="muted">Neighborhood</dt>
          <dd style="margin:0"><?= e((string) $p['neighborhood']) ?></dd>
        <?php endif; ?>
        <?php if (!empty($p['phone'])): ?>
          <dt class="muted">Phone</dt>
          <dd style="margin:0"><a href="tel:<?= e(rmt_place_tel_href((string) $p['phone'])) ?>"><?= e((string) $p['phone']) ?></a></dd>
        <?php endif; ?>
        <?php if (!empty($p['website_url'])): ?>
          <dt class="muted">Website</dt>
          <dd style="margin:0"><a href="<?= e((string) $p['website_url']) ?>" rel="nofollow noopener" target="_blank"><?= e(parse_url((string) $p['website_url'], PHP_URL_HOST) ?: (string) $p['website_url']) ?></a></dd>
        <?php endif; ?>
        <?php if ($priceLabel): ?>
          <dt class="muted">Price</dt>
          <dd style="margin:0"><?= e($priceLabel) ?> <span class="hint"><?= e((string) rmt_place_price_title((int) $p['price_level'])) ?></span></dd>
        <?php endif; ?>
        <?php if ($coords): ?>
          <?php /* A link, not an embedded map: a third-party iframe on every place page costs more
                   load time than a map nobody asked to open is worth. */ ?>
          <dt class="muted">Map</dt>
          <dd style="margin:0"><a href="<?= e(rmt_place_map_url($coords[0], $coords[1])) ?>" rel="nofollow noopener" target="_blank">Open in maps</a></dd>
        <?php endif; ?>
      </dl>

      <?php if ($hoursByDay): ?>
        <?php /* Only days we hold are listed. A missing day is left out rather than printed as
                 "Closed", which would assert something we were never told. */ ?>
        <h3 style="margin:16px 0 6px;font-size:.98rem">Opening hours</h3>
        <dl style="display:grid;grid-template-columns:auto 1fr;gap:4px 14px;margin:0;font-size:.93rem">
          <?php foreach ($hoursByDay as $d): ?>
            <dt class="muted"><?= e($d['day']) ?></dt>
            <dd style="margin:0"><?= $d['closed'] ? 'Closed' : e(implode(', ', $d['intervals'])) ?></dd>
          <?php endforeach; ?>
        </dl>
        <?php if (!empty($p['data_source_url'])): ?>
          <p class="hint" style="margin:8px 0 0">Hours from
            <a href="<?= e((string) $p['data_source_url']) ?>" rel="nofollow noopener" target="_blank">the venue</a><?php
            if (!empty($p['data_checked_at'])): ?>, checked <?= e(substr((string) $p['data_checked_at'], 0, 10)) ?><?php endif; ?>.
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </div></section>
  <?php endif; ?>

  <?php /* Structured editorial. Only sections with content render, so a page never pads itself with
           headings that say nothing. Every claim here is sourced; the list is printed at the end. */ ?>
  <?php if ($ed): ?>
    <div class="card" style="margin:0 0 26px"><div class="card-body">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
        <h2 style="margin:0;font-size:1.15rem">RuinMyTrip guide to <?= e($p['name']) ?></h2>
        <?= rmt_editorial_badge('review') ?>
      </div>
      <p class="hint" style="margin:.5rem 0 0"><?= e(rmt_editorial_disclosure()) ?></p>
    </div></div>

    <?php foreach (rmt_place_editorial_sections((string) $p['type']) as $col => $heading): ?>
      <?php $val = trim((string) ($ed[$col] ?? '')); if ($val === '') continue; ?>
      <section style="margin:0 0 22px">
        <h2 style="font-size:1.05rem;margin:0 0 6px"><?= e($heading) ?></h2>
        <?php foreach (preg_split('/\n\s*\n/', $val) ?: [] as $para): ?>
          <?php $para = trim($para); if ($para === '') continue; ?>
          <p style="margin:0 0 .6rem"><?= e($para) ?></p>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>

    <?php if (!empty($ed['sources'])): ?>
      <section style="margin:0 0 26px">
        <h2 style="font-size:1.05rem;margin:0 0 6px">Sources</h2>
        <p class="hint" style="margin:0 0 .5rem">Every figure above was checked against these before publication. If one has since changed, tell us and we will correct it.</p>
        <ul class="list-plain" style="margin:0">
          <?php foreach ($ed['sources'] as $s): ?>
            <li style="padding:4px 0;font-size:.92rem">
              <?= e((string) ($s['fact'] ?? '')) ?>
              <?php if (!empty($s['url'])): ?>
                <a href="<?= e((string) $s['url']) ?>" rel="nofollow noopener" target="_blank">source</a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* Photos of the place and photos travelers attached to reviews of it, in one gallery. A
           review photo links to the review it belongs to; a place photo has nowhere to go and is
           rendered as a plain image rather than a link to nothing. */ ?>
  <?php if ($photos): ?>
    <h2 style="font-size:1.1rem;margin:0 0 10px"><?= count($photos) === 1 ? 'Photo' : 'Photos' ?></h2>
    <div class="grid g-4" style="margin-bottom:<?= $photoCount > count($photos) ? '10px' : '28px' ?>">
      <?php foreach ($photos as $ph): ?>
        <?php
          $alt = $ph['alt_text'] ?: ($ph['caption'] ?: ($ph['kind'] === 'review'
                 ? $p['name'] . ' photo by @' . ($ph['author']['username'] ?? '')
                 : $p['name']));
        ?>
        <?php if ($ph['kind'] === 'review' && $ph['parent_id']): ?>
          <a href="<?= e(url('review/'.$ph['parent_id'].($ph['parent_slug'] ? '/'.$ph['parent_slug'] : ''))) ?>" title="<?= e((string) ($ph['caption'] ?? '')) ?>">
            <img class="card-media" loading="lazy" style="aspect-ratio:1;object-fit:cover"
                 src="<?= e(abs_url($ph['url'])) ?>" alt="<?= e($alt) ?>">
          </a>
        <?php else: ?>
          <img class="card-media" loading="lazy" style="aspect-ratio:1;object-fit:cover"
               src="<?= e(abs_url($ph['url'])) ?>" alt="<?= e($alt) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php if ($photoCount > count($photos)): ?>
      <p class="hint" style="margin:0 0 28px"><?= (int) $photoCount ?> photos in total.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($editorial): ?>
    <h2 style="font-size:1.1rem;margin:0 0 10px">From the RuinMyTrip team</h2>
    <p class="hint" style="margin:-4px 0 12px"><?= e(rmt_editorial_disclosure()) ?></p>
    <div class="grid" style="gap:14px;margin-bottom:28px">
      <?php foreach ($editorial as $r): ?>
        <?php $href = url('review/'.(int)$r['id'].'/'.($r['slug'] ?: rmt_review_slug($r))); ?>
        <article class="card"><div class="card-body">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
            <span class="stars"><?= stars((int)$r['rating']) ?></span>
            <?= rmt_editorial_badge('review') ?>
          </div>
          <h3 style="margin:.35rem 0 .2rem;font-size:1.05rem"><a href="<?= e($href) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a></h3>
          <p style="margin:.4rem 0 0"><?= e(mb_strimwidth((string)$r['body'], 0, 200, '…')) ?></p>
        </div></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2 style="font-size:1.1rem;margin:0 0 10px">
    <?= $stats['c'] > 0 ? 'What travelers said' : 'Traveler reviews' ?>
  </h2>

  <?php if (!$reviews): ?>
    <div class="empty-cta" style="margin-bottom:50px">
      <h3>Be the first to review <?= e($p['name']) ?>.</h3>
      <p class="muted" style="margin:0">The bad parts are the useful parts. Say what it actually cost and what you wish you had known.</p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url('review/new?place='.(int)$p['id'])) ?>">Share your experience</a></p>
    </div>
  <?php endif; ?>

  <div class="grid" style="gap:14px;padding-bottom:50px">
    <?php foreach ($reviews as $r): ?>
      <?php $href = url('review/'.(int)$r['id'].'/'.($r['slug'] ?: rmt_review_slug($r))); ?>
      <article class="card"><div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
          <span class="stars"><?= stars((int)$r['rating']) ?></span>
          <?php if (show_verified($r)): ?><span class="verified">Verified</span><?php endif; ?>
        </div>
        <h3 style="margin:.35rem 0 .2rem;font-size:1.05rem"><a href="<?= e($href) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a></h3>
        <p style="margin:.4rem 0 0"><?= e(mb_strimwidth((string)$r['body'], 0, 200, '…')) ?></p>
        <div class="meta-row" style="justify-content:space-between">
          <span>@<?= e($r['author']['username'] ?? 'traveler') ?> · <?= e(ago((string)$r['created_at'])) ?><?php if (!empty($r['useful_count'])): ?> · 👍 <?= (int)$r['useful_count'] ?> found this useful<?php endif; ?><?php if (rmt_review_is_stale($r)): ?> · <span class="hint">⏳ may be outdated</span><?php endif; ?></span>
          <?php if (rmt_review_can_edit($r, $me)): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('review/'.(int)$r['id'].'/edit')) ?>">Edit</a>
          <?php endif; ?>
        </div>
      </div></article>
    <?php endforeach; ?>
  </div>

  <?php /* Internal links to sibling attractions, but only ones with editorial behind them, so this
           can never become a ring of empty pages pointing at each other. */ ?>
  <?php if ($nearby): ?>
    <section style="margin:0 0 50px">
      <h2 style="font-size:1.1rem;margin:0 0 10px">Also in <?= e($p['dest_name']) ?></h2>
      <div class="grid" style="gap:12px">
        <?php foreach ($nearby as $n): ?>
          <article class="card"><div class="card-body">
            <span class="eyebrow" style="text-transform:capitalize"><?= e(rmt_place_type_label((string)$n['type'])) ?></span>
            <h3 style="margin:.25rem 0 .2rem;font-size:1.02rem">
              <a href="<?= e(url('p/'.$n['slug'])) ?>"><?= e($n['name']) ?></a>
            </h3>
            <?php if (!empty($n['meta_description'])): ?>
              <p class="muted" style="margin:0;font-size:.93rem"><?= e((string)$n['meta_description']) ?></p>
            <?php endif; ?>
          </div></article>
        <?php endforeach; ?>
      </div>
      <p style="margin:14px 0 0"><a href="<?= e(url('d/'.$p['dest_slug'].'/places')) ?>">All reviewed places in <?= e($p['dest_name']) ?> →</a></p>
    </section>
  <?php endif; ?>
</div>
