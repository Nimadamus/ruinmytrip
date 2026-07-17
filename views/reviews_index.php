<?php /** @var array $reviews @var bool $mine @var string $cat @var ?array $me */ ?>
<section class="block"><div class="wrap">
  <div class="section-head">
    <div>
      <h1><?= $mine ? 'Your reviews' : 'Traveler reviews' ?></h1>
      <p class="muted"><?= $mine
        ? 'Everything you have written, including drafts only you can see.'
        : 'Honest reviews of destinations, stays, food, attractions and experiences.' ?></p>
    </div>
    <a class="btn btn-accent btn-sm" href="<?= e(url('review/new')) ?>">Write a Review</a>
  </div>

  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
    <?php if (!$mine): ?>
      <a class="chip" href="<?= e(url('reviews')) ?>" style="<?= $cat==='' ? 'background:var(--ink);color:#fff' : '' ?>">All</a>
      <?php foreach (RMT_REVIEW_CATEGORIES as $c): ?>
        <a class="chip" href="<?= e(url('reviews?category='.$c)) ?>"
           style="<?= $cat===$c ? 'background:var(--ink);color:#fff' : '' ?>"><?= e(ucfirst($c)) ?></a>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($me): ?>
      <a class="chip" href="<?= e(url($mine ? 'reviews' : 'reviews?mine=1')) ?>"
         style="<?= $mine ? 'background:var(--ink);color:#fff' : '' ?>"><?= $mine ? 'All reviews' : 'My reviews & drafts' ?></a>
    <?php endif; ?>
  </div>

  <?php if (!$reviews): ?>
    <p class="muted"><?= $mine
      ? 'You have not written a review yet.'
      : 'No reviews here yet. The first honest one can be yours.' ?></p>
  <?php endif; ?>

  <div class="grid" style="gap:14px">
    <?php foreach ($reviews as $r): ?>
      <?php $href = url('review/'.(int)$r['id'].'/'.($r['slug'] ?: rmt_review_slug($r))); ?>
      <article class="card"><div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
          <span class="stars"><?= stars((int)$r['rating']) ?></span>
          <span style="display:flex;gap:6px;align-items:center">
            <?php if ($r['status'] === 'draft'): ?>
              <span class="chip" style="background:#fef3c7;color:#92400e">Draft</span>
            <?php elseif (in_array($r['status'], ['hidden','removed'], true)): ?>
              <span class="chip" style="background:#fee2e2;color:#b42318"><?= e(ucfirst($r['status'])) ?></span>
            <?php endif; ?>
            <?php if (show_verified($r)): ?><span class="verified">Verified</span><?php endif; ?>
          </span>
        </div>
        <h2 style="margin:.35rem 0 .2rem;font-size:1.1rem">
          <a href="<?= e($href) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a>
        </h2>
        <p class="muted" style="margin:0">
          <?= e($r['subject_name']) ?> · <span style="text-transform:capitalize"><?= e($r['subject_type']) ?></span>
          <?php if ($r['dest_name']): ?> · <?= e($r['dest_name']) ?><?php endif; ?>
        </p>
        <p style="margin:.5rem 0 0"><?= e(mb_strimwidth((string)$r['body'], 0, 160, '…')) ?></p>
        <div class="meta-row" style="justify-content:space-between">
          <span>@<?= e($r['author']['username'] ?? 'traveler') ?> · <?= e(ago((string)$r['created_at'])) ?></span>
          <?php if (rmt_review_can_edit($r, $me)): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('review/'.(int)$r['id'].'/edit')) ?>">Edit</a>
          <?php endif; ?>
        </div>
      </div></article>
    <?php endforeach; ?>
  </div>
</div></section>
