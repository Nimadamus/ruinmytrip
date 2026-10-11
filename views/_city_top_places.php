<?php
/* The strongest places in this city, linked straight from it (app/design_v2.php,
   rmt_city_top_places): indexable under our own rule, most to read first. Moves them from four
   clicks from the home page to three. @var array $d @var list<array> $cityTop */
?>
<section class="v2-top-places" aria-labelledby="v2-top-h">
  <div class="v2-sec-head">
    <h2 class="v2-h2" id="v2-top-h">Top places in <?= e($d['name']) ?></h2>
    <a class="v2-link" href="<?= e(url('d/' . $d['slug'] . '/places')) ?>">All places →</a>
  </div>
  <div class="v2-place-grid">
    <?php foreach ($cityTop as $tp): $tpN = (int) $tp['traveler_reviews']; ?>
      <article class="v2-place">
        <a class="v2-place-main" href="<?= e(url('p/' . $tp['slug'])) ?>" data-cta="city_top_place">
          <span class="v2-place-glyph" aria-hidden="true"><?= rmt_design_type_glyph((string) $tp['type']) ?></span>
          <span class="v2-place-txt">
            <b><?= e((string) $tp['name']) ?></b>
            <small><?= e(rmt_place_type_label((string) $tp['type'])) ?><?php if (trim((string) $tp['neighborhood']) !== ''): ?> · <?= e((string) $tp['neighborhood']) ?><?php endif; ?></small>
            <small class="v2-place-score"><?php if ($tpN > 0): ?>★ <?= number_format((float) $tp['traveler_avg'], 1) ?> · <?= $tpN ?> <?= $tpN === 1 ? 'review' : 'reviews' ?><?php elseif ((int) $tp['has_editorial'] > 0): ?>RuinMyTrip guide<?php else: ?>Hours and directions<?php endif; ?></small>
          </span>
        </a>
        <a class="v2-place-rate" rel="nofollow" data-cta="city_rate" href="<?= e(url('review/new?place=' . (int) $tp['id'] . '&src=destination')) ?>">Rate</a>
      </article>
    <?php endforeach; ?>
  </div>
</section>
