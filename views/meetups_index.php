<?php /** @var array $meetups */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Meetups</p>
  <h1>Public travel meetups</h1>
  <div class="callout"><b>Optional, public, and safety-first.</b> Meetups are a way to meet fellow travelers in a destination — <b>not dating, not hookups</b>. We never share precise or real-time location. <a href="<?= e(url('safety')) ?>">Read the safety guidance →</a></div>
  <?php if (!$meetups): ?>
    <div class="empty-cta" style="margin:14px 0 50px">
      <h3>No public meetups yet.</h3>
      <p class="muted" style="margin:0">When a traveler hosts one, it shows up here — optional, public, never dating, never precise location. In the meantime, see where travelers are headed.</p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url('explore')) ?>">Explore destinations</a></p>
    </div>
  <?php else: ?>
    <div class="grid g-2" style="padding:14px 0 50px">
      <?php foreach ($meetups as $m): ?>
        <article class="card"><div class="card-body">
          <span class="chip"><?= e($m['dest_name']) ?></span>
          <h3 style="margin:.4rem 0 .2rem"><a href="<?= e(url('meetup/'.$m['id'])) ?>"><?= e($m['title']) ?></a></h3>
          <p class="muted" style="margin:0"><?= e(date('l, M j, Y · g:ia', strtotime((string)$m['date_start']))) ?></p>
          <p style="margin:.5rem 0"><?= e(mb_strimwidth((string)$m['description'],0,140,'…')) ?></p>
          <div class="meta-row">Hosted by @<?= e($m['host']['username']??'') ?> · <?= (int)$m['going'] ?> going</div>
        </div></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
