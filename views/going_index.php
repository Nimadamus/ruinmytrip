<?php /** @var array $rows @var ?array $me @var array $dests @var array $cities */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Who's going</p>
  <h1>Who's going</h1>
  <div class="callout"><b>Destination + date range only.</b> RuinMyTrip never shows precise or real-time location. Share your plans only if you choose to, and control who sees them.</div>

  <?php if ($me): ?>
    <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url('matches')) ?>">See who overlaps your dates</a></p>
    <div class="card" style="margin:18px 0"><div class="card-body">
      <?php $current = null; $lockDestId = 0; include __DIR__.'/_going_form.php'; ?>
    </div></div>
  <?php else: ?>
    <p style="margin:16px 0"><a class="btn btn-accent" href="<?= e(url('register')) ?>">Join free to share dates</a></p>
  <?php endif; ?>

  <?php /* Three sentences, because somebody who arrived from a search for "travel buddy" has never
           heard of this site and will not read a paragraph to find out what it does. */ ?>
  <div class="grid g-3" style="gap:14px;margin:18px 0">
    <div class="card"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 6px">1</p>
      <b>Post the city and the dates.</b>
      <p class="muted" style="margin:.3rem 0 0">Twenty seconds. Nothing finer than the city and the range, ever.</p>
    </div></div>
    <div class="card"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 6px">2</p>
      <b>See whose trip overlaps yours.</b>
      <p class="muted" style="margin:.3rem 0 0">Same city, same days. You are told when somebody new lands on your dates.</p>
    </div></div>
    <div class="card"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 6px">3</p>
      <b>Meet in public, or do not.</b>
      <p class="muted" style="margin:.3rem 0 0">Message first, meet if you want to. Meetups are public and 18+.</p>
    </div></div>
  </div>

  <?php if (!empty($cities)): ?>
    <h2 style="margin:26px 0 10px">Find travelers by city</h2>
    <div class="tag-list" style="margin-bottom:26px">
      <?php foreach ($cities as $c): $n = (int)$c['going_count'] + (int)$c['meetup_count']; ?>
        <a class="chip" href="<?= e(url('d/'.$c['slug'].'/travelers')) ?>"><?= e($c['name']) ?><?php
          if ($n): ?> <span class="hint"><?= $n ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <div class="empty-cta" style="margin:14px 0 50px">
      <h3>Nobody has posted public travel plans yet.</h3>
      <p class="muted" style="margin:0">Whoever goes first is the traveler everybody arriving after them
        sees. Post the city and the range; nothing finer is ever shown.</p>
    </div>
  <?php else: ?>
    <div class="grid g-2" style="padding:14px 0 50px">
      <?php foreach ($rows as $r): ?>
        <div class="card"><div class="card-body" style="display:flex;gap:14px;align-items:center">
          <img class="avatar" style="width:48px;height:48px" src="<?= e(avatar_url($r['avatar_url']??null)) ?>" alt="">
          <div>
            <b><a href="<?= e(url('u/'.$r['username'])) ?>">@<?= e($r['username']) ?></a></b>
            <p class="muted" style="margin:.1rem 0 0">Heading to <a href="<?= e(url('d/'.$r['dest_slug'])) ?>"><?= e($r['dest_name']) ?></a></p>
            <p class="hint" style="margin:.1rem 0 0"><?= e(date('M j', strtotime((string)$r['date_from']))) ?> to <?= e(date('M j, Y', strtotime((string)$r['date_to']))) ?></p>
          </div>
        </div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
