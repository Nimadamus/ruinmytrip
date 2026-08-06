<?php
/**
 * The standalone alert-subscription page.
 *
 * Double opt-in, and the frequency controls are on the sign-up form rather than buried in a
 * settings screen afterwards — the moment someone is deciding whether to hand over an address is
 * the moment they want to know how often it will be used.
 *
 * @var ?array $d @var array $dests @var array $errors @var bool $done
 */
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Travel warning alerts</p>
</div>

<?php if ($done): ?>
  <div class="form-card">
    <h1>Check your email</h1>
    <p>If that address can receive mail from us, a confirmation link is on its way. Click it and your alerts
       start — nothing is sent until you do.</p>
    <p class="muted">Did not arrive within a few minutes? Check spam, then
       <a href="<?= e(url('alerts')) ?>">try again</a>.</p>
    <a class="btn btn-primary" href="<?= e(url()) ?>">Back to RuinMyTrip</a>
  </div>
<?php else: ?>
  <form class="form-card form-wide" method="post" action="<?= e(url('alerts/subscribe')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="source" value="alerts_page">
    <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <h1 style="margin-bottom:.2rem">Get warned before your trip, not after</h1>
    <p class="muted" style="margin-top:0">Tell us where you are going. We will email you when a warning serious
      enough to change your plans is published for that destination — a new tourist tax, a strike, a closure,
      a scam pattern that just started.</p>

    <p>
      <label for="al-dest"><b>Destination</b></label>
      <select id="al-dest" name="destination" required style="width:100%">
        <option value="">Choose a destination…</option>
        <?php foreach ($dests as $dd): ?>
          <option value="<?= e($dd['slug']) ?>" <?= ($d && $d['id'] === $dd['id']) ? 'selected' : '' ?>>
            <?= e($dd['name'] . ', ' . $dd['country']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </p>

    <p>
      <label for="al-email"><b>Email address</b></label>
      <input id="al-email" type="email" name="email" required style="width:100%" placeholder="you@example.com"
             value="<?= e(current_user()['email'] ?? '') ?>">
    </p>

    <p>
      <label for="al-freq"><b>How often, at most?</b></label>
      <select id="al-freq" name="frequency" style="width:100%">
        <?php foreach (RMT_ALERT_FREQUENCIES as $k => $lab): if ($k === 'none') continue; ?>
          <option value="<?= e($k) ?>" <?= $k === 'weekly' ? 'selected' : '' ?>><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </p>

    <p>
      <label for="al-sev"><b>Only warnings rated</b></label>
      <select id="al-sev" name="min_severity" style="width:100%">
        <?php foreach (RMT_WARNING_SEVERITIES as $n => $s): ?>
          <option value="<?= (int) $n ?>" <?= $n === 2 ? 'selected' : '' ?>><?= e($s['label']) ?> and above — <?= e($s['desc']) ?></option>
        <?php endforeach; ?>
      </select>
    </p>

    <fieldset style="border:0;padding:0;margin:20px 0 0">
      <legend><b>Categories</b></legend>
      <p class="hint" style="margin-top:0">Leave all unticked to hear about everything.</p>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:4px">
        <?php foreach (RMT_WARNING_CATEGORIES as $k => $c): ?>
          <label style="font-weight:400"><input type="checkbox" name="categories[]" value="<?= e($k) ?>"> <?= $c['icon'] ?> <?= e($c['label']) ?></label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <button class="btn btn-accent" style="margin-top:20px" type="submit">Send me the confirmation link</button>
    <p class="hint" style="margin-top:12px">We confirm your address before sending anything, never sell it, and
      every email carries a one-click unsubscribe. <a href="<?= e(url('privacy')) ?>">Privacy policy</a>.</p>

    <?php if (!current_user()): ?>
      <div class="callout" style="margin-top:22px">
        <b>A free account does more.</b> Save the trip with your travel dates, get a preparation checklist built
        from the actual warnings for that destination, and track reports you submit.
        <a href="<?= e(url('register')) ?>">Create an account</a>.
      </div>
    <?php endif; ?>
  </form>
<?php endif; ?>
