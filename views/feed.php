<?php /** @var array $items @var array $me @var bool $isEveryone @var string $scope @var array $cities @var array $rails */
$rmt_kind_verbs = ['trip' => 'shared a trip', 'review' => 'reviewed', 'guide' => 'wrote a guide',
                   'blog_post' => 'posted', 'collection' => 'made the list', 'going' => 'is going to', 'post' => 'said', 'meetup' => 'is hosting', 'photo' => 'posted a photo from', 'activity' => 'is planning, in'];
/* An update posted from a trip is not somebody "saying" something into the void: it is a person
   in a city, mid-trip, and the feed row reads wrong without that. */
/* Nullable on purpose: every row that is not a trip update falls through to the ordinary verb.
   Typed as string, this returned null for the first plain post in the feed and killed the page
   halfway down with a TypeError. The rows above it had already been printed, so the response was
   still 200 and looked fine to anything that only checked a status code. */
$rmt_post_verb = static fn(array $it): ?string =>
    ($it['kind'] === 'post' && !empty($it['trip_id'])) ? 'posted an update from' : null;
$rails = $rails ?? ['invites' => [], 'review' => [], 'joinable' => [], 'matches' => [], 'trips' => [], 'suggested' => [],
                    'meetups' => [], 'match_count' => 0];
