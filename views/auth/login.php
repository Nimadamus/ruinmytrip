<?php /** @var array $errors */ ?>
<div class="wrap"><div class="form-card">
  <h1>Welcome back</h1>
  <p class="muted">Sign in to your RuinMyTrip account.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('login')) ?>"><?= csrf_field() ?>
    <label for="email">Email</label>
    <input type="email" id="email" name="email" required autocomplete="email">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">
    <div style="margin-top:18px"><button class="btn btn-primary btn-block">Sign in</button></div>
  </form>
  <p class="muted" style="margin-top:16px">New here? <a href="<?= e(url('register')) ?>">Create a free account</a></p>
  <p class="hint">Demo login: maya@example.com / travel1234</p>
</div></div>
