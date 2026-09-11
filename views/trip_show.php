<?php /** @var array $t @var array $photos @var array $comments @var int $likeCount @var int $saveCount
        @var bool $liked @var bool $saved @var array $updates @var bool $isOwner @var string $phase
        @var array $alsoThere @var bool $isFollowingAuthor @var int $destGoing @var array $related
        @var array $authorSaid @var array $members @var array $invited @var bool $myInvite
        @var ?string $tripRole @var bool $canEdit */
$me = current_user();
$isFollowingAuthor = $isFollowingAuthor ?? false;
$destGoing = $destGoing ?? 0;
$related = $related ?? [];
$authorSaid = $authorSaid ?? [];
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <?php if($t['dest_slug']):?><a href="<?= e(url('d/'.$t['dest_slug'])) ?>"><?= e($t['dest_name']) ?></a> / <?php endif;?><?= e($t['title']) ?></p>
</div>
<div class="wrap prose">
  <h1><?= e($t['title']) ?></h1>
  <div class="meta-row"><img class="avatar" src="<?= e(avatar_url($t['author']['avatar_url']??null)) ?>" alt="">
    <span><a href="<?= e(url('u/'.$t['author']['username'])) ?>">@<?= e($t['author']['username']) ?></a> · <?= e(ago($t['created_at'])) ?>
    <?php if($t['visited_on']):?> · visited <?= e(date('M Y', strtotime((string)$t['visited_on']))) ?><?php endif;?></span>
    <?php if (show_verified($t)): ?><span class="verified">Verified visit</span><?php endif; ?>
    <?php /* Following the traveler is the action a good trip page earns, and there was no way to
             do it from here: a reader who liked this had to go and find the profile. */ ?>
    <?php if ($me && !$isOwner): ?>
      <form method="post" action="<?= e(url('follow')) ?>" style="margin-left:auto"><?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int) $t['user_id'] ?>">
        <input type="hidden" name="return" value="<?= e('/trip/'.(int) $t['id'].'/'.(string) $t['slug']) ?>">
        <button class="btn btn-ghost btn-sm"><?= $isFollowingAuthor ? 'Following' : 'Follow' ?></button>
      </form>
    <?php elseif (!$me): ?>
      <a class="btn btn-ghost btn-sm" style="margin-left:auto"
         href="<?= e(url('register?return=' . rawurlencode('/trip/'.(int) $t['id']))) ?>">Follow @<?= e($t['author']['username']) ?></a>
    <?php endif; ?>
  </div>
  <?php if ($t['cover_url']): ?><img class="article-hero" src="<?= e($t['cover_url']) ?>" alt="<?= e($t['title']) ?>"><?php endif; ?>
  <div><?= rmt_linkify_mentions(rmt_linkify_tags(nl2br(e($t['body'])))) ?></div>
  <?php if (!empty($tags)): ?>
    <div class="tag-row"><?php foreach ($tags as $tg): ?><a class="chip" href="<?= e(url('tag/'.$tg['name'])) ?>">#<?= e($tg['name']) ?></a><?php endforeach; ?></div>
  <?php endif; ?>
  <?php /* The album. The first photograph leads at double size and every cell opens the photo's
           own page, because the thing somebody clicked is the thing they want to see. */ ?>
  <?php if ($photos): ?>
    <?php $gridPhotos = array_map(static fn(array $ph) => [
            'url' => (string) $ph['url'], 'caption' => (string) ($ph['caption'] ?? ''),
            'kind' => 'trip', 'id' => (int) $ph['id']], $photos);
          $gridLead = count($photos) > 2;
          include __DIR__ . '/_photo_grid.php'; ?>
  <?php endif; ?>

  <?php /* A photograph from anybody planning the trip, straight from the page, because during a
           trip the person holding the picture is as often the one who was invited. Up to six on a
           trip, the same cap the form has. */ ?>
  <?php if ($canEdit ?? false): ?>
    <form id="photos" class="trip-photo-add" method="post" enctype="multipart/form-data"
          action="<?= e(url('trip/'.(int) $t['id'].'/photos')) ?>">
      <?= csrf_field() ?>
      <label class="btn btn-ghost btn-sm" style="cursor:pointer">Add a photo
        <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple
               style="display:none" onchange="this.form.submit()"></label>
      <?php if ($me && (int) $t['user_id'] === (int) $me['id']): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('trip/'.$t['id'].'/edit')) ?>">Edit</a>
      <?php endif; ?>
    </form>
  <?php endif; ?>
  <?php $shareUrl = url('trip/'.$t['id'].'/'.$t['slug']); $shareText = (string) $t['title'];
        include __DIR__ . '/_share.php'; ?>

  <?php /* When the trip is, in words. A page that says "2027-04-02" tells the reader a date; a page
           that says "coming up" tells them whether to bother saying hello. */ ?>
  <?php if (!empty($t['date_from'])): ?>
    <p class="muted" style="margin:.2rem 0 1rem">
      <?php $label = ['upcoming' => 'Coming up', 'current' => 'Happening now', 'past' => 'Trip taken'][$phase] ?? ''; ?>
      <?php if ($label): ?><b><?= e($label) ?></b> · <?php endif; ?>
      <?= e(date('j M Y', strtotime((string) $t['date_from']))) ?>
      <?php if (!empty($t['date_to']) && $t['date_to'] !== $t['date_from']): ?>
        to <?= e(date('j M Y', strtotime((string) $t['date_to']))) ?>
      <?php endif; ?>
      <?php if (!empty($t['dest_slug'])): ?>
        · <a href="<?= e(url('d/'.$t['dest_slug'].'/travelers')) ?>">who else is going to <?= e($t['dest_name']) ?></a>
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <?php /* A countdown and the city, together, because those are the two questions a reader has
           after "who wrote this": when is it, and where. The city card is also the way out of this
           page into the part of the site that has other people in it. */ ?>
  <?php if (!empty($t['dest_slug'])): ?>
    <?php
      $rmt_days = null;
      if (!empty($t['date_from']) && $phase === 'upcoming') {
          $rmt_days = (int) ceil((strtotime((string) $t['date_from']) - time()) / 86400);
      }
    ?>
    <div class="trip-city">
      <div class="trip-city-main">
        <?php if ($rmt_days !== null): ?>
          <span class="trip-count"><?= $rmt_days <= 0 ? 'Starts today' : ($rmt_days === 1 ? 'Tomorrow' : 'In ' . $rmt_days . ' days') ?></span>
        <?php elseif ($phase === 'current'): ?>
          <span class="trip-count on">Happening now</span>
        <?php endif; ?>
        <b><a href="<?= e(url('d/'.$t['dest_slug'])) ?>"><?= e((string) $t['dest_name']) ?></a></b>
        <span class="hint">
          <?php if ($destGoing > 0): ?>
            <?= $destGoing ?> <?= $destGoing === 1 ? 'traveler has' : 'travelers have' ?> upcoming dates here.
          <?php else: ?>
            Nobody else has posted dates for this city yet.
          <?php endif; ?>
        </span>
      </div>
      <div class="trip-city-acts">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('d/'.$t['dest_slug'].'/travelers')) ?>">Who is going</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('d/'.$t['dest_slug'])) ?>">About <?= e((string) $t['dest_name']) ?></a>
      </div>
    </div>
  <?php endif; ?>

  <?php /* The plan, high on the page. It is the thing a reader came for and the thing that makes
           this trip worth anybody else's attention, so it sits above the comments rather than
           under them. */ ?>
  <?php /* While the trip is on, what is on today goes above everything else. A page written for
           planning is the wrong page to open on the third morning in Lisbon. */ ?>
  <?php $planDays = $planDays ?? []; include __DIR__ . '/_trip_today.php'; ?>

  <?php include __DIR__ . '/_trip_members.php'; ?>

  <?php $planDays = $planDays ?? []; include __DIR__ . '/_trip_plan.php'; ?>

  <?php /* The same itinerary, seen from above. It sits under the plan rather than over it, because
           the answer to "what are we doing" is a list and the answer to "how far apart is it all"
           is a map, and only one of those is the question somebody opens this page with. */ ?>
  <?php $mapPoints = $tripMap ?? []; $mapId = 'trip-map'; $mapTitle = 'On a map';
        include __DIR__ . '/_map.php'; ?>

  <?php /* The one thing a reader of somebody else's upcoming trip actually wants to do. Before
           this the site would tell you a stranger's trip overlapped yours and then leave you to
           type the same dates into a different form. */ ?>
  <?php if (!$isOwner && in_array($phase, ['upcoming', 'current'], true) && !empty($t['destination_id'])): ?>
    <?php if ($me): ?>
      <form method="post" action="<?= e(url('trip/'.(int)$t['id'].'/going-too')) ?>" style="margin:0 0 18px">
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e('/trip/'.(int)$t['id'].'/'.$t['slug']) ?>">
        <button class="btn btn-primary">I am going too</button>
        <span class="hint">Posts the same city and dates as your own plan, and tells @<?= e($t['author']['username'] ?? '') ?>.</span>
      </form>
    <?php else: ?>
      <p style="margin:0 0 18px">
        <a class="btn btn-accent" href="<?= e(url('register?return=' . rawurlencode('/trip/'.(int)$t['id']))) ?>">Join to say you are going too</a>
      </p>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* Everybody else who will be there then. Faces, not a number: a count is a statistic and
           a row of people is a reason to say something. */ ?>
  <?php if (!empty($alsoThere)): ?>
    <section class="also-there">
      <h2 style="font-size:1.05rem;margin:0 0 10px">Also there then</h2>
      <div class="also-row">
        <?php foreach ($alsoThere as $o): ?>
          <a class="also-person" href="<?= e(url('u/'.$o['username'])) ?>">
            <img class="avatar" src="<?= e(avatar_url($o['avatar_url'] ?? null)) ?>" alt="">
            <span>
              <b>@<?= e($o['username']) ?></b>
              <span class="hint"><?= e(rmt_card_date_range((string) $o['date_from'], (string) $o['date_to'])) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if (!empty($t['dest_slug'])): ?>
        <p class="hint" style="margin:10px 0 0">
          <a href="<?= e(url('d/'.$t['dest_slug'].'/travelers')) ?>">Everybody going to <?= e((string) $t['dest_name']) ?></a>
        </p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php /* The updates. Oldest first, because a trip reads forwards: everywhere else on this site is
           a feed and puts the newest on top, but a trip is a sequence of days. */ ?>
  <?php if ($isOwner): ?>
    <form method="post" action="<?= e(url('post/new')) ?>" style="margin:0 0 18px"><?= csrf_field() ?>
      <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('post_new')) ?>">
      <input type="hidden" name="trip_id" value="<?= (int)$t['id'] ?>">
      <input type="hidden" name="return" value="<?= e('/trip/'.(int)$t['id'].'/'.$t['slug']) ?>">
      <textarea name="body" rows="2" maxlength="1000" style="width:100%"
                placeholder="<?= $phase === 'past' ? 'Add something you remember' : 'Post an update from this trip' ?>"></textarea>
      <button class="btn btn-primary btn-sm" style="margin-top:6px">Post update</button>
    </form>
  <?php endif; ?>

  <?php if (!empty($updates)): ?>
    <h2 style="font-size:1.15rem;margin:0 0 10px">Updates</h2>
    <ol class="list-plain" style="margin:0 0 22px">
      <?php foreach ($updates as $up): ?>
        <li class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px">
          <span class="hint"><?= e(date('j M, H:i', strtotime((string) $up['created_at']))) ?></span>
          <p style="margin:.25rem 0 0"><?= rmt_linkify_tags(rmt_linkify_mentions(nl2br(e($up['body'])))) ?></p>
          <?php if (!empty($up['image_url'])): ?>
            <p style="margin:.5rem 0 0"><img class="card-media" loading="lazy" style="border-radius:10px"
                 src="<?= e(abs_url($up['image_url'])) ?>" alt=""></p>
          <?php endif; ?>
          <p class="hint" style="margin:.35rem 0 0">
            <a href="<?= e(url('post/'.(int)$up['id'])) ?>"><?= (int)$up['reply_count'] === 1 ? '1 reply' : (int)$up['reply_count'] . ' replies' ?></a>
          </p>
        </div></li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <?php if (!empty($t['dest_slug'])): ?>
    <?php $destSlug = (string) $t['dest_slug']; $destName = (string) $t['dest_name'];
          include __DIR__ . '/_meet_travelers.php'; ?>
  <?php endif; ?>

  <?php /* What this traveler wrote about the city afterwards. The review is the thing the trip
           was for, and the two were never linked to each other. */ ?>
  <?php if ($authorSaid): ?>
    <section class="trip-extra">
      <h2>What @<?= e($t['author']['username']) ?> said about <?= e((string) $t['dest_name']) ?></h2>
      <?php foreach ($authorSaid as $rv): ?>
        <a class="rail-row" href="<?= e(url(ltrim(rmt_review_path($rv), '/'))) ?>">
          <b><?= e((string) ($rv['title'] ?: $rv['subject_name'])) ?></b>
          <span class="hint"><?php if (!empty($rv['rating'])): ?><?= str_repeat('&#9733;', max(0, min(5, (int) $rv['rating']))) ?> &middot; <?php endif; ?><?= e(mb_strimwidth(strip_tags((string) $rv['body']), 0, 120, '...')) ?></span>
        </a>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <?php /* Somewhere to go next that is not the back button. Public trips to the same city, with
           something actually written on them. */ ?>
  <?php if ($related): ?>
    <section class="trip-extra">
      <h2>More trips to <?= e((string) $t['dest_name']) ?></h2>
      <div class="grid g-3">
        <?php foreach ($related as $rt): ?>
          <article class="card"><a href="<?= e(url('trip/'.(int) $rt['id'].'/'.(string) $rt['slug'])) ?>">
            <?php if (!empty($rt['cover_url'])): ?>
              <img class="card-media" loading="lazy" src="<?= e(abs_url((string) $rt['cover_url'])) ?>" alt="">
            <?php endif; ?>
            <div class="card-body">
              <h3 style="font-size:1rem"><?= e((string) $rt['title']) ?></h3>
              <p class="hint" style="margin:.2rem 0 0">@<?= e((string) $rt['username']) ?><?php
                if (!empty($rt['date_from'])): ?> &middot; <?= e(rmt_card_date_range((string) $rt['date_from'], (string) $rt['date_to'])) ?><?php endif; ?></p>
            </div>
          </a></article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php
    $targetType = 'trip'; $targetId = (int)$t['id']; $ownerId = (int)$t['user_id'];
    $returnUrl = url('trip/'.$t['id'].'/'.$t['slug']);
    include __DIR__ . '/_engagement.php';
  ?>
</div>
