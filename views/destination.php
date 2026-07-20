<?php /** @var array $d @var array $trips @var array $reviews @var array $editorial @var array $tips @var array $guides @var array $meetups @var array $going @var array $avg */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> / <?= e($d['name']) ?></p>
  <div class="dest-hero">
    <img src="<?= e($d['hero_url']) ?>" alt="<?= e($d['name'].', '.$d['country']) ?>">
    <div class="overlay">
      <div>
        <span class="chip"><?= e($d['category']) ?></span>
        <h1><?= e($d['name']) ?>, <?= e($d['country']) ?></h1>
        <p style="color:#e8eef5;margin:.2rem 0 0;max-width:60ch"><?= e($d['summary']) ?></p>
      </div>
    </div>
  </div>
  <?= rmt_photo_credit_html($d) ?>

  <?php /* Two ratings, never blended. The community score is what travelers said; the editorial
            score is the site's own research-based assessment and is labelled as such. */ ?>
  <div class="card" style="margin-top:18px"><div class="card-body">
    <div class="rating-split">
      <div class="rs-item">
        <p class="rs-label">Community rating</p>
        <?php if ((int)$avg['c'] > 0): ?>
          <p class="rs-value"><span class="stars"><?= stars((int)round((float)$avg['a'])) ?></span> <?= e((string)$avg['a']) ?><span class="muted" style="font-weight:400"> from <?= (int)$avg['c'] ?> traveler <?= (int)$avg['c'] === 1 ? 'review' : 'reviews' ?></span></p>
        <?php else: ?>
          <p class="rs-value muted" style="font-weight:600">No traveler reviews yet</p>
          <p class="hint" style="margin:0">This score stays empty until real travelers post. We do not fill it in ourselves.</p>
        <?php endif; ?>
      </div>
      <?php foreach ($editorial as $ed): ?>
        <div class="rs-item">
          <p class="rs-label"><?= rmt_editorial_badge('review') ?> rating</p>
          <p class="rs-value"><span class="stars"><?= stars((int)$ed['rating']) ?></span> <?= (int)$ed['rating'] ?>/5</p>
          <?php if ($ed['safety_rating'] || $ed['value_rating']): ?>
            <p class="hint" style="margin:0">
              <?php if ($ed['safety_rating']): ?>Safety <?= (int)$ed['safety_rating'] ?>/5<?php endif; ?>
              <?php if ($ed['safety_rating'] && $ed['value_rating']): ?> · <?php endif; ?>
              <?php if ($ed['value_rating']): ?>Value <?= (int)$ed['value_rating'] ?>/5<?php endif; ?>
            </p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div></div>

  <div class="grid g-2" style="margin-top:26px;align-items:start">
    <div>
      <?php foreach ($editorial as $ed): ?>
        <div class="card ed-panel" style="margin-bottom:18px"><div class="card-body">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <?= rmt_editorial_badge('review') ?>
            <span class="stars"><?= stars((int)$ed['rating']) ?></span>
          </div>
          <h2 style="margin:.5rem 0 .2rem;font-size:1.25rem">
            <a href="<?= e(url('review/'.(int)$ed['id'].'/'.($ed['slug'] ?: rmt_review_slug($ed)))) ?>"><?= e($ed['title'] ?: $ed['subject_name']) ?></a>
          </h2>
          <p class="muted" style="margin:0">By <?= e(rmt_editorial_name()) ?></p>
          <p style="margin:.7rem 0 0"><?= e(mb_strimwidth((string)$ed['body'], 0, 420, '…')) ?></p>
          <?php if ($ed['what_ruined']): ?>
            <p style="margin:.7rem 0 0;font-size:.95rem"><b style="color:#b42318">What nearly ruins it:</b> <?= e(mb_strimwidth((string)$ed['what_ruined'], 0, 180, '…')) ?></p>
          <?php endif; ?>
          <p style="margin:.9rem 0 0"><a class="btn btn-ghost btn-sm" href="<?= e(url('review/'.(int)$ed['id'].'/'.($ed['slug'] ?: rmt_review_slug($ed)))) ?>">Read the full editorial review</a></p>
          <p class="ed-note"><?= e(rmt_editorial_disclosure()) ?></p>
        </div></div>
      <?php endforeach; ?>

      <?php if ($tips): ?>
        <div class="card" style="margin-bottom:18px"><div class="card-body">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <h2 style="margin:0;font-size:1.15rem">Practical tips</h2>
            <?= rmt_editorial_badge() ?>
          </div>
          <ul class="tips-list" style="margin-top:12px">
            <?php foreach ($tips as $t): ?><li><?= e($t['body']) ?></li><?php endforeach; ?>
          </ul>
        </div></div>
      <?php endif; ?>

      <div class="section-rule">
        <h2>Traveler reviews</h2>
        <span class="count"><?= count($reviews) ?></span>
      </div>
      <?php if (!$reviews): ?>
        <div class="empty-cta">
          <h3>Be the first traveler to review <?= e($d['name']) ?></h3>
          <p class="muted" style="margin:0">The editorial review above is desk research. What this page actually needs is somebody who went. If that is you, the honest version, including the part that went wrong, is worth more here than a polished one.</p>
          <ol class="empty-steps">
            <li>Rate it out of five, plus safety and value.</li>
            <li>Say what was great, in specifics.</li>
            <li>Say what nearly ruined the trip. This field is required.</li>
          </ol>
          <p style="margin:16px 0 0">
            <a class="btn btn-accent" href="<?= e(url('review/new')) ?>">Share your experience</a>
            <a class="btn btn-ghost" href="<?= e(url('invite')) ?>">Invite someone who has been</a>
          </p>
        </div>
      <?php endif; ?>
      <?php foreach ($reviews as $r): ?>
        <div class="card" style="margin-bottom:14px"><div class="card-body">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span class="stars"><?= stars((int)$r['rating']) ?></span>
            <?php if (show_verified($r)): ?><span class="verified">Verified</span><?php endif; ?>
          </div>
          <h3 style="margin:.3rem 0 .1rem;font-size:1.1rem">
            <a href="<?= e(url('review/'.(int)$r['id'].'/'.($r['slug'] ?: rmt_review_slug($r)))) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a>
          </h3>
          <p class="muted" style="margin:0"><?= e($r['subject_name']) ?> · <span style="text-transform:capitalize"><?= e($r['subject_type']) ?></span> · @<?= e($r['author']['username']??'') ?><?php if ($r['visited_on']): ?> · visited <?= e(date('M Y', strtotime((string)$r['visited_on']))) ?><?php endif; ?></p>
          <p style="margin:.5rem 0 0"><?= e(mb_strimwidth((string)$r['body'], 0, 240, '…')) ?></p>
          <?php if ($r['what_ruined']): ?>
            <p class="muted" style="margin:.5rem 0 0;font-size:.92rem"><b style="color:#b42318">Nearly ruined it:</b> <?= e(mb_strimwidth((string)$r['what_ruined'], 0, 120, '…')) ?></p>
          <?php endif; ?>
        </div></div>
      <?php endforeach; ?>

      <div class="section-rule">
        <h2>Trip stories</h2>
        <span class="count"><?= count($trips) ?></span>
      </div>
      <?php if (!$trips): ?>
        <p class="muted">No trip stories from <?= e($d['name']) ?> yet. <a href="<?= e(url('trip/new')) ?>">Share the first one.</a></p>
      <?php endif; ?>
      <div class="grid" style="gap:16px">
        <?php foreach ($trips as $t): ?>
          <article class="card"><a href="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>">
            <img class="card-media" loading="lazy" src="<?= e($t['cover_url']) ?>" alt="<?= e($t['title']) ?>">
            <div class="card-body"><h3><?= e($t['title']) ?></h3>
              <div class="meta-row"><img class="avatar" src="<?= e($t['author']['avatar_url']??'') ?>" alt="">@<?= e($t['author']['username']??'') ?>
              <?php if (show_verified($t)): ?><span class="verified">Verified visit</span><?php endif; ?></div>
            </div></a></article>
        <?php endforeach; ?>
      </div>
    </div>

    <aside>
      <div class="card"><div class="card-body">
        <h3>Guides &amp; itineraries</h3>
        <?php if (!$guides): ?><p class="muted">No guides yet.</p><?php endif; ?>
        <ul class="list-plain">
          <?php foreach ($guides as $g): ?>
            <li style="padding:8px 0;border-bottom:1px solid var(--line)">
              <a href="<?= e(url('g/'.$g['slug'])) ?>"><?= e($g['title']) ?></a>
              <?php if (rmt_is_editorial($g)): ?><br><?= rmt_editorial_badge() ?><?php endif; ?>
            </li>
          <?php endforeach; ?>
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
        <a class="btn btn-accent btn-block" href="<?= e(url('review/new')) ?>">Share your experience</a>
        <a class="btn btn-primary btn-block" href="<?= e(url('trip/new')) ?>">Share a trip here</a>
        <a class="btn btn-ghost btn-block" href="<?= e(url('invite')) ?>">Invite a traveler</a>
      </div>
    </aside>
  </div>
</div>
