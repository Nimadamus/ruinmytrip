<?php /** @var array $dests @var array $saved @var array $me @var array $communities */ ?>
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

    <?php /* Rooms first, then words. Both optional, both here rather than "later", because later
             is where new members go to never come back. */ ?>
    <?php if ($communities): ?>
      <h2 style="font-size:1.15rem;margin-top:26px">Join a community (optional)</h2>
      <p class="hint" style="margin:0 0 10px">Groups other travelers started. You can leave any of them whenever.</p>
      <?php foreach ($communities as $cc): ?>
        <label class="card" style="display:block;margin-bottom:8px;cursor:pointer"><span class="card-body" style="display:block;padding:12px 16px">
          <input type="checkbox" name="join[]" value="<?= (int) $cc['id'] ?>" style="margin-right:8px">
          <b><?= e((string) $cc['title']) ?></b>
          <span class="hint"> · <?= (int) $cc['member_count'] ?> members</span>
          <?php if (!empty($cc['summary'])): ?><span class="muted" style="display:block;margin-top:.2rem"><?= e((string) $cc['summary']) ?></span><?php endif; ?>
        </span></label>
      <?php endforeach; ?>
    <?php endif; ?>

    <h2 style="font-size:1.15rem;margin-top:26px">Say something (optional)</h2>
    <p class="hint" style="margin:0 0 8px">A question counts. So does a warning about the last place that ruined your trip.</p>
    <textarea name="hello" rows="3" maxlength="<?= RMT_POST_MAX ?>"
              placeholder="Where are you going next, or what should the rest of us avoid?"></textarea>

    <p style="margin:22px 0 0;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit">Save and get started</button>
      <a class="btn btn-ghost" href="<?= e(url('feed')) ?>">Skip</a>
    </p>
  </form>
</div></section>
