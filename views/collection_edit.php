<?php /** @var array $c @var array $items @var array $available @var array $errors */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Edit list</h1>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/edit')) ?>">
    <?= csrf_field() ?>
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="<?= e($c['title']) ?>" required>
    <label for="summary">Summary <span class="hint">(optional)</span></label>
    <textarea id="summary" name="summary" maxlength="500"><?= e($c['summary'] ?? '') ?></textarea>

    <?php /* The two decisions that turn a list into a community. They are separate because letting
             somebody in and handing them the pen are separate decisions, and a founder should be
             able to make the first without the second. */ ?>
    <label for="join_policy">Who can join</label>
    <select id="join_policy" name="join_policy">
      <option value="closed" <?= ($c['join_policy'] ?? 'closed') === 'closed' ? 'selected' : '' ?>>Nobody. This is my own list.</option>
      <option value="invite" <?= ($c['join_policy'] ?? '') === 'invite' ? 'selected' : '' ?>>People I send an invite link to</option>
      <option value="open"   <?= ($c['join_policy'] ?? '') === 'open'   ? 'selected' : '' ?>>Anyone with an account</option>
    </select>
    <label style="display:flex;gap:8px;align-items:center;margin-top:12px;font-weight:400">
      <input type="checkbox" name="members_can_add" value="1" <?= (int) ($c['members_can_add'] ?? 0) === 1 ? 'checked' : '' ?>>
      Members can add places and cities
    </label>
    <p class="hint">A community stays out of search and off the browse page until it has
      <?= RMT_COMMUNITY_MIN_ITEMS ?> things in it and <?= RMT_COMMUNITY_MIN_MEMBERS ?> members. An empty room turns
      away the first person who finds it.</p>

    <div style="margin-top:18px"><button class="btn btn-primary" type="submit">Save details</button></div>
  </form>

  <?php if (in_array($c['join_policy'] ?? 'closed', ['invite','open'], true)): ?>
    <hr style="margin:28px 0">
    <h2>Invite link</h2>
    <?php $inv = rmt_community_invite((int) $c['id']); ?>
    <?php if ($inv): ?>
      <p><code><?= e(abs_url('/join/'.$inv['token'])) ?></code></p>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/invite')) ?>">
          <?= csrf_field() ?><button class="btn btn-ghost btn-sm">Replace link</button>
        </form>
        <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/invite/revoke')) ?>">
          <?= csrf_field() ?><button class="btn btn-ghost btn-sm" style="color:#b42318">Revoke link</button>
        </form>
      </div>
      <p class="hint">Replacing the link stops the old one working. There is only ever one live link.</p>
    <?php else: ?>
      <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/invite')) ?>">
        <?= csrf_field() ?><button class="btn btn-accent btn-sm">Create an invite link</button>
      </form>
    <?php endif; ?>
    <p style="margin-top:14px"><a href="<?= e(url('c/'.$c['slug'].'/members')) ?>">Members and removals →</a></p>
  <?php endif; ?>

  <hr style="margin:28px 0">

  <h2>On this list <span class="muted" style="font-weight:400;font-size:1rem">(<?= count($items) ?>)</span></h2>
  <?php if (!$items): ?><p class="muted">Nothing added yet.</p><?php endif; ?>
  <?php foreach ($items as $it): ?>
    <div class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px;display:flex;gap:10px;align-items:flex-start">
      <div style="flex:1">
        <?php if (!empty($it['place_id'])): ?>
          <b><?= e((string) $it['place_name']) ?></b>
          <span class="muted" style="text-transform:capitalize"> &middot; <?= e(rmt_place_type_label((string) $it['place_type'])) ?>
            &middot; <?= e((string) $it['place_dest_name']) ?></span>
        <?php else: ?>
          <b><?= e((string) $it['dest_name']) ?>, <?= e((string) $it['dest_country']) ?></b>
        <?php endif; ?>
        <?php if ($it['note']): ?><p style="margin:.3rem 0 0"><?= e($it['note']) ?></p><?php endif; ?>
      </div>
      <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/items/'.(int)$it['id'].'/delete')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" style="color:#b42318">Remove</button>
      </form>
    </div></div>
  <?php endforeach; ?>

  <?php if ($available): ?>
    <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/items')) ?>" style="margin-top:14px">
      <?= csrf_field() ?>
      <label for="destination_id">Add a destination</label>
      <select id="destination_id" name="destination_id" required>
        <option value="">Choose…</option>
        <?php foreach ($available as $d): ?>
          <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?>, <?= e($d['country']) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="note">Why it's on this list <span class="hint">(optional)</span></label>
      <textarea id="note" name="note" maxlength="500" placeholder="A sentence or two on why this one made the cut"></textarea>
      <div style="margin-top:12px"><button class="btn btn-accent" type="submit">Add to list</button></div>
    </form>
  <?php else: ?>
    <p class="muted" style="margin-top:14px">Every destination on the site is already on this list.</p>
  <?php endif; ?>

  <hr style="margin:28px 0">
  <p><a href="<?= e(url('c/'.$c['slug'])) ?>">View list →</a></p>
  <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/delete')) ?>" onsubmit="return confirm('Delete this list? This cannot be undone.');">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-sm" style="color:#b42318">Delete list</button>
  </form>
</div></div>
