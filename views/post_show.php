<?php /** @var array $p @var array $comments @var int $likeCount @var int $saveCount @var bool $liked @var bool $saved @var ?array $me */ ?>
<div class="wrap"><p class="crumbs">
  <a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('talk')) ?>">Talk</a>
  <?php if (!empty($p['dest_slug'])): ?> / <a href="<?= e(url('d/'.$p['dest_slug'])) ?>"><?= e((string) $p['dest_name']) ?></a><?php endif; ?>
</p></div>
<div class="wrap prose">
  <div class="meta-row" style="display:flex;gap:12px;align-items:center">
    <img class="avatar" src="<?= e(avatar_url($p['author']['avatar_url'] ?? null)) ?>" alt="">
    <div>
      <b><a href="<?= e(url('u/'.$p['author']['username'])) ?>">@<?= e((string) $p['author']['username']) ?></a></b>
      <span class="hint"> · <?= e(ago((string) $p['created_at'])) ?><?php if (!empty($p['updated_at'])): ?> · edited<?php endif; ?></span>
      <?php if (!empty($p['community_slug'])): ?>
        <div class="hint">in <a href="<?= e(url('c/'.$p['community_slug'])) ?>"><?= e((string) $p['community_title']) ?></a></div>
      <?php endif; ?>
    </div>
  </div>

  <?php /* No headline. A post is what somebody said, and inventing a title for it on the page would
           put words in their mouth -- the derived one-liner is for the tab and the feed only. */ ?>
  <div style="font-size:1.12rem;line-height:1.65;margin:18px 0;white-space:pre-wrap"><?= rmt_linkify_mentions(e((string) $p['body'])) ?></div>

  <?php if ($me && (rmt_post_can_edit($p, $me) || rmt_post_can_remove($p, $me))): ?>
    <div style="display:flex;gap:8px;margin:0 0 8px">
      <?php if (rmt_post_can_edit($p, $me)): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('post/'.(int) $p['id'].'/edit')) ?>">Edit</a>
      <?php endif; ?>
      <form method="post" action="<?= e(url('post/'.(int) $p['id'].'/delete')) ?>"
            onsubmit="return confirm('Delete this post? Replies go with it.');">
        <?= csrf_field() ?><button class="btn btn-ghost btn-sm" style="color:#b42318">Delete</button>
      </form>
    </div>
  <?php endif; ?>

  <?php
    $targetType = 'post';
    $targetId = (int) $p['id'];
    $returnUrl = url('post/' . (int) $p['id']);
    $ownerId = (int) $p['user_id'];
    include __DIR__ . '/_engagement.php';
  ?>

  <p style="margin:26px 0 50px"><a class="btn btn-ghost" href="<?= e(url('talk')) ?>">← All travel talk</a>
    <?php if (!empty($p['dest_slug'])): ?>
      <a class="btn btn-ghost" href="<?= e(url('talk?d='.$p['dest_slug'])) ?>">More about <?= e((string) $p['dest_name']) ?></a>
    <?php endif; ?>
  </p>
</div>
