<?php
/**
 * Everything waiting for a human, on one screen.
 * @var array $stats @var array $queue @var array $reports @var array $gaps
 */
$here = 'admin';
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Admin</h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <div class="grid g-4">
    <?php
    $tiles = [
      ['Warnings awaiting review', $stats['pending_warnings'], 'admin/warnings?status=pending'],
      ['Revisions requested',      $stats['revision_warnings'], 'admin/warnings?status=needs_revision'],
      ['Business responses',       $stats['pending_responses'], 'admin/responses'],
      ['Outdated-info flags',      $stats['open_outdated'],     'admin/outdated'],
      ['Open abuse reports',       $stats['open_reports'],      'admin/reports'],
      ['Published warnings',       $stats['approved_warnings'], 'admin/warnings?status=approved'],
      ['Destinations with a report', $stats['covered'] . ' / ' . $stats['destinations'], 'admin/destinations'],
      ['Alert subscribers',        $stats['subscribers'],       'admin/alerts'],
    ];
    foreach ($tiles as [$label, $value, $link]): ?>
      <a class="card" href="<?= e(url($link)) ?>"><div class="card-body">
        <p class="eyebrow"><?= e($label) ?></p>
        <b style="font-size:1.8rem"><?= is_int($value) ? number_format($value) : e((string) $value) ?></b>
      </div></a>
    <?php endforeach; ?>
  </div>

  <h2 style="margin-top:34px;font-size:1.25rem">Moderation queue</h2>
  <?php if ($queue): ?>
    <p class="muted">Oldest first, so nobody's report sits behind a newer one.</p>
    <?php foreach ($queue as $w): include BASE_PATH . '/views/admin/_queue_row.php'; endforeach; ?>
    <p><a class="btn btn-ghost" href="<?= e(url('admin/warnings')) ?>">Open the full queue</a></p>
  <?php else: ?>
    <p class="muted">Nothing waiting. The queue is clear.</p>
  <?php endif; ?>

  <h2 style="margin-top:34px;font-size:1.25rem">Content gaps</h2>
  <p class="muted">Destinations with the least reviewed risk content, most-warned first — the best place to
    spend the next writing session.</p>
  <div class="table-scroll"><table class="tbl">
    <thead><tr><th>Destination</th><th>Risk sections</th><th>Warnings</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($gaps as $g): ?>
        <tr>
          <td><a href="<?= e(url('d/' . $g['slug'])) ?>"><?= e($g['name']) ?></a></td>
          <td><?= (int) $g['sections'] ?> / 13</td>
          <td><?= (int) $g['warnings'] ?></td>
          <td><a href="<?= e(url('admin/destination/' . (int) $g['id'])) ?>">Edit report</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>

  <?php if ($reports): ?>
    <h2 style="margin-top:34px;font-size:1.25rem">Open abuse reports</h2>
    <div class="table-scroll"><table class="tbl">
      <thead><tr><th>Reason</th><th>Target</th><th>Reporter</th><th>When</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($reports as $r): ?>
          <tr><td><?= e($r['reason']) ?></td><td><?= e($r['target_type']) ?> #<?= (int) $r['target_id'] ?></td>
            <td>@<?= e($r['reporter']) ?></td><td><?= e(ago((string) $r['created_at'])) ?></td>
            <td><a href="<?= e(url('admin/reports')) ?>">Resolve</a></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
  <div style="height:50px"></div>
</div>
