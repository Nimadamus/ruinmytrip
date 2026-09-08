<?php
/** A strip that turns a search visitor into a member.
 *
 *  @var string $destSlug @var string $destName @var ?array $me
 *
 *  Every page that ranks on this site answers a question about a building, and then offers the
 *  reader nothing to do but leave. The one thing they cannot get from the ten other pages on that
 *  results screen is the people: who is going to this city, and when. So that is the offer, and it
 *  is made in the reader's own context rather than as a generic "sign up for our newsletter".
 */
$rmt_mt_here = '/d/' . $destSlug . '/travelers';
?>
<div class="callout" style="margin:22px 0">
  <b>Going to <?= e($destName) ?>?</b>
  See who else will be there and when, join a meetup, or ask travelers who have been.
  <?php if ($me): ?>
    <a href="<?= e(url(ltrim($rmt_mt_here, '/'))) ?>">Travelers in <?= e($destName) ?> &rarr;</a>
  <?php else: ?>
    <a href="<?= e(url(ltrim($rmt_mt_here, '/'))) ?>">Travelers in <?= e($destName) ?></a>
    &middot; <a href="<?= e(url('register?return=' . rawurlencode($rmt_mt_here))) ?>"><b>Join free</b></a>
  <?php endif; ?>
</div>
