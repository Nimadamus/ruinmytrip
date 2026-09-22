<?php /** @var string $country @var string $slug @var array $dests */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> / <?= e($country) ?></p>
  <h1><?= e($country) ?></h1>
  <p class="muted"><?= count($dests) ?> <?= count($dests)===1?'destination':'destinations' ?> on RuinMyTrip. See who is going, and find travel buddies on your dates.</p>
  <?php if ($bl = rmt_buddy_landing_for_country($country)): ?><p><a class="btn btn-accent btn-sm" href="<?= e(url(rmt_buddy_landing_path($bl['slug']))) ?>">Travel buddies in <?= e($bl['name']) ?></a></p><?php endif; ?>

  <div class="grid g-3" style="padding:18px 0 40px">
    <?php foreach ($dests as $d): ?>
      <article class="card"><a href="<?= e(url('d/'.$d['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e(abs_url($d['hero_url'])) ?>" alt="<?= e($d['name']) ?>">
        <div class="card-body">
          <?php if ($d['category']): ?><span class="chip chip-cap"><?= e($d['category']) ?></span><?php endif; ?>
          <h3><?= e($d['name']) ?></h3>
          <p class="muted"><?= e(mb_strimwidth((string)$d['summary'],0,140,'…')) ?></p>
        </div></a></article>
    <?php endforeach; ?>
  </div>

</div>
