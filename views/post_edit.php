<?php /** @var array $p @var ?array $me */ ?>
<div class="wrap"><p class="crumbs">
  <a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('talk')) ?>">Talk</a> /
  <a href="<?= e(url('post/'.(int) $p['id'])) ?>">Post</a> / Edit
</p></div>
<div class="wrap prose" style="max-width:680px">
  <h1>Edit your post</h1>
  <?php /* Only the words change. Moving a post to another city or community after people replied
           would rewrite what they were replying to, so those stay where they were. */ ?>
  <p class="hint">You can change what you said. Where you said it stays put.</p>
  <form method="post" action="<?= e(url('post/'.(int) $p['id'].'/edit')) ?>">
    <?= csrf_field() ?>
    <label for="body">Your post</label>
    <textarea id="body" name="body" rows="8" required maxlength="<?= RMT_POST_MAX ?>"><?= e((string) $p['body']) ?></textarea>
    <p style="margin-top:14px">
      <button class="btn btn-accent">Save</button>
      <a class="btn btn-ghost" href="<?= e(url('post/'.(int) $p['id'])) ?>">Cancel</a>
    </p>
  </form>
</div>
