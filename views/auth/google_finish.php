<?php /** @var array $g @var string $suggest @var array $errors */ ?>
<div class="wrap"><div class="form-card">
  <h1>One last thing</h1>
  <p class="muted">You are joining as <b><?= e((string) $g['email']) ?></b> with Google. Pick the name other
    travelers see, confirm your age, and you are in.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('join/google')) ?>"><?= csrf_field() ?>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= e($suggest) ?>" required pattern="[A-Za-z0-9_]{3,24}"
           title="3 to 24 letters, numbers or underscores" autocomplete="username">
    <label class="qj-age"><input type="checkbox" name="age_ok" value="1" required> I am 16 or older</label>
    <p class="hint" style="margin-top:10px">By joining you agree to our <a href="<?= e(url('terms')) ?>">Terms</a>,
      <a href="<?= e(url('privacy')) ?>">Privacy Policy</a>, and <a href="<?= e(url('guidelines')) ?>">Community Guidelines</a>.</p>
    <div style="margin-top:12px"><button class="btn btn-primary btn-block">Join RuinMyTrip</button></div>
  </form>
</div></div>
