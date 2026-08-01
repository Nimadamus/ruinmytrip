<?php /** @var array $d @var array $photos */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> / <a href="<?= e(url('d/'.$d['slug'])) ?>"><?= e($d['name']) ?></a> / Photos</p>
  <h1 style="margin-top:6px">Photos of <?= e($d['name']) ?>, <?= e($d['country']) ?></h1>
  <p class="muted"><?= count($photos) ?> real traveler <?= count($photos)===1?'photo':'photos' ?> from trips and reviews. No stock images.</p>

  <?php if (!$photos): ?>
    <div class="empty-cta" style="margin:20px 0">
      <h3>No traveler photos yet.</h3>
      <p class="muted" style="margin:0">Been to <?= e($d['name']) ?>? Add photos to a trip or review.</p>
      <p style="margin:16px 0 0">
        <a class="btn btn-accent" href="<?= e(url('trip/new')) ?>">Share a trip</a>
        <a class="btn btn-ghost" href="<?= e(url('review/new')) ?>">Write a review</a>
      </p>
    </div>
  <?php endif; ?>

  <div class="grid g-4" style="padding-bottom:50px">
    <?php foreach ($photos as $p): $parentUrl = $p['kind']==='trip' ? url('trip/'.$p['parent_id'].'/'.$p['parent_slug']) : url('review/'.$p['parent_id'].($p['parent_slug'] ? '/'.$p['parent_slug'] : '')); ?>
      <a href="<?= e($parentUrl) ?>" title="<?= e($p['caption'] ?? '') ?>">
        <img class="card-media" loading="lazy" style="aspect-ratio:1;object-fit:cover" src="<?= e(abs_url($p['url'])) ?>" alt="<?= e($p['caption'] ?: ($d['name'].' photo by @'.($p['author']['username'] ?? ''))) ?>">
      </a>
    <?php endforeach; ?>
  </div>
</div>
