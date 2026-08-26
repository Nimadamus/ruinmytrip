<?php /** @var array $d @var array $trips @var array $reviews @var array $editorial @var array $tips @var array $guides @var array $meetups @var array $going @var array $avg @var array $avgByCategory @var ?array $me @var bool $saved @var int $wantCount @var array $photos @var int $photoCount */ // reviews/editorial rows also carry 'useful_count' ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> / <?= e($d['name']) ?></p>
  <div class="dest-hero">
    <img src="<?= e(abs_url($d['hero_url'])) ?>" alt="<?= e($d['name'].', '.$d['country']) ?>">
    <div class="overlay">
      <div>
        <span class="chip"><?= e($d['category']) ?></span>
        <h1><?= e($d['name']) ?>, <?= e($d['country']) ?></h1>
        <p style="color:#e8eef5;margin:.2rem 0 0;max-width:60ch"><?= e($d['summary']) ?></p>
      </div>
      <div style="margin-left:auto;text-align:right">
        <?php if ($me): ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
          <form method="post" action="<?= e(url('destination/been')) ?>">
            <?= csrf_field() ?><input type="hidden" name="destination_id" value="<?= (int)$d['id'] ?>">
            <input type="hidden" name="return" value="<?= e(url('d/'.$d['slug'])) ?>">
            <button class="btn <?= !empty($been) ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= !empty($been) ? "Been ✓" : "I've been" ?></button>
          </form>
          <form method="post" action="<?= e(url('destination/save')) ?>">
            <?= csrf_field() ?><input type="hidden" name="destination_id" value="<?= (int)$d['id'] ?>">
            <input type="hidden" name="return" value="<?= e(url('d/'.$d['slug'])) ?>">
            <button class="btn <?= $saved ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $saved ? '★ Saved' : '☆ Want to visit' ?></button>
          </form>
          </div>
        <?php else: ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('register')) ?>">Join to mark been / want</a>
        <?php endif; ?>
        <?php if ($wantCount > 0 || !empty($beenCount)): ?>
          <p class="hint" style="color:#e8eef5;margin:.4rem 0 0">
            <?php if (!empty($beenCount)): ?><?= (int)$beenCount ?> been here<?php endif; ?>
            <?php if (!empty($beenCount) && $wantCount > 0): ?> · <?php endif; ?>
            <?php if ($wantCount > 0): ?><?= $wantCount ?> <?= $wantCount === 1 ? 'wants' : 'want' ?> to visit<?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?= rmt_photo_credit_html($d) ?>

  <?php if (!empty($relatedPosts)): ?>
    <div class="callout" style="margin-top:16px">
      <p style="margin:0 0 8px"><b>2026 costs for <?= e($d['name']) ?></b></p>
      <ul style="margin:0;padding-left:1.2em">
        <?php foreach ($relatedPosts as $rp): ?>
          <li><a href="<?= e(url('blog/'.$rp['slug'])) ?>"><?= e($rp['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php /* Two ratings, never blended. The community score is what travelers said; the editorial
            score is the site's own research-based assessment and is labelled as such. */ ?>
  <div class="card" style="margin-top:18px"><div class="card-body">
    <div class="rating-split">
      <div class="rs-item">
        <p class="rs-label">Community rating</p>
        <?php if ((int)$avg['c'] > 0): ?>
          <p class="rs-value"><span class="stars"><?= stars((int)round((float)$avg['a'])) ?></span> <?= e((string)$avg['a']) ?><span class="muted" style="font-weight:400"> from <?= (int)$avg['c'] ?> traveler <?= (int)$avg['c'] === 1 ? 'review' : 'reviews' ?></span></p>
          <?php if ($avg['safety_c'] > 0 || $avg['value_c'] > 0): ?>
            <p class="hint" style="margin:0">
              <?php if ($avg['safety_c'] > 0): ?>Safety <?= e((string)$avg['safety_a']) ?>/5 <span class="muted">(<?= $avg['safety_c'] ?>)</span><?php endif; ?>
              <?php if ($avg['safety_c'] > 0 && $avg['value_c'] > 0): ?> · <?php endif; ?>
              <?php if ($avg['value_c'] > 0): ?>Value <?= e((string)$avg['value_a']) ?>/5 <span class="muted">(<?= $avg['value_c'] ?>)</span><?php endif; ?>
            </p>
          <?php endif; ?>
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

  <?php $rmt_catBreakdown = array_filter($avgByCategory, fn($c) => $c['subject_type'] !== 'destination'); ?>
  <?php if ($rmt_catBreakdown): ?>
    <div class="card" style="margin-top:12px"><div class="card-body">
      <p class="rs-label" style="margin:0 0 8px">By what travelers actually reviewed</p>
      <div style="display:flex;gap:18px;flex-wrap:wrap">
        <?php foreach ($rmt_catBreakdown as $cat): ?>
          <div>
            <p class="muted" style="margin:0;text-transform:capitalize"><?= e($cat['subject_type']) ?>s</p>
            <p style="margin:0"><span class="stars" style="font-size:.9rem"><?= stars((int)round((float)$cat['a'])) ?></span> <?= e((string)$cat['a']) ?> <span class="muted">(<?= (int)$cat['c'] ?>)</span></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div></div>
  <?php endif; ?>

  <?php if (!empty($beenPeople) || !empty($wantPeople)): ?>
    <div class="card" style="margin-top:12px"><div class="card-body">
      <?php if (!empty($beenPeople)): ?>
        <p class="rs-label" style="margin:0 0 8px">Been here</p>
        <div class="meta-row" style="flex-wrap:wrap;margin:0 0 10px">
          <?php foreach ($beenPeople as $bp): ?>
            <a href="<?= e(url('u/'.$bp['username'])) ?>" title="@<?= e($bp['username']) ?>"><img class="avatar" src="<?= e(avatar_url($bp['avatar_url']??null)) ?>" alt=""></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($wantPeople)): ?>
        <p class="rs-label" style="margin:0 0 8px">Want to visit</p>
        <div class="meta-row" style="flex-wrap:wrap;margin:0">
          <?php foreach ($wantPeople as $wp): ?>
            <a href="<?= e(url('u/'.$wp['username'])) ?>" title="@<?= e($wp['username']) ?>"><img class="avatar" src="<?= e(avatar_url($wp['avatar_url']??null)) ?>" alt=""></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div></div>
  <?php endif; ?>

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

      <?php /* Places only exist because somebody reviewed one, so this whole block stays hidden
               until there is something real in it rather than showing an empty shell. */ ?>
      <?php if ($topPlaces): ?>
        <div class="section-rule">
          <h2>Reviewed places</h2>
          <span class="count"><?= (int)$placeCount ?></span>
        </div>
        <div class="grid" style="gap:10px;margin-bottom:10px">
          <?php foreach ($topPlaces as $tp): $tc = (int)$tp['review_count']; ?>
            <div class="card"><div class="card-body">
              <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px">
                <span>
                  <a href="<?= e(url('p/'.$tp['slug'])) ?>"><strong><?= e($tp['name']) ?></strong></a>
                  <span class="muted" style="text-transform:capitalize"> · <?= e(rmt_place_type_label((string)$tp['type'])) ?></span>
                </span>
                <span class="muted" style="white-space:nowrap">
                  <?php if ($tc > 0): ?>
                    <span class="stars"><?= stars((int) round((float)$tp['avg_rating'])) ?></span>
                    <?= e((string)$tp['avg_rating']) ?>/5 · <?= $tc ?>
                  <?php elseif ((int)$tp['editorial_count'] > 0): ?>
                    <?= rmt_editorial_badge('review') ?>
                  <?php else: ?>
                    No reviews yet
                  <?php endif; ?>
                </span>
              </div>
              <?php if (!empty($tp['snippet'])): ?>
                <p class="muted" style="margin:.35rem 0 0;font-size:.93rem"><?= e((string)$tp['snippet']) ?></p>
              <?php endif; ?>
            </div></div>
          <?php endforeach; ?>
        </div>
        <?php if ($placeCount > count($topPlaces)): ?>
          <p style="margin:0 0 26px"><a href="<?= e(url('d/'.$d['slug'].'/places')) ?>">See all <?= (int)$placeCount ?> places →</a></p>
        <?php else: ?>
          <p style="margin:0 0 26px"><a href="<?= e(url('d/'.$d['slug'].'/places')) ?>">Browse places by type →</a></p>
        <?php endif; ?>
      <?php endif; ?>

      <div class="section-rule">
        <h2>Traveler reviews</h2>
        <span class="count"><?= (int)$avg['c'] ?></span>
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
            <a class="btn btn-accent" href="<?= e(url('review/new?destination='.(int)$d['id'])) ?>">Share your experience</a>
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
          <p class="muted" style="margin:0"><?= e($r['subject_name']) ?> · <span style="text-transform:capitalize"><?= e($r['subject_type']) ?></span> · @<?= e($r['author']['username']??'') ?><?php if ($r['visited_on']): ?> · visited <?= e(date('M Y', strtotime((string)$r['visited_on']))) ?><?php endif; ?><?php if (!empty($r['useful_count'])): ?> · 👍 <?= (int)$r['useful_count'] ?><?php endif; ?><?php if (rmt_review_is_stale($r)): ?> · ⏳<?php endif; ?></p>
          <p style="margin:.5rem 0 0"><?= e(mb_strimwidth((string)$r['body'], 0, 240, '…')) ?></p>
          <?php if ($r['what_ruined']): ?>
            <p class="muted" style="margin:.5rem 0 0;font-size:.92rem"><b style="color:#b42318">Nearly ruined it:</b> <?= e(mb_strimwidth((string)$r['what_ruined'], 0, 120, '…')) ?></p>
          <?php endif; ?>
        </div></div>
      <?php endforeach; ?>

      <?php if ($photos): ?>
        <div class="section-rule">
          <h2>Photos</h2>
          <span class="count"><?= $photoCount ?></span>
        </div>
        <div class="grid g-4" style="gap:8px;margin-bottom:24px">
          <?php foreach ($photos as $p): ?>
            <a href="<?= e($p['kind']==='trip' ? url('trip/'.$p['parent_id'].'/'.$p['parent_slug']) : url('review/'.$p['parent_id'].($p['parent_slug'] ? '/'.$p['parent_slug'] : ''))) ?>">
              <img class="card-media" loading="lazy" style="aspect-ratio:1;object-fit:cover" src="<?= e(abs_url($p['url'])) ?>" alt="<?= e($p['caption'] ?: $d['name']) ?>">
            </a>
          <?php endforeach; ?>
        </div>
        <?php if ($photoCount > count($photos)): ?>
          <p style="margin:0 0 26px"><a href="<?= e(url('d/'.$d['slug'].'/photos')) ?>">See all <?= $photoCount ?> photos →</a></p>
        <?php endif; ?>
      <?php endif; ?>

      <div class="section-rule">
        <h2>Trip stories</h2>
        <span class="count"><?= $tripCount ?></span>
      </div>
      <?php if (!$trips): ?>
        <p class="muted">No trip stories from <?= e($d['name']) ?> yet. <a href="<?= e(url('trip/new')) ?>">Share the first one.</a></p>
      <?php endif; ?>
      <div class="grid" style="gap:16px">
        <?php foreach ($trips as $t): ?>
          <article class="card"><a href="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>">
            <img class="card-media" loading="lazy" src="<?= e(abs_url($t['cover_url'])) ?>" alt="<?= e($t['title']) ?>">
            <div class="card-body"><h3><?= e($t['title']) ?></h3>
              <div class="meta-row"><img class="avatar" src="<?= e(avatar_url($t['author']['avatar_url']??null)) ?>" alt="">@<?= e($t['author']['username']??'') ?>
              <?php if (show_verified($t)): ?><span class="verified">Verified visit</span><?php endif; ?></div>
            </div></a></article>
        <?php endforeach; ?>
      </div>

      <p style="margin:18px 0 0">
        <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e(url('d/'.$d['slug'])) ?>">Copy link</button>
      </p>

      <?php
        $targetType = 'destination'; $targetId = (int)$d['id']; $ownerId = 0;
        $returnUrl = url('d/'.$d['slug']);
        $likeCount = 0; $saveCount = 0; $liked = false; $saved = $saved ?? false;
        $showActionsBar = false;
        include __DIR__ . '/_engagement.php';
      ?>
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
        <?php if (!$going): ?><p class="muted">No travelers listed yet. Be the first.</p><?php endif; ?>
        <ul class="list-plain">
          <?php foreach ($going as $g): ?>
            <li class="meta-row" style="justify-content:flex-start">
              <img class="avatar" src="<?= e(avatar_url($g['avatar_url']??null)) ?>" alt="">
              <span><a href="<?= e(url('u/'.$g['username'])) ?>">@<?= e($g['username']) ?></a> · <?= e(date('M j', strtotime((string)$g['date_from']))) ?>–<?= e(date('M j', strtotime((string)$g['date_to']))) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($me): ?>
          <?php $dests = [['id'=>$d['id'],'name'=>$d['name'],'country'=>$d['country']]]; $current = $myGoing; $lockDestId = (int)$d['id']; include __DIR__.'/_going_form.php'; ?>
        <?php else: ?>
          <a class="btn btn-accent btn-sm btn-block" style="margin-top:10px" href="<?= e(url('register')) ?>">Join to share dates</a>
        <?php endif; ?>
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
        <a class="btn btn-accent btn-block" href="<?= e(url('review/new?destination='.(int)$d['id'])) ?>">Share your experience</a>
        <a class="btn btn-primary btn-block" href="<?= e(url('trip/new')) ?>">Share a trip here</a>
      </div>
    </aside>
  </div>
</div>
