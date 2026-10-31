<?php
/**
 * The chat for the people on one trip.
 *
 * @var array $t        the trip, with dest_name
 * @var array $lines    what the reader may see, oldest first
 * @var array $members  active members, owner first
 */
$me = current_user();
$tripUrl = url('trip/' . (int) $t['id'] . '/' . (string) $t['slug']);
$lastId = $lines ? (int) end($lines)['id'] : 0;
$others = array_values(array_filter($members, static fn(array $m) => (int) $m['user_id'] !== (int) $me['id']));
?>
<div class="wrap thread-shell">
  <div class="thread-head">
    <a href="<?= e($tripUrl) ?>" class="thread-back" aria-label="Back to the trip">&#8592;</a>
    <span class="thread-who">
      <span>
        <b><?= e((string) $t['title']) ?></b>
        <span class="hint">Trip chat &middot; <?= count($members) ?> <?= count($members) === 1 ? 'traveler' : 'travelers' ?>
          &middot; only the people on this trip can read it</span>
      </span>
    </span>
  </div>

  <?php if ($others): ?>
    <p class="trip-chat-people">
      <?php foreach ($members as $m): ?>
        <a href="<?= e(url('u/' . $m['username'])) ?>" title="@<?= e((string) $m['username']) ?>">
          <img class="avatar" src="<?= e(avatar_url($m['avatar_url'] ?? null)) ?>"
               alt="<?= e((string) ($m['display_name'] ?: $m['username'])) ?>"></a>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>

  <div class="msg-list" id="trip-chat-list"
       data-trip-chat="<?= e(url('trip/' . (int) $t['id'] . '/chat')) ?>" data-after="<?= $lastId ?>">
    <?php foreach ($lines as $m): $mine = (int) $m['user_id'] === (int) $me['id'];
      $gone = (string) $m['user_status'] !== 'active'; ?>
      <div class="msg-row<?= $mine ? ' mine' : '' ?>" id="m<?= (int) $m['id'] ?>">
        <div class="msg-bubble">
          <?php if (!$mine): ?>
            <span class="msg-who"><?php if ($gone): ?>Deleted member<?php else: ?><a href="<?= e(url('u/' . $m['username'])) ?>"><?= e((string) ($m['display_name'] ?: $m['username'])) ?></a><?php endif; ?></span>
          <?php endif; ?>
          <p><?= rmt_linkify_mentions(e((string) $m['body'])) ?></p>
          <span class="msg-when"><?= e(ago((string) $m['created_at'])) ?><?php
            if (!$mine): ?> &middot; <a href="<?= e(url('report?target_type=trip_message&target_id=' . (int) $m['id'])) ?>">Report</a><?php endif; ?></span>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$lines): ?>
      <p class="muted trip-chat-empty" style="text-align:center;margin:28px 0">
        Nothing here yet. Flights, where to stay, who is booking dinner: this is the place for it.
      </p>
    <?php endif; ?>
  </div>

  <form class="msg-composer" method="post" action="<?= e(url('trip/' . (int) $t['id'] . '/chat')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('trip_chat_' . (int) $t['id'])) ?>">
    <textarea name="body" rows="1" aria-label="Message the trip" placeholder="Message everyone on this trip"
              maxlength="<?= RMT_TRIP_CHAT_BODY_MAX ?>" required></textarea>
    <button class="btn btn-primary">Send</button>
  </form>
</div>
