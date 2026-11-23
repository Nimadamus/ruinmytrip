<?php
/**
 * The group page a shared trip link opens (app/trip_group.php).
 *
 * @var array $t @var ?array $me @var ?array $guest @var array $viewer @var array $people
 * @var array $items @var array $done @var array $claims @var array $options @var ?array $tax @var string $link
 */
$tgIn = $viewer['kind'] !== null;
$tgOwner = $viewer['kind'] === 'owner';
$tgBase = 'trip/join/' . (string) $t['group_code'];
$tgDates = '';
if (!empty($t['date_from'])) {
    $tgDates = date('j M Y', (int) strtotime((string) $t['date_from']));
    if (!empty($t['date_to']) && $t['date_to'] !== $t['date_from']) $tgDates .= ' to ' . date('j M Y', (int) strtotime((string) $t['date_to']));
}
$tgGroups = [];
foreach ($items as $i) $tgGroups[$i['group']][] = $i;
$tgDoneN = count(array_filter($items, static fn(array $i) => isset($done[$i['key']])));
?>
<div class="wrap" style="max-width:760px">
  <section style="margin:24px 0 8px">
    <p class="eyebrow" style="margin:0">Group trip<?= !empty($t['dest_name']) ? ' · ' . e((string) $t['dest_name']) : '' ?></p>
    <h1 style="margin:4px 0 6px"><?= e((string) $t['title']) ?></h1>
    <p class="hint" style="margin:0"><?= $tgDates !== '' ? e($tgDates) . ' · ' : 'Dates not settled yet · ' ?><?= count($people) ?> in: <?= e(implode(', ', array_column($people, 'name'))) ?></p>
  </section>

  <?php if (!$tgIn): ?>
    <section class="card" id="join" style="margin:18px 0;border-left:4px solid #0f766e"><div class="card-body">
      <h2 class="h-card" style="margin:0 0 6px">Join this trip</h2>
      <p class="hint" style="margin:0 0 10px">Put your first name and you can tick things off, say what you will sort out and vote on dates. No account, no password.</p>
      <form method="post" action="<?= e(url($tgBase)) ?>" class="ma-form">
        <?= csrf_field() ?>
        <div class="ma-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="ma-row" style="grid-template-columns:1fr;max-width:320px">
          <label>Your first name<input type="text" name="name" required maxlength="40" autocomplete="given-name"></label>
        </div>
        <button class="btn btn-accent btn-sm" type="submit">I'm in</button>
      </form>
    </div></section>
  <?php endif; ?>

  <?php if ($tax): ?>
    <section class="card" style="margin:18px 0"><div class="card-body">
      <h2 class="h-card" style="margin:0 0 6px">Tourist tax for the group</h2>
      <?php if ($tax['total'] !== null): ?>
        <p style="margin:0 0 6px">About <b><?= e($tax['total']) ?></b> for <?= (int) $tax['people'] ?> <?= $tax['people'] === 1 ? 'person' : 'people' ?> over <?= (int) $tax['nights'] ?> <?= $tax['nights'] === 1 ? 'night' : 'nights' ?>, so <?= e((string) $tax['per_person']) ?> each.</p>
        <p class="hint" style="margin:0">Rate in <?= e($tax['city']) ?>: <?= e($tax['rate']) ?>. Worked out on <?= (int) ($tax['rooms'] ?? 1) ?> <?= ($tax['rooms'] ?? 1) === 1 ? 'room' : 'rooms' ?>; <a href="<?= e($tax['calc']) ?>">change the details</a>.</p>
      <?php else: ?>
        <p style="margin:0 0 6px">In <?= e($tax['city']) ?>: <?= e($tax['rate']) ?>.</p>
        <p class="hint" style="margin:0"><?= $tax['nights'] > 0 ? 'Put in your room price to get the total for ' . (int) $tax['people'] . ' people over ' . (int) $tax['nights'] . ' nights: ' : 'Settle the dates and put in your room price for the total: ' ?><a href="<?= e($tax['calc']) ?>">tourist tax calculator</a>.</p>
      <?php endif; ?>
    </div></section>
  <?php endif; ?>

  <?php if ($options || $tgOwner): ?>
    <section class="card" id="dates" style="margin:18px 0"><div class="card-body">
      <h2 class="h-card" style="margin:0 0 6px">When do we go?</h2>
      <?php if (!$options): ?><p class="hint" style="margin:0 0 10px">Add up to four date options and the group votes.</p><?php endif; ?>
      <?php foreach ($options as $o): ?>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 8px">
          <span><b><?= e(date('j M', (int) strtotime((string) $o['date_from']))) ?> to <?= e(date('j M Y', (int) strtotime((string) $o['date_to']))) ?></b> · <?= (int) $o['votes'] ?> <?= (int) $o['votes'] === 1 ? 'vote' : 'votes' ?></span>
          <?php if ($tgIn): ?>
            <form method="post" action="<?= e(url($tgBase . '/vote')) ?>"><?= csrf_field() ?>
              <input type="hidden" name="option" value="<?= (int) $o['id'] ?>"><input type="hidden" name="yes" value="<?= $o['mine'] ? '0' : '1' ?>">
              <button class="btn <?= $o['mine'] ? 'btn-primary' : 'btn-ghost' ?> btn-sm" type="submit"><?= $o['mine'] ? 'Works for me ✓' : 'Works for me' ?></button></form>
          <?php endif; ?>
          <?php if ($tgOwner): ?>
            <form method="post" action="<?= e(url($tgBase . '/dates')) ?>"><?= csrf_field() ?>
              <input type="hidden" name="pick" value="<?= (int) $o['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Go with these</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($tgOwner && count($options) < RMT_TG_MAX_OPTIONS): ?>
        <form method="post" action="<?= e(url($tgBase . '/dates')) ?>" class="ma-form" style="margin-top:8px"><?= csrf_field() ?>
          <div class="ma-row" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));max-width:420px">
            <label>From<input type="date" name="date_from" required min="<?= e(date('Y-m-d')) ?>"></label>
            <label>To<input type="date" name="date_to" required min="<?= e(date('Y-m-d')) ?>"></label>
          </div>
          <button class="btn btn-ghost btn-sm" type="submit">Add these dates</button>
        </form>
      <?php endif; ?>
    </div></section>
  <?php endif; ?>

  <?php if ($items): ?>
    <section class="card tcl" id="checklist" style="margin:18px 0"><div class="card-body">
      <h2 class="h-card" style="margin:0 0 4px">Before you go to <?= e((string) $t['dest_name']) ?></h2>
      <p class="hint" style="margin:0 0 12px"><?= $tgDoneN ?> of <?= count($items) ?> sorted. Each item comes from an official source.<?= $tgIn ? ' Say you will do one so nobody does it twice.' : '' ?></p>
      <?php foreach ($tgGroups as $g => $list): ?>
        <h3 class="tcl-group"><?= e($g) ?></h3>
        <ul class="tcl-list">
          <?php foreach ($list as $i): $isDone = isset($done[$i['key']]); $who = $claims[$i['key']] ?? ''; ?>
            <li class="tcl-item<?= $isDone ? ' is-done' : '' ?>">
              <?php if ($tgIn): ?>
                <form method="post" action="<?= e(url($tgBase . '/item')) ?>"><?= csrf_field() ?>
                  <input type="hidden" name="item" value="<?= e($i['key']) ?>"><input type="hidden" name="act" value="<?= $isDone ? 'undone' : 'done' ?>">
                  <button class="tcl-box" type="submit" aria-pressed="<?= $isDone ? 'true' : 'false' ?>" aria-label="<?= $isDone ? 'Mark as not done' : 'Mark as done' ?>: <?= e($i['title']) ?>"><?= $isDone ? '&#10003;' : '' ?></button>
                </form>
              <?php endif; ?>
              <div class="tcl-text">
                <p class="tcl-title"><?= e($i['title']) ?></p>
                <?php if ($i['detail'] !== ''): ?><p class="tcl-detail"><?= e($i['detail']) ?></p><?php endif; ?>
                <a class="tcl-src" href="<?= e($i['url']) ?>"<?= str_starts_with($i['url'], url('')) ? '' : ' rel="noopener" target="_blank"' ?>><?= e($i['source']) ?></a>
                <?php if ($who !== ''): ?>
                  <p class="hint" style="margin:4px 0 0"><?= e($who) ?> is sorting this<?php if ($tgIn && $who === $viewer['name']): ?>
                    <form method="post" action="<?= e(url($tgBase . '/item')) ?>" style="display:inline"><?= csrf_field() ?>
                      <input type="hidden" name="item" value="<?= e($i['key']) ?>"><input type="hidden" name="act" value="unclaim">
                      <button class="btn btn-link btn-sm" type="submit">not me</button></form><?php endif; ?></p>
                <?php elseif ($tgIn && !$isDone): ?>
                  <form method="post" action="<?= e(url($tgBase . '/item')) ?>" style="margin:4px 0 0"><?= csrf_field() ?>
                    <input type="hidden" name="item" value="<?= e($i['key']) ?>"><input type="hidden" name="act" value="claim">
                    <button class="btn btn-ghost btn-sm" type="submit">I'll do this</button></form>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    </div></section>
  <?php endif; ?>

  <?php if ($tgIn): ?>
    <section class="card" style="margin:18px 0"><div class="card-body">
      <h2 class="h-card" style="margin:0 0 6px">Bring the rest of the group</h2>
      <p class="hint" style="margin:0 0 10px">Same link for everyone.</p>
      <p style="margin:0;display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($link) ?>">Copy link</button>
        <a class="btn btn-ghost btn-sm" rel="noopener" target="_blank" href="https://wa.me/?text=<?= rawurlencode('Our trip plan: ' . $link) ?>">WhatsApp</a>
      </p>
      <?php if ($viewer['kind'] === 'guest'): ?>
        <p class="hint" style="margin:14px 0 6px">This browser remembers you. To keep the trip on your phone and laptop, save it to a free account.</p>
        <form method="post" action="<?= e(url($tgBase . '/save')) ?>"><?= csrf_field() ?>
          <button class="btn btn-accent btn-sm" type="submit"><?= $me ? 'Save to my account' : 'Save it to an account' ?></button></form>
      <?php endif; ?>
    </div></section>
  <?php endif; ?>
</div>
