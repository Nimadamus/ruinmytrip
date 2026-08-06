<?php
/**
 * Shared admin navigation. `$here` is the path segment of the current screen so it can highlight.
 * @var string $here
 */
$here = $here ?? '';
?>
<nav class="admin-nav" aria-label="Admin sections">
  <?php foreach (rmt_admin_nav() as [$label, $path]): ?>
    <a class="<?= $here === $path ? 'on' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <a href="<?= e(url('admin/reports')) ?>">Abuse reports</a>
</nav>
