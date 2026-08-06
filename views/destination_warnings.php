<?php
/**
 * Every warning for one destination, with the full filter set.
 * @var array $d @var array $rows @var int $total @var array $f @var int $page @var int $perPage @var array $counts
 */
$destUrl = url('d/' . $d['slug']);
?>
<div class="wrap">
  <p class="crumbs">
    <a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Destinations</a> /
    <a href="<?= e($destUrl) ?>"><?= e($d['name']) ?></a> / Warnings
  </p>

  <div class="section-head" style="margin-top:6px">
    <div>
      <p class="eyebrow"><?= e($d['country']) ?></p>
      <h1 style="margin:0"><?= e($d['name']) ?> travel warnings</h1>
      <p class="muted" style="margin:.4rem 0 0;max-width:64ch">
        First-hand reports from travelers, reviewed before publication. Sort by how helpful others found them,
        how bad it was, or the month it happened — seasonal problems only make sense against a date.
      </p>
    </div>
    <a class="btn btn-accent" href="<?= e(url('warning/new?destination=' . (int) $d['id'])) ?>">Share a warning</a>
  </div>

  <div class="filter-chips" style="margin-top:18px">
    <a class="<?= empty($f['category']) ? 'on' : '' ?>" href="<?= e($destUrl . '/warnings') ?>">All</a>
    <?php foreach (RMT_WARNING_CATEGORIES as $k => $c): if (empty($counts[$k])) continue; ?>
      <a class="<?= ($f['category'] ?? '') === $k ? 'on' : '' ?>" href="<?= e($destUrl . '/warnings?category=' . $k) ?>">
        <?= $c['icon'] ?> <?= e($c['label']) ?> <b><?= (int) $counts[$k]['c'] ?></b>
      </a>
    <?php endforeach; ?>
  </div>

  <?php $action = $destUrl . '/warnings'; include __DIR__ . '/_warning_filters.php'; ?>

  <?php if ($rows): ?>
    <p class="muted" style="margin:0 0 14px"><?= number_format($total) ?> <?= $total === 1 ? 'warning' : 'warnings' ?> match.</p>
    <?php foreach ($rows as $w) { $showDest = false; include __DIR__ . '/_warning_card.php'; } ?>
    <?php $base = '/d/' . $d['slug'] . '/warnings'; include __DIR__ . '/_pager.php'; ?>
  <?php else: ?>
    <div class="empty-cta">
      <h3>Nothing matches</h3>
      <p class="muted">No warnings for <?= e($d['name']) ?> match those filters. Try clearing the season or
        severity filter, or read the <a href="<?= e($destUrl) ?>">researched risk report</a> instead.</p>
      <a class="btn btn-accent" style="margin-top:12px" href="<?= e(url('warning/new?destination=' . (int) $d['id'])) ?>">Share a warning</a>
    </div>
  <?php endif; ?>

  <p style="margin-top:20px"><a href="<?= e($destUrl) ?>">← Back to the <?= e($d['name']) ?> risk report</a></p>
  <div style="height:40px"></div>
</div>
