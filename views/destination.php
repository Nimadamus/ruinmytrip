<?php /** @var array $d @var array $trips @var array $reviews @var array $guides @var array $meetups @var array $going @var array $avg */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> / <?= e($d['name']) ?></p>
  <div class="dest-hero">
    <img src="<?= e($d['hero_url']) ?>" alt="<?= e($d['name'].', '.$d['country']) ?>">
    <div class="overlay">
      <div>
        <span class="chip"><?= e($d['category']) ?></span>
        <h1><?= e($d['name']) ?>, <?= e($d['country']) ?></h1>
        <p style="color:#e8eef5;margin:.2rem 0 0;max-width:60ch"><?= e($d['summary']) ?></p>
        <?php if ($avg && (int)$avg['c']>0): ?>
          <p style="color:#fff;margin:.4rem 0 0"><span class="stars"><?= stars((int)round((float)$avg['a'])) ?></span> <?= e($avg['a']) ?> · <?= (int)$avg['c'] ?> reviews</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="grid g-2" style="margin-top:30px;align-items:start">
    <div>
      <h2>Trip stories</h2>
      <?php if (!$trips): ?><p class="muted">No stories yet. <a href="<?= e(url('trip/new')) ?>">Be the first to share one.</a></p><?php endif; ?>
      <div class="grid" style="gap:16px">
        <?php foreach ($trips as $t): ?>
          <article class="card"><a href="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>">
            <img class="card-media" loading="lazy" src="<?= e($t['cover_url']) ?>" alt="<?= e($t['title']) ?>">
            <div class="card-body"><h3><?= e($t['title']) ?></h3>
              <div class="meta-row"><img class="avatar" src="<?= e($t['author']['avatar_url']??'') ?>" alt="">@<?= e($t['author']['username']??'') ?>
              <?php if ($t['verified']): ?><span class="verified">Verified visit</span><?php endif; ?></div>
            </div></a></article>
        <?php endforeach; ?>
      </div>

      <h2 style="margin-top:34px">Reviews</h2>
      <?php if (!$reviews): ?><p class="muted">No reviews yet. <a href="<?= e(url('review/new')) ?>">Write one.</a></p><?php endif; ?>
      <?php foreach ($reviews as $r): ?>
        <div class="card" style="margin-bottom:14px"><div class="card-body">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span class="stars"><?= stars((int)$r['rating']) ?></span>
            <?php if ($r['verified']): ?><span class="verified">Verified</span><?php endif; ?>
          </div>
          <h3 style="margin:.3rem 0 .1rem;font-size:1.1rem"><?= e($r['title']) ?></h3>
          <p class="muted" style="margin:0"><?= e($r['subject_name']) ?> · <span style="text-transform:capitalize"><?= e($r['subject_type']) ?></span> · @<?= e($r['author']['username']??'') ?></p>
          <p style="margin:.5rem 0 0"><?= e($r['body']) ?></p>
        </div></div>
      <?php endforeach; ?>
    </div>

    <aside>
      <div class="card"><div class="card-body">
        <h3>Guides & itineraries</h3>
        <?php if (!$guides): ?><p class="muted">No guides yet.</p><?php endif; ?>
        <ul class="list-plain">
          <?php foreach ($guides as $g): ?><li style="padding:6px 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('g/'.$g['slug'])) ?>"><?= e($g['title']) ?></a></li><?php endforeach; ?>
        </ul>
        <a class="btn btn-ghost btn-sm btn-block" style="margin-top:10px" href="<?= e(url('guides')) ?>">All guides</a>
      </div></div>

      <div class="card" style="margin-top:18px"><div class="card-body">
        <h3>Who's going</h3>
        <p class="hint">Destination + date range only. Never precise location.</p>
        <?php if (!$going): ?><p class="muted">No travelers listed yet.</p><?php endif; ?>
        <ul class="list-plain">
          <?php foreach ($going as $g): ?>
            <li class="meta-row" style="justify-content:flex-start">
              <img class="avatar" src="<?= e($g['avatar_url']??'') ?>" alt="">
              <span><a href="<?= e(url('u/'.$g['username'])) ?>">@<?= e($g['username']) ?></a> · <?= e(date('M j', strtotime((string)$g['date_from']))) ?>–<?= e(date('M j', strtotime((string)$g['date_to']))) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <a class="btn btn-ghost btn-sm btn-block" style="margin-top:10px" href="<?= e(url('going')) ?>">See who's going</a>
      </div></div>

      <div class="card" style="margin-top:18px"><div class="card-body">
        <h3>Meetups here</h3>
        <?php if (!$meetups): ?><p class="muted">No public meetups yet.</p><?php endif; ?>
        <ul class="list-plain">
          <?php foreach ($meetups as $m): ?><li style="padding:6px 0"><a href="<?= e(url('meetup/'.$m['id'])) ?>"><?= e($m['title']) ?></a><br><span class="hint"><?= e(date('M j, g:ia', strtotime((string)$m['date_start']))) ?></span></li><?php endforeach; ?>
        </ul>
      </div></div>

      <div style="margin-top:18px;display:grid;gap:8px">
        <a class="btn btn-primary btn-block" href="<?= e(url('trip/new')) ?>">Share a trip here</a>
        <a class="btn btn-ghost btn-block" href="<?= e(url('review/new')) ?>">Write a review</a>
      </div>
    </aside>
  </div>
</div>
