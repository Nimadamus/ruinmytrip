<?php /** @var array $u @var array $trips @var array $reviews @var array $guides @var array $collections @var int $followers @var int $following @var bool $is_following @var ?array $me @var array $stats @var array $badges @var bool $isMe @var array $compliments @var array $myCompliments @var bool $is_blocked @var bool $i_blocked_them @var array $wishlist */ ?>
<div class="wrap">
  <div class="profile-cover" style="<?= $u['cover_url']?'background-image:url(\''.e($u['cover_url']).'\')':'' ?>"></div>
  <div class="profile-head">
    <img class="avatar-lg" src="<?= e(avatar_url($u['avatar_url'])) ?>" alt="<?= e($u['username']) ?>">
    <div style="flex:1;min-width:220px">
      <h1 style="margin:0"><?= e($u['display_name'] ?: $u['username']) ?>
        <?php if (rmt_is_editorial($u)): ?><?= rmt_editorial_badge() ?>
        <?php elseif (in_array($u['role'],['admin','mod'],true)): ?><span class="chip" style="background:#eef;color:#334">Team</span>
        <?php elseif ($u['role']==='creator'): ?><span class="chip" style="background:#fef3c7;color:#92400e">Creator</span><?php endif; ?>
      </h1>
      <p class="muted" style="margin:.1rem 0">@<?= e($u['username']) ?> <?= $u['home_city']?' · '.e($u['home_city']):'' ?></p>
      <div class="stat-inline">
        <?php /* Every figure is a live COUNT (see rmt_profile_stats) — no stored counters. */ ?>
        <span><b><?= (int)$stats['reviews'] ?></b> <?= $stats['reviews'] === 1 ? 'review' : 'reviews' ?></span>
        <span><b><?= (int)$stats['photos'] ?></b> <?= $stats['photos'] === 1 ? 'photo' : 'photos' ?></span>
        <span><b><?= (int)$stats['places'] ?></b> <?= $stats['places'] === 1 ? 'place visited' : 'places visited' ?></span>
        <span title="Useful + funny + cool votes from other travelers"><b><?= (int)$stats['votes'] ?></b> <?= $stats['votes'] === 1 ? 'vote' : 'votes' ?></span>
        <a href="<?= e(url('u/'.$u['username'].'/followers')) ?>"><b><?= $followers ?></b> <?= $followers === 1 ? 'follower' : 'followers' ?></a>
        <a href="<?= e(url('u/'.$u['username'].'/following')) ?>"><b><?= $following ?></b> following</a>
      </div>
      <?php if ($badges): ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
          <?php foreach ($badges as $b): ?>
            <span class="chip" style="background:#0f766e;color:#fff" title="<?= e($b['description']) ?>">
              <?= e($b['icon']) ?> <?= e($b['name']) ?>
            </span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div>
      <?php if ($me && (int)$me['id']===(int)$u['id']): ?>
        <a class="btn btn-ghost" href="<?= e(url('u/'.$u['username'].'/edit')) ?>">Edit profile</a>
      <?php elseif ($me && $i_blocked_them): ?>
        <form class="inline-form" method="post" action="<?= e(url('unblock')) ?>">
          <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <input type="hidden" name="return" value="<?= e(url('u/'.$u['username'])) ?>">
          <button class="btn btn-ghost" style="color:#b42318">Unblock</button>
        </form>
      <?php elseif ($me && $is_blocked): ?>
        <?php /* They blocked me; nothing to offer here. */ ?>
      <?php elseif ($me): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
          <form class="inline-form" method="post" action="<?= e(url('follow')) ?>">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <input type="hidden" name="return" value="<?= e(url('u/'.$u['username'])) ?>">
            <button class="btn <?= $is_following?'btn-ghost':'btn-primary' ?>"><?= $is_following?'Following':'Follow' ?></button>
          </form>
          <a class="btn btn-ghost" href="<?= e(url('messages/'.$u['username'])) ?>">Message</a>
          <a class="btn btn-ghost" href="<?= e(url('report?target_type=user&target_id='.(int)$u['id'])) ?>">⚑ Report</a>
          <form class="inline-form" method="post" action="<?= e(url('block')) ?>" onsubmit="return confirm('Block @<?= e($u['username']) ?>? They will no longer be able to message or follow you.');">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <input type="hidden" name="return" value="<?= e(url('u/'.$u['username'])) ?>">
            <button class="btn btn-ghost" style="color:#b42318">Block</button>
          </form>
        </div>
      <?php else: ?>
        <a class="btn btn-primary" href="<?= e(url('login')) ?>">Follow</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($u['bio']): ?><p style="max-width:70ch;margin:18px 0"><?= e($u['bio']) ?></p><?php endif; ?>

  <?php if ($compliments || ($me && !$isMe)): ?>
    <div class="card" style="margin:18px 0"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 8px">Compliments</p>
      <?php if (!$compliments): ?><p class="muted" style="margin:0 0 10px">No compliments yet.</p><?php endif; ?>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach ($compliments as $c): ?>
          <span class="chip" title="<?= (int)$c['c'] ?>"><?= e(RMT_COMPLIMENT_TYPES[$c['type']] ?? $c['type']) ?> · <?= (int)$c['c'] ?></span>
        <?php endforeach; ?>
      </div>
      <?php if ($me && !$isMe && !$is_blocked): ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">
          <?php foreach (RMT_COMPLIMENT_TYPES as $slug=>$label): $sent = in_array($slug, $myCompliments, true); ?>
            <?php if ($sent): ?>
              <span class="chip" style="background:#0f766e;color:#fff">Sent: <?= e($label) ?></span>
            <?php else: ?>
              <form class="inline-form" method="post" action="<?= e(url('compliment')) ?>">
                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="type" value="<?= e($slug) ?>">
                <input type="hidden" name="return" value="<?= e(url('u/'.$u['username'])) ?>">
                <button class="btn btn-ghost btn-sm">+ <?= e($label) ?></button>
              </form>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div></div>
  <?php endif; ?>

  <?php if ($wishlist): ?>
    <div class="card" style="margin:18px 0"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 8px">Want to visit</p>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach ($wishlist as $w): ?>
          <a class="chip" href="<?= e(url('d/'.$w['slug'])) ?>"><?= e($w['name']) ?>, <?= e($w['country']) ?></a>
        <?php endforeach; ?>
      </div>
    </div></div>
  <?php endif; ?>

  <h2 style="margin-top:24px">Trips</h2>
  <?php if (!$trips): ?><p class="muted">No trips shared yet.</p><?php endif; ?>
  <div class="grid g-3">
    <?php foreach ($trips as $t): ?>
      <article class="card"><a href="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e(abs_url($t['cover_url'])) ?>" alt="<?= e($t['title']) ?>">
        <div class="card-body"><?php if($t['dest_name']):?><span class="chip"><?= e($t['dest_name']) ?></span><?php endif;?><h3 style="font-size:1.05rem"><?= e($t['title']) ?></h3></div></a></article>
    <?php endforeach; ?>
  </div>

  <?php if ($guides): ?><h2 style="margin-top:30px">Guides</h2>
  <div class="grid g-3"><?php foreach ($guides as $g): ?>
    <article class="card"><a href="<?= e(url('g/'.$g['slug'])) ?>"><img class="card-media" loading="lazy" src="<?= e(abs_url($g['cover_url'])) ?>" alt=""><div class="card-body"><h3 style="font-size:1.05rem"><?= e($g['title']) ?></h3></div></a></article>
  <?php endforeach; ?></div><?php endif; ?>

  <?php if ($collections): ?><h2 style="margin-top:30px">Collections</h2>
  <div class="grid g-3"><?php foreach ($collections as $c): ?>
    <article class="card"><a href="<?= e(url('c/'.$c['slug'])) ?>"><div class="card-body">
      <h3 style="font-size:1.05rem"><?= e($c['title']) ?></h3>
      <p class="muted" style="margin:.3rem 0 0"><?= (int)$c['item_count'] ?> <?= (int)$c['item_count']===1?'destination':'destinations' ?></p>
    </div></a></article>
  <?php endforeach; ?></div><?php endif; ?>

  <?php if ($reviews): ?><h2 style="margin-top:30px">Reviews <span class="muted" style="font-weight:400;font-size:1rem">(<?= count($reviews) ?>)</span></h2>
  <?php foreach ($reviews as $r): ?><div class="card" style="margin-bottom:12px"><div class="card-body">
    <span class="stars"><?= stars((int)$r['rating']) ?></span>
    <b><a href="<?= e(url('review/'.(int)$r['id'].'/'.($r['slug'] ?: rmt_review_slug($r)))) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a></b>
    <p class="muted" style="margin:.2rem 0 0"><?= e($r['subject_name']) ?> · <span style="text-transform:capitalize"><?= e($r['subject_type']) ?></span><?php if ($r['visited_on']): ?> · visited <?= e(date('M Y', strtotime((string)$r['visited_on']))) ?><?php endif; ?></p>
    <p style="margin:.4rem 0 0"><?= e(mb_strimwidth((string)$r['body'], 0, 200, '…')) ?></p>
  </div></div><?php endforeach; ?><?php endif; ?>
  <?php if ($isMe && !$reviews && !$trips): ?>
    <div class="callout" style="margin-top:24px">
      Your profile is empty. <a href="<?= e(url('review/new')) ?>">Write your first review</a> and it will show up here.
    </div>
  <?php endif; ?>
  <div style="height:40px"></div>
</div>
