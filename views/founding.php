<?php /** @var int $n @var int $left */ $me = current_user(); ?>
<section class="hero" style="min-height:auto;padding:48px 0">
  <div class="hero-inner">
    <p class="eyebrow" style="color:#7dd3c8">The first 100</p>
    <h1>Founding Traveler</h1>
    <p>RuinMyTrip does not invent members. The first 100 real people who join and publish a review get the Founding Traveler badge. That is the whole deal.</p>
    <p style="margin:18px 0 0">
      <?php if ($me): ?>
        <a class="btn btn-accent" href="<?= e(url('review/new')) ?>">Write your first review</a>
      <?php else: ?>
        <a class="btn btn-accent" href="<?= e(url('register')) ?>">Join free</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= e(url('travelers')) ?>" style="color:#fff;border-color:rgba(255,255,255,.45)">See who is here</a>
    </p>
  </div>
</section>
<section class="block"><div class="wrap" style="max-width:720px">
  <div class="grid g-2">
    <div class="card"><div class="card-body">
      <p class="eyebrow">How to earn it</p>
      <ol style="margin:0;padding-left:1.2em">
        <li>Create an account. You must be 16+.</li>
        <li>Publish one honest review of a destination or a place. The part that nearly ruined the trip is required.</li>
        <li>If you are among the first 100 members to do that, the badge is automatic.</li>
      </ol>
    </div></div>
    <div class="card"><div class="card-body">
      <p class="eyebrow">Right now</p>
      <p style="font-size:2rem;margin:0;font-weight:800"><?= (int)$n ?></p>
      <p class="muted" style="margin:.2rem 0 0"><?= $n === 1 ? 'traveler so far' : 'travelers so far' ?><?php if ($left > 0): ?>, <?= (int)$left ?> founding slots left<?php endif; ?>.</p>
      <p class="hint" style="margin:12px 0 0">Editorial staff do not count. Fake reviews are not published. Empty profiles do not get the badge.</p>
    </div></div>
  </div>
  <div class="callout" style="margin-top:24px">
    Signing up is not an achievement. Writing something true is.
  </div>
</div></section>
