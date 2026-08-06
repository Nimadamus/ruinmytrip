<?php
/**
 * One of the ten "what can ruin a trip" categories.
 *
 * A real page rather than a redirect into a query string: these are the pages people search for
 * and link to, so they get their own copy, their own worst-affected-destination list, and their
 * own editorial guides.
 *
 * @var string $key @var array $cat @var array $rows @var int $total @var array $f
 * @var int $page @var int $perPage @var array $dests @var array $guides
 */
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('warnings')) ?>">Warnings</a> / <?= e($cat['label']) ?></p>

  <div class="section-head" style="margin-top:6px">
    <div>
      <p class="eyebrow"><span aria-hidden="true"><?= $cat['icon'] ?></span> What can ruin a trip</p>
      <h1 style="margin:0"><?= e($cat['label']) ?></h1>
      <p class="muted" style="margin:.4rem 0 0;max-width:64ch"><?= e($cat['blurb']) ?></p>
    </div>
    <a class="btn btn-accent" href="<?= e(url('warning/new?category=' . $key)) ?>">Report this</a>
  </div>

  <?php if ($dests): ?>
    <section style="margin-top:26px">
      <h2 style="font-size:1.2rem">Where this comes up most</h2>
      <div class="filter-chips">
        <?php foreach ($dests as $dd): ?>
          <a href="<?= e(url('d/' . $dd['slug'] . '/warnings?category=' . $key)) ?>">
            <?= e($dd['name']) ?> <b><?= (int) $dd['c'] ?></b>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($guides): ?>
    <section style="margin-top:20px">
      <h2 style="font-size:1.2rem">Researched guides on this</h2>
      <div class="grid g-3">
        <?php foreach ($guides as $g): ?>
          <a class="cat-tile" href="<?= e(url($g['slug'])) ?>">
            <b><?= e($g['h1']) ?></b>
            <span><?= e(mb_strimwidth((string) $g['meta_description'], 0, 110, '…')) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section style="margin-top:30px">
    <h2 style="font-size:1.2rem">Traveler reports</h2>
    <?php $action = url('warnings/' . $key); $showCategory = false; include __DIR__ . '/_warning_filters.php'; ?>

    <?php if ($rows): ?>
      <p class="muted" style="margin:0 0 14px"><?= number_format($total) ?> <?= $total === 1 ? 'report' : 'reports' ?>.</p>
      <?php foreach ($rows as $w) { include __DIR__ . '/_warning_card.php'; } ?>
      <?php $base = '/warnings/' . $key; include __DIR__ . '/_pager.php'; ?>
    <?php else: ?>
      <div class="empty-cta">
        <h3>No <?= e(mb_strtolower($cat['label'])) ?> reports yet</h3>
        <p class="muted">Nobody has filed one in this category. If it happened to you, writing it down here is
          the difference between the next traveler being caught out and not.</p>
        <a class="btn btn-accent" style="margin-top:12px" href="<?= e(url('warning/new?category=' . $key)) ?>">Report a <?= e(mb_strtolower($cat['label'])) ?> problem</a>
      </div>
    <?php endif; ?>
  </section>

  <nav class="filter-chips" style="margin-top:30px" aria-label="Other categories">
    <?php foreach (RMT_WARNING_CATEGORIES as $k2 => $c2): if ($k2 === $key) continue; ?>
      <a href="<?= e(url('warnings/' . $k2)) ?>"><?= $c2['icon'] ?> <?= e($c2['label']) ?></a>
    <?php endforeach; ?>
  </nav>
  <div style="height:40px"></div>
</div>
