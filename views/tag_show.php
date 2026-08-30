<?php /** @var array $tag @var array $items @var ?array $me */
$rmt_kind_labels = ['trip' => 'Trip', 'review' => 'Review', 'guide' => 'Guide', 'blog_post' => 'Blog', 'collection' => 'Collection', 'post' => 'Talk'];
?>
<div class="wrap" style="max-width:760px">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('tags')) ?>">Topics</a> / #<?= e($tag['name']) ?></p>
  <h1 style="margin-top:8px">#<?= e($tag['name']) ?></h1>
  <p class="muted"><?= count($items) ?> published post<?= count($items) === 1 ? '' : 's' ?> tagged #<?= e($tag['name']) ?>.</p>
  <?php if (!$items): ?>
    <div class="empty-cta" style="margin:24px 0">
      <h3>Nothing published under this topic yet.</h3>
      <p class="muted" style="margin:0">Use #<?= e($tag['name']) ?> in a trip, review, guide or blog post and it will show up here.</p>
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
