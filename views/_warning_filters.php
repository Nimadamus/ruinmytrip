<?php
/**
 * The shared warning filter bar.
 *
 * A GET form, so every filtered view is a real, shareable, bookmarkable URL rather than hidden
 * client state — which also means a filtered list can be linked to from an editorial page.
 *
 * @var array  $f          parsed filters (rmt_warning_filters_from_query)
 * @var string $action     form action URL
 * @var bool   $showCategory   hide on a category page, where it is fixed
 */
$showCategory = $showCategory ?? true;
/** Extra query params this page owns (e.g. the admin queue's `status`) that Apply must not drop. */
$hidden = $hidden ?? [];
$months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<form class="filters" method="get" action="<?= e($action) ?>">
  <?php /* Preserve a fixed category on pages where the control is hidden. */ ?>
  <?php if (!$showCategory && !empty($f['category'])): ?>
    <input type="hidden" name="category" value="<?= e($f['category']) ?>">
  <?php endif; ?>
  <?php foreach ($hidden as $hk => $hv): ?>
    <input type="hidden" name="<?= e((string) $hk) ?>" value="<?= e((string) $hv) ?>">
  <?php endforeach; ?>

  <div style="flex:1;min-width:180px">
    <label for="f-q">Search these warnings</label>
    <input id="f-q" type="search" name="q" style="width:100%" placeholder="taxi, resort fee, Sagrada…" value="<?= e($f['q'] ?? '') ?>">
  </div>

  <?php if ($showCategory): ?>
  <div>
    <label for="f-cat">Category</label>
    <select id="f-cat" name="category">
      <option value="">All categories</option>
      <?php foreach (RMT_WARNING_CATEGORIES as $k => $c): ?>
        <option value="<?= e($k) ?>" <?= ($f['category'] ?? '') === $k ? 'selected' : '' ?>><?= e($c['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>

  <div>
    <label for="f-sev">Severity at least</label>
    <select id="f-sev" name="severity">
      <option value="">Any</option>
      <?php foreach (RMT_WARNING_SEVERITIES as $n => $s): ?>
        <option value="<?= (int) $n ?>" <?= (int) ($f['severity_min'] ?? 0) === $n ? 'selected' : '' ?>><?= e($s['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div>
    <label for="f-ver">Status</label>
    <select id="f-ver" name="verification">
      <option value="">Any</option>
      <?php foreach (RMT_WARNING_VERIFICATION as $v): ?>
        <option value="<?= e($v) ?>" <?= ($f['verification'] ?? '') === $v ? 'selected' : '' ?>><?= e(ucfirst($v)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div>
    <label for="f-trav">Traveler type</label>
    <select id="f-trav" name="traveler">
      <option value="">Anyone</option>
      <?php foreach (RMT_TRAVELER_TYPES as $k => $lab): ?>
        <option value="<?= e($k) ?>" <?= ($f['traveler_type'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div>
    <label for="f-month">Month experienced</label>
    <select id="f-month" name="month">
      <option value="">Any month</option>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= (int) ($f['month'] ?? 0) === $m ? 'selected' : '' ?>><?= e($months[$m]) ?></option>
      <?php endfor; ?>
    </select>
  </div>

  <div>
    <label for="f-sort">Sort by</label>
    <select id="f-sort" name="sort">
      <?php foreach (['recent'=>'Most recent','helpful'=>'Most helpful','severity'=>'Most severe',
                      'experienced'=>'Date experienced','verified'=>'Verified first','oldest'=>'Oldest first'] as $k => $lab): ?>
        <option value="<?= e($k) ?>" <?= ($f['sort'] ?? 'recent') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <button class="btn btn-primary btn-sm" type="submit">Apply</button>
  <a class="btn btn-ghost btn-sm" href="<?= e($action) ?>">Reset</a>
</form>
