<?php
/**
 * The one line that turns a search visitor into a traveler on this site.
 *
 * @var string      $dsSlug  destination slug
 * @var string      $dsName  destination name
 * @var int|null    $dsId    destination id, when the caller knows it, so the trip form can be filled
 * @var string|null $dsWhere a short phrase for where the reader is, used once in the sentence
 *
 * Why this exists. Every page that earns a search impression on this site is a place page, a guide
 * or an article, and until now not one of them offered the thing the site is actually for. Somebody
 * arriving from Google on the Book of Kells page, which is our single most seen page, could read it
 * and leave without ever learning that there is a community for Dublin with dates in it.
 *
 * It is one component, included only where a destination is genuinely known, and it says the same
 * true thing everywhere: put your dates up, see whose overlap. It claims no numbers, because on most
 * cities the honest number is zero and a made up one would be worse than silence.
 */
$dsSlug = (string) ($dsSlug ?? '');
$dsName = (string) ($dsName ?? '');
if ($dsSlug === '' || $dsName === '') return;
$dsId = isset($dsId) ? (int) $dsId : 0;
/* A campaign window that names this city, when one is running, so a reader of the Munich guide in
   September is offered the Oktoberfest dates rather than an empty form. */
$dsWindow = function_exists('rmt_acq_window_near') ? rmt_acq_window_near($dsSlug) : null;
$dsBuddy = ($dsSlug !== '' && function_exists('rmt_buddy_landing_for_dest_slug'))
    ? rmt_buddy_landing_for_dest_slug($dsSlug) : null;
?>
<section class="ds-cta card" style="margin:26px 0"><div class="card-body">
  <p style="margin:0 0 6px;font-size:1.02rem"><b>Going to <?= e($dsName) ?>?</b>
    <?php if ($dsWindow): ?>
      <?= e((string) $dsWindow['label']) ?> runs
      <?= e(date('j F', (int) strtotime((string) $dsWindow['from']))) ?> to
      <?= e(date('j F', (int) strtotime((string) $dsWindow['to']))) ?>.
      Put your dates up and see which other travelers are there the same days.
    <?php else: ?>
      Put your dates up and see which other travelers are there the same days.
    <?php endif; ?>
  </p>
  <p class="hint" style="margin:0 0 10px">Nobody sees your dates until you post them, and you decide
    who can contact you.</p>
  <p style="margin:0;display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-primary btn-sm" href="<?= e($dsWindow
        ? rmt_acq_trip_link($dsWindow)
        : url('trip/new' . ($dsId > 0 ? '?destination_id=' . $dsId : ''))) ?>">Post your <?= e($dsName) ?> dates</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('d/' . $dsSlug . '/travelers')) ?>">See who is going</a>
    <?php if ($dsBuddy): ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(url(rmt_buddy_landing_path($dsBuddy['slug']))) ?>">Travel buddies in <?= e($dsBuddy['name']) ?></a>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('d/' . $dsSlug)) ?>">The <?= e($dsName) ?> community</a>
  </p>
</div></section>
<?php unset($dsSlug, $dsName, $dsId, $dsWindow, $dsBuddy); ?>
