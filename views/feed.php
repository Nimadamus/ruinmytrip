<?php /** @var array $items @var array $me @var bool $isEveryone @var string $scope @var array $cities */
$rmt_kind_verbs = ['trip' => 'shared a trip', 'review' => 'reviewed', 'guide' => 'wrote a guide',
                   'blog_post' => 'posted', 'collection' => 'made the list', 'going' => 'is going to', 'post' => 'said', 'meetup' => 'is hosting'];
/* An update posted from a trip is not somebody "saying" something into the void: it is a person
   in a city, mid-trip, and the feed row reads wrong without that. */
/* Nullable on purpose: every row that is not a trip update falls through to the ordinary verb.
   Typed as string, this returned null for the first plain post in the feed and killed the page
   halfway down with a TypeError -- the rows above it had already been printed, so the response was
   still 200 and looked fine to anything that only checked a status code. */
$rmt_post_verb = static fn(array $it): ?string =>
    ($it['kind'] === 'post' && !empty($it['trip_id'])) ? 'posted an update from' : null;
$rmt_kind_labels = ['trip' => 'Trip', 'review' => 'Review', 'guide' => 'Guide', 'blog_post' => 'Blog', 'collection' => 'Collection', 'going' => "Who's going", 'post' => 'Talk', 'meetup' => 'Meetup'];
?>
<div class="wrap" style="max-width:760px">
  <h1 style="margin-top:24px">Your feed</h1>
  <p class="muted">Trips, reviews, guides, talk and meetups from the travelers you follow and the cities you saved.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 20px">
    <a class="btn btn-primary btn-sm" href="<?= e(url('trip/new')) ?>">Share a trip</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('review/new')) ?>">Write a review</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('guide/new')) ?>">Write a guide</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('blog/new')) ?>">Write a blog post</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('going')) ?>">Share dates</a>
  </div>
  <?php if (!$items): ?>
    <?php /* An empty feed on a young site is the normal state, not a failure, and the honest
             version of it points at the three things that actually fill it: following somebody,
             going somewhere, or writing the first thing yourself. */ ?>
    <div class="callout">
      Nothing here yet &mdash; this fills up as the travelers you follow post, and as anything
      happens in a city you saved.
      <a href="<?= e(url('travelers')) ?>">Find travelers</a>,
      <a href="<?= e(url('explore')) ?>">save the cities you care about</a>, or
      <a data-review-cta="feed" href="<?= e(url('contribute')) ?>">review a place you went to</a>.
    </div>
  <?php endif; ?>
  <p style="margin:0 0 14px">
    <a class="btn btn-sm <?= ($scope ?? 'following') === 'following' ? 'btn-primary' : 'btn-ghost' ?>"
       href="<?= e(url('feed')) ?>">Following</a>
    <a class="btn btn-sm <?= ($scope ?? '') === 'everyone' ? 'btn-primary' : 'btn-ghost' ?>"
       href="<?= e(url('feed?scope=everyone')) ?>">Everyone</a>
  </p>

  <?php if (!empty($cities) && ($scope ?? 'following') === 'following'): ?>
    <?php /* Which cities are in here. Also the fastest way back to one of them. */ ?>
    <p class="hint" style="margin:0 0 14px">Cities in your feed:
      <?php foreach ($cities as $i => $c): ?><?= $i ? ' · ' : ' ' ?><a href="<?= e(url('d/'.$c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
    </p>
  <?php endif; ?>

  <?php if (!empty($isEveryone) && ($scope ?? '') !== 'everyone'): ?>
    <?php /* Say whose activity this is. A feed that quietly shows strangers as if they were people
             you chose to follow is a small lie that gets found out the moment somebody checks. */ ?>
    <p class="hint">You are not following anybody yet and nothing has happened in the cities you saved,
      so this is everyone on RuinMyTrip.
      <a href="<?= e(url('travelers')) ?>">Find people to follow</a>.</p>
  <?php endif; ?>

  <?php foreach ($items as $it): ?>
    <article class="card" style="margin-bottom:18px">
      <?php if (!empty($it['cover_url'])): ?>
        <a href="<?= e($it['feed_url']) ?>"><img class="card-media" loading="lazy" src="<?= e(abs_url($it['cover_url'])) ?>" alt="<?= e($it['title']) ?>"></a>
      <?php endif; ?>
      <div class="card-body">
        <div class="meta-row" style="margin:0 0 8px">
          <img class="avatar" src="<?= e(avatar_url($it['author']['avatar_url']??null)) ?>" alt="">
          <span>
            <?php /* An activity feed has to say who did what to which thing. It used to lead with a
                     kind chip and then the author, so a review entry read "Review, @somebody, 49m
                     ago, Prague" and never named the place that was reviewed -- the one fact the
                     entry exists to carry. The verb does the work the chip was doing, so the chip
                     goes. */ ?>
            <a href="<?= e(url('u/'.($it['author']['username']??''))) ?>">@<?= e($it['author']['username']??'') ?></a>
            <?= e($rmt_post_verb($it) ?? ($rmt_kind_verbs[$it['kind']] ?? 'posted')) ?><?php if (!empty($it['subject'])): ?>
              <?php if (!empty($it['subject_url'])): ?><a href="<?= e($it['subject_url']) ?>"><b><?= e((string) $it['subject']) ?></b></a><?php
                    else: ?><b><?= e((string) $it['subject']) ?></b><?php endif; ?><?php endif; ?>
            <span class="hint">&middot; <?= e(ago($it['created_at'])) ?><?= !empty($it['dest_name'])?' · '.e($it['dest_name']):'' ?></span>
          </span>
        </div>
        <h3><a href="<?= e($it['feed_url']) ?>"><?= e($it['title']) ?></a></h3>
        <p><?= e($it['feed_excerpt']) ?></p>
      </div>
    </article>
  <?php endforeach; ?>
</div>
