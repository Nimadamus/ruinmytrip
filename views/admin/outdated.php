<?php
/** @var array $rows */
$here = 'admin/outdated';
$linkFor = static function (array $r): ?string {
    return match ((string) $r['target_type']) {
        'warning'      => url('w/' . (int) $r['target_id']),
        'destination'  => url('admin/destination/' . (int) $r['target_id']),
        'landing_page' => url('admin/page/' . (int) $r['target_id']),
        default        => null,
    };
};
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Outdated-information reports</h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <p class="muted" style="max-width:70ch">Readers flag content that has gone stale — a price that moved, a
    museum that reopened, a rule that changed. This is separate from abuse reports on purpose: it is a
    maintenance queue, not a moderation one.</p>

  <?php if (!$rows): ?><p class="muted">Nothing flagged.</p><?php endif; ?>

  <div class="table-scroll"><table class="tbl">
    <thead><tr><th>What</th><th>Note</th><th>Reporter</th><th>When</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): $link = $linkFor($r); ?>
        <tr>
          <td><?= e(str_replace('_', ' ', (string) $r['target_type'])) ?> #<?= (int) $r['target_id'] ?>
            <?php if ($link): ?><br><a href="<?= e($link) ?>">Open</a><?php endif; ?>
            <?php if ((string) $r['target_type'] === 'risk_section'): ?>
              <?php $sec = q_one('SELECT s.section_key, d.name, d.id did FROM destination_risk_sections s
                                  JOIN destinations d ON d.id = s.destination_id WHERE s.id = ?', [(int) $r['target_id']]); ?>
              <?php if ($sec): ?><br><a href="<?= e(url('admin/destination/' . (int) $sec['did'] . '#s-' . $sec['section_key'])) ?>">
                <?= e((string) $sec['name']) ?> · <?= e((string) $sec['section_key']) ?></a><?php endif; ?>
            <?php endif; ?>
          </td>
          <td><?= e((string) ($r['note'] ?? '')) ?></td>
          <td><?= $r['username'] ? '@' . e((string) $r['username']) : '<span class="muted">anonymous</span>' ?></td>
          <td><?= e(ago((string) $r['created_at'])) ?></td>
          <td><span class="chip <?= $r['status'] === 'open' ? 'chip-muted' : '' ?>"><?= e((string) $r['status']) ?></span></td>
          <td>
            <form method="post" action="<?= e(url('admin/outdated/' . (int) $r['id'])) ?>">
              <?= csrf_field() ?>
              <?php if ($r['status'] === 'open'): ?>
                <button class="btn btn-primary btn-sm" name="action" value="resolve">Mark checked</button>
              <?php else: ?>
                <button class="btn btn-ghost btn-sm" name="action" value="reopen">Reopen</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <div style="height:50px"></div>
</div>
