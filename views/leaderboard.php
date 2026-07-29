<?php /** @var array $rows @var ?array $dest @var array $destinations */ ?>
<div class="wrap">
  <p class="crumbs">
    <a href="<?= e(url()) ?>">Home</a> /
    <?php if ($dest): ?><a href="<?= e(url('d/'.$dest['slug'])) ?>"><?= e($dest['name']) ?></a> / <?php endif; ?>
    Top Reviewers
  </p>
  <h1><?= $dest ? 'Top Reviewers in '.e($dest['name']) : 'Top Reviewers' ?></h1>
  <p class="muted">
    Ranked by published reviews, and the useful/funny/cool votes and compliments other travelers
    gave them back. No self-reported scores — every number here is a live count.
  </p>

  <form action="<?= e(url('leaderboard')) ?>" method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 30px">
    <select name="d" onchange="this.form.submit()">
      <option value="">All destinations</option>
      <?php foreach ($destinations as $d): ?>
        <option value="<?= e($d['slug']) ?>" <?= ($dest && $dest['slug']===$d['slug'])?'selected':'' ?>><?= e($d['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if (!$rows): ?>
    <p class="muted">
      No ranked reviewers yet<?= $dest ? ' for '.e($dest['name']) : '' ?>. Be the first to
      <a href="<?= e(url('review/new')) ?>">post a review</a>.
    </p>
  <?php endif; ?>

  <div class="grid" style="gap:12px;padding-bottom:50px">
    <?php foreach ($rows as $i => $r): ?>
      <article class="card"><div class="card-body" style="display:flex;gap:14px;align-items:center">
        <div style="font-size:1.3rem;font-weight:700;width:32px;text-align:center;flex-shrink:0" class="muted">
          #<?= $i + 1 ?>
        </div>
        <?php if (!empty($r['avatar_url'])): ?>
          <img class="avatar" style="width:48px;height:48px;flex-shrink:0" src="<?= e(avatar_url($r['avatar_url'])) ?>" alt="">
        <?php endif; ?>
        <div style="flex:1;min-width:0">
          <h2 style="font-size:1.05rem;margin:0">
            <a href="<?= e(url('u/'.$r['username'])) ?>"><?= e($r['display_name'] ?: $r['username']) ?></a>
          </h2>
          <p class="muted" style="margin:.1rem 0 0">
            @<?= e($r['username']) ?><?= $r['home_city'] ? ' · '.e($r['home_city']) : '' ?>
          </p>
          <?php if ($r['badges']): ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
              <?php foreach ($r['badges'] as $b): ?>
                <span class="chip" style="background:#0f766e;color:#fff" title="<?= e($b['description']) ?>">
                  <?= e($b['icon']) ?> <?= e($b['name']) ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="meta-row" style="text-align:right;flex-shrink:0">
          <div><b><?= (int)$r['review_count'] ?></b> <?= (int)$r['review_count']===1?'review':'reviews' ?></div>
          <div class="muted"><?= (int)$r['votes'] ?> votes · <?= (int)$r['compliments'] ?> compliments</div>
        </div>
      </div></article>
    <?php endforeach; ?>
  </div>
</div>
