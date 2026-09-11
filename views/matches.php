<?php /** @var array $byDest @var array $wishlist @var array $shared @var array $myPlans @var array $me @var ?array $home @var array $visitors @var array $neighbours */ ?>
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
        <?= e(date('M j', strtotime((string) $d['my_from']))) ?> to
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
                  · <?= e(date('M j', strtotime((string) $r['overlap_from']))) ?> to
                    <?= e(date('M j', strtotime((string) $r['overlap_to']))) ?>
                <?php endif; ?>
              </p>
              <p class="hint" style="margin:.15rem 0 0">Their trip:
                <?= e(date('M j', strtotime((string) $r['their_from']))) ?> to
                <?= e(date('M j, Y', strtotime((string) $r['their_to']))) ?></p>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('messages/'.$r['username'])) ?>">Message</a>
          </div></div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <?php /* An empty match list is the normal state on a young network and the page has to stay
             useful anyway: what is missing, what fills it, and then real cities and real people,
             never an invented one. */ ?>
    <?php if (!$myPlans): ?>
      <?php $emptyTitle = 'Nobody can match you yet';
            $emptyWhy = 'Matching needs one thing from you: a city and a date range. Post it and this page fills in as other travelers post theirs.';
            $emptyCtaText = 'Post your dates'; $emptyCtaUrl = url('trip/new');
            include __DIR__ . '/_nothing_yet.php'; ?>
    <?php else: ?>
      <?php $emptyTitle = 'No overlapping dates yet';
            $emptyWhy = 'Your dates are in. Nobody else is holding dates in those cities that touch yours, so this page will change on its own when somebody does.';
            $emptyCtaText = "See everyone's dates"; $emptyCtaUrl = url('going');
            include __DIR__ . '/_nothing_yet.php'; ?>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* Where you live, pointed the other way round. Matching everywhere else means two people
           going to the same city; for somebody at home it means a visitor, and a local is the
           person a visitor most wants to meet. */ ?>
  <?php if ($home && ($visitors || $neighbours)): ?>
    <hr style="margin:32px 0">
    <h2>In <?= e($home['name']) ?>, where you live</h2>
    <?php if ($visitors): ?>
      <p class="hint" style="margin:0 0 10px">Travelers coming to your city. You are the local here.</p>
      <div class="tag-list" style="margin-bottom:18px">
        <?php foreach ($visitors as $v): ?>
          <a class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:.35rem .7rem"
             href="<?= e(url('u/'.$v['username'])) ?>">
            <img class="avatar" style="width:22px;height:22px" src="<?= e(avatar_url($v['avatar_url'] ?? null)) ?>" alt="">
            @<?= e($v['username']) ?>
            <span class="hint"><?= e(date('M j', strtotime((string)$v['date_from']))) ?> to <?= e(date('M j', strtotime((string)$v['date_to']))) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($neighbours): ?>
      <p class="hint" style="margin:0 0 10px">Members who live in <?= e($home['name']) ?> too.</p>
      <div class="tag-list" style="margin-bottom:8px">
        <?php foreach ($neighbours as $nb): ?>
          <a class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:.35rem .7rem"
             href="<?= e(url('u/'.$nb['username'])) ?>">
            <img class="avatar" style="width:22px;height:22px" src="<?= e(avatar_url($nb['avatar_url'])) ?>" alt="">
            @<?= e($nb['username']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <p style="margin:6px 0 0"><a href="<?= e(url('d/'.$home['slug'].'/travelers')) ?>">Everyone in <?= e($home['name']) ?> &rarr;</a></p>
  <?php elseif (!$home): ?>
    <hr style="margin:32px 0">
    <p class="hint" style="margin:0">Set the city you live in on
      <a href="<?= e(url('u/'.($me['username'] ?? '').'/edit')) ?>">your profile</a> and travelers heading
      there will find you, and you will see them coming.</p>
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
