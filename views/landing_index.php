<?php
/**
 * The index of every published editorial guide, grouped by template.
 * @var array $byTemplate @var string $tplFilter
 */
$templates = rmt_landing_templates();
$total = 0;
foreach ($byTemplate as $rows) $total += count($rows);
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Warning guides</p>

  <div class="section-head" style="margin-top:6px">
    <div>
      <p class="eyebrow">Researched, dated, sourced</p>
      <h1 style="margin:0">Travel warning guides</h1>
      <p class="muted" style="margin:.4rem 0 0;max-width:66ch">
        Written guides to the specific things that go wrong at a destination — the scams, the fees nobody
        quotes, the months to avoid, the airport traps, the transport mistakes. Each one carries its sources
        and the date it was last reviewed, and each links to the live traveler reports underneath it.
      </p>
    </div>
  </div>

  <?php if ($total === 0): ?>
    <div class="empty-cta">
      <h3>No guides published yet</h3>
      <p class="muted">Guides are written and reviewed by hand — we do not auto-generate a page per destination,
        because a page that restates a database row helps nobody. In the meantime, every destination has a
        risk report.</p>
      <a class="btn btn-primary" style="margin-top:12px" href="<?= e(url('explore')) ?>">Browse destinations</a>
    </div>
  <?php else: ?>
    <nav class="filter-chips" style="margin-top:18px" aria-label="Guide types">
      <a class="<?= $tplFilter === '' ? 'on' : '' ?>" href="<?= e(url('warning-guides')) ?>">All (<?= $total ?>)</a>
      <?php foreach ($templates as $k => $t): if (empty($byTemplate[$k])) continue; ?>
        <a class="<?= $tplFilter === $k ? 'on' : '' ?>" href="<?= e(url('warning-guides?type=' . $k)) ?>">
          <?= e($t['label']) ?> (<?= count($byTemplate[$k]) ?>)
        </a>
      <?php endforeach; ?>
    </nav>

    <?php foreach ($templates as $k => $t): if (empty($byTemplate[$k])) continue; ?>
      <section style="margin-top:26px">
        <h2 style="font-size:1.2rem"><?= e($t['label']) ?></h2>
        <div class="grid g-3">
          <?php foreach ($byTemplate[$k] as $p): ?>
            <a class="cat-tile" href="<?= e(url($p['slug'])) ?>">
              <b><?= e($p['h1']) ?></b>
              <span><?= e(mb_strimwidth((string) $p['meta_description'], 0, 120, '…')) ?></span>
              <?php if (!empty($p['last_reviewed_at'])): ?>
                <span class="n">Reviewed <?= e(date('M Y', strtotime((string) $p['last_reviewed_at']))) ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
  <div style="height:50px"></div>
</div>
