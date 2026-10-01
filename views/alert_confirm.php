<?php /** @var ?array $alert @var string $token @var string $label */ ?>
<div class="wrap" style="max-width:560px">
  <section class="card" style="margin:28px 0"><div class="card-body">
    <?php if ($alert && in_array($alert['status'], ['pending', 'active'], true)): ?>
      <h1 style="margin:0 0 10px">Switch on your match alert</h1>
      <p><?= e($label) ?></p>
      <form method="post" action="<?= e(url('alerts/confirm')) ?>">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <button class="btn btn-accent">Switch it on</button>
      </form>
    <?php else: ?>
      <h1 style="margin:0 0 10px">This link has expired</h1>
      <p>Set the alert again from the city or event page and we will send a fresh one.</p>
      <p><a class="btn btn-ghost" href="<?= e(url('events')) ?>">Events</a></p>
    <?php endif; ?>
  </div></section>
</div>
