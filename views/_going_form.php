<?php
/** @var array $dests @var ?array $current @var ?int $lockDestId */
$lockDestId = isset($lockDestId) ? (int)$lockDestId : 0;
$current = $current ?? null;
$from = $current['date_from'] ?? '';
$to = $current['date_to'] ?? '';
$vis = $current['visibility'] ?? 'public';
?>
<form method="post" action="<?= e(url('going')) ?>" style="margin:12px 0 0">
  <?= csrf_field() ?>
  <input type="hidden" name="return" value="<?= e(rmt_current_url()) ?>">
  <p style="margin:0 0 10px"><b><?= $current ? 'Update your dates' : "Share that you're going" ?></b></p>
  <p class="hint" style="margin:0 0 12px">Destination and a date range only. Never a street, hotel, or live location.</p>
  <?php if ($lockDestId > 0): ?>
    <input type="hidden" name="destination_id" value="<?= $lockDestId ?>">
  <?php else: ?>
    <label>Destination
      <select name="destination_id" required>
        <option value="">Choose…</option>
        <?php foreach ($dests as $dd): ?>
          <option value="<?= (int)$dd['id'] ?>" <?= (int)($current['destination_id'] ?? 0) === (int)$dd['id'] ? 'selected' : '' ?>><?= e($dd['name']) ?><?= !empty($dd['country']) ? ', '.e($dd['country']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
  <div class="grid g-2" style="gap:10px;margin-top:10px">
    <label>From <input type="date" name="date_from" required value="<?= e((string)$from) ?>"></label>
    <label>Until <input type="date" name="date_to" required value="<?= e((string)$to) ?>"></label>
  </div>
  <label style="margin-top:10px">Who can see this
    <select name="visibility">
      <option value="public" <?= $vis==='public'?'selected':'' ?>>Anyone on RuinMyTrip</option>
      <option value="followers" <?= $vis==='followers'?'selected':'' ?>>People who follow me</option>
      <option value="private" <?= $vis==='private'?'selected':'' ?>>Only me</option>
    </select>
  </label>
  <p style="margin:12px 0 0"><button class="btn btn-primary" type="submit"><?= $current ? 'Update plan' : 'Share plan' ?></button></p>
</form>
<?php if ($current && $lockDestId > 0): ?>
<form method="post" action="<?= e(url('going/delete')) ?>" onsubmit="return confirm('Remove this plan?');" style="margin-top:8px">
  <?= csrf_field() ?>
  <input type="hidden" name="destination_id" value="<?= $lockDestId ?>">
  <button class="btn btn-ghost btn-sm" type="submit">Remove plan</button>
</form>
<?php endif; ?>
