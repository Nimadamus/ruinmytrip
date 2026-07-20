<?php /** @var array $guides */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Guides</p>
  <h1>Travel guides & itineraries</h1>
  <p class="muted">Detailed, practical plans you can actually follow. Guides marked <?= rmt_editorial_badge() ?> are researched and written by our own team; the rest come from travelers.</p>
  <?php if (!$guides): ?>
    <div class="empty-cta" style="margin-bottom:24px">
      <h3>No guides published yet.</h3>
      <p class="muted" style="margin:0">Know a place well enough to plan somebody else's week there? That is a guide.</p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url('review/new')) ?>">Share your experience</a></p>
    </div>
  <?php endif; ?>
  <div class="grid g-3" style="padding:20px 0 50px">
    <?php foreach ($guides as $g): ?>
      <article class="card"><a href="<?= e(url('g/'.$g['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e($g['cover_url']) ?>" alt="<?= e($g['title']) ?>">
        <div class="card-body">
          <?php if($g['dest_name']):?><span class="chip"><?= e($g['dest_name']) ?></span><?php endif;?>
          <?php if(rmt_is_editorial($g)):?><?= rmt_editorial_badge() ?><?php endif;?>
          <?php if($g['premium']):?><span class="chip" style="background:#fef3c7;color:#92400e">Premium</span><?php endif;?>
          <h3><?= e($g['title']) ?></h3>
          <p class="muted"><?= e($g['summary']) ?></p>
          <div class="meta-row">by <?= rmt_is_editorial($g) ? e(rmt_editorial_name()) : '@'.e($g['author']['username']??'') ?></div>
        </div></a></article>
    <?php endforeach; ?>
  </div>
</div>
