<?php
/**
 * "Going to X? Get your before you go checklist" (app/trip_checklist.php). The one reason on a
 * reference page to save a trip: the same sourced items, as a personal list you tick off.
 * Shown only when the city has items, with the real count.
 *
 * @var string $clDest  destination slug
 * @var string $clName  city name
 */
$clN = $clDest !== '' ? count(rmt_trip_checklist_items($clDest)) : 0;
if ($clN < 3) return;
$clDestRow = q_one('SELECT id FROM destinations WHERE slug = ?', [$clDest]);
if (!$clDestRow) return;
?>
<section class="card cl-cta" style="margin:18px 0;border-left:4px solid #b3261e"><div class="card-body">
  <h2 class="h-card" style="margin:0 0 6px">Going to <?= e($clName) ?>? Get your before you go checklist</h2>
  <p class="hint" style="margin:0 0 10px"><?= $clN ?> things to sort before you travel, from the tourist tax to the scams to know, each from an official source. Add your dates and tick them off as you go. Free.</p>
  <a class="btn btn-accent btn-sm" data-cta="checklist_cta" data-destination-id="<?= (int) $clDestRow['id'] ?>" href="<?= e(url('plan?d=' . rawurlencode($clDest) . '&cta=checklist_cta')) ?>">Make my checklist</a>
</div></section>
