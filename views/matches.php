<?php /** @var array $byDest @var array $wishlist @var array $shared @var array $myPlans @var array $me @var ?array $home @var array $visitors @var array $neighbours @var ?array $newTrip @var array $cities @var array $interests @var array $followingIds @var array $myConnects @var array $connectsIn */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Matches</p></div>
<div class="wrap">
  <h1>Your matches</h1>
  <p class="muted" style="max-width:60ch">Travelers who will be in the same city at the same time as
    you, and people who want to go where you want to go. Destination and dates only, the same as
    everywhere else on RuinMyTrip.</p>

  <?php /* People waiting on an answer from this reader. It is above the matches because it is the
           only thing on the page that somebody else is waiting for: a match list can be read
           tomorrow, a person who asked to meet you cannot. Two buttons and no text box, because
           the whole point of the request is that it carries no words for anybody to have to read
           or report. */ ?>
  <?php $rmtPending = []; foreach ($connectsIn as $tid => $rows) { foreach ($rows as $r) { if ((string) $r['state'] === 'interested') $rmtPending[] = [$tid, $r]; } } ?>
  <?php if ($rmtPending): ?>
    <section class="card connect-inbox" style="margin:18px 0"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 8px"><?= count($rmtPending) === 1 ? 'Somebody would like to meet on your trip' : count($rmtPending) . ' travelers would like to meet on your trips' ?></p>
      <?php foreach ($rmtPending as [$tid, $r]):
              $rt = $cities[$tid] ?? null; ?>
        <div class="ci-row">
          <a href="<?= e(url('u/' . $r['username'])) ?>"><img class="avatar" src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt=""></a>
          <div class="ci-main">
            <p style="margin:0">
              <b><a href="<?= e(url('u/' . $r['username'])) ?>"><?= e(trim((string) ($r['display_name'] ?? '')) !== '' ? (string) $r['display_name'] : '@' . $r['username']) ?></a></b>
              <span class="hint">would like to meet
                <?php if ($rt): ?>on your <?= e((string) $rt['name']) ?> trip<?php endif; ?></span>
            </p>
            <p class="hint" style="margin:.1rem 0 0">Saying yes lets you message each other. Saying no
              tells them nothing.</p>
            <div class="ci-acts">
              <form method="post" action="<?= e(url('connect/' . (int) $r['id'] . '/decide')) ?>"><?= csrf_field() ?>
                <input type="hidden" name="answer" value="accept">
                <input type="hidden" name="return" value="/matches">
                <button class="btn btn-primary btn-sm">Yes</button>
              </form>
              <form method="post" action="<?= e(url('connect/' . (int) $r['id'] . '/decide')) ?>"><?= csrf_field() ?>
                <input type="hidden" name="answer" value="decline">
                <input type="hidden" name="return" value="/matches">
                <button class="btn btn-ghost btn-sm">No thanks</button>
              </form>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('u/' . $r['username'])) ?>">View profile</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div></section>
  <?php endif; ?>

  <?php /* The trip somebody has just posted, named back to them. Everything below it is the answer
           to the question posting it asked. */ ?>
  <?php if ($newTrip && !empty($newTrip['dest_name']) && !empty($newTrip['date_from'])): ?>
    <div class="callout newtrip">
      <p style="margin:0 0 4px"><b>You are going to <a href="<?= e(url('d/' . $newTrip['dest_slug'])) ?>"><?= e((string) $newTrip['dest_name']) ?></a>,
        <?= e(date('M j', strtotime((string) $newTrip['date_from']))) ?> to
        <?= e(date('M j, Y', strtotime((string) $newTrip['date_to']))) ?>.</b></p>
      <p class="hint" style="margin:0">It is on your profile, and it is what everything below is
        matched against. <a href="<?= e(url('trip/' . (int) $newTrip['id'])) ?>">Open the trip</a>
        to add a plan or a photograph.</p>
    </div>
  <?php endif; ?>

  <?php /* One block per upcoming trip, whether or not anybody overlaps. A city with nobody in it
           is the normal state of a young network and is exactly where this page has to keep being
           worth opening, so the empty case gets the same room as the full one and is made of the
           same real things: the city's own questions, the people who have actually been, and the
           two controls that put the reader into it. */ ?>
  <?php foreach ($cities as $c): $slug = (string) $c['slug']; ?>
    <section class="mcity<?= $newTrip && (int) $c['trip_id'] === (int) $newTrip['id'] ? ' mcity-new' : '' ?>">
      <h2 style="margin:26px 0 2px"><a href="<?= e(url('d/' . $slug)) ?>"><?= e((string) $c['name']) ?></a></h2>
      <p class="hint" style="margin:0 0 12px">You are there
        <?= e(date('M j', strtotime((string) $c['my_from']))) ?> to
        <?= e(date('M j, Y', strtotime((string) $c['my_to']))) ?></p>

      <?php if (!empty($c['meetups'])): ?>
        <?php /* An event that already exists on a day they are already there is a much smaller
                 first step than messaging somebody they have never met. */ ?>
        <?php foreach ($c['meetups'] as $mu): ?>
          <div class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px">
            <b><a href="<?= e(url('meetup/' . (int) $mu['id'])) ?>"><?= e((string) $mu['title']) ?></a></b>
            <p class="hint" style="margin:.2rem 0 0"><?= e(date('D M j, H:i', strtotime((string) $mu['date_start']))) ?>
              &middot; hosted by @<?= e((string) $mu['host_username']) ?>
              &middot; <?= (int) $mu['going_count'] ?> going</p>
          </div></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($c['people']): ?>
        <p class="eyebrow" style="margin:14px 0 8px">On your dates</p>
        <div class="grid g-2">
          <?php foreach ($c['people'] as $tc):
                  $tcBack = '/matches'; $tcInterests = $interests; $tcFollowing = $followingIds; $tcConnects = $myConnects;
                  include __DIR__ . '/_traveler_card.php'; ?>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <?php /* The honest empty state. It says what is true, says why, and then hands over four
                 things that are real on this city today. Never an invented traveler and never a
                 count that is not a count. */ ?>
        <div class="cc-empty" style="margin-bottom:14px">
          <p style="margin:0 0 4px"><b>You may be early. Nobody's dates overlap yours in <?= e((string) $c['name']) ?> yet.</b></p>
          <p class="hint" style="margin:0 0 10px">This page fills in on its own as other travelers
            post their dates. In the meantime the city itself is not empty.</p>
          <?php /* The one moment an invite is genuinely useful rather than a nag: the reader has
                   just been told nobody is there, and the person most likely to be going the same
                   week is somebody they already know. No reward, no credit, nothing sent for them:
                   a link they hand over themselves. */ ?>
          <div class="cc-share" style="margin-top:0">
            <span class="hint">Know somebody going to <?= e((string) $c['name']) ?>?</span>
            <?php $shareUrl = !empty($c['trip_id'])
                      ? abs_url('/trip/' . (int) $c['trip_id'])
                      : abs_url('/d/' . $slug);
                  $shareText = !empty($c['trip_id'])
                      ? 'My dates for ' . (string) $c['name'] . ' are up. Post yours and we will both see it.'
                      : 'Going to ' . (string) $c['name'] . '? See who else is traveling there.';
                  $shareLabel = 'Invite a traveler';
                  include __DIR__ . '/_share.php'; ?>
          </div>
          <?php /* Three things that are real on this city today, so an empty page is still a page
                   worth being on. Following is how the reader hears when somebody does post dates,
                   which is the only thing that fixes an empty match list. */ ?>
          <p class="cc-empty-acts" style="margin:10px 0 0;display:flex;gap:8px;flex-wrap:wrap">
            <form method="post" action="<?= e(url('destination/save')) ?>" style="margin:0">
              <?= csrf_field() ?>
              <input type="hidden" name="destination_id" value="<?= (int) $c['id'] ?>">
              <input type="hidden" name="return" value="/matches">
              <input type="hidden" name="want" value="<?= !empty($c['following']) ? 'off' : 'on' ?>">
              <button class="btn btn-ghost btn-sm"><?= !empty($c['following'])
                  ? '★ Following ' . e((string) $c['name'])
                  : '☆ Follow ' . e((string) $c['name']) ?></button>
            </form>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('d/' . $slug . '#city-ask')) ?>">Ask the community</a>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('d/' . $slug . '/travelers')) ?>">Everyone in <?= e((string) $c['name']) ?></a>
          </p>
        </div>
      <?php endif; ?>

      <?php if ($c['near']): ?>
        <p class="eyebrow" style="margin:18px 0 8px">Just before or after you</p>
        <div class="grid g-2">
          <?php foreach ($c['near'] as $tc):
                  $tcBack = '/matches'; $tcInterests = $interests; $tcFollowing = $followingIds; $tcConnects = $myConnects;
                  include __DIR__ . '/_traveler_card.php'; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($c['talk']): ?>
        <p class="eyebrow" style="margin:18px 0 8px">Being asked in <?= e((string) $c['name']) ?></p>
        <?php foreach ($c['talk'] as $tp): ?>
          <div class="card" style="margin-bottom:8px"><div class="card-body" style="padding:12px 16px">
            <b><a href="<?= e(url('u/' . $tp['username'])) ?>">@<?= e((string) $tp['username']) ?></a></b>
            <span class="hint"> &middot; <?= e(ago((string) $tp['created_at'])) ?></span>
            <p style="margin:.35rem 0 .3rem"><?= e(mb_strimwidth((string) $tp['body'], 0, 180, '…')) ?></p>
            <p class="hint" style="margin:0"><a href="<?= e(url('post/' . (int) $tp['id'])) ?>">Answer this</a></p>
          </div></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($c['been']): ?>
        <p class="eyebrow" style="margin:18px 0 8px">Have actually been to <?= e((string) $c['name']) ?></p>
        <div class="tag-list" style="margin-bottom:6px">
          <?php foreach ($c['been'] as $bp): ?>
            <a class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:.35rem .7rem"
               href="<?= e(url('u/' . $bp['username'])) ?>">
              <img class="avatar" style="width:22px;height:22px" src="<?= e(avatar_url($bp['avatar_url'] ?? null)) ?>" alt="">
              @<?= e((string) $bp['username']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="mcity-acts">
        <?php if (!$c['following']): ?>
          <form method="post" action="<?= e(url('destination/save')) ?>">
            <?= csrf_field() ?><input type="hidden" name="destination_id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="want" value="on">
            <input type="hidden" name="return" value="/matches">
            <button class="btn btn-primary btn-sm">Follow <?= e((string) $c['name']) ?></button>
          </form>
        <?php else: ?>
          <span class="chip">Following <?= e((string) $c['name']) ?></span>
        <?php endif; ?>
        <a class="btn btn-accent btn-sm" href="<?= e(url('d/' . $slug) . '#city-ask') ?>">Ask the <?= e((string) $c['name']) ?> community</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('d/' . $slug . '/travelers')) ?>">Everyone in <?= e((string) $c['name']) ?></a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('meetup/new?d=' . $slug . '&start=' . substr((string) $c['my_from'], 0, 10))) ?>">Propose a meetup</a>
      </div>
    </section>
  <?php endforeach; ?>

  <?php if (!$cities): ?>
    <?php /* An empty match list is the normal state on a young network and the page has to stay
             useful anyway: what is missing, what fills it, and then real cities and real people,
             never an invented one. */ ?>
    <?php $emptyTitle = 'Nobody can match you yet';
          $emptyWhy = 'Matching needs one thing from you: a city and a date range. Post it and this page fills in as other travelers post theirs.';
          $emptyCtaText = 'Post your first trip'; $emptyCtaUrl = url('trip/new');
          include __DIR__ . '/_nothing_yet.php'; ?>
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
