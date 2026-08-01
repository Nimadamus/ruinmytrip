<?php /** @var array $them @var array $items @var bool $blocked */ $me = current_user(); ?>
<div class="wrap" style="max-width:680px;min-height:50vh">
  <div style="display:flex;align-items:center;gap:10px;margin-top:24px">
    <img class="avatar" src="<?= e(avatar_url($them['avatar_url'])) ?>" alt="<?= e($them['username']) ?>">
    <h1 style="margin:0;font-size:1.3rem">
      <a href="<?= e(url('u/'.$them['username'])) ?>" style="color:inherit"><?= e($them['display_name'] ?: $them['username']) ?></a>
      <span class="muted" style="font-weight:400">@<?= e($them['username']) ?></span>
    </h1>
    <div style="margin-left:auto">
      <form method="post" action="<?= e(url($blocked ? 'unblock' : 'block')) ?>" onsubmit="return confirm('<?= $blocked ? 'Unblock' : 'Block' ?> @<?= e($them['username']) ?>?');">
        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$them['id'] ?>">
        <input type="hidden" name="return" value="<?= e(url('messages/'.$them['username'])) ?>">
        <button class="btn btn-ghost btn-sm" style="color:#b42318"><?= $blocked ? 'Unblock' : 'Block' ?></button>
      </form>
    </div>
  </div>
  <p><a href="<?= e(url('messages')) ?>">&larr; All messages</a></p>

  <?php if ($blocked): ?>
    <div class="empty-cta" style="margin-top:16px">
      <p class="muted" style="margin:0">You and @<?= e($them['username']) ?> cannot message each other while a block is in place.</p>
    </div>
  <?php endif; ?>

  <div style="margin:16px 0">
    <?php foreach ($items as $m): $mine = (int)$m['sender_id'] === (int)$me['id']; ?>
      <div style="display:flex;<?= $mine ? 'justify-content:flex-end' : '' ?>;margin-bottom:8px">
        <div class="card" style="max-width:80%;<?= $mine ? 'background:#0f766e;color:#fff' : '' ?>">
          <div class="card-body" style="padding:8px 12px">
            <p style="margin:0;white-space:pre-wrap"><?= rmt_linkify_mentions(e($m['body'])) ?></p>
            <span class="hint" style="<?= $mine ? 'color:#dfece9' : '' ?>"><?= e(ago($m['created_at'])) ?></span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$items): ?><p class="muted">No messages yet. Say hello.</p><?php endif; ?>
  </div>

  <?php if (!$blocked): ?>
    <form method="post" action="<?= e(url('messages/'.$them['username'].'/send')) ?>" style="margin-bottom:60px">
      <?= csrf_field() ?>
      <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('message_'.(int)$them['id'])) ?>">
      <textarea name="body" placeholder="Write a message" maxlength="<?= RMT_MESSAGE_BODY_MAX ?>" style="min-height:80px" required></textarea>
      <button class="btn btn-primary" style="margin-top:8px">Send</button>
    </form>
  <?php endif; ?>
</div>
