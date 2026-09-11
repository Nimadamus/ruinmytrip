<?php /** @var array $items @var array $me @var bool $isEveryone @var string $scope @var array $cities @var array $rails */
$rmt_kind_verbs = ['trip' => 'shared a trip', 'review' => 'reviewed', 'guide' => 'wrote a guide',
                   'blog_post' => 'posted', 'collection' => 'made the list', 'going' => 'is going to', 'post' => 'said', 'meetup' => 'is hosting', 'photo' => 'posted a photo from'];
/* An update posted from a trip is not somebody "saying" something into the void: it is a person
   in a city, mid-trip, and the feed row reads wrong without that. */
/* Nullable on purpose: every row that is not a trip update falls through to the ordinary verb.
   Typed as string, this returned null for the first plain post in the feed and killed the page
   halfway down with a TypeError. The rows above it had already been printed, so the response was
   still 200 and looked fine to anything that only checked a status code. */
$rmt_post_verb = static fn(array $it): ?string =>
    ($it['kind'] === 'post' && !empty($it['trip_id'])) ? 'posted an update from' : null;
$rails = $rails ?? ['matches' => [], 'trips' => [], 'suggested' => [], 'meetups' => [], 'match_count' => 0];
$rmt_day = static fn(?string $d): string => $d ? date('j M', strtotime($d)) : '';
$engagement = $engagement ?? ['likes' => [], 'comments' => [], 'mine' => []];
$threads = $threads ?? [];
?>
<div class="wrap feed-shell">

  <div class="feed-main">
    <?php /* The composer is the first thing in the column, because the difference between a feed
             and a network is whether the reader can answer it without going somewhere else. It
             posts to the same endpoint /talk uses. */ ?>
    <form class="composer card" method="post" action="<?= e(url('post/new')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('post_new')) ?>">
      <input type="hidden" name="return" value="/feed">
      <div class="composer-row">
        <img class="avatar" src="<?= e(avatar_url(rmt_profile_avatar((int) $me['id']))) ?>" alt="">
        <textarea name="body" rows="2" maxlength="1000"
                  placeholder="Where are you going, or what did you just find out?"></textarea>
      </div>
      <div class="composer-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('trip/new')) ?>">Post a trip</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('review/new')) ?>">Write a review</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('meetup/new')) ?>">Host a meetup</a>
        <button class="btn btn-primary btn-sm" style="margin-left:auto">Post</button>
      </div>
    </form>

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
