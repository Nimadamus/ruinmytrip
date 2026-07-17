<?php /** @var array $r @var ?array $author @var array $photos @var ?array $me */ ?>
<article class="wrap" style="max-width:760px;padding-top:28px">
  <?php if ($r['status'] !== 'published'): ?>
    <div class="callout"><b><?= e(ucfirst((string)$r['status'])) ?>.</b>
      <?php if ($r['status']==='draft'): ?> Only you can see this page.
      <?php else: ?> This review is not publicly visible.<?php endif; ?>
    </div>
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
      <img class="avatar" src="<?= e($author['avatar_url']) ?>" alt="">
    <?php endif; ?>
    <span>by <a href="<?= e(url('u/'.$author['username'])) ?>">@<?= e($author['username']) ?></a>
      · <?= e(ago((string)$r['created_at'])) ?>
      <?php if ($r['visited_on']): ?> · visited <?= e(date('M Y', strtotime((string)$r['visited_on']))) ?><?php endif; ?>
    </span>
  </div>

  <p style="margin:22px 0;white-space:pre-wrap;font-size:1.05rem;line-height:1.7"><?= e($r['body']) ?></p>

  <?php if ($r['what_great']): ?>
    <div class="card" style="margin:14px 0"><div class="card-body">
      <p class="eyebrow" style="color:#0f766e;margin:0 0 6px">What was great</p>
      <p style="margin:0;white-space:pre-wrap"><?= e($r['what_great']) ?></p>
    </div></div>
  <?php endif; ?>

  <?php if ($r['what_ruined']): ?>
    <div class="card" style="margin:14px 0"><div class="card-body">
      <p class="eyebrow" style="color:#b42318;margin:0 0 6px">What nearly ruined the trip</p>
      <p style="margin:0;white-space:pre-wrap"><?= e($r['what_ruined']) ?></p>
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

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin:28px 0 40px">
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
</article>
