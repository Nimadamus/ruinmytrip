<?php
/** @var array $rateDest  id, name of the city
 * "Been to Phuket?" with five stars for a city (2026-10-17). The blog posts are what search lands
 * on, and the visits are 2 to 10 seconds long, so the one way in sits under the summary where it is
 * read, not under the article where nobody arrives. Each star opens the review form about the city
 * itself with that rating chosen; nothing is stored by the tap, and no account is needed to write.
 * Same look as the place stars (views/_rate_place.php).
 */
$rateSrc  = $rateSrc ?? 'blog_rate';
$rateBase = 'review/new?destination=' . (int) $rateDest['id'] . '&about=destination&src=' . rawurlencode($rateSrc) . '&rating=';
?>
<div class="rate-place">
  <span class="rate-q">Been to <?= e((string) $rateDest['name']) ?>?</span>
  <span class="rate-stars" role="group" aria-label="Rate <?= e((string) $rateDest['name']) ?>">
    <?php for ($i = 5; $i >= 1; $i--): ?>
      <a rel="nofollow" data-review-cta="<?= e($rateSrc) ?>" data-destination-id="<?= (int) $rateDest['id'] ?>"
         href="<?= e(url($rateBase . $i)) ?>" aria-label="<?= $i ?> of 5 stars" title="<?= $i ?> of 5">★</a>
    <?php endfor; ?>
  </span>
  <span class="rate-hint">Tap a star and tell the next traveler how it went. No account needed to start.</span>
</div>
