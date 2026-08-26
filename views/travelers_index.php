<?php /** @var array $people @var ?array $me */ ?>
<section class="block"><div class="wrap">
  <div class="section-head">
    <div>
      <h1>Travelers</h1>
      <p class="muted">Real people on RuinMyTrip. Follow the ones whose trips and reviews you trust. We do not invent members.</p>
    </div>
    <?php if (!$me): ?>
      <a class="btn btn-accent btn-sm" href="<?= e(url('register')) ?>">Join free</a>
    <?php endif; ?>
  </div>

  <?php if (!$people): ?>
    <div class="empty-cta">
      <h3>No traveler profiles yet.</h3>
      <p class="muted" style="margin:0">The editorial account is not listed here. The first real member can be you.</p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url('register')) ?>">Create a profile</a></p>
    </div>
  <?php endif; ?>

  <div class="grid g-2" style="padding-bottom:50px">
    <?php foreach ($people as $pp): ?>
      <article class="card"><div class="card-body" style="display:flex;gap:14px;align-items:flex-start">
        <img class="avatar" style="width:56px;height:56px" src="<?= e(avatar_url($pp['avatar_url']??null)) ?>" alt="">
        <div style="flex:1;min-width:0">
          <h2 style="font-size:1.1rem;margin:0">
            <a href="<?= e(url('u/'.$pp['username'])) ?>"><?= e($pp['display_name'] ?: $pp['username']) ?></a>
          </h2>
          <p class="muted" style="margin:.1rem 0 0">@<?= e($pp['username']) ?><?php if (!empty($pp['home_city'])): ?> · <?= e($pp['home_city']) ?><?php endif; ?></p>
          <?php if (!empty($pp['bio'])): ?>
            <p style="margin:.4rem 0 0"><?= e(mb_strimwidth((string)$pp['bio'], 0, 140, '…')) ?></p>
          <?php endif; ?>
          <p class="hint" style="margin:.4rem 0 0"><?= (int)$pp['reviews'] ?> <?= (int)$pp['reviews']===1?'review':'reviews' ?> · <?= (int)$pp['trips'] ?> <?= (int)$pp['trips']===1?'trip':'trips' ?></p>
        </div>
        <?php if ($me && (int)$me['id'] !== (int)$pp['id']): ?>
          <form class="inline-form" method="post" action="<?= e(url('follow')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int)$pp['id'] ?>">
            <input type="hidden" name="return" value="<?= e(url('travelers')) ?>">
            <button class="btn btn-ghost btn-sm">Follow</button>
          </form>
        <?php endif; ?>
      </div></article>
    <?php endforeach; ?>
  </div>
</div></section>
