<?php /** @var array $errors */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Start a collection</h1>
  <p class="muted">A curated list of destinations with your own reasoning — "Best beaches for solo travelers", "3 cities that ruined me for the right reasons". Add the destinations after you create it.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('collection/new')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('collection_new')) ?>">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="<?= e(input('title')) ?>" placeholder="Best beaches for solo travelers" required>
    <label for="summary">Summary <span class="hint">(optional)</span></label>
    <textarea id="summary" name="summary" maxlength="500" placeholder="What ties this list together, and who it's for"><?= e(input('summary')) ?></textarea>
    <div style="margin-top:18px"><button class="btn btn-primary" type="submit">Create collection</button></div>
  </form>
</div></div>
