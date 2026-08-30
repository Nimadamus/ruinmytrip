<?php /** @var array $items @var ?array $me */
$rmt_kind_verbs = ['trip' => 'shared a trip', 'review' => 'reviewed', 'guide' => 'wrote a guide',
                   'blog_post' => 'posted', 'collection' => 'made the list', 'going' => 'is going to', 'post' => 'said'];
$rmt_kind_labels = ['trip' => 'Trip', 'review' => 'Review', 'guide' => 'Guide', 'blog_post' => 'Blog', 'collection' => 'Collection', 'going' => "Who's going", 'post' => 'Talk'];
?>

<?php /* Two things a stranger can act on without an account: what people are saying, and which
         rooms are open. Both above the stream, because the stream is a list of things to read and
         these are things to join. */ ?>
<?php if (!empty($topTalk)): ?>
  <div class="wrap">
    <h2 style="margin:18px 0 8px">Travelers are saying</h2>
    <?php foreach ($topTalk as $tp): ?>
      <div class="card" style="margin-bottom:8px"><div class="card-body" style="padding:12px 16px">
        <a href="<?= e(url('post/'.(int) $tp['id'])) ?>"><?= e(mb_strimwidth((string) $tp['body'], 0, 150, '…')) ?></a>
        <span class="hint"> · @<?= e((string) $tp['username']) ?>
          <?php if ((int) ($tp['reply_count'] ?? 0) > 0): ?> · <?= (int) $tp['reply_count'] ?> replies<?php endif; ?></span>
      </div></div>
    <?php endforeach; ?>
    <p style="margin:6px 0 20px"><a class="btn btn-ghost btn-sm" href="<?= e(url('talk')) ?>">All travel talk</a></p>
  </div>
<?php endif; ?>

<?php if (!empty($communities)): ?>
  <div class="wrap">
    <h2 style="margin:18px 0 8px">Communities open to join</h2>
    <div class="grid g-2" style="margin-bottom:22px">
      <?php foreach ($communities as $cc): ?>
        <div class="card"><div class="card-body" style="padding:12px 16px">
          <b><a href="<?= e(url('c/'.$cc['slug'])) ?>"><?= e((string) $cc['title']) ?></a></b>
          <p class="hint" style="margin:.2rem 0 0"><?= (int) $cc['member_count'] ?> members
            <?php if ((int) ($cc['post_count'] ?? 0) > 0): ?> · <?= (int) $cc['post_count'] ?> posts<?php endif; ?></p>
        </div></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
<div class="wrap" style="max-width:760px">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Discover</p>
  <h1 style="margin-top:8px">Discover</h1>
  <p class="muted">The latest trips, reviews, guides and blog posts from every traveler on RuinMyTrip.</p>
  <?php if (!empty($topTags)): ?>
    <div class="tag-row">
      <?php foreach ($topTags as $t): ?>
        <a class="chip" href="<?= e(url('tag/'.$t['name'])) ?>">#<?= e($t['name']) ?></a>
      <?php endforeach; ?>
      <a class="chip" href="<?= e(url('tags')) ?>">All topics →</a>
    </div>
  <?php endif; ?>
  <?php if (!$me): ?>
    <div class="callout">
      <a href="<?= e(url('register')) ?>">Join free</a> to follow travelers and get this curated into your own feed.
    </div>
  <?php endif; ?>
  <?php if (!$items): ?>
    <?php /* The public feed's empty state. /feed already says the honest version of this and points
             at the three things that fill it; this said one sentence and offered nowhere to go.
             Same meaning, different words -- the sweep is for consistency of philosophy, not
             identical copy on every page. */ ?>
    <div class="empty-cta" style="margin:24px 0">
      <h3>Nothing published yet.</h3>
      <p class="muted" style="margin:0">
        This fills up as travelers post. Nothing is invented to fill it in the meantime.
      </p>
      <p style="margin:14px 0 0;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn-accent" data-review-cta="feed" href="<?= e(url('contribute')) ?>">Review a place you went to</a>
        <a class="btn btn-ghost" href="<?= e(url('explore')) ?>">Explore destinations</a>
      </p>
    </div>
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
