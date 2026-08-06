<?php
/** @var array $rows @var int $total @var string $status @var array $counts @var int $page @var array $f */
$here = 'admin/warnings';
$returnTo = '/admin/warnings?status=' . rawurlencode($status);
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Warning moderation</h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <div class="filter-chips">
    <?php foreach (['pending' => 'Awaiting review', 'needs_revision' => 'Revision requested', 'approved' => 'Published',
                    'rejected' => 'Rejected', 'draft' => 'Drafts', 'any' => 'Everything'] as $k => $label): ?>
      <a class="<?= $status === $k ? 'on' : '' ?>" href="<?= e(url('admin/warnings?status=' . $k)) ?>">
        <?= e($label) ?><?= isset($counts[$k]) ? ' (' . (int) $counts[$k] . ')' : '' ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php /* The status chips above are their own control, so the filter bar carries the current
           status through as a hidden field rather than silently dropping it on Apply. */ ?>
  <?php $action = url('admin/warnings'); $showCategory = true; $hidden = ['status' => $status];
        include BASE_PATH . '/views/_warning_filters.php'; ?>

  <p class="muted"><?= number_format($total) ?> <?= $total === 1 ? 'warning' : 'warnings' ?>.</p>
  <?php if ($rows): ?>
    <?php foreach ($rows as $w) { include __DIR__ . '/_queue_row.php'; } ?>
    <?php $perPage = 25; $base = '/admin/warnings'; include BASE_PATH . '/views/_pager.php'; ?>
  <?php else: ?>
    <p class="muted">Nothing here.</p>
  <?php endif; ?>
  <div style="height:50px"></div>
</div>
