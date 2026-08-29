<?php /** @var array $board @var int $days @var array $steps @var array $byAuth @var array $bySource @var array $failures @var array $counts */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Moderation</a> / Contribution funnel</p>
  <h1 style="margin:.2rem 0 .4rem">Contribution funnel</h1>
  <p class="hint" style="margin:0 0 6px">
    Attempts, not people. This table holds no user id, no address and no review text &mdash; the
    questions it answers do not need any of them. An attempt is one journey through the flow, so
    somebody clicking a button three times before the page loads counts once.
  </p>
  <p class="hint" style="margin:0 0 20px">
    <?php foreach ([1 => 'Today', 7 => '7 days', 30 => '30 days', 0 => 'All time'] as $d => $lbl): ?>
      <a href="<?= e(url('admin/funnel') . '?days=' . $d) ?>"<?= $d === $days ? ' style="font-weight:700"' : '' ?>><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </p>

  <?php
  /* The scoreboard. Whether this is a review site yet is one number, and it is the first thing on
     the page rather than something to scroll for. */
  $published = (int) ($counts['review_publish_success'] ?? 0);
  $clicks    = (int) ($counts['review_cta_click'] ?? 0);
  ?>
  <section class="card" style="margin:0 0 20px"><div class="card-body">
    <h2 style="margin:0 0 4px;font-size:1.05rem">Community scoreboard</h2>
    <p class="hint" style="margin:0 0 12px">
      Real rows only, editorial excluded. A zero here means zero &mdash; the number does not move
      until a traveler writes something.
    </p>
    <div class="grid g-2" style="gap:4px 24px">
      <?php foreach ([
        'reviews'             => 'Community reviews',
        'reviewers'           => 'Unique reviewers',
        'places_reviewed'     => 'Places with a review',
        'places_rankable'     => 'Places with ' . RMT_TOP_MIN_REVIEWS . '+ reviews',
        'destinations_active' => 'Destinations with activity',
        'photos'              => 'Community photos',
        'reviews_7d'          => 'Reviews, last 7 days',
        'reviews_30d'         => 'Reviews, last 30 days',
      ] as $k => $label): ?>
        <p style="margin:2px 0;font-size:.94rem">
          <span class="muted"><?= e($label) ?></span>
          <strong style="float:right"><?= (int) $board[$k] ?></strong>
        </p>
      <?php endforeach; ?>
    </div>
    <p style="margin:12px 0 0;font-size:.94rem">
      <span class="muted">CTA to published</span>
      <strong style="float:right">
        <?= $clicks > 0 ? (int) round($published * 100 / $clicks) . '%' : '&mdash;' ?>
      </strong>
    </p>
    <?php if (!empty($board['last_community_review'])): ?>
      <p class="hint" style="margin:8px 0 0">Last community review: <?= e(substr((string) $board['last_community_review'], 0, 16)) ?>.</p>
    <?php endif; ?>
  </div></section>

  <section class="card" style="margin:0 0 20px"><div class="card-body">
    <h2 style="margin:0 0 4px;font-size:1.05rem">Who could be told</h2>
    <p class="hint" style="margin:0 0 10px">
      Counts only. Nothing here sends anything and nothing here reads an address; it exists so an
      acquisition decision is made against a real number rather than a guess.
    </p>
    <p style="margin:2px 0;font-size:.94rem"><span class="muted">Registered accounts</span>
      <strong style="float:right"><?= (int) $board['users'] ?></strong></p>
    <p style="margin:2px 0;font-size:.94rem"><span class="muted">Active</span>
      <strong style="float:right"><?= (int) $board['users_active'] ?></strong></p>
    <p style="margin:2px 0;font-size:.94rem"><span class="muted">Email confirmed</span>
      <strong style="float:right"><?= (int) $board['users_verified'] ?></strong></p>
  </div></section>

  <?php $top = max(1, (int) ($steps[0]['count'] ?? 0)); ?>
  <section class="card" style="margin:0 0 20px"><div class="card-body">
    <h2 style="margin:0 0 10px;font-size:1.05rem">The path</h2>
    <?php if (!array_sum(array_column($steps, 'count'))): ?>
      <p class="muted" style="margin:0">
        Nothing recorded in this window yet. That is the honest state: no traveler has started a
        review here.
      </p>
    <?php else: ?>
      <?php foreach ($steps as $i => $st): $pct = (int) round($st['count'] * 100 / $top); ?>
        <div style="display:flex;align-items:center;gap:10px;margin:5px 0">
          <span class="muted" style="width:14rem;font-size:.9rem">
            <?= e($st['label']) ?>
            <?php /* A branch step applies only to some attempts, so reading it as a straight-line
                     loss would be wrong -- most people do not need to sign up. */ ?>
            <?php if ($st['branch']): ?><span class="hint">(some)</span><?php endif; ?>
          </span>
          <span style="flex:1;height:8px;background:#e9e9ee;border-radius:99px;overflow:hidden">
            <span style="display:block;height:100%;width:<?= $pct ?>%;background:var(--ink)"></span>
          </span>
          <strong style="width:3.5rem;text-align:right;font-size:.9rem"><?= (int) $st['count'] ?></strong>
          <?php $prev = $i > 0 ? (int) $steps[$i - 1]['count'] : 0; ?>
          <span class="hint" style="width:5.5rem;text-align:right">
            <?php if ($i > 0 && $prev > 0 && !$st['branch'] && !$steps[$i - 1]['branch']): ?>
              <?= (int) round($st['count'] * 100 / $prev) ?>% of prev
            <?php endif; ?>
          </span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div></section>

  <section class="card" style="margin:0 0 20px"><div class="card-body">
    <h2 style="margin:0 0 4px;font-size:1.05rem">Signed in before starting, or not</h2>
    <p class="hint" style="margin:0 0 10px">
      An anonymous attempt has two extra steps in front of it. Lumping the two together hides
      whichever one is broken.
    </p>
    <?php foreach (['authed' => 'Already signed in', 'anonymous' => 'Not signed in'] as $k => $lbl):
      $row = $byAuth[$k]; $rate = $row['started'] > 0 ? round($row['published'] * 100 / $row['started']) : 0; ?>
      <p style="margin:3px 0;font-size:.94rem">
        <?= e($lbl) ?>:
        <strong><?= (int) $row['published'] ?></strong> published of
        <?= (int) $row['started'] ?> attempts
        <?php if ($row['started'] > 0): ?><span class="muted">(<?= $rate ?>%)</span><?php endif; ?>
      </p>
    <?php endforeach; ?>
  </div></section>

  <?php if ($bySource): ?>
    <section class="card" style="margin:0 0 20px"><div class="card-body">
      <h2 style="margin:0 0 4px;font-size:1.05rem">Where the attempt started</h2>
      <p class="hint" style="margin:0 0 10px">
        Which surface produces reviews, which is not the same question as which one gets clicked.
      </p>
      <?php foreach ($bySource as $r): ?>
        <p style="margin:3px 0;font-size:.94rem">
          <?= e((string) $r['source']) ?>:
          <strong><?= (int) $r['published'] ?></strong> published of <?= (int) $r['attempts'] ?> attempts
        </p>
      <?php endforeach; ?>
    </div></section>
  <?php endif; ?>

  <?php if ($failures): ?>
    <section class="card" style="margin:0 0 20px"><div class="card-body">
      <h2 style="margin:0 0 4px;font-size:1.05rem">Why a publish did not happen</h2>
      <p class="hint" style="margin:0 0 10px">Operational reasons only. Never the content that failed.</p>
      <?php foreach ($failures as $f): ?>
        <p style="margin:3px 0;font-size:.94rem"><?= e((string) $f['reason']) ?>: <strong><?= (int) $f['n'] ?></strong></p>
      <?php endforeach; ?>
    </div></section>
  <?php endif; ?>

  <section class="card" style="margin:0 0 30px"><div class="card-body">
    <h2 style="margin:0 0 10px;font-size:1.05rem">Every event</h2>
    <div class="grid g-2" style="gap:2px 24px">
      <?php foreach ($counts as $event => $n): ?>
        <p style="margin:2px 0;font-size:.9rem">
          <span class="muted"><?= e(str_replace('_', ' ', (string) $event)) ?></span>
          <strong style="float:right"><?= (int) $n ?></strong>
        </p>
      <?php endforeach; ?>
    </div>
  </div></section>
</div>
