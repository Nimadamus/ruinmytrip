<?php /** @var array $posts @var ?array $me @var ?string $type @var ?string $label */
$fmtRange = static fn(array $p): string =>
    date('M j', strtotime((string) $p['date_from'])) . ' to ' . date('M j, Y', strtotime((string) $p['date_to']));
$newPath = '/buddies/new' . ($type ? '?type=' . $type : '');
$newHref = url($me ? ltrim($newPath, '/') : 'login?return=' . rawurlencode($newPath)); ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <?php if ($type): ?><a href="<?= e(url('buddies')) ?>">Travel buddies</a> / <?= e($label) ?><?php else: ?>Travel buddies<?php endif; ?></p>
  <div class="section-head">
    <div><h1 style="margin:0"><?= $type === 'cruise' ? 'Find a cruise buddy' : ($type ? 'Find a ' . e(strtolower($label)) . ' buddy' : 'Find a travel buddy') ?></h1>
      <p class="hint" style="margin:.3rem 0 0">Members posting the cruise or trip they are taking and the kind of company they want.
        Put your hand up, and once the poster accepts you, you can message each other.</p></div>
    <a class="btn btn-accent btn-sm" href="<?= e($newHref) ?>">Post your trip</a>
  </div>
  <nav style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0" aria-label="Kind of trip">
    <a class="chip<?= $type === null ? ' active' : '' ?>" href="<?= e(url('buddies')) ?>">All</a>
    <?php foreach (RMT_BUDDY_TYPES as $k => $v): ?>
      <a class="chip<?= $type === $k ? ' active' : '' ?>" href="<?= e(url('buddies/' . str_replace('_', '-', $k))) ?>"><?= e($v) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="callout"><b>Company for a trip, not dating.</b> 18+ only. Nobody can message you until you accept them, and you can block or <a href="<?= e(url('report')) ?>">report</a> anyone. Meet in public before you travel together. <a href="<?= e(url('safety')) ?>">Safety guide</a></div>
  <?php if (!$posts): ?>
    <div class="empty-cta" style="margin:14px 0 50px">
      <h3>Nobody has posted <?= $type ? 'a ' . e(strtolower($label)) : 'a trip' ?> here yet.</h3>
      <p class="muted" style="margin:0">Booked a cruise and would rather not go alone? Planning a trip and want someone to split the cabin, the car or the room? Post it and be the first one people find.</p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e($newHref) ?>">Post your trip</a></p>
    </div>
  <?php else: ?>
    <div class="grid g-2" style="padding:14px 0 50px">
      <?php foreach ($posts as $p): ?>
        <article class="card"><div class="card-body">
          <span class="chip"><?= e(RMT_BUDDY_TYPES[$p['trip_type']] ?? 'Trip') ?></span>
          <?php if (!empty($p['dest_name'])): ?><span class="chip"><?= e((string) $p['dest_name']) ?></span><?php endif; ?>
          <h3 style="margin:.4rem 0 .2rem"><a href="<?= e(url('buddy/' . (int) $p['id'])) ?>"><?= e((string) $p['title']) ?></a></h3>
          <p class="muted" style="margin:0"><?= e((string) $p['where_text']) ?> &middot; <?= e($fmtRange($p)) ?><?= (int) $p['flexible'] ? ' (flexible)' : '' ?></p>
          <p style="margin:.5rem 0"><?= e(mb_strimwidth((string) $p['description'], 0, 160, '…')) ?></p>
          <div class="meta-row">Posted by @<?= e($p['author']['username'] ?? '') ?> &middot;
            looking for <?= (int) $p['spots'] ?> &middot; <?= (int) $p['interest_count'] ?> interested<?php if ($p['budget'] !== 'any'): ?> &middot; <?= e(RMT_BUDDY_BUDGETS[$p['budget']] ?? '') ?><?php endif; ?></div>
        </div></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
