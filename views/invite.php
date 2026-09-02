<?php /** @var array $me @var string $link @var string $message @var int $count @var array $recent */ ?>
<div class="wrap" style="max-width:720px;min-height:50vh">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Invite</p>
  <h1 style="margin-top:8px">Bring a traveler</h1>
  <p class="muted" style="max-width:60ch">This site gets better with every person who has been somewhere and will say
    what went wrong. Your link tells us who sent them, so you hear when they arrive.</p>

  <div class="card" style="margin:18px 0"><div class="card-body">
    <label for="invite-link" class="hint">Your link</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:6px 0 0">
      <input id="invite-link" type="text" readonly value="<?= e($link) ?>" style="flex:1;min-width:240px" onclick="this.select()">
      <button type="button" class="btn btn-primary" data-copy="<?= e($link) ?>">Copy</button>
    </div>
    <?php $shareUrl = $link; $shareText = $message; include __DIR__ . '/_share.php'; ?>
    <p class="hint" style="margin:0">Or paste this: <em><?= e($message) ?></em></p>
  </div></div>

  <h2 style="font-size:1.15rem;margin:26px 0 8px">
    <?= $count === 0 ? 'Nobody yet' : $count . ($count === 1 ? ' traveler' : ' travelers') . ' joined from your link' ?>
  </h2>
  <?php if (!$recent): ?>
    <p class="muted">Send it to the one friend who always has a story about a trip that went sideways.</p>
  <?php else: ?>
    <ul class="list-plain">
      <?php foreach ($recent as $r): ?>
        <li class="card" style="margin-bottom:8px"><div class="card-body" style="padding:10px 16px;display:flex;gap:12px;align-items:center">
          <img class="avatar" style="width:32px;height:32px" src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt="">
          <a href="<?= e(url('u/'.$r['username'])) ?>"><b>@<?= e((string) $r['username']) ?></b></a>
          <span class="hint">joined <?= e(ago((string) $r['created_at'])) ?></span>
        </div></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p style="margin:26px 0 50px"><a class="btn btn-ghost" href="<?= e(url('u/'.$me['username'])) ?>">← Your profile</a></p>
</div>
