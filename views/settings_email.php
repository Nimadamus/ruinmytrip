<?php /** /settings/email @var array $me @var array $muted @var bool $all_off */ ?>
<div class="wrap" style="max-width:680px;padding-top:28px;padding-bottom:40px">
  <p class="crumbs"><a href="<?= e(url('u/' . $me['username'] . '/edit')) ?>">Settings</a> / Email</p>
  <h1 style="margin:0 0 6px">Email settings</h1>
  <p class="muted">Choose which emails RuinMyTrip sends you. Everything still shows in your <a href="<?= e(url('notifications')) ?>">notifications</a>.</p>
  <form method="post" action="<?= e(url('settings/email')) ?>" class="card" style="margin-top:16px"><div class="card-body">
    <?= csrf_field() ?>
    <?php foreach (RMT_EMAIL_CATEGORIES as $k => [$label, $hint]): ?>
      <label class="opt-in" style="margin:0 0 12px">
        <input type="checkbox" name="on[]" value="<?= e($k) ?>"<?= in_array($k, $muted, true) ? '' : ' checked' ?>>
        <span><b><?= e($label) ?></b><span class="hint"><?= e($hint) ?></span></span>
      </label>
    <?php endforeach; ?>
    <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
    <label class="opt-in" style="margin:0 0 12px">
      <input type="checkbox" name="all_off" value="1"<?= $all_off ? ' checked' : '' ?>>
      <span><b>Turn off all emails</b><span class="hint">Except emails about your account and its security, like confirming your address or resetting a password.</span></span>
    </label>
    <button class="btn btn-primary" type="submit">Save</button>
  </div></form>
</div>
