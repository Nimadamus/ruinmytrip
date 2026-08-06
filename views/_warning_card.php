<?php
/**
 * One warning, as it appears in every list on the site.
 *
 * Kept as a single partial deliberately: the trust labelling (severity, verification status,
 * both dates, author) is the product's whole credibility story, and it must be impossible for
 * one list page to render a warning without it.
 *
 * @var array $w          warning row (with dest_name/dest_slug/author)
 * @var bool  $showDest   set false on a destination's own page, where the place is obvious
 */
$showDest = $showDest ?? true;
$sev = (int) $w['severity'];
$path = rmt_warning_path($w);
$isEd = rmt_is_editorial($w);
?>
<article class="warn-card<?= $sev >= 3 ? ' s' . $sev : '' ?>">
  <div class="warn-meta">
    <span class="sev <?= e(rmt_severity_class($sev)) ?>"><?= e(rmt_severity_label($sev)) ?></span>
    <span class="chip chip-cat"><?= rmt_warning_category_icon($w['category']) ?> <?= e(rmt_warning_category_label($w['category'])) ?></span>
    <?php if ($showDest && !empty($w['dest_slug'])): ?>
      <a href="<?= e(url('d/' . $w['dest_slug'])) ?>"><?= e($w['dest_name']) ?></a>
    <?php endif; ?>
    <?php if (!empty($w['location_detail'])): ?><span>· <?= e($w['location_detail']) ?></span><?php endif; ?>
  </div>

  <h3><a href="<?= e(url(ltrim($path, '/'))) ?>"><?= e($w['title']) ?></a></h3>

  <div class="warn-meta">
    <?php $v = (string) ($w['verification'] ?? 'unverified'); ?>
    <span class="trust trust-<?= e($v) ?>"><?= e(ucfirst($v)) ?></span>
    <?php if ($isEd): ?><?= rmt_editorial_badge() ?><?php endif; ?>
    <?php if (!empty($w['date_experienced'])): ?>
      <span>Experienced <?= e(rmt_experienced_label($w['date_experienced'])) ?></span>
    <?php endif; ?>
    <span>· Submitted <?= e(ago((string) $w['created_at'])) ?></span>
    <?php if (!empty($w['author']['username'])): ?>
      <span>· by <a href="<?= e(url('u/' . $w['author']['username'])) ?>">@<?= e($w['author']['username']) ?></a></span>
    <?php endif; ?>
    <?php if (rmt_warning_is_stale($w)): ?>
      <span class="chip chip-muted" title="Nothing has been checked on this report for over a year. Travel facts move fast.">Over a year old</span>
    <?php endif; ?>
  </div>

  <p class="warn-body"><?= e(mb_strimwidth(strip_tags((string) $w['body']), 0, 320, '…')) ?></p>

  <?php if (!empty($w['advice'])): ?>
    <p class="warn-advice"><b>How to avoid it:</b> <?= e(mb_strimwidth(strip_tags((string) $w['advice']), 0, 220, '…')) ?></p>
  <?php endif; ?>

  <div class="warn-foot">
    <?php if ($w['cost_impact_usd'] !== null && $w['cost_impact_usd'] !== ''): ?>
      <span class="warn-cost">Cost about $<?= number_format((int) $w['cost_impact_usd']) ?></span>
    <?php endif; ?>
    <?php if (!empty($w['provider_name'])): ?>
      <span class="muted">Involved: <?= e($w['provider_name']) ?></span>
    <?php endif; ?>
    <?php if (!empty($w['traveler_type'])): ?>
      <span class="muted"><?= e(rmt_traveler_type_label($w['traveler_type'])) ?></span>
    <?php endif; ?>
    <span class="muted"><?= (int) $w['helpful_count'] ?> found this helpful</span>
    <a href="<?= e(url(ltrim($path, '/'))) ?>">Read the full report →</a>
  </div>
</article>
