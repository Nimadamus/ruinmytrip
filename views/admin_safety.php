<?php /** /admin/safety: members whose recent behavior needs a look. @var array $rows */ ?>
<div class="wrap hl-sec hl-admin">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Admin</a> / Safety signals</p>
  <h1 class="hl-h2">Safety signals</h1>
  <p class="hint">Members who tripped a rule in the last <?= RMT_SIGNAL_DAYS ?> days. A flag is a reason to look, not proof of anything; act from
    <a href="<?= e(url('admin/moderation')) ?>">moderation</a>. Rules:
    <?php foreach (RMT_SIGNAL_RULES as [$min, $label]): ?><br>&middot; <?= e($label) ?><?php endforeach; ?></p>
  <?php if (!$rows): ?><p class="muted">Nobody is flagged right now.</p><?php else: ?>
  <table>
    <thead><tr><th>Member</th><th>Why flagged</th><th>Last 7 days</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $n = $r['counts']; ?>
      <tr>
        <td><a href="<?= e(url('u/' . $r['username'])) ?>">@<?= e((string) $r['username']) ?></a><br>
          <span class="hint">Joined <?= e(ago((string) $r['created_at'])) ?> &middot; <?= e((string) $r['status']) ?>
          &middot; <?= !empty($r['email_verified_at']) ? 'email confirmed' : 'email NOT confirmed' ?></span></td>
        <td><?php foreach ($r['flags'] as $label): ?><?= e($label) ?><br><?php endforeach; ?></td>
        <td class="hint">Requests <?= (int) $n['requests'] ?> &middot; declined <?= (int) $n['declined'] ?> &middot; new chats <?= (int) $n['new_chats'] ?>
          &middot; open reports <?= (int) $n['reports'] ?> &middot; blocked by <?= (int) $n['blocked'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
