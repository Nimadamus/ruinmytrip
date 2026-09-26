<?php
/** @var array $dests @var array $errors @var array $p @var ?array $dest
 *
 * The trip first form. One screen, the city and the dates first, everything else optional and
 * short. It posts to /plan, which either publishes (a confirmed member), holds it for the confirm
 * click (a new member), or keeps it in the session and shows the account step (a stranger).
 */
$pv = static function (string $k, string $default = '') use ($p): string {
    $v = $p[$k] ?? $default;
    return is_array($v) ? '' : (string) $v;
};
$ints = array_map('strval', (array) ($p['interests'] ?? []));
$party = $pv('party');
$meet = $pv('meet', 'yes');
$buddyOn = $pv('want_buddy') === '1';
$me = current_user();
$cityName = $dest['name'] ?? '';
?>
<div class="wrap"><div class="form-card form-wide pf">
  <p class="eyebrow" style="margin:0 0 6px">Takes a minute</p>
  <h1 style="margin:0 0 6px"><?= $cityName !== '' ? 'Going to ' . e($cityName) . '? Post your trip.' : 'Where are you going next?' ?></h1>
  <p class="muted" style="margin:0 0 16px">Say where and when. We show you the travelers who will be there on the same days,
    and tell you when somebody new lands on your dates. Nobody can message you until you say yes.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <form method="post" action="<?= e(url('plan')) ?>" id="plan-form" data-plan-form>
    <?= csrf_field() ?>
    <label for="pf-city">City</label>
    <select id="pf-city" name="destination_id" required>
      <option value="">Pick a city</option>
      <?php foreach ($dests as $d): ?>
        <option value="<?= (int) $d['id'] ?>"<?= $pv('destination_id') === (string) $d['id'] ? ' selected' : '' ?>><?= e($d['name'] . ', ' . $d['country']) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="pf-row">
      <div><label for="pf-from">Arriving</label>
        <input type="date" id="pf-from" name="date_from" required min="<?= e(date('Y-m-d')) ?>" value="<?= e($pv('date_from')) ?>"></div>
      <div><label for="pf-to">Leaving</label>
        <input type="date" id="pf-to" name="date_to" required min="<?= e(date('Y-m-d')) ?>" value="<?= e($pv('date_to')) ?>"></div>
    </div>

    <p class="pf-q">Who is coming?</p>
    <div class="pf-pick">
      <?php foreach (RMT_BUDDY_PARTIES as $k => $v): ?>
        <label><input type="radio" name="party" value="<?= e($k) ?>"<?= $party === $k ? ' checked' : '' ?>><span><?= e($v) ?></span></label>
      <?php endforeach; ?>
    </div>

    <p class="pf-q">What do you want to do? <span class="hint">(pick any)</span></p>
    <div class="pf-pick">
      <?php foreach (RMT_INTERESTS as $k => $v): ?>
        <label><input type="checkbox" name="interests[]" value="<?= e($k) ?>"<?= in_array($k, $ints, true) ? ' checked' : '' ?>><span><?= e($v) ?></span></label>
      <?php endforeach; ?>
    </div>

    <p class="pf-q">Meet other travelers on your dates?</p>
    <div class="pf-pick pf-stack">
      <?php foreach (RMT_PLAN_MEET as $k => $v): ?>
        <label><input type="radio" name="meet" value="<?= e($k) ?>"<?= $meet === $k ? ' checked' : '' ?>><span><?= e($v) ?></span></label>
      <?php endforeach; ?>
    </div>

    <label class="pf-buddy"><input type="checkbox" name="want_buddy" value="1" id="pf-buddy"<?= $buddyOn ? ' checked' : '' ?>>
      <span><b>I am looking for a travel buddy</b> <span class="hint">(18+). Also posts you on the travel buddy board.</span></span></label>
    <div class="pf-buddy-fields" id="pf-buddy-fields"<?= $buddyOn ? '' : ' hidden' ?>>
      <label for="pf-bnote">Who are you hoping to travel with?</label>
      <textarea id="pf-bnote" name="buddy_note" rows="3" maxlength="4000"
        placeholder="Someone to share a few dinners and a day trip with. I am 30, into food and walking everywhere."><?= e($pv('buddy_note')) ?></textarea>
      <div class="pf-row">
        <div><label for="pf-amin">Ages from <span class="hint">(optional)</span></label><input type="number" id="pf-amin" name="age_min" min="18" max="99" value="<?= e($pv('age_min')) ?>"></div>
        <div><label for="pf-amax">to</label><input type="number" id="pf-amax" name="age_max" min="18" max="99" value="<?= e($pv('age_max')) ?>"></div>
      </div>
      <label class="pf-check"><input type="checkbox" name="flexible" value="1"<?= $pv('flexible') === '1' ? ' checked' : '' ?>> <span>My dates are flexible</span></label>
      <label class="pf-check"><input type="checkbox" name="safety_ack" value="1"<?= $pv('safety_ack') === '1' ? ' checked' : '' ?>>
        <span>I am 18 or over, I will meet people in public first, and I will block or report anyone who makes me uneasy. <a href="<?= e(url('safety')) ?>">Safety guide</a></span></label>
    </div>

    <details class="pf-more"<?= ($pv('note') !== '' || $pv('visibility', 'public') !== 'public') ? ' open' : '' ?>>
      <summary>Add a note or change who can see it</summary>
      <label for="pf-note">A note on your trip <span class="hint">(optional)</span></label>
      <textarea id="pf-note" name="note" rows="3" maxlength="20000" placeholder="First time in the city, staying near the old town, want to find a good food tour."><?= e($pv('note')) ?></textarea>
      <label for="pf-vis">Who can see this trip</label>
      <select id="pf-vis" name="visibility">
        <option value="public"<?= $pv('visibility', 'public') === 'public' ? ' selected' : '' ?>>Anyone (the only way other travelers find you)</option>
        <option value="followers"<?= $pv('visibility') === 'followers' ? ' selected' : '' ?>>People who follow me</option>
        <option value="private"<?= $pv('visibility') === 'private' ? ' selected' : '' ?>>Only me</option>
      </select>
    </details>

    <p class="hint" style="margin:14px 0 0">We show the city and the dates, never where you are staying.</p>
    <div style="margin-top:14px"><button class="btn btn-primary btn-block" type="submit"><?= $me ? 'Post my trip' : 'Continue' ?></button></div>
    <?php if (!$me): ?><p class="hint" style="margin:10px 0 0;text-align:center">Next: a free account so the trip is yours. Already a member? <a href="<?= e(url('login?return=' . rawurlencode('/plan'))) ?>">Sign in</a></p><?php endif; ?>
  </form>
</div></div>
<script>
(function () {
  var box = document.getElementById('pf-buddy'), f = document.getElementById('pf-buddy-fields');
  if (box && f) box.addEventListener('change', function () { f.hidden = !box.checked; });
  var from = document.getElementById('pf-from'), to = document.getElementById('pf-to');
  if (from && to) from.addEventListener('change', function () { if (from.value) { to.min = from.value; if (!to.value || to.value < from.value) to.value = from.value; } });
  // One row the first time somebody puts anything into the form: the step between seeing it and sending it.
  var form = document.getElementById('plan-form'), sent = false;
  if (form) form.addEventListener('change', function () {
    if (sent || !window.rmtTrack) return; sent = true;
    var c = document.getElementById('pf-city');
    window.rmtTrack('plan_started', { source: 'plan', destination_id: c ? c.value : '' });
  });
})();
</script>
