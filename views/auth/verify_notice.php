<?php /** @var ?array $me @var bool $verified @var array $errors */ $errors = $errors ?? []; ?>
<div class="wrap"><div class="form-card">
  <?php if ($verified): ?>
    <h1>Your email is confirmed</h1>
    <p class="muted">You are all set. Nothing else to do here.</p>
    <p style="margin-top:20px"><a class="btn btn-primary" href="<?= e(url('feed')) ?>">Go to your feed</a></p>
  <?php else: ?>
    <h1>Confirm your email</h1>
    <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
    <?php if ($me): ?>
      <?php /* Confirming an email is a chore. Saying what it releases turns it into the last step
               of something they already did, which is the difference between doing it now and
               doing it never. */ ?>
      <?php if (function_exists('rmt_pending_has') && rmt_pending_has()): ?>
        <p style="font-size:1.05rem;margin:0 0 10px"><b>Your travel dates and your first post are saved.</b>
          They go live the moment you confirm this address.</p>
      <?php endif; ?>
      <p class="muted">We sent a link to <b><?= e($me['email']) ?></b>. Click it to confirm this address.
        You can browse RuinMyTrip in the meantime, but you will need a confirmed email before posting
        trips or reviews.</p>
      <form method="post" action="<?= e(url('verify-email/resend')) ?>" style="margin-top:18px"><?= csrf_field() ?>
        <button class="btn btn-primary">Send me a new link</button>
      </form>
      <p class="muted" style="margin-top:16px">Wrong address? Update it in
        <a href="<?= e(url('settings')) ?>">settings</a>, then request a new link.</p>
    <?php else: ?>
      <p class="muted">Sign in to request a confirmation link.</p>
      <p style="margin-top:20px"><a class="btn btn-primary" href="<?= e(url('login')) ?>">Sign in</a></p>
    <?php endif; ?>
  <?php endif; ?>
</div></div>
