<?php /** @var array $c @var array $items @var array $available @var array $errors */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Edit collection</h1>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/edit')) ?>">
    <?= csrf_field() ?>
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="<?= e($c['title']) ?>" required>
    <label for="summary">Summary <span class="hint">(optional)</span></label>
    <textarea id="summary" name="summary" maxlength="500"><?= e($c['summary'] ?? '') ?></textarea>
    <div style="margin-top:18px"><button class="btn btn-primary" type="submit">Save details</button></div>
  </form>

  <hr style="margin:28px 0">

  <h2>Destinations <span class="muted" style="font-weight:400;font-size:1rem">(<?= count($items) ?>)</span></h2>
  <?php if (!$items): ?><p class="muted">Nothing added yet.</p><?php endif; ?>
  <?php foreach ($items as $it): ?>
    <div class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px;display:flex;gap:10px;align-items:flex-start">
      <div style="flex:1">
        <b><?= e($it['dest_name']) ?>, <?= e($it['dest_country']) ?></b>
        <?php if ($it['note']): ?><p style="margin:.3rem 0 0"><?= e($it['note']) ?></p><?php endif; ?>
      </div>
      <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/items/'.(int)$it['id'].'/delete')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" style="color:#b42318">Remove</button>
      </form>
    </div></div>
  <?php endforeach; ?>

  <?php if ($available): ?>
    <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/items')) ?>" style="margin-top:14px">
      <?= csrf_field() ?>
      <label for="destination_id">Add a destination</label>
      <select id="destination_id" name="destination_id" required>
        <option value="">Choose…</option>
        <?php foreach ($available as $d): ?>
          <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?>, <?= e($d['country']) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="note">Why it's on this list <span class="hint">(optional)</span></label>
      <textarea id="note" name="note" maxlength="500" placeholder="A sentence or two on why this one made the cut"></textarea>
      <div style="margin-top:12px"><button class="btn btn-accent" type="submit">Add to collection</button></div>
    </form>
  <?php else: ?>
    <p class="muted" style="margin-top:14px">Every destination on the site is already in this collection.</p>
  <?php endif; ?>

  <hr style="margin:28px 0">
  <p><a href="<?= e(url('c/'.$c['slug'])) ?>">View collection →</a></p>
  <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/delete')) ?>" onsubmit="return confirm('Delete this collection? This cannot be undone.');">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-sm" style="color:#b42318">Delete collection</button>
  </form>
</div></div>
