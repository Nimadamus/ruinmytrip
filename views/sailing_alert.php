<?php
/**
 * The sailing alert screens (app/sailings.php): sent, confirm, on, off.
 *
 * @var string $state sent|confirm|on|expired|off_ask|off @var ?array $alert @var string $label @var string $token
 */
$page = $alert ? url(ltrim(rmt_sailing_alert_page($alert), '/')) : url('buddies/cruise');
?>
<div class="wrap" style="max-width:560px">
  <section class="card" style="margin:28px 0"><div class="card-body">
    <?php if ($state === 'sent'): ?>
      <h1 style="margin:0 0 10px">Check your inbox</h1>
      <p>We sent a link to switch on your alert for <b><?= e($label) ?></b>. Nothing is sent until you click it.</p>
      <p><a class="btn btn-ghost" href="<?= e($page) ?>">Back to the sailing</a></p>
    <?php elseif ($state === 'confirm'): ?>
      <h1 style="margin:0 0 10px">Switch on your sailing alert</h1>
      <p><?= e($label) ?></p>
      <form method="post" action="<?= e(url('sailing-alerts/confirm')) ?>">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <button class="btn btn-accent">Switch it on</button>
      </form>
    <?php elseif ($state === 'on'): ?>
      <h1 style="margin:0 0 10px">Your alert is on</h1>
      <p>We will email you when a real traveler posts <b><?= e($label) ?></b>.</p>
      <p>Want them to find you too? <a href="<?= e(url('register?return=' . rawurlencode(rmt_sailing_join_url($alert)))) ?>">Join free and post your sailing</a>.</p>
      <p><a class="btn btn-ghost" href="<?= e($page) ?>">See the sailing</a></p>
    <?php elseif ($state === 'off_ask'): ?>
      <h1 style="margin:0 0 10px">Turn this alert off?</h1>
      <p><?= e($label) ?></p>
      <form method="post" action="<?= e(url('sailing-alerts/off')) ?>">
        <?= csrf_field() ?><input type="hidden" name="a" value="<?= (int) ($id ?? 0) ?>"><input type="hidden" name="s" value="<?= e((string) ($sig ?? '')) ?>">
        <button class="btn btn-ghost">Turn it off</button>
      </form>
    <?php elseif ($state === 'off'): ?>
      <h1 style="margin:0 0 10px">Your alert is off</h1>
      <p>No more emails about <?= e($label) ?>.</p>
    <?php else: ?>
      <h1 style="margin:0 0 10px">This link has expired</h1>
      <p>Set the alert again from the sailing page and we will send a fresh one.</p>
      <p><a class="btn btn-ghost" href="<?= e(url('buddies/cruise')) ?>">Cruise buddies</a></p>
    <?php endif; ?>
  </div></section>
</div>
