<?php
/**
 * GET /warnings — every published warning, filterable.
 * @var array $rows @var int $total @var array $f @var int $page @var int $perPage @var array $byCat
 */
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Warnings</p>
  <div class="section-head" style="margin-top:6px">
    <div>
      <p class="eyebrow">What travelers actually hit</p>
      <h1 style="margin:0">Travel warnings</h1>
      <p class="muted" style="margin:.4rem 0 0;max-width:64ch">
        First-hand reports of the things that ruin trips — scams, fees nobody mentions, transport traps,
        closures, crowds. Every one is read by a moderator before it appears here, and every one shows when
        it happened, when it was submitted, and whether it has been independently verified.
      </p>
    </div>
    <a class="btn btn-accent" href="<?= e(url('warning/new')) ?>">Share a warning</a>
  </div>

  <div class="filter-chips" style="margin-top:18px">
    <a class="<?= empty($f['category']) ? 'on' : '' ?>" href="<?= e(url('warnings')) ?>">All<?= $total ? ' (' . number_format($total) . ')' : '' ?></a>
    <?php foreach (RMT_WARNING_CATEGORIES as $k => $c): ?>
      <a class="<?= ($f['category'] ?? '') === $k ? 'on' : '' ?>" href="<?= e(url('warnings/' . $k)) ?>">
        <?= $c['icon'] ?> <?= e($c['label']) ?><?= !empty($byCat[$k]) ? ' ' . (int) $byCat[$k] : '' ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php $action = url('warnings'); include __DIR__ . '/_warning_filters.php'; ?>

  <?php if ($rows): ?>
    <p class="muted" style="margin:0 0 14px"><?= number_format($total) ?> <?= $total === 1 ? 'warning' : 'warnings' ?> match.</p>
    <?php foreach ($rows as $w) { include __DIR__ . '/_warning_card.php'; } ?>
    <?php $base = '/warnings'; include __DIR__ . '/_pager.php'; ?>
  <?php else: ?>
    <div class="empty-cta">
      <h3><?= $total === 0 && empty($f['q']) && empty($f['category']) ? 'No warnings published yet' : 'Nothing matches those filters' ?></h3>
      <p class="muted">
        <?php if ($total === 0 && empty($f['q'])): ?>
          Every submission is read by a person before it appears, so this list starts honest rather than full.
          If something cost you money or a day of your trip, that is exactly what belongs here.
        <?php else: ?>
          Try widening the severity or clearing the season filter. You can also
          <a href="<?= e(url('explore')) ?>">browse destinations</a> and read their researched risk reports.
        <?php endif; ?>
      </p>
      <a class="btn btn-accent" style="margin-top:12px" href="<?= e(url('warning/new')) ?>">Share a warning</a>
    </div>
  <?php endif; ?>
  <div style="height:40px"></div>
</div>
