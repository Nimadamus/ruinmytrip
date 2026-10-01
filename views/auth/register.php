<?php /** @var array $errors */ ?>
<div class="wrap"><div class="form-card">
  <?php /* The place they were on their way to review, so the interruption explains itself. */ ?>
  <?php $rp = rmt_return_place_name((string) ($return ?? '')); ?>
  <h1>Join RuinMyTrip</h1>
  <?php if ($rp !== null): ?>
    <p class="muted">Create an account and we will take you straight back to your review of
      <b><?= e($rp) ?></b>. It takes a moment and your review is kept while you do it.</p>
  <?php endif; ?>
  <?php /* Finish the sentence the visitor started. Somebody who clicked join from a city's people
           page wants one specific thing, and being told the general pitch instead is how a signup
           stops halfway. */ ?>
  <?php $intent = function_exists('rmt_join_intent_line') ? rmt_join_intent_line((string) ($return ?? '')) : null; ?>
  <?php if ($intent !== null): ?>
    <p style="font-size:1.05rem;margin:0 0 10px"><b><?= e($intent) ?></b></p>
  <?php endif; ?>
  <p class="muted">RuinMyTrip is a travel community: post where you are going, see whose dates overlap
    yours, meet up in public, and write reviews other travelers can trust. Free, 16+, takes a minute.
    <a href="<?= e(url('start')) ?>">How it works</a>.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('register')) ?>"><?= csrf_field() ?>
    <?php /* Where the visitor was headed before they needed an account -- usually a review they
             were about to write. Carried through the form so a validation error does not lose it
             either. */ ?>
    <input type="hidden" name="return" value="<?= e((string) ($return ?? '')) ?>">
    <?php if (function_exists('rmt_invite_referrer_name') && ($refName = rmt_invite_referrer_name())): ?>
      <p class="hint" style="margin:0 0 10px">Invited by <b>@<?= e($refName) ?></b>. They will hear when you join.</p>
    <?php endif; ?>
    <?php $qjReturn = (string) ($return ?? ''); include __DIR__ . '/_quick_join_fields.php'; ?>
    <div style="margin-top:12px"><button class="btn btn-primary btn-block">Create account</button></div>
  </form>
  <p class="muted" style="margin-top:16px">Already have an account? <a href="<?= e(url('login') . (!empty($return) ? '?return=' . rawurlencode((string) $return) : '')) ?>">Sign in</a></p>
</div></div>
