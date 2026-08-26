<?php /** @var array $dests @var array $saved @var array $me */ ?>
<section class="block"><div class="wrap" style="max-width:760px">
  <p class="eyebrow">You're in</p>
  <h1>Start your traveler profile</h1>
  <p class="muted">Pick a few places you want to visit. Optionally share one upcoming trip — destination and dates only, never a precise location.</p>

  <form method="post" action="<?= e(url('welcome')) ?>">
    <?= csrf_field() ?>
    <h2 style="font-size:1.15rem">Want to visit</h2>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 22px">
      <?php foreach ($dests as $dd): $on = !empty($saved[(int)$dd['id']]); ?>
        <label class="chip" style="cursor:pointer;<?= $on ? 'background:var(--ink);color:#fff' : '' ?>">
          <input type="checkbox" name="want[]" value="<?= (int)$dd['id'] ?>" <?= $on ? 'checked' : '' ?> style="margin-right:6px">
          <?= e($dd['name']) ?>
        </label>
      <?php endforeach; ?>
    </div>

    <h2 style="font-size:1.15rem">One upcoming trip (optional)</h2>
    <label>Destination
      <select name="destination_id">
        <option value="">Skip for now</option>
        <?php foreach ($dests as $dd): ?>
          <option value="<?= (int)$dd['id'] ?>"><?= e($dd['name']) ?>, <?= e($dd['country']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="grid g-2" style="gap:10px">
      <label>From <input type="date" name="date_from"></label>
      <label>Until <input type="date" name="date_to"></label>
    </div>
    <input type="hidden" name="visibility" value="public">

    <p style="margin:22px 0 0;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit">Save and go to my feed</button>
      <a class="btn btn-ghost" href="<?= e(url('feed')) ?>">Skip</a>
    </p>
  </form>
</div></section>
