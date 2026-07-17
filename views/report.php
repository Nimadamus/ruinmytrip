<?php /** @var string $tt @var string $tid */ ?>
<div class="wrap"><div class="form-card">
  <h1>Report content</h1>
  <p class="muted">Tell us what's wrong. Our moderators review every report.</p>
  <form method="post" action="<?= e(url('report')) ?>"><?= csrf_field() ?>
    <input type="hidden" name="target_type" value="<?= e($tt) ?>">
    <input type="hidden" name="target_id" value="<?= e($tid) ?>">
    <label for="reason">Reason</label>
    <select id="reason" name="reason">
      <?php foreach (['Spam or scam','Harassment or hate','Unsafe or dangerous','Sexual or adult content','Impersonation','Off-topic / not travel','Other'] as $r): ?>
        <option value="<?= e($r) ?>"><?= e($r) ?></option><?php endforeach; ?>
    </select>
    <label for="details">Details (optional)</label>
    <textarea id="details" name="details" placeholder="Anything that helps us understand"></textarea>
    <div style="margin-top:16px"><button class="btn btn-primary">Submit report</button></div>
  </form>
</div></div>
