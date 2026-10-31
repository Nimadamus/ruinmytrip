<?php
/** /stay/{id}: one stay, for its guest and host only. @var array $s @var string $party @var array $me */
$hosting = $party === 'host';
$other = $hosting ? $s['guest_username'] : $s['host_username'];
$otherName = $hosting ? ($s['guest_name'] ?: '@' . $s['guest_username']) : ($s['host_name'] ?: '@' . $s['host_username']);
$otherActive = ($hosting ? $s['guest_status'] : $s['host_status']) === 'active';
$started = (string) $s['date_from'] <= date('Y-m-d');
$over = (string) $s['date_to'] < date('Y-m-d');
$nights = rmt_stay_nights((string) $s['date_from'], (string) $s['date_to']);
$btn = static function (string $action, string $label, bool $primary = true, string $confirm = '') use ($s): void { ?>
  <form method="post" action="<?= e(url('stay/' . (int) $s['id'] . '/' . $action)) ?>"<?= $confirm !== '' ? ' data-confirm-submit="' . e($confirm) . '"' : '' ?>><?= csrf_field() ?>
    <button class="btn <?= $primary ? 'btn-primary' : 'btn-ghost' ?>"><?= e($label) ?></button></form>
<?php }; ?>
<div class="wrap hl-sec" style="max-width:760px">
  <p class="crumbs"><a href="<?= e(url('stays')) ?>">Your stays</a> / Stay</p>
  <h1 class="hl-h2" style="font-size:1.7rem"><?= $hosting ? e((string) $otherName) . ' asked to stay with you' : 'Your stay with ' . e((string) $otherName) ?></h1>
  <p><span class="hl-badge<?= in_array($s['state'], ['accepted', 'confirmed'], true) ? ' hl-badge-ok' : '' ?>"><?= e(RMT_STAY_STATE_LABELS[$s['state']] ?? $s['state']) ?></span></p>
  <section class="hl-form" style="margin:12px 0">
    <dl class="hl-facts">
      <dt>Dates</dt><dd><?= e(date('D M j', strtotime((string) $s['date_from']))) ?> to <?= e(date('D M j, Y', strtotime((string) $s['date_to']))) ?> (<?= $nights ?> <?= $nights === 1 ? 'night' : 'nights' ?>)</dd>
      <dt>Guests</dt><dd><?= (int) $s['guests'] ?></dd>
      <dt>Where</dt><dd><?= e((string) $s['dest_name']) ?><?= ($s['neighborhood'] ?? '') !== '' ? ', ' . e((string) $s['neighborhood']) . ' area' : '' ?></dd>
      <dt><?= $hosting ? 'Guest' : 'Host' ?></dt><dd><?php if ($otherActive): ?><a href="<?= e(url('u/' . $other)) ?>"><?= e((string) $otherName) ?></a>
        &middot; <a href="<?= e(url('u/' . $other . '/references')) ?>">references</a><?php else: ?>Deleted member<?php endif; ?></dd>
    </dl>
    <h2 style="font-size:1rem;margin:14px 0 4px"><?= $hosting ? 'Their introduction' : 'Your introduction' ?></h2>
    <p style="white-space:pre-wrap;margin:0"><?= e((string) $s['intro']) ?></p>
  </section>

  <?php if ($otherActive && in_array($s['state'], ['accepted', 'confirmed'], true)): ?>
    <p class="hl-callout hl-callout-ok">Messages are open. Agree where and when to meet in <a href="<?= e(url('messages/' . $other)) ?>">your conversation with <?= e((string) $otherName) ?></a>. RuinMyTrip never stores an address.</p>
  <?php endif; ?>

  <div style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0">
    <?php if ($hosting && $s['state'] === 'requested' && !$started): ?>
      <?php $btn('accept', 'Accept'); $btn('decline', 'Decline', false); ?>
    <?php elseif ($hosting && $s['state'] === 'requested'): ?>
      <?php $btn('decline', 'Decline', false); ?>
    <?php endif; ?>
    <?php if (!$hosting && $s['state'] === 'accepted' && !$started): ?><?php $btn('confirm', 'Confirm the stay'); ?><?php endif; ?>
    <?php if (in_array($s['state'], ['requested', 'accepted', 'confirmed'], true) && !$started): ?>
      <?php $btn('cancel', $hosting && $s['state'] === 'requested' ? 'Cancel' : 'Cancel the stay', false, 'Cancel this stay? The other person is told.'); ?>
    <?php endif; ?>
  </div>

  <?php if ($s['state'] === 'confirmed' && $over && $otherActive): ?>
    <p class="hl-callout hl-callout-ok">The stay is over. <a href="<?= e(url('u/' . $other . '/references')) ?>">Leave a reference for <?= e((string) $otherName) ?></a>.</p>
  <?php endif; ?>

  <section class="hl-trust" style="margin-top:16px">
    <div><h3>Something wrong?</h3><p>If you are in danger, call the local emergency number first (112, 911 or 999). Then
      <a href="<?= e(url('report?target_type=stay&target_id=' . (int) $s['id'])) ?>">report this stay</a>. A moderator reviews every report.</p></div>
    <div><h3>Staying safe</h3><p>Stays are free, so never send or accept money. Tell someone where you are staying and keep a way to leave. <a href="<?= e(url('hosts-locals/safety')) ?>">Safety guide</a>.</p></div>
  </section>
</div>
