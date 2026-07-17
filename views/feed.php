<?php /** @var array $items @var array $me */ ?>
<div class="wrap" style="max-width:760px">
  <h1 style="margin-top:24px">Your feed</h1>
  <p class="muted">Latest trips from you and the travelers you follow.</p>
  <p><a class="btn btn-primary" href="<?= e(url('trip/new')) ?>">Share a trip</a></p>
  <?php if (!$items): ?>
    <div class="callout">You're not following anyone yet. <a href="<?= e(url('explore')) ?>">Explore destinations</a> and find travelers to follow.</div>
  <?php endif; ?>
  <?php foreach ($items as $t): ?>
    <article class="card" style="margin-bottom:18px">
      <a href="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>"><img class="card-media" loading="lazy" src="<?= e($t['cover_url']) ?>" alt="<?= e($t['title']) ?>"></a>
      <div class="card-body">
        <div class="meta-row" style="margin:0 0 8px"><img class="avatar" src="<?= e($t['author']['avatar_url']??'') ?>" alt="">
          <span><a href="<?= e(url('u/'.$t['author']['username'])) ?>">@<?= e($t['author']['username']) ?></a> · <?= e(ago($t['created_at'])) ?><?= $t['dest_name']?' · '.e($t['dest_name']):'' ?></span></div>
        <h3><a href="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>"><?= e($t['title']) ?></a></h3>
        <p><?= e(mb_strimwidth(strip_tags((string)$t['body']),0,180,'…')) ?></p>
      </div>
    </article>
  <?php endforeach; ?>
</div>
