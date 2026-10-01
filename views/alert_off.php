<?php /** @var bool $valid @var bool $done @var int $id @var string $sig @var string $label */ ?>
<div class="wrap" style="max-width:560px">
  <section class="card" style="margin:28px 0"><div class="card-body">
    <?php if (!$valid): ?>
      <h1 style="margin:0 0 10px">That link does not work</h1>
      <p>Use the link at the bottom of the alert email.</p>
    <?php elseif ($done): ?>
      <h1 style="margin:0 0 10px">Alert off</h1>
      <p>You will get no more emails about <?= e($label) ?>.</p>
    <?php else: ?>
      <h1 style="margin:0 0 10px">Turn off this alert?</h1>
      <p><?= e($label) ?></p>
      <form method="post" action="<?= e(url('alerts/off?a=' . $id . '&s=' . $sig)) ?>">
        <?= csrf_field() ?><button class="btn btn-accent">Turn it off</button>
      </form>
    <?php endif; ?>
  </div></section>
</div>
