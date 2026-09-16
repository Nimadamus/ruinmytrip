<?php /** @var array $rows @var array $threads @var array $requests */
$threads = $threads ?? $rows;
$requests = $requests ?? [];

/* One row, drawn the same way in both lists, because a request is not a lesser kind of message.
   It is a message from somebody the member has not answered yet, and the only difference is which
   heading it sits under. */
$rmtRow = static function (array $r): void { ?>
  <li class="card" style="margin-bottom:8px">
    <a href="<?= e(url('messages/'.$r['username'])) ?>" style="color:inherit;text-decoration:none">
      <div class="card-body" style="padding:12px 16px;display:flex;align-items:center;gap:10px">
        <img class="avatar" src="<?= e(avatar_url($r['avatar_url'])) ?>" alt="<?= e($r['username']) ?>">
        <div style="flex:1;min-width:0">
          <div class="inbox-who">
            <b><?= e($r['display_name'] ?: $r['username']) ?></b>
            <span class="muted">@<?= e($r['username']) ?></span>
            <?php if ((int) $r['unread'] > 0): ?><span class="chip inbox-new"><?= (int) $r['unread'] ?> new</span><?php endif; ?>
          </div>
          <p class="muted" style="margin:.2rem 0 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($r['last_body'] ?? '') ?></p>
        </div>
        <span class="hint"><?= $r['last_message_at'] ? e(ago($r['last_message_at'])) : '' ?></span>
      </div>
    </a>
  </li>
<?php }; ?>
<div class="wrap" style="max-width:680px;min-height:50vh">
  <h1 style="margin-top:24px">Messages</h1>
  <?php if (!$threads && !$requests): ?>
    <?php $emptyTitle = 'No conversations yet';
          $emptyWhy = 'Messages start from somebody\'s profile, or from a match. The people below are real members, and the cities are ones somebody has actually posted dates for.';
          $emptyCtaText = 'Find travelers'; $emptyCtaUrl = url('travelers');
          include __DIR__ . '/_nothing_yet.php'; ?>
  <?php endif; ?>

  <?php if ($threads): ?>
    <ul class="list-plain">
      <?php foreach ($threads as $r) $rmtRow($r); ?>
    </ul>
  <?php endif; ?>

  <?php /* People who wrote and are waiting on a first answer. Separate, because the moment a
           stranger can put something in the same list as a conversation, the list stops being
           worth opening. Nothing is hidden: it is right here, counted, one tap away. */ ?>
  <?php if ($requests): ?>
    <h2 style="margin:28px 0 4px">Requests <span class="hint" style="font-family:var(--sans);font-size:.8rem;text-transform:none;letter-spacing:0"><?= count($requests) ?></span></h2>
    <p class="hint" style="margin:0 0 10px">People you have not written back to. Answer one and it
      moves up. You can block anybody from their profile.</p>
    <ul class="list-plain">
      <?php foreach ($requests as $r) $rmtRow($r); ?>
    </ul>
  <?php endif; ?>
  <?php /* An inbox with two threads is a page with nothing to do on it, and the thing to do is
           the whole reason this site exists: somebody is in the same city on the same days and
           nobody has said hello. Real overlaps, and anybody already in the lists above is
           excluded, so this disappears rather than repeating them. */ ?>
  <?php if (!empty($couldWrite)): ?>
    <h2 style="margin:28px 0 4px">You both said yes</h2>
    <p class="hint" style="margin:0 0 10px">Agreed to meet, and nobody has said anything yet.</p>
    <ul class="list-plain">
      <?php foreach ($couldWrite as $c): ?>
        <li class="card" style="margin-bottom:8px"><div class="card-body"
             style="padding:12px 16px;display:flex;align-items:center;gap:10px">
          <img class="avatar" src="<?= e(avatar_url($c['avatar_url'] ?? null)) ?>" alt="">
          <div style="flex:1;min-width:0">
            <div class="inbox-who">
              <b><a href="<?= e(url('u/'.$c['username'])) ?>"><?= e($c['display_name'] ?: $c['username']) ?></a></b>
              <span class="muted">@<?= e($c['username']) ?></span>
            </div>
            <?php if (!empty($c['dest_name'])): ?>
              <p class="muted" style="margin:.2rem 0 0"><?= e((string) $c['dest_name']) ?><?php
                if (!empty($c['date_from'])): ?> &middot;
                <?= e(date('M j', strtotime((string) $c['date_from']))) ?>
                to <?= e(date('M j', strtotime((string) $c['date_to']))) ?><?php endif; ?></p>
            <?php endif; ?>
          </div>
          <a class="btn btn-primary btn-sm" href="<?= e(url('messages/'.$c['username'])) ?>">Start conversation</a>
        </div></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div style="height:40px"></div>
</div>
