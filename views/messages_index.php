<?php /** @var array $rows */ ?>
<div class="wrap" style="max-width:680px;min-height:50vh">
  <h1 style="margin-top:24px">Messages</h1>
  <?php if (!$rows): ?>
    <?php $emptyTitle = 'No conversations yet';
          $emptyWhy = 'Messages start from somebody\'s profile, or from a match. The people below are real members, and the cities are ones somebody has actually posted dates for.';
          $emptyCtaText = 'Find travelers'; $emptyCtaUrl = url('travelers');
          include __DIR__ . '/_nothing_yet.php'; ?>
  <?php endif; ?>
  <ul class="list-plain">
    <?php foreach ($rows as $r): ?>
      <li class="card" style="margin-bottom:8px">
        <a href="<?= e(url('messages/'.$r['username'])) ?>" style="color:inherit;text-decoration:none">
          <div class="card-body" style="padding:12px 16px;display:flex;align-items:center;gap:10px">
            <img class="avatar" src="<?= e(avatar_url($r['avatar_url'])) ?>" alt="<?= e($r['username']) ?>">
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:8px">
                <b><?= e($r['display_name'] ?: $r['username']) ?></b>
                <span class="muted">@<?= e($r['username']) ?></span>
                <?php if ((int)$r['unread'] > 0): ?><span class="chip" style="background:#0f766e;color:#fff"><?= (int)$r['unread'] ?> new</span><?php endif; ?>
              </div>
              <p class="muted" style="margin:.2rem 0 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($r['last_body'] ?? '') ?></p>
            </div>
            <span class="hint"><?= $r['last_message_at'] ? e(ago($r['last_message_at'])) : '' ?></span>
          </div>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <div style="height:40px"></div>
</div>
