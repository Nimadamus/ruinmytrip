<?php /** @var array $dests @var array $cats @var string $qs @var string $cat @var string $sort */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Explore</p>
  <h1>Explore destinations</h1>
  <p class="muted">Every destination here carries a researched <a href="<?= e(url('editorial-policy')) ?>">editorial review</a> and practical tips. The review counts below are travelers only, so they read zero until real people post.</p>
  <form action="<?= e(url('explore')) ?>" method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 30px">
    <input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search a city or country" style="flex:1;min-width:220px">
    <select name="category" onchange="this.form.submit()">
      <option value="">All styles</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= e($c['category']) ?>" <?= $cat===$c['category']?'selected':'' ?>><?= e(ucfirst($c['category'])) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="sort" onchange="this.form.submit()">
      <option value="name" <?= $sort==='name'?'selected':'' ?>>A to Z</option>
      <option value="rating" <?= $sort==='rating'?'selected':'' ?>>Highest rated</option>
      <option value="popular" <?= $sort==='popular'?'selected':'' ?>>Most wanted</option>
    </select>
    <button class="btn btn-primary" type="submit">Search</button>
  </form>
  <?php if ($sort === 'rating'): ?>
    <?php $hasTravelerRating = false; foreach ($dests as $dx) { if ($dx['avg_rating'] !== null) { $hasTravelerRating = true; break; } } ?>
    <?php if (!$hasTravelerRating): ?>
      <p class="callout" style="margin:-10px 0 24px">No traveler ratings yet, so this list is still A to Z. Official reviews never fill the ranking.</p>
    <?php endif; ?>
  <?php endif; ?>
  <?php if (!empty($topTags)): ?>
    <div class="tag-row" style="margin:-14px 0 24px">
      <?php foreach ($topTags as $t): ?>
        <a class="chip" href="<?= e(url('tag/'.$t['name'])) ?>">#<?= e($t['name']) ?></a>
      <?php endforeach; ?>
      <a class="chip" href="<?= e(url('tags')) ?>">All topics →</a>
    </div>
  <?php endif; ?>
  <?php if (!$dests): ?><p class="muted">No destinations match. Try a broader search.</p><?php endif; ?>
  <div class="grid g-3" style="padding-bottom:50px">
    <?php foreach ($dests as $d): ?>
      <article class="card"><a href="<?= e(url('d/'.$d['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e($d['hero_url']) ?>" alt="<?= e($d['name']) ?>">
        <div class="card-body">
          <span class="chip"><?= e($d['category']) ?></span>
          <?php if ((int)$d['editorial'] > 0): ?><?= rmt_editorial_badge('review', false) ?><?php endif; ?>
          <?php if ($d['avg_rating'] !== null): ?><span class="stars" style="font-size:.85rem"><?= stars((int)round((float)$d['avg_rating'])) ?></span><?php endif; ?>
          <h3><?= e($d['name']) ?>, <?= e($d['country']) ?></h3>
          <p class="muted"><?= e($d['summary']) ?></p>
          <div class="meta-row">
            <?= (int)$d['reviews'] ?> traveler <?= (int)$d['reviews'] === 1 ? 'review' : 'reviews' ?>
            · <?= (int)$d['trips'] ?> <?= (int)$d['trips'] === 1 ? 'trip' : 'trips' ?>
            <?php if ((int)$d['wants'] > 0): ?> · ★ <?= (int)$d['wants'] ?> want to visit<?php endif; ?>
          </div>
        </div></a></article>
    <?php endforeach; ?>
  </div>
</div>
