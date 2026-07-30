<?php /** @var array $tags */ ?>
<div class="wrap" style="max-width:760px">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Topics</p>
  <h1 style="margin-top:8px">Topics</h1>
  <p class="muted">Every hashtag travelers are using across trips, reviews, guides and the blog. Add a #tag to anything you write and it joins these pages automatically.</p>
  <?php if (!$tags): ?>
    <div class="empty-cta" style="margin:24px 0">
      <h3>No topics yet.</h3>
      <p class="muted" style="margin:0">Write #hashtags into a trip, review, guide or blog post to start one.</p>
    </div>
  <?php endif; ?>
  <div class="tag-cloud">
    <?php foreach ($tags as $t): ?>
      <a class="chip tag-chip" href="<?= e(url('tag/'.$t['name'])) ?>">#<?= e($t['name']) ?> <span class="muted"><?= (int)$t['n'] ?></span></a>
    <?php endforeach; ?>
  </div>
</div>
