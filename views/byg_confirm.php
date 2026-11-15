<?php /** @var ?array $s @var string $token @var string $label */ ?>
<div class="wrap" style="max-width:560px">
  <section class="card" style="margin:28px 0"><div class="card-body">
    <?php if ($s && in_array($s['status'], ['pending', 'active'], true)): ?>
      <h1 style="margin:0 0 10px">Confirm your updates</h1>
      <p><?= e($label) ?></p>
      <form method="post" action="<?= e(url('updates/confirm')) ?>">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <button class="btn btn-accent">Confirm</button>
      </form>
    <?php else: ?>
      <h1 style="margin:0 0 10px">This link has expired</h1>
      <p>Sign up again from the tax or warnings page and we will send a fresh one.</p>
      <p><a class="btn btn-ghost" href="<?= e(url('tourist-tax-calculator')) ?>">Tourist tax calculator</a></p>
    <?php endif; ?>
  </div></section>
</div>
