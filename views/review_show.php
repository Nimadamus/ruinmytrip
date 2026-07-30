<?php /** @var array $r @var ?array $author @var array $photos @var ?array $me @var array $voteCounts @var array $myVotes */ ?>
<?php $rmt_vote_labels = ['useful'=>'👍 Useful','funny'=>'😄 Funny','cool'=>'😎 Cool']; ?>
<article class="wrap" style="max-width:760px;padding-top:28px">
  <?php if ($r['status'] !== 'published'): ?>
    <div class="callout"><b><?= e(ucfirst((string)$r['status'])) ?>.</b>
      <?php if ($r['status']==='draft'): ?> Only you can see this page.
      <?php else: ?> This review is not publicly visible.<?php endif; ?>
    </div>
  <?php endif; ?>

  <?php $isEd = rmt_is_editorial($author); ?>
  <?php if ($isEd): ?>
    <div class="card ed-panel" style="margin-bottom:18px"><div class="card-body">
      <?= rmt_editorial_badge('review') ?>
      <p style="margin:.5rem 0 0"><?= e(rmt_editorial_disclosure()) ?></p>
    </div></div>
  <?php endif; ?>

  <p class="eyebrow" style="text-transform:capitalize"><?= e($r['subject_type']) ?>
    <?php if ($r['dest_name']): ?> · <a href="<?= e(url('d/'.$r['dest_slug'])) ?>"><?= e($r['dest_name']) ?></a><?php endif; ?>
  </p>
  <h1 style="margin:.2rem 0 .4rem"><?= e($r['title'] ?: $r['subject_name']) ?></h1>

  <div class="meta-row" style="gap:10px;align-items:center">
    <span class="stars" style="font-size:1.1rem"><?= stars((int)$r['rating']) ?></span>
    <span class="muted"><?= (int)$r['rating'] ?>/5</span>
    <?php if (show_verified($r)): ?><span class="verified">Verified</span><?php endif; ?>
  </div>

  <div class="meta-row" style="margin-top:10px">
    <?php if (!empty($author['avatar_url'])): ?>
      <img class="avatar" src="<?= e(avatar_url($author['avatar_url']??null)) ?>" alt="">
    <?php endif; ?>
    <span>by <a href="<?= e(url('u/'.$author['username'])) ?>"><?= $isEd ? e(rmt_editorial_name()) : '@'.e($author['username']) ?></a>
      · <?= e(ago((string)$r['created_at'])) ?>
      <?php if ($r['visited_on']): ?> · visited <?= e(date('M Y', strtotime((string)$r['visited_on']))) ?><?php endif; ?>
    </span>
  </div>

  <?php if ($isEd): ?>
    <div class="empty-cta" style="margin-top:22px">
      <h2 style="margin:0 0 6px;font-size:1.15rem">Been to <?= e($r['dest_name'] ?: $r['subject_name']) ?>? This page needs you more than it needs us.</h2>
      <p class="muted" style="margin:0 0 14px">A first-hand review outranks desk research every time, and yours will sit above this one.</p>
      <a class="btn btn-accent" href="<?= e(url('review/new')) ?>">Share your experience</a>
    </div>
  <?php endif; ?>

  <p style="margin:22px 0;white-space:pre-wrap;font-size:1.05rem;line-height:1.7"><?= rmt_linkify_mentions(rmt_linkify_tags(e($r['body']))) ?></p>
  <?php if (!empty($tags)): ?>
    <div class="tag-row"><?php foreach ($tags as $tg): ?><a class="chip" href="<?= e(url('tag/'.$tg['name'])) ?>">#<?= e($tg['name']) ?></a><?php endforeach; ?></div>
  <?php endif; ?>

  <?php if ($r['what_great']): ?>
    <div class="card" style="margin:14px 0"><div class="card-body">
      <p class="eyebrow" style="color:#0f766e;margin:0 0 6px">What was great</p>
      <p style="margin:0;white-space:pre-wrap"><?= rmt_linkify_mentions(rmt_linkify_tags(e($r['what_great']))) ?></p>
    </div></div>
  <?php endif; ?>

  <?php if ($r['what_ruined']): ?>
    <div class="card" style="margin:14px 0"><div class="card-body">
      <p class="eyebrow" style="color:#b42318;margin:0 0 6px">What nearly ruined the trip</p>
      <p style="margin:0;white-space:pre-wrap"><?= rmt_linkify_mentions(rmt_linkify_tags(e($r['what_ruined']))) ?></p>
    </div></div>
  <?php endif; ?>

  <?php if ($r['safety_rating'] || $r['value_rating']): ?>
    <div class="grid g-2" style="gap:14px;margin:18px 0">
      <?php if ($r['safety_rating']): ?>
        <div class="card"><div class="card-body">
          <p class="muted" style="margin:0 0 4px">Safety</p>
          <span class="stars"><?= stars((int)$r['safety_rating']) ?></span>
        </div></div>
      <?php endif; ?>
      <?php if ($r['value_rating']): ?>
        <div class="card"><div class="card-body">
          <p class="muted" style="margin:0 0 4px">Value for money</p>
          <span class="stars"><?= stars((int)$r['value_rating']) ?></span>
        </div></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($photos): ?>
    <div class="grid g-2" style="gap:10px;margin:18px 0">
      <?php foreach ($photos as $p): ?>
        <figure style="margin:0">
          <img class="card-media" loading="lazy" src="<?= e($p['url']) ?>" alt="<?= e($p['caption'] ?: $r['subject_name']) ?>">
          <?php if ($p['caption']): ?><figcaption class="muted" style="font-size:.9rem"><?= e($p['caption']) ?></figcaption><?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php $isOwn = $me && (int)$me['id'] === (int)$r['user_id']; ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:26px 0 4px">
    <span class="muted" style="margin-right:4px">Was this review helpful?</span>
    <?php foreach (RMT_REVIEW_VOTE_TYPES as $vt): $mine = in_array($vt, $myVotes, true); ?>
      <?php if ($me && !$isOwn): ?>
        <form class="inline-form" method="post" action="<?= e(url('review/'.(int)$r['id'].'/vote')) ?>">
          <?= csrf_field() ?><input type="hidden" name="vote_type" value="<?= e($vt) ?>">
          <input type="hidden" name="return" value="<?= e(url(ltrim(rmt_review_path($r),'/'))) ?>">
          <button class="btn btn-sm <?= $mine?'btn-primary':'btn-ghost' ?>"><?= e($rmt_vote_labels[$vt]) ?> <?= (int)$voteCounts[$vt] ?></button>
        </form>
      <?php else: ?>
        <span class="chip"><?= e($rmt_vote_labels[$vt]) ?> <?= (int)$voteCounts[$vt] ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 40px">
    <?php if (rmt_review_can_edit($r, $me)): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('review/'.(int)$r['id'].'/edit')) ?>">Edit</a>
    <?php endif; ?>
    <?php if ($me && !rmt_review_can_edit($r, $me)): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('report?target_type=review&target_id='.(int)$r['id'])) ?>">Report</a>
    <?php endif; ?>
    <?php if ($r['dest_name']): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('d/'.$r['dest_slug'])) ?>">More about <?= e($r['dest_name']) ?></a>
    <?php endif; ?>
  </div>

  <?php
    $showActionsBar = false; $targetType = 'review'; $targetId = (int)$r['id']; $ownerId = (int)$r['user_id'];
    $returnUrl = url(ltrim(rmt_review_path($r),'/'));
    include __DIR__ . '/_engagement.php';
  ?>
</article>
