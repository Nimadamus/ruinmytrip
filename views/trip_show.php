<?php /** @var array $t @var array $photos @var array $comments @var int $likeCount @var int $saveCount @var bool $liked @var bool $saved */ $me = current_user(); ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <?php if($t['dest_slug']):?><a href="<?= e(url('d/'.$t['dest_slug'])) ?>"><?= e($t['dest_name']) ?></a> / <?php endif;?><?= e($t['title']) ?></p>
</div>
<div class="wrap prose">
  <h1><?= e($t['title']) ?></h1>
  <div class="meta-row"><img class="avatar" src="<?= e(avatar_url($t['author']['avatar_url']??null)) ?>" alt="">
    <span><a href="<?= e(url('u/'.$t['author']['username'])) ?>">@<?= e($t['author']['username']) ?></a> · <?= e(ago($t['created_at'])) ?>
    <?php if($t['visited_on']):?> · visited <?= e(date('M Y', strtotime((string)$t['visited_on']))) ?><?php endif;?></span>
    <?php if (show_verified($t)): ?><span class="verified">Verified visit</span><?php endif; ?>
  </div>
  <?php if ($t['cover_url']): ?><img class="article-hero" src="<?= e($t['cover_url']) ?>" alt="<?= e($t['title']) ?>"><?php endif; ?>
  <div><?= rmt_linkify_mentions(rmt_linkify_tags(nl2br(e($t['body'])))) ?></div>
  <?php if (!empty($tags)): ?>
    <div class="tag-row"><?php foreach ($tags as $tg): ?><a class="chip" href="<?= e(url('tag/'.$tg['name'])) ?>">#<?= e($tg['name']) ?></a><?php endforeach; ?></div>
  <?php endif; ?>
  <?php foreach ($photos as $p): ?><img class="article-hero" loading="lazy" src="<?= e($p['url']) ?>" alt="<?= e($p['caption']) ?>"><?php endforeach; ?>

  <?php if ($me && (int)$t['user_id'] === (int)$me['id']): ?>
    <p style="margin:12px 0 0"><a class="btn btn-ghost btn-sm" href="<?= e(url('trip/'.$t['id'].'/edit')) ?>">Edit</a></p>
  <?php endif; ?>
  <?php $shareUrl = url('trip/'.$t['id'].'/'.$t['slug']); $shareText = (string) $t['title'];
        include __DIR__ . '/_share.php'; ?>

  <?php
    $targetType = 'trip'; $targetId = (int)$t['id']; $ownerId = (int)$t['user_id'];
    $returnUrl = url('trip/'.$t['id'].'/'.$t['slug']);
    include __DIR__ . '/_engagement.php';
  ?>
</div>
