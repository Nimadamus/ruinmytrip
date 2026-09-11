<?php /** @var array $photo @var array $sib @var ?array $me @var bool $isPrivate
        @var string $target @var bool $canReact @var int $likeCount @var bool $liked @var array $comments */ ?>
<div class="photo-stage">
  <?php /* The picture first and as large as the screen allows, on a dark ground, because a photo
           on a white page is a thumbnail with ambition. Prev and next are plain links, so an album
           can be walked with a keyboard, by a crawler, and with JavaScript switched off. */ ?>
  <?php if ($sib['prev']): ?>
    <a class="photo-step prev" href="<?= e(url(ltrim(rmt_photo_path($photo['kind'], (int) $sib['prev']), '/'))) ?>"
       rel="prev" aria-label="Previous photo">&#8249;</a>
  <?php endif; ?>
  <img class="photo-main" src="<?= e($photo['url']) ?>"
       alt="<?= e($photo['caption'] !== '' ? $photo['caption'] : 'Traveler photo' . ($photo['dest_name'] ? ' from ' . $photo['dest_name'] : '')) ?>"
       <?php if ((int) $photo['width'] > 0): ?>width="<?= (int) $photo['width'] ?>" height="<?= (int) $photo['height'] ?>"<?php endif; ?>>
  <?php if ($sib['next']): ?>
    <a class="photo-step next" href="<?= e(url(ltrim(rmt_photo_path($photo['kind'], (int) $sib['next']), '/'))) ?>"
       rel="next" aria-label="Next photo">&#8250;</a>
  <?php endif; ?>
</div>

