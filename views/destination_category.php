<?php /** @var array $d @var array $places @var array $counts @var string $type @var string $sort
        @var string $heading @var ?array $me @var array $savedMap @var array $saveCounts
        @var array $areas @var array $verdict */ ?>
<div class="wrap">
  <p class="crumbs">
    <a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> /
    <a href="<?= e(url('d/'.$d['slug'])) ?>"><?= e((string) $d['name']) ?></a> /
    <?= e($heading) ?>
  </p>

  <h1 style="margin:.2rem 0 .3rem"><?= e($heading) ?></h1>

  <?php /* The factual line, and only the factual line. No paragraph about how Paris is a beautiful
           city with many wonderful hotels: the reader came for the hotels, the inventory is the
           value, and filler is what makes a landing page look like a landing page. */ ?>
  <p class="muted" style="margin:0 0 18px">
    <?= (int) ($counts[$type] ?? 0) ?> in <?= e((string) $d['name']) ?><?php
      if (!empty($d['country'])): ?>, <?= e((string) $d['country']) ?><?php endif; ?>.
    Addresses, opening hours and prices where we hold them, and reviews from travelers who went.
  </p>

  <?php /* The other categories in this city, and the same city's other real pages. Crawlable, and
           useful: somebody who wanted hotels frequently wants dinner too. Only categories that
           actually have places are offered. */ ?>
  <div class="chip-row" style="margin:0 0 18px">
    <?php foreach (RMT_PLACE_TYPES as $t): ?>
      <?php if ((int) ($counts[$t] ?? 0) < 1 || $t === $type) continue; ?>
      <?php if (!rmt_indexable('category', ['place_count' => (int) $counts[$t]])['ok']) continue; ?>
      <a class="chip" href="<?= e(url('d/'.$d['slug'].'/'.rmt_category_slug($t))) ?>">
        <?= e(rmt_category_heading($t, (string) $d['name'])) ?>
        <span class="chip-count"><?= (int) $counts[$t] ?></span></a>
    <?php endforeach; ?>
    <a class="chip" href="<?= e(url('d/'.$d['slug'].'/places')) ?>">Everything in <?= e((string) $d['name']) ?>
      <span class="chip-count"><?= (int) array_sum($counts) ?></span></a>
  </div>

  <?php if ($areas): ?>
    <?php /* Neighborhoods, where they exist. This is the lateral path a reader most often wants
             from a city-wide list -- "yes, but which part of town" -- and it is how the crawler
             reaches the area pages from a page that is in the index. */ ?>
    <div class="chip-row" style="margin:0 0 22px">
      <?php foreach (array_slice($areas, 0, 8) as $nb): ?>
        <a class="chip" href="<?= e(url('d/'.$d['slug'].'/n/'.$nb['slug'])) ?>"><?= e((string) $nb['name']) ?>
          <span class="chip-count"><?= (int) $nb['places'] ?></span></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php /* Sorting is a way to read this list, not a different page: every ordering carries the
           same canonical and says noindex, so the four of them cannot compete with each other. */ ?>
  <p class="hint" style="margin:0 0 16px">
    Sort:
    <?php foreach (RMT_BROWSE_SORTS as $key => $label): ?>
      <a href="<?= e(url('d/'.$d['slug'].'/'.rmt_category_slug($type)) . ($key === 'best' ? '' : '?sort='.$key)) ?>"<?= $key === $sort ? ' style="font-weight:700"' : '' ?>><?= e($label) ?></a><?= $key === array_key_last(RMT_BROWSE_SORTS) ? '' : ' · ' ?>
    <?php endforeach; ?>
  </p>

  <div class="place-row" style="padding-bottom:26px">
    <?php foreach ($places as $card): ?>
      <?php
        $card['saved'] = !empty($savedMap[(int) $card['id']]);
        $card['save_count'] = (int) ($saveCounts[(int) $card['id']] ?? 0);
        $cardActions = true;
        include __DIR__ . '/_place_card.php';
      ?>
    <?php endforeach; ?>
  </div>

  <div class="empty-cta" style="margin:0 0 50px">
    <h3>Been to one of these?</h3>
    <p class="muted" style="margin:0">
      What they are actually like comes from travelers who went. Say what it cost and what you wish
      you had known.
    </p>
    <p style="margin:14px 0 0">
      <a class="btn btn-accent" data-review-cta="browse" href="<?= e(url('contribute')) ?>">Review a place you went to</a>
    </p>
  </div>
</div>
