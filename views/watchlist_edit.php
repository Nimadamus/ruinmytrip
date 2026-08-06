<?php
/**
 * Edit one saved trip: dates, which categories matter, how loud the alerts should be.
 * @var array $w @var array $errors
 */
$cats = rmt_categories_decode($w['categories_json'] ?? null);
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('dashboard')) ?>">Dashboard</a> / Edit trip</p>
</div>
<form class="form-card form-wide" method="post" action="<?= e(url('watchlist/' . (int) $w['id'] . '/edit')) ?>">
  <?= csrf_field() ?>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <h1 style="margin-bottom:.2rem"><?= e($w['dest_name']) ?></h1>
  <p class="muted" style="margin-top:0">We will only email you about warnings that clear the bar you set here.</p>

  <p>
    <label for="tw-label">Name this trip</label>
    <input id="tw-label" name="label" maxlength="120" style="width:100%" placeholder="Honeymoon, work trip, Easter with the kids"
           value="<?= e((string) ($w['label'] ?? '')) ?>">
  </p>

  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <p style="flex:1;min-width:180px">
      <label for="tw-from">Departure</label>
      <input id="tw-from" type="date" name="date_from" style="width:100%" value="<?= e(substr((string) ($w['date_from'] ?? ''), 0, 10)) ?>">
    </p>
    <p style="flex:1;min-width:180px">
      <label for="tw-to">Return</label>
      <input id="tw-to" type="date" name="date_to" style="width:100%" value="<?= e(substr((string) ($w['date_to'] ?? ''), 0, 10)) ?>">
    </p>
  </div>

  <p>
    <label for="tw-freq">How often should we email you?</label>
    <select id="tw-freq" name="alert_frequency" style="width:100%">
      <?php foreach (RMT_ALERT_FREQUENCIES as $k => $lab): ?>
        <option value="<?= e($k) ?>" <?= (string) $w['alert_frequency'] === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="hint">“Immediate” still means at most one email an hour, and only for warnings that clear your severity bar.</span>
  </p>

  <p>
    <label for="tw-sev">Only tell me about problems rated</label>
    <select id="tw-sev" name="min_severity" style="width:100%">
      <?php foreach (RMT_WARNING_SEVERITIES as $n => $s): ?>
        <option value="<?= (int) $n ?>" <?= (int) $w['min_severity'] === $n ? 'selected' : '' ?>><?= e($s['label']) ?> and above — <?= e($s['desc']) ?></option>
      <?php endforeach; ?>
    </select>
  </p>

  <fieldset style="border:0;padding:0;margin:20px 0 0">
    <legend><b>Categories you care about</b></legend>
    <p class="hint" style="margin-top:0">Leave all unticked to hear about everything.</p>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:4px">
      <?php foreach (RMT_WARNING_CATEGORIES as $k => $c): ?>
        <label style="font-weight:400">
          <input type="checkbox" name="categories[]" value="<?= e($k) ?>" <?= in_array($k, $cats, true) ? 'checked' : '' ?>>
          <?= $c['icon'] ?> <?= e($c['label']) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <p style="margin-top:20px">
    <label for="tw-note">Private note</label>
    <textarea id="tw-note" name="note" rows="3" style="width:100%" maxlength="1000"><?= e((string) ($w['note'] ?? '')) ?></textarea>
  </p>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
    <button class="btn btn-primary" type="submit">Save trip</button>
    <a class="btn btn-ghost" href="<?= e(url('dashboard')) ?>">Cancel</a>
  </div>
</form>

<div class="wrap" style="max-width:760px;margin-bottom:50px">
  <form method="post" action="<?= e(url('watchlist/' . (int) $w['id'] . '/delete')) ?>">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-sm" data-confirm="Remove this trip from your watchlist?">Remove this trip</button>
  </form>
</div>