<div class="wrap photo-meta">
  <?php if ($photo['caption'] !== ''): ?>
    <p class="photo-caption"><?= e($photo['caption']) ?></p>
  <?php endif; ?>

  <div class="photo-by">
    <a href="<?= e(url('u/'.($photo['author']['username'] ?? ''))) ?>">
      <img class="avatar" src="<?= e(avatar_url($photo['author']['avatar_url'] ?? null)) ?>" alt=""></a>
    <span>
      <a href="<?= e(url('u/'.($photo['author']['username'] ?? ''))) ?>"><b>@<?= e((string) ($photo['author']['username'] ?? '')) ?></b></a>
      <span class="hint">
        <?php if ($photo['dest_slug']): ?><a href="<?= e(url('d/'.$photo['dest_slug'])) ?>"><?= e((string) $photo['dest_name']) ?></a> &middot; <?php endif; ?>
        <?= e(ago((string) $photo['created_at'])) ?>
        <?php if ($sib['total'] > 1): ?> &middot; <?= (int) $sib['index'] ?> of <?= (int) $sib['total'] ?><?php endif; ?>
      </span>
    </span>
    <?php if ($isPrivate): ?>
      <?php /* Said out loud on the page, because somebody looking at their own private photo needs
               to know it is not the one strangers can see. */ ?>
      <span class="chip" style="margin-left:auto">Only <?= $photo['visibility'] === 'followers' ? 'your followers' : 'you' ?></span>
    <?php endif; ?>
  </div>

  <p class="photo-from">
    From <a href="<?= e($photo['parent_url']) ?>"><b><?= e((string) $photo['parent_title']) ?></b></a><?php
      if ($photo['parent_kind'] === 'trip'): ?>, a trip<?php
      elseif ($photo['parent_kind'] === 'review'): ?>, a review<?php endif; ?>.
    <?php if ($photo['dest_slug']): ?>
      <a href="<?= e(url('d/'.$photo['dest_slug'].'/photos')) ?>">More photos of <?= e((string) $photo['dest_name']) ?></a>.
    <?php endif; ?>
  </p>

  <?php /* A photograph is the thing most likely to need taking down quickly, and until now the
           only way to reach a moderator about one was to report the person. */ ?>
  <?php if ($me && (int) ($photo['user_id'] ?? 0) !== (int) $me['id']): ?>
    <p class="hint" style="margin:6px 0 0">
      <a href="<?= e(url('report?target_type='.$target.'&target_id='.(int) $photo['id'])) ?>">Report this photo</a>
    </p>
  <?php endif; ?>

  <?php if ($canReact): ?>
    <?php $rmt_back = rmt_photo_path($photo['kind'], (int) $photo['id']); ?>
    <div class="act-row" style="margin-top:16px">
      <?php if ($me): ?>
        <form method="post" action="<?= e(url('react')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="kind" value="like">
          <input type="hidden" name="target_type" value="<?= e($target) ?>">
          <input type="hidden" name="target_id" value="<?= (int) $photo['id'] ?>">
          <input type="hidden" name="return" value="<?= e($rmt_back) ?>">
          <button class="act<?= $liked ? ' on' : '' ?>" aria-label="<?= $liked ? 'Remove like' : 'Like this photo' ?>">
            <span aria-hidden="true"><?= $liked ? '&#9829;' : '&#9825;' ?></span><?php if ($likeCount): ?> <?= $likeCount ?><?php endif; ?>
          </button>
        </form>
        <form method="post" action="<?= e(url('react')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="kind" value="save">
          <input type="hidden" name="target_type" value="<?= e($target) ?>">
          <input type="hidden" name="target_id" value="<?= (int) $photo['id'] ?>">
          <input type="hidden" name="return" value="<?= e($rmt_back) ?>">
          <button class="act" aria-label="Save this photo"><span aria-hidden="true">&#9733;</span></button>
        </form>
      <?php elseif ($likeCount): ?>
        <span class="act on"><span aria-hidden="true">&#9829;</span> <?= $likeCount ?></span>
      <?php endif; ?>
      <span class="act" style="cursor:default">
        <span aria-hidden="true">&#128172;</span><?php if ($comments): ?> <?= count($comments) ?><?php endif; ?>
      </span>
    </div>

    <?php /* What people said about this picture, under it, in the order it was said. */ ?>
    <div id="comments" class="thread" style="border-top:0;margin-top:6px">
      <?php foreach ($comments as $c): ?>
        <div class="thread-line">
          <img class="avatar" style="width:26px;height:26px" src="<?= e(avatar_url($c['avatar_url'] ?? null)) ?>" alt="">
          <span><a href="<?= e(url('u/'.$c['username'])) ?>"><b>@<?= e((string) $c['username']) ?></b></a>
            <?= rmt_linkify_mentions(e((string) $c['body'])) ?>
            <span class="hint"><?= e(ago((string) $c['created_at'])) ?></span></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($me): ?>
      <form class="thread-reply" method="post" action="<?= e(url('comment')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="target_type" value="<?= e($target) ?>">
        <input type="hidden" name="target_id" value="<?= (int) $photo['id'] ?>">
        <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('comment_'.$target.'_'.(int) $photo['id'])) ?>">
        <input type="hidden" name="return" value="<?= e($rmt_back) ?>">
        <img class="avatar" style="width:26px;height:26px" src="<?= e(avatar_url(rmt_profile_avatar((int) $me['id']))) ?>" alt="">
        <input type="text" name="body" maxlength="2000" placeholder="Say something about this photo">
        <button class="btn btn-ghost btn-sm">Reply</button>
      </form>
    <?php elseif (!$isPrivate): ?>
      <p class="hint" style="margin:12px 0 0">
        <a href="<?= e(url('register?return=' . rawurlencode($rmt_back))) ?>">Join</a> to say something about this photo.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!$isPrivate): ?>
    <?php $shareUrl = abs_url(rmt_photo_path($photo['kind'], (int) $photo['id']));
          $shareText = $photo['caption'] !== '' ? rmt_photo_trim($photo['caption'], 80)
                                                : (string) ($photo['dest_name'] ?: 'A traveler photo');
          include __DIR__ . '/_share.php'; ?>
  <?php endif; ?>
</div>
