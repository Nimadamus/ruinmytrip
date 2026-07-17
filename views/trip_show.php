<?php /** @var array $t @var array $photos @var array $comments */ $me = current_user(); ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <?php if($t['dest_slug']):?><a href="<?= e(url('d/'.$t['dest_slug'])) ?>"><?= e($t['dest_name']) ?></a> / <?php endif;?><?= e($t['title']) ?></p>
</div>
<div class="wrap prose">
  <h1><?= e($t['title']) ?></h1>
  <div class="meta-row"><img class="avatar" src="<?= e($t['author']['avatar_url']??'') ?>" alt="">
    <span><a href="<?= e(url('u/'.$t['author']['username'])) ?>">@<?= e($t['author']['username']) ?></a> · <?= e(ago($t['created_at'])) ?>
    <?php if($t['visited_on']):?> · visited <?= e(date('M Y', strtotime((string)$t['visited_on']))) ?><?php endif;?></span>
    <?php if (show_verified($t)): ?><span class="verified">Verified visit</span><?php endif; ?>
  </div>
  <?php if ($t['cover_url']): ?><img class="article-hero" src="<?= e($t['cover_url']) ?>" alt="<?= e($t['title']) ?>"><?php endif; ?>
  <div><?= nl2br(e($t['body'])) ?></div>
  <?php foreach ($photos as $p): ?><img class="article-hero" loading="lazy" src="<?= e($p['url']) ?>" alt="<?= e($p['caption']) ?>"><?php endforeach; ?>

  <div style="display:flex;gap:10px;margin:24px 0;flex-wrap:wrap">
    <?php if ($me): ?>
      <form class="inline-form" method="post" action="<?= e(url('react')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="kind" value="like"><input type="hidden" name="target_type" value="trip"><input type="hidden" name="target_id" value="<?= (int)$t['id'] ?>">
        <input type="hidden" name="return" value="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>">
        <button class="btn btn-ghost btn-sm">♥ Like</button></form>
      <form class="inline-form" method="post" action="<?= e(url('react')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="kind" value="save"><input type="hidden" name="target_type" value="trip"><input type="hidden" name="target_id" value="<?= (int)$t['id'] ?>">
        <input type="hidden" name="return" value="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>">
        <button class="btn btn-ghost btn-sm">⭑ Save</button></form>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('report?target_type=trip&target_id='.$t['id'])) ?>">⚑ Report</a>
    <?php endif; ?>
  </div>

  <h2>Comments</h2>
  <?php foreach ($comments as $c): ?>
    <div class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px">
      <b>@<?= e($c['username']) ?></b> <span class="hint"><?= e(ago($c['created_at'])) ?></span>
      <p style="margin:.3rem 0 0"><?= nl2br(e($c['body'])) ?></p>
    </div></div>
  <?php endforeach; ?>
  <?php if (!$comments): ?><p class="muted">No comments yet.</p><?php endif; ?>
  <?php if ($me): ?>
    <form method="post" action="<?= e(url('comment')) ?>" style="margin:12px 0 60px"><?= csrf_field() ?>
      <input type="hidden" name="target_type" value="trip"><input type="hidden" name="target_id" value="<?= (int)$t['id'] ?>">
      <input type="hidden" name="return" value="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>">
      <textarea name="body" placeholder="Add a comment" style="min-height:80px"></textarea>
      <button class="btn btn-primary" style="margin-top:8px">Post comment</button>
    </form>
  <?php else: ?><p style="margin-bottom:60px"><a href="<?= e(url('login')) ?>">Sign in</a> to comment.</p><?php endif; ?>
</div>
