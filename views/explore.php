<?php /** @var array $dests @var array $cats @var string $qs @var string $cat */ ?>
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
    <button class="btn btn-primary" type="submit">Search</button>
  </form>
  <?php if (!$dests): ?><p class="muted">No destinations match. Try a broader search.</p><?php endif; ?>
  <div class="grid g-3" style="padding-bottom:50px">
    <?php foreach ($dests as $d): ?>
      <article class="card"><a href="<?= e(url('d/'.$d['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e($d['hero_url']) ?>" alt="<?= e($d['name']) ?>">
        <div class="card-body">
          <span class="chip"><?= e($d['category']) ?></span>
          <?php if ((int)$d['editorial'] > 0): ?><?= rmt_editorial_badge('review') ?><?php endif; ?>
          <h3><?= e($d['name']) ?>, <?= e($d['country']) ?></h3>
          <p class="muted"><?= e($d['summary']) ?></p>
          <div class="meta-row">
            <?= (int)$d['reviews'] ?> traveler <?= (int)$d['reviews'] === 1 ? 'review' : 'reviews' ?>
            · <?= (int)$d['trips'] ?> <?= (int)$d['trips'] === 1 ? 'trip' : 'trips' ?>
          </div>
        </div></a></article>
    <?php endforeach; ?>
  </div>
</div>
