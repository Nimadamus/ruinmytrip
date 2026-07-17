<?php /** @var array $reviews */ $me = current_user(); ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Reviews</p>
  <div class="section-head"><div><h1>Traveler reviews</h1><p class="muted">Honest, verified reviews of destinations, stays, food, nightlife, attractions and tours.</p></div>
    <a class="btn btn-primary" href="<?= e($me?url('review/new'):url('login')) ?>">Write a review</a></div>
  <div class="grid g-2" style="padding-bottom:50px">
    <?php foreach ($reviews as $r): ?>
      <div class="card"><div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span class="stars"><?= stars((int)$r['rating']) ?></span>
          <?php if ($r['verified']): ?><span class="verified">Verified</span><?php endif; ?>
        </div>
        <h3 style="margin:.3rem 0 .1rem"><?= e($r['title']) ?></h3>
        <p class="muted" style="margin:0"><?= e($r['subject_name']) ?> · <span style="text-transform:capitalize"><?= e($r['subject_type']) ?></span>
          <?php if($r['dest_slug']):?> · <a href="<?= e(url('d/'.$r['dest_slug'])) ?>"><?= e($r['dest_name']) ?></a><?php endif;?></p>
        <p style="margin:.5rem 0"><?= e($r['body']) ?></p>
        <div class="meta-row">@<?= e($r['author']['username']??'traveler') ?> · <?= e(ago($r['created_at'])) ?></div>
      </div></div>
    <?php endforeach; ?>
  </div>
</div>
