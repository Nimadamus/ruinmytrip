<?php
/**
 * The chat for the members on one sailing (app/sailing_chat.php).
 *
 * @var string $key @var string $ship @var string $date @var string $label
 * @var array  $lines    what the reader may see, oldest first
 * @var array  $members  the members on this sailing (blocks removed)
 */
$me = current_user();
$base = url(ltrim(rmt_sailing_path($ship, $date), '/'));
$lastId = $lines ? (int) end($lines)['id'] : 0;
$people = [];
foreach ($members as $m) $people[(int) $m['user_id']] = $m;
?>
<div class="wrap thread-shell">
  <div class="thread-head">
    <a href="<?= e($base) ?>" class="thread-back" aria-label="Back to the sailing">&#8592;</a>
    <span class="thread-who">
      <span>
        <b><?= e($ship) ?>, <?= e($label) ?></b>
        <span class="hint">Sailing chat &middot; <?= count($people) ?> <?= count($people) === 1 ? 'traveler' : 'travelers' ?>
          &middot; only the people on this sailing can read it</span>
      </span>
    </span>
  </div>

  <p class="trip-chat-people">
    <?php foreach ($people as $m): ?>
      <a href="<?= e(url('u/' . $m['username'])) ?>" title="@<?= e((string) $m['username']) ?>">
        <img class="avatar" src="<?= e(avatar_url($m['avatar_url'] ?? null)) ?>"
             alt="<?= e((string) ($m['display_name'] ?: $m['username'])) ?>"></a>
    <?php endforeach; ?>
  </p>

  <div class="msg-list" id="trip-chat-list" data-trip-chat="<?= e($base . '/chat') ?>" data-after="<?= $lastId ?>">
    <?php foreach ($lines as $m): $mine = (int) $m['user_id'] === (int) $me['id'];
      $gone = (string) $m['user_status'] !== 'active'; ?>
      <div class="msg-row<?= $mine ? ' mine' : '' ?>" id="m<?= (int) $m['id'] ?>">
        <div class="msg-bubble">
          <?php if (!$mine): ?>
            <span class="msg-who"><?php if ($gone): ?>Deleted member<?php else: ?><a href="<?= e(url('u/' . $m['username'])) ?>"><?= e((string) ($m['display_name'] ?: $m['username'])) ?></a><?php endif; ?></span>
          <?php endif; ?>
          <p><?= rmt_linkify_mentions(e((string) $m['body'])) ?></p>
          <span class="msg-when"><?= e(ago((string) $m['created_at'])) ?><?php
            if (!$mine): ?> &middot; <a href="<?= e(url('report?target_type=sailing_message&target_id=' . (int) $m['id'])) ?>">Report</a><?php endif; ?></span>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$lines): ?>
      <p class="muted trip-chat-empty" style="text-align:center;margin:28px 0">
        Nothing here yet. Say hello: excursions, dinner seating, where you are flying in from.
      </p>
    <?php endif; ?>
  </div>

  <form class="msg-composer" method="post" action="<?= e($base . '/chat') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('sailing_chat_' . $key . $date)) ?>">
    <textarea name="body" rows="1" aria-label="Message the sailing" placeholder="Message everyone on this sailing"
              maxlength="<?= RMT_SAILING_CHAT_BODY_MAX ?>" required></textarea>
    <button class="btn btn-primary">Send</button>
  </form>
</div>
