<?php /** @var array $p @var array $errors */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Edit post</h1>
  <p class="muted">Changes go live as soon as you save.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('blog/'.(int)$p['id'].'/edit')) ?>">
    <?= csrf_field() ?>
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="<?= e($p['title'] ?? '') ?>" required>
    <label for="category">Category</label>
    <select id="category" name="category">
      <?php foreach (RMT_BLOG_CATEGORIES as $c): ?>
        <option value="<?= e($c) ?>"<?= (string)($p['category'] ?? '') === $c ? ' selected' : '' ?>><?= e(ucfirst($c)) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="summary">One-line summary</label>
    <input type="text" id="summary" name="summary" maxlength="300" value="<?= e($p['summary'] ?? '') ?>" required>
    <label for="cover_url">Cover image URL <span class="hint">(optional)</span></label>
    <input type="url" id="cover_url" name="cover_url" value="<?= e(editable_url_value($p['cover_url'] ?? null)) ?>" placeholder="https://…">
    <label for="body">The post</label>
    <textarea id="body" name="body" rows="14" required><?= e($p['body'] ?? '') ?></textarea>
    <div style="margin-top:18px"><button class="btn btn-primary" type="submit">Save changes</button></div>
  </form>
  <hr style="border:0;border-top:1px solid var(--line);margin:28px 0 18px">
  <form method="post" action="<?= e(url('blog/'.(int)$p['id'].'/delete')) ?>"
        onsubmit="return confirm('Delete this post?');">
    <?= csrf_field() ?>
    <button class="btn btn-ghost" style="color:#b42318">Delete this post</button>
  </form>
</div></div>
