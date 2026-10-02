<?php
/** @var array $p
 * "Been here?" with five stars (2026-10-02). Place pages are where Google sends people, and most
 * of them are planning; the few who have been get a one tap way in. Each star opens the review form
 * for this place with that rating already chosen; no account is needed to write it. Nothing is
 * stored by the tap itself: a rating only exists once somebody writes the review behind it.
 */
$rateBase = 'review/new?place=' . (int) $p['id'] . '&src=place_rate&rating=';
?>
<div class="rate-place">
  <span class="rate-q">Been to <?= e($p['name']) ?>?</span>
  <span class="rate-stars" role="group" aria-label="Rate <?= e($p['name']) ?>">
    <?php for ($i = 5; $i >= 1; $i--): ?>
      <a rel="nofollow" data-review-cta="place_rate" data-place-id="<?= (int) $p['id'] ?>"
         href="<?= e(url($rateBase . $i)) ?>" aria-label="<?= $i ?> of 5 stars" title="<?= $i ?> of 5">★</a>
    <?php endfor; ?>
  </span>
  <span class="rate-hint">Tap a star to rate it. No account needed to start.</span>
</div>
