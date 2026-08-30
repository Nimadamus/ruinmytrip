<?php /** @var array $communities @var array $mine @var ?array $me */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Communities</p></div>
<div class="wrap prose">
  <h1>Communities</h1>
  <p style="font-size:1.15rem;color:var(--muted)">Groups started by travelers, about the things a
    city page cannot hold: who you travel as, what you are willing to put up with, and what ruined
    it last time.</p>

  <?php /* Your own first. A member arriving here is almost always coming back to a room they are
           already in, not shopping for a new one. */ ?>
  <?php if ($mine): ?>
    <h2>Yours</h2>
    <?php foreach ($mine as $c): ?>
      <div class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px">
        <a href="<?= e(url('c/'.$c['slug'])) ?>"><b><?= e((string) $c['title']) ?></b></a>
        <?php if (($c['role'] ?? '') === 'owner'): ?><span class="chip">founder</span><?php endif; ?>
        <?php if (!empty($c['summary'])): ?><p class="muted" style="margin:.3rem 0 0"><?= e((string) $c['summary']) ?></p><?php endif; ?>
      </div></div>
    <?php endforeach; ?>
    <hr style="margin:28px 0">
  <?php endif; ?>

  <h2>Open to join</h2>
  <?php /* Deliberately not every community: one with an empty room and a single member turns away
           the first stranger who finds it, and that stranger does not come back. A community earns
           its place here by having something in it and somebody besides its founder. */ ?>
  <?php if (!$communities): ?>
    <p class="muted">No community is ready to be listed yet. They appear here once they have
      <?= RMT_COMMUNITY_MIN_ITEMS ?> things in them and <?= RMT_COMMUNITY_MIN_MEMBERS ?> members, so that
      the first person through the door finds something worth staying for.</p>
  <?php endif; ?>

  <?php foreach ($communities as $c): ?>
    <div class="card" style="margin-bottom:12px"><div class="card-body" style="padding:14px 16px">
      <a href="<?= e(url('c/'.$c['slug'])) ?>" style="font-size:1.1rem"><b><?= e((string) $c['title']) ?></b></a>
      <p class="muted" style="margin:.35rem 0 0">
        <?= (int) $c['member_count'] ?> <?= (int) $c['member_count'] === 1 ? 'member' : 'members' ?>
        &middot; <?= (int) $c['item_count'] ?> <?= (int) $c['item_count'] === 1 ? 'entry' : 'entries' ?>
        &middot; started by <a href="<?= e(url('u/'.$c['owner_username'])) ?>">@<?= e((string) $c['owner_username']) ?></a>
        <?php if ($c['join_policy'] === 'invite'): ?> &middot; invite only<?php endif; ?>
      </p>
      <?php if (!empty($c['summary'])): ?><p style="margin:.5rem 0 0"><?= e((string) $c['summary']) ?></p><?php endif; ?>
    </div></div>
  <?php endforeach; ?>

  <hr style="margin:28px 0">
  <h2>Start your own</h2>
  <p>A community here is a collection with a door on it. Make the collection, put a few things in
    it, then choose who can join from its edit page.</p>
  <p>
    <?php if ($me): ?>
      <a class="btn btn-accent" href="<?= e(url('collection/new')) ?>">Start a community</a>
    <?php else: ?>
      <a class="btn btn-accent" href="<?= e(url('login?return='.urlencode('/collection/new'))) ?>">Sign in to start one</a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(url('collections')) ?>">Browse all collections</a>
  </p>
</div>
