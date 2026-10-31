<?php
/** /admin/hosts: host applications and community health. @var array $rows @var string $status @var array $counts @var array $growth */
?>
<div class="wrap hl-sec hl-admin">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Admin</a> / Hosts &amp; Locals</p>
  <h1 class="hl-h2">Host applications</h1>
  <p class="hint">New members in 7 days: <b><?= (int) $growth['members_7d'] ?></b> &middot; listed locals: <b><?= (int) $growth['locals'] ?></b>
    &middot; published references: <b><?= (int) $growth['references'] ?></b> &middot; disputed references open: <b><?= (int) $growth['disputed'] ?></b>
    &middot; <a href="<?= e(url('admin/moderation')) ?>">Reports and suspensions</a></p>
  <nav class="hl-tabs" aria-label="Status">
    <?php foreach (RMT_HOST_STATUSES as $s): ?>
      <a href="<?= e(url('admin/hosts?status=' . $s)) ?>"<?= $s === $status ? ' aria-current="page"' : '' ?>><?= e(ucfirst($s)) ?> (<?= (int) ($counts[$s] ?? 0) ?>)</a>
    <?php endforeach; ?>
  </nav>
  <?php if (!$rows): ?><p class="muted">Nothing here.</p><?php else: ?>
  <table>
    <thead><tr><th>Member</th><th>Application</th><th>Decision</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $h): $age = !empty($h['birthdate']) ? age_from((string) $h['birthdate']) : null; ?>
      <tr>
        <td><a href="<?= e(url('u/' . $h['username'])) ?>">@<?= e((string) $h['username']) ?></a><br>
          <span class="hint">Joined <?= e(ago((string) $h['joined_at'])) ?> &middot; <?= !empty($h['email_verified_at']) ? 'email confirmed' : 'email NOT confirmed' ?>
          &middot; <?= $age !== null ? 'age ' . (int) $age : 'no birthdate' ?> &middot; reports about them: <?= (int) $h['reports'] ?></span></td>
        <td><b><?= e((string) $h['dest_name']) ?></b><?= ($h['neighborhood'] ?? '') !== '' ? ', ' . e((string) $h['neighborhood']) : '' ?><br>
          <?= e(RMT_HOST_SPACES[$h['space_type']] ?? '') ?>, <?= (int) $h['max_guests'] ?> guests, <?= (int) $h['min_nights'] ?> to <?= (int) $h['max_nights'] ?> nights,
          <?= e(RMT_HOST_PETS[$h['pets']] ?? '') ?>, <?= e(RMT_HOST_SMOKING[$h['smoking']] ?? '') ?><br>
          <span style="white-space:pre-wrap"><?= e((string) $h['about_space']) ?></span>
          <?php if (($h['house_rules'] ?? '') !== ''): ?><br><span class="hint">Rules: <?= e((string) $h['house_rules']) ?></span><?php endif; ?>
          <?php if (($h['review_note'] ?? '') !== ''): ?><br><span class="hint">Last note: <?= e((string) $h['review_note']) ?></span><?php endif; ?></td>
        <td><form method="post" action="<?= e(url('admin/hosts/decide')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int) $h['user_id'] ?>">
          <input type="text" name="note" maxlength="500" placeholder="Note (shown to the member if rejected)" aria-label="Note" style="width:100%;min-height:36px">
          <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap">
            <?php if ($h['status'] !== 'approved'): ?><button class="btn btn-primary btn-sm" name="decision" value="approved">Approve</button><?php endif; ?>
            <?php if ($h['status'] !== 'rejected'): ?><button class="btn btn-ghost btn-sm" name="decision" value="rejected">Reject</button><?php endif; ?>
            <?php if ($h['status'] === 'approved'): ?><button class="btn btn-ghost btn-sm" name="decision" value="paused">Pause</button><?php endif; ?>
          </div></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <p class="hint" style="margin-top:14px">Approving shows the profile as "interested in hosting". It does not enable stays: rmt_stays_enabled() is off in code.</p>
</div>
