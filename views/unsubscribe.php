<?php /** @var bool $ok */ ?>
<div class="wrap" style="text-align:center;padding:80px 20px;min-height:50vh">
  <?php if ($ok): ?>
    <h1>You're unsubscribed</h1>
    <p class="muted">You will not get any more weekly activity emails from RuinMyTrip. This does not affect account, verification, or password emails.</p>
  <?php else: ?>
    <h1>That link didn't work</h1>
    <p class="muted">This unsubscribe link is invalid or has already been used differently. Nothing was changed.</p>
  <?php endif; ?>
  <p style="margin-top:20px"><a class="btn btn-primary" href="<?= e(url()) ?>">Back home</a></p>
</div>
