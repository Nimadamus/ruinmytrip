<?php
/** @var array $w @var array $dests @var array $errors @var array $photos */
$formKey = 'warning_edit_' . (int) $w['id'];
$action  = url('warning/' . (int) $w['id'] . '/edit');
$isEdit  = true;
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('dashboard?tab=reports')) ?>">Your reports</a> / Edit</p>
  <?php if ($photos): ?>
    <p class="muted" style="margin-top:10px"><?= count($photos) ?> photo<?= count($photos) === 1 ? '' : 's' ?> already attached; any you add here are appended.</p>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/_warning_form.php'; ?>
<div class="wrap" style="max-width:760px;margin-bottom:50px">
  <form method="post" action="<?= e(url('warning/' . (int) $w['id'] . '/delete')) ?>">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-sm" data-confirm="Delete this warning permanently?">Delete this warning</button>
  </form>
</div>
