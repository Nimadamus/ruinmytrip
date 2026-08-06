<?php
/**
 * The business response form.
 *
 * Deliberately open to people with no account: the manager of a named hotel should not have to
 * join a travel community to answer a claim about their business. Verification happens via the
 * contact email and a moderator, not via a login wall.
 *
 * @var array $w @var array $errors
 */
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> /
    <a href="<?= e(url(ltrim(rmt_warning_path($w), '/'))) ?>"><?= e($w['title']) ?></a> / Respond</p>
</div>
<form class="form-card form-wide" method="post" action="<?= e(url('w/' . (int) $w['id'] . '/respond')) ?>">
  <?= csrf_field() ?>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <h1 style="margin-bottom:.2rem">Respond to a warning</h1>
  <p class="muted" style="margin-top:0">
    You are responding to <b><?= e($w['title']) ?></b><?php if (!empty($w['provider_name'])): ?>,
    which names <b><?= e($w['provider_name']) ?></b><?php endif; ?>.
  </p>

  <div class="callout">
    <b>How this works.</b> Your response is checked by a moderator, then published directly under the
    warning at the same prominence. We do not delete a traveler's account because a business disagrees with
    it — but a documented factual error will be corrected or the report marked <b>Disputed</b>. Your email
    address is used to verify who you are and is never published.
  </div>

  <p>
    <label for="rn"><b>Business or organisation</b> (required)</label>
    <input id="rn" name="responder_name" required maxlength="160" style="width:100%" value="<?= e((string) input('responder_name')) ?>">
  </p>
  <p>
    <label for="rr"><b>Your role</b></label>
    <input id="rr" name="responder_role" maxlength="120" style="width:100%" placeholder="General manager, owner, guest relations" value="<?= e((string) input('responder_role')) ?>">
  </p>
  <p>
    <label for="re"><b>Contact email</b> (required, never published)</label>
    <input id="re" type="email" name="contact_email" required style="width:100%" value="<?= e((string) input('contact_email')) ?>">
    <span class="hint">Use an address at the business's own domain where possible — it is the fastest way for us to verify you.</span>
  </p>
  <p>
    <label for="rb"><b>Your response</b> (required)</label>
    <textarea id="rb" name="body" rows="8" required maxlength="4000" style="width:100%"
              placeholder="What actually happened from your side, what has changed since, and what a guest should expect now."><?= e((string) ($_POST['body'] ?? '')) ?></textarea>
  </p>

  <button class="btn btn-accent" type="submit">Send response for review</button>
  <a class="btn btn-ghost" href="<?= e(url(ltrim(rmt_warning_path($w), '/'))) ?>">Cancel</a>
</form>
