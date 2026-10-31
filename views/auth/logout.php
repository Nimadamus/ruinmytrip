<?php /** @var ?array $me */ ?>
<div class="wrap"><div class="form-card">
  <?php if ($me): ?>
    <h1>Sign out</h1>
    <p class="muted">You are signed in as <b>@<?= e((string) $me['username']) ?></b>. Sign out on this device?</p>
    <form method="post" action="<?= e(url('logout')) ?>" style="margin-top:18px"><?= csrf_field() ?>
      <button type="submit" class="btn btn-primary btn-block">Sign out</button>
    </form>
    <p class="muted" style="margin-top:16px"><a href="<?= e(url('feed')) ?>">Stay signed in</a></p>
  <?php else: ?>
    <h1>You are signed out</h1>
    <p class="muted">Nobody is signed in on this device.</p>
    <p style="margin-top:20px"><a class="btn btn-primary" href="<?= e(url('login')) ?>">Sign in</a>
      <a class="btn btn-ghost" href="<?= e(url('')) ?>">Home</a></p>
  <?php endif; ?>
</div></div>
