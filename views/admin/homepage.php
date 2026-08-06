<?php
/** @var array $dests @var array $featuredWarnings */
$here = 'admin/homepage';
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Homepage</h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <form method="post" action="<?= e(url('admin/homepage')) ?>">
    <?= csrf_field() ?>

    <div class="form-card form-wide" style="max-width:900px;margin-top:0">
      <h2 style="font-size:1.15rem;margin-top:0">Supporting line</h2>
      <p>
        <label for="hi">Hero paragraph <span class="muted">(leave blank for the default)</span></label>
        <textarea id="hi" name="home_intro" rows="3" maxlength="500" style="width:100%"><?= e((string) rmt_setting('home_intro', '')) ?></textarea>
      </p>

      <h2 style="font-size:1.15rem">Trending warnings</h2>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <p style="flex:1;min-width:200px">
          <label for="td">Only warnings from the last N days</label>
          <input id="td" name="trending_days" type="number" min="7" max="365" style="width:100%"
                 value="<?= e((string) rmt_setting('home_trending_days', '120')) ?>">
          <span class="hint">Keeps a strike that ended six months ago off the homepage.</span>
        </p>
        <p style="flex:1;min-width:200px">
          <label for="tc">How many to show</label>
          <input id="tc" name="trending_count" type="number" min="3" max="12" style="width:100%"
                 value="<?= e((string) rmt_setting('home_trending_count', '6')) ?>">
        </p>
      </div>
      <p class="hint">Individual warnings can be pinned above the rest with “Feature” in the moderation queue.
        Featured warnings ignore the date window.</p>

      <h2 style="font-size:1.15rem">Featured destinations</h2>
      <p class="muted">Ticked destinations appear first in “Popular destinations”. Unticked ones are ordered by
        how much reviewed risk content and how many warnings they have.</p>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:4px;max-height:420px;overflow:auto;
                  border:1px solid var(--line);border-radius:12px;padding:12px">
        <?php foreach ($dests as $d): ?>
          <label style="font-weight:400;font-size:.9rem">
            <input type="checkbox" name="featured[]" value="<?= (int) $d['id'] ?>" <?= !empty($d['featured']) ? 'checked' : '' ?>>
            <?= e((string) $d['name']) ?>
            <span class="muted">(<?= (int) $d['sections'] ?>s/<?= (int) $d['warnings'] ?>w)</span>
          </label>
        <?php endforeach; ?>
      </div>

      <button class="btn btn-primary" style="margin-top:18px" type="submit">Save homepage settings</button>
    </div>
  </form>

  <?php if ($featuredWarnings): ?>
    <h2 style="font-size:1.2rem;margin-top:30px">Currently featured warnings</h2>
    <?php foreach ($featuredWarnings as $w) { $returnTo = '/admin/homepage'; include __DIR__ . '/_queue_row.php'; } ?>
  <?php endif; ?>
  <div style="height:50px"></div>
</div>
