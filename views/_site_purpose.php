<?php
/**
 * One sentence on the hub pages saying what the site is for, in plain words, with a link to each
 * of the three jobs. A first time visitor landing on /reviews from a search should not have to
 * guess that the same site also lists cities and finds travel companions.
 *
 * @var string $purposeHere which hub is showing it: reviews, explore or buddies (not linked to itself)
 */
$purposeHere = $purposeHere ?? '';
$purposeLink = static fn(string $key, string $path, string $text): string =>
    $purposeHere === $key ? '<b>' . e($text) . '</b>' : '<a href="' . e(url($path)) . '">' . e($text) . '</a>';
?>
<p class="hint site-purpose" style="margin:6px 0 14px;max-width:62em">RuinMyTrip does three things:
  <?= $purposeLink('reviews', 'reviews', 'honest reviews') ?> from travelers who went, bad parts included;
  <?= $purposeLink('explore', 'explore', 'city pages') ?> with places and current 2026 costs to help you pick where to go;
  and <?= $purposeLink('buddies', 'buddies', 'travel companions') ?>, people heading to the same place on your dates.
  Our own researched write ups are always labelled <a href="<?= e(url('editorial-policy')) ?>">Editorial or Official Review</a>.</p>
