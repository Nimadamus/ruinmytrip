<?php
/**
 * One place card, used by every destination discovery row so they cannot drift apart.
 *
 * @var array $card  a place row: id, slug, name, type, plus any of category_name, neighborhood,
 *                   rating_avg, review_count, price_level, cover_url
 *
 * Each line is dropped when the data behind it is absent. A place we hold nothing about renders as
 * a name and a kind, which is honest and still useful; it never renders a row of dashes or a grey
 * box where a photograph should be.
 */
$rating = isset($card['rating_avg']) ? (float) $card['rating_avg'] : null;
$count  = isset($card['review_count']) ? (int) $card['review_count'] : 0;
$price  = rmt_place_price_label(isset($card['price_level']) && $card['price_level'] !== null ? (int) $card['price_level'] : null);
$meta   = array_filter([
    $card['category_name'] ?? rmt_place_type_label((string) ($card['type'] ?? '')),
    $card['neighborhood'] ?? null,
    $price,
]);
?>
<article class="card place-card">
  <?php if (!empty($card['cover_url'])): ?>
    <a href="<?= e(url('p/' . $card['slug'])) ?>" tabindex="-1" aria-hidden="true">
      <img class="card-media" loading="lazy" src="<?= e(abs_url((string) $card['cover_url'])) ?>"
           alt="" style="aspect-ratio:16/10;object-fit:cover;width:100%">
    </a>
  <?php endif; ?>
  <div class="card-body" style="padding:12px 14px">
    <h3 style="margin:0 0 3px;font-size:1rem;line-height:1.3">
      <a href="<?= e(url('p/' . $card['slug'])) ?>"><?= e((string) $card['name']) ?></a>
    </h3>
    <?php if ($meta): ?>
      <p class="muted" style="margin:0 0 5px;font-size:.86rem"><?= e(implode(' · ', $meta)) ?></p>
    <?php endif; ?>
    <?php /* A rating is only shown with the number of people behind it. A score with no
             denominator is the kind of confident-looking figure this site does not print. */ ?>
    <?php if ($rating !== null && $count > 0): ?>
      <p style="margin:0;font-size:.88rem">
        <span class="stars"><?= stars((int) round($rating)) ?></span>
        <strong><?= e(number_format($rating, 1)) ?></strong>
        <span class="muted">· <?= $count ?> <?= $count === 1 ? 'review' : 'reviews' ?></span>
      </p>
    <?php endif; ?>
  </div>
</article>
