<?php /** @var array $rows */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Moderation</a> / Destinations</p>
  <h1 style="margin:.2rem 0 .4rem">Destinations</h1>
  <?php $ready = count(array_filter($rows, static fn($r) => $r['ready'])); ?>
  <p class="muted" style="margin:0 0 6px"><?= count($rows) ?> destinations · <?= $ready ?> with enough behind them to carry a discovery page.</p>
  <?php $cready = count(array_filter($rows, static fn($r) => $r['community_ready'])); ?>
  <p class="hint" style="margin:0 0 18px">
    <strong>Data</strong> means five or more places across at least two kinds with coordinates on
    most of them. <strong>Community</strong> means three or more places with enough traveler
    reviews to be ranked, written by at least three different people &mdash; forty reviews from one
    person is not a community. They are different questions and a destination can pass one and fail
    the other; today <?= $cready ?> pass the second. Nothing is published or hidden on the strength
    of either.
  </p>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.92rem">
      <thead><tr style="text-align:left;border-bottom:1px solid #e9e9ee">
        <th style="padding:6px 10px 6px 0">Destination</th>
        <th style="padding:6px 8px">Places</th><th style="padding:6px 8px">Htl</th>
        <th style="padding:6px 8px">Rst</th><th style="padding:6px 8px">Attr</th>
        <th style="padding:6px 8px">Located</th><th style="padding:6px 8px">Hoods</th>
        <th style="padding:6px 8px">Reviews</th><th style="padding:6px 8px">People</th>
        <th style="padding:6px 8px">Reviewed</th><th style="padding:6px 8px">Rankable</th>
        <th style="padding:6px 8px">Photos</th><th style="padding:6px 8px">Last review</th>
        <th style="padding:6px 8px">Data</th><th style="padding:6px 8px">Community</th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): if ($r['places'] === 0 && $r['reviews'] === 0) continue; ?>
          <tr style="border-bottom:1px solid #f1f1f5">
            <td style="padding:6px 10px 6px 0"><a href="<?= e(url('d/'.$r['slug'])) ?>"><?= e($r['name']) ?></a>
              <span class="muted"><?= e((string) $r['country']) ?></span></td>
            <td style="padding:6px 8px"><?= $r['places'] ?></td>
            <td style="padding:6px 8px"><?= $r['hotels'] ?: '<span class="hint">–</span>' ?></td>
            <td style="padding:6px 8px"><?= $r['restaurants'] ?: '<span class="hint">–</span>' ?></td>
            <td style="padding:6px 8px"><?= $r['attractions'] ?: '<span class="hint">–</span>' ?></td>
            <td style="padding:6px 8px"><?= $r['located'] ?></td>
            <td style="padding:6px 8px"><?= $r['neighborhoods'] ?: '<span class="hint">–</span>' ?></td>
            <td style="padding:6px 8px"><?= $r['reviews'] ?></td>
            <td style="padding:6px 8px"><?= $r['reviewers'] ?: '<span class="hint">0</span>' ?></td>
            <td style="padding:6px 8px"><?= $r['places_reviewed'] ?: '<span class="hint">0</span>' ?></td>
            <td style="padding:6px 8px"><?= $r['places_rankable'] ?: '<span class="hint">0</span>' ?></td>
            <td style="padding:6px 8px"><?= $r['photos'] ?: '<span class="hint">0</span>' ?></td>
            <td style="padding:6px 8px" class="muted"><?= e(substr((string) $r['last_review_at'], 0, 10)) ?></td>
            <td style="padding:6px 8px"><?= $r['ready'] ? '<strong>yes</strong>' : '<span class="hint">not yet</span>' ?></td>
            <td style="padding:6px 8px"><?= $r['community_ready'] ? '<strong>yes</strong>' : '<span class="hint">not yet</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
