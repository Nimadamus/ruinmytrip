<?php /** @var array $meetups @var ?array $me @var bool $canHost */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Meetups</p>
  <div class="section-head">
    <div><h1 style="margin:0">Public travel meetups</h1></div>
    <?php /* The host route is offered to everyone, signed in or not. A page that says "when a
             traveler hosts one, it shows up here" and never shows anyone how is the reason there
             were none. The 18+ rule is enforced on the route, and stated here rather than hiding
             the button, so an under-18 member is told why instead of wondering. */ ?>
    <?php if (!$me): ?>
      <a class="btn btn-accent btn-sm" href="<?= e(url('login?return=' . rawurlencode('/meetup/new'))) ?>">Host a meetup</a>
    <?php elseif ($canHost): ?>
      <a class="btn btn-accent btn-sm" href="<?= e(url('meetup/new')) ?>">Host a meetup</a>
    <?php else: ?>
      <span class="hint">Hosting and attending are 18+.</span>
    <?php endif; ?>
  </div>
  <div class="callout"><b>Optional, public, and safety-first.</b> Meetups are a way to meet fellow travelers in a destination — <b>not dating, not hookups</b>. We never share precise or real-time location. <a href="<?= e(url('safety')) ?>">Read the safety guidance →</a></div>
  <?php if (!$meetups): ?>
    <div class="empty-cta" style="margin:14px 0 50px">
      <h3>No public meetups yet.</h3>
      <p class="muted" style="margin:0">Meetups are optional, public, never dating and never precise location. Host the first one, or see where travelers are headed.</p>
      <p style="margin:16px 0 0">
        <?php if ($canHost): ?><a class="btn btn-accent" href="<?= e(url('meetup/new')) ?>">Host a meetup</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('explore')) ?>">Explore destinations</a>
      </p>
    </div>
  <?php else: ?>
    <div class="grid g-2" style="padding:14px 0 50px">
      <?php foreach ($meetups as $m): ?>
        <article class="card"><div class="card-body">
          <span class="chip"><?= e($m['dest_name']) ?></span>
          <h3 style="margin:.4rem 0 .2rem"><a href="<?= e(url('meetup/'.$m['id'])) ?>"><?= e($m['title']) ?></a></h3>
          <p class="muted" style="margin:0"><?= e(date('l, M j, Y · g:ia', strtotime((string)$m['date_start']))) ?></p>
          <p style="margin:.5rem 0"><?= e(mb_strimwidth((string)$m['description'],0,140,'…')) ?></p>
          <div class="meta-row">Hosted by @<?= e($m['host']['username']??'') ?> ·
            <?= (int)$m['going'] ?> going<?php if ((int)$m['capacity'] > 0): ?> of <?= (int)$m['capacity'] ?><?php endif; ?>
            <?php if (rmt_meetup_is_full($m, (int)$m['going'])): ?> · <span class="chip">Full</span><?php endif; ?>
          </div>
        </div></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
