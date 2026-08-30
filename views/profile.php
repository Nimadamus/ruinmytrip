<?php /** @var array $u @var array $trips @var array $reviews @var array $guides @var array $collections @var int $followers @var int $following @var bool $is_following @var ?array $me @var array $stats @var array $badges @var bool $isMe @var array $compliments @var array $myCompliments @var bool $is_blocked @var bool $i_blocked_them @var array $wishlist @var array $hostedMeetups @var array $attendingMeetups */ ?>
<div class="wrap">
  <div class="profile-cover<?= $u['cover_url'] ? ' has-image' : '' ?>" style="<?= $u['cover_url']?'background-image:url(\''.e($u['cover_url']).'\')':'' ?>"></div>
  <div class="profile-head">
    <img class="avatar-lg" src="<?= e(avatar_url($u['avatar_url'])) ?>" alt="<?= e($u['username']) ?>">
    <div style="flex:1;min-width:220px">
      <h1 style="margin:0"><?= e($u['display_name'] ?: $u['username']) ?>
        <?php if (rmt_is_editorial($u)): ?><?= rmt_editorial_badge() ?>
        <?php elseif (in_array($u['role'],['admin','mod'],true)): ?><span class="chip" style="background:#eef;color:#334">Team</span>
        <?php elseif ($u['role']==='creator'): ?><span class="chip" style="background:#fef3c7;color:#92400e">Creator</span><?php endif; ?>
      </h1>
      <p class="muted" style="margin:.1rem 0">@<?= e($u['username']) ?> <?= $u['home_city']?' · '.e($u['home_city']):'' ?></p>
      <?php /* A reader arriving from "Written by RuinMyTrip Editorial" lands here, and what they
               need first is what kind of account this is -- not a traveler with an extraordinary
               number of trips. */ ?>
      <?php if (rmt_is_editorial($u)): ?>
        <p class="hint" style="margin:.2rem 0 0;max-width:60ch">
          This is the site's editorial account, not a traveler. Everything it publishes is
          researched from published and official sources rather than from a personal visit, is
          labelled wherever it appears, and is never counted in any community rating.
          <a href="<?= e(url('editorial-policy')) ?>">How we research</a>.
        </p>
      <?php endif; ?>
      <div class="stat-inline">
        <?php /* Every figure is a live COUNT (see rmt_profile_stats) — no stored counters. */ ?>
        <?php /* Only what this traveler actually has. A row reading "0 photos, 0 places visited,
                 0 votes, 0 followers, 0 following" is five statements of absence and reads as a
                 scoreboard nobody is winning -- on a new profile it is the first thing a person
                 sees about themselves. Reviews always shows, because it is the number the page is
                 fundamentally about and its empty state is handled below with an invitation
                 rather than a zero. */ ?>
        <?php /* "185 reviews" on the editorial account reads as a traveler who reviewed 185 places.
                 The same number, named for what it actually is, reads as what it is. */ ?>
        <?php $isEdProfile = rmt_is_editorial($u); ?>
        <span><b><?= (int)$stats['reviews'] ?></b>
          <?php if ($isEdProfile): ?>
            editorial <?= $stats['reviews'] === 1 ? 'review' : 'reviews' ?>
          <?php else: ?>
            traveler <?= $stats['reviews'] === 1 ? 'review' : 'reviews' ?>
          <?php endif; ?>
        </span>
        <?php if ((int) ($stats['posts'] ?? 0) > 0): ?>
          <span><b><?= (int)$stats['posts'] ?></b> <?= $stats['posts'] === 1 ? 'post' : 'posts' ?></span>
        <?php endif; ?>
        <?php if ((int) $stats['photos'] > 0): ?>
          <span><b><?= (int)$stats['photos'] ?></b> <?= $stats['photos'] === 1 ? 'photo' : 'photos' ?></span>
        <?php endif; ?>
        <?php if ((int) $stats['places'] > 0): ?>
          <?php /* "80 places visited" on the editorial account directly contradicted the editorial
                   policy, which says in as many words that we never claim to have gone. The count
                   is true; the verb was not. Covered, not visited. */ ?>
          <span><b><?= (int)$stats['places'] ?></b>
            <?php if ($isEdProfile): ?>
              <?= $stats['places'] === 1 ? 'place covered' : 'places covered' ?>
            <?php else: ?>
              <?= $stats['places'] === 1 ? 'place visited' : 'places visited' ?>
            <?php endif; ?>
          </span>
        <?php endif; ?>
        <?php if ((int) $stats['votes'] > 0): ?>
          <span title="Useful + funny + cool votes from other travelers"><b><?= (int)$stats['votes'] ?></b> <?= $stats['votes'] === 1 ? 'vote' : 'votes' ?></span>
        <?php endif; ?>
        <?php /* Shown only once somebody has one. "0 found helpful" on every new profile is noise
                 that makes the page look like a scoreboard nobody is winning. */ ?>
        <?php if ((int) $stats['helpful'] > 0): ?>
          <span title="Times other travelers marked this person's reviews useful"><b><?= (int)$stats['helpful'] ?></b> found helpful</span>
        <?php endif; ?>
        <?php /* Follow counts appear once there is one, and for the profile's owner regardless, so
                 they always have the way in to see who is following them. */ ?>
        <?php if ($followers > 0 || $isMe): ?>
          <a href="<?= e(url('u/'.$u['username'].'/followers')) ?>"><b><?= $followers ?></b> <?= $followers === 1 ? 'follower' : 'followers' ?></a>
        <?php endif; ?>
        <?php if ($following > 0 || $isMe): ?>
          <a href="<?= e(url('u/'.$u['username'].'/following')) ?>"><b><?= $following ?></b> following</a>
        <?php endif; ?>
      </div>
      <?php if ($badges): ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
          <?php foreach ($badges as $b): ?>
            <span class="chip" style="background:#0f766e;color:#fff" title="<?= e($b['description']) ?>">
              <?= e($b['icon']) ?> <?= e($b['name']) ?>
            </span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div>
      <?php if ($me && (int)$me['id']===(int)$u['id']): ?>
        <a class="btn btn-ghost" href="<?= e(url('u/'.$u['username'].'/edit')) ?>">Edit profile</a>
      <?php elseif ($me && $i_blocked_them): ?>
        <form class="inline-form" method="post" action="<?= e(url('unblock')) ?>">
          <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <input type="hidden" name="return" value="<?= e(url('u/'.$u['username'])) ?>">
          <button class="btn btn-ghost" style="color:#b42318">Unblock</button>
        </form>
      <?php elseif ($me && $is_blocked): ?>
        <?php /* They blocked me; nothing to offer here. */ ?>
      <?php elseif ($me): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
          <form class="inline-form" method="post" action="<?= e(url('follow')) ?>">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <input type="hidden" name="return" value="<?= e(url('u/'.$u['username'])) ?>">
            <button class="btn <?= $is_following?'btn-ghost':'btn-primary' ?>"><?= $is_following?'Following':'Follow' ?></button>
          </form>
          <a class="btn btn-ghost" href="<?= e(url('messages/'.$u['username'])) ?>">Message</a>
          <a class="btn btn-ghost" href="<?= e(url('report?target_type=user&target_id='.(int)$u['id'])) ?>">⚑ Report</a>
          <form class="inline-form" method="post" action="<?= e(url('block')) ?>" onsubmit="return confirm('Block @<?= e($u['username']) ?>? They will no longer be able to message or follow you.');">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <input type="hidden" name="return" value="<?= e(url('u/'.$u['username'])) ?>">
            <button class="btn btn-ghost" style="color:#b42318">Block</button>
          </form>
        </div>
      <?php else: ?>
        <a class="btn btn-primary" href="<?= e(url('login')) ?>">Follow</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($u['bio']): ?><p style="max-width:70ch;margin:18px 0"><?= e($u['bio']) ?></p><?php endif; ?>

  <?php if ($compliments || ($me && !$isMe)): ?>
    <div class="card" style="margin:18px 0"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 8px">Compliments</p>
      <?php if (!$compliments): ?><p class="muted" style="margin:0 0 10px">No compliments yet.</p><?php endif; ?>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach ($compliments as $c): ?>
          <span class="chip" title="<?= (int)$c['c'] ?>"><?= e(RMT_COMPLIMENT_TYPES[$c['type']] ?? $c['type']) ?> · <?= (int)$c['c'] ?></span>
        <?php endforeach; ?>
      </div>
      <?php if ($me && !$isMe && !$is_blocked): ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">
          <?php foreach (RMT_COMPLIMENT_TYPES as $slug=>$label): $sent = in_array($slug, $myCompliments, true); ?>
            <?php if ($sent): ?>
              <span class="chip" style="background:#0f766e;color:#fff">Sent: <?= e($label) ?></span>
            <?php else: ?>
              <form class="inline-form" method="post" action="<?= e(url('compliment')) ?>">
                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="type" value="<?= e($slug) ?>">
                <input type="hidden" name="return" value="<?= e(url('u/'.$u['username'])) ?>">
                <button class="btn btn-ghost btn-sm">+ <?= e($label) ?></button>
              </form>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div></div>
  <?php endif; ?>

  <?php if (!empty($beenPlaces)): ?>
    <div class="card" style="margin:18px 0"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 8px">Been</p>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach ($beenPlaces as $b): ?>
          <a class="chip" href="<?= e(url('d/'.$b['slug'])) ?>"><?= e($b['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <p class="hint" style="margin:8px 0 0">Self-asserted. Not a review and not a rating.</p>
    </div></div>
  <?php endif; ?>

  <?php if (!empty($plans)): ?>
    <div class="card" style="margin:18px 0"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 8px"><?= $isMe ? 'Your upcoming trips' : 'Upcoming trips' ?></p>
      <ul class="list-plain" style="margin:0">
        <?php foreach ($plans as $pl): ?>
          <li style="padding:6px 0;border-bottom:1px solid var(--line)">
            <a href="<?= e(url('d/'.$pl['dest_slug'])) ?>"><?= e($pl['dest_name']) ?></a>
            <span class="muted"> · <?= e(date('M j', strtotime((string)$pl['date_from']))) ?> – <?= e(date('M j, Y', strtotime((string)$pl['date_to']))) ?></span>
            <?php if ($isMe && $pl['visibility'] !== 'public'): ?>
              <span class="hint"> · <?= $pl['visibility'] === 'followers' ? 'followers' : 'only you' ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div></div>
  <?php endif; ?>

  <?php if ($wishlist): ?>
    <div class="card" style="margin:18px 0"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 8px">Want to visit</p>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach ($wishlist as $w): ?>
          <a class="chip" href="<?= e(url('d/'.$w['slug'])) ?>"><?= e($w['name']) ?>, <?= e($w['country']) ?></a>
        <?php endforeach; ?>
      </div>
    </div></div>
  <?php endif; ?>

  <?php if ($hostedMeetups): ?>
    <?php /* Public. The host is already named on the meetup page and on the index, and this is
             where somebody deciding whether to go and meet a stranger will look for it. */ ?>
    <h2 style="margin-top:30px">Hosting</h2>
    <div class="grid g-2">
      <?php foreach ($hostedMeetups as $hm): ?>
        <article class="card"><div class="card-body">
          <?php if ($hm['dest_name']): ?><span class="chip"><?= e($hm['dest_name']) ?></span><?php endif; ?>
          <h3 style="margin:.4rem 0 .2rem;font-size:1.05rem">
            <a href="<?= e(url('meetup/'.(int)$hm['id'])) ?>"><?= e($hm['title']) ?></a></h3>
          <p class="muted" style="margin:0"><?= e(date('l, M j, Y · g:ia', strtotime((string)$hm['date_start']))) ?></p>
          <div class="meta-row"><?= (int)$hm['going'] ?> going<?php if ((int)$hm['capacity'] > 0): ?> of <?= (int)$hm['capacity'] ?><?php endif; ?></div>
        </div></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($isMe && $attendingMeetups): ?>
    <?php /* Only ever rendered on your own profile. Each going-list is already public on its own
             meetup page, but a per-person list of everywhere somebody will physically be over the
             next month is a different thing, and this site does not build that for strangers. */ ?>
    <h2 style="margin-top:30px">Going to <span class="muted" style="font-weight:400;font-size:1rem">(only you can see this)</span></h2>
    <div class="grid g-2">
      <?php foreach ($attendingMeetups as $am): ?>
        <article class="card"><div class="card-body">
          <?php if ($am['dest_name']): ?><span class="chip"><?= e($am['dest_name']) ?></span><?php endif; ?>
          <h3 style="margin:.4rem 0 .2rem;font-size:1.05rem">
            <a href="<?= e(url('meetup/'.(int)$am['id'])) ?>"><?= e($am['title']) ?></a></h3>
          <p class="muted" style="margin:0"><?= e(date('l, M j, Y · g:ia', strtotime((string)$am['date_start']))) ?></p>
        </div></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>


  <?php /* Reviews first. This is a travel review site, a reviewer's reviews are the answer to
           "who is this traveler", and they were below trips, guides and collections. */ ?>
  <?php if ($reviews): ?><h2 style="margin-top:30px"><?= rmt_is_editorial($u) ? 'Editorial reviews' : 'Traveler reviews' ?> <span class="muted" style="font-weight:400;font-size:1rem">(<?= count($reviews) ?>)</span></h2>
  <?php foreach ($reviews as $r): ?><div class="card" style="margin-bottom:12px"><div class="card-body">
    <span class="stars"><?= stars((int)$r['rating']) ?></span>
    <?php /* Labelled on the card, not only in the page header. A reader scrolling a list of 185
             does not carry the header down the page with them. */ ?>
    <?php if (rmt_is_editorial($u)): ?><?= rmt_editorial_badge('review') ?><?php endif; ?>
    <b><a href="<?= e(url('review/'.(int)$r['id'].'/'.($r['slug'] ?: rmt_review_slug($r)))) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a></b>
    <p class="muted" style="margin:.2rem 0 0"><?= e($r['subject_name']) ?> · <span style="text-transform:capitalize"><?= e($r['subject_type']) ?></span><?php if ($r['visited_on']): ?> · visited <?= e(date('M Y', strtotime((string)$r['visited_on']))) ?><?php endif; ?></p>
    <p style="margin:.4rem 0 0"><?= e(mb_strimwidth((string)$r['body'], 0, 200, '…')) ?></p>
  </div></div><?php endforeach; ?><?php endif; ?>

  <?php if ($talkPosts): ?>
    <h2 style="margin-top:30px">Talk <span class="muted" style="font-weight:400;font-size:1rem">(<?= count($talkPosts) ?>)</span></h2>
    <?php foreach ($talkPosts as $tp): ?>
      <div class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px">
        <span class="hint"><?= e(ago((string) $tp['created_at'])) ?>
          <?php if (!empty($tp['dest_name'])): ?> · <a href="<?= e(url('d/'.$tp['dest_slug'])) ?>"><?= e((string) $tp['dest_name']) ?></a><?php endif; ?>
          <?php if (!empty($tp['community_slug'])): ?> · <a href="<?= e(url('c/'.$tp['community_slug'])) ?>"><?= e((string) $tp['community_title']) ?></a><?php endif; ?>
        </span>
        <p style="margin:.35rem 0 .3rem;white-space:pre-wrap"><?= nl2br(e(mb_strimwidth((string) $tp['body'], 0, 300, '…'))) ?></p>
        <p class="hint" style="margin:0"><a href="<?= e(url('post/'.(int) $tp['id'])) ?>">
          <?php $rn = (int) ($tp['reply_count'] ?? 0); ?>
          <?= $rn ? $rn . ' ' . ($rn === 1 ? 'reply' : 'replies') : 'Open' ?></a></p>
      </div></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php /* Trips only when there are trips. An empty "Trips" heading over "No trips shared yet."
           was the FIRST section on a profile carrying 185 reviews: the page led with the one thing
           this traveler had not done. */ ?>
  <?php if ($trips): ?>
  <h2 style="margin-top:24px">Trips</h2>
  <div class="grid g-3">
    <?php foreach ($trips as $t): ?>
      <article class="card"><a href="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e(abs_url($t['cover_url'])) ?>" alt="<?= e($t['title']) ?>">
        <div class="card-body"><?php if($t['dest_name']):?><span class="chip"><?= e($t['dest_name']) ?></span><?php endif;?><h3 style="font-size:1.05rem"><?= e($t['title']) ?></h3></div></a></article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($guides): ?><h2 style="margin-top:30px">Guides</h2>
  <div class="grid g-3"><?php foreach ($guides as $g): ?>
    <article class="card"><a href="<?= e(url('g/'.$g['slug'])) ?>"><img class="card-media" loading="lazy" src="<?= e(abs_url($g['cover_url'])) ?>" alt=""><div class="card-body"><h3 style="font-size:1.05rem"><?= e($g['title']) ?></h3></div></a></article>
  <?php endforeach; ?></div><?php endif; ?>

  <?php if ($collections): ?><h2 style="margin-top:30px">Collections</h2>
  <div class="grid g-3"><?php foreach ($collections as $c): ?>
    <article class="card"><a href="<?= e(url('c/'.$c['slug'])) ?>"><div class="card-body">
      <h3 style="font-size:1.05rem"><?= e($c['title']) ?></h3>
      <p class="muted" style="margin:.3rem 0 0"><?= e(rmt_collection_summary((int) ($c['dest_count'] ?? 0), (int) ($c['place_count'] ?? 0))) ?></p>
    </div></a></article>
  <?php endforeach; ?></div><?php endif; ?>

  <?php /* An empty profile is the most common one on a site with no reviews yet, and "your profile
           is empty" is a statement of failure rather than an invitation. This says what the page
           becomes, and sends them to the page built for having a trip in mind rather than a URL. */ ?>
  <?php if ($isMe && !$reviews && !$trips): ?>
    <div class="empty-cta" style="margin-top:24px">
      <h3 style="margin:0 0 4px">Nothing here yet.</h3>
      <p class="muted" style="margin:0">
        This is where your travel record lives: the places you went, what they actually cost, and
        what you would tell a friend. Start with somewhere you went recently, or keep a list of
        where you are going next.
      </p>
      <?php /* Two ways in, not one, and not a wall of them. A review is the thing we most want and
               is also the bigger ask -- it needs somewhere you have actually been. A list needs
               only somewhere you would like to go, which is a real thing to do on a travel site on
               a day you have nothing to review. Both, once, and then the page stops asking. */ ?>
      <p style="margin:14px 0 0;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn-accent" data-review-cta="profile"
           href="<?= e(url('contribute')) ?>">Review a place you went to</a>
        <a class="btn btn-ghost" href="<?= e(url('collection/new')) ?>">Start a travel list</a>
      </p>
    </div>
  <?php elseif (!$isMe && !$reviews && !$trips): ?>
    <p class="muted" style="margin-top:24px">@<?= e($u['username']) ?> has not published anything yet.</p>
  <?php endif; ?>
  <div style="height:40px"></div>
</div>
