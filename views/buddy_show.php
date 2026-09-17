<?php /** @var array $b @var ?array $me @var bool $isOwner @var array $interest @var ?array $mine @var int $accepted @var bool $isPast
 *  @var array $interests @var array $langs @var array $sameSailing @var array $similar @var array $alsoGoing @var bool $saved */
$open = $b['status'] === 'open' && !$isPast;
$pid = (int) $b['id'];
$poster = (string) $b['author']['username'];
$back = '/buddy/' . $pid;
$nights = rmt_buddy_nights((string) $b['date_from'], (string) $b['date_to']); ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('buddies')) ?>">Travel buddies</a> / <?= e($b['title']) ?></p></div>
<div class="wrap" style="max-width:860px">
  <a class="chip" href="<?= e(url('buddies/' . str_replace('_', '-', $b['trip_type']))) ?>"><?= e(RMT_BUDDY_TYPES[$b['trip_type']] ?? 'Trip') ?></a>
  <?php if (!empty($b['dest_slug'])): ?><a class="chip" href="<?= e(url('buddies?dest=' . rawurlencode($b['dest_slug']))) ?>"><?= e($b['dest_name']) ?></a><?php endif; ?>
  <?php if (!empty($b['travel_party']) && isset(RMT_BUDDY_PARTIES[$b['travel_party']])): ?><span class="chip"><?= e(RMT_BUDDY_PARTIES[$b['travel_party']]) ?></span><?php endif; ?>
  <h1><?= e($b['title']) ?></h1>
  <p class="muted"><?= e($b['where_text']) ?> &middot;
    <?= e(date('M j, Y', strtotime((string) $b['date_from']))) ?> to <?= e(date('M j, Y', strtotime((string) $b['date_to']))) ?><?= (int) $b['flexible'] ? ' (flexible)' : '' ?>
    &middot; Posted by <a href="<?= e(url('u/' . $poster)) ?>">@<?= e($poster) ?></a></p>

  <?php if ($b['trip_type'] === 'cruise'): ?>
    <div class="card" style="margin:12px 0"><div class="card-body">
      <p style="margin:0"><b><?= e($b['ship'] ?: 'Ship not given') ?></b><?= $b['cruise_line'] ? ' &middot; ' . e($b['cruise_line']) : '' ?></p>
      <p class="muted" style="margin:.2rem 0 0"><?= e(implode(' · ', array_filter([
          $b['departure_port'] ? 'Departs ' . $b['departure_port'] : '', 'Sails ' . date('M j, Y', strtotime((string) $b['date_from'])),
          $nights ? $nights . ' nights' : '', (string) ($b['itinerary'] ?? '')]))) ?></p>
    </div></div>
  <?php endif; ?>

  <p class="meta-row">Looking for <?= (int) $b['spots'] ?> &middot; <?= e(RMT_BUDDY_BUDGETS[$b['budget']] ?? 'Any budget') ?>
    <?php if (!empty($b['age_min']) || !empty($b['age_max'])): ?> &middot; ages <?= (int) ($b['age_min'] ?: 18) ?> to <?= (int) ($b['age_max'] ?: 99) ?><?php endif; ?>
    &middot; <?= count($interest) ?> interested &middot; <?= (int) $accepted ?> accepted</p>

  <?php if ($b['status'] === 'closed'): ?>
    <div class="callout" style="margin:14px 0"><b>The poster has found their buddies.</b> This trip is no longer taking new requests.</div>
  <?php elseif ($isPast): ?>
    <div class="callout" style="margin:14px 0"><b>This trip has already happened.</b></div>
  <?php endif; ?>

  <p style="font-size:1.08rem"><?= nl2br(e($b['description'])) ?></p>

  <?php if ($interests || $langs): ?>
    <div class="bcard-chips" style="margin:8px 0">
      <?php foreach ($interests as $lab): ?><span class="chip chip-soft"><?= e($lab) ?></span><?php endforeach; ?>
    </div>
    <?php if ($langs): ?><p class="hint">Speaks <?= e(implode(', ', $langs)) ?></p><?php endif; ?>
  <?php endif; ?>

  <?php if (!$isOwner): ?>
    <?php $trustUserId = (int) $b['user_id']; $trustUsername = $poster; include __DIR__ . '/_trust.php'; ?>
    <div class="callout warn" style="margin-top:14px"><b>Before you travel with anyone:</b> talk first, meet somewhere public, check they are who they say, never send money, and tell someone your plans. <a href="<?= e(url('safety')) ?>">Safety guide</a></div>
    <div style="margin:20px 0">
      <?php if (!$me): ?>
        <?php if ($open): ?><a class="btn btn-primary" href="<?= e(url('login?return=' . rawurlencode($back))) ?>">Sign in to join this trip</a><?php endif; ?>
      <?php elseif ($mine): ?>
        <p><b><?= $mine['state'] === 'accepted' ? 'You are accepted.' : ($mine['state'] === 'declined' ? 'The poster has passed on this one.' : 'Your request is in. Waiting for the poster.') ?></b></p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php if ($mine['state'] === 'accepted'): ?>
            <a class="btn btn-primary" href="<?= e(url('messages/' . $poster)) ?>">Message @<?= e($poster) ?></a>
          <?php endif; ?>
          <form method="post" action="<?= e(url('buddy/' . $pid . '/interest')) ?>" style="margin:0"><?= csrf_field() ?>
            <button class="btn btn-ghost">Withdraw</button></form>
        </div>
      <?php elseif ($open): ?>
        <form method="post" action="<?= e(url('buddy/' . $pid . '/interest')) ?>"><?= csrf_field() ?>
          <label for="note">A note to @<?= e($poster) ?> <span class="hint">(optional, no contact details)</span></label>
          <textarea id="note" name="note" rows="3" maxlength="500" placeholder="Who you are, why this trip, what you would like to do."></textarea>
          <button class="btn btn-primary" style="margin-top:10px">I'm interested</button>
        </form>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <h2 style="margin-top:28px">People who want to come</h2>
    <?php if (!$interest): ?>
      <p class="muted">Nobody yet. You will get a notification the moment somebody asks, and when a matching trip is posted.</p>
    <?php else: ?>
      <?php foreach ($interest as $i): ?>
        <div class="bdash-row">
          <img class="avatar" src="<?= e(avatar_url($i['avatar_url'])) ?>" alt="">
          <div class="bdash-main">
            <b><a href="<?= e(url('u/' . $i['username'])) ?>">@<?= e($i['username']) ?></a></b>
            <span class="chip"><?= e(ucfirst((string) $i['state'])) ?></span>
            <?php if (!empty($i['note'])): ?><p style="margin:.4rem 0"><?= nl2br(e((string) $i['note'])) ?></p><?php endif; ?>
          </div>
          <div class="bdash-acts">
            <?php if ($i['state'] === 'accepted'): ?>
              <a class="btn btn-primary btn-sm" href="<?= e(url('messages/' . $i['username'])) ?>">Message</a>
            <?php else: ?>
              <form method="post" action="<?= e(url('buddy/' . $pid . '/decide/' . (int) $i['user_id'])) ?>"><?= csrf_field() ?>
                <input type="hidden" name="answer" value="accepted"><button class="btn btn-primary btn-sm">Accept</button></form>
            <?php endif; ?>
            <?php if ($i['state'] !== 'declined'): ?>
              <form method="post" action="<?= e(url('buddy/' . $pid . '/decide/' . (int) $i['user_id'])) ?>"><?= csrf_field() ?>
                <input type="hidden" name="answer" value="declined"><button class="btn btn-ghost btn-sm">Pass</button></form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:20px 0">
      <a class="btn btn-ghost" href="<?= e(url('buddy/' . $pid . '/edit')) ?>">Edit</a>
      <form method="post" action="<?= e(url('buddy/' . $pid . '/status')) ?>" style="margin:0"><?= csrf_field() ?>
        <input type="hidden" name="status" value="<?= $b['status'] === 'open' ? 'closed' : 'open' ?>">
        <button class="btn btn-ghost"><?= $b['status'] === 'open' ? 'I found my buddies, close it' : 'Reopen' ?></button></form>
      <form method="post" action="<?= e(url('buddy/' . $pid . '/status')) ?>" style="margin:0" onsubmit="return confirm('Cancel this trip and remove the post?')"><?= csrf_field() ?>
        <input type="hidden" name="status" value="removed"><button class="btn btn-ghost">Cancel trip</button></form>
    </div>
  <?php endif; ?>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 24px">
    <?php if ($me && !$isOwner): ?>
      <form method="post" action="<?= e(url('react')) ?>" style="margin:0"><?= csrf_field() ?>
        <input type="hidden" name="kind" value="save"><input type="hidden" name="target_type" value="buddy">
        <input type="hidden" name="target_id" value="<?= $pid ?>"><input type="hidden" name="return" value="<?= e($back) ?>">
        <button class="btn btn-ghost btn-sm" aria-pressed="<?= $saved ? 'true' : 'false' ?>"><?= $saved ? 'Saved' : 'Save trip' ?></button></form>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('report?target_type=buddy&target_id=' . $pid)) ?>">Report</a>
  </div>

  <?php if ($sameSailing): ?>
    <h2>Also on this sailing</h2>
    <?php foreach ($sameSailing as $s): ?>
      <p class="bdash-row" style="margin:0"><img class="avatar" src="<?= e(avatar_url($s['avatar_url'])) ?>" alt="">
        <span class="bdash-main"><a href="<?= e(url('buddy/' . (int) $s['id'])) ?>"><?= e($s['title']) ?></a><br><span class="hint">@<?= e($s['username']) ?></span></span></p>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if ($similar): ?>
    <h2>Similar sailings</h2>
    <?php foreach ($similar as $s): ?>
      <p style="margin:.3rem 0"><a href="<?= e(url('buddy/' . (int) $s['id'])) ?>"><?= e($s['title']) ?></a>
        <span class="hint"><?= e(implode(' · ', array_filter([(string) $s['ship'], (string) $s['cruise_line'], date('M j', strtotime((string) $s['date_from']))]))) ?></span></p>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if ($alsoGoing): ?>
    <h2>Others in <?= e($b['dest_name']) ?> around these dates</h2>
    <div class="buddy-grid">
      <?php $bcBack = $back; foreach (rmt_buddy_card_interests(array_slice($alsoGoing, 0, 6)) as $bc): ?><?php include __DIR__ . '/_buddy_card.php'; ?><?php endforeach; ?>
    </div>
    <p><a href="<?= e(url('buddies') . rmt_buddy_query(rmt_buddy_filters(['dest' => $b['dest_slug'], 'from' => $b['date_from'], 'to' => $b['date_to']]))) ?>">See everyone going</a></p>
  <?php endif; ?>
</div>
