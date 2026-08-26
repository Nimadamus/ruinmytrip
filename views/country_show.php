<?php /** @var string $country @var string $slug @var array $dests @var array $guides */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> / <?= e($country) ?></p>
  <h1><?= e($country) ?> in 2026</h1>
  <p class="muted"><?= count($dests) ?> <?= count($dests)===1?'destination':'destinations' ?> with current costs, tickets, taxes and the part that nearly ruins the trip.</p>

  <div class="grid g-3" style="padding:18px 0 40px">
    <?php foreach ($dests as $d): ?>
      <article class="card"><a href="<?= e(url('d/'.$d['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e(abs_url($d['hero_url'])) ?>" alt="<?= e($d['name']) ?>">
        <div class="card-body">
          <?php if ($d['category']): ?><span class="chip"><?= e($d['category']) ?></span><?php endif; ?>
          <h3><?= e($d['name']) ?></h3>
          <p class="muted"><?= e(mb_strimwidth((string)$d['summary'],0,140,'…')) ?></p>
        </div></a></article>
    <?php endforeach; ?>
  </div>

  <?php if ($guides): ?>
    <h2>Guides</h2>
    <div class="grid g-3" style="padding:8px 0 50px">
      <?php foreach ($guides as $g): ?>
        <article class="card"><a href="<?= e(url('g/'.$g['slug'])) ?>">
          <?php if ($g['cover_url']): ?><img class="card-media" loading="lazy" src="<?= e(abs_url($g['cover_url'])) ?>" alt="<?= e($g['title']) ?>"><?php endif; ?>
          <div class="card-body">
            <?php if ($g['dest_name']): ?><span class="chip"><?= e($g['dest_name']) ?></span><?php endif; ?>
            <?php if (rmt_is_editorial($g)): ?><?= rmt_editorial_badge('editorial', false) ?><?php endif; ?>
            <h3><?= e($g['title']) ?></h3>
          </div></a></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
