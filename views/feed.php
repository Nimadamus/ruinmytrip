<?php /** @var array $items @var array $me @var bool $isEveryone */
$rmt_kind_verbs = ['trip' => 'shared a trip', 'review' => 'reviewed', 'guide' => 'wrote a guide',
                   'blog_post' => 'posted', 'collection' => 'made the list', 'going' => 'is going to', 'post' => 'said'];
$rmt_kind_labels = ['trip' => 'Trip', 'review' => 'Review', 'guide' => 'Guide', 'blog_post' => 'Blog', 'collection' => 'Collection', 'going' => "Who's going", 'post' => 'Talk'];
?>
<div class="wrap" style="max-width:760px">
  <h1 style="margin-top:24px">Your feed</h1>
  <p class="muted">Latest trips, reviews, guides and blog posts from you and the travelers you follow.</p>
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
      Nothing here yet &mdash; this fills up as the travelers you follow post.
      <a href="<?= e(url('travelers')) ?>">Find travelers</a>,
      <a href="<?= e(url('explore')) ?>">explore destinations</a>, or
      <a data-review-cta="feed" href="<?= e(url('contribute')) ?>">review a place you went to</a>.
    </div>
  <?php endif; ?>
  <?php if (!empty($isEveryone)): ?>
    <?php /* Say whose activity this is. A feed that quietly shows strangers as if they were people
             you chose to follow is a small lie that gets found out the moment somebody checks. */ ?>
    <p class="hint">You are not following anybody yet, so this is everyone on RuinMyTrip.
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
            <?= e($rmt_kind_verbs[$it['kind']] ?? 'posted') ?><?php if (!empty($it['subject'])): ?>
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
