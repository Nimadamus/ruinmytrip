<?php
/** @var array $rows @var string $q */
$here = 'admin/users';
$me = current_user();
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Users</h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <div class="callout">
    <b>Accounts are suspended, never deleted.</b> Deleting an account would take that person's genuine
    warnings with it, and those warnings are useful to other travelers regardless of what the author did later.
    Suspension blocks sign-in; individual content is moderated on its own merits.
  </div>

  <form class="filters" method="get" action="<?= e(url('admin/users')) ?>">
    <div style="flex:1;min-width:220px">
      <label for="uq">Search</label>
      <input id="uq" type="search" name="q" style="width:100%" value="<?= e($q) ?>" placeholder="Username or email">
    </div>
    <button class="btn btn-primary btn-sm" type="submit">Search</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/users')) ?>">Reset</a>
  </form>

  <div class="table-scroll"><table class="tbl">
    <thead><tr><th>User</th><th>Email</th><th>Warnings</th><th>Reviews</th><th>Joined</th><th>Role &amp; status</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $u): ?>
        <tr>
          <td><a href="<?= e(url('u/' . $u['username'])) ?>">@<?= e((string) $u['username']) ?></a>
            <?php if (!empty($u['display_name'])): ?><br><span class="hint"><?= e((string) $u['display_name']) ?></span><?php endif; ?></td>
          <td><?= e((string) $u['email']) ?></td>
          <td><?= (int) $u['warnings'] ?></td>
          <td><?= (int) $u['reviews'] ?></td>
          <td><?= e(date('M j, Y', strtotime((string) $u['created_at']))) ?></td>
          <td>
            <?php if ((int) $u['id'] === (int) $me['id']): ?>
              <span class="muted"><?= e((string) $u['role']) ?> · you</span>
            <?php else: ?>
              <form method="post" action="<?= e(url('admin/user/' . (int) $u['id'])) ?>" style="display:flex;gap:6px;flex-wrap:wrap">
                <?= csrf_field() ?>
                <select name="role" style="padding:.3rem">
                  <?php foreach (['user', 'mod', 'admin', 'editorial'] as $role): ?>
                    <option value="<?= e($role) ?>" <?= (string) $u['role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="status" style="padding:.3rem">
                  <?php foreach (['active', 'suspended'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= (string) $u['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-ghost btn-sm" type="submit">Save</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <div style="height:50px"></div>
</div>
