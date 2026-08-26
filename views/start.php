<?php $me = current_user(); ?>
<section class="hero founding-hero">
  <div class="hero-inner">
    <p class="eyebrow" style="color:#7dd3c8">The site is live. This is the launch.</p>
    <h1>How to start RuinMyTrip</h1>
    <p>There is no waiting list and no secret switch. ruinmytrip.com is already public. What it needs is real people writing real reviews. Here is the exact sequence.</p>
  </div>
</section>
<section class="block"><div class="wrap" style="max-width:760px">
  <ol class="empty-steps">
    <li><b>Create an account.</b> Use <a href="<?= e(url('register')) ?>">Join free</a>. You must be 16+. Confirm the email we send, or you cannot publish.</li>
    <li><b>Stamp cities you have actually been to.</b> Open a destination and tap I've been. That is not a review and not a rating. It puts a real face on the page.</li>
    <li><b>Write one honest review.</b> Rate it, say what was great, and fill in what nearly ruined the trip. That last field is required on purpose. First 100 reviewers get Founding Traveler.</li>
    <li><b>Share the link, not the pitch.</b> Send one city page or one tax article to people who already travel. Do not invent extra members. The empty counts are the point until someone posts.</li>
  </ol>
  <p style="margin:28px 0 0;display:flex;gap:10px;flex-wrap:wrap">
    <?php if ($me): ?>
      <a class="btn btn-accent" href="<?= e(url('review/new')) ?>">Write a review</a>
      <a class="btn btn-ghost" href="<?= e(url('explore')) ?>">Pick a city</a>
    <?php else: ?>
      <a class="btn btn-accent" href="<?= e(url('register')) ?>">Create your account</a>
      <a class="btn btn-ghost" href="<?= e(url('founding')) ?>">Founding Traveler rules</a>
    <?php endif; ?>
  </p>
  <div class="callout" style="margin-top:28px">
    Sharing the founding page, a 2026 tax article, or a city you actually know beats any more product features. The reviews are the launch.
  </div>
</div></section>
