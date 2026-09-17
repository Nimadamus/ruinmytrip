<?php /** @var array $b @var ?array $me @var bool $isOwner @var array $interest @var ?array $mine @var int $accepted @var bool $isPast @var array $hostStats */
$open = $b['status'] === 'open' && !$isPast;
$pid = (int) $b['id'];
$poster = (string) $b['author']['username']; ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('buddies')) ?>">Travel buddies</a> / <?= e($b['title']) ?></p></div>
<div class="wrap" style="max-width:820px">
  <a class="chip" href="<?= e(url('buddies/' . str_replace('_', '-', $b['trip_type']))) ?>"><?= e(RMT_BUDDY_TYPES[$b['trip_type']] ?? 'Trip') ?></a>
  <?php if (!empty($b['dest_slug'])): ?><a class="chip" href="<?= e(url('d/' . $b['dest_slug'] . '/travelers')) ?>"><?= e($b['dest_name']) ?></a><?php endif; ?>
  <h1><?= e($b['title']) ?></h1>
  <p class="muted"><?= e($b['where_text']) ?> &middot;
    <?= e(date('M j, Y', strtotime((string) $b['date_from']))) ?> to <?= e(date('M j, Y', strtotime((string) $b['date_to']))) ?><?= (int) $b['flexible'] ? ' (flexible)' : '' ?>
    &middot; Posted by <a href="<?= e(url('u/' . $poster)) ?>">@<?= e($poster) ?></a></p>
  <p class="meta-row">Looking for <?= (int) $b['spots'] ?> &middot; <?= e(RMT_BUDDY_BUDGETS[$b['budget']] ?? 'Any budget') ?>
    &middot; <?= count($interest) ?> interested &middot; <?= (int) $accepted ?> accepted</p>

  <?php if ($b['status'] === 'closed'): ?>
    <div class="callout" style="margin:14px 0"><b>The poster has found their buddies.</b> This post is no longer taking new interest.</div>
  <?php elseif ($isPast): ?>
    <div class="callout" style="margin:14px 0"><b>This trip has already happened.</b></div>
  <?php endif; ?>

  <p style="font-size:1.1rem"><?= nl2br(e($b['description'])) ?></p>

  <?php if (!$isOwner): ?>
    <div class="callout warn"><b>Before you travel with anyone:</b> talk first, meet somewhere public, check they are who they say, never send money, and tell someone your plans. <a href="<?= e(url('safety')) ?>">Safety guide</a></div>
    <div style="margin:20px 0">
      <?php if (!$me): ?>
        <?php if ($open): ?><a class="btn btn-primary" href="<?= e(url('login?return=' . rawurlencode('/buddy/' . $pid))) ?>">Sign in to join this trip</a><?php endif; ?>
      <?php elseif ($mine): ?>
        <p><b><?= $mine['state'] === 'accepted' ? 'You are accepted.' : ($mine['state'] === 'declined' ? 'The poster has passed on this one.' : 'Your hand is up. Waiting for the poster.') ?></b></p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php if ($mine['state'] === 'accepted'): ?>
            <a class="btn btn-primary" href="<?= e(url('messages/' . $poster)) ?>">Message @<?= e($poster) ?></a>
          <?php endif; ?>
          <form method="post" action="<?= e(url('buddy/' . $pid . '/interest')) ?>" style="margin:0"><?= csrf_field() ?>
            <button class="btn btn-ghost">Withdraw</button></form>
        </div>
      <?php elseif ($open): ?>
        <form method="post" action="<?= e(url('buddy/' . $pid . '/interest')) ?>"><?= csrf_field() ?>
          <label for="note">A note to @<?= e($poster) ?> <span class="hint">(optional)</span></label>
          <textarea id="note" name="note" rows="3" maxlength="500" placeholder="Who you are, why this trip, what you would like to do."></textarea>
          <button class="btn btn-primary" style="margin-top:10px">I'm interested</button>
        </form>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <h2 style="margin-top:28px">People who want to come</h2>
    <?php if (!$interest): ?>
      <p class="muted">Nobody yet. You will get a notification the moment somebody puts their hand up.</p>
    <?php else: ?>
      <?php foreach ($interest as $i): ?>
        <div class="card" style="margin:10px 0"><div class="card-body">
          <b><a href="<?= e(url('u/' . $i['username'])) ?>">@<?= e($i['username']) ?></a></b>
          <span class="chip"><?= e(ucfirst((string) $i['state'])) ?></span>
          <?php if (!empty($i['note'])): ?><p style="margin:.4rem 0"><?= nl2br(e((string) $i['note'])) ?></p><?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
            <?php if ($i['state'] === 'accepted'): ?>
              <a class="btn btn-primary btn-sm" href="<?= e(url('messages/' . $i['username'])) ?>">Message</a>
            <?php else: ?>
              <form method="post" action="<?= e(url('buddy/' . $pid . '/decide/' . (int) $i['user_id'])) ?>" style="margin:0"><?= csrf_field() ?>
                <input type="hidden" name="answer" value="accepted"><button class="btn btn-primary btn-sm">Accept</button></form>
            <?php endif; ?>
            <?php if ($i['state'] !== 'declined'): ?>
              <form method="post" action="<?= e(url('buddy/' . $pid . '/decide/' . (int) $i['user_id'])) ?>" style="margin:0"><?= csrf_field() ?>
                <input type="hidden" name="answer" value="declined"><button class="btn btn-ghost btn-sm">Pass</button></form>
            <?php endif; ?>
          </div>
        </div></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:20px 0">
      <form method="post" action="<?= e(url('buddy/' . $pid . '/status')) ?>" style="margin:0"><?= csrf_field() ?>
        <input type="hidden" name="status" value="<?= $b['status'] === 'open' ? 'closed' : 'open' ?>">
        <button class="btn btn-ghost"><?= $b['status'] === 'open' ? 'I found my buddies, close it' : 'Reopen' ?></button></form>
      <form method="post" action="<?= e(url('buddy/' . $pid . '/status')) ?>" style="margin:0" onsubmit="return confirm('Remove this post?')"><?= csrf_field() ?>
        <input type="hidden" name="status" value="removed"><button class="btn btn-ghost">Remove</button></form>
    </div>
  <?php endif; ?>
  <p><a class="btn btn-ghost btn-sm" href="<?= e(url('report?target_type=buddy&target_id=' . $pid)) ?>">⚑ Report</a></p>
</div>
