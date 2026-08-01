<?php /** @var array $rows */ ?>
<div class="wrap" style="max-width:680px;min-height:50vh">
  <h1 style="margin-top:24px">Messages</h1>
  <?php if (!$rows): ?>
    <div class="empty-cta" style="margin-top:16px">
      <h3>No conversations yet.</h3>
      <p class="muted" style="margin:0">Visit a traveler's profile and send them a message to start one.</p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url('explore')) ?>">Explore destinations</a></p>
    </div>
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
