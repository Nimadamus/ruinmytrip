<?php /** @var array $byDest @var array $wishlist @var array $shared @var array $myPlans @var array $me */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Matches</p></div>
<div class="wrap">
  <h1>Your matches</h1>
  <p class="muted" style="max-width:60ch">Travelers who will be in the same city at the same time as
    you, and people who want to go where you want to go. Destination and dates only, the same as
    everywhere else on RuinMyTrip.</p>

  <?php /* The strong tier first. A date overlap is the only thing on this page somebody can act on
           today, so it goes above the fold and the weaker tier waits below it. */ ?>
  <?php if ($byDest): ?>
    <?php foreach ($byDest as $slug => $g): $d = $g['dest']; ?>
      <h2 style="margin:26px 0 4px"><a href="<?= e(url('d/'.$slug)) ?>"><?= e((string) $d['name']) ?></a></h2>
      <p class="hint" style="margin:0 0 12px">You are there
        <?= e(date('M j', strtotime((string) $d['my_from']))) ?> –
        <?= e(date('M j, Y', strtotime((string) $d['my_to']))) ?></p>
      <?php /* An event that already exists on a day they are already there is a much smaller first
               step than messaging somebody they have never met. */ ?>
      <?php if (!empty($g['meetups'])): ?>
        <?php foreach ($g['meetups'] as $mu): ?>
          <div class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px">
            <b><a href="<?= e(url('meetup/'.(int) $mu['id'])) ?>"><?= e((string) $mu['title']) ?></a></b>
            <p class="hint" style="margin:.2rem 0 0"><?= e(date('D M j, H:i', strtotime((string) $mu['date_start']))) ?>
              · hosted by @<?= e((string) $mu['host_username']) ?>
              · <?= (int) $mu['going_count'] ?> going</p>
          </div></div>
        <?php endforeach; ?>
      <?php endif; ?>
      <p style="margin:0 0 14px"><a class="btn btn-ghost btn-sm"
         href="<?= e(url('meetup/new?d='.$slug.'&start='.substr((string) $d['my_from'], 0, 10))) ?>">Propose a meetup here</a></p>

      <div class="grid g-2">
        <?php foreach ($g['people'] as $r): ?>
          <div class="card"><div class="card-body" style="display:flex;gap:14px;align-items:center">
            <img class="avatar" style="width:48px;height:48px" src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt="">
            <div style="flex:1;min-width:0">
              <b><a href="<?= e(url('u/'.$r['username'])) ?>">@<?= e((string) $r['username']) ?></a></b>
              <?php if (!empty($r['home_city'])): ?><span class="hint"> · <?= e((string) $r['home_city']) ?></span><?php endif; ?>
              <p class="muted" style="margin:.15rem 0 0">
                <?= (int) $r['overlap_days'] ?> <?= (int) $r['overlap_days'] === 1 ? 'day' : 'days' ?> together
                <?php if (!empty($r['overlap_from'])): ?>
                  · <?= e(date('M j', strtotime((string) $r['overlap_from']))) ?> –
                    <?= e(date('M j', strtotime((string) $r['overlap_to']))) ?>
                <?php endif; ?>
              </p>
              <p class="hint" style="margin:.15rem 0 0">Their trip:
                <?= e(date('M j', strtotime((string) $r['their_from']))) ?> –
                <?= e(date('M j, Y', strtotime((string) $r['their_to']))) ?></p>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('messages/'.$r['username'])) ?>">Message</a>
          </div></div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-cta" style="margin:18px 0">
      <h3>No overlapping dates yet.</h3>
      <?php if (!$myPlans): ?>
        <p class="muted" style="margin:0">Nobody can match you while we do not know where you are
          headed. Share a destination and a date range, and this fills in as other travelers do the same.</p>
        <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url('going')) ?>">Share your dates</a></p>
      <?php else: ?>
        <p class="muted" style="margin:0">Your plans are in. Nobody else is holding dates in those
          cities that touch yours yet, so this page will change on its own when they do.</p>
        <p style="margin:16px 0 0"><a class="btn btn-ghost" href="<?= e(url('going')) ?>">See everyone's plans</a></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php /* The cold-start tier. Somebody who joined an hour ago has saved cities and booked nothing,
           and shared taste is the only honest thing there is to show them. */ ?>
  <?php if ($wishlist): ?>
    <hr style="margin:32px 0">
    <h2>Want to go to the same places</h2>
    <p class="hint" style="margin:0 0 12px">No dates from either of you yet. Same cities on both wishlists.</p>
    <div class="grid g-2" style="padding-bottom:50px">
      <?php foreach ($wishlist as $w): $places = $shared[(int) $w['user_id']] ?? []; ?>
        <div class="card"><div class="card-body" style="display:flex;gap:14px;align-items:center">
          <img class="avatar" style="width:48px;height:48px" src="<?= e(avatar_url($w['avatar_url'] ?? null)) ?>" alt="">
          <div style="flex:1;min-width:0">
            <b><a href="<?= e(url('u/'.$w['username'])) ?>">@<?= e((string) $w['username']) ?></a></b>
            <?php if (!empty($w['home_city'])): ?><span class="hint"> · <?= e((string) $w['home_city']) ?></span><?php endif; ?>
            <p class="muted" style="margin:.15rem 0 0">
              <?php foreach (array_slice($places, 0, 3) as $i => $pl): ?>
                <?= $i ? ', ' : '' ?><a href="<?= e(url('d/'.$pl['slug'])) ?>"><?= e($pl['name']) ?></a>
              <?php endforeach; ?>
              <?php if (count($places) > 3): ?> and <?= count($places) - 3 ?> more<?php endif; ?>
            </p>
          </div>
        </div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div style="height:30px"></div>
</div>
