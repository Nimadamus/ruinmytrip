<?php /** @var int $days @var array $zero @var array $low @var array $top @var array $clicks @var int $total */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Moderation</a> / Search</p>
  <h1 style="margin:.2rem 0 .4rem">Search</h1>
  <p class="muted" style="margin:0 0 6px">
    <?= (int) $total ?> searches in the last <?= (int) $days ?> days.
  </p>
  <p class="hint" style="margin:0 0 18px">
    The log holds a normalised query, how many suggestions it produced and when. No user, no
    session, no address — an analytics table does not need to know who, and one that does not hold
    it cannot leak it.
    <?php foreach ([7, 30, 90, 365] as $d): ?>
      <a href="<?= e(url('admin/search') . '?days=' . $d) ?>"<?= $d === $days ? ' style="font-weight:700"' : '' ?>><?= $d ?>d</a>
    <?php endforeach; ?>
  </p>

  <?php /* The point of the whole table. Sixty people asking for something we do not have is a
           decision waiting to be made, and this is the only place it says so out loud. */ ?>
  <section class="card" style="margin:0 0 20px"><div class="card-body">
    <h2 style="margin:0 0 4px;font-size:1.05rem">Found nothing (<?= count($zero) ?>)</h2>
    <p class="hint" style="margin:0 0 10px">A content and data queue, most asked first.</p>
    <?php if (!$zero): ?>
      <p class="muted" style="margin:0">Nothing yet. Either every search has landed, or nobody has searched.</p>
    <?php else: ?>
      <table style="width:100%;border-collapse:collapse;font-size:.93rem">
        <thead><tr style="text-align:left;border-bottom:1px solid #e9e9ee">
          <th style="padding:6px 10px 6px 0">Query</th>
          <th style="padding:6px 10px">Searches</th>
          <th style="padding:6px 10px">Last searched</th>
        </tr></thead>
        <tbody>
          <?php foreach ($zero as $r): ?>
            <tr style="border-bottom:1px solid #f1f1f5">
              <td style="padding:6px 10px 6px 0"><?= e((string) $r['query_norm']) ?></td>
              <td style="padding:6px 10px"><?= (int) $r['searches'] ?></td>
              <td style="padding:6px 10px" class="muted"><?= e(substr((string) $r['last_searched'], 0, 16)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div></section>

  <?php /* Almost as informative: one weak result is a gap wearing a result's clothes. */ ?>
  <?php if ($low): ?>
    <section class="card" style="margin:0 0 20px"><div class="card-body">
      <h2 style="margin:0 0 4px;font-size:1.05rem">Found barely anything (<?= count($low) ?>)</h2>
      <p class="hint" style="margin:0 0 10px">One or two suggestions. Often a gap rather than a hit.</p>
      <ul class="list-plain" style="margin:0">
        <?php foreach ($low as $r): ?>
          <li style="padding:4px 0;font-size:.92rem">
            <?= e((string) $r['query_norm']) ?>
            <span class="muted">— <?= (int) $r['searches'] ?> searches, best <?= (int) $r['best'] ?> result<?= (int) $r['best'] === 1 ? '' : 's' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div></section>
  <?php endif; ?>

  <?php if ($top): ?>
    <section class="card" style="margin:0 0 20px"><div class="card-body">
      <h2 style="margin:0 0 10px;font-size:1.05rem">Most searched</h2>
      <ul class="list-plain" style="margin:0">
        <?php foreach ($top as $r): ?>
          <li style="padding:4px 0;font-size:.92rem">
            <?= e((string) $r['query_norm']) ?>
            <span class="muted">— <?= (int) $r['searches'] ?> ·
              best <?= (int) $r['best'] ?> result<?= (int) $r['best'] === 1 ? '' : 's' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div></section>
  <?php endif; ?>

  <?php /* Which kind of thing people actually take, and how far down the list it sat. A high
           average position means the ranking is making people hunt. */ ?>
  <?php if ($clicks): ?>
    <section class="card" style="margin:0 0 20px"><div class="card-body">
      <h2 style="margin:0 0 10px;font-size:1.05rem">What gets clicked</h2>
      <ul class="list-plain" style="margin:0">
        <?php foreach ($clicks as $r): ?>
          <li style="padding:4px 0;font-size:.92rem">
            <?= e((string) $r['clicked_type']) ?>
            <span class="muted">— <?= (int) $r['clicks'] ?> clicks, average position <?= e((string) $r['avg_position']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div></section>
  <?php endif; ?>
</div>
