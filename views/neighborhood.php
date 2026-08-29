<?php /** @var array $me @var array $savedMap @var array $saveCounts @var array $d @var array $nb @var array $byType @var array $places @var ?string $type @var int $total */ ?>
<div class="wrap">
  <p class="crumbs">
    <a href="<?= e(url('destinations')) ?>">Destinations</a> /
    <a href="<?= e(url('d/' . $d['slug'])) ?>"><?= e((string) $d['name']) ?></a> /
    <?= e((string) $nb['canonical_name']) ?>
  </p>

  <h1 style="margin:.2rem 0 .3rem"><?= e((string) $nb['canonical_name']) ?></h1>
  <p class="muted" style="margin:0 0 6px">
    <?php /* The local-language name is shown when it differs, because it is the name on the street
             signs and the one a traveler will actually see when they get there. */ ?>
    <?php if (!empty($nb['local_name']) && $nb['local_name'] !== $nb['canonical_name']): ?>
      <?= e((string) $nb['local_name']) ?> &middot;
    <?php endif; ?>
    <?= (int) $total ?> <?= $total === 1 ? 'place' : 'places' ?> in
    <a href="<?= e(url('d/' . $d['slug'])) ?>"><?= e((string) $d['name']) ?></a><?php
      if (!empty($d['country'])): ?>, <?= e((string) $d['country']) ?><?php endif; ?>
  </p>
  <?php if (!empty($nb['blurb'])): ?>
    <p style="margin:0 0 16px;max-width:64ch"><?= e((string) $nb['blurb']) ?></p>
  <?php endif; ?>

  <?php /* Filters are the type counts themselves, so a row that would return nothing is never
           offered. Every one is a real link rather than a script toggle: this is how the places
           in an area get crawled. */ ?>
  <div class="chip-row" style="margin:14px 0 22px">
    <a class="chip<?= $type === null ? ' chip-on' : '' ?>" href="<?= e(url('d/' . $d['slug'] . '/n/' . $nb['slug'])) ?>">
      All <span class="chip-count"><?= (int) $total ?></span></a>
    <?php foreach ($byType as $t => $n): ?>
      <a class="chip<?= $type === $t ? ' chip-on' : '' ?>"
         href="<?= e(url('d/' . $d['slug'] . '/n/' . $nb['slug'] . '?type=' . $t)) ?>">
        <?= e(rmt_place_type_label((string) $t)) ?><span class="chip-count"><?= (int) $n ?></span></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$places): ?>
    <p class="muted">Nothing here yet.</p>
  <?php else: ?>
    <div class="grid g-3">
      <?php foreach ($places as $card): ?>
        <?php
          $card['saved'] = !empty($savedMap[(int) $card['id']]);
          $card['save_count'] = (int) ($saveCounts[(int) $card['id']] ?? 0);
          $cardActions = true;
          include __DIR__ . '/_place_card.php';
        ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <p style="margin:26px 0 40px">
    <a href="<?= e(url('d/' . $d['slug'] . '/places')) ?>">All places in <?= e((string) $d['name']) ?> &rarr;</a>
  </p>
</div>
