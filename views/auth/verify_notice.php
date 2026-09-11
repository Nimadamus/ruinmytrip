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
      <?php /* What actually happened, not what usually happens. A page that says "we sent you a
               link" under a banner saying the email could not be sent is the first screen a new
               member sees, and it makes the whole product look unreliable in one glance. */ ?>
      <?php if (($mailSent ?? true)): ?>
        <p class="muted">We sent a link to <b><?= e($me['email']) ?></b>. Click it to confirm this address.</p>
      <?php else: ?>
        <p class="muted">The email to <b><?= e($me['email']) ?></b> did not go out. Ask for another
          one below, and if it keeps failing the address may have a typo in it.</p>
      <?php endif; ?>
      <p class="muted">Confirming is only needed before you post a trip or a review. Everything
        else is open to you now.</p>
      <form method="post" action="<?= e(url('verify-email/resend')) ?>" style="margin-top:18px"><?= csrf_field() ?>
        <button class="btn btn-primary">Send me a new link</button>
      </form>
      <p class="muted" style="margin-top:16px">Wrong address? Update it in
        <a href="<?= e(url('settings')) ?>">settings</a>, then request a new link.</p>
      <?php /* And somewhere to go. This was a dead end: a new member's first screen offered one
               button, and it was about email rather than about travelling anywhere. */ ?>
      <p style="margin-top:22px;display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('explore')) ?>">Browse cities</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('travelers')) ?>">See who is going</a>
      </p>
    <?php else: ?>
      <p class="muted">Sign in to request a confirmation link.</p>
      <p style="margin-top:20px"><a class="btn btn-primary" href="<?= e(url('login')) ?>">Sign in</a></p>
    <?php endif; ?>
  <?php endif; ?>
</div></div>
