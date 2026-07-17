<?php /** @var array $g */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('guides')) ?>">Guides</a> / <?= e($g['title']) ?></p></div>
<div class="wrap prose">
  <?php if($g['dest_name']):?><span class="chip"><?= e($g['dest_name']) ?></span><?php endif;?>
  <h1><?= e($g['title']) ?></h1>
  <p class="muted">by <a href="<?= e(url('u/'.$g['author']['username'])) ?>">@<?= e($g['author']['username']) ?></a> · <?= e(ago($g['created_at'])) ?></p>
  <?php if ($g['cover_url']): ?><img class="article-hero" src="<?= e($g['cover_url']) ?>" alt="<?= e($g['title']) ?>"><?php endif; ?>
  <p style="font-size:1.15rem;color:var(--muted)"><?= e($g['summary']) ?></p>
  <?php if ($g['premium']): ?><div class="callout warn"><b>Premium guide.</b> A preview is shown. Full booking-ready detail unlocks with a creator subscription (coming soon).</div><?php endif; ?>
  <div><?= $g['body'] // trusted seed/creator HTML ?></div>
  <p style="margin:30px 0 60px"><a class="btn btn-ghost" href="<?php if($g['dest_slug']):?><?= e(url('d/'.$g['dest_slug'])) ?><?php else:?><?= e(url('guides')) ?><?php endif;?>">← More about this destination</a></p>
</div>
