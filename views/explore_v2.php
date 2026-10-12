<?php
/**
 * The redesigned Explore page (app/design_v2.php, surface "explore"). Same URL, same filters and
 * sorts as views/explore.php (?q, ?category, ?sort), so no new URLs. Cities are on the first
 * screen; the first 24 show and the rest open in place, all in the HTML so every link is crawlable.
 *
 * @var array $dests @var array $cats @var string $qs @var string $cat @var string $sort
 * @var array $topTags @var array $countries
 */
$exFirst = 24;
$exQuery = static fn(array $over): string => url('explore' . (($q = http_build_query(array_filter(
    array_merge(['q' => $qs, 'category' => $cat, 'sort' => $sort === 'name' ? '' : $sort], $over),
    static fn($v) => $v !== '' && $v !== null))) !== '' ? '?' . $q : ''));
?>
<div class="wrap v2-explore">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Explore</p>
  <div class="v2-ex-head">
    <h1 class="v2-h1 v2-h1-page">Explore cities</h1>
    <p class="v2-sub"><?= count($dests) ?> <?= count($dests) === 1 ? 'city' : 'cities' ?><?= $cat !== '' ? ' for ' . e(ucfirst($cat)) : '' ?>, each with places, hours, addresses and honest reviews.</p>
  </div>

  <form class="v2-ex-tools" action="<?= e(url('explore')) ?>" method="get" role="search">
    <div class="v2-ex-search">
      <span class="v2-search-ico" aria-hidden="true">⌕</span>
      <input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search cities" aria-label="Search a city or country">
    </div>
    <?php if ($cat !== ''): ?><input type="hidden" name="category" value="<?= e($cat) ?>"><?php endif; ?>
    <label class="v2-ex-sort"><span class="sr-only">Sort</span>
      <select name="sort" aria-label="Sort by" onchange="this.form.submit()">
        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>A to Z</option>
        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest rated</option>
        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most wanted</option>
      </select>
    </label>
  </form>

  <div class="v2-chips" role="list">
    <a role="listitem" class="v2-chip<?= $cat === '' ? ' on' : '' ?>" href="<?= e($exQuery(['category' => ''])) ?>">All</a>
    <?php foreach ($cats as $c): ?>
      <a role="listitem" class="v2-chip<?= $cat === $c['category'] ? ' on' : '' ?>" href="<?= e($exQuery(['category' => $c['category']])) ?>"><?= e(ucfirst((string) $c['category'])) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($sort === 'rating'): ?>
    <?php $hasTravelerRating = false; foreach ($dests as $dx) { if ($dx['avg_rating'] !== null) { $hasTravelerRating = true; break; } } ?>
    <?php if (!$hasTravelerRating): ?>
      <p class="v2-note">No traveler ratings yet, so this list is still A to Z. Our own reviews never fill the ranking.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!$dests): ?><p class="v2-note">No cities match. Try a broader search.</p><?php endif; ?>
  <div class="v2-dest-grid" id="v2-dests">
    <?php foreach ($dests as $d): $dPlaces = (int) ($d['places'] ?? 0); $dRev = (int) $d['reviews']; ?>
      <article class="v2-dest">
        <a class="v2-dest-link" href="<?= e(url('d/' . $d['slug'])) ?>" data-cta="explore_city">
          <span class="v2-dest-img"><img loading="lazy" src="<?= e($d['hero_url']) ?>" alt="" onerror="this.remove()"></span>
          <span class="v2-dest-body">
            <b class="v2-dest-name"><?= e($d['name']) ?></b>
            <span class="v2-dest-country"><?= e($d['country']) ?></span>
            <span class="v2-dest-meta">
              <?php if ($dPlaces > 0): ?><span><?= $dPlaces ?> <?= $dPlaces === 1 ? 'place' : 'places' ?></span><?php endif; ?>
              <?php if ($dRev > 0): ?><span>★ <?= number_format((float) $d['avg_rating'], 1) ?> · <?= $dRev ?> <?= $dRev === 1 ? 'review' : 'reviews' ?></span><?php endif; ?>
              <span class="v2-dest-cat<?= $dPlaces === 0 && $dRev === 0 ? ' v2-dest-cat-only' : '' ?>"><?= e(ucfirst((string) $d['category'])) ?></span>
            </span>
          </span>
        </a>
        <a class="v2-dest-people" href="<?= e(url('d/' . $d['slug'] . '/travelers')) ?>">Travelers →</a>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if (count($dests) > $exFirst): ?>
    <p class="v2-more-row"><button type="button" class="v2-btn v2-btn-ghost" data-v2-more
      onclick="document.getElementById('v2-dests').classList.add('all');this.parentNode.remove()">Show all <?= count($dests) ?> cities</button></p>
  <?php endif; ?>

  <?php if (!empty($countries)): ?>
    <section class="v2-ex-more">
      <h2 class="v2-h3">Browse by country</h2>
      <div class="v2-chips v2-chips-wrap">
        <?php foreach ($countries as $co): ?>
          <a class="v2-chip v2-chip-sm" href="<?= e(url(rmt_country_path((string) $co['country']))) ?>"><?= e($co['country']) ?> <span><?= (int) $co['n'] ?></span></a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
  <?php if (!empty($topTags)): ?>
    <section class="v2-ex-more">
      <h2 class="v2-h3">Topics travelers write about</h2>
      <div class="v2-chips v2-chips-wrap">
        <?php foreach ($topTags as $t): ?>
          <a class="v2-chip v2-chip-sm" href="<?= e(url('tag/' . $t['name'])) ?>">#<?= e($t['name']) ?></a>
        <?php endforeach; ?>
        <a class="v2-chip v2-chip-sm" href="<?= e(url('tags')) ?>">All topics →</a>
      </div>
    </section>
  <?php endif; ?>
  <p class="v2-note" style="margin-top:28px">Travelling for something in particular? <a href="<?= e(url('events')) ?>">See the upcoming events</a> and who else is going. Every city also carries our own researched <a href="<?= e(url('editorial-policy')) ?>">editorial review</a>, labelled as ours.</p>
</div>
