<?php /** @var array $d @var array $places @var array $counts @var int $total @var string $type @var string $label */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> / <a href="<?= e(url('d/'.$d['slug'])) ?>"><?= e($d['name']) ?></a> / Places</p>
  <h1 style="margin-top:6px"><?= e($label) ?> in <?= e($d['name']) ?>, <?= e($d['country']) ?></h1>
  <p class="muted">
    <?= $total ?> reviewed <?= $total === 1 ? 'place' : 'places' ?>. Ratings are the community average —
    our own editorial reviews are never counted in them.
  </p>

  <?php if ($counts): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 18px">
      <a class="chip" href="<?= e(url('d/'.$d['slug'].'/places')) ?>" style="<?= $type==='' ? 'background:var(--ink);color:#fff' : '' ?>">All <?= $total ?></a>
      <?php foreach (RMT_PLACE_TYPES as $t): if (empty($counts[$t])) continue; ?>
        <a class="chip" href="<?= e(url('d/'.$d['slug'].'/places?type='.$t)) ?>"
           style="<?= $type===$t ? 'background:var(--ink);color:#fff' : '' ?>"><?= e(rmt_place_type_label($t, true)) ?> <?= (int)$counts[$t] ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$places): ?>
    <div class="empty-cta" style="margin:20px 0">
      <h3>Nothing reviewed here yet<?= $type ? ' in this category' : '' ?>.</h3>
      <p class="muted" style="margin:0">
        Places appear the moment somebody reviews one. We do not import listings or invent them to
        look busy — if you stayed, ate, or booked something in <?= e($d['name']) ?>, you are the
        first entry.
      </p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url('review/new?destination='.(int)$d['id'])) ?>">Write the first review</a></p>
    </div>
  <?php endif; ?>

  <div class="grid" style="gap:14px;padding-bottom:50px">
    <?php foreach ($places as $p): $c = (int)$p['review_count']; ?>
      <article class="card"><div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
          <span class="eyebrow" style="text-transform:capitalize"><?= e(rmt_place_type_label((string)$p['type'])) ?></span>
          <?php if ($c > 0): ?>
            <span style="display:flex;gap:8px;align-items:center">
              <span class="stars"><?= stars((int) round((float)$p['avg_rating'])) ?></span>
              <span class="muted"><?= e((string)$p['avg_rating']) ?>/5</span>
            </span>
          <?php endif; ?>
        </div>
        <h2 style="margin:.35rem 0 .2rem;font-size:1.1rem">
          <a href="<?= e(url('p/'.$p['slug'])) ?>"><?= e($p['name']) ?></a>
        </h2>
        <?php /* The snippet is the hand-written meta description for that place, not a truncated
                 slice of its body: a generic first-40-words teaser is exactly the boilerplate this
                 page is supposed to avoid. Places with no editorial simply show no snippet. */ ?>
        <?php if (!empty($p['snippet'])): ?>
          <p style="margin:0 0 .35rem"><?= e((string)$p['snippet']) ?></p>
        <?php endif; ?>
        <p class="muted" style="margin:0">
          <?php if ($c > 0): ?>
            <?= $c ?> traveler <?= $c === 1 ? 'review' : 'reviews' ?>
          <?php elseif ((int)$p['editorial_count'] > 0): ?>
            <?= rmt_editorial_badge('review') ?> <span class="hint">no traveler reviews yet</span>
          <?php else: ?>
            No published reviews yet
          <?php endif; ?>
        </p>
      </div></article>
    <?php endforeach; ?>
  </div>
</div>
