<?php /** @var array $p @var int $daysAsked */
$t = $p['totals'];
$fmt = static fn($v) => $v === null ? 'n/a' : (is_float($v) ? number_format($v, 1) . '%' : number_format((int) $v));
$labels = ['verified_humans' => 'Verified humans', 'suspected_bots' => 'Suspected bots', 'unverified' => 'Unverified',
           'returning_humans' => 'Returning', 'search_humans' => 'From search', 'referral_humans' => 'From referrals',
           'review_opened' => 'Review opened', 'review_submitted' => 'Review submitted', 'review_held' => 'Guest review held',
           'reviews_published' => 'Reviews published', 'signups' => 'Signups', 'confirmed' => 'Confirmed',
           'trips' => 'Trips', 'buddy_posts' => 'Buddy posts', 'messages' => 'Messages', 'alerts_confirmed' => 'Alerts confirmed'];
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Moderation</a> / <a href="<?= e(url('admin/funnel')) ?>">Funnel</a> / Proof</p>
  <h1 style="margin:.2rem 0 .4rem">Proof: real people, real actions</h1>
  <p class="hint" style="margin:0 0 6px">Every browser in the window is a verified human, a suspected bot or unverified, with the reason kept.
    Days are Pacific. Product numbers come from their own tables and leave house accounts out. Read
    <code>app/proof.php</code> and <code>docs/METRICS.md</code> for every rule.</p>
  <p class="hint" style="margin:0 0 18px">
    <?php foreach ([1 => 'Today', 7 => '7 days', 14 => '14 days', 31 => '31 days'] as $d => $lbl): ?>
      <a href="<?= e(url('admin/proof') . '?days=' . $d) ?>"<?= $d === $daysAsked ? ' style="font-weight:700"' : '' ?>><?= e($lbl) ?></a>
    <?php endforeach; ?>
    &middot; <?= number_format((int) $p['browsers_read']) ?> browsers from <?= number_format((int) $p['events_read']) ?> events
  </p>

  <div class="card" style="margin-bottom:18px"><div class="card-body">
    <table class="table proof-sum" style="width:100%">
      <tr><th>Verified humans</th><td><?= $fmt($t['verified_humans']) ?></td>
          <th>Signups</th><td><?= $fmt($t['signups']) ?> <span class="muted">(<?= $fmt($p['rates']['signup_rate_pct']) ?> of humans)</span></td></tr>
      <tr><th>Suspected bots</th><td><?= $fmt($t['suspected_bots']) ?></td>
          <th>Confirmed email</th><td><?= $fmt($t['confirmed']) ?> <span class="muted">(<?= $fmt($p['rates']['confirm_rate_pct']) ?>)</span></td></tr>
      <tr><th>Unverified</th><td><?= $fmt($t['unverified']) ?></td>
          <th>Reviews submitted / opened</th><td><?= $fmt($t['review_submitted']) ?> / <?= $fmt($t['review_opened']) ?></td></tr>
      <tr><th>Returning humans</th><td><?= $fmt($t['returning_humans']) ?></td>
          <th>Reviews published</th><td><?= $fmt($t['reviews_published']) ?></td></tr>
    </table>
  </div></div>

  <h2 style="font-size:1.1rem">By day</h2>
  <div style="overflow-x:auto;margin-bottom:22px">
    <table class="table" style="min-width:900px;font-size:.88rem">
      <thead><tr><th>Day</th><?php foreach ($labels as $k => $l): ?><th title="<?= e($p['definitions'][$k] ?? '') ?>"><?= e($l) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach (array_reverse($p['days']) as $row): ?>
        <tr><td style="white-space:nowrap"><?= e($row['day']) ?></td><?php foreach ($labels as $k => $l): ?><td><?= (int) $row[$k] ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="grid g-2" style="gap:18px;margin-bottom:22px">
    <div class="card"><div class="card-body">
      <h2 style="font-size:1.05rem;margin-top:0">Why browsers were called human</h2>
      <table class="table"><?php foreach ($p['reasons']['human'] as $why => $n): ?>
        <tr><td><?= e($why) ?><br><span class="muted" style="font-size:.85rem"><?= e($p['classification']['human_evidence'][$why] ?? '') ?></span></td><td><?= (int) $n ?></td></tr>
      <?php endforeach; ?></table>
    </div></div>
    <div class="card"><div class="card-body">
      <h2 style="font-size:1.05rem;margin-top:0">Why browsers were called bots</h2>
      <table class="table"><?php foreach ($p['reasons']['bot'] as $why => $n): ?>
        <tr><td><?= e($why) ?><br><span class="muted" style="font-size:.85rem"><?= e($p['classification']['bot_reasons'][$why] ?? '') ?></span></td><td><?= (int) $n ?></td></tr>
      <?php endforeach; ?></table>
    </div></div>
  </div>

  <h2 style="font-size:1.1rem">Verified humans by channel</h2>
  <table class="table" style="margin-bottom:22px">
    <thead><tr><th>Channel</th><th>Humans</th><th>Joined</th><th>Submitted a review</th></tr></thead>
    <?php foreach ($p['channels'] as $ch => $c): ?>
      <tr><td><?= e((string) $ch) ?></td><td><?= (int) $c['humans'] ?></td><td><?= (int) $c['joined'] ?></td><td><?= (int) $c['reviewed'] ?></td></tr>
    <?php endforeach; ?>
  </table>

  <h2 style="font-size:1.1rem">Definitions</h2>
  <dl class="proof-defs">
    <?php foreach ($p['definitions'] as $k => $d): ?><dt><?= e($labels[$k] ?? $k) ?></dt><dd class="muted"><?= e($d) ?></dd><?php endforeach; ?>
  </dl>
</div>
