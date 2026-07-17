<?php /** @var string $token @var bool $valid @var array $errors */ ?>
<div class="wrap"><div class="form-card">
  <?php if (!$valid): ?>
    <h1>This link has expired</h1>
    <p class="muted">Reset links are single-use and last one hour. Request a fresh one and it will
      arrive in a moment.</p>
    <p style="margin-top:20px"><a class="btn btn-primary" href="<?= e(url('forgot-password')) ?>">Request a new link</a></p>
  <?php else: ?>
    <h1>Choose a new password</h1>
    <p class="muted">Pick something at least 8 characters long.</p>
    <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
    <form method="post" action="<?= e(url('reset-password')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label for="password">New password</label>
      <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
      <label for="password_confirm">Confirm new password</label>
      <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
      <div style="margin-top:18px"><button class="btn btn-primary btn-block">Update password</button></div>
    </form>
  <?php endif; ?>
</div></div>
