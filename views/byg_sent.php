<?php /** @var array $s @var string $label @var bool $confirmed */ ?>
<div class="wrap" style="max-width:620px">
  <section class="card" style="margin:28px 0"><div class="card-body">
    <?php if ($confirmed): ?>
      <p class="eyebrow" style="margin:0">You are signed up</p>
      <h1 style="margin:4px 0 10px"><?= e($label) ?></h1>
      <p>We will email you only when this changes at the official source. At most one email a day, and every one has an off switch.</p>
    <?php else: ?>
      <p class="eyebrow" style="margin:0">One more step</p>
      <h1 style="margin:4px 0 10px">Check your inbox</h1>
      <p>We sent a link to confirm <b><?= e($label) ?></b>. Nothing else is sent until you click it.</p>
    <?php endif; ?>
    <p style="margin:16px 0 0;display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn btn-primary btn-sm" href="<?= e(rmt_byg_page($s)) ?>">Back to what you were reading</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('buddies')) ?>">Find someone going to the same place</a>
    </p>
  </div></section>
</div>
