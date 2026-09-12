<?php /** @var array $d @var array $places @var array $counts @var int $total @var string $type @var string $label @var ?array $me @var array $savedMap @var array $saveCounts @var string $sort */ ?>
<div class="wrap">
  <p class="crumbs">
    <a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> /
    <a href="<?= e(url('d/'.$d['slug'])) ?>"><?= e($d['name']) ?></a> / Places
  </p>
  <h1 style="margin-top:6px"><?= e($label) ?> in <?= e($d['name']) ?>, <?= e($d['country']) ?></h1>
  <p class="muted" style="margin:0 0 4px">
    <?= (int) count($places) ?> <?= count($places) === 1 ? 'place' : 'places' ?><?= $type !== '' ? '' : ' we cover here' ?>.
    <?php /* The sentence about how ratings are worked out only earns its place once a rating is
             actually on the page. A city where nobody has rated anything yet was explaining the
             arithmetic of a number that appears nowhere on it. */ ?>
    <?php $rmt_rated = false; foreach ($places as $rmt_p) {
            if (($rmt_p['rating_avg'] ?? null) !== null && (int) ($rmt_p['review_count'] ?? 0) > 0) { $rmt_rated = true; break; }
          } ?>
    <?php if ($rmt_rated): ?>Ratings are the community average, and our own editorial reviews are never counted in them.<?php endif; ?>
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

  <?php /* The finer word: museums, parks, bars. Built from the categories this city actually holds,
           so a chip never opens an empty list, and scrolling sideways rather than wrapping into a
           block that pushes the places themselves off a phone screen. */ ?>
  <?php $catCounts = $catCounts ?? []; $cat = $cat ?? ''; ?>
  <?php /* When a category is chosen the page is about that category, and it should say so in the
           reader's words rather than leaving the heading to do all of it. */ ?>
  <?php if (($cat ?? '') !== ''): ?>
    <p class="hint" style="margin:0 0 12px"><?= (int) count($places) ?>
      <?= e(mb_strtolower($label)) ?> in <?= e($d['name']) ?>, from OpenStreetMap and from travelers here.
      <a href="<?= e(url('d/'.$d['slug'].'/places')) ?>">All places</a>.</p>
  <?php endif; ?>

  <?php if (count($catCounts) > 1): ?>
    <nav class="plan-filters" aria-label="Filter by category" style="margin:0 0 10px">
      <?php $rmt_base = 'd/'.$d['slug'].'/places'.($type !== '' ? '?type='.$type : ''); ?>
      <?php $rmt_sep = $type !== '' ? '&' : '?'; ?>
      <a class="chip<?= $cat === '' ? ' is-on' : '' ?>" href="<?= e(url($rmt_base)) ?>">All kinds</a>
      <?php foreach ($catCounts as $c): ?>
        <a class="chip<?= $cat === $c['slug'] ? ' is-on' : '' ?>"
           href="<?= e(url($rmt_base . ($cat === $c['slug'] ? '' : $rmt_sep.'cat='.$c['slug']))) ?>">
          <?= e((string) ($c['plural'] ?: $c['name'])) ?> <span class="hint"><?= (int) $c['n'] ?></span></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php /* Sorting is a way to read the same list. Every option is a plain link, so it works with
           no JavaScript and a crawler can follow it; all four canonicalise to the unsorted URL. */ ?>
  <?php if (count($places) > 1): ?>
    <?php /* Sorting is lighter than filtering and now looks it. Three rows of identical chips
             before the first place made the page read as a control panel, and the two rows that
             actually change WHAT you see should not compete with the one that changes the order. */ ?>
    <nav class="sort-row" aria-label="Sort" style="margin:0 0 18px">
      <span class="hint">Sort</span>
      <?php foreach (RMT_BROWSE_SORTS as $key => $sortLabel): ?>
        <?php $q = array_filter(['type' => $type, 'cat' => $cat, 'sort' => $key === 'best' ? '' : $key]); ?>
        <a class="sort-link<?= $sort === $key ? ' is-on' : '' ?>" rel="nofollow"
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
        look busy. If you stayed, ate, or booked something in <?= e($d['name']) ?>, you are the
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

  <?php /* The rest of the list, one tap away rather than twenty two screens of scrolling. A plain
           link so it works with no JavaScript and a crawler can follow it. */ ?>
  <?php if (!($showAll ?? false) && ($placesTotal ?? 0) > count($places)): ?>
    <p style="margin:18px 0 0">
      <a class="btn btn-ghost" href="<?= e(url('d/'.$d['slug'].'/places?'
          . http_build_query(array_filter(['type' => $type, 'cat' => $cat, 'sort' => $sort, 'all' => '1'])))) ?>">
        Show all <?= (int) $placesTotal ?> <?= e(mb_strtolower($label)) ?></a>
    </p>
  <?php endif; ?>
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
