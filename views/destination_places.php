<?php /** @var array $d @var array $places @var array $counts @var int $total @var string $type @var string $label @var ?array $me @var array $savedMap @var array $saveCounts @var string $sort */ ?>
<div class="wrap">
  <p class="crumbs">
    <a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> /
    <a href="<?= e(url('d/'.$d['slug'])) ?>"><?= e($d['name']) ?></a> / Places
  </p>
  <h1 style="margin-top:6px"><?= e($label) ?> in <?= e($d['name']) ?>, <?= e($d['country']) ?></h1>
  <p class="muted" style="margin:0 0 4px">
    <?= (int) count($places) ?> <?= count($places) === 1 ? 'place' : 'places' ?><?= $type !== '' ? '' : ' we cover here' ?>.
    Ratings are the community average &mdash; our own editorial reviews are never counted in them.
  </p>

  <?php /* Kind first, because "where do I eat" is a different question from "where do I stay", and
           a kind with nothing in it is not offered. */ ?>
  <?php if ($counts): ?>
    <nav class="chip-row" aria-label="Filter by kind" style="margin:14px 0 10px">
      <a class="chip<?= $type === '' ? ' is-on' : '' ?>" href="<?= e(url('d/'.$d['slug'].'/places')) ?>">
        All <span class="chip-count"><?= (int) $total ?></span></a>
      <?php foreach (RMT_PLACE_TYPES as $t): if (empty($counts[$t])) continue; ?>
        <a class="chip<?= $type === $t ? ' is-on' : '' ?>"
           href="<?= e(url('d/'.$d['slug'].'/places?type='.$t)) ?>">
          <?= e(rmt_place_type_label($t, true)) ?> <span class="chip-count"><?= (int) $counts[$t] ?></span></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php /* Sorting is a way to read the same list. Every option is a plain link, so it works with
           no JavaScript and a crawler can follow it; all four canonicalise to the unsorted URL. */ ?>
  <?php if (count($places) > 1): ?>
    <nav class="chip-row" aria-label="Sort" style="margin:0 0 20px">
      <?php foreach (RMT_BROWSE_SORTS as $key => $sortLabel): ?>
        <?php $q = array_filter(['type' => $type, 'sort' => $key === 'best' ? '' : $key]); ?>
        <a class="chip<?= $sort === $key ? ' is-on' : '' ?>" rel="nofollow"
           href="<?= e(url('d/'.$d['slug'].'/places') . ($q ? '?' . http_build_query($q) : '')) ?>">
          <?= e($sortLabel) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if (!$places): ?>
    <div class="empty-cta" style="margin:20px 0">
      <h3>Nothing here yet<?= $type ? ' in this category' : '' ?>.</h3>
      <p class="muted" style="margin:0">
        Places appear the moment somebody reviews one. We do not import listings or invent them to
        look busy &mdash; if you stayed, ate, or booked something in <?= e($d['name']) ?>, you are the
        first entry.
      </p>
      <p style="margin:16px 0 0">
        <a class="btn btn-accent" data-review-cta="browse" data-destination-id="<?= (int) $d['id'] ?>"
           href="<?= e(url('review/new?destination='.(int)$d['id'].'&src=browse')) ?>">Write the first review</a>
      </p>
    </div>
  <?php endif; ?>

  <?php if ($places): ?>
    <div class="place-row" style="padding-bottom:26px">
      <?php foreach ($places as $card): ?>
        <?php
          $card['saved'] = !empty($savedMap[(int) $card['id']]);
          $card['save_count'] = (int) ($saveCounts[(int) $card['id']] ?? 0);
          $cardActions = true;   // this page is for choosing, so each card carries its actions
          include __DIR__ . '/_place_card.php';
        ?>
      <?php endforeach; ?>
    </div>

    <?php /* The contribution prompt belongs at the end of a list somebody has just read: they have
             seen what is here and know whether they have something to add. */ ?>
    <div class="empty-cta" style="margin:0 0 50px">
      <h3>Been to one of these?</h3>
      <p class="muted" style="margin:0">
        <?= (int) count($places) ?> <?= count($places) === 1 ? 'place' : 'places' ?> in
        <?= e($d['name']) ?>, and what they are actually like comes from travelers who went.
        Say what it cost and what you wish you had known.
      </p>
      <p style="margin:16px 0 0">
        <a class="btn btn-accent" data-review-cta="browse" data-destination-id="<?= (int) $d['id'] ?>"
           href="<?= e(url('review/new?destination='.(int)$d['id'].'&src=browse')) ?>">Write a review</a>
        <a class="btn btn-ghost" href="<?= e(url('d/'.$d['slug'])) ?>">Back to <?= e($d['name']) ?></a>
      </p>
    </div>
  <?php endif; ?>
</div>
