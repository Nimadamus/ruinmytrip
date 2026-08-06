<?php
/** @var array $funnel @var array $totals @var array $topDests @var array $searches @var int $days @var array $affiliates */
$here = 'admin/analytics';
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Analytics</h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <div class="filter-chips">
    <?php foreach ([7, 30, 90, 365] as $dd): ?>
      <a class="<?= $days === $dd ? 'on' : '' ?>" href="<?= e(url('admin/analytics?days=' . $dd)) ?>">Last <?= $dd ?> days</a>
    <?php endforeach; ?>
  </div>

  <h2 style="font-size:1.2rem;margin-top:20px">Funnel</h2>
  <p class="muted" style="max-width:70ch">Each step counts distinct visitors over the window. Steps are measured
    independently rather than as strict subsets — someone arriving on a destination page straight from search
    never had a homepage step, and pretending otherwise would understate the top of the funnel.</p>

  <?php $max = max(1, (int) ($funnel[0]['n'] ?? 1)); ?>
  <?php foreach ($funnel as $step): ?>
    <div class="funnel-row">
      <div><b><?= e($step['label']) ?></b></div>
      <div class="funnel-bar"><i style="width:<?= max(0.5, min(100, $step['n'] * 100 / $max)) ?>%"></i></div>
      <div><b><?= number_format((int) $step['n']) ?></b> <span class="muted"><?= e((string) $step['pct']) ?>%</span></div>
    </div>
  <?php endforeach; ?>

  <h2 style="font-size:1.2rem;margin-top:30px">Events</h2>
  <div class="table-scroll"><table class="tbl">
    <thead><tr><th>Event</th><th>Distinct visitors</th><th>Total</th></tr></thead>
    <tbody>
      <?php foreach (RMT_EVENTS as $k => $label): $row = $totals[$k] ?? ['hits' => 0, 'visitors' => 0]; ?>
        <tr><td><?= e($label) ?> <span class="hint"><?= e($k) ?></span></td>
          <td><?= number_format((int) $row['visitors']) ?></td>
          <td><?= number_format((int) $row['hits']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>

  <div class="grid g-2" style="margin-top:30px">
    <div>
      <h2 style="font-size:1.2rem">Most-viewed destinations</h2>
      <div class="table-scroll"><table class="tbl">
        <thead><tr><th>Destination</th><th>Visitors</th><th>Views</th></tr></thead>
        <tbody>
          <?php foreach ($topDests as $t): ?>
            <tr><td><a href="<?= e(url('d/' . $t['slug'])) ?>"><?= e((string) $t['name']) ?></a></td>
              <td><?= number_format((int) $t['visitors']) ?></td><td><?= number_format((int) $t['hits']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$topDests): ?><tr><td colspan="3" class="muted">No destination views recorded in this window.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
    <div>
      <h2 style="font-size:1.2rem">What people searched for</h2>
      <p class="muted" style="font-size:.88rem">Searches returning 0 results are the clearest signal of what to
        write next.</p>
      <div class="table-scroll"><table class="tbl">
        <thead><tr><th>Query</th><th>Results</th><th>Times</th></tr></thead>
        <tbody>
          <?php foreach ($searches as $s): ?>
            <tr <?= (int) ($s['results'] ?? 1) === 0 ? 'style="background:#fff7ed"' : '' ?>>
              <td><?= e($s['q']) ?></td>
              <td><?= $s['results'] === null ? '—' : (int) $s['results'] ?></td>
              <td><?= (int) $s['c'] ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$searches): ?><tr><td colspan="3" class="muted">No searches recorded in this window.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <?php if ($affiliates): ?>
    <h2 style="font-size:1.2rem;margin-top:30px">Affiliate clicks (all time)</h2>
    <div class="table-scroll"><table class="tbl">
      <thead><tr><th>Link</th><th>Provider</th><th>Kind</th><th>Clicks</th></tr></thead>
      <tbody>
        <?php foreach ($affiliates as $a2): ?>
          <tr><td><?= e((string) $a2['label']) ?></td><td><?= e((string) $a2['provider']) ?></td>
            <td><?= e((string) $a2['kind']) ?></td><td><?= (int) $a2['click_count'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>

  <p class="hint" style="margin-top:24px">First-party only. The visitor key is a salted hash that rotates every
    24 hours — enough to stitch one visit into a funnel, useless for following anyone across days. No raw IP,
    no user agent, no third-party tag.</p>
  <div style="height:50px"></div>
</div>
