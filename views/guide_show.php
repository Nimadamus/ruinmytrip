<?php /** @var array $g */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('guides')) ?>">Guides</a> / <?= e($g['title']) ?></p></div>
<div class="wrap prose">
  <?php $isEd = rmt_is_editorial($g); ?>
  <?php if($g['dest_name']):?><span class="chip"><?= e($g['dest_name']) ?></span><?php endif;?>
  <?php if($isEd):?><?= rmt_editorial_badge() ?><?php endif;?>
  <h1><?= e($g['title']) ?></h1>
  <p class="muted">by <a href="<?= e(url('u/'.$g['author']['username'])) ?>"><?= $isEd ? e(rmt_editorial_name()) : '@'.e($g['author']['username']) ?></a> · <?= e(ago($g['created_at'])) ?></p>
  <?php if($isEd):?><div class="callout"><?= e(rmt_editorial_disclosure()) ?></div><?php endif;?>
  <?php if ($g['cover_url']): ?><img class="article-hero" src="<?= e($g['cover_url']) ?>" alt="<?= e($g['title']) ?>"><?php endif; ?>
  <p style="font-size:1.15rem;color:var(--muted)"><?= e($g['summary']) ?></p>
  <?php if ($g['premium']): ?><div class="callout warn"><b>Premium guide.</b> A preview is shown. Full booking-ready detail unlocks with a creator subscription (coming soon).</div><?php endif; ?>
  <div><?= $g['body'] // trusted seed/creator HTML ?></div>
  <?php if($isEd):?>
    <div class="empty-cta" style="margin-top:30px">
      <h2 style="margin:0 0 6px;font-size:1.2rem">Been there? Correct us.</h2>
      <p class="muted" style="margin:0 0 14px">Prices move, routes close, places go downhill. A first-hand review is worth more than this guide and will be shown alongside it.</p>
      <a class="btn btn-accent" href="<?= e(url('review/new')) ?>">Share your experience</a>
    </div>
  <?php endif;?>
  <p style="margin:30px 0 60px"><a class="btn btn-ghost" href="<?php if($g['dest_slug']):?><?= e(url('d/'.$g['dest_slug'])) ?><?php else:?><?= e(url('guides')) ?><?php endif;?>">← More about this destination</a></p>
</div>
