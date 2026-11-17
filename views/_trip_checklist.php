<?php
/**
 * "Before you go" checklist on a trip (app/trip_checklist.php). Shown to the people who can edit
 * the trip, and only when the city has sourced items.
 *
 * @var array $t      the trip, with dest_slug and dest_name
 * @var bool  $canEdit
 */
if (empty($canEdit) || empty($t['dest_slug'])) return;
$tclItems = rmt_trip_checklist_items((string) $t['dest_slug']);
if (!$tclItems) return;
$tclDone = rmt_trip_checklist_done((int) $t['id']);
$tclCount = count(array_filter($tclItems, static fn(array $i) => isset($tclDone[$i['key']])));
$tclGroups = [];
foreach ($tclItems as $i) $tclGroups[$i['group']][] = $i;
?>
<section class="card tcl" id="before-you-go" style="margin:18px 0"><div class="card-body">
  <h2 class="h-card" style="margin:0 0 4px">Before you go to <?= e((string) $t['dest_name']) ?></h2>
  <p class="hint" style="margin:0 0 12px"><span data-tcl-count><?= $tclCount ?></span> of <?= count($tclItems) ?> done. Each item comes from an official source; tick it off when you have sorted it. Only people on this trip see your ticks.</p>
  <?php foreach ($tclGroups as $g => $list): ?>
    <h3 class="tcl-group"><?= e($g) ?></h3>
    <ul class="tcl-list">
      <?php foreach ($list as $i): $done = isset($tclDone[$i['key']]); ?>
        <li class="tcl-item<?= $done ? ' is-done' : '' ?>">
          <form method="post" action="<?= e(url('trip/' . (int) $t['id'] . '/checklist')) ?>" data-tcl-form>
            <?= csrf_field() ?>
            <input type="hidden" name="item" value="<?= e($i['key']) ?>">
            <input type="hidden" name="done" value="<?= $done ? '0' : '1' ?>">
            <button class="tcl-box" type="submit" aria-pressed="<?= $done ? 'true' : 'false' ?>" aria-label="<?= $done ? 'Mark as not done' : 'Mark as done' ?>: <?= e($i['title']) ?>"><?= $done ? '&#10003;' : '' ?></button>
          </form>
          <div class="tcl-text">
            <p class="tcl-title"><?= e($i['title']) ?></p>
            <?php if ($i['detail'] !== ''): ?><p class="tcl-detail"><?= e($i['detail']) ?></p><?php endif; ?>
            <a class="tcl-src" href="<?= e($i['url']) ?>"<?= str_starts_with($i['url'], url('')) ? '' : ' rel="noopener" target="_blank"' ?>><?= e($i['source']) ?></a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
</div></section>
<script src="<?= e(rmt_asset('assets/js/trip-checklist.js')) ?>" defer></script>
