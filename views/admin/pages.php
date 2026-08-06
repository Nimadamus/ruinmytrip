<?php
/** @var array $rows */
$here = 'admin/pages';
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Guide pages</h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <p class="muted" style="max-width:70ch">These are the search-visible editorial pages. A page must have at
    least 600 characters of real content before it can be published — a draft 404s for the public, so an
    unfinished page is never indexed.</p>
  <p><a class="btn btn-accent" href="<?= e(url('admin/page/new')) ?>">New guide page</a></p>

  <div class="table-scroll"><table class="tbl">
    <thead><tr><th>URL</th><th>H1</th><th>Template</th><th>Destination</th><th>Status</th><th>Reviewed</th><th>Views</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= e(url($r['slug'])) ?>">/<?= e($r['slug']) ?></a></td>
          <td><?= e($r['h1']) ?></td>
          <td><?= e(rmt_landing_template_label((string) $r['template'])) ?></td>
          <td><?= e((string) ($r['dest_name'] ?? '—')) ?></td>
          <td><span class="chip <?= $r['status'] === 'published' ? '' : 'chip-muted' ?>"><?= e($r['status']) ?></span></td>
          <td><?= e((string) ($r['last_reviewed_at'] ?? '')) ?></td>
          <td><?= (int) $r['view_count'] ?></td>
          <td><a href="<?= e(url('admin/page/' . (int) $r['id'])) ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="muted">No guide pages yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <div style="height:50px"></div>
</div>
