<?php
/**
 * "Report outdated information".
 *
 * Travel facts rot faster than almost any other kind of content — a price, a visa rule, a metro
 * closure. Showing a "last reviewed" date without giving the reader a way to say "this is wrong
 * now" makes the date decorative. This control is open to logged-out visitors on purpose: the
 * person who just found out the museum reopened is usually not a member.
 *
 * @var string $outdatedTarget  warning|risk_section|destination|landing_page|faq
 * @var int    $outdatedId
 * @var string $outdatedReturn
 */
?>
<form method="post" action="<?= e(url('outdated')) ?>" class="inline-form">
  <?= csrf_field() ?>
  <input type="hidden" name="target_type" value="<?= e($outdatedTarget) ?>">
  <input type="hidden" name="target_id" value="<?= (int) $outdatedId ?>">
  <input type="hidden" name="return" value="<?= e($outdatedReturn) ?>">
  <button type="submit" class="btn btn-ghost btn-sm"
          style="font-size:.75rem;padding:.15rem .6rem;font-weight:600"
          data-confirm="Flag this as out of date so a moderator re-checks it?">Report outdated info</button>
</form>
