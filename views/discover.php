<?php /** @var array $items @var ?array $me */
$rmt_kind_labels = ['trip' => 'Trip', 'review' => 'Review', 'guide' => 'Guide', 'blog_post' => 'Blog'];
?>
<div class="wrap" style="max-width:760px">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Discover</p>
  <h1 style="margin-top:8px">Discover</h1>
  <p class="muted">The latest trips, reviews, guides and blog posts from every traveler on RuinMyTrip.</p>
  <?php if (!$me): ?>
    <div class="callout">
      <a href="<?= e(url('register')) ?>">Join free</a> to follow travelers and get this curated into your own feed.
    </div>
  <?php endif; ?>
  <?php if (!$items): ?>
    <div class="empty-cta" style="margin:24px 0">
      <h3>Nothing published yet.</h3>
      <p class="muted" style="margin:0">Be the first to share a trip, a review, a guide, or a blog post.</p>
    </div>
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
