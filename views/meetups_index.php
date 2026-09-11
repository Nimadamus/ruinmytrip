<?php /** @var array $meetups @var ?array $me @var bool $canHost @var array $openPlans */
$openPlans = $openPlans ?? [];
$items = $items ?? [];
$anything = (bool) $items; ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Meetups</p>
  <div class="section-head">
    <div><h1 style="margin:0">Meet other travelers</h1>
      <p class="hint" style="margin:.3rem 0 0">Meetups anyone can post, and plans on somebody's trip
        that they opened to other people. Same thing, one list.</p></div>
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
  <div class="callout"><b>Optional, public, and safety-first.</b> Meetups are a way to meet fellow travelers in a destination, <b>not dating, not hookups</b>. We never share precise or real-time location. <a href="<?= e(url('safety')) ?>">Read the safety guidance →</a></div>
  <?php if (!$anything): ?>
    <div class="empty-cta" style="margin:14px 0 50px">
      <h3>Nothing to turn up to yet.</h3>
      <p class="muted" style="margin:0">Host a meetup, or open a plan on a trip you have already
        posted so other travelers can join it. Optional, public, never dating, and never precise
        location.</p>
      <p style="margin:16px 0 0">
        <?php if ($canHost): ?><a class="btn btn-accent" href="<?= e(url('meetup/new')) ?>">Host a meetup</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('explore')) ?>">Explore destinations</a>
      </p>
    </div>
  <?php else: ?>
    <div class="grid g-2" style="padding:14px 0 50px">
      <?php foreach ($items as $it): $r = $it['row']; ?>
        <?php if ($it['kind'] === 'meetup'): ?>
          <article class="card"><div class="card-body">
            <span class="chip"><?= e((string) $r['dest_name']) ?></span>
            <h3 style="margin:.4rem 0 .2rem"><a href="<?= e(url('meetup/'.(int) $r['id'])) ?>"><?= e((string) $r['title']) ?></a></h3>
            <p class="muted" style="margin:0"><?= e(date('l, M j, Y · g:ia', strtotime((string) $r['date_start']))) ?></p>
            <p style="margin:.5rem 0"><?= e(mb_strimwidth((string) $r['description'], 0, 140, '…')) ?></p>
            <div class="meta-row">Hosted by @<?= e($r['host']['username'] ?? '') ?> &middot;
              <?= (int) $r['going'] ?> going<?php if ((int) $r['capacity'] > 0): ?> of <?= (int) $r['capacity'] ?><?php endif; ?>
              <?php if (rmt_meetup_is_full($r, (int) $r['going'])): ?> &middot; <span class="chip">Full</span><?php endif; ?>
            </div>
          </div></article>
        <?php else: ?>
          <?php /* A plan somebody opened to other travelers. Same card, because it is the same
                   offer: turn up on this day and you will not be on your own. */ ?>
          <article class="card"><div class="card-body">
            <?php if (!empty($r['dest_name'])): ?><span class="chip"><?= e((string) $r['dest_name']) ?></span><?php endif; ?>
            <h3 style="margin:.4rem 0 .2rem"><a href="<?= e(url('activity/'.(int) $r['id'])) ?>"><?= e((string) $r['title']) ?></a></h3>
            <p class="muted" style="margin:0"><?php
              if (!empty($r['day'])) {
                  echo e(date('l, M j, Y', strtotime((string) $r['day'])));
                  if (!empty($r['start_time'])) echo ' &middot; ' . e((string) $r['start_time']);
              } elseif (!empty($r['trip_from'])) {
                  echo 'Some time between ' . e(rmt_card_date_range((string) $r['trip_from'], (string) $r['trip_to']));
              } ?></p>
            <?php if (!empty($r['notes'])): ?>
              <p style="margin:.5rem 0"><?= e(mb_strimwidth((string) $r['notes'], 0, 140, '…')) ?></p>
            <?php endif; ?>
            <div class="meta-row">Planned by @<?= e((string) $r['username']) ?> &middot;
              <?= (int) $r['going_count'] ?> going<?php if ((int) ($r['capacity'] ?? 0) > 0): ?> of <?= (int) $r['capacity'] ?><?php endif; ?></div>
            <p style="margin:.4rem 0 0"><a class="btn btn-ghost btn-sm" href="<?= e(url('activity/'.(int) $r['id'])) ?>"><?= (string) $r['join_mode'] === 'open' ? 'Join this' : 'Ask to join' ?></a></p>
          </div></article>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
