<?php
/**
 * A city's room: the travelers checked in to this city right now, and what they are saying.
 *
 * Members (a live check-in for this city) see the roster, the messages and the composer. Everybody
 * else signed in sees how many travelers are there and the way in.
 *
 * @var array  $d        the destination
 * @var ?array $checkin  the reader's live check-in for this city, or null
 * @var int    $count    travelers checked in right now
 * @var array  $roster   who the reader may see (empty for a non member)
 * @var array  $lines    messages the reader may see, oldest first (empty for a non member)
 */
$me = current_user();
$lastId = $lines ? (int) end($lines)['id'] : 0;
$cityUrl = url('d/' . (string) $d['slug'] . '/travelers');
?>
<div class="wrap thread-shell">
  <div class="thread-head">
    <a href="<?= e($cityUrl) ?>" class="thread-back" aria-label="Back to <?= e((string) $d['name']) ?>">&#8592;</a>
    <span class="thread-who">
      <span>
        <b><?= e((string) $d['name']) ?> travelers, right now</b>
        <span class="hint"><span id="city-room-count"><?= (int) $count ?></span> checked in
          &middot; only travelers checked in to <?= e((string) $d['name']) ?> can read this</span>
      </span>
    </span>
  </div>

  <?php if (!$checkin): ?>
    <div class="card" style="margin:18px 0"><div class="card-body">
      <p style="margin:0"><?= $count > 0
          ? (int) $count . ($count === 1 ? ' traveler is' : ' travelers are') . ' checked in to ' . e((string) $d['name']) . ' right now.'
          : 'Nobody is checked in to ' . e((string) $d['name']) . ' yet. Be the first and the room opens with you.' ?>
        The room is for people who are in the city today. When a check-in runs out, that traveler and everything they said leave the room.</p>
    </div></div>
    <?php $ckDest = $d; $ckBack = '/city-room/' . (int) $d['id']; include __DIR__ . '/_checkin_card.php'; ?>
  <?php else: ?>
    <p class="hint" style="margin:10px 0">You are here for another <?= e(rmt_checkin_left((string) $checkin['expires_at'])) ?>.
      Meet in public places, and report anything that feels off.</p>

    <p class="trip-chat-people" id="city-room-people">
      <?php foreach ($roster as $m): ?>
        <a href="<?= e(url('u/' . $m['username'])) ?>" title="@<?= e((string) $m['username']) ?>, here for another <?= e(rmt_checkin_left((string) $m['expires_at'])) ?>">
          <img class="avatar" src="<?= e(avatar_url($m['avatar_url'] ?? null)) ?>"
               alt="<?= e((string) ($m['display_name'] ?: $m['username'])) ?>"></a>
      <?php endforeach; ?>
    </p>

    <div class="msg-list" id="city-room-list"
         data-city-room="<?= e(url('city-room/' . (int) $d['id'] . '/feed')) ?>" data-after="<?= $lastId ?>">
      <?php foreach ($lines as $m): $mine = (int) $m['user_id'] === (int) $me['id']; ?>
        <div class="msg-row<?= $mine ? ' mine' : '' ?>" id="m<?= (int) $m['id'] ?>">
          <div class="msg-bubble">
            <?php if (!$mine): ?>
              <span class="msg-who"><a href="<?= e(url('u/' . $m['username'])) ?>"><?= e((string) ($m['display_name'] ?: $m['username'])) ?></a></span>
            <?php endif; ?>
            <p><?= rmt_linkify_mentions(e((string) $m['body'])) ?></p>
            <span class="msg-when"><?= e(ago((string) $m['created_at'])) ?><?php
              if (!$mine): ?> &middot; <a href="<?= e(url('report?target_type=room_message&target_id=' . (int) $m['id'])) ?>">Report</a><?php endif; ?></span>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$lines): ?>
        <p class="muted trip-chat-empty" style="text-align:center;margin:28px 0">
          Quiet so far. Say where you are headed tonight and see who is around.
        </p>
      <?php endif; ?>
    </div>

    <form class="msg-composer" method="post" action="<?= e(url('city-room/' . (int) $d['id'] . '/message')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('city_room_' . (int) $d['id'])) ?>">
      <textarea name="body" rows="1" aria-label="Message the room" placeholder="Message travelers in <?= e((string) $d['name']) ?>"
                maxlength="<?= RMT_CITY_ROOM_BODY_MAX ?>" required></textarea>
      <button class="btn btn-primary">Send</button>
    </form>

    <form method="post" action="<?= e(url('checkin/end')) ?>" style="margin-top:14px;text-align:center"><?= csrf_field() ?>
      <input type="hidden" name="return" value="<?= e('/city-room/' . (int) $d['id']) ?>">
      <button class="btn btn-ghost btn-sm">Check out of <?= e((string) $d['name']) ?></button>
    </form>
  <?php endif; ?>
</div>
