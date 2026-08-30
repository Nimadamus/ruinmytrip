<?php /** @var array $c @var ?array $me @var array $members @var array $removed @var bool $canEdit */ ?>
<div class="wrap"><p class="crumbs">
  <a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('communities')) ?>">Communities</a> /
  <a href="<?= e(url('c/'.$c['slug'])) ?>"><?= e($c['title']) ?></a> / Members
</p></div>
<div class="wrap prose">
  <h1>Members of <?= e($c['title']) ?></h1>
  <p class="muted"><?= count($members) ?> <?= count($members) === 1 ? 'member' : 'members' ?></p>

  <?php foreach ($members as $m): ?>
    <div class="card" style="margin-bottom:10px"><div class="card-body"
         style="padding:12px 16px;display:flex;gap:12px;align-items:center">
      <div style="flex:1">
        <a href="<?= e(url('u/'.$m['username'])) ?>">@<?= e((string) $m['username']) ?></a>
        <?php if ($m['role'] === 'owner'): ?><span class="chip">founder</span><?php endif; ?>
        <span class="muted"> &middot; joined <?= e(ago((string) $m['joined_at'])) ?></span>
      </div>
      <?php /* The founder cannot remove themselves: a community with nobody who can moderate it is
               worse for its members than one with a founder who has lost interest. */ ?>
      <?php if ($canEdit && $m['role'] !== 'owner'): ?>
        <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/members/'.(int)$m['user_id'].'/remove')) ?>"
              onsubmit="return confirm('Remove @<?= e((string) $m['username']) ?> from this community?');">
          <?= csrf_field() ?>
          <button class="btn btn-ghost btn-sm" style="color:#b42318">Remove</button>
        </form>
      <?php endif; ?>
    </div></div>
  <?php endforeach; ?>

  <?php /* Removals are shown only to the founder, and only so that a removal made in a bad hour
           can be undone. What a removed member contributed stays where it is unless the founder
           takes it down separately: deleting somebody's writing because they left is a different
           decision, and not one to make on their behalf. */ ?>
  <?php if ($canEdit && $removed): ?>
    <hr style="margin:28px 0">
    <h2>Removed</h2>
    <p class="muted">They cannot rejoin, even while the door is open, until you reinstate them.</p>
    <?php foreach ($removed as $m): ?>
      <div class="card" style="margin-bottom:10px"><div class="card-body"
           style="padding:12px 16px;display:flex;gap:12px;align-items:center">
        <div style="flex:1">
          <a href="<?= e(url('u/'.$m['username'])) ?>">@<?= e((string) $m['username']) ?></a>
          <span class="muted"> &middot; removed <?= e(ago((string) $m['removed_at'])) ?></span>
        </div>
        <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/members/'.(int)$m['user_id'].'/reinstate')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-ghost btn-sm">Reinstate</button>
        </form>
      </div></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <p style="margin-top:24px"><a class="btn btn-ghost" href="<?= e(url('c/'.$c['slug'])) ?>">← Back to <?= e($c['title']) ?></a></p>
</div>
