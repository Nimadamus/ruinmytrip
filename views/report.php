<?php /** @var string $tt @var string $tid @var array $errors */ $errors = $errors ?? []; ?>
<div class="wrap"><div class="form-card">
  <h1>Report content</h1>
  <p class="muted">Tell us what's wrong. Our moderators review every report.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('report')) ?>"><?= csrf_field() ?>
    <input type="hidden" name="target_type" value="<?= e($tt) ?>">
    <input type="hidden" name="target_id" value="<?= e($tid) ?>">
    <label for="reason">Reason</label>
    <?php /* Values are the slugs the server allow-lists (RMT_REPORT_REASONS); the label is only
             what the human reads. Sending the label as the value would fail validation. */ ?>
    <select id="reason" name="reason">
      <?php foreach ([
        'spam'           => 'Spam or scam',
        'abuse'          => 'Harassment, hate or impersonation',
        'unsafe'         => 'Unsafe or dangerous',
        'misinformation' => 'Misleading or false',
        'off_topic'      => 'Off-topic / not travel',
        'other'          => 'Other',
      ] as $val => $label): ?>
        <option value="<?= e($val) ?>"><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="details">Details (optional)</label>
    <textarea id="details" name="details" maxlength="2000" placeholder="Anything that helps us understand"></textarea>
    <div style="margin-top:16px"><button class="btn btn-primary">Submit report</button></div>
  </form>
</div></div>
