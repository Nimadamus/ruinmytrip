<?php /** @var array $p @var array $errors @var bool $sent */ ?>
<div class="wrap" style="max-width:640px;padding:30px 20px 60px">
  <p class="crumbs">
    <a href="<?= e(url()) ?>">Home</a> /
    <a href="<?= e(url('d/'.$p['dest_slug'])) ?>"><?= e((string) $p['dest_name']) ?></a> /
    <a href="<?= e(url(ltrim(rmt_place_path($p), '/'))) ?>"><?= e((string) $p['name']) ?></a> /
    Correction
  </p>

  <?php if ($sent): ?>
    <h1 style="margin:.2rem 0 .4rem">Thank you.</h1>
    <p style="font-size:1.05rem">
      A person will read this and check it. Nothing you sent has changed the page, and nothing will
      until somebody has looked.
    </p>
    <p class="hint">
      That is deliberate. A form that could mark a business permanently closed on one anonymous
      click would be a way to damage a business, not a way to fix a page.
    </p>
    <p style="margin:20px 0 0">
      <a class="btn btn-accent" href="<?= e(url(ltrim(rmt_place_path($p), '/'))) ?>">Back to <?= e((string) $p['name']) ?></a>
    </p>
  <?php else: ?>
    <h1 style="margin:.2rem 0 .4rem">Suggest a correction</h1>
    <p class="muted" style="margin:0 0 4px"><?= e((string) $p['name']) ?>, <?= e((string) $p['dest_name']) ?></p>
    <p class="hint" style="margin:0 0 20px">
      No account needed. A person reads every one of these, and nothing here edits the page
      automatically.
    </p>

    <?php if ($errors): ?>
      <div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url(ltrim(rmt_place_path($p), '/') . '/correct')) ?>">
      <?= csrf_field() ?>
      <label for="kind">What is wrong?</label>
      <select id="kind" name="kind" required>
        <?php foreach (RMT_FEEDBACK_PLACE_KINDS as $k): ?>
          <option value="<?= e($k) ?>"<?= input('kind') === $k ? ' selected' : '' ?>><?= e(rmt_feedback_kind_label($k)) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="message">What should it say?</label>
      <textarea id="message" name="message" maxlength="<?= RMT_FEEDBACK_MAX ?>" required
                placeholder="It moved to 14 Rue Cler in March, or: it now closes at 4pm on Sundays."><?= e(input('message')) ?></textarea>
      <p class="hint" style="margin:4px 0 0">
        If you have a link that shows it &mdash; the venue's own site, a notice on the door you
        photographed &mdash; paste it in. It gets checked faster.
      </p>

      <?php /* Optional and last. Requiring an address to tell us a museum moved is a good way not
               to be told. */ ?>
      <label for="contact_email" style="margin-top:16px">Your email <span class="muted">(optional, only so we can ask a follow-up)</span></label>
      <input type="email" id="contact_email" name="contact_email" maxlength="190" value="<?= e(input('contact_email')) ?>">

      <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-accent" type="submit">Send correction</button>
        <a class="btn btn-ghost" href="<?= e(url(ltrim(rmt_place_path($p), '/'))) ?>">Cancel</a>
      </div>
    </form>
  <?php endif; ?>
</div>
