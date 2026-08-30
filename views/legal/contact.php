<?php /** @var array $errors @var bool $sent */ ?>
<div class="wrap prose" style="max-width:760px;padding:30px 20px 60px">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Contact</p>
  <h1>Get in touch</h1>

  <?php /* Routes first, address last. Most of what people want to tell us has a place in the
           product that handles it properly -- a correction reaches the queue with the place already
           attached, a report reaches moderation with the review already attached -- and sending
           those to an inbox instead loses that context and takes longer for everyone. */ ?>
  <p style="font-size:1.05rem;color:var(--muted)">
    Most things are quickest to report from the page they are about.
  </p>

  <h2>Something on a place page is wrong</h2>
  <p>
    Wrong address, wrong opening hours, wrong phone number or website, wrong category, the place has
    moved, or it has closed. Use <b>Suggest a correction</b> at the bottom of that place page. It
    reaches a person, and nothing you send changes the site on its own.
  </p>

  <h2>A review breaks the guidelines</h2>
  <p>
    Use <b>Report</b> on the review itself. Worth knowing before you do: a report is not a verdict.
    It does not hide anything, no number of reports hides anything, and a review is never actioned
    for being negative. See the <a href="<?= e(url('guidelines')) ?>">community guidelines</a> for
    what actually breaks the rules.
  </p>

  <h2>A place is missing</h2>
  <p>
    Tell us on <a href="<?= e(url('contribute')) ?>">the contribution page</a>. Places are added by
    hand after we check them, which is slower and is the reason the site cannot be filled with
    entries that do not exist.
  </p>

  <h2>Everything else</h2>
  <?php /* A form rather than an address. We publish no staff inbox, and inventing one that nobody
           reads would be worse than having none: the message would go nowhere and the sender would
           never know. This lands in the same queue as the place corrections, which a person works
           through. */ ?>
  <?php if (!empty($sent)): ?>
    <div class="callout"><b>Sent.</b> A person reads these. If you left an address we may come back
      to you; if not, we still read it.</div>
  <?php else: ?>
    <?php if (!empty($errors)): ?>
      <div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('contact')) ?>" style="max-width:560px">
      <?= csrf_field() ?>
      <label for="kind">What is this about?</label>
      <select id="kind" name="kind">
        <option value="site_problem">Something on the site is broken</option>
        <option value="privacy_request">A privacy request</option>
        <option value="general">General feedback</option>
      </select>
      <label for="message">Tell us</label>
      <textarea id="message" name="message" maxlength="2000" required
                placeholder="What happened, and where on the site."></textarea>
      <label for="contact_email" style="margin-top:14px">Your email
        <span class="muted">(optional, and the only way we can reply)</span></label>
      <input type="email" id="contact_email" name="contact_email" maxlength="190">
      <div style="margin-top:18px"><button class="btn btn-accent" type="submit">Send</button></div>
    </form>
  <?php endif; ?>
  <p style="margin-top:18px">
    For privacy requests, the <a href="<?= e(url('privacy')) ?>">privacy policy</a> sets out what we
    hold and what you can ask for.
  </p>
</div>
