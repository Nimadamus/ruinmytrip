<?php /** @var array $t @var array $photos @var array $comments @var int $likeCount @var int $saveCount @var bool $liked @var bool $saved @var array $updates @var bool $isOwner @var string $phase */ $me = current_user(); ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <?php if($t['dest_slug']):?><a href="<?= e(url('d/'.$t['dest_slug'])) ?>"><?= e($t['dest_name']) ?></a> / <?php endif;?><?= e($t['title']) ?></p>
</div>
<div class="wrap prose">
  <h1><?= e($t['title']) ?></h1>
  <div class="meta-row"><img class="avatar" src="<?= e(avatar_url($t['author']['avatar_url']??null)) ?>" alt="">
    <span><a href="<?= e(url('u/'.$t['author']['username'])) ?>">@<?= e($t['author']['username']) ?></a> · <?= e(ago($t['created_at'])) ?>
    <?php if($t['visited_on']):?> · visited <?= e(date('M Y', strtotime((string)$t['visited_on']))) ?><?php endif;?></span>
    <?php if (show_verified($t)): ?><span class="verified">Verified visit</span><?php endif; ?>
  </div>
  <?php if ($t['cover_url']): ?><img class="article-hero" src="<?= e($t['cover_url']) ?>" alt="<?= e($t['title']) ?>"><?php endif; ?>
  <div><?= rmt_linkify_mentions(rmt_linkify_tags(nl2br(e($t['body'])))) ?></div>
  <?php if (!empty($tags)): ?>
    <div class="tag-row"><?php foreach ($tags as $tg): ?><a class="chip" href="<?= e(url('tag/'.$tg['name'])) ?>">#<?= e($tg['name']) ?></a><?php endforeach; ?></div>
  <?php endif; ?>
  <?php foreach ($photos as $p): ?><img class="article-hero" loading="lazy" src="<?= e($p['url']) ?>" alt="<?= e($p['caption']) ?>"><?php endforeach; ?>

  <?php if ($me && (int)$t['user_id'] === (int)$me['id']): ?>
    <p style="margin:12px 0 0"><a class="btn btn-ghost btn-sm" href="<?= e(url('trip/'.$t['id'].'/edit')) ?>">Edit</a></p>
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

  <?php
    $targetType = 'trip'; $targetId = (int)$t['id']; $ownerId = (int)$t['user_id'];
    $returnUrl = url('trip/'.$t['id'].'/'.$t['slug']);
    include __DIR__ . '/_engagement.php';
  ?>
</div>
