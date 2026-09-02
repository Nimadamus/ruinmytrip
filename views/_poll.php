<?php
/**
 * A poll on a post. Radio buttons until you have voted or the poll has closed; bars after.
 *
 * @var array  $poll   from rmt_poll_for_post()/rmt_polls_for_posts()
 * @var int    $postId
 * @var ?array $me
 */
$pollShowResults = $poll['closed'] || $poll['my_option_id'] !== null || !$me;
$pollReturn = $pollReturn ?? url('post/' . $postId);
?>
<div class="poll" data-post="<?= (int) $postId ?>" style="margin:.4rem 0 .6rem;max-width:520px">
  <?php if ($pollShowResults): ?>
    <?php foreach ($poll['options'] as $o): $mine = $poll['my_option_id'] === $o['id']; ?>
      <div style="position:relative;margin:6px 0;border:1px solid var(--line);border-radius:8px;overflow:hidden">
        <div style="position:absolute;inset:0;width:<?= (int) $o['pct'] ?>%;background:<?= $mine ? 'rgba(15,118,110,.22)' : 'rgba(15,27,45,.07)' ?>"></div>
        <div style="position:relative;display:flex;justify-content:space-between;gap:10px;padding:7px 10px">
          <span><?= $mine ? '✓ ' : '' ?><?= e($o['label']) ?></span><b><?= (int) $o['pct'] ?>%</b>
        </div>
      </div>
    <?php endforeach; ?>
    <p class="hint" style="margin:4px 0 0"><?= (int) $poll['total'] ?> <?= $poll['total'] === 1 ? 'vote' : 'votes' ?>
      · <?= e(rmt_poll_closes_label($poll)) ?>
      <?php if (!$me): ?> · <a href="<?= e(url('login?return=' . rawurlencode('/post/' . $postId))) ?>">Sign in to vote</a><?php endif; ?></p>
  <?php else: ?>
    <form method="post" action="<?= e(url('post/' . $postId . '/vote')) ?>">
      <?= csrf_field() ?><input type="hidden" name="return" value="<?= e($pollReturn) ?>">
      <?php foreach ($poll['options'] as $o): ?>
        <label style="display:flex;gap:10px;align-items:center;margin:6px 0;border:1px solid var(--line);border-radius:8px;padding:7px 10px;cursor:pointer">
          <input type="radio" name="option_id" value="<?= (int) $o['id'] ?>" required> <span><?= e($o['label']) ?></span>
        </label>
      <?php endforeach; ?>
      <p style="margin:6px 0 0;display:flex;gap:10px;align-items:center">
        <button class="btn btn-sm btn-primary">Vote</button>
        <span class="hint"><?= (int) $poll['total'] ?> <?= $poll['total'] === 1 ? 'vote' : 'votes' ?> · <?= e(rmt_poll_closes_label($poll)) ?></span>
      </p>
    </form>
  <?php endif; ?>
</div>
