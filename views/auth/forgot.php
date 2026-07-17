<?php /** @var array $errors @var bool $sent */ ?>
<div class="wrap"><div class="form-card">
  <?php if ($sent): ?>
    <h1>Check your inbox</h1>
    <?php /* Deliberately identical whether or not the address is registered — telling a stranger
             which emails have accounts would leak who is a member of a travel/meetup community. */ ?>
    <p class="muted">If that email address has a RuinMyTrip account, we have sent a link to reset the
      password. It expires in one hour.</p>
    <p class="muted" style="margin-top:16px">Nothing arrived? Check your spam folder, then
      <a href="<?= e(url('forgot-password')) ?>">try again</a>.</p>
    <p style="margin-top:20px"><a class="btn btn-ghost" href="<?= e(url('login')) ?>">Back to sign in</a></p>
  <?php else: ?>
    <h1>Reset your password</h1>
    <p class="muted">Enter the email address on your account and we will send you a reset link.</p>
    <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
    <form method="post" action="<?= e(url('forgot-password')) ?>"><?= csrf_field() ?>
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="email">
      <div style="margin-top:18px"><button class="btn btn-primary btn-block">Send reset link</button></div>
    </form>
    <p class="muted" style="margin-top:16px">Remembered it? <a href="<?= e(url('login')) ?>">Sign in</a></p>
  <?php endif; ?>
</div></div>
