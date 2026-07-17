<?php /** @var array $u @var array $trips @var array $reviews @var array $guides @var int $followers @var int $following @var bool $is_following @var ?array $me */ ?>
<div class="wrap">
  <div class="profile-cover" style="<?= $u['cover_url']?'background-image:url(\''.e($u['cover_url']).'\')':'' ?>"></div>
  <div class="profile-head">
    <img class="avatar-lg" src="<?= e($u['avatar_url'] ?: url('assets/img/og-default.svg')) ?>" alt="<?= e($u['username']) ?>">
    <div style="flex:1;min-width:220px">
      <h1 style="margin:0"><?= e($u['display_name'] ?: $u['username']) ?>
        <?php if (in_array($u['role'],['admin','mod'],true)): ?><span class="chip" style="background:#eef;color:#334">Team</span>
        <?php elseif ($u['role']==='creator'): ?><span class="chip" style="background:#fef3c7;color:#92400e">Creator</span><?php endif; ?>
      </h1>
      <p class="muted" style="margin:.1rem 0">@<?= e($u['username']) ?> <?= $u['home_city']?' · '.e($u['home_city']):'' ?></p>
      <div class="stat-inline">
        <span><b><?= $followers ?></b> followers</span>
        <span><b><?= $following ?></b> following</span>
        <span><b><?= (int)$u['credibility_score'] ?></b> credibility</span>
      </div>
    </div>
    <div>
      <?php if ($me && (int)$me['id']===(int)$u['id']): ?>
        <a class="btn btn-ghost" href="<?= e(url('settings')) ?>">Edit profile</a>
      <?php elseif ($me): ?>
        <form class="inline-form" method="post" action="<?= e(url('follow')) ?>">
          <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <input type="hidden" name="return" value="<?= e(url('u/'.$u['username'])) ?>">
          <button class="btn <?= $is_following?'btn-ghost':'btn-primary' ?>"><?= $is_following?'Following':'Follow' ?></button>
        </form>
      <?php else: ?>
        <a class="btn btn-primary" href="<?= e(url('login')) ?>">Follow</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($u['bio']): ?><p style="max-width:70ch;margin:18px 0"><?= e($u['bio']) ?></p><?php endif; ?>

  <h2 style="margin-top:24px">Trips</h2>
  <?php if (!$trips): ?><p class="muted">No trips shared yet.</p><?php endif; ?>
  <div class="grid g-3">
    <?php foreach ($trips as $t): ?>
      <article class="card"><a href="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e($t['cover_url']) ?>" alt="<?= e($t['title']) ?>">
        <div class="card-body"><?php if($t['dest_name']):?><span class="chip"><?= e($t['dest_name']) ?></span><?php endif;?><h3 style="font-size:1.05rem"><?= e($t['title']) ?></h3></div></a></article>
    <?php endforeach; ?>
  </div>

  <?php if ($guides): ?><h2 style="margin-top:30px">Guides</h2>
  <div class="grid g-3"><?php foreach ($guides as $g): ?>
    <article class="card"><a href="<?= e(url('g/'.$g['slug'])) ?>"><img class="card-media" loading="lazy" src="<?= e($g['cover_url']) ?>" alt=""><div class="card-body"><h3 style="font-size:1.05rem"><?= e($g['title']) ?></h3></div></a></article>
  <?php endforeach; ?></div><?php endif; ?>

  <?php if ($reviews): ?><h2 style="margin-top:30px">Reviews</h2>
  <?php foreach ($reviews as $r): ?><div class="card" style="margin-bottom:12px"><div class="card-body">
    <span class="stars"><?= stars((int)$r['rating']) ?></span> <b><?= e($r['title']) ?></b>
    <p class="muted" style="margin:.2rem 0 0"><?= e($r['subject_name']) ?></p>
    <p style="margin:.4rem 0 0"><?= e($r['body']) ?></p>
  </div></div><?php endforeach; ?><?php endif; ?>
  <div style="height:40px"></div>
</div>
