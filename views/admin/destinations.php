<?php
/** @var array $rows @var string $q */
$here = 'admin/destinations';
$totalSections = count(rmt_risk_section_defs());
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Destinations</h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <form class="filters" method="get" action="<?= e(url('admin/destinations')) ?>">
    <div style="flex:1;min-width:220px">
      <label for="dq">Search</label>
      <input id="dq" type="search" name="q" style="width:100%" value="<?= e($q) ?>" placeholder="Name or country">
    </div>
    <button class="btn btn-primary btn-sm" type="submit">Search</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/destinations')) ?>">Reset</a>
  </form>

  <p class="muted"><?= count($rows) ?> destinations. “Risk sections” is how much of the report spine is written.</p>
  <div class="table-scroll"><table class="tbl">
    <thead><tr><th>Destination</th><th>Risk level</th><th>Risk sections</th><th>FAQs</th><th>Warnings</th><th>Guides</th><th>Featured</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= e(url('d/' . $r['slug'])) ?>"><?= e($r['name']) ?></a>
            <span class="muted">, <?= e($r['country']) ?></span></td>
          <td><?= !empty($r['risk_level']) ? e(rmt_risk_level_label((int) $r['risk_level'])) : '<span class="muted">—</span>' ?></td>
          <td><?= (int) $r['sections'] ?> / <?= $totalSections ?></td>
          <td><?= (int) $r['faqs'] ?></td>
          <td><?= (int) $r['warnings'] ?></td>
          <td><?= (int) $r['pages'] ?></td>
          <td><?= !empty($r['featured']) ? 'Yes' : '' ?></td>
          <td><a href="<?= e(url('admin/destination/' . (int) $r['id'])) ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <div style="height:50px"></div>
</div>
