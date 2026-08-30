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
          <img class="avatar" style="width:48px;height:48px" src="<?= e(avatar_url($pp['avatar_url']??null)) ?>" alt="">
        <?php endif; ?>
        <div style="flex:1;min-width:0">
          <h2 style="font-size:1.05rem;margin:0">
            <a href="<?= e(url('u/'.$pp['username'])) ?>"><?= e($pp['display_name'] ?: $pp['username']) ?></a>
          </h2>
          <?php /* What they have written, where we know it. Following exists here so that people
                   whose reviews you trust show up in your feed, and a list where a traveler with
                   forty reviews looks identical to one with none gives you no way to act on that.
                   Shown only when there is something to show -- "0 reviews" beside a name is a
                   judgement nobody asked for. */ ?>
          <p class="muted" style="margin:.1rem 0 0">@<?= e($pp['username']) ?><?php
            if ($pp['home_city']): ?> · <?= e($pp['home_city']) ?><?php endif; ?><?php
            if ((int) ($pp['review_count'] ?? 0) > 0): ?> · <b><?= (int) $pp['review_count'] ?></b>
              <?= (int) $pp['review_count'] === 1 ? 'review' : 'reviews' ?><?php endif; ?></p>
          <?php if ($pp['bio']): ?>
            <p style="margin:.4rem 0 0"><?= e(excerpt((string) $pp['bio'], 140)) ?></p>
          <?php endif; ?>
        </div>
      </div></article>
    <?php endforeach; ?>
  </div>
</div></section>
