<?php /** @var string $token @var ?string $email */ ?>
<div class="wrap"><div class="form-card">
  <h1>Confirm your email</h1>
  <p class="muted">
    <?php if ($email): ?>Confirm <b><?= e($email) ?></b> to finish setting up your account.
    <?php else: ?>Click below to confirm your email address.<?php endif; ?>
  </p>
  <?php /* One click = a POST. The link in the email is a GET that only shows this page, so
           scanners/prefetchers that fetch the URL never consume the single-use token. */ ?>
  <form method="post" action="<?= e(url('verify-email/confirm')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <button class="btn btn-primary btn-block" style="margin-top:8px">Confirm my email</button>
  </form>
</div></div>
