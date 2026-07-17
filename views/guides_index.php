<?php /** @var array $guides */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Guides</p>
  <h1>Travel guides & itineraries</h1>
  <p class="muted">Detailed, traveler-written plans you can actually follow.</p>
  <div class="grid g-3" style="padding:20px 0 50px">
    <?php foreach ($guides as $g): ?>
      <article class="card"><a href="<?= e(url('g/'.$g['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e($g['cover_url']) ?>" alt="<?= e($g['title']) ?>">
        <div class="card-body">
          <?php if($g['dest_name']):?><span class="chip"><?= e($g['dest_name']) ?></span><?php endif;?>
          <?php if($g['premium']):?><span class="chip" style="background:#fef3c7;color:#92400e">Premium</span><?php endif;?>
          <h3><?= e($g['title']) ?></h3>
          <p class="muted"><?= e($g['summary']) ?></p>
          <div class="meta-row">by @<?= e($g['author']['username']??'') ?></div>
        </div></a></article>
    <?php endforeach; ?>
  </div>
</div>
