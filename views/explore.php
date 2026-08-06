<?php
/**
 * The destination directory.
 *
 * Reframed for the risk product: a destination card now leads with the risk read and the number
 * of traveler warnings, and the older review/trip counts are kept but demoted to a second line.
 *
 * Deliberately NOT paginated. This is a directory — the whole point is being able to scan or
 * Ctrl-F the full A–Z list — and at the current size it is one 78KB page whose 85 images are all
 * lazy-loaded below the fold. Revisit if the destination count passes roughly 250, at which point
 * the correlated subqueries in explore() are the thing to fix first, not the page length.
 *
 * @var array $dests @var array $cats @var string $qs @var string $cat @var string $sort
 * @var array $topTags @var array $topCats @var int $covered
 */
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Destinations</p>
  <h1>All destinations</h1>
  <p class="muted" style="max-width:70ch">
    <?= count($dests) ?> destinations<?php if ($covered > 0): ?>, <?= (int) $covered ?> with a full researched
    risk report<?php endif; ?>. A risk report covers the scams, hidden costs, transport traps, closures,
    crowding and seasonal problems that ruin trips there — with sources and the date it was last reviewed.
    Traveler warning counts are first-hand reports from members, so they read zero until real people file them.
  </p>

  <form action="<?= e(url('explore')) ?>" method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 24px">
    <input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search a city or country" style="flex:1;min-width:220px">
    <select name="category" onchange="this.form.submit()" aria-label="Travel style">
      <option value="">All styles</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= e($c['category']) ?>" <?= $cat === $c['category'] ? 'selected' : '' ?>><?= e(ucfirst($c['category'])) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="sort" onchange="this.form.submit()" aria-label="Sort by">
      <option value="name"     <?= $sort === 'name'     ? 'selected' : '' ?>>A to Z</option>
      <option value="risk"     <?= $sort === 'risk'     ? 'selected' : '' ?>>Highest trip risk</option>
      <option value="warnings" <?= $sort === 'warnings' ? 'selected' : '' ?>>Most traveler warnings</option>
      <option value="covered"  <?= $sort === 'covered'  ? 'selected' : '' ?>>Most complete report</option>
      <option value="rating"   <?= $sort === 'rating'   ? 'selected' : '' ?>>Highest rated</option>
      <option value="popular"  <?= $sort === 'popular'  ? 'selected' : '' ?>>Most saved</option>
    </select>
    <button class="btn btn-primary" type="submit">Search</button>
  </form>

  <?php if (!empty($topTags)): ?>
    <div class="tag-row" style="margin:-10px 0 24px">
      <?php foreach ($topTags as $t): ?>
        <a class="chip" href="<?= e(url('tag/' . $t['name'])) ?>">#<?= e($t['name']) ?></a>
      <?php endforeach; ?>
      <a class="chip" href="<?= e(url('tags')) ?>">All topics →</a>
    </div>
  <?php endif; ?>

  <?php if (!$dests): ?>
    <div class="empty-cta">
      <h3>No destinations match</h3>
      <p class="muted">Try a broader search, or browse <a href="<?= e(url('warnings')) ?>">every warning</a> instead.</p>
    </div>
  <?php endif; ?>

  <div class="grid g-3" style="padding-bottom:50px">
    <?php foreach ($dests as $d): $id = (int) $d['id']; $risk = (int) ($d['risk_level'] ?? 0); $wc = (int) $d['warnings']; ?>
      <article class="card">
        <a href="<?= e(url('d/' . $d['slug'])) ?>">
          <?php /* Explicit dimensions as well as the CSS aspect-ratio: the ratio reserves the box in
                   modern browsers, the attributes do it everywhere else, and this page renders 85 of
                   them — the one place on the site where getting it wrong is visible as layout shift. */ ?>
          <img class="card-media" loading="lazy" decoding="async" width="380" height="238"
               src="<?= e($d['hero_url'] ?: url('assets/img/og-default.svg')) ?>"
               alt="<?= e($d['name'] . ', ' . $d['country']) ?>">
          <div class="card-body">
            <?php if (!empty($d['category'])): ?><span class="chip"><?= e($d['category']) ?></span><?php endif; ?>
            <?php if ((int) $d['sections'] > 0): ?><span class="chip chip-cat">Risk report</span><?php endif; ?>
            <h3><?= e($d['name']) ?>, <?= e($d['country']) ?></h3>

            <?php if ($risk): ?>
              <p style="margin:.1rem 0 .5rem;font-size:.85rem" class="risk-<?= $risk ?>">
                <span class="risk-meter r<?= $risk ?>"><i></i><i></i><i></i><i></i></span>
                <b style="margin-left:6px"><?= e(rmt_risk_level_label($risk)) ?></b>
              </p>
            <?php endif; ?>

            <p class="muted" style="font-size:.9rem"><?= e(mb_strimwidth(strip_tags((string) ($d['risk_summary'] ?: $d['summary'])), 0, 140, '…')) ?></p>

            <?php if (!empty($topCats[$id])): ?>
              <div class="tag-row" style="margin:.5rem 0 0">
                <?php foreach ($topCats[$id] as $ck): ?>
                  <span class="chip chip-cat"><?= rmt_warning_category_icon($ck) ?> <?= e(rmt_warning_category_label($ck)) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="meta-row">
              <?php if ($wc > 0): ?>
                <b><?= number_format($wc) ?></b> traveler <?= $wc === 1 ? 'warning' : 'warnings' ?>
              <?php elseif ((int) $d['sections'] > 0): ?>
                <span>Researched report · no traveler reports yet</span>
              <?php else: ?>
                <span>No report yet — be the first to warn</span>
              <?php endif; ?>
              <?php if ((int) $d['reviews'] > 0): ?> · <?= (int) $d['reviews'] ?> <?= (int) $d['reviews'] === 1 ? 'review' : 'reviews' ?><?php endif; ?>
              <?php if ((int) $d['trips'] > 0): ?> · <?= (int) $d['trips'] ?> <?= (int) $d['trips'] === 1 ? 'trip' : 'trips' ?><?php endif; ?>
            </div>
          </div>
        </a>
      </article>
    <?php endforeach; ?>
  </div>
</div>
