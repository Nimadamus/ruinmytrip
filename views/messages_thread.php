<?php /** @var array $gate @var ?array $incoming @var array $them @var array $items @var bool $blocked @var array $shared @var ?array $theirHome */
$me = current_user();
$shared = $shared ?? [];
?>
<div class="wrap thread-shell">
  <div class="thread-head">
    <a href="<?= e(url('messages')) ?>" class="thread-back" aria-label="All messages">&#8592;</a>
    <a class="thread-who" href="<?= e(url('u/'.$them['username'])) ?>">
      <img class="avatar" src="<?= e(avatar_url($them['avatar_url'])) ?>" alt="">
      <span>
        <b><?= e($them['display_name'] ?: $them['username']) ?></b>
        <span class="hint">@<?= e($them['username']) ?><?php
          if (!empty($theirHome)): ?> &middot; lives in <?= e((string) $theirHome['name']) ?><?php endif; ?></span>
      </span>
    </a>
    <?php /* Block moves into the overflow. It is still one click away, which is what safety needs;
             what changes is that the loudest control on a page for talking to somebody stops being
             the one for cutting them off. */ ?>
    <details class="more-menu" style="margin-left:auto">
      <summary aria-label="More actions">&#8943;</summary>
      <div class="more-menu-panel">
        <a href="<?= e(url('u/'.$them['username'])) ?>">View profile</a>
        <a href="<?= e(url('report?target_type=user&target_id='.(int)$them['id'])) ?>">Report</a>
        <form method="post" action="<?= e(url($blocked ? 'unblock' : 'block')) ?>"
              onsubmit="return confirm('<?= $blocked ? 'Unblock' : 'Block' ?> @<?= e($them['username']) ?>?');">
          <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$them['id'] ?>">
          <input type="hidden" name="return" value="<?= e(url('messages/'.$them['username'])) ?>">
          <button class="more-menu-danger"><?= $blocked ? 'Unblock' : 'Block' ?> @<?= e($them['username']) ?></button>
        </form>
      </div>
    </details>
  </div>

  <?php /* The reason the two of you are in the same place at the same time, said once at the top.
           Dates only, and only the ones their own trip already shows to you. */ ?>
  <?php if ($shared): ?>
    <div class="thread-context">
      <?php foreach (array_slice($shared, 0, 2) as $sm): ?>
        <p style="margin:0">
          <b>Both of you are in <?= e((string) $sm['dest_name']) ?></b>
          <?php if (!empty($sm['overlap_from'])): ?>
            &middot; <?= e(rmt_card_date_range((string) $sm['overlap_from'], (string) $sm['overlap_to'])) ?>
            (<?= (int) $sm['overlap_days'] ?> <?= (int) $sm['overlap_days'] === 1 ? 'day' : 'days' ?> together)
          <?php endif; ?>
          &middot; <a href="<?= e(url('d/'.$sm['dest_slug'].'/travelers')) ?>">who else is going</a>
        </p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($blocked): ?>
    <div class="callout" style="margin:16px 0">
      You and @<?= e($them['username']) ?> cannot message each other while a block is in place.
    </div>
  <?php endif; ?>

  <div class="msg-list">
    <?php foreach ($items as $m): $mine = (int)$m['sender_id'] === (int)$me['id']; ?>
      <div class="msg-row<?= $mine ? ' mine' : '' ?>">
        <div class="msg-bubble">
          <p><?= rmt_linkify_mentions(e($m['body'])) ?></p>
          <span class="msg-when"><?= e(ago($m['created_at'])) ?><?php
            if ($mine && !empty($m['read_at'])): ?> &middot; read<?php endif; ?><?php
            /* One message, not the person. Blocking stops them writing to you and leaves them
               free to send the same thing to somebody else; a report is how a moderator ever
               learns a private message existed. */
            if (!$mine): ?> &middot; <a href="<?= e(url('report?target_type=message&target_id='.(int) $m['id'])) ?>">Report</a><?php endif; ?></span>
        </div>
      </div>
    <?php endforeach; ?>
    <?php /* Only when they could actually write one. Inviting somebody to say hello directly above
             a panel explaining that they cannot is two halves of the page disagreeing. */ ?>
    <?php if (!$items && $gate['ok']): ?>
      <p class="muted" style="text-align:center;margin:28px 0">
        No messages yet. <?php if ($shared): ?>You are in the same city at the same time, which is a
        better opening line than most.<?php else: ?>Say hello.<?php endif; ?>
      </p>
    <?php endif; ?>
  </div>

  <?php /* The composer appears when both of them have said they would like to meet, and not
           before. When it does not, the page says which kind of not yet it is and offers the one
           thing that would change it, because "you cannot message this person" with no reason and
           no next step is how a product feels broken rather than careful. */ ?>
  <?php if (!$gate['ok'] && $gate['reason'] !== 'blocked'): ?>
    <div class="msg-gate">
      <?php if ($gate['reason'] === 'requested'): ?>
        <p style="margin:0 0 4px"><b>Waiting on @<?= e($them['username']) ?>.</b></p>
        <p class="hint" style="margin:0">You said you would like to meet. If they say yes, you can
          message each other here.</p>
      <?php elseif ($gate['reason'] === 'incoming' && $incoming): ?>
        <p style="margin:0 0 4px"><b>@<?= e($them['username']) ?> would like to meet
          <?php if (!empty($incoming['dest_name'])): ?>on your <?= e((string) $incoming['dest_name']) ?> trip<?php endif; ?>.</b></p>
        <p class="hint" style="margin:0 0 10px">Say yes and you can message each other. Say no and
          they are not told.</p>
        <div class="msg-gate-acts">
          <form method="post" action="<?= e(url('connect/' . (int) $incoming['id'] . '/decide')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="answer" value="accept">
            <input type="hidden" name="return" value="<?= e('/messages/' . $them['username']) ?>">
            <button class="btn btn-primary btn-sm">Yes</button>
          </form>
          <form method="post" action="<?= e(url('connect/' . (int) $incoming['id'] . '/decide')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="answer" value="decline">
            <input type="hidden" name="return" value="<?= e('/messages/' . $them['username']) ?>">
            <button class="btn btn-ghost btn-sm">No thanks</button>
          </form>
        </div>
      <?php elseif ($gate['reason'] === 'declined'): ?>
        <p style="margin:0"><b>@<?= e($them['username']) ?> answered.</b>
          <span class="hint">There is nothing more to do here.</span></p>
      <?php else: ?>
        <p style="margin:0 0 4px"><b>You can message each other once you have both said so.</b></p>
        <p class="hint" style="margin:0">Find them on a trip whose dates cross yours and say you are
          interested in meeting. <a href="<?= e(url('matches')) ?>">Your overlaps</a>.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!$blocked && $gate['ok']): ?>
    <form class="msg-composer" method="post" action="<?= e(url('messages/'.$them['username'].'/send')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('message_'.(int)$them['id'])) ?>">
      <textarea name="body" rows="1" placeholder="Message @<?= e($them['username']) ?>"
                maxlength="<?= RMT_MESSAGE_BODY_MAX ?>" required></textarea>
      <button class="btn btn-primary">Send</button>
    </form>
  <?php endif; ?>
</div>
