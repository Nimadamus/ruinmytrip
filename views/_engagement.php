<?php
/** @var string $targetType @var int $targetId @var string $returnUrl @var ?array $me
 *  @var int $likeCount @var int $saveCount @var bool $liked @var bool $saved
 *  @var array $comments @var int $ownerId @var bool $showActionsBar set false when the page
 *  already renders its own like/save/report row (e.g. reviews already have useful/funny/cool
 *  votes and their own edit/report links) and only wants the comments block from here.
 */
$showActionsBar ??= true;
?>
<?php if ($showActionsBar): ?>
<div style="display:flex;gap:10px;margin:24px 0;flex-wrap:wrap">
  <?php if ($me): ?>
    <form class="inline-form" method="post" action="<?= e(url('react')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="kind" value="like"><input type="hidden" name="target_type" value="<?= e($targetType) ?>"><input type="hidden" name="target_id" value="<?= (int)$targetId ?>">
      <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
      <button class="btn <?= $liked?'btn-primary':'btn-ghost' ?> btn-sm" aria-pressed="<?= $liked?'true':'false' ?>">
        <?= $liked?'♥ Liked':'♥ Like' ?><?= $likeCount?' · '.$likeCount:'' ?></button></form>
    <form class="inline-form" method="post" action="<?= e(url('react')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="kind" value="save"><input type="hidden" name="target_type" value="<?= e($targetType) ?>"><input type="hidden" name="target_id" value="<?= (int)$targetId ?>">
      <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
      <button class="btn <?= $saved?'btn-primary':'btn-ghost' ?> btn-sm" aria-pressed="<?= $saved?'true':'false' ?>">
        <?= $saved?'⭑ Saved':'⭑ Save' ?><?= $saveCount?' · '.$saveCount:'' ?></button></form>
    <?php if ((int)$ownerId === (int)$me['id']): ?>
      <?php /* Edit link is intentionally left to each page -- edit URLs differ per content type. */ ?>
    <?php else: ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('report?target_type='.$targetType.'&target_id='.$targetId)) ?>">⚑ Report</a>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<h2>Comments</h2>
<?php foreach ($comments as $c): ?>
  <div class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
      <span><b>@<?= e($c['username']) ?></b> <span class="hint"><?= e(ago($c['created_at'])) ?></span></span>
      <?php if ($me && (int)$c['user_id'] === (int)$me['id']): ?>
        <form method="post" action="<?= e(url('comment/'.(int)$c['id'].'/delete')) ?>"
              onsubmit="return confirm('Delete this comment?');"><?= csrf_field() ?>
          <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
          <button class="btn btn-ghost btn-sm" style="color:#b42318">Delete</button>
        </form>
      <?php endif; ?>
    </div>
    <p style="margin:.3rem 0 0"><?= rmt_linkify_mentions(nl2br(e($c['body']))) ?></p>
  </div></div>
<?php endforeach; ?>
<?php if (!$comments): ?><p class="muted">No comments yet.</p><?php endif; ?>
<?php if ($me): ?>
  <form method="post" action="<?= e(url('comment')) ?>" style="margin:12px 0 60px"><?= csrf_field() ?>
    <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('comment_'.$targetType.'_'.$targetId)) ?>">
    <input type="hidden" name="target_type" value="<?= e($targetType) ?>"><input type="hidden" name="target_id" value="<?= (int)$targetId ?>">
    <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
    <textarea name="body" placeholder="Add a comment" maxlength="2000" style="min-height:80px"></textarea>
    <button class="btn btn-primary" style="margin-top:8px">Post comment</button>
  </form>
<?php else: ?><p style="margin-bottom:60px"><a href="<?= e(url('login')) ?>">Sign in</a> to comment.</p><?php endif; ?>
