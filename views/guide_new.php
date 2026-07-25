<?php /** @var array $dests @var array $errors */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Write a guide</h1>
  <p class="muted">Detailed, practical plans other travelers can actually follow.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('guide/new')) ?>">
    <?= csrf_field() ?>
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="<?= e(input('title')) ?>" placeholder="Four days in Kyoto without the tour buses" required>
    <label for="destination_id">Destination</label>
    <select id="destination_id" name="destination_id">
      <option value="">— Select a destination —</option>
      <?php foreach ($dests as $d): ?><option value="<?= (int)$d['id'] ?>"<?= (string)input('destination_id') === (string)$d['id'] ? ' selected' : '' ?>><?= e($d['name'].', '.$d['country']) ?></option><?php endforeach; ?>
    </select>
    <label for="summary">One-line summary</label>
    <input type="text" id="summary" name="summary" maxlength="300" value="<?= e(input('summary')) ?>" placeholder="What this guide covers, in one sentence" required>
    <label for="cover_url">Cover image URL <span class="hint">(optional — defaults to the destination photo)</span></label>
    <input type="url" id="cover_url" name="cover_url" value="<?= e(input('cover_url')) ?>" placeholder="https://…">
    <label for="body">The guide</label>
    <textarea id="body" name="body" rows="14" placeholder="Day-by-day plan, what to book ahead, what to skip, what it actually costs." required><?= e(input('body')) ?></textarea>
    <div style="margin-top:18px"><button class="btn btn-primary" type="submit">Publish guide</button></div>
  </form>
</div></div>
