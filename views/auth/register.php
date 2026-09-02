<?php /** @var array $errors */ ?>
<div class="wrap"><div class="form-card">
  <?php /* The place they were on their way to review, so the interruption explains itself. */ ?>
  <?php $rp = rmt_return_place_name((string) ($return ?? '')); ?>
  <h1>Join RuinMyTrip</h1>
  <?php if ($rp !== null): ?>
    <p class="muted">Create an account and we will take you straight back to your review of
      <b><?= e($rp) ?></b>. It takes a moment and your review is kept while you do it.</p>
  <?php endif; ?>
  <p class="muted">Build your traveler profile. Share trips, reviews, and guides. The first 100 people who publish a review get Founding Traveler. <a href="<?= e(url('start')) ?>">How launch works</a>.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('register')) ?>"><?= csrf_field() ?>
    <?php /* Where the visitor was headed before they needed an account -- usually a review they
             were about to write. Carried through the form so a validation error does not lose it
             either. */ ?>
    <input type="hidden" name="return" value="<?= e((string) ($return ?? '')) ?>">
    <?php if (function_exists('rmt_invite_referrer_name') && ($refName = rmt_invite_referrer_name())): ?>
      <p class="hint" style="margin:0 0 10px">Invited by <b>@<?= e($refName) ?></b>. They will hear when you join.</p>
    <?php endif; ?>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= e(input('username')) ?>" required pattern="[A-Za-z0-9_]{3,24}" autocomplete="username">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e(input('email')) ?>" required autocomplete="email">
    <label for="password">Password <span class="hint">(8+ characters)</span></label>
    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
    <label for="birthdate">Date of birth <span class="hint">(you must be 16+ to join)</span></label>
    <input type="date" id="birthdate" name="birthdate" value="<?= e(input('birthdate')) ?>" required>
    <p class="hint" style="margin-top:14px">By joining you agree to our <a href="<?= e(url('terms')) ?>">Terms</a>, <a href="<?= e(url('privacy')) ?>">Privacy Policy</a>, and <a href="<?= e(url('guidelines')) ?>">Community Guidelines</a>.</p>
    <div style="margin-top:12px"><button class="btn btn-primary btn-block">Create account</button></div>
  </form>
  <p class="muted" style="margin-top:16px">Already have an account? <a href="<?= e(url('login')) ?>">Sign in</a></p>
</div></div>
