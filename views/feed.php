<?php /** @var array $items @var array $me */
$rmt_kind_labels = ['trip' => 'Trip', 'review' => 'Review', 'guide' => 'Guide', 'blog_post' => 'Blog'];
?>
<div class="wrap" style="max-width:760px">
  <h1 style="margin-top:24px">Your feed</h1>
  <p class="muted">Latest trips, reviews, guides and blog posts from you and the travelers you follow.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 20px">
    <a class="btn btn-primary btn-sm" href="<?= e(url('trip/new')) ?>">Share a trip</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('review/new')) ?>">Write a review</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('guide/new')) ?>">Write a guide</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('blog/new')) ?>">Write a blog post</a>
  </div>
  <?php if (!$items): ?>
    <div class="callout">You're not following anyone yet. <a href="<?= e(url('explore')) ?>">Explore destinations</a> and find travelers to follow.</div>
  <?php endif; ?>
  <?php foreach ($items as $it): ?>
    <article class="card" style="margin-bottom:18px">
      <?php if (!empty($it['cover_url'])): ?>
        <a href="<?= e($it['feed_url']) ?>"><img class="card-media" loading="lazy" src="<?= e(abs_url($it['cover_url'])) ?>" alt="<?= e($it['title']) ?>"></a>
      <?php endif; ?>
      <div class="card-body">
        <div class="meta-row" style="margin:0 0 8px">
          <img class="avatar" src="<?= e(avatar_url($it['author']['avatar_url']??null)) ?>" alt="">
          <span>
            <span class="chip" style="margin-right:6px"><?= e($rmt_kind_labels[$it['kind']] ?? ucfirst($it['kind'])) ?></span>
            <a href="<?= e(url('u/'.($it['author']['username']??''))) ?>">@<?= e($it['author']['username']??'') ?></a> · <?= e(ago($it['created_at'])) ?><?= !empty($it['dest_name'])?' · '.e($it['dest_name']):'' ?>
          </span>
        </div>
        <h3><a href="<?= e($it['feed_url']) ?>"><?= e($it['title']) ?></a></h3>
        <p><?= e($it['feed_excerpt']) ?></p>
      </div>
    </article>
  <?php endforeach; ?>
</div>
