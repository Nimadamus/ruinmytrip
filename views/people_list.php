<?php /** @var array $u @var array $people @var string $mode @var ?array $me */ ?>
<section class="block"><div class="wrap" style="max-width:760px">
  <div class="section-head">
    <div>
      <h1><?= $mode === 'followers' ? 'Followers' : 'Following' ?></h1>
      <p class="muted">
        <?= $mode === 'followers'
            ? 'Travelers following @'.e($u['username']).'.'
            : 'Travelers @'.e($u['username']).' follows.' ?>
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('u/'.$u['username'])) ?>">Back to profile</a>
  </div>

  <?php if (!$people): ?>
    <p class="muted">
      <?= $mode === 'followers' ? 'No followers yet.' : 'Not following anyone yet.' ?>
    </p>
  <?php endif; ?>

  <div class="grid" style="gap:12px">
    <?php foreach ($people as $pp): ?>
      <article class="card"><div class="card-body" style="display:flex;gap:12px;align-items:flex-start">
        <?php if (!empty($pp['avatar_url'])): ?>
          <img class="avatar" style="width:48px;height:48px" src="<?= e($pp['avatar_url']) ?>" alt="">
        <?php endif; ?>
        <div style="flex:1;min-width:0">
          <h2 style="font-size:1.05rem;margin:0">
            <a href="<?= e(url('u/'.$pp['username'])) ?>"><?= e($pp['display_name'] ?: $pp['username']) ?></a>
          </h2>
          <p class="muted" style="margin:.1rem 0 0">@<?= e($pp['username']) ?><?php
            if ($pp['home_city']): ?> · <?= e($pp['home_city']) ?><?php endif; ?></p>
          <?php if ($pp['bio']): ?>
            <p style="margin:.4rem 0 0"><?= e(mb_strimwidth((string)$pp['bio'], 0, 120, '…')) ?></p>
          <?php endif; ?>
        </div>
      </div></article>
    <?php endforeach; ?>
  </div>
</div></section>
