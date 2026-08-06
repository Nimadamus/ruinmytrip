<?php
/** @var array $subs @var array $stats */
$here = 'admin/alerts';
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Alert subscribers</h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <div class="grid g-4">
    <?php foreach ([['Confirmed', $stats['confirmed']], ['Awaiting confirmation', $stats['pending']],
                    ['Unsubscribed', $stats['gone']], ['Emails sent (7 days)', $stats['sent7']],
                    ['Saved trips', $stats['watchlists']]] as [$label, $n]): ?>
      <div class="card"><div class="card-body">
        <p class="eyebrow"><?= e($label) ?></p><b style="font-size:1.8rem"><?= number_format((int) $n) ?></b>
      </div></div>
    <?php endforeach; ?>
  </div>

  <div class="callout" style="margin-top:20px">
    <b>Sending is manual.</b> Alerts are built and sent by <code>scripts/send_alerts.php</code>, which refuses
    to build a batch when there is nothing new and cannot send the same warning to the same address twice
    (a unique index enforces it). Run it from a scheduled job when you are ready.
  </div>

  <h2 style="margin-top:30px;font-size:1.2rem">Subscriptions</h2>
  <p class="muted">Addresses are shown to you as the operator only; they are never rendered on a public page.</p>
  <div class="table-scroll"><table class="tbl">
    <thead><tr><th>Email</th><th>Destination</th><th>Frequency</th><th>Min severity</th><th>State</th><th>Source</th><th>Created</th></tr></thead>
    <tbody>
      <?php foreach ($subs as $s): ?>
        <tr>
          <td><?= e((string) $s['email']) ?></td>
          <td><?= e((string) ($s['dest_name'] ?? 'All')) ?></td>
          <td><?= e((string) $s['frequency']) ?></td>
          <td><?= e(rmt_severity_label((int) $s['min_severity'])) ?></td>
          <td>
            <?php if (!empty($s['unsubscribed_at'])): ?><span class="chip chip-muted">unsubscribed</span>
            <?php elseif (!empty($s['confirmed_at'])): ?><span class="chip">confirmed</span>
            <?php else: ?><span class="chip chip-muted">pending</span><?php endif; ?>
          </td>
          <td><?= e((string) ($s['source'] ?? '')) ?></td>
          <td><?= e(date('M j, Y', strtotime((string) $s['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$subs): ?><tr><td colspan="7" class="muted">No subscriptions yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <div style="height:50px"></div>
</div>