$rmt_day = static fn(?string $d): string => $d ? date('j M', strtotime($d)) : '';
$engagement = $engagement ?? ['likes' => [], 'comments' => [], 'mine' => []];
$threads = $threads ?? [];
?>
<div class="wrap feed-shell">

  <?php /* The trip this member is actually on, or about to be on. It is the thing the page is
           about; everything below it is context for it. No trip, no card: an empty one would be
           the site asking somebody to feel bad about not travelling. */ ?>
  <?php $nt = $rails['next_trip'] ?? null; ?>
  <?php if ($nt): ?>
    <?php
      $ntPhase = rmt_trip_phase($nt);
      $ntDays  = $ntPhase === 'upcoming'
          ? (int) ceil((strtotime((string) $nt['date_from']) - time()) / 86400) : null;
      $ntToday = $rails['next_trip_today'] ?? [];
    ?>
    <section class="next-trip feed-nudge">
      <div class="next-trip-head">
        <span class="next-trip-when">
          <?php if ($ntPhase === 'current'): ?>You are in <?= e((string) ($nt['dest_name'] ?: 'this city')) ?>
          <?php elseif ($ntDays !== null && $ntDays <= 0): ?>Leaving today
          <?php elseif ($ntDays === 1): ?>Tomorrow
          <?php else: ?>In <?= (int) $ntDays ?> days<?php endif; ?>
        </span>
        <a class="next-trip-title" href="<?= e(url('trip/'.(int) $nt['id'].'/'.(string) $nt['slug'])) ?>">
          <?= e((string) $nt['title']) ?></a>
        <span class="hint"><?php if (!empty($nt['dest_name']) && $ntPhase !== 'current'): ?><?= e((string) $nt['dest_name']) ?> &middot; <?php endif; ?><?= e(rmt_card_date_range((string) $nt['date_from'], (string) $nt['date_to'])) ?></span>
      </div>

      <?php if ($ntToday): ?>
        <ul class="next-trip-today">
          <?php foreach (array_slice($ntToday, 0, 3) as $nta): ?>
            <li>
              <span class="today-time<?= empty($nta['start_time']) ? ' today-time-any' : '' ?>">
                <?= !empty($nta['start_time']) ? e((string) $nta['start_time']) : 'any time' ?></span>
              <a href="<?= e(url('activity/'.(int) $nta['id'])) ?>"><?= e((string) $nta['title']) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php /* One useful sentence about THIS trip, rather than the same two buttons whatever
               state it is in. A traveller coming back tomorrow wants to know what to do next, and
               the answer depends on what the trip is short of: nothing planned, plans but nowhere
               to go, or a trip that is genuinely ready. Counted from real rows; a trip that is
               ready is told so rather than nagged. Nothing is suggested about photographs before
               the trip has happened. */ ?>
      <?php
        $rmt_plans = (int) ($rails['next_trip_plans'] ?? 0);
        $rmt_saved = (int) ($rails['next_trip_saved'] ?? 0);
        $rmt_city  = (string) ($nt['dest_name'] ?? '');
        $rmt_next  = null;
        if ($ntPhase !== 'past') {
            if ($rmt_plans === 0 && $rmt_saved === 0 && $rmt_city !== '') {
                $rmt_next = ['Nothing planned yet. Places in ' . $rmt_city . ' are a good place to start.',
                             'Find places in ' . $rmt_city, url('d/'.$nt['dest_slug'].'/places')];
            } elseif ($rmt_plans === 0) {
                $rmt_next = ['You have saved places but nothing is on a day yet.',
                             'Add your first plan', url('trip/'.(int) $nt['id'].'/'.(string) $nt['slug'].'#plan')];
            } elseif ($rmt_saved === 0 && $rmt_city !== '') {
                $rmt_next = [$rmt_plans === 1 ? 'One thing planned so far.' : $rmt_plans . ' things planned so far.',
                             'Find places in ' . $rmt_city, url('d/'.$nt['dest_slug'].'/places')];
            }
        }
      ?>
      <?php if ($rmt_next && !$ntToday): ?>
        <p class="next-trip-next"><?= e($rmt_next[0]) ?>
          <a href="<?= e($rmt_next[2]) ?>"><?= e($rmt_next[1]) ?></a></p>
      <?php endif; ?>

      <p class="next-trip-acts">
        <a class="btn btn-primary btn-sm" href="<?= e(url('trip/'.(int) $nt['id'].'/'.(string) $nt['slug'])) ?>">Open the trip</a>
        <?php if (!empty($nt['dest_slug'])): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('d/'.$nt['dest_slug'].'/travelers')) ?>">
            <?php if ((int) ($rails['next_trip_overlap'] ?? 0) > 0): ?>
              <?= (int) $rails['next_trip_overlap'] ?> on your dates
            <?php else: ?>Who else is going<?php endif; ?></a>
        <?php endif; ?>
      </p>
    </section>
  <?php endif; ?>

  <?php /* Somebody is waiting on an answer about a trip they are planning right now. Above even
           the "how was it" card, because it is a person waiting rather than a question. */ ?>
  <?php if (!empty($rails['invites'])): ?>
    <section class="rail-card feed-nudge">
      <h2 class="rail-h">You were asked to help plan</h2>
      <?php foreach ($rails['invites'] as $inv): ?>
        <div class="rail-row rail-review">
          <b><?= e((string) $inv['title']) ?></b>
          <span class="hint">@<?= e((string) $inv['owner_username']) ?><?php
            if (!empty($inv['dest_name'])): ?> &middot; <?= e((string) $inv['dest_name']) ?><?php endif; ?><?php
            if (!empty($inv['date_from'])): ?> &middot; <?= e(rmt_card_date_range((string) $inv['date_from'], (string) $inv['date_to'])) ?><?php endif; ?></span>
          <form method="post" action="<?= e(url('trip/'.(int) $inv['trip_id'].'/invite/answer')) ?>" class="review-yn">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-sm" name="answer" value="yes">Join the trip</button>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('trip/'.(int) $inv['trip_id'].'/'.(string) $inv['slug'])) ?>">Look first</a>
          </form>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <?php /* The day has passed and nobody has said how it went. One tap, and the answer is a real
           recommendation with a real person's name on it, which is the only kind this site has.
           Above the feed rather than beside it, because it expires: ask a fortnight later and it
           is homework. */ ?>
  <?php if (!empty($rails['review'])): ?>
    <section class="rail-card feed-nudge">
      <h2 class="rail-h">How was it?</h2>
      <?php foreach ($rails['review'] as $rv): ?>
        <div class="rail-row rail-review">
          <b><?= e((string) $rv['title']) ?></b>
          <span class="hint"><?= e(date('D j M', strtotime((string) $rv['day']))) ?><?php
            if (!empty($rv['dest_name'])): ?> &middot; <?= e((string) $rv['dest_name']) ?><?php endif; ?><?php
            /* Whose plan it was, when it was not yours. "Worth it" about a stranger's dinner you
               turned up to is a different sentence from "worth it" about your own. */
            if (empty($rv['mine']) && !empty($rv['host_username'])): ?> &middot; @<?= e((string) $rv['host_username']) ?><?php endif; ?></span>
          <form method="post" action="<?= e(url('activity/'.(int) $rv['id'].'/done')) ?>" class="review-yn">
            <?= csrf_field() ?>
            <input type="hidden" name="done" value="1">
            <input type="hidden" name="return" value="/feed">
            <button class="btn btn-primary btn-sm" name="recommend" value="1">Worth it</button>
            <button class="btn btn-sm btn-ghost" name="recommend" value="0">Skip it</button>
          </form>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <?php /* Somebody with no trip yet.
           Their first screen led with a box asking what they had just found out, which is a
           question you can only answer if you are already travelling. The thing this product does
           starts one step earlier: say where you are going, and the dates, and everything else on
           the site keys off that. So they are asked that instead, once, and the card disappears
           the moment there is a trip to show above it. */ ?>
  <?php if (!$nt): ?>
    <section class="next-trip feed-nudge">
      <div class="next-trip-head">
        <p class="eyebrow" style="margin:0">Start here</p>
        <h2 style="margin:.2rem 0 .4rem">Where are you going?</h2>
      </div>
      <p class="muted" style="margin:0 0 12px">Post your dates and this site starts working: who
        else is there the same week, what they are planning, and the places worth your time.</p>
      <p style="margin:0;display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-accent" href="<?= e(url('trip/new')) ?>">Add your trip</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('explore')) ?>">Not sure yet, browse cities</a>
      </p>
    </section>
  <?php endif; ?>

  <?php /* The composer, above both columns. The difference between a feed and a network is whether
           the reader can answer it without going somewhere else, and on a phone that means it has
           to be reachable before the scrolling starts. It posts to the same endpoint /talk uses. */ ?>
  <div class="feed-nudge composer-wrap">
    <form class="composer card" method="post" enctype="multipart/form-data" action="<?= e(url('post/new')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('post_new')) ?>">
      <input type="hidden" name="return" value="/feed">
      <div class="composer-row">
        <img class="avatar" src="<?= e(avatar_url(rmt_profile_avatar((int) $me['id']))) ?>" alt="">
        <textarea name="body" rows="2" maxlength="1000"
                  <?php /* Not the same question twice. Somebody with no trip has the card above
                           asking where they are going; asking it again ten pixels below reads as
                           a template repeating itself rather than as an invitation. */ ?>
                  placeholder="<?= $nt ? 'Where are you going, or what did you just find out?' : 'Ask the travelers here anything about a city' ?>"></textarea>
      </div>
      <div class="composer-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('trip/new')) ?>">Post a trip</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('review/new')) ?>">Write a review</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('meetup/new')) ?>">Host a meetup</a>
        <?php /* A travel network that makes you go to another page to post a photograph is a
                 message board. The endpoint already accepts one; the feed just never offered it. */ ?>
        <label class="btn btn-ghost btn-sm" style="cursor:pointer">Photo
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                 style="display:none" id="feed-photo"></label>
        <span class="hint" id="feed-photo-name" hidden></span>
        <button class="btn btn-primary btn-sm" style="margin-left:auto">Post</button>
      </div>
    </form>
    <script>
      /* Nothing clever: on a phone the file picker closes and there is otherwise no sign at all
         that a photograph is attached, so people attach the same one twice. */
      (function () {
        var f = document.getElementById('feed-photo'), n = document.getElementById('feed-photo-name');
        if (!f || !n) return;
        f.addEventListener('change', function () {
          var name = f.files && f.files[0] ? f.files[0].name : '';
          n.textContent = name ? 'Attached: ' + name : '';
          n.hidden = !name;
        });
      })();
    </script>
  </div>

  <div class="feed-main">
    <div class="feed-scopes">
      <a class="feed-scope<?= ($scope ?? 'following') === 'following' ? ' on' : '' ?>"
         href="<?= e(url('feed')) ?>">Following</a>
      <a class="feed-scope<?= ($scope ?? '') === 'everyone' ? ' on' : '' ?>"
         href="<?= e(url('feed?scope=everyone')) ?>">Everyone</a>
    </div>

    <?php if (!empty($isEveryone) && ($scope ?? '') !== 'everyone'): ?>
      <?php /* Say whose activity this is. A feed that quietly shows strangers as if they were
               people you chose to follow is a small lie that gets found out the moment somebody
               checks. */ ?>
      <p class="hint" style="margin:0 0 14px">You are not following anybody yet and nothing has happened
        in the cities you saved, so this is everyone on RuinMyTrip.
        <a href="<?= e(url('travelers')) ?>">Find people to follow</a>.</p>
    <?php endif; ?>

    <?php if (!empty($cities) && ($scope ?? 'following') === 'following'): ?>
      <p class="hint" style="margin:0 0 14px">Cities in your feed:
        <?php foreach ($cities as $i => $c): ?><?= $i ? ' · ' : ' ' ?><a href="<?= e(url('d/'.$c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
      </p>
    <?php endif; ?>

    <?php if (!$items): ?>
      <?php /* An empty feed on a young site is the normal state, not a failure, and the honest
               version of it points at the three things that actually fill it: following somebody,
               going somewhere, or writing the first thing yourself. */ ?>
      <div class="callout">
        Nothing here yet. This fills up as the travelers you follow post, and as anything
        happens in a city you saved.
        <a href="<?= e(url('travelers')) ?>">Find travelers</a>,
        <a href="<?= e(url('explore')) ?>">save the cities you care about</a>, or
        <a data-review-cta="feed" href="<?= e(url('contribute')) ?>">review a place you went to</a>.
      </div>
    <?php endif; ?>

    <?php foreach ($items as $it): ?>
      <article class="card feed-item">
        <?php if (!empty($it['cover_url'])): ?>
          <a href="<?= e($it['feed_url']) ?>"><img class="card-media" loading="lazy" src="<?= e(abs_url($it['cover_url'])) ?>" alt="<?= e($it['title']) ?>"></a>
        <?php endif; ?>
        <div class="card-body">
          <div class="meta-row" style="margin:0 0 8px">
            <img class="avatar" src="<?= e(avatar_url($it['author']['avatar_url']??null)) ?>" alt="">
            <span>
              <?php /* An activity feed has to say who did what to which thing. It used to lead with
                       a kind chip and then the author, so a review entry read "Review, @somebody,
                       49m ago, Prague" and never named the place that was reviewed, which is the
                       one fact the entry exists to carry. The verb does the work the chip did. */ ?>
              <a href="<?= e(url('u/'.($it['author']['username']??''))) ?>">@<?= e($it['author']['username']??'') ?></a>
              <?= e($rmt_post_verb($it) ?? ($rmt_kind_verbs[$it['kind']] ?? 'posted')) ?><?php if (!empty($it['subject'])): ?>
                <?php if (!empty($it['subject_url'])): ?><a href="<?= e($it['subject_url']) ?>"><b><?= e((string) $it['subject']) ?></b></a><?php
                      else: ?><b><?= e((string) $it['subject']) ?></b><?php endif; ?><?php endif; ?>
              <span class="hint">· <?= e(ago($it['created_at'])) ?><?= !empty($it['dest_name'])?' · '.e($it['dest_name']):'' ?></span>
            </span>
          </div>
          <?php if (!empty($it['feed_reason'])): ?>
            <?php /* Why this row is where it is. A ranked feed that cannot explain itself is
                     indistinguishable from a broken one. */ ?>
            <p class="feed-why"><?= e((string) $it['feed_reason']) ?></p>
          <?php endif; ?>
          <h3><a href="<?= e($it['feed_url']) ?>"><?= e($it['title']) ?></a></h3>
          <p><?= e($it['feed_excerpt']) ?></p>
          <?php
            /* The row that turns reading into taking part. A feed entry whose only affordance is
               its own link is an index entry; one you can press without leaving is a post. Counts
               come from one batched lookup for the whole page, never a query per row, and they are
               drawn only when they are real, because "0 likes" is an advert for an empty room. */
            $rmt_t = $it['kind'] === 'going' ? 'trip' : (string) $it['kind'];
            $rmt_k = $rmt_t . ':' . (int) ($it['id'] ?? 0);
            $rmt_likes = (int) ($engagement['likes'][$rmt_k] ?? 0);
            $rmt_comments = (int) ($engagement['comments'][$rmt_k] ?? 0);
            $rmt_liked = !empty($engagement['mine'][$rmt_k]);
            $rmt_can = isset(RMT_INTERACT_TARGETS[$rmt_t]) && (int) ($it['id'] ?? 0) > 0;
          ?>
          <?php if ($rmt_can): ?>
            <div class="act-row">
              <form method="post" action="<?= e(url('react')) ?>"><?= csrf_field() ?>
                <input type="hidden" name="kind" value="like">
                <input type="hidden" name="target_type" value="<?= e($rmt_t) ?>">
                <input type="hidden" name="target_id" value="<?= (int) $it['id'] ?>">
                <input type="hidden" name="return" value="<?= e($scope === 'everyone' ? '/feed?scope=everyone' : '/feed') ?>">
                <button class="act<?= $rmt_liked ? ' on' : '' ?>" title="<?= $rmt_liked ? 'Liked' : 'Like' ?>"
                        aria-label="<?= $rmt_liked ? 'Remove like' : 'Like this' ?>">
                  <span aria-hidden="true"><?= $rmt_liked ? '&#9829;' : '&#9825;' ?></span><?php
                  if ($rmt_likes): ?> <?= $rmt_likes ?><?php endif; ?>
                </button>
              </form>
              <a class="act" href="<?= e($it['feed_url']) ?>#comments">
                <span aria-hidden="true">&#128172;</span><?php if ($rmt_comments): ?> <?= $rmt_comments ?><?php endif; ?>
                <span class="sr-only">comments</span>
              </a>
              <form method="post" action="<?= e(url('react')) ?>"><?= csrf_field() ?>
                <input type="hidden" name="kind" value="save">
                <input type="hidden" name="target_type" value="<?= e($rmt_t) ?>">
                <input type="hidden" name="target_id" value="<?= (int) $it['id'] ?>">
                <input type="hidden" name="return" value="<?= e($scope === 'everyone' ? '/feed?scope=everyone' : '/feed') ?>">
                <button class="act" title="Save" aria-label="Save this"><span aria-hidden="true">&#9733;</span></button>
              </form>
            </div>

            <?php /* The thread, in place. A like says somebody was here and nothing about what
                     they thought, and a feed that hides its replies behind a click has no
                     conversation on it, because nobody opens a thread whose first line they
                     cannot see. Two lines and a box: enough to read the room and answer it. */ ?>
            <?php $rmt_thread = $threads[$rmt_k] ?? []; ?>
            <?php if ($rmt_thread): ?>
              <div class="thread">
                <?php foreach ($rmt_thread as $c): ?>
                  <div class="thread-line">
                    <img class="avatar" style="width:24px;height:24px" src="<?= e(avatar_url($c['avatar_url'] ?? null)) ?>" alt="">
                    <span><a href="<?= e(url('u/'.$c['username'])) ?>"><b>@<?= e($c['username']) ?></b></a>
                      <?= e(mb_strimwidth((string) $c['body'], 0, 180, '...')) ?></span>
                  </div>
                <?php endforeach; ?>
                <?php if ($rmt_comments > count($rmt_thread)): ?>
                  <a class="thread-more" href="<?= e($it['feed_url']) ?>#comments">Read all <?= $rmt_comments ?> replies</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <form class="thread-reply" method="post" action="<?= e(url('comment')) ?>"><?= csrf_field() ?>
              <input type="hidden" name="target_type" value="<?= e($rmt_t) ?>">
              <input type="hidden" name="target_id" value="<?= (int) $it['id'] ?>">
              <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('comment_'.$rmt_t.'_'.(int) $it['id'])) ?>">
              <input type="hidden" name="return" value="<?= e($scope === 'everyone' ? '/feed?scope=everyone' : '/feed') ?>">
              <img class="avatar" style="width:24px;height:24px" src="<?= e(avatar_url(rmt_profile_avatar((int) $me['id']))) ?>" alt="">
              <input type="text" name="body" maxlength="2000" placeholder="Reply to @<?= e($it['author']['username'] ?? '') ?>">
              <button class="btn btn-ghost btn-sm">Reply</button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <aside class="feed-rail">
    <?php /* Things the member could actually walk into while they are there. This is the end of
             the sentence the product is built toward, so it is the first thing in the rail. */ ?>
    <?php $railJoin = !empty($rails['joinable']) ? $rails['joinable'] : ($rails['joinable_anywhere'] ?? []);
          $railJoinMine = !empty($rails['joinable']); ?>
    <?php if ($railJoin): ?>
      <section class="rail-card">
        <h2 class="rail-h"><?= $railJoinMine ? 'You could join these' : 'Open to anyone, anywhere' ?></h2>
        <?php if (!$railJoinMine): ?>
          <p class="hint" style="margin:-4px 0 8px">Post where you are going and this becomes plans on your own dates.</p>
        <?php endif; ?>
        <?php foreach ($railJoin as $j): ?>
          <a class="rail-row" href="<?= e(url('activity/'.(int) $j['id'])) ?>">
            <b><?= e((string) $j['title']) ?></b>
            <span class="hint">@<?= e((string) $j['username']) ?><?php
              if (!$railJoinMine && !empty($j['dest_name'])): ?> &middot; <?= e((string) $j['dest_name']) ?><?php endif; ?><?php
              if (!empty($j['day'])): ?> &middot; <?= e(date('D j M', strtotime((string) $j['day']))) ?><?php endif; ?><?php
              if (!empty($j['start_time'])): ?> &middot; <?= e((string) $j['start_time']) ?><?php endif; ?>
              &middot; <?= $j['join_mode'] === 'open' ? 'anyone can join' : 'ask to join' ?></span>
          </a>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ($rails['matches']): ?>
      <?php /* The most valuable box on the site: people whose dates land on top of yours. */ ?>
      <section class="rail-card">
        <h2 class="rail-h">On your dates</h2>
        <?php foreach ($rails['matches'] as $m): ?>
          <a class="rail-person" href="<?= e(url('u/'.$m['username'])) ?>">
            <img class="avatar" src="<?= e(avatar_url($m['avatar_url'] ?? null)) ?>" alt="">
            <span>
              <b>@<?= e($m['username']) ?></b>
              <span class="hint"><?= e((string) $m['dest_name']) ?> ·
                <?= (int) $m['overlap_days'] ?> <?= (int) $m['overlap_days'] === 1 ? 'day' : 'days' ?> with you</span>
            </span>
          </a>
        <?php endforeach; ?>
        <a class="rail-more" href="<?= e(url('matches')) ?>">All <?= (int) $rails['match_count'] ?> matches</a>
      </section>
    <?php endif; ?>

    <section class="rail-card">
      <h2 class="rail-h">Your trips</h2>
      <?php if ($rails['trips']): ?>
        <?php foreach ($rails['trips'] as $t): ?>
          <a class="rail-row" href="<?= e(url('trip/'.(int) $t['id'])) ?>">
            <b><?= e((string) ($t['dest_name'] ?: $t['title'])) ?></b>
            <span class="hint"><?= e($rmt_day($t['date_from'])) ?> to <?= e($rmt_day($t['date_to'])) ?><?php
              if (($t['visibility'] ?? 'public') !== 'public'): ?> · <?= e((string) $t['visibility']) ?><?php endif; ?></span>
          </a>
        <?php endforeach; ?>
        <a class="rail-more" href="<?= e(url('trip/new')) ?>">Post another</a>
      <?php else: ?>
        <p class="hint" style="margin:0 0 10px">Post where you are going and the people going at the
          same time can find you. Destination and dates only.</p>
        <a class="btn btn-primary btn-sm" href="<?= e(url('trip/new')) ?>">Post a trip</a>
      <?php endif; ?>
    </section>

    <?php if ($rails['suggested']): ?>
      <section class="rail-card">
        <h2 class="rail-h">Travelers to follow</h2>
        <?php foreach ($rails['suggested'] as $u): ?>
          <div class="rail-person">
            <a href="<?= e(url('u/'.$u['username'])) ?>">
              <img class="avatar" src="<?= e(avatar_url($u['avatar_url'] ?? null)) ?>" alt=""></a>
            <span>
              <a href="<?= e(url('u/'.$u['username'])) ?>"><b>@<?= e($u['username']) ?></b></a>
              <?php if (!empty($u['reason'])): ?><span class="hint"><?= e((string) $u['reason']) ?></span><?php endif; ?>
            </span>
            <form method="post" action="<?= e(url('follow')) ?>" style="margin-left:auto"><?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="return" value="/feed">
              <button class="btn btn-ghost btn-sm">Follow</button>
            </form>
          </div>
        <?php endforeach; ?>
        <a class="rail-more" href="<?= e(url('travelers')) ?>">Browse travelers</a>
      </section>
    <?php endif; ?>

    <?php if ($rails['meetups']): ?>
      <section class="rail-card">
        <h2 class="rail-h"><?= empty($rails['meetups'][0]['elsewhere']) ? 'Meetups in your cities' : 'Meetups coming up' ?></h2>
        <?php foreach ($rails['meetups'] as $m): ?>
          <a class="rail-row" href="<?= e(url('meetup/'.(int) $m['id'])) ?>">
            <b><?= e((string) $m['title']) ?></b>
            <span class="hint"><?= e((string) $m['dest_name']) ?> · <?= e(date('D j M', strtotime((string) $m['date_start']))) ?></span>
          </a>
        <?php endforeach; ?>
        <a class="rail-more" href="<?= e(url('meetups')) ?>">All meetups</a>
      </section>
    <?php endif; ?>
  </aside>
</div>
