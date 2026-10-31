<?php
/** /stays: requests sent and received. @var array $stays @var array $me */
$in = array_values(array_filter($stays, static fn($s) => (int) $s['host_id'] === (int) $me['id']));
$out = array_values(array_filter($stays, static fn($s) => (int) $s['guest_id'] === (int) $me['id']));
$row = static function (array $s, bool $hosting): void {
    $who = $hosting ? $s['guest_username'] : $s['host_username'];
    $nm = $hosting ? ($s['guest_name'] ?: '@' . $s['guest_username']) : ($s['host_name'] ?: '@' . $s['host_username']);
    $av = $hosting ? $s['guest_avatar'] : $s['host_avatar']; ?>
  <li class="card" style="margin-bottom:8px"><a href="<?= e(url('stay/' . (int) $s['id'])) ?>" style="color:inherit;text-decoration:none">
    <div class="card-body" style="padding:12px 16px;display:flex;gap:10px;align-items:center">
      <img class="avatar" src="<?= e(avatar_url($av)) ?>" alt="">
      <div style="flex:1;min-width:0"><b><?= e((string) $nm) ?></b> <span class="hint">@<?= e((string) $who) ?></span><br>
        <span class="hint"><?= e(date('M j', strtotime((string) $s['date_from']))) ?> to <?= e(date('M j, Y', strtotime((string) $s['date_to']))) ?>
          &middot; <?= (int) $s['guests'] ?> <?= (int) $s['guests'] === 1 ? 'guest' : 'guests' ?></span></div>
      <span class="hl-badge<?= in_array($s['state'], ['accepted', 'confirmed'], true) ? ' hl-badge-ok' : '' ?>"><?= e(RMT_STAY_STATE_LABELS[$s['state']] ?? $s['state']) ?></span>
    </div></a></li>
<?php }; ?>
<div class="wrap hl-sec" style="max-width:760px">
  <p class="crumbs"><a href="<?= e(url('hosts-locals')) ?>">Hosts &amp; Locals</a> / Your stays</p>
  <h1 class="hl-h2" style="font-size:1.8rem">Your stays</h1>
  <h2 class="hl-h2" style="font-size:1.2rem;margin-top:18px">Requests to stay with you</h2>
  <?php if ($in): ?><ul class="list-plain"><?php foreach ($in as $s) $row($s, true); ?></ul>
  <?php else: ?><p class="muted">None. <a href="<?= e(url('hosts-locals/me#host')) ?>">Your hosting settings</a>.</p><?php endif; ?>
  <h2 class="hl-h2" style="font-size:1.2rem;margin-top:18px">Stays you asked for</h2>
  <?php if ($out): ?><ul class="list-plain"><?php foreach ($out as $s) $row($s, false); ?></ul>
  <?php else: ?><p class="muted">None yet. <a href="<?= e(url('hosts-locals/destinations')) ?>">Find a host</a>.</p><?php endif; ?>
</div>
