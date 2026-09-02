<?php /** @var array $p @var array $comments @var int $likeCount @var int $saveCount @var bool $liked @var bool $saved @var ?array $me @var ?array $original @var int $repostCount @var array $related */ ?>
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
      <?php if (!empty($p['place_slug'])): ?>
        <div class="hint">about <a href="<?= e(url('p/'.$p['place_slug'])) ?>"><?= e((string) $p['place_name']) ?></a></div>
      <?php endif; ?>
      <?php if (!empty($p['community_slug'])): ?>
        <div class="hint">in <a href="<?= e(url('c/'.$p['community_slug'])) ?>"><?= e((string) $p['community_title']) ?></a></div>
      <?php endif; ?>
    </div>
  </div>

  <?php /* No headline. A post is what somebody said, and inventing a title for it on the page would
           put words in their mouth -- the derived one-liner is for the tab and the feed only. */ ?>
  <div style="font-size:1.12rem;line-height:1.65;margin:18px 0;white-space:pre-wrap"><?= rmt_linkify_tags(rmt_linkify_mentions(e((string) $p['body']))) ?></div>

  <?php if (!empty($poll)): $postId = (int) $p['id']; include __DIR__ . '/_poll.php'; endif; ?>

  <?php /* What this passes on, quoted rather than restated, so the credit is unambiguous. */ ?>
  <?php if ($original): ?>
    <div class="card" style="margin:0 0 18px"><div class="card-body" style="padding:14px 16px">
      <b><a href="<?= e(url('u/'.$original['author']['username'])) ?>">@<?= e((string) $original['author']['username']) ?></a></b>
      <span class="hint"> · <?= e(ago((string) $original['created_at'])) ?></span>
      <p style="margin:.4rem 0 .3rem;white-space:pre-wrap"><?= nl2br(e(mb_strimwidth((string) $original['body'], 0, 600, '…'))) ?></p>
      <?php if (!empty($original['image_url'])): ?>
        <img loading="lazy" src="<?= e(abs_url((string) $original['image_url'])) ?>" alt=""
             style="width:100%;max-height:360px;object-fit:cover;border-radius:10px;margin:.2rem 0 .4rem">
      <?php endif; ?>
      <p class="hint" style="margin:0"><a href="<?= e(url('post/'.(int) $original['id'])) ?>">Open the original</a></p>
    </div></div>
  <?php elseif (!empty($p['repost_of'])): ?>
    <p class="hint">The post this passed on has been removed.</p>
  <?php endif; ?>

  <?php if (!empty($p['image_url'])): ?>
    <img src="<?= e(abs_url((string) $p['image_url'])) ?>" alt=""
         style="width:100%;border-radius:12px;margin:0 0 18px">
  <?php endif; ?>

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

  <?php /* Reposting is how something reaches people who have never heard of whoever wrote it.
           Adding a line is encouraged and optional: the empty version is one click. */ ?>
  <?php if ($me && (int) $p['user_id'] !== (int) $me['id']): ?>
    <form method="post" action="<?= e(url('post/'.(int) $p['id'].'/repost')) ?>" style="margin:0 0 14px">
      <?= csrf_field() ?>
      <input type="text" name="body" maxlength="<?= RMT_POST_MAX ?>" placeholder="Add a line (optional)"
             style="max-width:420px;display:inline-block;vertical-align:middle">
      <button class="btn btn-ghost btn-sm">Repost<?= $repostCount ? ' · '.$repostCount : '' ?></button>
    </form>
  <?php elseif ($repostCount): ?>
    <p class="hint">Reposted <?= (int) $repostCount ?> <?= $repostCount === 1 ? 'time' : 'times' ?>.</p>
  <?php endif; ?>

  <?php $shareUrl = url('post/'.(int) $p['id']); $shareText = rmt_post_title($p);
        include __DIR__ . '/_share.php'; ?>

  <?php
    $targetType = 'post';
    $targetId = (int) $p['id'];
    $returnUrl = url('post/' . (int) $p['id']);
    $ownerId = (int) $p['user_id'];
    include __DIR__ . '/_engagement.php';
  ?>

  <?php /* The person best placed to answer a question about a place is somebody who has been, and
           the site wants the longer thing they know rather than only the one-line reply. */ ?>
  <?php if (!empty($p['place_slug'])): ?>
    <div class="empty-cta" style="margin:24px 0">
      <h3 style="margin:0 0 6px">Been to <?= e((string) $p['place_name']) ?>?</h3>
      <p class="muted" style="margin:0">Answer the question above, or write the review this page is
        missing. The bad parts are the useful parts.</p>
      <p style="margin:14px 0 0">
        <a class="btn btn-accent" href="<?= e(url('review/new?place='.(int) $p['place_id'].'&src=post')) ?>">Write the review</a>
        <a class="btn btn-ghost" href="<?= e(url('p/'.$p['place_slug'])) ?>">See the place</a>
      </p>
    </div>
  <?php endif; ?>

  <?php /* A page that ends in nothing sends the reader back to the search result they came from. */ ?>
  <?php if ($related): ?>
    <h2 style="margin:30px 0 10px;font-size:1.15rem">More talk</h2>
    <?php foreach ($related as $rl): ?>
      <div class="card" style="margin-bottom:8px"><div class="card-body" style="padding:12px 16px">
        <a href="<?= e(url('post/'.(int) $rl['id'])) ?>"><?= e(mb_strimwidth((string) $rl['body'], 0, 140, '…')) ?></a>
        <span class="hint"> · @<?= e((string) $rl['username']) ?></span>
      </div></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <p style="margin:26px 0 50px"><a class="btn btn-ghost" href="<?= e(url('talk')) ?>">← All travel talk</a>
    <?php if (!empty($p['dest_slug'])): ?>
      <a class="btn btn-ghost" href="<?= e(url('talk?d='.$p['dest_slug'])) ?>">More about <?= e((string) $p['dest_name']) ?></a>
    <?php endif; ?>
  </p>
</div>
