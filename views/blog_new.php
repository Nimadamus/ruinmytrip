<?php /** @var array $errors */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Write a blog post</h1>
  <p class="muted">A real story, a safety note, a budget breakdown, a packing list that actually worked.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('blog/new')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('blog_new')) ?>">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="<?= e(input('title')) ?>" placeholder="What I wish I knew before backpacking solo" required>
    <label for="category">Category</label>
    <select id="category" name="category">
      <?php foreach (RMT_BLOG_CATEGORIES as $c): ?>
        <option value="<?= e($c) ?>"<?= (string)input('category') === $c ? ' selected' : '' ?>><?= e(ucfirst($c)) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="summary">One-line summary</label>
    <input type="text" id="summary" name="summary" maxlength="300" value="<?= e(input('summary')) ?>" placeholder="What this post covers, in one sentence" required>
    <label for="cover_url">Cover image URL <span class="hint">(optional)</span></label>
    <input type="url" id="cover_url" name="cover_url" value="<?= e(input('cover_url')) ?>" placeholder="https://…">
    <label for="body">The post</label>
    <textarea id="body" name="body" rows="14" placeholder="Write the real thing, not the highlight reel." required><?= e(input('body')) ?></textarea>
    <div style="margin-top:18px"><button class="btn btn-primary" type="submit">Publish post</button></div>
  </form>
</div></div>
