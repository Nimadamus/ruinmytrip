<?php /** @var array $errors @var string $return */ ?>
<div class="wrap"><div class="form-card">
  <h1>Welcome back</h1>
  <p class="muted">Sign in to your RuinMyTrip account.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('login')) ?>"><?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= e($return ?? '') ?>">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e(input('email')) ?>" required autocomplete="email">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">
    <div style="margin-top:18px"><button class="btn btn-primary btn-block">Sign in</button></div>
  </form>
  <p class="muted" style="margin-top:16px">
    <a href="<?= e(url('forgot-password')) ?>">Forgot your password?</a>
  </p>
  <?php /* The link to signup carries wherever the visitor was headed, or a person who needs an
         account loses the place they were about to review at the last step. */ ?>
  <p class="muted" style="margin-top:4px">New here?
    <a href="<?= e(url('register') . (!empty($return) ? '?return=' . rawurlencode($return) : '')) ?>">Create a free account</a></p>
</div></div>
