<?php /** @var string $return @var array $errors @var string $dob */ $errors = $errors ?? []; ?>
<div class="wrap"><div class="form-card">
  <h1>One question first</h1>
  <p class="muted">Travel buddies and meetups are for travelers 18 and over, so we ask your date of birth once.
    It stays private: nobody sees it, and it is only used for this check and for age ranges on buddy posts.</p>
  <?php if ($errors): ?><div class="errors" role="alert"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('account/age')) ?>"><?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= e($return) ?>">
    <label for="dob">Date of birth</label>
    <input id="dob" type="date" name="dob" value="<?= e($dob) ?>" required autocomplete="bday"
           max="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d', strtotime('-110 years'))) ?>">
    <div style="margin-top:16px"><button class="btn btn-primary" type="submit">Continue</button></div>
  </form>
  <p class="muted" style="margin-top:14px;font-size:.9rem">Your answer cannot be changed later, so please enter it carefully.
    <a href="<?= e(url('safety')) ?>">How we keep meetups safe</a></p>
</div></div>
