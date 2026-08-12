<?php /** @var array $p @var array $stats @var array $breakdown @var array $reviews @var array $editorial @var array $photos @var ?array $me @var string $typeLabel */ ?>
<div class="wrap">
  <p class="crumbs">
    <a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> /
    <a href="<?= e(url('d/'.$p['dest_slug'])) ?>"><?= e($p['dest_name']) ?></a> /
    <a href="<?= e(url('d/'.$p['dest_slug'].'/places')) ?>">Places</a>
  </p>
  <p class="eyebrow" style="margin-top:6px"><?= e($typeLabel) ?> · <?= e($p['dest_name']) ?>, <?= e($p['dest_country']) ?></p>
  <h1 style="margin:.2rem 0 .5rem"><?= e($p['name']) ?></h1>

  <?php if ($stats['c'] > 0): ?>
    <div class="meta-row" style="gap:12px;align-items:center;margin-bottom:6px">
      <span class="stars" style="font-size:1.25rem"><?= stars((int) round((float)$stats['a'])) ?></span>
      <strong style="font-size:1.15rem"><?= e((string)$stats['a']) ?>/5</strong>
      <span class="muted">from <?= (int)$stats['c'] ?> traveler <?= $stats['c'] === 1 ? 'review' : 'reviews' ?></span>
    </div>
    <?php /* Denominators are shown per line: safety and value are optional on the write form, so a
             place with 12 reviews and 3 safety ratings must say "from 3", never average the blanks. */ ?>
    <p class="muted" style="margin:0 0 14px">
      <?php if ($stats['safety_c'] > 0): ?>Safety <?= e((string)$stats['safety_a']) ?>/5 <span class="hint">(from <?= (int)$stats['safety_c'] ?>)</span><?php endif; ?>
      <?php if ($stats['safety_c'] > 0 && $stats['value_c'] > 0): ?> · <?php endif; ?>
      <?php if ($stats['value_c'] > 0): ?>Value <?= e((string)$stats['value_a']) ?>/5 <span class="hint">(from <?= (int)$stats['value_c'] ?>)</span><?php endif; ?>
    </p>

    <div style="max-width:420px;margin:0 0 22px">
      <?php foreach ([5,4,3,2,1] as $n): $c = (int)$breakdown[$n]; $pct = $stats['c'] > 0 ? round($c * 100 / $stats['c']) : 0; ?>
        <div style="display:flex;align-items:center;gap:10px;margin:3px 0">
          <span class="muted" style="width:2.4rem;font-size:.9rem"><?= $n ?> ★</span>
          <span style="flex:1;height:8px;background:#e9e9ee;border-radius:99px;overflow:hidden">
            <span style="display:block;height:100%;width:<?= $pct ?>%;background:var(--ink)"></span>
          </span>
          <span class="muted" style="width:2rem;text-align:right;font-size:.9rem"><?= $c ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="muted" style="margin:0 0 18px">
      No published traveler reviews yet, and we will not invent one to fill the space.
    </p>
  <?php endif; ?>

  <p style="margin:0 0 26px">
    <a class="btn btn-accent" href="<?= e(url('review/new?destination='.(int)$p['destination_id'])) ?>">Write a review</a>
    <a class="btn btn-ghost" href="<?= e(url('d/'.$p['dest_slug'].'/places')) ?>">More in <?= e($p['dest_name']) ?></a>
  </p>

  <?php if ($photos): ?>
    <h2 style="font-size:1.1rem;margin:0 0 10px">Traveler photos</h2>
    <div class="grid g-4" style="margin-bottom:28px">
      <?php foreach ($photos as $ph): ?>
        <a href="<?= e(url('review/'.$ph['parent_id'].($ph['parent_slug'] ? '/'.$ph['parent_slug'] : ''))) ?>" title="<?= e($ph['caption'] ?? '') ?>">
          <img class="card-media" loading="lazy" style="aspect-ratio:1;object-fit:cover"
               src="<?= e(abs_url($ph['url'])) ?>"
               alt="<?= e($ph['caption'] ?: ($p['name'].' photo by @'.($ph['author']['username'] ?? ''))) ?>">
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($editorial): ?>
    <h2 style="font-size:1.1rem;margin:0 0 10px">From the RuinMyTrip team</h2>
    <p class="hint" style="margin:-4px 0 12px"><?= e(rmt_editorial_disclosure()) ?></p>
    <div class="grid" style="gap:14px;margin-bottom:28px">
      <?php foreach ($editorial as $r): ?>
        <?php $href = url('review/'.(int)$r['id'].'/'.($r['slug'] ?: rmt_review_slug($r))); ?>
        <article class="card"><div class="card-body">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
            <span class="stars"><?= stars((int)$r['rating']) ?></span>
            <?= rmt_editorial_badge('review') ?>
          </div>
          <h3 style="margin:.35rem 0 .2rem;font-size:1.05rem"><a href="<?= e($href) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a></h3>
          <p style="margin:.4rem 0 0"><?= e(mb_strimwidth((string)$r['body'], 0, 200, '…')) ?></p>
        </div></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2 style="font-size:1.1rem;margin:0 0 10px">
    <?= $stats['c'] > 0 ? 'What travelers said' : 'Traveler reviews' ?>
  </h2>

  <?php if (!$reviews): ?>
    <div class="empty-cta" style="margin-bottom:50px">
      <h3>Be the first to review <?= e($p['name']) ?>.</h3>
      <p class="muted" style="margin:0">The bad parts are the useful parts. Say what it actually cost and what you wish you had known.</p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url('review/new?destination='.(int)$p['destination_id'])) ?>">Share your experience</a></p>
    </div>
  <?php endif; ?>

  <div class="grid" style="gap:14px;padding-bottom:50px">
    <?php foreach ($reviews as $r): ?>
      <?php $href = url('review/'.(int)$r['id'].'/'.($r['slug'] ?: rmt_review_slug($r))); ?>
      <article class="card"><div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
          <span class="stars"><?= stars((int)$r['rating']) ?></span>
          <?php if (show_verified($r)): ?><span class="verified">Verified</span><?php endif; ?>
        </div>
        <h3 style="margin:.35rem 0 .2rem;font-size:1.05rem"><a href="<?= e($href) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a></h3>
        <p style="margin:.4rem 0 0"><?= e(mb_strimwidth((string)$r['body'], 0, 200, '…')) ?></p>
        <div class="meta-row" style="justify-content:space-between">
          <span>@<?= e($r['author']['username'] ?? 'traveler') ?> · <?= e(ago((string)$r['created_at'])) ?><?php if (!empty($r['useful_count'])): ?> · 👍 <?= (int)$r['useful_count'] ?> found this useful<?php endif; ?><?php if (rmt_review_is_stale($r)): ?> · <span class="hint">⏳ may be outdated</span><?php endif; ?></span>
          <?php if (rmt_review_can_edit($r, $me)): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('review/'.(int)$r['id'].'/edit')) ?>">Edit</a>
          <?php endif; ?>
        </div>
      </div></article>
    <?php endforeach; ?>
  </div>
</div>
