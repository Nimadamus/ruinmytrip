<?php
/** @var array $dests @var array $errors @var ?array $w */
$formKey = 'warning_new';
$action  = url('warning/new');
$isEdit  = false;
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('warnings')) ?>">Warnings</a> / Share a warning</p>
</div>
<?php include __DIR__ . '/_warning_form.php'; ?>
