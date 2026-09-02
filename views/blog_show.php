<?php /** @var array $p @var ?array $me @var array $comments @var int $likeCount @var int $saveCount @var bool $liked @var bool $saved */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('blog')) ?>">Blog</a> / <?= e($p['title']) ?></p></div>
<div class="wrap prose">
  <?php $isEd = rmt_is_editorial($p); ?>
  <span class="chip"><?= e(ucfirst($p['category'])) ?></span>
  <?php if ($isEd): ?><?= rmt_editorial_badge() ?><?php endif; ?>
  <h1><?= e($p['title']) ?></h1>
  <p class="muted">by <a href="<?= e(url('u/'.$p['author']['username'])) ?>"><?= $isEd ? e(rmt_editorial_name()) : '@'.e($p['author']['username']) ?></a> · <?= e(ago($p['created_at'])) ?></p>
  <?php if ($isEd): ?><div class="callout"><?= rmt_editorial_disclosure() ?></div><?php endif; ?>
  <?php if ($p['cover_url']): ?><img class="article-hero" src="<?= e(abs_url($p['cover_url'])) ?>" alt="<?= e($p['title']) ?>"><?php endif; ?>
  <p style="font-size:1.15rem;color:var(--muted)"><?= e($p['summary']) ?></p>
  <div style="white-space:<?= $isEd ? 'normal' : 'pre-wrap' ?>"><?= $isEd ? $p['body'] : rmt_linkify_mentions(rmt_linkify_tags(nl2br(e($p['body'])))) ?></div>
  <?php if (!empty($tags)): ?>
    <div class="tag-row"><?php foreach ($tags as $tg): ?><a class="chip" href="<?= e(url('tag/'.$tg['name'])) ?>">#<?= e($tg['name']) ?></a><?php endforeach; ?></div>
  <?php endif; ?>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin:30px 0 20px">
    <a class="btn btn-ghost" href="<?= e(url('blog')) ?>">← Back to blog</a>
  </div>

  <?php /* A reader who came for a price has a trip behind them. Ask for the sentence, here, while
           the page is still open: it becomes their first review. */ ?>
  <div class="card" style="margin:22px 0"><div class="card-body">
    <?php $dests = $askDests ?? []; $askVariant = 'card'; include __DIR__ . '/_ruined_ask.php'; ?>
  </div></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (rmt_blog_can_edit($p, $me)): ?>
      <a class="btn btn-ghost" href="<?= e(url('blog/'.(int)$p['id'].'/edit')) ?>">Edit</a>
    <?php endif; ?>
  </div>

  <?php
    $targetType = 'blog_post'; $targetId = (int)$p['id']; $ownerId = (int)$p['user_id'];
    $returnUrl = url('blog/'.$p['slug']);
    include __DIR__ . '/_engagement.php';
  ?>
</div>
